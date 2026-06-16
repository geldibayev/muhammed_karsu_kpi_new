<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class EvaluationController extends Controller
{
    public function handleChat(Request $request)
    {
        $messages = $request->input('messages', []);
        $category = $request->input('category', 'umumiy');
        $formattedContents = [];
        foreach ($messages as $msg) {
            $formattedContents[] = [
                'role' => $msg['role'] === 'user' ? 'user' : 'model',
                'parts' => [['text' => $msg['content']]]
            ];
        }

        $systemInstruction = "Siz Ózbekstan Respublikasındaǵı qaraqalpaq tilinde jumıs isleytuǵın «Turmıslıq xızmetler»
        (Kommunal tólemler, suw, gaz, bir ayna xızmetleri hám taǵı basqalar) boyınsha rásmiy járdem beriwshi bot bolasız.
        Klientlerge mulayım, qaraqalpaq tilinde anıq hám qısqa juwap beriwge háreket etiń. Húrmet retinde hár bir
        sózińiz «siz» kontakt sózi menen bolsın. Paydalanıwshı házirde tómendegi bólim boyınsha maǵlıwmat soramaqshı: " . $category;
        $apiKey = env('GEMINI_API_KEY');
        $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key={$apiKey}";
        $response = Http::post($url, [
            'system_instruction' => [
                'parts' => [['text' => $systemInstruction]]
            ],
            'contents' => $formattedContents,
            'generationConfig' => [
                'temperature' => 0.6,
            ]
        ]);

        if ($response->successful()) {
            $reply = $response->json('candidates.0.content.parts.0.text');
            return response()->json(['reply' => $reply]);
        }

        return response()->json(['reply' => 'Uzur, tizimda nosozlik yuz berdi. Iltimos keyinroq qayta urinib ko\'ring.'], 500);
    }
}
