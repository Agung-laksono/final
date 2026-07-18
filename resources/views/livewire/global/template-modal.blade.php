<?php

use Livewire\Volt\Component;
use App\Models\NoteTemplate;
use Livewire\Attributes\On;

new class extends Component {
    public $show = false;
    public $mode = 'list'; // 'list', 'create', 'edit'
    
    public $search = '';
    public $filterType = '';
    public $context = null;
    
    public function mount($context = null)
    {
        $this->context = $context;
        if ($context) {
            $this->filterType = $context;
        }
    }
    
    // Form fields
    public $templateId = null;
    public $title = '';
    public $type = '';
    public $tags = '';
    public $content = '';
    public $is_active = true;
    
    #[On('open-template-modal')]
    public function openModal()
    {
        $this->resetForm();
        $this->mode = 'list';
        $this->show = true;
    }
    
    public function createNew()
    {
        $this->resetForm();
        if ($this->context) {
            $this->type = $this->context;
        }
        $this->mode = 'create';
    }
    
    public function editTemplate($id)
    {
        $template = NoteTemplate::find($id);
        if ($template) {
            $this->templateId = $template->id;
            $this->title = $template->title;
            $this->type = $template->type;
            $this->tags = is_array($template->tags) ? implode(', ', $template->tags) : '';
            $this->content = $template->content;
            $this->is_active = $template->is_active;
            $this->mode = 'edit';
        }
    }
    
    public function saveTemplate()
    {
        $this->validate([
            'title' => 'required|string|max:255',
            'type' => 'nullable|string|max:50',
            'content' => 'required|string',
        ]);
        
        $tagsArray = array_filter(array_map('trim', explode(',', $this->tags)));
        
        // Pastikan staff biasa tidak bisa memanipulasi tipe di luar konteks
        if ($this->context && !auth()->user()->can('settings.view')) {
            $this->type = $this->context;
        }
        
        if ($this->mode === 'create') {
            NoteTemplate::create([
                'title' => $this->title,
                'type' => $this->type,
                'tags' => $tagsArray,
                'content' => $this->content,
                'is_active' => $this->is_active,
                'created_by' => auth()->id(),
            ]);
            \Flux::toast('Template berhasil dibuat.', variant: 'success');
        } else {
            $template = NoteTemplate::find($this->templateId);
            if ($template) {
                $history = is_array($template->updated_by) ? $template->updated_by : [];
                $history[] = [
                    'user_id' => auth()->id(),
                    'name' => explode(' ', auth()->user()->name)[0],
                    'time' => now()->toDateTimeString(),
                ];
                
                $template->update([
                    'title' => $this->title,
                    'type' => $this->type,
                    'tags' => $tagsArray,
                    'content' => $this->content,
                    'is_active' => $this->is_active,
                    'updated_by' => $history,
                ]);
                \Flux::toast('Template berhasil diperbarui.', variant: 'success');
            }
        }
        
        $this->mode = 'list';
    }
    
    public function deleteTemplate($id)
    {
        $template = NoteTemplate::find($id);
        if ($template) {
            $template->delete();
            \Flux::toast('Template dihapus.', variant: 'danger');
        }
    }
    
    public function useTemplate($id)
    {
        $template = NoteTemplate::find($id);
        if ($template) {
            // Kita akan mendispatch event ke frontend untuk ditangkap oleh Alpine (Rich Editor)
            $this->dispatch('template-selected', content: $template->content);
            $this->show = false;
        }
    }
    
    public function resetForm()
    {
        $this->reset(['templateId', 'title', 'type', 'tags', 'content', 'is_active']);
    }
    
    public function with()
    {
        $query = NoteTemplate::with(['creator']);
        
        if ($this->search) {
            $query->where(function($q) {
                $q->where('title', 'like', '%' . $this->search . '%')
                  ->orWhere('tags', 'like', '%' . $this->search . '%');
            });
        }
        
        if ($this->context && !auth()->user()->can('settings.view')) {
            $query->where('type', $this->context)
                  ->where('is_active', true);
        } else {
            if ($this->filterType) {
                $query->where('type', $this->filterType);
            }
            // Admin/Manajer secara default melihat semua, kita tidak batasi is_active di sini
            // Namun kita bisa membedakannya secara visual di UI
        }
        
        $templates = $query->orderBy('title')->get();
        
        $types = NoteTemplate::select('type')->whereNotNull('type')->where('type', '!=', '')->distinct()->pluck('type');
        
        $allTags = NoteTemplate::whereNotNull('tags')->pluck('tags');
        $tagCounts = [];
        foreach($allTags as $tagsArray) {
            if (is_array($tagsArray)) {
                foreach($tagsArray as $tag) {
                    $tag = trim($tag);
                    if (!empty($tag)) {
                        $tagCounts[$tag] = ($tagCounts[$tag] ?? 0) + 1;
                    }
                }
            }
        }
        arsort($tagCounts);
        $popularTags = array_slice(array_keys($tagCounts), 0, 10);
        
        return [
            'templates' => $templates,
            'types' => $types,
            'popularTags' => $popularTags,
        ];
    }
};
?>

<div>
    <div x-data="{ show: @entangle('show') }" style="display: none;" x-show="show">
        <!-- Backdrop -->
        <div x-show="show" class="fixed inset-0 z-[210] bg-zinc-900/50 backdrop-blur-sm transition-opacity" x-on:click="show = false"
             x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
             x-transition:leave="ease-in duration-200" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"></div>
        
        <!-- Modal Container -->
        <div x-show="show" class="fixed inset-0 z-[210] overflow-y-auto" @click="show = false">
            <div class="flex min-h-full items-center justify-center p-4 text-center sm:p-0">
                <!-- Modal Panel -->
                <div x-show="show" @click.stop
                     x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95" x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
                     x-transition:leave="ease-in duration-200" x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100" x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                     class="relative transform rounded-xl bg-white dark:bg-zinc-900 text-left shadow-xl transition-all w-full md:w-[800px] max-w-full border border-zinc-200 dark:border-zinc-800"
                     wire:ignore.self>
                    <div class="p-6">
                        <div class="space-y-6">
                            <div class="flex justify-between items-start">
                                <div>
                                    <flux:heading size="lg">{{ $mode === 'list' ? 'Template Catatan' : ($mode === 'create' ? 'Buat Template Baru' : 'Edit Template') }}</flux:heading>
                                    <flux:subheading>
                                        {{ $mode === 'list' ? 'Pilih template untuk disisipkan, atau buat template baru.' : 'Isi form di bawah untuk mengelola template.' }}
                                    </flux:subheading>
                                </div>
                                
                                @if($mode === 'list')
                                    <flux:button variant="primary" size="sm" icon="plus" wire:click="createNew" class="shadow-sm">Buat Baru</flux:button>
                                @else
                                    <flux:button variant="ghost" size="sm" icon="arrow-left" wire:click="$set('mode', 'list')">Kembali ke Daftar</flux:button>
                                @endif
                            </div>

                            <div x-show="$wire.mode === 'list'">
                                <!-- Mode: List Templates -->
                                <div class="space-y-4">
                                    <div class="flex gap-2">
                                        <flux:input wire:model.live.debounce.300ms="search" placeholder="Cari judul atau tag..." icon="magnifying-glass" class="flex-1" />
                                        @if(!$context || auth()->user()->can('settings.view'))
                                            <flux:select wire:model.live="filterType" placeholder="Semua Tipe" class="w-40">
                                                <flux:select.option value="">Semua Tipe</flux:select.option>
                                                @foreach($types as $t)
                                                    <flux:select.option value="{{ $t }}">{{ ucfirst($t) }}</flux:select.option>
                                                @endforeach
                                            </flux:select>
                                        @endif
                                    </div>

                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3 max-h-[60vh] overflow-y-auto p-1 custom-scrollbar">
                                        @forelse($templates as $template)
                                            <div class="border rounded-lg p-3 transition-all group flex flex-col h-full shadow-sm {{ $template->is_active ? 'bg-white dark:bg-zinc-900 border-zinc-200 dark:border-zinc-700 hover:border-indigo-400 dark:hover:border-indigo-600' : 'bg-zinc-50 dark:bg-zinc-800/30 border-zinc-200 border-dashed dark:border-zinc-700 opacity-60 grayscale-[0.5] hover:opacity-100 hover:grayscale-0' }}">
                                                <div class="flex justify-between items-start mb-2 gap-2">
                                                    <div>
                                                        <h4 class="font-bold text-zinc-900 dark:text-zinc-100 line-clamp-1" title="{{ $template->title }}">
                                                            {{ $template->title }}
                                                        </h4>
                                                        <div class="flex gap-1 mt-1 flex-wrap">
                                                            @if(!$template->is_active)
                                                                <span class="px-1.5 py-0.5 bg-rose-100 text-rose-700 dark:bg-rose-900/30 dark:text-rose-400 text-[9px] font-bold uppercase tracking-wider rounded">Nonaktif</span>
                                                            @endif
                                                            @if($template->type)
                                                                <span class="px-1.5 py-0.5 bg-indigo-50 text-indigo-700 dark:bg-indigo-900/30 dark:text-indigo-400 text-[9px] font-bold uppercase tracking-wider rounded">{{ $template->type }}</span>
                                                            @endif
                                                            @if(is_array($template->tags))
                                                                @foreach($template->tags as $tag)
                                                                    <span class="px-1.5 py-0.5 bg-zinc-100 text-zinc-600 dark:bg-zinc-800 dark:text-zinc-400 text-[10px] rounded">{{ $tag }}</span>
                                                                @endforeach
                                                            @endif
                                                        </div>
                                                    </div>
                                                    <div class="flex opacity-0 group-hover:opacity-100 transition-opacity">
                                                        <flux:button size="xs" variant="ghost" class="!px-1.5" tooltip="Edit" wire:click="editTemplate({{ $template->id }})">
                                                            <flux:icon.pencil-square class="w-4 h-4 text-zinc-400 hover:text-indigo-600" />
                                                        </flux:button>
                                                        <flux:button size="xs" variant="ghost" class="!px-1.5" tooltip="Hapus" wire:click="deleteTemplate({{ $template->id }})" wire:confirm="Hapus template ini?">
                                                            <flux:icon.trash class="w-4 h-4 text-zinc-400 hover:text-red-600" />
                                                        </flux:button>
                                                    </div>
                                                </div>
                                                
                                                <div class="text-xs text-zinc-500 line-clamp-3 mb-3 flex-1 bg-zinc-50 dark:bg-zinc-800/50 p-2 rounded prose prose-xs max-w-none prose-img:hidden" x-html="$el.dataset.content" data-content="{{ $template->content }}">
                                                </div>
                                                
                                                <div class="mt-auto pt-2 border-t border-zinc-100 dark:border-zinc-800">
                                                    @if($template->creator || $template->updater)
                                                        <div class="flex justify-between items-center text-[10px] text-zinc-400 mb-2 px-1">
                                                            <span>
                                                                @if($template->creator)
                                                                    <flux:icon.user class="w-3 h-3 inline-block mr-0.5 text-zinc-300" /> {{ explode(' ', $template->creator->name)[0] }}
                                                                @endif
                                                            </span>
                                                            <span>
                                                                @if(is_array($template->updated_by) && count($template->updated_by) > 0)
                                                                    @php $lastEdit = collect($template->updated_by)->last(); @endphp
                                                                    <span title="Diedit {{ count($template->updated_by) }} kali. Terakhir pada {{ \Carbon\Carbon::parse($lastEdit['time'])->format('d M Y H:i') }}">
                                                                        <flux:icon.pencil class="w-3 h-3 inline-block mr-0.5 text-zinc-300" /> {{ $lastEdit['name'] }} ({{ count($template->updated_by) }}x)
                                                                    </span>
                                                                @endif
                                                            </span>
                                                        </div>
                                                    @endif
                                                    <flux:button variant="primary" size="sm" class="w-full" wire:click="useTemplate({{ $template->id }})" :disabled="!$template->is_active">Gunakan Template Ini</flux:button>
                                                </div>
                                            </div>
                                        @empty
                                            <div class="col-span-full py-8 text-center text-zinc-500">
                                                <flux:icon.document-text class="w-10 h-10 mx-auto text-zinc-300 mb-2" />
                                                <p>Belum ada template yang cocok.</p>
                                            </div>
                                        @endforelse
                                    </div>
                                </div>
                            </div>
                            
                            <div x-show="$wire.mode !== 'list'" style="display: none;">
                                <!-- Mode: Create / Edit -->
                                <form wire:submit="saveTemplate" class="space-y-4">
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                        <flux:field>
                                            <flux:label>Judul Template <span class="text-red-500">*</span></flux:label>
                                            <flux:input wire:model="title" required placeholder="Contoh: Instruksi Standar Maklon" />
                                            <flux:error name="title" />
                                        </flux:field>
                                        
                                        <flux:field>
                                            <flux:label>Tipe (Kategori Utama)</flux:label>
                                            <flux:select wire:model="type" :disabled="$context && !auth()->user()->can('settings.view')">
                                                <flux:select.option value="sales">Penjualan (Sales)</flux:select.option>
                                                <flux:select.option value="production">Produksi</flux:select.option>
                                                <flux:select.option value="purchase">Pembelian (Purchasing)</flux:select.option>
                                                <flux:select.option value="inventory">Gudang (Inventory)</flux:select.option>
                                            </flux:select>
                                            <flux:error name="type" />
                                        </flux:field>
                                    </div>
                                    
                                    <flux:field>
                                        <flux:label>Tags (Opsional)</flux:label>
                                        <div x-data="{
                                                tagsString: $wire.entangle('tags'),
                                                tagsArray: [],
                                                newTag: '',
                                                init() {
                                                    this.syncArray();
                                                    this.$watch('tagsString', () => this.syncArray());
                                                },
                                                syncArray() {
                                                    this.tagsArray = this.tagsString ? this.tagsString.split(',').map(t => t.trim()).filter(t => t) : [];
                                                },
                                                addTag(tag = null) {
                                                    let t = (tag || this.newTag).trim();
                                                    if (t && !this.tagsArray.includes(t)) {
                                                        this.tagsArray.push(t);
                                                        this.updateString();
                                                    }
                                                    this.newTag = '';
                                                },
                                                removeTag(index) {
                                                    this.tagsArray.splice(index, 1);
                                                    this.updateString();
                                                },
                                                updateString() {
                                                    this.tagsString = this.tagsArray.join(', ');
                                                },
                                                handleBackspace(e) {
                                                    if (this.newTag === '' && this.tagsArray.length > 0) {
                                                        this.removeTag(this.tagsArray.length - 1);
                                                    }
                                                }
                                            }" 
                                            class="w-full">
                                            
                                            <!-- Input area with visual pills -->
                                            <div class="flex flex-wrap items-center gap-1.5 p-1.5 border border-zinc-200 dark:border-zinc-700 rounded-lg bg-white dark:bg-zinc-900 focus-within:ring-2 focus-within:ring-indigo-500 focus-within:border-indigo-500 transition-shadow">
                                                <template x-for="(tag, index) in tagsArray" :key="tag">
                                                    <span class="inline-flex items-center gap-1 px-2 py-1 text-xs font-medium bg-zinc-100 text-zinc-700 dark:bg-zinc-800 dark:text-zinc-300 rounded-md">
                                                        <span x-text="tag"></span>
                                                        <button type="button" @click="removeTag(index)" class="hover:text-red-500 transition-colors focus:outline-none">
                                                            <flux:icon.x-mark class="w-3 h-3" />
                                                        </button>
                                                    </span>
                                                </template>
                                                
                                                <input type="text" x-model="newTag" @keydown.enter.prevent="addTag()" @keydown.comma.prevent="addTag()" @keydown.backspace="handleBackspace" placeholder="Ketik lalu Enter atau Koma..." class="flex-1 min-w-[150px] bg-transparent border-0 focus:ring-0 text-sm p-1 text-zinc-800 dark:text-zinc-200 outline-none" />
                                            </div>
                                            <flux:error name="tags" />
                                            
                                            <!-- Popular tags suggestion -->
                                            @if(count($popularTags) > 0)
                                                <div class="flex flex-wrap items-center gap-1.5 mt-2">
                                                    <span class="text-xs text-zinc-500 mr-1">Saran:</span>
                                                    @foreach($popularTags as $pt)
                                                        <button type="button" @click="addTag('{{ $pt }}')" class="px-2 py-0.5 text-[10px] bg-indigo-50 text-indigo-600 hover:bg-indigo-100 dark:bg-indigo-900/30 dark:text-indigo-400 dark:hover:bg-indigo-900/50 rounded-full transition-colors border border-indigo-100 dark:border-indigo-800/30 focus:outline-none">
                                                            + {{ $pt }}
                                                        </button>
                                                    @endforeach
                                                </div>
                                            @endif
                                        </div>
                                    </flux:field>
                                    
                                    <flux:field>
                                        <flux:label>Isi Konten <span class="text-red-500">*</span></flux:label>
                                        <div class="h-[400px] w-full max-w-full mt-2 mb-1 overflow-hidden relative border border-zinc-200 dark:border-zinc-700 rounded-lg flex flex-col" wire:ignore>
                                            <x-rich-editor wire:model="content" height="100%" />
                                        </div>
                                        <flux:error name="content" />
                                        <p class="text-xs text-zinc-500">Gunakan toolbar untuk menyisipkan tabel, gambar, atau pemformatan teks lanjutan.</p>
                                    </flux:field>
                                    
                                    <flux:switch wire:model="is_active" label="Template Aktif" />
                                    
                                    <div class="flex justify-end gap-2 pt-4 border-t border-zinc-200 dark:border-zinc-700">
                                        <flux:button variant="ghost" wire:click="$set('mode', 'list')"> Batal </flux:button>
                                        <flux:button icon="check" variant="primary" type="submit"> Simpan Template </flux:button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@once
<style>
    /* TinyMCE: pastikan editor mengisi container flex sepenuhnya dan tidak meluap di layar HP */
    .tox-tinymce { 
        height: 100% !important; 
        width: 100% !important; 
        max-width: 100% !important;
        border: none !important; 
    }
    
    /* Fix z-index popup TinyMCE (table menu, dll) agar tidak tertutup modal */
    .tox-tinymce-aux { z-index: 999999 !important; }
</style>
<script>
document.addEventListener('focusin', function (e) {
    if (e.target.closest('.tox-tinymce-aux, .moxman-window') !== null) {
        e.stopImmediatePropagation();
    }
});
</script>
@endonce
