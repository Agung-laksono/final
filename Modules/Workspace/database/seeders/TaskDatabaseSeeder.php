<?php

namespace Modules\Workspace\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Workspace\Models\Task;
use Modules\Workspace\Models\TaskSubtask;

class TaskDatabaseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $projects = [
            [
                'title' => 'Promo Cuci Gudang Akhir Tahun',
                'description' => 'Kampanye besar-besaran di semua platform untuk menghabiskan stok lama menjelang pergantian tahun.',
                'platform' => 'Instagram Reels',
                'status' => 'ide_backlog',
                'budget' => 'Rp 0',
                'date' => '2026-11-01',
                'subtasks' => [
                    ['title' => 'Tentukan list produk cuci gudang', 'is_completed' => true],
                    ['title' => 'Buat script video reels', 'is_completed' => false],
                    ['title' => 'Cari referensi lagu trending', 'is_completed' => false],
                ]
            ],
            [
                'title' => 'Kolaborasi Influencer X',
                'description' => 'Kerjasama dengan micro-influencer di niche gaya hidup untuk mempromosikan produk unggulan.',
                'platform' => 'TikTok',
                'status' => 'ide_backlog',
                'budget' => 'Rp 5.000.000',
                'date' => '2026-10-15',
                'subtasks' => [
                    ['title' => 'Riset 10 kandidat influencer', 'is_completed' => true],
                    ['title' => 'Kontak dan minta ratecard', 'is_completed' => true],
                    ['title' => 'Tanda tangan kontrak dengan 2 influencer', 'is_completed' => false],
                ]
            ],
            [
                'title' => 'Video Unboxing Produk Baru',
                'description' => 'Video detail unboxing produk terbaru untuk channel YouTube official.',
                'platform' => 'YouTube',
                'status' => 'shooting',
                'budget' => 'Rp 1.000.000',
                'date' => '2026-09-20',
                'subtasks' => [
                    ['title' => 'Siapkan properti studio', 'is_completed' => true],
                    ['title' => 'Take video A-roll (bicara)', 'is_completed' => true],
                    ['title' => 'Take video B-roll (detail produk)', 'is_completed' => false],
                ]
            ],
            [
                'title' => 'Desain Banner Promo 10.10',
                'description' => 'Desain berbagai ukuran banner untuk kebutuhan Facebook dan Instagram Ads promo puncak 10.10.',
                'platform' => 'Facebook Ads',
                'status' => 'editing',
                'budget' => 'Rp 2.000.000',
                'date' => '2026-10-01',
                'subtasks' => [
                    ['title' => 'Kumpulkan aset foto produk', 'is_completed' => true],
                    ['title' => 'Draft desain awal', 'is_completed' => true],
                    ['title' => 'Revisi warna dan tipografi', 'is_completed' => false],
                ]
            ],
            [
                'title' => 'Email Newsletter September',
                'description' => 'Newsletter bulanan yang berisi rekap artikel terbaik dan promo eksklusif subscriber.',
                'platform' => 'Email Marketing',
                'status' => 'ready',
                'budget' => 'Rp 0',
                'date' => '2026-09-25',
                'subtasks' => [
                    ['title' => 'Tulis copywriting', 'is_completed' => true],
                    ['title' => 'Buat desain template email', 'is_completed' => true],
                    ['title' => 'Setup automation di Mailchimp', 'is_completed' => true],
                ]
            ],
            [
                'title' => 'Flash Sale Kemerdekaan',
                'description' => 'Kampanye flash sale 17 Agustus di website utama.',
                'platform' => 'Website',
                'status' => 'analytic',
                'budget' => 'Rp 0',
                'date' => '2026-08-17',
                'subtasks' => [
                    ['title' => 'Ubah banner hero website', 'is_completed' => true],
                    ['title' => 'Setup diskon di database', 'is_completed' => true],
                    ['title' => 'Pantau trafik server', 'is_completed' => true],
                ]
            ],
            [
                'title' => 'Posting Konten Kolaborasi X',
                'description' => 'Mempublikasikan hasil video kolaborasi dengan influencer ke berbagai platform sosial media official.',
                'platform' => 'Multi-Platform',
                'status' => 'publish',
                'budget' => 'Rp 0',
                'date' => '2026-10-18',
                'subtasks' => [
                    ['title' => 'Publish di Facebook Reels', 'is_completed' => false, 'requires_input' => true],
                    ['title' => 'Publish di TikTok', 'is_completed' => false, 'requires_input' => true],
                    ['title' => 'Publish di YouTube Shorts', 'is_completed' => false, 'requires_input' => true],
                ]
            ]
        ];

        foreach ($projects as $projData) {
            $subtasks = $projData['subtasks'] ?? [];
            $dueDate = $projData['date'] ?? null;
            
            unset($projData['subtasks']);
            unset($projData['date']);
            
            $projData['due_date'] = $dueDate;
            
            $project = Task::create($projData);
            
            foreach ($subtasks as $sub) {
                $sub['task_id'] = $project->id;
                TaskSubtask::create($sub);
            }
        }
    }
}
