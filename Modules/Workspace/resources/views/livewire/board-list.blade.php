<div class="p-6 h-full flex flex-col bg-gray-50/50">
    <div class="flex items-center justify-between mb-8">
        <div>
            <h1 class="text-2xl font-bold text-zinc-900 dark:text-zinc-100 flex items-center gap-2">
                <flux:icon.briefcase class="w-6 h-6 text-indigo-500" />
                Workspaces
            </h1>
            <p class="text-sm text-zinc-500 dark:text-zinc-400 mt-1">Kelola project board dan tugas Anda</p>
        </div>
        <div class="flex items-center gap-3">
            <flux:button variant="subtle" icon="book-open" wire:click="$set('showGuideModal', true)">
                Panduan
            </flux:button>
            @if(auth()->user()->hasRole(['Manager', 'Super Admin']))
            <flux:button variant="primary" icon="plus" wire:click="$set('showCreateModal', true)">
                Workspace Baru
            </flux:button>
            @endif
        </div>
    </div>

    <!-- Workspace Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
        @forelse($workspaces as $workspace)
            @php
                $isOwner = $workspace->owner_id === auth()->id();
                $userRole = $workspace->users->firstWhere('id', auth()->id())?->pivot->role ?? null;
                $canManage = $isOwner || $userRole === 'admin';
            @endphp
            <div class="group relative flex flex-col bg-white dark:bg-zinc-900 rounded-2xl border {{ $workspace->is_active ? 'border-zinc-200 dark:border-zinc-800' : 'border-dashed border-zinc-300 dark:border-zinc-700 opacity-60' }} hover:shadow-xl hover:border-indigo-300 dark:hover:border-indigo-500/50 transition-all duration-300 overflow-hidden">
                
                {{-- Active indicator bar --}}
                <div class="absolute top-0 left-0 w-full h-1.5 bg-gradient-to-r from-indigo-500 to-purple-600 transform origin-left scale-x-0 group-hover:scale-x-100 transition-transform duration-300 z-10"></div>

                {{-- Action menu (top-right) --}}
                @if($canManage)
                <div class="absolute top-3 right-3 z-20" @click.stop>
                    <flux:dropdown>
                        <button class="w-8 h-8 flex items-center justify-center rounded-lg bg-white/80 dark:bg-zinc-800/80 backdrop-blur-sm border border-zinc-200/70 dark:border-zinc-700/70 text-zinc-500 hover:text-zinc-800 dark:hover:text-zinc-200 hover:bg-white dark:hover:bg-zinc-700 shadow-sm transition-all opacity-0 group-hover:opacity-100">
                            <flux:icon.ellipsis-horizontal class="w-4 h-4" />
                        </button>
                        <flux:menu class="w-48">
                            <flux:menu.item icon="pencil-square" wire:click="openEdit({{ $workspace->id }})">Edit</flux:menu.item>
                            <flux:menu.item icon="{{ $workspace->is_active ? 'pause-circle' : 'play-circle' }}" wire:click="toggleActive({{ $workspace->id }})">
                                {{ $workspace->is_active ? 'Non-aktifkan' : 'Aktifkan' }}
                            </flux:menu.item>
                            @if($isOwner)
                                <flux:menu.separator />
                                <flux:menu.item icon="trash" variant="danger" wire:click="deleteWorkspace({{ $workspace->id }})" wire:confirm="Hapus workspace '{{ $workspace->name }}' secara permanen? Semua data di dalamnya akan hilang!">
                                    Hapus
                                </flux:menu.item>
                            @endif
                        </flux:menu>
                    </flux:dropdown>
                </div>
                @endif

                {{-- Cover Image --}}
                <a href="{{ $workspace->is_active ? '/workspaces/' . $workspace->id : '#' }}" class="{{ !$workspace->is_active ? 'pointer-events-none' : '' }}">
                    @if($workspace->cover_image)
                        <div class="w-full h-32 bg-zinc-100 dark:bg-zinc-800 overflow-hidden">
                            <img src="{{ \Illuminate\Support\Facades\Storage::url($workspace->cover_image) }}" class="w-full h-full object-cover transition-transform duration-700 group-hover:scale-105" alt="{{ $workspace->name }}">
                        </div>
                    @else
                        <div class="w-full h-24 bg-gradient-to-br from-indigo-50 to-purple-50 dark:from-indigo-900/20 dark:to-purple-900/20 flex items-center justify-center">
                            <flux:icon.briefcase class="w-8 h-8 text-indigo-200 dark:text-indigo-800/50" />
                        </div>
                    @endif

                    {{-- Card Body --}}
                    <div class="p-5 flex-1 flex flex-col">
                        <div class="flex-1">
                            <div class="flex items-start justify-between gap-4 mb-2">
                                <div class="flex items-center gap-2 flex-1 min-w-0">
                                    <h3 class="text-base font-bold text-zinc-900 dark:text-zinc-100 group-hover:text-indigo-600 dark:group-hover:text-indigo-400 transition-colors leading-tight truncate">{{ $workspace->name }}</h3>
                                    @if(!$workspace->is_active)
                                        <span class="shrink-0 text-[10px] font-bold uppercase tracking-wider px-1.5 py-0.5 rounded bg-zinc-200 dark:bg-zinc-700 text-zinc-500 dark:text-zinc-400">Non-aktif</span>
                                    @endif
                                </div>
                                <div class="flex -space-x-2 shrink-0">
                                    @foreach($workspace->users as $user)
                                        <div class="w-7 h-7 rounded-full bg-indigo-100 dark:bg-indigo-900/30 border-2 border-white dark:border-zinc-900 flex items-center justify-center text-[10px] font-bold text-indigo-700 dark:text-indigo-300 shadow-sm overflow-hidden" title="{{ $user->name }} ({{ ucfirst($user->pivot->role ?? 'Member') }})">
                                            @if($user->avatar)
                                                <img src="{{ \Illuminate\Support\Facades\Storage::url($user->avatar) }}" class="w-full h-full object-cover" alt="{{ $user->name }}">
                                            @else
                                                {{ substr($user->name, 0, 1) }}
                                            @endif
                                        </div>
                                    @endforeach
                                    @if($workspace->users_count > 5)
                                        <div class="w-7 h-7 rounded-full bg-zinc-100 dark:bg-zinc-800 border-2 border-white dark:border-zinc-900 flex items-center justify-center text-[10px] font-bold text-zinc-600 dark:text-zinc-400 shadow-sm">
                                            +{{ $workspace->users_count - 5 }}
                                        </div>
                                    @endif
                                </div>
                            </div>
                            <p class="text-xs text-zinc-500 dark:text-zinc-400 line-clamp-2 leading-relaxed">{{ !empty($workspace->description) ? html_entity_decode(strip_tags($workspace->description)) : 'Tidak ada deskripsi.' }}</p>
                            
                            @php
                                $leader = $workspace->users->firstWhere('id', $workspace->owner_id) 
                                    ?? $workspace->users->firstWhere('pivot.role', 'admin') 
                                    ?? $workspace->users->firstWhere('pivot.role', 'leader');
                            @endphp
                            @if($leader)
                            <div class="mt-2.5 flex items-center gap-1.5 text-xs text-zinc-500 dark:text-zinc-400">
                                <flux:icon.star class="w-3.5 h-3.5 text-amber-500" />
                                <span>Leader: <span class="font-medium text-zinc-700 dark:text-zinc-300">{{ $leader->name }}</span></span>
                            </div>
                            @endif
                        </div>
                        
                        <div class="mt-4 pt-3 border-t border-zinc-100 dark:border-zinc-800/80">
                            <div class="grid grid-cols-3 gap-2">
                                <div class="bg-zinc-50 dark:bg-zinc-800/50 rounded-lg p-2 text-center group-hover:bg-indigo-50 dark:group-hover:bg-indigo-900/20 transition-colors">
                                    <div class="text-base font-bold text-zinc-700 dark:text-zinc-300 group-hover:text-indigo-600 dark:group-hover:text-indigo-400">{{ $workspace->tasks_count }}</div>
                                    <div class="text-[10px] font-medium text-zinc-500 uppercase tracking-wider">Tasks</div>
                                </div>
                                <div class="bg-zinc-50 dark:bg-zinc-800/50 rounded-lg p-2 text-center group-hover:bg-purple-50 dark:group-hover:bg-purple-900/20 transition-colors">
                                    <div class="text-base font-bold text-zinc-700 dark:text-zinc-300 group-hover:text-purple-600 dark:group-hover:text-purple-400">{{ $workspace->objectives_count }}</div>
                                    <div class="text-[10px] font-medium text-zinc-500 uppercase tracking-wider">OKR</div>
                                </div>
                                <div class="bg-zinc-50 dark:bg-zinc-800/50 rounded-lg p-2 text-center group-hover:bg-emerald-50 dark:group-hover:bg-emerald-900/20 transition-colors">
                                    <div class="text-base font-bold text-zinc-700 dark:text-zinc-300 group-hover:text-emerald-600 dark:group-hover:text-emerald-400">{{ $workspace->kpis_count }}</div>
                                    <div class="text-[10px] font-medium text-zinc-500 uppercase tracking-wider">KPI</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </a>
            </div>
        @empty
            <div class="col-span-full flex flex-col items-center justify-center py-24 text-center">
                <flux:icon.briefcase class="w-14 h-14 text-zinc-300 dark:text-zinc-700 mb-4" />
                <h3 class="text-lg font-semibold text-zinc-600 dark:text-zinc-400">Belum ada workspace</h3>
                <p class="text-sm text-zinc-400 dark:text-zinc-600 mt-1 mb-5">Buat workspace pertama Anda untuk mulai bekerja.</p>
                <flux:button variant="primary" icon="plus" @click="$flux.modal('create-workspace-modal').show()">Buat Workspace</flux:button>
            </div>
        @endforelse
    </div>

    @if(auth()->user()->hasRole(['Manager', 'Super Admin']))
    {{-- ======================== --}}
    {{-- Create Workspace Modal  --}}
    {{-- ======================== --}}
    <flux:modal wire:model="showCreateModal" class="w-full max-w-lg">
        <div class="p-1 space-y-5">
            <div>
                <h2 class="text-xl font-bold text-zinc-900 dark:text-zinc-100">Buat Workspace Baru</h2>
                <p class="text-sm text-zinc-500 dark:text-zinc-400 mt-1">Isi detail workspace Anda di bawah ini.</p>
            </div>

            <form wire:submit="createWorkspace" class="space-y-4">
                <x-image-cropper id="workspace-cropper" wire:model="cover_image" accept="image/*" label="Cover Image (Opsional)" reset-event="reset-cropper" />
                <flux:input wire:model="newWorkspaceName" label="Nama Workspace" placeholder="cth. Marketing Q4, Produksi Video" required />
                <flux:textarea wire:model="newWorkspaceDescription" label="Deskripsi (Opsional)" rows="3" placeholder="Workspace ini untuk apa?" />
                <div class="flex gap-3 justify-end pt-1">
                    <flux:button type="button" variant="ghost" wire:click="$set('showCreateModal', false)">Batal</flux:button>
                    <flux:button type="submit" variant="primary" icon="plus">
                        Buat Workspace
                        <div wire:loading wire:target="createWorkspace" class="w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin ml-1"></div>
                    </flux:button>
                </div>
            </form>
        </div>
    </flux:modal>
    @endif

    {{-- ======================== --}}
    {{-- Edit Workspace Modal    --}}
    {{-- ======================== --}}
    <flux:modal wire:model="showEditModal" class="w-full max-w-lg">
        <div class="p-1 space-y-5">
            <div>
                <h2 class="text-xl font-bold text-zinc-900 dark:text-zinc-100">Edit Workspace</h2>
                <p class="text-sm text-zinc-500 dark:text-zinc-400 mt-1">Perbarui informasi workspace Anda.</p>
            </div>

            <form wire:submit="updateWorkspace" class="space-y-4">
                <x-image-cropper id="workspace-edit-cropper" wire:model="editCoverImage" :image="$existingCoverImage" accept="image/*" label="Cover Image" reset-event="reset-cropper" />
                <flux:input wire:model="editName" label="Nama Workspace" required />
                <flux:textarea wire:model="editDescription" label="Deskripsi (Opsional)" rows="3" />
                <div class="flex gap-3 justify-end pt-1">
                    <flux:button type="button" variant="ghost" wire:click="$set('showEditModal', false)">Batal</flux:button>
                    <flux:button type="submit" variant="primary" icon="check">
                        Simpan Perubahan
                        <div wire:loading wire:target="updateWorkspace" class="w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin ml-1"></div>
                    </flux:button>
                </div>
            </form>
        </div>
    </flux:modal>

    {{-- Guide Modal --}}
    <flux:modal wire:model="showGuideModal" class="w-full md:max-w-4xl">
        <div class="p-6" x-data="{ activeTab: 'kanban' }">
            {{-- Header --}}
            <div class="flex justify-between items-start border-b border-zinc-200 dark:border-zinc-700 pb-4 mb-5">
                <div>
                    <h2 class="text-xl font-bold text-zinc-900 dark:text-zinc-100 flex items-center gap-2">
                        <flux:icon.book-open class="w-6 h-6 text-indigo-500" />
                        Panduan Penggunaan Workspace
                    </h2>
                    <p class="text-sm text-zinc-500 dark:text-zinc-400 mt-1">Panduan lengkap fitur Kanban, Task, OKR, dan KPI.</p>
                </div>
            </div>

            {{-- Tab Navigation --}}
            <div class="flex flex-wrap gap-1.5 mb-5 border-b border-zinc-200 dark:border-zinc-700 pb-3">
                @foreach([
                    ['id' => 'roles',  'icon' => 'users',           'label' => 'Peran & Akses',  'color' => 'blue'],
                    ['id' => 'kanban', 'icon' => 'rectangle-stack', 'label' => 'Kanban Board',   'color' => 'emerald'],
                    ['id' => 'task',   'icon' => 'document-text',   'label' => 'Detail Tugas',   'color' => 'orange'],
                    ['id' => 'okr',    'icon' => 'flag',             'label' => 'OKR',            'color' => 'red'],
                    ['id' => 'kpi',    'icon' => 'chart-bar',        'label' => 'KPI',            'color' => 'violet'],
                ] as $tab)
                <button @click="activeTab = '{{ $tab['id'] }}'"
                        :class="activeTab === '{{ $tab['id'] }}' ? 'bg-indigo-50 dark:bg-indigo-900/30 text-indigo-700 dark:text-indigo-300 border-indigo-300 dark:border-indigo-700' : 'text-zinc-600 dark:text-zinc-400 border-zinc-200 dark:border-zinc-700 hover:bg-zinc-50 dark:hover:bg-zinc-800'"
                        class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg border text-sm font-medium transition-all whitespace-nowrap">
                    <flux:icon.{{ $tab['icon'] }} class="w-4 h-4" />
                    {{ $tab['label'] }}
                </button>
                @endforeach
            </div>

            {{-- Tab Content --}}
            <div class="max-h-[60vh] overflow-y-auto custom-scrollbar pr-2 space-y-5">

                {{-- ===== ROLES ===== --}}
                <div x-show="activeTab === 'roles'" x-transition.opacity>
                    <div class="space-y-4">
                        <p class="text-sm text-zinc-600 dark:text-zinc-400">Terdapat 3 tingkatan peran di dalam setiap Workspace:</p>

                        <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                            <div class="rounded-xl border border-blue-200 dark:border-blue-800/50 bg-blue-50/50 dark:bg-blue-900/10 p-4">
                                <div class="flex items-center gap-2 mb-2">
                                    <span class="text-lg">👑</span>
                                    <h4 class="font-bold text-blue-800 dark:text-blue-300 text-sm">Super Admin / Manager</h4>
                                </div>
                                <ul class="text-xs text-blue-700 dark:text-blue-400 space-y-1 list-disc list-inside">
                                    <li>Membuat & menghapus Workspace</li>
                                    <li>Mengatur peran semua anggota</li>
                                    <li>Akses penuh ke semua fitur</li>
                                </ul>
                            </div>
                            <div class="rounded-xl border border-indigo-200 dark:border-indigo-800/50 bg-indigo-50/50 dark:bg-indigo-900/10 p-4">
                                <div class="flex items-center gap-2 mb-2">
                                    <span class="text-lg">🏢</span>
                                    <h4 class="font-bold text-indigo-800 dark:text-indigo-300 text-sm">Admin / Leader (Kepala)</h4>
                                </div>
                                <ul class="text-xs text-indigo-700 dark:text-indigo-400 space-y-1 list-disc list-inside">
                                    <li>Membuat & mengelola kolom Kanban</li>
                                    <li>Membuat & menghapus task</li>
                                    <li>Mengatur OKR & KPI</li>
                                    <li>Mengundang & mengeluarkan anggota</li>
                                    <li>Menyeret kartu task milik siapapun</li>
                                </ul>
                            </div>
                            <div class="rounded-xl border border-emerald-200 dark:border-emerald-800/50 bg-emerald-50/50 dark:bg-emerald-900/10 p-4">
                                <div class="flex items-center gap-2 mb-2">
                                    <span class="text-lg">👤</span>
                                    <h4 class="font-bold text-emerald-800 dark:text-emerald-300 text-sm">Member (Pelaksana)</h4>
                                </div>
                                <ul class="text-xs text-emerald-700 dark:text-emerald-400 space-y-1 list-disc list-inside">
                                    <li>Hanya melihat task yang ditugaskan</li>
                                    <li>Menyeret kartu milik sendiri saja</li>
                                    <li>Menambah komentar & lampiran</li>
                                    <li>Mencatat waktu (Time Log)</li>
                                    <li>Tidak bisa edit OKR & KPI</li>
                                </ul>
                            </div>
                        </div>

                        <div class="rounded-lg bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800/50 p-3 text-xs text-amber-800 dark:text-amber-300">
                            <strong>💡 Tips:</strong> Ada juga peran <strong>Pengawas (Observer)</strong> — bisa melihat semua task tanpa bisa mengedit apapun. Cocok untuk atasan yang hanya memantau.
                        </div>
                    </div>
                </div>

                {{-- ===== KANBAN BOARD ===== --}}
                <div x-show="activeTab === 'kanban'" x-transition.opacity>
                    <div class="space-y-5">

                        <div>
                            <h4 class="font-semibold text-zinc-800 dark:text-zinc-200 mb-2 flex items-center gap-1.5">📋 Sistem Kolom</h4>
                            <p class="text-sm text-zinc-600 dark:text-zinc-400 mb-3">Kolom merepresentasikan tahapan/status pekerjaan. Setiap kolom memiliki tipe yang mengatur aturan perpindahan task:</p>
                            <div class="grid grid-cols-2 md:grid-cols-4 gap-2">
                                @foreach([
                                    ['type' => 'Normal', 'desc' => 'Task bebas dipindah masuk/keluar oleh siapapun.', 'color' => 'zinc'],
                                    ['type' => 'Backlog', 'desc' => 'Task hanya bisa ditarik keluar oleh Leader/Admin.', 'color' => 'blue'],
                                    ['type' => 'Review', 'desc' => 'Task perlu persetujuan Leader sebelum lanjut.', 'color' => 'amber'],
                                    ['type' => 'Done', 'desc' => 'Task terkunci — Member tidak bisa memindahkan masuk.', 'color' => 'green'],
                                ] as $c)
                                <div class="rounded-lg border border-zinc-200 dark:border-zinc-700 p-3 text-xs">
                                    <span class="font-bold text-zinc-800 dark:text-zinc-200">{{ $c['type'] }}</span>
                                    <p class="text-zinc-500 dark:text-zinc-400 mt-1">{{ $c['desc'] }}</p>
                                </div>
                                @endforeach
                            </div>
                        </div>

                        <div>
                            <h4 class="font-semibold text-zinc-800 dark:text-zinc-200 mb-2 flex items-center gap-1.5">🗂️ Grup Kolom</h4>
                            <p class="text-sm text-zinc-600 dark:text-zinc-400">Kolom bisa dikelompokkan menjadi satu grup (contoh: "IKLAN" berisi sub-kolom "Shooting", "Editing", "Review"). Gunakan menu <strong>⋮ → Jadikan Grup Kolom</strong> pada kolom yang ada. Kemudian klik <strong>+ Sub Kolom</strong> untuk menambah anggota grup.</p>
                        </div>

                        <div>
                            <h4 class="font-semibold text-zinc-800 dark:text-zinc-200 mb-2 flex items-center gap-1.5">🎨 Warna Badge Kolom</h4>
                            <p class="text-sm text-zinc-600 dark:text-zinc-400">Setiap kolom bisa diatur warna badge-nya. Buka menu <strong>⋮ → Edit Kolom</strong>, lalu pilih warna dari palet yang tersedia — termasuk warna kustom bebas. Warna ini tersimpan permanen di database.</p>
                        </div>

                        <div>
                            <h4 class="font-semibold text-zinc-800 dark:text-zinc-200 mb-2 flex items-center gap-1.5">🖱️ Drag & Drop</h4>
                            <ul class="text-sm text-zinc-600 dark:text-zinc-400 space-y-1 list-disc list-inside">
                                <li>Seret kartu task ke kolom tujuan untuk mengubah statusnya</li>
                                <li>Member hanya bisa menyeret kartu yang ditugaskan ke dirinya</li>
                                <li>Urutan kartu dalam kolom bisa diatur ulang — tersimpan otomatis</li>
                                <li>Kolom juga bisa diurutkan ulang dengan menyeret area header kolom</li>
                            </ul>
                        </div>

                        <div class="rounded-lg bg-indigo-50 dark:bg-indigo-900/20 border border-indigo-200 dark:border-indigo-800/50 p-3 text-xs text-indigo-800 dark:text-indigo-300">
                            <strong>⚡ Otomatisasi:</strong> Jika sebuah task terhubung ke Key Result (OKR), memindahkannya ke kolom bertipe <strong>Done</strong> akan otomatis menambah nilai pencapaian Key Result tersebut!
                        </div>
                    </div>
                </div>

                {{-- ===== TASK DETAIL ===== --}}
                <div x-show="activeTab === 'task'" x-transition.opacity>
                    <div class="space-y-5">
                        <p class="text-sm text-zinc-600 dark:text-zinc-400">Klik kartu task di Kanban untuk membuka panel detail. Di sini Anda bisa mengelola semua informasi terkait task tersebut:</p>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                            @foreach([
                                ['icon' => '📝', 'title' => 'Judul & Deskripsi', 'desc' => 'Edit judul dengan klik langsung. Tambah deskripsi lengkap menggunakan rich text editor.'],
                                ['icon' => '✅', 'title' => 'Subtask (Checklist)', 'desc' => 'Pecah task besar menjadi langkah kecil. Progress subtask ditampilkan sebagai progress bar di kartu.'],
                                ['icon' => '👥', 'title' => 'Assignee (Ditugaskan)', 'desc' => 'Pilih anggota workspace yang bertanggung jawab atas task ini. Bisa lebih dari satu orang.'],
                                ['icon' => '📅', 'title' => 'Deadline', 'desc' => 'Atur tanggal jatuh tempo. Kartu otomatis menampilkan hitungan mundur dan berubah merah jika terlambat.'],
                                ['icon' => '🏷️', 'title' => 'Label & Prioritas', 'desc' => 'Tambahkan label warna untuk kategorisasi. Atur prioritas (Low/Normal/High/Critical) agar mudah disaring.'],
                                ['icon' => '💬', 'title' => 'Komentar & Mention', 'desc' => 'Diskusikan task bersama tim. Gunakan @Nama untuk men-tag rekan dan mengirim notifikasi.'],
                                ['icon' => '📎', 'title' => 'Lampiran (Attachments)', 'desc' => 'Unggah file pendukung (gambar, dokumen, video) langsung di dalam task.'],
                                ['icon' => '🔗', 'title' => 'Tautan (URLs)', 'desc' => 'Simpan link referensi, Google Drive, atau konten terkait agar mudah diakses tim.'],
                                ['icon' => '⏱️', 'title' => 'Time Log', 'desc' => 'Catat jam kerja yang dihabiskan. Berguna untuk pelaporan dan evaluasi produktivitas.'],
                                ['icon' => '🎯', 'title' => 'Kaitkan ke Key Result', 'desc' => 'Hubungkan task ke salah satu Key Result OKR. Saat task selesai, progress KR otomatis bertambah.'],
                            ] as $item)
                            <div class="flex gap-3 p-3 rounded-lg border border-zinc-200 dark:border-zinc-700 bg-zinc-50/50 dark:bg-zinc-800/30">
                                <span class="text-xl shrink-0 mt-0.5">{{ $item['icon'] }}</span>
                                <div>
                                    <p class="text-sm font-semibold text-zinc-800 dark:text-zinc-200">{{ $item['title'] }}</p>
                                    <p class="text-xs text-zinc-500 dark:text-zinc-400 mt-0.5">{{ $item['desc'] }}</p>
                                </div>
                            </div>
                            @endforeach
                        </div>
                    </div>
                </div>

                {{-- ===== OKR ===== --}}
                <div x-show="activeTab === 'okr'" x-transition.opacity>
                    <div class="space-y-5">
                        <div class="rounded-xl border border-red-200 dark:border-red-800/50 bg-red-50/50 dark:bg-red-900/10 p-4 text-sm text-red-800 dark:text-red-300">
                            <strong>🎯 Apa itu OKR?</strong><br>
                            <span class="text-xs mt-1 block">OKR = <em>Objectives & Key Results</em>. Alat untuk menetapkan tujuan besar tim dan mengukur keberhasilannya dengan angka yang konkret.</span>
                        </div>

                        <div>
                            <h4 class="font-semibold text-zinc-800 dark:text-zinc-200 mb-3">Struktur OKR</h4>
                            <div class="space-y-2">
                                <div class="flex gap-3 items-start p-3 rounded-lg bg-zinc-50 dark:bg-zinc-800/50 border border-zinc-200 dark:border-zinc-700">
                                    <span class="text-2xl">🏔️</span>
                                    <div>
                                        <p class="font-bold text-zinc-800 dark:text-zinc-200 text-sm">Objective (Tujuan)</p>
                                        <p class="text-xs text-zinc-500 mt-0.5">Pernyataan kualitatif tentang apa yang ingin dicapai. Bersifat ambisius dan inspiratif.</p>
                                        <p class="text-xs text-indigo-600 dark:text-indigo-400 mt-1">Contoh: <em>"Tingkatkan performa konten di semua platform Q4 2025"</em></p>
                                    </div>
                                </div>
                                <div class="flex gap-3 items-start p-3 rounded-lg bg-zinc-50 dark:bg-zinc-800/50 border border-zinc-200 dark:border-zinc-700 ml-6">
                                    <span class="text-2xl">🔑</span>
                                    <div>
                                        <p class="font-bold text-zinc-800 dark:text-zinc-200 text-sm">Key Result (Hasil Kunci)</p>
                                        <p class="text-xs text-zinc-500 mt-0.5">Ukuran spesifik & terukur yang membuktikan Objective tercapai. Setiap Objective bisa punya beberapa Key Result.</p>
                                        <p class="text-xs text-indigo-600 dark:text-indigo-400 mt-1">Contoh: <em>"Selesaikan 40 video dalam Q4"</em> → Target: 40, Saat ini: 0</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div>
                            <h4 class="font-semibold text-zinc-800 dark:text-zinc-200 mb-2">Cara Membuat OKR</h4>
                            <ol class="text-sm text-zinc-600 dark:text-zinc-400 space-y-2 list-decimal list-inside">
                                <li>Buka Workspace → Klik tombol <strong>OKR</strong> di header</li>
                                <li>Klik <strong>"Tambah Objective"</strong> → Isi judul, periode (Q1/Q2/Q3/Q4/Annual), dan tahun</li>
                                <li>Setelah Objective dibuat, klik <strong>"+ Tambah Key Result"</strong> di bawahnya</li>
                                <li>Isi nama KR, tipe (Numerik/Persentase/Boolean), nilai awal, nilai target, dan satuan</li>
                                <li>Update nilai <strong>"Saat Ini"</strong> secara berkala dengan mengetik di kolom nilai → tekan Enter</li>
                            </ol>
                        </div>

                        <div>
                            <h4 class="font-semibold text-zinc-800 dark:text-zinc-200 mb-2">Status Objective</h4>
                            <div class="grid grid-cols-2 md:grid-cols-5 gap-2 text-xs">
                                @foreach([
                                    ['Draft', 'Belum dimulai', 'zinc'],
                                    ['On Track', 'Progres sesuai rencana', 'blue'],
                                    ['At Risk', 'Perlu perhatian khusus', 'yellow'],
                                    ['Achieved', 'Target tercapai! 🎉', 'green'],
                                    ['Failed', 'Target tidak tercapai', 'red'],
                                ] as $s)
                                <div class="rounded-lg border p-2 text-center border-zinc-200 dark:border-zinc-700">
                                    <p class="font-bold text-zinc-800 dark:text-zinc-200">{{ $s[0] }}</p>
                                    <p class="text-zinc-500 mt-0.5">{{ $s[1] }}</p>
                                </div>
                                @endforeach
                            </div>
                        </div>

                        <div class="rounded-lg bg-indigo-50 dark:bg-indigo-900/20 border border-indigo-200 dark:border-indigo-800/50 p-3 text-xs text-indigo-800 dark:text-indigo-300">
                            <strong>🔗 Hubungan OKR ↔ Task:</strong> Di panel detail task, Anda bisa menghubungkan task ke Key Result tertentu. Saat task dipindahkan ke kolom <strong>Done</strong>, nilai Key Result akan otomatis bertambah sesuai kontribusinya.
                        </div>
                    </div>
                </div>

                {{-- ===== KPI ===== --}}
                <div x-show="activeTab === 'kpi'" x-transition.opacity>
                    <div class="space-y-5">
                        <div class="rounded-xl border border-violet-200 dark:border-violet-800/50 bg-violet-50/50 dark:bg-violet-900/10 p-4 text-sm text-violet-800 dark:text-violet-300">
                            <strong>📊 Apa itu KPI?</strong><br>
                            <span class="text-xs mt-1 block">KPI = <em>Key Performance Indicators</em>. Angka yang Anda pantau secara <strong>rutin</strong> untuk tahu apakah kinerja tim berada di jalur yang benar — bukan tujuan sekali waktu, melainkan metrik yang dipantau terus-menerus.</span>
                        </div>

                        <div>
                            <h4 class="font-semibold text-zinc-800 dark:text-zinc-200 mb-3">Contoh KPI untuk Tim Produksi Konten</h4>
                            <div class="overflow-x-auto">
                                <table class="w-full text-xs border-collapse">
                                    <thead>
                                        <tr class="bg-zinc-100 dark:bg-zinc-800">
                                            <th class="text-left p-2 rounded-tl-lg font-semibold text-zinc-700 dark:text-zinc-300">Nama KPI</th>
                                            <th class="text-center p-2 font-semibold text-zinc-700 dark:text-zinc-300">Target</th>
                                            <th class="text-center p-2 font-semibold text-zinc-700 dark:text-zinc-300">Periode</th>
                                            <th class="text-left p-2 rounded-tr-lg font-semibold text-zinc-700 dark:text-zinc-300">Tren</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                                        @foreach([
                                            ['Video selesai diproduksi', '12', 'Bulanan', '↑ Makin tinggi makin baik'],
                                            ['Waktu editing rata-rata', '3 hari', 'Per video', '↓ Makin rendah makin baik'],
                                            ['Jumlah GIVEAWAY selesai', '10', 'Kwartal', '↑ Makin tinggi makin baik'],
                                            ['Engagement rate konten', '5%', 'Mingguan', '↑ Makin tinggi makin baik'],
                                        ] as $row)
                                        <tr class="hover:bg-zinc-50 dark:hover:bg-zinc-800/50">
                                            <td class="p-2 text-zinc-700 dark:text-zinc-300">{{ $row[0] }}</td>
                                            <td class="p-2 text-center font-semibold text-zinc-800 dark:text-zinc-200">{{ $row[1] }}</td>
                                            <td class="p-2 text-center text-zinc-500">{{ $row[2] }}</td>
                                            <td class="p-2 text-zinc-500">{{ $row[3] }}</td>
                                        </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <div>
                            <h4 class="font-semibold text-zinc-800 dark:text-zinc-200 mb-2">Cara Membuat & Update KPI</h4>
                            <ol class="text-sm text-zinc-600 dark:text-zinc-400 space-y-2 list-decimal list-inside">
                                <li>Buka Workspace → Klik tombol <strong>KPI</strong> di header</li>
                                <li>Klik <strong>"Tambah KPI"</strong> → Isi nama, target, satuan, dan periode pengukuran</li>
                                <li>Pilih tren: <strong>↑ Semakin tinggi semakin baik</strong> (contoh: jumlah video) atau <strong>↓ Semakin rendah semakin baik</strong> (contoh: waktu pengerjaan)</li>
                                <li>Setelah dibuat, <strong>update nilai "Saat Ini" secara rutin</strong> sesuai periode (harian/mingguan/bulanan) dengan mengetik angka baru → tekan Enter</li>
                                <li>Sistem otomatis menghitung persentase dan menampilkan indikator warna (🟢 tercapai, 🔴 di bawah target)</li>
                            </ol>
                        </div>

                        <div>
                            <h4 class="font-semibold text-zinc-800 dark:text-zinc-200 mb-2">Hubungan KPI ↔ OKR (Opsional)</h4>
                            <p class="text-sm text-zinc-600 dark:text-zinc-400">Saat membuat atau mengedit KPI, Anda bisa mengkaitkannya ke salah satu Key Result di OKR. Ini membuat dashboard menampilkan konteks yang lebih lengkap antara metrik rutinitas (KPI) dan tujuan jangka menengah (OKR).</p>
                        </div>

                        <div class="rounded-lg bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800/50 p-3 text-xs text-amber-800 dark:text-amber-300">
                            <strong>📌 Perbedaan KPI vs OKR:</strong><br>
                            <span class="mt-1 block"><strong>OKR</strong> = tujuan yang ingin dicapai dalam satu periode tertentu (Q1, Q2, dst). Sekali tercapai, selesai.<br>
                            <strong>KPI</strong> = angka yang terus dipantau setiap hari/minggu/bulan tanpa batas waktu. Seperti detak jantung bisnis Anda.</span>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </flux:modal>
</div>

