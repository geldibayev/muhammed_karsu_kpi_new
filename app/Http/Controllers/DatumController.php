<?php

namespace App\Http\Controllers;

use App\Models\Criterion;
use App\Models\Datum;
use App\Models\Language;
use App\Models\Year;
use Illuminate\Http\Request;

class DatumController extends Controller
{
    public function show(Criterion $upload)
    {
        $years = Year::all();
        $languages = Language::all();
        return view('pages.users.upload.index', compact(['upload', 'years', 'languages']));
    }

    public function update(Request $request, Criterion $upload)
    {
        $request->validate([
            'uploadResourceType' => 'required|in:file,url',
            'year' => 'required|exists:years,id',
            'uploadResourceFile' => 'nullable|required_if:uploadResourceType,file|file|max:2048',
            'uploadResourceUrl' => 'nullable|required_if:uploadResourceType,url|url|max:255',
        ]);
        $existingFilesCount = Datum::where('criterion_id', $upload->id)
            ->where('user_id', auth()->id())->count();
        if ($upload->file_limit > 0 && $existingFilesCount >= $upload->file_limit) {
            return back()->with('error', 'Fayl yuklash chegarasidan oshib ketdingiz!');
        }
        $materialData = [];
        if ($request->uploadResourceType === 'file') {
            if ($request->hasFile('uploadResourceFile')) {
                $file = $request->file('uploadResourceFile');
                $folder = 'uploads/kpi_resources/' . date('Y/m');
                $path = $file->store($folder, 'public');
                $materialData = [
                    'type' => 'file',
                    'path' => $path,
                    'original_name' => $file->getClientOriginalName(),
                    'size' => $file->getSize(),
                    'extension' => $file->getClientOriginalExtension()
                ];
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
        //dd($materialData);
        Datum::create([
            'user_id' => auth()->id(),
            'criterion_id' => $upload->id,
            'year_id' => $request->year,
            'material' => $materialData,
            'name' => $request->uploadResourceType === 'file' ? $file->getClientOriginalName() : 'URL Havola',
        ]);

        return back()->with('success', 'Resurs muvaffaqiyatli yuklandi va tekshiruvga yuborildi.');
    }
}
