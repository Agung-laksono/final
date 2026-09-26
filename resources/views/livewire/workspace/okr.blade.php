<?php

use Livewire\Volt\Component;
use Modules\Workspace\Models\Workspace;
use Modules\Workspace\Models\Objective;
use Modules\Workspace\Models\KeyResult;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

new class extends Component {
    public Workspace $workspace;
    public bool $isAdminOrLeader = false;

    // Objective form
    public $showObjectiveForm = false;
    public $editingObjective = null;
    public $objTitle = '';
    public $objDescription = '';
    public $objPeriod = 'Q1';
    public $objYear = '';
    public $objStartDate = '';
    public $objEndDate = '';
    public $objStatus = 'draft';

    // Key Result form
    public $showKrForm = false;
    public $activeObjectiveId = null;
    public $krTitle = '';
    public $krDescription = '';
    public $krType = 'numeric';
    public $krStartValue = 0;
    public $krTargetValue = 100;
    public $krCurrentValue = 0;
    public $krUnit = '';
    public $editingKr = null;

    public function mount(Workspace $workspace)
    {
        $this->workspace = $workspace;
        $this->objYear = date('Y');
    }

    public function saveObjective()
    {
        $this->validate([
            'objTitle'  => 'required|string|max:255',
            'objPeriod' => 'required|in:Q1,Q2,Q3,Q4,annual',
            'objYear'   => 'required|integer|min:2020|max:2050',
        ]);

        $data = [
            'workspace_id' => $this->workspace->id,
            'owner_id'     => Auth::id(),
            'title'        => $this->objTitle,
            'description'  => $this->objDescription,
            'period'       => $this->objPeriod,
            'year'         => $this->objYear,
            'status'       => $this->objStatus,
            'start_date'   => $this->objStartDate ?: null,
            'end_date'     => $this->objEndDate ?: null,
        ];

        if ($this->editingObjective) {
            Objective::findOrFail($this->editingObjective)->update($data);
            \Flux::toast('Objective berhasil diperbarui.', variant: 'success');
        } else {
            Objective::create($data);
            \Flux::toast('Objective berhasil ditambahkan!', variant: 'success');
        }

        $this->resetObjectiveForm();
    }

    public function editObjective($id)
    {
        $obj = Objective::findOrFail($id);
        $this->editingObjective = $id;
        $this->objTitle       = $obj->title;
        $this->objDescription = $obj->description;
        $this->objPeriod      = $obj->period;
        $this->objYear        = $obj->year;
        $this->objStatus      = $obj->status;
        $this->objStartDate   = $obj->start_date?->format('Y-m-d') ?? '';
        $this->objEndDate     = $obj->end_date?->format('Y-m-d') ?? '';
        $this->showObjectiveForm = true;
    }

    public function deleteObjective($id)
    {
        Objective::findOrFail($id)->delete();
        \Flux::toast('Objective dihapus.', variant: 'warning');
    }

    public function resetObjectiveForm()
    {
        $this->showObjectiveForm = false;
        $this->editingObjective  = null;
        $this->objTitle = $this->objDescription = $this->objStartDate = $this->objEndDate = '';
        $this->objPeriod  = 'Q1';
        $this->objYear    = date('Y');
        $this->objStatus  = 'draft';
    }

    public function openKrForm($objectiveId)
    {
        $this->activeObjectiveId = $objectiveId;
        $this->showKrForm = true;
        $this->editingKr  = null;
        $this->krTitle = $this->krDescription = $this->krUnit = '';
        $this->krType = 'numeric';
        $this->krStartValue = 0;
        $this->krTargetValue = 100;
        $this->krCurrentValue = 0;
    }

    public function editKr($id)
    {
        $kr = KeyResult::findOrFail($id);
        $this->editingKr         = $id;
        $this->activeObjectiveId = $kr->objective_id;
        $this->krTitle           = $kr->title;
        $this->krDescription     = $kr->description;
        $this->krType            = $kr->type;
        $this->krStartValue      = $kr->start_value;
        $this->krTargetValue     = $kr->target_value;
        $this->krCurrentValue    = $kr->current_value;
        $this->krUnit            = $kr->unit;
        $this->showKrForm        = true;
    }

    public function saveKr()
    {
        $this->validate([
            'krTitle'        => 'required|string|max:255',
            'krType'         => 'required|in:numeric,percentage,boolean',
            'krTargetValue'  => 'required|numeric',
            'krCurrentValue' => 'required|numeric',
        ]);

        $data = [
            'objective_id'  => $this->activeObjectiveId,
            'owner_id'      => Auth::id(),
            'title'         => $this->krTitle,
            'description'   => $this->krDescription,
            'type'          => $this->krType,
            'start_value'   => $this->krStartValue,
            'target_value'  => $this->krTargetValue,
            'current_value' => $this->krCurrentValue,
            'unit'          => $this->krUnit,
        ];

        if ($this->editingKr) {
            $kr = KeyResult::findOrFail($this->editingKr);
            $kr->update($data);
            $kr->objective->syncAutoStatus();
            \Flux::toast('Key Result diperbarui.', variant: 'success');
        } else {
            $kr = KeyResult::create($data);
            $kr->objective->syncAutoStatus();
            \Flux::toast('Key Result ditambahkan!', variant: 'success');
        }

        $this->showKrForm = false;
    }

    public function deleteKr($id)
    {
        $kr = KeyResult::findOrFail($id);
        $objectiveId = $kr->objective_id;
        $kr->delete();
        Objective::findOrFail($objectiveId)->syncAutoStatus();
        \Flux::toast('Key Result dihapus.', variant: 'warning');
    }

    public function updateKrValue($krId, $value)
    {
        $kr = KeyResult::findOrFail($krId);
        $kr->update(['current_value' => max(0, (float) $value)]);
        // Auto-update objective status based on new progress
        $kr->objective->syncAutoStatus();
    }

    public function with(): array
    {
        return [
            'objectives' => $this->workspace->objectives()
                ->with(['keyResults.owner', 'owner'])
                ->get(),
            'workspaceUsers' => $this->workspace->users,
            'periods' => ['Q1', 'Q2', 'Q3', 'Q4', 'annual'],
            'statusOptions' => ['draft' => 'Draft', 'on_track' => 'On Track', 'at_risk' => 'At Risk', 'achieved' => 'Achieved', 'failed' => 'Failed'],
        ];
    }
}
?>

<div>
    {{-- Header --}}
    <div class="mb-3">
        <div class="flex items-center justify-between gap-2 flex-wrap">
            <div>
                <h1 class="text-base sm:text-xl font-bold text-zinc-900 dark:text-white">Objectives & Key Results</h1>
                <p class="text-xs sm:text-sm text-zinc-500 dark:text-zinc-400 mt-0.5">Kelola target dan capaian utama tim Anda.</p>
            </div>
            @if($this->isAdminOrLeader)
            <flux:button wire:click="$set('showObjectiveForm', true)" variant="primary" icon="plus" size="sm">
                <span class="hidden sm:inline">Tambah Objective</span>
                <span class="sm:hidden">Tambah</span>
            </flux:button>
            @endif
        </div>

        {{-- Summary stats --}}
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 sm:gap-4 mt-3">
            @php
                $totalObj    = $objectives->count();
                $achieved    = $objectives->where('status', 'achieved')->count();
                $onTrack     = $objectives->where('status', 'on_track')->count();
                $atRisk      = $objectives->where('status', 'at_risk')->count();
                $avgProgress = $objectives->isNotEmpty() ? round($objectives->avg(fn($o) => $o->progress), 1) : 0;
            @endphp
            <div class="bg-gradient-to-br from-indigo-50 to-indigo-100 dark:from-indigo-900/20 dark:to-indigo-800/20 rounded-xl p-4 border border-indigo-200 dark:border-indigo-800/50">
                <div class="text-2xl font-bold text-indigo-700 dark:text-indigo-400">{{ $totalObj }}</div>
                <div class="text-xs text-indigo-600 dark:text-indigo-400 mt-1 font-medium">Total Objectives</div>
            </div>
            <div class="bg-gradient-to-br from-green-50 to-green-100 dark:from-green-900/20 dark:to-green-800/20 rounded-xl p-4 border border-green-200 dark:border-green-800/50">
                <div class="text-2xl font-bold text-green-700 dark:text-green-400">{{ $achieved }}</div>
                <div class="text-xs text-green-600 dark:text-green-400 mt-1 font-medium">Achieved</div>
            </div>
            <div class="bg-gradient-to-br from-blue-50 to-blue-100 dark:from-blue-900/20 dark:to-blue-800/20 rounded-xl p-4 border border-blue-200 dark:border-blue-800/50">
                <div class="text-2xl font-bold text-blue-700 dark:text-blue-400">{{ $onTrack }}</div>
                <div class="text-xs text-blue-600 dark:text-blue-400 mt-1 font-medium">On Track</div>
            </div>
            <div class="bg-gradient-to-br from-violet-50 to-violet-100 dark:from-violet-900/20 dark:to-violet-800/20 rounded-xl p-4 border border-violet-200 dark:border-violet-800/50">
                <div class="text-2xl font-bold text-violet-700 dark:text-violet-400">{{ $avgProgress }}%</div>
                <div class="text-xs text-violet-600 dark:text-violet-400 mt-1 font-medium">Rata-rata Progress</div>
            </div>
        </div>
    </div>

    <div class="p-3 sm:p-6 space-y-4 sm:space-y-6">

        @if($objectives->isEmpty())
        <div class="flex flex-col items-center justify-center py-24 text-center">
            <div class="w-20 h-20 bg-indigo-50 dark:bg-indigo-900/20 rounded-full flex items-center justify-center mb-5">
                <flux:icon.flag class="w-10 h-10 text-indigo-400" />
            </div>
            <h3 class="text-lg font-semibold text-zinc-800 dark:text-zinc-200 mb-2">Belum ada Objective</h3>
            <p class="text-sm text-zinc-500 dark:text-zinc-400 max-w-sm mb-6">Mulai dengan menambahkan tujuan besar yang ingin dicapai tim Anda di workspace ini.</p>
            @if($this->isAdminOrLeader)
            <flux:button wire:click="$set('showObjectiveForm', true)" variant="primary" icon="plus">Tambah Objective Pertama</flux:button>
            @endif
        </div>
        @else

        {{-- Objectives List --}}
        @foreach($objectives as $objective)
        @php
            $progress = $objective->progress;
            $krCount  = $objective->keyResults->count();
            $statusColors = [
                'draft'     => 'zinc',
                'on_track'  => 'blue',
                'at_risk'   => 'yellow',
                'achieved'  => 'green',
                'failed'    => 'red',
            ];
            $progressColor = $progress >= 100 ? 'bg-green-500' : ($progress >= 70 ? 'bg-blue-500' : ($progress >= 40 ? 'bg-yellow-500' : 'bg-red-500'));
        @endphp

        <div class="bg-white dark:bg-zinc-900 rounded-xl border border-zinc-200 dark:border-zinc-800 overflow-hidden shadow-sm hover:shadow-md transition-shadow">
            {{-- Objective Header --}}
            <div class="px-3 sm:px-6 py-3 sm:py-5">
                <div class="flex items-start justify-between gap-2">
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-3 flex-wrap mb-2">
                            <flux:badge color="{{ $statusColors[$objective->status] }}" size="sm">{{ str_replace('_', ' ', ucfirst($objective->status)) }}</flux:badge>
                            <span class="text-xs font-semibold bg-indigo-50 dark:bg-indigo-900/30 text-indigo-600 dark:text-indigo-400 px-2 py-0.5 rounded-full">
                                {{ strtoupper($objective->period) }} {{ $objective->year }}
                            </span>
                            <span class="text-xs text-zinc-500 dark:text-zinc-400">{{ $krCount }} Key Result{{ $krCount !== 1 ? 's' : '' }}</span>
                        </div>
                        <h3 class="text-lg font-bold text-zinc-900 dark:text-white leading-snug">{{ $objective->title }}</h3>
                        @if($objective->description)
                        <p class="text-sm text-zinc-500 dark:text-zinc-400 mt-1 line-clamp-2">{{ $objective->description }}</p>
                        @endif
                    </div>

                    @if($this->isAdminOrLeader)
                    <div class="flex items-center gap-2 shrink-0">
                        <flux:button wire:click="editObjective({{ $objective->id }})" variant="subtle" size="xs" icon="pencil-square" />
                        <flux:button wire:click="deleteObjective({{ $objective->id }})" variant="subtle" size="xs" icon="trash" class="text-red-500 hover:text-red-600" wire:confirm="Hapus objective ini beserta semua Key Results-nya?" />
                    </div>
                    @endif
                </div>

                {{-- Progress Bar --}}
                <div class="mt-4">
                    <div class="flex items-center justify-between mb-1.5">
                        <span class="text-xs text-zinc-500 dark:text-zinc-400">Overall Progress</span>
                        <span class="text-sm font-bold text-zinc-800 dark:text-zinc-200">{{ $progress }}%</span>
                    </div>
                    <div class="h-2.5 bg-zinc-100 dark:bg-zinc-800 rounded-full overflow-hidden">
                        <div class="h-full {{ $progressColor }} rounded-full transition-all duration-700 ease-out" style="width: {{ $progress }}%"></div>
                    </div>
                </div>
            </div>

            {{-- Key Results --}}
            @if($objective->keyResults->isNotEmpty())
            <div class="border-t border-zinc-100 dark:border-zinc-800 divide-y divide-zinc-50 dark:divide-zinc-800/50">
                @foreach($objective->keyResults as $kr)
                @php
                    $krProgress = $kr->progress;
                    $krBar = $krProgress >= 100 ? 'bg-green-500' : ($krProgress >= 70 ? 'bg-blue-500' : ($krProgress >= 40 ? 'bg-yellow-500' : 'bg-red-500'));
                @endphp
                <div class="px-3 sm:px-6 py-3 sm:py-4 hover:bg-zinc-50 dark:hover:bg-zinc-800/50 transition-colors group">
                    <div class="flex flex-col sm:flex-row sm:items-center gap-3 sm:gap-4">
                        <div class="flex items-center gap-3 flex-1 min-w-0">
                            <div class="w-8 h-8 rounded-lg bg-indigo-50 dark:bg-indigo-900/30 flex items-center justify-center shrink-0">
                                <flux:icon.key class="w-4 h-4 text-indigo-500" />
                            </div>
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-2 mb-1">
                                    <span class="text-sm font-semibold text-zinc-800 dark:text-zinc-200 truncate">{{ $kr->title }}</span>
                                    @if($kr->owner)
                                    <span class="text-xs text-zinc-400 shrink-0 hidden sm:inline">{{ $kr->owner->name }}</span>
                                    @endif
                                </div>
                                <div class="flex items-center gap-3 sm:hidden mb-1">
                                    <div class="flex-1 h-1.5 bg-zinc-100 dark:bg-zinc-800 rounded-full overflow-hidden">
                                        <div class="h-full {{ $krBar }} rounded-full transition-all duration-500" style="width: {{ $krProgress }}%"></div>
                                    </div>
                                    <span class="text-xs font-bold shrink-0 {{ $krProgress >= 100 ? 'text-green-600' : 'text-zinc-500 dark:text-zinc-400' }}">{{ $krProgress }}%</span>
                                </div>
                                <div class="flex items-center gap-1">
                                    @if($this->isAdminOrLeader)
                                    <input type="number"
                                        wire:change="updateKrValue({{ $kr->id }}, $event.target.value)"
                                        value="{{ $kr->current_value }}"
                                        class="w-14 sm:w-16 text-xs text-right bg-transparent border border-zinc-200 dark:border-zinc-700 hover:border-zinc-300 dark:hover:border-zinc-600 focus:border-indigo-400 dark:focus:border-indigo-500 rounded px-1.5 py-0.5 text-zinc-700 dark:text-zinc-300 focus:outline-none transition-colors"
                                        min="0" max="{{ $kr->target_value }}" step="any"
                                    />
                                    @else
                                    <span class="text-xs font-semibold text-zinc-700 dark:text-zinc-300">{{ $kr->current_value }}</span>
                                    @endif
                                    <span class="text-xs text-zinc-400">/ {{ number_format($kr->target_value) }}{{ $kr->unit ? ' ' . $kr->unit : '' }}</span>
                                </div>
                            </div>
                        </div>

                        <div class="hidden sm:flex items-center gap-3 flex-1">
                            <div class="flex-1 h-1.5 bg-zinc-100 dark:bg-zinc-800 rounded-full overflow-hidden">
                                <div class="h-full {{ $krBar }} rounded-full transition-all duration-500" style="width: {{ $krProgress }}%"></div>
                            </div>
                            <span class="text-xs font-bold shrink-0 {{ $krProgress >= 100 ? 'text-green-600' : 'text-zinc-500 dark:text-zinc-400' }}">{{ $krProgress }}%</span>
                        </div>

                        @if($this->isAdminOrLeader)
                        <div class="flex sm:opacity-0 group-hover:opacity-100 transition-opacity justify-end shrink-0 gap-1 mt-1 sm:mt-0">
                            <flux:button wire:click="editKr({{ $kr->id }})" variant="subtle" size="xs" icon="pencil-square" />
                            <flux:button wire:click="deleteKr({{ $kr->id }})" variant="subtle" size="xs" icon="trash" class="text-red-500" wire:confirm="Hapus Key Result ini?" />
                        </div>
                        @endif
                    </div>
                </div>
                @endforeach
            </div>
            @endif

            {{-- Add KR button --}}
            @if($this->isAdminOrLeader)
            <div class="px-6 py-3 bg-zinc-50/50 dark:bg-zinc-800/30 border-t border-zinc-100 dark:border-zinc-800">
                <button wire:click="openKrForm({{ $objective->id }})"
                        class="text-sm text-indigo-600 dark:text-indigo-400 hover:text-indigo-700 dark:hover:text-indigo-300 font-medium flex items-center gap-1.5 transition-colors">
                    <flux:icon.plus class="w-4 h-4" />
                    Tambah Key Result
                </button>
            </div>
            @endif
        </div>
        @endforeach
        @endif
    </div>

    {{-- Objective Modal --}}
    <flux:modal wire:model="showObjectiveForm" class="max-w-lg w-full">
        <div class="p-6">
            <h3 class="text-lg font-bold text-zinc-900 dark:text-white mb-5">
                {{ $editingObjective ? 'Edit Objective' : 'Tambah Objective Baru' }}
            </h3>
            <form wire:submit="saveObjective" class="space-y-4">
                <flux:input wire:model="objTitle" label="Judul Objective *" placeholder="Contoh: Tingkatkan Omzet Q4 2025" />
                <flux:textarea wire:model="objDescription" label="Deskripsi (opsional)" rows="3" placeholder="Jelaskan tujuan ini..." />
                <div class="grid grid-cols-2 gap-4">
                    <flux:select wire:model="objPeriod" label="Periode">
                        <flux:select.option value="Q1">Q1 (Jan–Mar)</flux:select.option>
                        <flux:select.option value="Q2">Q2 (Apr–Jun)</flux:select.option>
                        <flux:select.option value="Q3">Q3 (Jul–Sep)</flux:select.option>
                        <flux:select.option value="Q4">Q4 (Okt–Des)</flux:select.option>
                        <flux:select.option value="annual">Annual (Tahunan)</flux:select.option>
                    </flux:select>
                    <flux:input type="number" wire:model="objYear" label="Tahun" min="2020" max="2050" />
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <flux:input type="date" wire:model="objStartDate" label="Tanggal Mulai" />
                    <flux:input type="date" wire:model="objEndDate" label="Tanggal Selesai" />
                </div>
                <flux:select wire:model="objStatus" label="Status">
                    @foreach($statusOptions as $val => $label)
                    <flux:select.option value="{{ $val }}">{{ $label }}</flux:select.option>
                    @endforeach
                </flux:select>
                <div class="flex justify-end gap-3 pt-2">
                    <flux:button type="button" wire:click="resetObjectiveForm" variant="ghost">Batal</flux:button>
                    <flux:button type="submit" variant="primary">{{ $editingObjective ? 'Simpan Perubahan' : 'Buat Objective' }}</flux:button>
                </div>
            </form>
        </div>
    </flux:modal>

    {{-- Key Result Modal --}}
    <flux:modal wire:model="showKrForm" class="max-w-lg w-full">
        <div class="p-6">
            <h3 class="text-lg font-bold text-zinc-900 dark:text-white mb-5">
                {{ $editingKr ? 'Edit Key Result' : 'Tambah Key Result' }}
            </h3>
            <form wire:submit="saveKr" class="space-y-4">
                <flux:input wire:model="krTitle" label="Judul Key Result *" placeholder="Contoh: Raih 100 pelanggan baru" />
                <flux:textarea wire:model="krDescription" label="Deskripsi" rows="2" />
                <div class="grid grid-cols-2 gap-4">
                    <flux:select wire:model="krType" label="Tipe">
                        <flux:select.option value="numeric">Numerik (angka)</flux:select.option>
                        <flux:select.option value="percentage">Persentase (%)</flux:select.option>
                        <flux:select.option value="boolean">Boolean (ya/tidak)</flux:select.option>
                    </flux:select>
                    <flux:input wire:model="krUnit" label="Satuan" placeholder="pelanggan, %, juta Rp" />
                </div>
                <div class="grid grid-cols-3 gap-4">
                    <flux:input type="number" wire:model="krStartValue" label="Nilai Awal" step="any" />
                    <flux:input type="number" wire:model="krCurrentValue" label="Nilai Saat Ini" step="any" />
                    <flux:input type="number" wire:model="krTargetValue" label="Nilai Target *" step="any" />
                </div>
                <div class="flex justify-end gap-3 pt-2">
                    <flux:button type="button" wire:click="$set('showKrForm', false)" variant="ghost">Batal</flux:button>
                    <flux:button type="submit" variant="primary">{{ $editingKr ? 'Simpan Perubahan' : 'Tambah Key Result' }}</flux:button>
                </div>
            </form>
        </div>
    </flux:modal>
</div>
