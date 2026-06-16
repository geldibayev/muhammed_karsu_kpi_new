<?php

namespace App\Http\Controllers;

use App\Models\Criterion;
use App\Models\Datum;
use App\Models\Language;
use App\Models\Year;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Gemini\Laravel\Facades\Gemini;
use Gemini\Data\Blob;
use Gemini\Enums\MimeType;

class DatumController extends Controller
{
    public function show(Criterion $upload)
    {
        if ($upload->upload != '1') return redirect()->route('home')
            ->with('error', 'Ruxsat etilmagan sahifa.');
        $years = Year::where('status', '1')->get();
        $breadcrumbs = [
            [
                'url' => route('home'),
                'name' => 'Asosiy sahifa'
            ],
            [
                'url' => '#',
                'name' => mb_substr($upload->name['uz'], 0, 30, 'UTF-8') . '...'
            ]
        ];
        $languages = Language::all();
        return view('pages.users.upload.index', compact(['upload', 'years', 'languages', 'breadcrumbs']));
    }

    public function update(Request $request, Criterion $upload)
    {
        $request->validate([
            'uploadResourceType' => 'required|in:file,url',
            'year' => 'required|exists:years,id',
            'uploadResourceFile' => 'nullable|required_if:uploadResourceType,file|file|mimes:pdf,jpg,png|max:2048',
            'uploadResourceUrl' => 'nullable|required_if:uploadResourceType,url|url|max:255',
        ]);
        $existingFilesCount = Datum::where('criterion_id', $upload->id)->where('user_id', auth()->id())->count();
        if ($upload->file_limit > 0 && $existingFilesCount >= $upload->file_limit) {
            return back()->with('error', 'Fayl yuklash chegarasidan oshib ketdingiz!');
        }
        $materialData = [];
        $fileMimeType = null;
        $fileBase64 = null;
        if ($request->uploadResourceType === 'file') {
            if ($request->hasFile('uploadResourceFile')) {
                $file = $request->file('uploadResourceFile');
                $folder = 'uploads/kpi_resources/' . date('Y/m');
                $filePath = $file->store($folder, 'public');
                $materialData = [
                    'type' => 'file',
                    'path' => $filePath,
                    'original_name' => $file->getClientOriginalName(),
                    'extension' => $file->getClientOriginalExtension()
                ];
                $fileBase64 = base64_encode(file_get_contents($file->getRealPath()));
                $fileMimeType = $file->getClientMimeType();
            }
        } else {
            $materialData = [
                'type' => 'url',
                'link' => $request->uploadResourceUrl
            ];
        }
        if ($request->has('article') && is_array($request->input('article'))) {
            $materialData['article'] = array_filter($request->input('article'));
        }
        if ($upload->checking == 'ai') {
            $gemini_data = [
                'status' => 'checking',
                'point' => 0,
                'reason' => 'AI tomonidan to‘liq tahlil qilinmadi'
            ];
            $full_name = auth()->user()->full;
            $me_point = $upload->criterionEvaluation($upload->id, auth()->user()->degree)->score;
            $ai_prompt = $upload->ai_prompt;
            $cleanPrompt = preg_replace('/[ \t]+/', ' ', $ai_prompt);
            $cleanPrompt = trim($cleanPrompt);
            $cleanPrompt = str_replace('%pointing%', $me_point, $cleanPrompt);
            $cleanPrompt .= "\nResurs muallifi tizimga quyidagicha ma’lumotlarni taqdim etgan. Resursning mualliflari ro‘yxatida resursni kiritgan shaxsning ism-familiyasi bor-yo'qligini tekshiring. DIQQAT: Ism-familiyalarni solishtirishda qat'iy (harfma-harf) moslik talab qilmang. Kichik imlo xatolari, tutuq belgisi (‘, '), chiziqcha (-), bo'shliqlar, shuningdek, lotin/kirill transliteratsiyasi farqlari (masalan, I va Y, E va YE, O' va O) sababli ismlar biroz farq qilsa ham, ularni yagona shaxs deb qabul qiling. Faqatgina ism-familiya butunlay boshqa odamga tegishli ekanligi yaqqol tasdiqlansagina 'cancelled' statusini qaytaring:\n\n";
            $cleanPrompt .= "Muallifning to‘liq ismi: {$full_name};\n";
            if (isset($materialData['article']['name'])) $cleanPrompt .= "Resurs nomi: «{$materialData['article']['name']}»;\n";
            if (isset($materialData['article']['keywords'])) $cleanPrompt .= "Kalit so‘zlar: «{$materialData['article']['keywords']}»;\n";
            if (isset($materialData['article']['authors_num'])) $cleanPrompt .= "Mualliflar soni: «{$materialData['article']['authors_num']}»;\n";
            if (isset($materialData['article']['authors'])) $cleanPrompt .= "Mualliflar: «{$materialData['article']['authors']}»;\n";
            if (isset($materialData['article']['lang'])) $cleanPrompt .= "Resurs tili: «{$materialData['article']['lang']}»;\n";
            if (isset($materialData['article']['doi'])) $cleanPrompt .= "DOI: «{$materialData['article']['doi']}»;\n";
            if (isset($materialData['article']['journal'])) $cleanPrompt .= "Nashriyot: «{$materialData['article']['journal']}»;\n";
            if (isset($materialData['article']['params'])) $cleanPrompt .= "Nashr parametrlari: «{$materialData['article']['params']}»;\n";

            if ($upload->ai_prompt && $upload->ai_model) {
                $apiKey = env('GEMINI_API_KEY');
                $modelName = $upload->ai_model;
                $client = \Gemini::factory()->withApiKey($apiKey)->make();
                $contentParts = [
                    $cleanPrompt,
                ];
                if ($request->uploadResourceType === 'file' && $fileBase64 !== null) {
                    $mime = match ($fileMimeType) {
                        'image/jpeg', 'image/jpg' => MimeType::IMAGE_JPEG,
                        'image/png' => MimeType::IMAGE_PNG,
                        default => MimeType::APPLICATION_PDF,
                    };
                    $contentParts[] = new Blob(mimeType: $mime, data: $fileBase64);
                } elseif ($request->uploadResourceType === 'url' && $request->filled('uploadResourceUrl')) {
                    $contentParts[] = "Tahlil qilish uchun taqdim etilgan havola: " . $request->uploadResourceUrl;
                }
                try {
                    $result = $client->generativeModel($modelName)->generateContent($contentParts);
                    $responseText = $result->text();
                    $responseText = str_replace(['```json', '```'], '', $responseText);
                    $responseText = trim($responseText);
                    preg_match('/\{[\s\S]*\}/', $responseText, $matches);
                    $jsonString = $matches[0] ?? '{}';
                    $parsedData = json_decode($jsonString, true);
                    if (is_array($parsedData) && isset($parsedData['status'])) {
                        $gemini_data = $parsedData;
                    }
                } catch (\Exception $e) {
                    Log::error('Gemini API xatoligi: ' . $e->getMessage(), [
                        'file' => $e->getFile(),
                        //'line' => $e->Line(),
                        'upload_id' => $upload->id,
                        'user_id' => auth()->id()
                    ]);

                    $gemini_data['reason'] = 'AI tahlilida xatolik yuz berdi. Iltimos, administrator ko‘rib chiqsin.';
                }
            }
        }
        Datum::create([
            'user_id' => auth()->id(),
            'criterion_id' => $upload->id,
            'year_id' => $request->year,
            'material' => $materialData,
            'status' => $gemini_data['status'] ?? 'received',
            'point' => $gemini_data['point'] ?? 0,
            'reason' => $gemini_data['reason'] ?? '',
            'name' => $request->uploadResourceType === 'file' && $request->hasFile('uploadResourceFile')
                ? $request->file('uploadResourceFile')->getClientOriginalName()
                : 'URL Havola',
        ]);

        return redirect()->route('upload.show', $upload->id)->with('success', 'Resurs muvaffaqiyatli yuklandi va AI tekshiruvidan o‘tdi.');
    }

    public function download($id)
    {
        $file = Datum::findOrFail($id);
        if ($file->material['type']) {
            //dd($file->material);
            $filePath = storage_path('app/public/' . $file->material['path']);
            if (file_exists($filePath)) {
                return response()->download($filePath, $file->name);
            }
        }
        return back()->with('error', 'Fayl topilmadi!');
    }
}
