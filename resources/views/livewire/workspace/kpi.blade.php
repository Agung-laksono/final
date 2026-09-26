<?php

use Livewire\Volt\Component;
use Modules\Workspace\Models\Workspace;
use Modules\Workspace\Models\Kpi;
use Modules\Workspace\Models\KpiLog;
use Modules\Workspace\Models\KeyResult;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

new class extends Component {
    public Workspace $workspace;
    public bool $isAdminOrLeader = false;

    public $showForm = false;
    public $editingKpi = null;
    public $kpiTitle = '';
    public $kpiDescription = '';
    public $kpiFormula = '';
    public $kpiTargetValue = 100;
    public $kpiCurrentValue = 0;
    public $kpiUnit = '';
    public $kpiPeriod = 'monthly';
    public $kpiTrend = 'up_is_better';
    public $kpiKeyResultId = '';
    public $kpiIsActive = true;
    public $filterActive = 'active'; // 'active' | 'archived' | 'all'

    public $viewingHistoryKpi = null;
    public $kpiHistoryLogs = [];

    public function mount(Workspace $workspace)
    {
        $this->workspace = $workspace;
    }

    public function saveKpi()
    {
        $this->validate([
            'kpiTitle'        => 'required|string|max:255',
            'kpiTargetValue'  => 'required|numeric',
            'kpiCurrentValue' => 'required|numeric',
            'kpiPeriod'       => 'required|in:daily,weekly,monthly,quarterly,annual',
        ]);

        $data = [
            'workspace_id'  => $this->workspace->id,
            'owner_id'      => Auth::id(),
            'key_result_id' => $this->kpiKeyResultId ?: null,
            'title'         => $this->kpiTitle,
            'description'   => $this->kpiDescription,
            'formula'       => $this->kpiFormula,
            'target_value'  => $this->kpiTargetValue,
            'current_value' => $this->kpiCurrentValue,
            'unit'          => $this->kpiUnit,
            'period'        => $this->kpiPeriod,
            'trend'         => $this->kpiTrend,
            'is_active'     => $this->kpiIsActive,
        ];

        if ($this->editingKpi) {
            $kpi = Kpi::findOrFail($this->editingKpi);
            $oldValue = $kpi->current_value;
            $kpi->update($data);
            
            if ($oldValue != $this->kpiCurrentValue) {
                KpiLog::create([
                    'kpi_id' => $kpi->id,
                    'user_id' => Auth::id(),
                    'old_value' => $oldValue,
                    'new_value' => $this->kpiCurrentValue,
                    'notes' => 'Diperbarui via form',
                ]);
            }
            \Flux::toast('KPI diperbarui.', variant: 'success');
        } else {
            $kpi = Kpi::create($data);
            KpiLog::create([
                'kpi_id' => $kpi->id,
                'user_id' => Auth::id(),
                'old_value' => null,
                'new_value' => $this->kpiCurrentValue,
                'notes' => 'KPI dibuat',
            ]);
            \Flux::toast('KPI berhasil ditambahkan!', variant: 'success');
        }

        $this->resetForm();
    }

    public function editKpi($id)
    {
        $kpi = Kpi::findOrFail($id);
        $this->editingKpi       = $id;
        $this->kpiTitle         = $kpi->title;
        $this->kpiDescription   = $kpi->description;
        $this->kpiFormula       = $kpi->formula;
        $this->kpiTargetValue   = $kpi->target_value;
        $this->kpiCurrentValue  = $kpi->current_value;
        $this->kpiUnit          = $kpi->unit;
        $this->kpiPeriod        = $kpi->period;
        $this->kpiTrend         = $kpi->trend;
        $this->kpiIsActive      = (bool) $kpi->is_active;
        $this->kpiKeyResultId   = $kpi->key_result_id ?? '';
        $this->showForm         = true;
    }

    public function updateCurrentValue($id, $value)
    {
        $kpi = Kpi::findOrFail($id);
        $oldValue = $kpi->current_value;
        $newValue = max(0, (float) $value);
        
        if ($oldValue != $newValue) {
            $kpi->update(['current_value' => $newValue]);
            KpiLog::create([
                'kpi_id' => $kpi->id,
                'user_id' => Auth::id(),
                'old_value' => $oldValue,
                'new_value' => $newValue,
                'notes' => 'Diperbarui via input cepat',
            ]);
        }
    }

    public function viewHistory($id)
    {
        $this->viewingHistoryKpi = Kpi::findOrFail($id);
        $this->kpiHistoryLogs = KpiLog::where('kpi_id', $id)
            ->with('user')
            ->orderByDesc('created_at')
            ->get();
        
        $this->dispatch('open-kpi-history-modal');
    }

    public function deleteKpi($id)
    {
        Kpi::findOrFail($id)->delete();
        \Flux::toast('KPI dihapus.', variant: 'warning');
    }

    public function resetForm()
    {
        $this->showForm = false;
        $this->editingKpi = null;
        $this->kpiTitle = $this->kpiDescription = $this->kpiFormula = $this->kpiUnit = '';
        $this->kpiTargetValue = 100;
        $this->kpiCurrentValue = 0;
        $this->kpiPeriod = 'monthly';
        $this->kpiTrend = 'up_is_better';
        $this->kpiKeyResultId = '';
        $this->kpiIsActive = true;
    }

    public function toggleActive($id)
    {
        $kpi = Kpi::findOrFail($id);
        $kpi->update(['is_active' => !$kpi->is_active]);
        \Flux::toast($kpi->is_active ? 'KPI diarsipkan.' : 'KPI diaktifkan kembali.', variant: 'success');
    }

    public function with(): array
    {
        // Get all Key Results from all Objectives in this workspace for linking
        $keyResults = KeyResult::whereHas('objective', fn($q) => $q->where('workspace_id', $this->workspace->id))->get();

        $kpisQuery = $this->workspace->kpis()->with('owner', 'keyResult');
        if ($this->filterActive === 'active') {
            $kpisQuery->where('is_active', true);
        } elseif ($this->filterActive === 'archived') {
            $kpisQuery->where('is_active', false);
        }

        return [
            'kpis'       => $kpisQuery->get(),
            'keyResults' => $keyResults,
            'periodLabels' => [
                'daily'     => 'Harian',
                'weekly'    => 'Mingguan',
                'monthly'   => 'Bulanan',
                'quarterly' => 'Kwartal',
                'annual'    => 'Tahunan',
            ],
        ];
    }
}
?>

<div>
    {{-- Header --}}
    <div class="mb-3">
        <div class="flex items-center justify-between gap-2 flex-wrap">
            <div>
                <h1 class="text-base sm:text-xl font-bold text-zinc-900 dark:text-white">Key Performance Indicators</h1>
                <p class="text-xs sm:text-sm text-zinc-500 dark:text-zinc-400 mt-0.5">Pantau dan kelola metrik kinerja utama.</p>
            </div>
            @if($this->isAdminOrLeader)
            <flux:button wire:click="$set('showForm', true)" variant="primary" icon="plus" size="sm">
                <span class="hidden sm:inline">Tambah KPI</span>
                <span class="sm:hidden">Tambah</span>
            </flux:button>
            @endif
        </div>

        {{-- Filter Tabs --}}
        <div class="flex items-center gap-1.5 mt-3">
            @foreach(['active' => 'Aktif', 'archived' => 'Diarsipkan', 'all' => 'Semua'] as $val => $label)
            <button wire:click="$set('filterActive', '{{ $val }}')"
                    class="px-3 py-1 rounded-full text-xs font-medium transition-all
                           {{ $filterActive === '$val' ? 'bg-indigo-100 dark:bg-indigo-900/30 text-indigo-700 dark:text-indigo-300' : 'text-zinc-500 dark:text-zinc-400 hover:bg-zinc-100 dark:hover:bg-zinc-800' }}"
                    :class="$wire.filterActive === '{{ $val }}' ? 'bg-indigo-100 dark:bg-indigo-900/30 text-indigo-700 dark:text-indigo-300' : 'text-zinc-500 dark:text-zinc-400 hover:bg-zinc-100 dark:hover:bg-zinc-800'">
                {{ $label }}
            </button>
            @endforeach
        </div>

        {{-- Summary --}}
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 sm:gap-4 mt-3">
            @php
                $totalKpi   = $kpis->count();
                $avgProg    = $kpis->isNotEmpty() ? round($kpis->avg(fn($k) => $k->progress), 1) : 0;
                $onTarget   = $kpis->filter(fn($k) => $k->progress >= 100)->count();
                $atRisk     = $kpis->filter(fn($k) => $k->progress < 70)->count();
            @endphp
            <div class="bg-gradient-to-br from-emerald-50 to-emerald-100 dark:from-emerald-900/20 dark:to-emerald-800/20 rounded-xl p-4 border border-emerald-200 dark:border-emerald-800/50">
                <div class="text-2xl font-bold text-emerald-700 dark:text-emerald-400">{{ $totalKpi }}</div>
                <div class="text-xs text-emerald-600 dark:text-emerald-400 mt-1 font-medium">Total KPI</div>
            </div>
            <div class="bg-gradient-to-br from-green-50 to-green-100 dark:from-green-900/20 dark:to-green-800/20 rounded-xl p-4 border border-green-200 dark:border-green-800/50">
                <div class="text-2xl font-bold text-green-700 dark:text-green-400">{{ $onTarget }}</div>
                <div class="text-xs text-green-600 dark:text-green-400 mt-1 font-medium">Tercapai (≥100%)</div>
            </div>
            <div class="bg-gradient-to-br from-red-50 to-red-100 dark:from-red-900/20 dark:to-red-800/20 rounded-xl p-4 border border-red-200 dark:border-red-800/50">
                <div class="text-2xl font-bold text-red-700 dark:text-red-400">{{ $atRisk }}</div>
                <div class="text-xs text-red-600 dark:text-red-400 mt-1 font-medium">Di Bawah Target (&lt;70%)</div>
            </div>
            <div class="bg-gradient-to-br from-violet-50 to-violet-100 dark:from-violet-900/20 dark:to-violet-800/20 rounded-xl p-4 border border-violet-200 dark:border-violet-800/50">
                <div class="text-2xl font-bold text-violet-700 dark:text-violet-400">{{ $avgProg }}%</div>
                <div class="text-xs text-violet-600 dark:text-violet-400 mt-1 font-medium">Rata-rata Pencapaian</div>
            </div>
        </div>
    </div>

    <div class="p-3 sm:p-6">
        @if($kpis->isEmpty())
        <div class="flex flex-col items-center justify-center py-24 text-center">
            <div class="w-20 h-20 bg-emerald-50 dark:bg-emerald-900/20 rounded-full flex items-center justify-center mb-5">
                <flux:icon.chart-bar class="w-10 h-10 text-emerald-400" />
            </div>
            <h3 class="text-lg font-semibold text-zinc-800 dark:text-zinc-200 mb-2">Belum ada KPI</h3>
            <p class="text-sm text-zinc-500 dark:text-zinc-400 max-w-sm mb-6">Tambahkan indikator kinerja untuk mengukur pencapaian tim secara terukur.</p>
            @if($this->isAdminOrLeader)
            <flux:button wire:click="$set('showForm', true)" variant="primary" icon="plus">Tambah KPI Pertama</flux:button>
            @endif
        </div>
        @else

        {{-- KPI Grid --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-3 sm:gap-5">
            @foreach($kpis as $kpi)
            @php
                $progress   = $kpi->progress;
                $barColor   = $progress >= 100 ? 'bg-green-500' : ($progress >= 70 ? 'bg-blue-500' : ($progress >= 40 ? 'bg-yellow-500' : 'bg-red-500'));
                $badgeColor = $kpi->status_color;
            @endphp
            <div class="bg-white dark:bg-zinc-900 rounded-xl border border-zinc-200 dark:border-zinc-800 p-3 sm:p-5 hover:shadow-md transition-shadow group">
                <div class="flex items-start justify-between gap-2 mb-3">
                    <div class="flex-1">
                        <h4 class="text-sm font-bold text-zinc-900 dark:text-white leading-snug">{{ $kpi->title }}</h4>
                        @if($kpi->description)
                        <p class="text-xs text-zinc-500 dark:text-zinc-400 mt-1 line-clamp-2">{{ $kpi->description }}</p>
                        @endif
                    </div>
                    @if($this->isAdminOrLeader)
                    <div class="flex items-center gap-1 sm:opacity-0 group-hover:opacity-100 transition-opacity">
                        <flux:button wire:click="viewHistory({{ $kpi->id }})" variant="subtle" size="xs" icon="clock" tooltip="Histori" />
                        <flux:button wire:click="editKpi({{ $kpi->id }})" variant="subtle" size="xs" icon="pencil-square" />
                        <flux:button wire:click="toggleActive({{ $kpi->id }})" variant="subtle" size="xs"
                                     icon="{{ $kpi->is_active ? 'archive-box' : 'arrow-up-tray' }}"
                                     tooltip="{{ $kpi->is_active ? 'Arsipkan' : 'Aktifkan Kembali' }}" />
                        <flux:button wire:click="deleteKpi({{ $kpi->id }})" variant="subtle" size="xs" icon="trash" class="text-red-500" wire:confirm="Hapus KPI ini?" />
                    </div>
                    @else
                    <div class="flex items-center gap-1 sm:opacity-0 group-hover:opacity-100 transition-opacity">
                        <flux:button wire:click="viewHistory({{ $kpi->id }})" variant="subtle" size="xs" icon="clock" tooltip="Histori" />
                    </div>
                    @endif
                </div>

                {{-- Badges --}}
                <div class="flex flex-wrap gap-2 mb-4">
                    <flux:badge color="{{ $badgeColor }}" size="sm">{{ $progress }}%</flux:badge>
                    <flux:badge color="zinc" size="sm">{{ $periodLabels[$kpi->period] ?? $kpi->period }}</flux:badge>
                    @if($kpi->keyResult)
                    <flux:badge color="indigo" size="sm">🔗 {{ Str::limit($kpi->keyResult->title, 20) }}</flux:badge>
                    @endif
                </div>

                {{-- Progress bar --}}
                <div class="mb-4">
                    <div class="h-2 bg-zinc-100 dark:bg-zinc-800 rounded-full overflow-hidden">
                        <div class="h-full {{ $barColor }} rounded-full transition-all duration-700" style="width: {{ $progress }}%"></div>
                    </div>
                </div>

                {{-- Value --}}
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                    <div class="text-xs text-zinc-500 dark:text-zinc-400">
                        Target: <span class="font-semibold text-zinc-700 dark:text-zinc-300">{{ number_format($kpi->target_value) }}{{ $kpi->unit ? ' ' . $kpi->unit : '' }}</span>
                    </div>
                    <div class="flex items-center gap-1">
                        <span class="text-xs text-zinc-500 hidden sm:inline">Saat ini:</span>
                        @if($this->isAdminOrLeader)
                        <input type="number"
                            wire:change="updateCurrentValue({{ $kpi->id }}, $event.target.value)"
                            value="{{ $kpi->current_value }}"
                            class="w-16 sm:w-20 text-xs text-right bg-transparent border border-zinc-200 dark:border-zinc-700 hover:border-zinc-300 dark:hover:border-zinc-600 focus:border-emerald-400 dark:focus:border-emerald-500 rounded px-1.5 py-0.5 text-zinc-700 dark:text-zinc-300 focus:outline-none transition-colors font-semibold"
                            step="any" min="0"
                        />
                        @else
                        <span class="text-xs font-semibold text-zinc-700 dark:text-zinc-300 ml-0 sm:ml-1">{{ $kpi->current_value }}</span>
                        @endif
                        @if($kpi->unit)<span class="text-xs text-zinc-400">{{ $kpi->unit }}</span>@endif
                    </div>
                </div>

                @if($kpi->formula)
                <div class="mt-3 pt-3 border-t border-zinc-100 dark:border-zinc-800">
                    <p class="text-xs text-zinc-400 italic">📐 {{ $kpi->formula }}</p>
                </div>
                @endif
            </div>
            @endforeach
        </div>
        @endif
    </div>

    {{-- KPI Modal --}}
    <flux:modal wire:model="showForm" class="max-w-lg w-full">
        <div class="p-6">
            <h3 class="text-lg font-bold text-zinc-900 dark:text-white mb-5">
                {{ $editingKpi ? 'Edit KPI' : 'Tambah KPI Baru' }}
            </h3>
            <form wire:submit="saveKpi" class="space-y-4">
                <flux:input wire:model="kpiTitle" label="Nama KPI *" placeholder="Contoh: Conversion Rate, Revenue per User" />
                <flux:textarea wire:model="kpiDescription" label="Deskripsi" rows="2" />
                <flux:input wire:model="kpiFormula" label="Formula (opsional)" placeholder="Contoh: (Penjualan / Prospek) × 100%" />

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 sm:gap-4">
                    <flux:select wire:model="kpiPeriod" label="Periode Pengukuran">
                        <flux:select.option value="daily">Harian</flux:select.option>
                        <flux:select.option value="weekly">Mingguan</flux:select.option>
                        <flux:select.option value="monthly">Bulanan</flux:select.option>
                        <flux:select.option value="quarterly">Kwartal</flux:select.option>
                        <flux:select.option value="annual">Tahunan</flux:select.option>
                    </flux:select>
                    <flux:select wire:model="kpiTrend" label="Arah yang Baik">
                        <flux:select.option value="up_is_better">↑ Semakin tinggi semakin baik</flux:select.option>
                        <flux:select.option value="down_is_better">↓ Semakin rendah semakin baik</flux:select.option>
                    </flux:select>
                </div>

                <div class="grid grid-cols-3 gap-3">
                    <flux:input type="number" wire:model="kpiCurrentValue" label="Nilai Saat Ini" step="any" />
                    <flux:input type="number" wire:model="kpiTargetValue" label="Target *" step="any" />
                    <flux:input wire:model="kpiUnit" label="Satuan" placeholder="%" />
                </div>

                @if($keyResults->isNotEmpty())
                <flux:select wire:model="kpiKeyResultId" label="Kaitkan ke Key Result (opsional)">
                    <flux:select.option value="">-- Tidak dikaitkan --</flux:select.option>
                    @foreach($keyResults as $kr)
                    <flux:select.option value="{{ $kr->id }}">{{ $kr->title }}</flux:select.option>
                    @endforeach
                </flux:select>
                @endif

                <div class="flex justify-end gap-3 pt-2">
                    <flux:button type="button" wire:click="resetForm" variant="ghost">Batal</flux:button>
                    <flux:button type="submit" variant="primary">{{ $editingKpi ? 'Simpan Perubahan' : 'Tambah KPI' }}</flux:button>
                </div>
            </form>
        </div>
    </flux:modal>

    {{-- History Modal --}}
    <flux:modal name="kpi-history-modal" class="w-full sm:w-[500px]" x-on:open-kpi-history-modal.window="$nextTick(() => $flux.modal('kpi-history-modal').show())">
        <div class="p-4 sm:p-6">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <h2 class="text-lg font-bold text-zinc-900 dark:text-white">Histori Perubahan KPI</h2>
                    @if($viewingHistoryKpi)
                    <p class="text-sm text-zinc-500">{{ $viewingHistoryKpi->title }}</p>
                    @endif
                </div>
                <flux:modal.close>
                    <flux:button variant="subtle" size="sm" icon="x-mark" />
                </flux:modal.close>
            </div>

            <div class="space-y-4 max-h-[60vh] overflow-y-auto custom-scrollbar pr-2">
                @if(empty($kpiHistoryLogs))
                <div class="text-center py-6 text-zinc-500 text-sm">Belum ada histori perubahan untuk KPI ini.</div>
                @else
                    @foreach($kpiHistoryLogs as $log)
                    <div class="relative pl-6 pb-4 border-l border-zinc-200 dark:border-zinc-700 last:border-l-0 last:pb-0">
                        <div class="absolute left-[-5px] top-1.5 w-2.5 h-2.5 rounded-full bg-indigo-500 ring-4 ring-white dark:ring-zinc-900"></div>
                        <div class="flex justify-between items-start mb-1">
                            <span class="text-xs font-semibold text-zinc-900 dark:text-white">
                                {{ $log->user ? $log->user->name : 'Sistem' }}
                            </span>
                            <span class="text-[10px] text-zinc-500">{{ $log->created_at->diffForHumans() }}</span>
                        </div>
                        <div class="text-sm text-zinc-700 dark:text-zinc-300">
                            @if($log->old_value !== null)
                                Mengubah nilai dari <span class="font-mono font-medium text-red-500">{{ (float)$log->old_value }}</span> 
                                menjadi <span class="font-mono font-medium text-emerald-500">{{ (float)$log->new_value }}</span>
                            @else
                                Menetapkan nilai awal menjadi <span class="font-mono font-medium text-emerald-500">{{ (float)$log->new_value }}</span>
                            @endif
                        </div>
                        @if($log->notes)
                        <div class="mt-1 text-xs text-zinc-500 italic">{{ $log->notes }}</div>
                        @endif
                    </div>
                    @endforeach
                @endif
            </div>
        </div>
    </flux:modal>
</div>
