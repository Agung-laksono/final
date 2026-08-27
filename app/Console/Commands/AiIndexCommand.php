<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\AiKnowledgeBase;
use App\Services\VectorSearchService;
use App\Services\KnowledgeFormatter;

class AiIndexCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ai:index {model_class=all}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Index ERP Eloquent Models into the AI Knowledge Base with rich relation formatting';

    /**
     * Default list of ERP models to index when "all" is specified.
     */
    protected array $defaultModels = [
        \Modules\Inventory\Models\Item::class,
        \Modules\Inventory\Models\Category::class,
        \Modules\Inventory\Models\SubCategory::class,
        \Modules\Inventory\Models\Type::class,
        \Modules\Inventory\Models\Unit::class,
        \Modules\Inventory\Models\Warehouse::class,
        \App\Models\Brand::class,
        \Modules\Sales\Models\SalesOrder::class,
        \Modules\Sales\Models\Customer::class,
        \Modules\Sales\Models\SalesPayment::class,
        \Modules\Purchase\Models\Vendor::class,
        \Modules\Purchase\Models\PurchaseOrder::class,
        \Modules\Purchase\Models\PurchasePayment::class,
        \Modules\Finance\Models\FinanceTransaction::class,
        \Modules\Finance\Models\FinanceAccount::class,
        \Modules\Production\Models\ProductionOrder::class,
    ];

    /**
     * Execute the console command.
     */
    public function handle(VectorSearchService $vectorService)
    {
        $target = $this->argument('model_class');

        if (strtolower($target) === 'all') {
            $this->info("=== MEMULAI RE-INDEX SELURUH MODEL ERP UTAMA & DATA TURUNAN ===");
            foreach ($this->defaultModels as $modelClass) {
                if (class_exists($modelClass)) {
                    $this->indexModelClass($modelClass, $vectorService);
                }
            }
            $this->info("\n=== SELESAI MENGINDEKS SELURUH DATA ERP! ===");
            return;
        }

        if (!class_exists($target)) {
            $this->error("Class {$target} tidak ditemukan.");
            return;
        }

        $this->indexModelClass($target, $vectorService);
    }

    protected function indexModelClass(string $modelClass, VectorSearchService $vectorService)
    {
        $records = $modelClass::all();
        $count = $records->count();
        $this->info("\nDitemukan {$count} data dari {$modelClass}. Mengolah dengan KnowledgeFormatter...");

        if ($count === 0) {
            return;
        }

        $chunks = $records->chunk(20);
        $bar = $this->output->createProgressBar($count);
        $bar->start();

        foreach ($chunks as $chunk) {
            $texts = [];
            $recordsInChunk = [];
            
            // Format setiap record menjadi teks naratif kaya relasi
            foreach ($chunk as $record) {
                $texts[] = KnowledgeFormatter::format($record);
                $recordsInChunk[] = $record;
            }

            // Coba dapatkan batch embedding jika API tersedia
            $embeddings = [];
            try {
                $embeddings = $vectorService->getBatchEmbeddings($texts);
            } catch (\Exception $e) {
                // Jika API embedding error / rate limit / permission denied,
                // tetap lanjutkan simpan content_text agar Pencarian Kata Kunci SQL tetap 100% bekerja!
            }

            // Simpan/update setiap record ke tabel ai_knowledge_bases
            foreach ($recordsInChunk as $idx => $record) {
                $formattedText = $texts[$idx];
                $embeddingData = $embeddings[$idx] ?? [];

                AiKnowledgeBase::updateOrCreate(
                    [
                        'model_type' => $modelClass,
                        'model_id' => $record->id,
                    ],
                    [
                        'content_text' => $formattedText,
                        'embedding' => json_encode($embeddingData),
                    ]
                );

                $bar->advance();
            }
        }

        $bar->finish();
        $this->info("\nSelesai indeks {$modelClass}!");
    }
}
