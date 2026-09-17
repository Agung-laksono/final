<?php

namespace App\Traits;

use App\Models\AiKnowledgeBase;
use App\Services\KnowledgeFormatter;
use App\Services\VectorSearchService;
use Illuminate\Support\Facades\Log;

trait SearchableAiKnowledge
{
    /**
     * Boot the trait to listen for Eloquent saved & deleted events.
     */
    public static function bootSearchableAiKnowledge(): void
    {
        static::saved(function ($model) {
            static::syncToAiKnowledgeBase($model);
        });

        static::deleted(function ($model) {
            static::removeFromAiKnowledgeBase($model);
        });
    }

    /**
     * Sync current model instance to ai_knowledge_bases table with rich text and embedding.
     */
    public static function syncToAiKnowledgeBase($model): void
    {
        try {
            $chunks = \App\Services\KnowledgeChunker::chunk($model);
            $vectorService = app(VectorSearchService::class);

            foreach ($chunks as $chunk) {
                $contentText = $chunk['content_text'];
                $embeddingVector = [];
                try {
                    $embeddingVector = $vectorService->getEmbedding($contentText) ?? [];
                } catch (\Exception $ex) {
                    // If embedding fails, keep content_text so SQL keyword search still works 100%!
                }

                AiKnowledgeBase::updateOrCreate(
                    [
                        'model_type' => get_class($model),
                        'model_id' => $model->id,
                        'chunk_index' => $chunk['chunk_index'],
                    ],
                    [
                        'chunk_type' => $chunk['chunk_type'],
                        'content_text' => $contentText,
                        'embedding' => json_encode($embeddingVector),
                    ]
                );
            }
        } catch (\Exception $e) {
            Log::warning("Gagal auto-index RAG untuk " . get_class($model) . " ID {$model->id}: " . $e->getMessage());
        }
    }

    /**
     * Remove model record from ai_knowledge_bases on delete.
     */
    public static function removeFromAiKnowledgeBase($model): void
    {
        try {
            AiKnowledgeBase::where('model_type', get_class($model))
                ->where('model_id', $model->id)
                ->delete();
        } catch (\Exception $e) {
            Log::warning("Gagal hapus RAG untuk " . get_class($model) . " ID {$model->id}: " . $e->getMessage());
        }
    }
}
