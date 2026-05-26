<?php

namespace App\Http\Controllers;

use App\Models\Criterion;
use App\Models\Datum;
use App\Models\Language;
use App\Models\Year;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
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
        $years = Year::all();
        $languages = Language::all();
        return view('pages.users.upload.index', compact(['upload', 'years', 'languages']));
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
        $filePath = null;
        $fileMimeType = null;
        $fileBase64 = null;

        // 1. Faylni yuklash va o'qish
        if ($request->uploadResourceType === 'file') {
            if ($request->hasFile('uploadResourceFile')) {
                $file = $request->file('uploadResourceFile');
                $folder = 'uploads/kpi_resources/' . date('Y/m');
                $filePath = $file->store($folder, 'public');

                $materialData = [
                    'type' => 'file',
                    'path' => $filePath,
                    'original_name' => $file->getClientOriginalName(),
                    'size' => $file->getSize(),
                    'extension' => $file->getClientOriginalExtension()
                ];

                // AI uchun faylni Base64 formatiga o'tkazish
                $fileBase64 = base64_encode(file_get_contents($file->getRealPath()));
                $fileMimeType = $file->getClientMimeType();
            }
        } else {
            $materialData = [
                'type' => 'url',
                'link' => $request->uploadResourceUrl
            ];
        }

        if ($request->has('data') && is_array($request->input('data'))) {
            $materialData['data'] = array_filter($request->input('data'));
        }

        $gemini_data = [
            'status' => 'checking',
            'point' => 0,
            'reason' => 'AI tomonidan to\'liq tahlil qilinmadi'
        ]; // Kutilmagan xatoliklar uchun default (standart) qiymatlar

        if ($upload->ai_prompt && $upload->ai_model) {
            $apiKey = env('GEMINI_API_KEY');
            $modelName = $upload->ai_model;

            $client = \Gemini::factory()->withApiKey($apiKey)->make();

            // 1. AI ga yuboriladigan asosiy ma'lumotlar massivini (array) tayyorlab olamiz
            $contentParts = [
                str_replace('%pointing%', $upload->criterionEvaluation($upload->id, 'no_degrees')->score, $upload->ai_prompt),
            ];

            // 2. Agar FAYL yuklangan bo'lsa
            if ($request->uploadResourceType === 'file' && $fileBase64 !== null) {
                $mime = match ($fileMimeType) {
                    'image/jpeg', 'image/jpg' => MimeType::IMAGE_JPEG,
                    'image/png' => MimeType::IMAGE_PNG,
                    default => MimeType::APPLICATION_PDF,
                };
                // Faylni Blob ko'rinishida massivga qo'shamiz
                $contentParts[] = new Blob(mimeType: $mime, data: $fileBase64);
            } // 3. Agar URL havola yuborilgan bo'lsa
            elseif ($request->uploadResourceType === 'url' && $request->filled('uploadResourceUrl')) {
                // Havolani oddiy matn ko'rinishida massivga qo'shamiz
                $contentParts[] = "Tahlil qilish uchun taqdim etilgan havola: " . $request->uploadResourceUrl;
            }

            try {
                // 4. Tayyor bo'lgan dinamik massivni modelga yuboramiz
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
                // Agar API da xatolik bo'lsa yoki JSON noto'g'ri qaytsa, checking holatiga o'tadi
                $gemini_data['reason'] = 'AI tahlilida xatolik yuz berdi. Iltimos, administrator ko\'rib chiqsin.';
            }
        }

        Datum::create([
            'user_id' => auth()->id(),
            'criterion_id' => $upload->id,
            'year_id' => $request->year,
            'material' => $materialData,
            'status' => $gemini_data['status'] ?? 'checking',
            'point' => $gemini_data['point'] ?? 0,
            'reason' => $gemini_data['reason'] ?? '',
            'name' => $request->uploadResourceType === 'file' && $request->hasFile('uploadResourceFile')
                ? $request->file('uploadResourceFile')->getClientOriginalName()
                : 'URL Havola',
        ]);

        return redirect()->route('upload.show', $upload->id)->with('success', 'Resurs muvaffaqiyatli yuklandi va AI tekshiruvidan o\'tdi.');
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
