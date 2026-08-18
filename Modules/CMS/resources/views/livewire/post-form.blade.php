<?php

use Livewire\Volt\Component;
use Modules\CMS\Models\CmsPost;
use Modules\CMS\Models\CmsCategory;
use Livewire\Attributes\Layout;
use Spatie\Permission\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

new class extends Component {
    public function layout()
    {
        return 'layouts.app';
    }
    public ?CmsPost $post = null;
    
    public $title = '';
    public $slug = '';
    public $content = '';
    public $category_id = '';
    public $is_pinned = false;
    public $status = 'published';
    
    // AI Generation
    public $isGeneratingAi = false;
    public $aiPrompt = '';
    public $useRagContext = false;
    
    // Quick add category
    public $newCategoryName = '';
    
    // For x-image-cropper
    public $cover_image = null;
    public $existing_cover = null;

    // Advanced Visibility
    public $selected_roles = [];
    public $selected_users = [];

    public function updatedSelectedRoles($value)
    {
        if (!empty($value)) {
            $usersInRoles = User::whereHas('roles', function($q) use ($value) {
                $q->whereIn('id', $value);
            })->pluck('id')->map(fn($id) => (string)$id)->toArray();
            
            $this->selected_users = array_values(array_unique(array_merge($this->selected_users, $usersInRoles)));
        }
    }
    public function generateWithAi()
    {
        $this->validate([
            'aiPrompt' => 'required|min:5'
        ]);

        $this->isGeneratingAi = true;

        try {
            $apiKey = env('GEMINI_API_KEY');
            if (!$apiKey) {
                throw new \Exception('API Key belum diatur');
            }
            
            $ragContextText = "";
            if ($this->useRagContext) {
                $vectorService = app(\App\Services\VectorSearchService::class);
                $relevantData = $vectorService->search($this->aiPrompt, 5); // Ambil Top 5
                
                if (count($relevantData) > 0) {
                    $ragContextText = "\n\nBerikut adalah data dari database internal kami yang mungkin relevan untuk menjawab permintaan ini:\n";
                    foreach ($relevantData as $index => $data) {
                        $ragContextText .= ($index + 1) . ". Sumber (" . $data['model_type'] . " ID " . $data['model_id'] . "): " . $data['content_text'] . "\n";
                    }
                } else {
                    $ragContextText = "\n\n(Tidak ada data internal yang ditemukan terkait permintaan ini.)\n";
                }
            }

            // Memanggil API Google Gemini (gemini-3.5-flash)
            $response = \Illuminate\Support\Facades\Http::withHeaders([
                'Content-Type' => 'application/json',
            ])->post('https://generativelanguage.googleapis.com/v1beta/models/gemini-3.5-flash:generateContent?key=' . $apiKey, [
                'contents' => [
                    [
                        'parts' => [
                            [
                                'text' => "Kamu adalah asisten penulis artikel blog yang profesional. Buatkan artikel lengkap dalam bahasa Indonesia berdasarkan instruksi berikut: '" . $this->aiPrompt . "'. " . $ragContextText . " Gunakan tag HTML yang sesuai (seperti <h1>, <h2>, <p>, <ul>, <li>, <strong>, <table>, <tr>, <td>) agar siap dirender di rich editor. Jika dirasa perlu, sertakan tabel (<table>) untuk data perbandingan, dan sisipkan tag <img> menggunakan layanan placeholder (contoh: <img src=\"https://placehold.co/800x400/f3f4f6/6b7280.png?text=Ilustrasi+Topik\" alt=\"Deskripsi\" style=\"width:100%; border-radius:8px;\">) sebagai ilustrasi. Jangan gunakan markdown (seperti ```html), langsung kembalikan raw HTML-nya saja."
                            ]
                        ]
                    ]
                ],
                'generationConfig' => [
                    'temperature' => 0.7,
                ]
            ]);

            if ($response->successful()) {
                $result = $response->json('candidates.0.content.parts.0.text');
                
                // Membersihkan markdown wrapper jika AI masih membandel mengirimkannya
                $result = preg_replace('/^```html\s*/i', '', $result);
                $result = preg_replace('/```$/', '', $result);
                
                $this->content = trim($result);
                
                // Beritahu rich-editor untuk memperbarui isinya
                $this->dispatch('ai-content-generated', content: $this->content);
                
                \Flux\Flux::modal('ai-writer-modal')->close();
                $this->aiPrompt = ''; // Reset prompt
                \Flux\Flux::toast('Artikel berhasil dibuat dengan Gemini AI!');
            } else {
                throw new \Exception($response->json('error.message') ?? 'Gagal menghubungi server Gemini API');
            }
        } catch (\Exception $e) {
            \Flux\Flux::toast(text: 'Error: ' . $e->getMessage(), variant: 'danger');
        }

        $this->isGeneratingAi = false;
    }

    public function saveCategory()
    {
        $this->validate([
            'newCategoryName' => 'required|string|max:255'
        ]);

        $cat = CmsCategory::create([
            'name' => $this->newCategoryName,
            'slug' => Str::slug($this->newCategoryName) . '-' . time(),
            'type' => 'post'
        ]);

        $this->category_id = $cat->id;
        $this->newCategoryName = '';
        
        \Flux\Flux::modal('create-category-modal')->close();
        \Flux\Flux::toast('Kategori berhasil ditambahkan!');
    }

    public function selectAllRoles()
    {
        $this->selected_roles = Role::pluck('id')->toArray();
        $this->updatedSelectedRoles($this->selected_roles);
    }

    public function selectAllUsers()
    {
        $this->selected_users = User::pluck('id')->map(fn($id) => (string)$id)->toArray();
    }

    public function mount(?CmsPost $post = null)
    {
        if ($post && $post->exists) {
            $this->post = $post;
            $this->title = $post->title;
            $this->slug = $post->slug;
            $this->content = $post->content;
            $this->category_id = $post->category_id;
            $this->is_pinned = $post->is_pinned;
            $this->status = $post->status;
            
            if ($post->cover_image) {
                $this->existing_cover = Storage::disk('public')->url($post->cover_image);
            }

            $this->selected_roles = $post->roles()->pluck('roles.id')->toArray();
            $this->selected_users = $post->users()->pluck('users.id')->toArray();
        } else {
            if (request()->has('slug')) {
                $this->slug = request()->query('slug');
            }
            if (request()->has('title')) {
                $this->title = request()->query('title');
            }
        }
    }

    public function with(): array
    {
        return [
            'categories' => CmsCategory::all(),
            'roles' => Role::all(),
            'users' => User::all()
        ];
    }

    public function save()
    {
        $this->validate([
            'title' => 'required|string|max:255',
            'category_id' => 'nullable|exists:cms_categories,id',
            'content' => 'required|string'
        ]);

        $isNew = !$this->post;
        if ($isNew) {
            $this->post = new CmsPost();
            $this->post->author_id = auth()->id();
        }

        $this->post->title = $this->title;
        // Jika slug manual diisi, gunakan itu. Jika tidak, generate dari title.
        $this->post->slug = $this->slug ?: (Str::slug($this->title) . '-' . time());
        $this->post->content = $this->content;
        $this->post->category_id = $this->category_id ?: null;
        $this->post->is_pinned = $this->is_pinned;
        $this->post->status = $this->status;

        // Handle image upload from x-image-cropper (base64)
        if ($this->cover_image && str_starts_with($this->cover_image, 'data:image')) {
            // Delete old cover
            if ($this->post->cover_image && Storage::disk('public')->exists($this->post->cover_image)) {
                Storage::disk('public')->delete($this->post->cover_image);
            }
            
            $image_parts = explode(";base64,", $this->cover_image);
            $image_base64 = base64_decode($image_parts[1]);
            
            $fileName = 'cms/' . uniqid() . '.webp';
            $saved = Storage::disk('public')->put($fileName, $image_base64);
            
            if ($saved) {
                $this->post->cover_image = $fileName;
            }
        }

        $this->post->save();

        // Sync roles and users
        $this->post->roles()->sync($this->selected_roles);
        $this->post->users()->sync($this->selected_users);

        \Flux\Flux::toast('Artikel berhasil disimpan!');
        
        return redirect()->route('cms.posts.index');
    }
};
?>
<div class="max-w-7xl mx-auto w-full">

        <flux:breadcrumbs class="mb-4">
            <flux:breadcrumbs.item href="/dashboard">Dashboard</flux:breadcrumbs.item>
            <flux:breadcrumbs.item href="{{ route('cms.posts.index') }}">Kelola CMS</flux:breadcrumbs.item>
            <flux:breadcrumbs.item>{{ $post ? 'Edit Artikel' : 'Tulis Artikel Baru' }}</flux:breadcrumbs.item>
        </flux:breadcrumbs>

        <div class="flex justify-between items-center mb-6">
            <flux:heading size="xl" level="1">{{ $post ? 'Edit Artikel' : 'Tulis Artikel Baru' }}</flux:heading>
        </div>

        <form wire:submit="save" class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            
            <!-- Main Content Area -->
            <div class="lg:col-span-2 space-y-6">
                <flux:card>
                    <div class="space-y-6">
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <flux:input wire:model="title" label="Judul Artikel" placeholder="Masukkan judul artikel yang menarik..." required />
                            <flux:input wire:model="slug" label="URL Slug (Otomatis jika kosong)" placeholder="contoh-judul-artikel" description="Unik. Jangan diubah jika tidak perlu." />
                        </div>
                        
                        <flux:field>
                            <div class="flex justify-between items-center">
                                <flux:label>Konten Artikel</flux:label>
                                <flux:modal.trigger name="ai-writer-modal">
                                    <button type="button" class="text-xs font-medium text-amber-600 dark:text-amber-400 hover:underline flex items-center gap-1 bg-amber-50 dark:bg-amber-900/30 px-2 py-1 rounded-md border border-amber-200 dark:border-amber-800 transition-colors">
                                        <flux:icon.sparkles class="w-3 h-3" />
                                        <span>Tulis dengan AI</span>
                                    </button>
                                </flux:modal.trigger>
                            </div>
                            <div class="mt-2">
                                <x-rich-editor wire:model="content" id="post-content" height="600px" />
                            </div>
                        </flux:field>
                    </div>
                </flux:card>
            </div>

            <!-- Sidebar (Settings) -->
            <div class="space-y-6">
                <flux:card>
                    <flux:heading size="md" class="mb-4">Pengaturan Publikasi</flux:heading>
                    
                    <div class="space-y-4">
                        <flux:select wire:model="status" label="Status">
                            <flux:select.option value="published">Langsung Terbitkan (Published)</flux:select.option>
                            <flux:select.option value="draft">Simpan sebagai Draf (Draft)</flux:select.option>
                        </flux:select>
                        
                        <flux:field>
                            <div class="flex justify-between items-center">
                                <flux:label>Kategori</flux:label>
                                <flux:modal.trigger name="create-category-modal">
                                    <button type="button" class="text-xs font-medium text-cyan-600 dark:text-cyan-400 hover:underline">+ Kategori Baru</button>
                                </flux:modal.trigger>
                            </div>
                            <flux:select wire:model="category_id" placeholder="Pilih Kategori..." class="mt-3">
                                <flux:select.option value="">- Tanpa Kategori -</flux:select.option>
                                @foreach($categories as $cat)
                                    <flux:select.option value="{{ $cat->id }}">{{ $cat->name }} ({{ ucfirst($cat->type) }})</flux:select.option>
                                @endforeach
                            </flux:select>
                        </flux:field>
                        
                        <flux:checkbox wire:model="is_pinned" label="Sematkan (Pin) ke Atas" />
                    </div>
                </flux:card>

                <flux:card>
                    <flux:heading size="md" class="mb-4">Gambar Sampul</flux:heading>
                    <x-image-cropper id="post-cropper" wire:model="cover_image" :image="$existing_cover" accept="image/*" label="Upload & Crop Thumbnail" />
                </flux:card>

                <flux:card>
                    <flux:heading size="md" class="mb-1">Target Pembaca</flux:heading>
                    <p class="text-xs text-zinc-500 mb-4">Biarkan kosong jika ini untuk semua orang.</p>
                    
                    <div class="space-y-4">
                        <!-- Multiple Select for Roles using Checkboxes -->
                        <flux:field>
                            <div class="flex justify-between items-center">
                                <flux:label>Target Divisi (Role)</flux:label>
                                <button type="button" wire:click="selectAllRoles" class="text-xs font-medium text-cyan-600 dark:text-cyan-400 hover:underline">Pilih Semua</button>
                            </div>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 mt-3 border border-zinc-200 dark:border-zinc-700 rounded-xl p-4 bg-zinc-50 dark:bg-zinc-800/50">
                                @foreach($roles as $role)
                                    <flux:checkbox wire:model.live="selected_roles" value="{{ $role->id }}" label="{{ $role->name }}" />
                                @endforeach
                            </div>
                        </flux:field>

                        <!-- Multiple Select for Users (Tag Style with Alpine.js) -->
                        <flux:field>
                            <div class="flex justify-between items-center">
                                <flux:label>Target Karyawan Tertentu</flux:label>
                                <button type="button" wire:click="selectAllUsers" class="text-xs font-medium text-cyan-600 dark:text-cyan-400 hover:underline">Pilih Semua</button>
                            </div>
                            <div x-data="{
                                    options: [
                                        @foreach($users as $user)
                                            { value: '{{ $user->id }}', label: '{{ addslashes($user->name) }}' },
                                        @endforeach
                                    ],
                                    selected: @entangle('selected_users'),
                                    open: false,
                                    search: '',
                                    get filteredOptions() {
                                        if (this.search === '') return this.options;
                                        return this.options.filter(o => o.label.toLowerCase().includes(this.search.toLowerCase()));
                                    },
                                    getLabel(val) {
                                        return this.options.find(o => String(o.value) === String(val))?.label || val;
                                    },
                                    toggle(val) {
                                        let stringVal = String(val);
                                        let idx = this.selected.findIndex(i => String(i) === stringVal);
                                        if (idx > -1) {
                                            this.selected.splice(idx, 1);
                                        } else {
                                            this.selected.push(stringVal);
                                        }
                                    },
                                    remove(val) {
                                        this.selected = this.selected.filter(i => String(i) !== String(val));
                                    }
                                }" class="relative mt-2">
                                
                                <!-- Tags Area -->
                                <div @click="open = !open" class="min-h-[42px] p-1.5 w-full border border-zinc-200 dark:border-zinc-700 rounded-lg flex flex-wrap gap-1.5 cursor-text bg-white dark:bg-zinc-900 transition focus-within:ring-2 focus-within:ring-cyan-500/20 focus-within:border-cyan-500 shadow-sm">
                                    <template x-for="val in selected" :key="val">
                                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 text-[13px] font-medium bg-cyan-50 dark:bg-cyan-900/30 text-cyan-700 dark:text-cyan-400 rounded-md border border-cyan-100 dark:border-cyan-800/50">
                                            <span x-text="getLabel(val)"></span>
                                            <button type="button" @click.stop="remove(val)" class="hover:text-red-500 focus:outline-none transition-colors">
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                                            </button>
                                        </span>
                                    </template>
                                    <div x-show="selected.length === 0" class="py-1.5 text-zinc-400 text-sm px-2">Ketik atau pilih karyawan...</div>
                                    <input x-show="open" type="text" x-model="search" @click.stop class="flex-1 min-w-[100px] border-none focus:ring-0 text-sm dark:bg-zinc-900 p-1" placeholder="Cari nama...">
                                </div>
                                
                                <!-- Dropdown -->
                                <div x-show="open" @click.away="open = false; search = ''" x-transition.opacity.duration.200ms class="absolute z-50 w-full mt-1 bg-white dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 rounded-lg shadow-xl max-h-56 flex flex-col overflow-hidden" style="display: none;">
                                    <div class="p-1 overflow-y-auto">
                                        <template x-for="option in filteredOptions" :key="option.value">
                                            <div @click="toggle(option.value)" class="px-3 py-2 text-sm rounded-md cursor-pointer hover:bg-zinc-100 dark:hover:bg-zinc-700 flex justify-between items-center transition-colors"
                                                 :class="{'bg-cyan-50 dark:bg-cyan-900/20 text-cyan-700 dark:text-cyan-400 font-medium': selected.includes(String(option.value))}">
                                                <span x-text="option.label"></span>
                                                <svg x-show="selected.includes(String(option.value))" class="w-4 h-4 text-cyan-600 dark:text-cyan-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"></path></svg>
                                            </div>
                                        </template>
                                        <div x-show="filteredOptions.length === 0" class="p-3 text-sm text-zinc-500 text-center">Karyawan tidak ditemukan.</div>
                                    </div>
                                </div>
                            </div>
                        </flux:field>
                    </div>
                </flux:card>

                <div class="flex flex-col-reverse sm:flex-row justify-end gap-3 mt-4 w-full">
                    <flux:button href="{{ route('cms.posts.index') }}" variant="ghost" class="w-full sm:w-auto">Batal</flux:button>
                    <flux:button type="submit" variant="primary" icon="check" class="w-full sm:w-auto">Simpan Artikel</flux:button>
                </div>
            </div>

        </form>

        <flux:modal name="create-category-modal" class="md:w-96">
            <form wire:submit="saveCategory" class="space-y-6">
                <div>
                    <flux:heading size="lg">Kategori Baru</flux:heading>
                    <flux:subheading>Tambahkan kategori baru secara cepat.</flux:subheading>
                </div>

                <flux:input wire:model="newCategoryName" label="Nama Kategori" placeholder="Contoh: Pengumuman" required />

                <div class="flex flex-col-reverse sm:flex-row justify-end gap-2 mt-2 w-full">
                    <flux:modal.close>
                        <flux:button variant="ghost" class="w-full sm:w-auto">Batal</flux:button>
                    </flux:modal.close>
                    <flux:button type="submit" variant="primary" class="w-full sm:w-auto">Simpan Kategori</flux:button>
                </div>
            </form>
        </flux:modal>

        <!-- AI Writer Modal -->
        <flux:modal name="ai-writer-modal" class="md:w-[600px]">
            <form wire:submit="generateWithAi" class="space-y-6">
                <div>
                    <flux:heading size="lg" class="flex items-center gap-2">
                        <flux:icon.sparkles class="w-5 h-5 text-amber-500" />
                        Asisten Penulis AI
                    </flux:heading>
                    <flux:subheading>Berikan instruksi yang jelas agar AI dapat menuliskan artikel sesuai keinginan Anda.</flux:subheading>
                </div>

                <flux:textarea 
                    wire:model="aiPrompt" 
                    label="Prompt / Instruksi Penulisan" 
                    placeholder="Contoh: Buatkan artikel santai sebanyak 4 paragraf tentang pentingnya menjaga kebersihan gudang. Berikan tips-tips praktis di dalamnya." 
                    rows="4" 
                    required 
                />
                
                <flux:checkbox wire:model="useRagContext" label="Gunakan Data Database (RAG)" description="Cari data yang relevan di seluruh sistem (Karyawan, Barang, Transaksi, dll) sebelum menulis." />

                <div class="flex flex-col-reverse sm:flex-row justify-end gap-2 mt-2 w-full">
                    <flux:modal.close>
                        <flux:button variant="ghost" class="w-full sm:w-auto" wire:loading.attr="disabled" wire:target="generateWithAi">Batal</flux:button>
                    </flux:modal.close>
                    <flux:button type="submit" variant="primary" class="bg-amber-600 hover:bg-amber-700 text-white border-none w-full sm:w-auto" wire:loading.attr="disabled" wire:target="generateWithAi">
                        <span wire:loading.remove wire:target="generateWithAi">Tulis Sekarang</span>
                        <span wire:loading wire:target="generateWithAi">Sedang Berpikir...</span>
                    </flux:button>
                </div>
            </form>
        </flux:modal>


</div>
