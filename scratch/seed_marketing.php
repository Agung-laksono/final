<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Modules\Workspace\Models\Workspace;
use Modules\Workspace\Models\Objective;
use Modules\Workspace\Models\KeyResult;
use Modules\Workspace\Models\Kpi;
use Modules\Workspace\Models\Task;
use App\Models\User;

try {
    // 1. Get or Create Admin User
    $user = User::first() ?? User::factory()->create(['name' => 'Admin Marketing']);

    // 2. Get or Create "MARKETING" Workspace
    $workspace = Workspace::firstOrCreate(
        ['name' => 'MARKETING'],
        [
            'description' => 'Workspace untuk tim Digital Marketing (FB, IG, TikTok, YT Shorts)',
            'owner_id' => $user->id
        ]
    );

    // Sync user as admin to workspace
    $workspace->users()->syncWithoutDetaching([$user->id => ['role' => 'admin']]);

    // 3. Setup Columns
    if ($workspace->columns()->count() === 0) {
        $workspace->columns()->createMany([
            ['title' => 'Ide / Backlog', 'color' => 'gray', 'position' => 1],
            ['title' => 'In Progress', 'color' => 'blue', 'position' => 2],
            ['title' => 'Review', 'color' => 'yellow', 'position' => 3],
            ['title' => 'Done', 'color' => 'green', 'position' => 4],
        ]);
    }
    
    $cols = $workspace->columns()->orderBy('position')->get();
    $colIde = $cols[0]->id;
    $colProgress = $cols[1]->id;
    $colReview = $cols[2]->id;
    $colDone = $cols[3]->id;

    // 4. Create Objective
    $objective = Objective::firstOrCreate(
        ['workspace_id' => $workspace->id, 'title' => 'Mendominasi Sosial Media Q4 2026'],
        [
            'owner_id' => $user->id,
            'description' => 'Meningkatkan brand awareness secara masif melalui konten video pendek di TikTok, Instagram Reels, dan YouTube Shorts.',
            'period' => 'Q4',
            'year' => 2026,
            'status' => 'on_track'
        ]
    );

    // 5. Create Key Results
    $kr1 = KeyResult::firstOrCreate(
        ['objective_id' => $objective->id, 'title' => 'Mencapai 1 Juta Views Organik Video Pendek'],
        [
            'owner_id' => $user->id,
            'description' => 'Total views gabungan dari TikTok, IG Reels, dan YT Shorts.',
            'type' => 'numeric',
            'start_value' => 0,
            'current_value' => 250000,
            'target_value' => 1000000,
            'unit' => 'views'
        ]
    );

    $kr2 = KeyResult::firstOrCreate(
        ['objective_id' => $objective->id, 'title' => 'Mendapatkan 10.000 Followers Baru'],
        [
            'owner_id' => $user->id,
            'description' => 'Fokus pada akuisisi audiens di Instagram dan TikTok.',
            'type' => 'numeric',
            'start_value' => 0,
            'current_value' => 3000,
            'target_value' => 10000,
            'unit' => 'followers'
        ]
    );
    
    // 6. Create KPIs
    Kpi::firstOrCreate(
        ['workspace_id' => $workspace->id, 'title' => 'Video Publish Rate (Mingguan)'],
        [
            'owner_id' => $user->id,
            'key_result_id' => $kr1->id,
            'description' => 'Jumlah video yang dipublish per minggu di semua platform.',
            'period' => 'weekly',
            'target_value' => 15,
            'current_value' => 8,
            'unit' => 'video',
            'formula' => 'Total video ter-publish minggu ini'
        ]
    );

    Kpi::firstOrCreate(
        ['workspace_id' => $workspace->id, 'title' => 'Average Engagement Rate'],
        [
            'owner_id' => $user->id,
            'key_result_id' => $kr2->id,
            'description' => 'Tingkat interaksi (like, komen, share) per konten.',
            'period' => 'monthly',
            'target_value' => 5,
            'current_value' => 3.2,
            'unit' => '%',
            'formula' => '(Total Interaksi / Total Views) * 100'
        ]
    );

    // 7. Create Kanban Tasks & Link to KRs
    $tasks = [
        [
            'title' => 'Riset Tren Audio TikTok & IG Reels untuk Oktober',
            'description' => 'Cari 10 audio yang sedang tren untuk digunakan dalam konten minggu depan.',
            'workspace_column_id' => $colIde,
            'kr_id' => $kr1->id,
            'contribution' => 0 // Riset belum nambah views
        ],
        [
            'title' => 'Shooting Video Campaign "Promo Diskon Q4"',
            'description' => 'Rekam 5 versi video untuk dipecah ke FB Ads dan organik.',
            'workspace_column_id' => $colProgress,
            'kr_id' => $kr1->id,
            'contribution' => 50000 // Potensi nambah 50k views kalau done
        ],
        [
            'title' => 'Editing & Animasi 3 Video YT Shorts',
            'description' => 'Tambahkan hook visual di 3 detik pertama.',
            'workspace_column_id' => $colReview,
            'kr_id' => $kr1->id,
            'contribution' => 30000
        ],
        [
            'title' => 'Optimasi Bio Instagram & Linktree',
            'description' => 'Ubah bio agar lebih menarik dan fokus pada konversi followers.',
            'workspace_column_id' => $colDone,
            'kr_id' => $kr2->id,
            'contribution' => 500 // Done, menyumbang nilai progress
        ],
        [
            'title' => 'Jalankan FB Ads Traffic & Page Likes',
            'description' => 'Budget Rp 50.000 per hari untuk mendapatkan audience baru di FB.',
            'workspace_column_id' => $colProgress,
            'kr_id' => $kr2->id,
            'contribution' => 2000
        ],
        [
            'title' => 'Kolaborasi (Live) dengan Influencer Lokal',
            'description' => 'Live bareng di Instagram selama 1 jam.',
            'workspace_column_id' => $colIde,
            'kr_id' => $kr2->id,
            'contribution' => 1500
        ],
    ];

    $pos = 0;
    foreach ($tasks as $taskData) {
        $task = Task::firstOrCreate(
            ['workspace_id' => $workspace->id, 'title' => $taskData['title']],
            [
                'description' => $taskData['description'],
                'workspace_column_id' => $taskData['workspace_column_id'],
                'position' => $pos++
            ]
        );

        // Link Task to Key Result with contribution
        if (!$task->keyResults->contains($taskData['kr_id'])) {
            $task->keyResults()->attach($taskData['kr_id'], ['contribution' => $taskData['contribution']]);
        }
    }

    echo "Sample data untuk Workspace MARKETING berhasil di-generate!\n";
    echo "Objective, Key Results, KPIs, dan Tasks sudah terhubung.\n";
    echo "Silakan cek di aplikasi (Workspace ID: " . $workspace->id . ").\n";

} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
