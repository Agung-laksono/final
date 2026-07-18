<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\AiKnowledgeBase;
use App\Services\VectorSearchService;

class AiIndexCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ai:index {model_class}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Index all records of a specific Eloquent Model into the AI Knowledge Base';

    /**
     * Execute the console command.
     */
    public function handle(VectorSearchService $vectorService)
    {
        $modelClass = $this->argument('model_class');

        if (!class_exists($modelClass)) {
            $this->error("Class {$modelClass} tidak ditemukan.");
            return;
        }

        $records = $modelClass::all();
        $this->info("Ditemukan {$records->count()} data dari {$modelClass}. Mulai proses embedding (Batch Mode)...");

        $chunks = $records->chunk(20);
        $bar = $this->output->createProgressBar(count($records));
        $bar->start();

        foreach ($chunks as $chunk) {
            $texts = [];
            
            // Siapkan teks dari setiap record dalam chunk
            foreach ($chunk as $record) {
                $texts[] = json_encode($record->toArray());
            }

            try {
                // Tarik batch embedding (mengirim maks 20 data dalam 1 HTTP Request)
                $embeddings = $vectorService->getBatchEmbeddings($texts);

                // Simpan setiap embedding ke database
                $embIndex = 0;
                foreach ($chunk as $record) {
                    AiKnowledgeBase::updateOrCreate(
                        [
                            'model_type' => $modelClass,
                            'model_id' => $record->id,
                        ],
                        [
                            'content_text' => json_encode($record->toArray()),
                            'embedding' => json_encode($embeddings[$embIndex] ?? []),
                        ]
                    );
                    $embIndex++;
                    $bar->advance();
                }
                
                // API Gemini gratis memiliki limit 100 request/menit untuk model embedding.
                // 1 item di dalam array dianggap 1 request.
                // Kita kirim 20 data per batch, lalu jeda 12 detik.
                // (20 data / 12 detik) = (100 data / 60 detik). Ini akan mengamankan limit Anda!
                sleep(12);
                
            } catch (\Exception $e) {
                $this->error("\nError pada batch: " . $e->getMessage());
            }
        }

        $bar->finish();
        $this->info("\nSelesai melakukan indexing pada {$modelClass}!");
    }
}
