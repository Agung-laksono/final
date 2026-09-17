<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class VectorSearchService
{
    /**
     * Resolve Gemini API Key strictly from Database Settings
     */
    public function getApiKey(): ?string
    {
        $aiProvidersJson = \App\Models\Setting::where('key', 'ai_providers')->value('value');
        if ($aiProvidersJson) {
            $providers = json_decode($aiProvidersJson, true) ?? [];
            foreach ($providers as $p) {
                if (strtolower(trim($p['name'] ?? '')) === 'gemini' && !empty(trim($p['key'] ?? ''))) {
                    return trim($p['key']);
                }
            }
        }
        return null;
    }

    /**
     * Get embedding from Gemini API for a given text.
     */
    public function getEmbedding(string $text): ?array
    {
        $apiKey = $this->getApiKey();
        if (!$apiKey) {
            throw new \Exception('API Key Gemini belum diisi. Silakan atur API Key pada menu Pengaturan > Integrasi.');
        }

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

        // Fallback ke text-embedding-004 jika gemini-embedding-2 mengalami kegagalan
        $fallback = Http::withHeaders([
            'Content-Type' => 'application/json',
        ])->post('https://generativelanguage.googleapis.com/v1beta/models/text-embedding-004:embedContent?key=' . $apiKey, [
            'model' => 'models/text-embedding-004',
            'content' => [
                'parts' => [
                    ['text' => $text]
                ]
            ]
        ]);

        if ($fallback->successful()) {
            return $fallback->json('embedding.values');
        }

        throw new \Exception('Gagal mendapatkan embedding: ' . $response->body());
    }

    /**
     * Get embeddings for multiple texts in a single batch request (Bypasses RPM limits).
     */
    public function getBatchEmbeddings(array $texts): array
    {
        $apiKey = $this->getApiKey();
        if (!$apiKey) {
            throw new \Exception('API Key Gemini belum diisi. Silakan atur API Key pada menu Pengaturan > Integrasi.');
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
     * Guess relevant model_type classes based on query keywords.
     * Reduces vector search scope dari ribuan row menjadi ratusan.
     */
    protected function guessRelevantModelTypes(string $query): array
    {
        $query = strtolower($query);
        $types = [];

        $map = [
            ['keywords' => ['so', 'sales order', 'penjualan', 'jual', 'customer', 'pelanggan', 'piutang', 'quotation', 'retur'],
             'types' => ['Modules\Sales\Models\SalesOrder', 'Modules\Sales\Models\Customer', 'Modules\Sales\Models\SalesPayment', 'Modules\Sales\Models\Quotation', 'Modules\Sales\Models\SalesReturn']],
            
            ['keywords' => ['po', 'purchase', 'pembelian', 'beli', 'vendor', 'supplier', 'hutang', 'receipt'],
             'types' => ['Modules\Purchase\Models\PurchaseOrder', 'Modules\Purchase\Models\Vendor', 'Modules\Purchase\Models\PurchasePayment', 'Modules\Purchase\Models\PurchaseReceipt', 'Modules\Purchase\Models\PurchaseReturn']],
            
            ['keywords' => ['stok', 'stock', 'barang', 'produk', 'item', 'gudang', 'warehouse', 'inventory', 'transfer', 'opname'],
             'types' => ['Modules\Inventory\Models\Item', 'Modules\Inventory\Models\Warehouse', 'Modules\Inventory\Models\StockAdjustment', 'Modules\Inventory\Models\StockTransfer']],
            
            ['keywords' => ['kas', 'keuangan', 'finance', 'uang', 'bayar', 'rekening', 'saldo', 'transaksi'],
             'types' => ['Modules\Finance\Models\FinanceTransaction', 'Modules\Finance\Models\FinanceAccount']],
            
            ['keywords' => ['produksi', 'production', 'manufacturing', 'resep', 'bom'],
             'types' => ['Modules\Production\Models\ProductionOrder', 'Modules\Production\Models\ProductionRecipe']],
        ];

        foreach ($map as $entry) {
            foreach ($entry['keywords'] as $kw) {
                if (str_contains($query, $kw)) {
                    $types = array_merge($types, $entry['types']);
                    break;
                }
            }
        }
        
        return array_unique($types);
    }

    /**
     * Search the most relevant rows across ALL indexed models in ai_knowledge_bases using HYBRID search.
     * Combines Cosine Similarity Vector Search with Exact Keyword SQL Matching.
     */
    public function search(string $query, int $limit = 7): array
    {
        $resultsMap = [];

        // 1. Keyword SQL Search (Pencarian Kata Kunci Presisi untuk Kode SO/Produk/Nama Customer)
        $stopwords = ['tolong', 'analisa', 'performa', 'berdasarkan', 'data', 'dan', 'atau', 'yang', 'dari', 'ke', 'di', 'pada', 'untuk', 'dengan', 'ini', 'itu', 'buatkan', 'jelaskan', 'berikan', 'identifikasi', 'secara', 'komprehensif', 'terbaru', 'selanjutnya'];
        $cleanQuery = preg_replace('/[^\w\s-]/u', ' ', strtolower($query));
        $tokens = array_filter(explode(' ', $cleanQuery), function($t) use ($stopwords) {
            $t = trim($t);
            return strlen($t) >= 4 && !in_array($t, $stopwords);
        });

        if (!empty($tokens)) {
            $kwQuery = DB::table('ai_knowledge_bases');
            $kwQuery->where(function ($q) use ($tokens) {
                foreach ($tokens as $token) {
                    $q->orWhere('content_text', 'LIKE', '%' . $token . '%');
                }
            });

            $kwRows = $kwQuery->take(20)->get(['id', 'model_type', 'model_id', 'content_text', 'embedding']);
            foreach ($kwRows as $row) {
                $rowArray = (array) $row;
                unset($rowArray['embedding']);
                $rowArray['_score'] = 0.5; // Base score for keyword match
                $rowArray['_match_type'] = 'keyword';
                $resultsMap[$row->id] = $rowArray;
            }
        }

        // 2. Vector Cosine Similarity Search
        try {
            $queryVector = $this->getEmbedding($query);
            if ($queryVector) {
                $relevantTypes = $this->guessRelevantModelTypes($query);
                $vectorQuery = DB::table('ai_knowledge_bases')->whereNotNull('embedding');
                if (!empty($relevantTypes)) {
                    $vectorQuery->whereIn('model_type', $relevantTypes);
                }
                $rows = $vectorQuery->get(['id', 'model_type', 'model_id', 'content_text', 'embedding']);
                foreach ($rows as $row) {
                    $rowVector = json_decode($row->embedding, true);
                    if (is_array($rowVector)) {
                        $similarity = $this->cosineSimilarity($queryVector, $rowVector);
                        
                        $isKeywordMatched = isset($resultsMap[$row->id]);
                        $threshold = $isKeywordMatched ? 0.35 : 0.45;
                        
                        if ($similarity >= $threshold) {
                            if (!$isKeywordMatched) {
                                $rowArray = (array) $row;
                                unset($rowArray['embedding']);
                                $rowArray['_score'] = $similarity;
                                $rowArray['_match_type'] = 'vector';
                                $resultsMap[$row->id] = $rowArray;
                            } else {
                                // Boost score if matched by both keyword AND vector similarity!
                                $resultsMap[$row->id]['_score'] = max($resultsMap[$row->id]['_score'], $similarity) + 0.35;
                                $resultsMap[$row->id]['_match_type'] = 'hybrid';
                            }
                        }
                    }
                }
            }
        } catch (\Exception $e) {
            Log::warning("Vector search fallback to keyword search: " . $e->getMessage());
        }

        // 3. Urutkan berdasarkan score tertinggi
        $results = array_values($resultsMap);
        usort($results, function ($a, $b) {
            return $b['_score'] <=> $a['_score'];
        });

        // 4. Ambil Top N (limit)
        return array_slice($results, 0, $limit);
    }

    /**
     * Generate chat completion using Gemini API.
     */
    public function generateChatCompletion(string $systemPrompt, array $historyMessages): string
    {
        $apiKey = $this->getApiKey();
        if (!$apiKey) {
            throw new \Exception('API Key Gemini belum diisi. Silakan atur API Key pada menu Pengaturan > Integrasi.');
        }

        $payload = [
            'systemInstruction' => [
                'parts' => [
                    ['text' => $systemPrompt]
                ]
            ],
            'contents' => $historyMessages
        ];

        // Coba gemini-3.6-flash
        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
        ])->post('https://generativelanguage.googleapis.com/v1beta/models/gemini-3.6-flash:generateContent?key=' . $apiKey, $payload);

        if ($response->successful()) {
            $data = $response->json();
            if (isset($data['candidates'][0]['content']['parts'][0]['text'])) {
                return $data['candidates'][0]['content']['parts'][0]['text'];
            }
        }

        // Fallback ke gemini-1.5-flash-latest
        $fallback = Http::withHeaders([
            'Content-Type' => 'application/json',
        ])->post('https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash-latest:generateContent?key=' . $apiKey, $payload);

        if ($fallback->successful()) {
            $data = $fallback->json();
            if (isset($data['candidates'][0]['content']['parts'][0]['text'])) {
                return $data['candidates'][0]['content']['parts'][0]['text'];
            }
        }

        throw new \Exception('Gagal melakukan generate chat: ' . $response->body());
    }
}
