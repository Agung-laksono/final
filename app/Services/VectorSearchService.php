<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;

class VectorSearchService
{
    /**
     * Get embedding from Gemini API for a given text.
     */
    public function getEmbedding(string $text): ?array
    {
        $apiKey = env('GEMINI_API_KEY');
        if (!$apiKey) {
            throw new \Exception('GEMINI_API_KEY tidak ditemukan di .env');
        }

        // Menggunakan model embedding bawaan Gemini
        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
        ])->post('https://generativelanguage.googleapis.com/v1beta/models/gemini-embedding-2:embedContent?key=' . $apiKey, [
            'model' => 'models/gemini-embedding-2',
            'content' => [
                'parts' => [
                    ['text' => $text]
                ]
            ]
        ]);

        if ($response->successful()) {
            return $response->json('embedding.values');
        }

        throw new \Exception('Gagal mendapatkan embedding: ' . $response->body());
    }

    /**
     * Get embeddings for multiple texts in a single batch request (Bypasses RPM limits).
     */
    public function getBatchEmbeddings(array $texts): array
    {
        $apiKey = env('GEMINI_API_KEY');
        if (!$apiKey) {
            throw new \Exception('GEMINI_API_KEY tidak ditemukan di .env');
        }

        $requests = [];
        foreach ($texts as $text) {
            $requests[] = [
                'model' => 'models/gemini-embedding-2',
                'content' => [
                    'parts' => [
                        ['text' => $text]
                    ]
                ]
            ];
        }

        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
        ])->post('https://generativelanguage.googleapis.com/v1beta/models/gemini-embedding-2:batchEmbedContents?key=' . $apiKey, [
            'requests' => $requests
        ]);

        if ($response->successful()) {
            $embeddings = [];
            $results = $response->json('embeddings');
            foreach ($results as $res) {
                $embeddings[] = $res['values'];
            }
            return $embeddings;
        }

        throw new \Exception('Gagal mendapatkan batch embedding: ' . $response->body());
    }

    /**
     * Calculate cosine similarity between two vectors.
     */
    public function cosineSimilarity(array $vec1, array $vec2): float
    {
        $dotProduct = 0;
        $normA = 0;
        $normB = 0;

        $count = min(count($vec1), count($vec2));
        for ($i = 0; $i < $count; $i++) {
            $dotProduct += $vec1[$i] * $vec2[$i];
            $normA += $vec1[$i] * $vec1[$i];
            $normB += $vec2[$i] * $vec2[$i];
        }

        if ($normA == 0 || $normB == 0) {
            return 0;
        }

        return $dotProduct / (sqrt($normA) * sqrt($normB));
    }

    /**
     * Search the most relevant rows across ALL indexed models in ai_knowledge_bases.
     */
    public function search(string $query, int $limit = 5): array
    {
        // 1. Ubah pertanyaan (query) menjadi vektor
        $queryVector = $this->getEmbedding($query);
        if (!$queryVector) {
            return [];
        }

        // 2. Tarik semua data dari tabel ai_knowledge_bases
        // Catatan: Ini aman untuk SQLite tabel kecil-menengah (< 20.000 baris)
        $rows = DB::table('ai_knowledge_bases')->whereNotNull('embedding')->get(['id', 'model_type', 'model_id', 'content_text', 'embedding']);

        $results = [];

        // 3. Hitung cosine similarity di memory
        foreach ($rows as $row) {
            $rowVector = json_decode($row->embedding, true);
            
            if (is_array($rowVector)) {
                $similarity = $this->cosineSimilarity($queryVector, $rowVector);
                
                // Tambahkan score similarity ke data
                $rowArray = (array) $row;
                // Kita tidak perlu mengembalikan vektor penuhnya
                unset($rowArray['embedding']);
                $rowArray['_similarity'] = $similarity;
                $results[] = $rowArray;
            }
        }

        // 4. Urutkan berdasarkan similarity tertinggi (paling mirip)
        usort($results, function($a, $b) {
            return $b['_similarity'] <=> $a['_similarity'];
        });

        // 5. Ambil Top N (limit)
        return array_slice($results, 0, $limit);
    }

    /**
     * Generate chat completion using Gemini 1.5 Flash.
     */
    public function generateChatCompletion(string $systemPrompt, array $historyMessages): string
    {
        $apiKey = env('GEMINI_API_KEY');
        if (!$apiKey) {
            throw new \Exception('GEMINI_API_KEY tidak ditemukan di .env');
        }

        $payload = [
            'systemInstruction' => [
                'parts' => [
                    ['text' => $systemPrompt]
                ]
            ],
            'contents' => $historyMessages
        ];

        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
        ])->post('https://generativelanguage.googleapis.com/v1beta/models/gemini-3.5-flash:generateContent?key=' . $apiKey, $payload);

        if ($response->successful()) {
            $data = $response->json();
            if (isset($data['candidates'][0]['content']['parts'][0]['text'])) {
                return $data['candidates'][0]['content']['parts'][0]['text'];
            }
        }

        throw new \Exception('Gagal melakukan generate chat: ' . $response->body());
    }
}
