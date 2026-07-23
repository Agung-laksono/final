<?php

use Livewire\Volt\Component;
use Livewire\Attributes\On;

new class extends Component {
    public $scope = 'default';

    public $province = '';
    public $city = '';
    public $district = '';
    public $village = '';

    public $provincesList = [];
    public $citiesList = [];
    public $districtsList = [];
    public $villagesList = [];

    public $searchQuery = '';
    public $searchResults = [];

    private function getPdo() {
        $pdo = new \PDO('sqlite:' . database_path('wilayah.sqlite'));
        // Load custom functions or anything else if needed, but not necessary.
        return $pdo;
    }

    public function mount($scope = 'default', $province = '', $city = '', $district = '', $village = '') {
        $this->scope = $scope;
        $this->hydrateWilayah($province, $city, $district, $village);
    }

    public function getListeners() {
        return [
            "hydrate-wilayah-{$this->scope}" => 'hydrateWilayah'
        ];
    }

    public function hydrateWilayah($province = '', $city = '', $district = '', $village = '') {
        $this->province = $province;
        $this->city = $city;
        $this->district = $district;
        $this->village = $village;
        
        $this->searchQuery = '';
        $this->searchResults = [];

        $this->provincesList = $this->getRegions('province');
        $this->citiesList = [];
        $this->districtsList = [];
        $this->villagesList = [];

        if ($this->province) {
            $provId = $this->getIdByName('province', $this->province);
            $this->citiesList = $this->getRegions('regency', $provId);
        }
        if ($this->city) {
            $cityId = $this->getIdByName('regency', $this->city);
            $this->districtsList = $this->getRegions('district', $cityId);
        }
        if ($this->district) {
            $distId = $this->getIdByName('district', $this->district);
            $this->villagesList = $this->getRegions('village', $distId);
        }
    }

    // --- REGION SQLITE HELPERS ---

    private function getRegions($type, $parentId = null) {
        $pdo = $this->getPdo();
        if ($parentId === null) {
            $stmt = $pdo->prepare("SELECT id, name FROM regions WHERE type = ? ORDER BY name ASC");
            $stmt->execute([$type]);
        } else {
            $stmt = $pdo->prepare("SELECT id, parent_id, name FROM regions WHERE type = ? AND parent_id = ? ORDER BY name ASC");
            $stmt->execute([$type, $parentId]);
        }
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    private function getIdByName($type, $name) {
        if (!$name) return null;
        $pdo = $this->getPdo();
        $stmt = $pdo->prepare("SELECT id FROM regions WHERE type = ? AND name LIKE ? LIMIT 1");
        $stmt->execute([$type, $name]);
        return $stmt->fetchColumn();
    }

    public function updatedProvince($value) {
        $this->city = '';
        $this->district = '';
        $this->village = '';
        $this->citiesList = [];
        $this->districtsList = [];
        $this->villagesList = [];
        
        if ($value) {
            $provId = $this->getIdByName('province', $value);
            if ($provId) {
                $this->citiesList = $this->getRegions('regency', $provId);
            }
        }
        $this->notifyParent();
    }

    public function updatedCity($value) {
        $this->district = '';
        $this->village = '';
        $this->districtsList = [];
        $this->villagesList = [];
        
        if ($value) {
            $cityId = $this->getIdByName('regency', $value);
            if ($cityId) {
                $this->districtsList = $this->getRegions('district', $cityId);
            }
        }
        $this->notifyParent();
    }

    public function updatedDistrict($value) {
        $this->village = '';
        $this->villagesList = [];
        
        if ($value) {
            $distId = $this->getIdByName('district', $value);
            if ($distId) {
                $this->villagesList = $this->getRegions('village', $distId);
            }
        }
        $this->notifyParent();
    }

    public function updatedVillage($value) {
        $this->notifyParent();
    }

    private function notifyParent() {
        $this->dispatch("wilayah-updated-{$this->scope}", 
            province: $this->province, 
            city: $this->city, 
            district: $this->district, 
            village: $this->village
        );
    }

    public function updatedSearchQuery($val) {
        if (strlen($val) < 3) {
            $this->searchResults = [];
            return;
        }
        
        $pdo = $this->getPdo();
        $keywords = explode(' ', strtolower(trim($val)));
        
        $sql = "
            SELECT v.name as village, d.name as district, r.name as city, p.name as province,
                   v.id as village_id, d.id as district_id, r.id as city_id, p.id as province_id
            FROM regions v
            JOIN regions d ON v.parent_id = d.id
            JOIN regions r ON d.parent_id = r.id
            JOIN regions p ON r.parent_id = p.id
            WHERE v.type = 'village'
        ";
        $params = [];
        foreach ($keywords as $kw) {
            if (empty($kw)) continue;
            $sql .= " AND (v.name LIKE ? OR d.name LIKE ? OR r.name LIKE ? OR p.name LIKE ?)";
            $k = "%{$kw}%";
            array_push($params, $k, $k, $k, $k);
        }
        $sql .= " LIMIT 20";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        
        $results = [];
        foreach ($rows as $row) {
            $results[] = [
                'type' => 'Desa/Kelurahan',
                'village' => $row['village'],
                'district' => $row['district'],
                'city' => $row['city'],
                'province' => $row['province'],
                'province_id' => $row['province_id'],
                'city_id' => $row['city_id'],
                'district_id' => $row['district_id']
            ];
        }
        $this->searchResults = $results;
    }

    public function selectResult($index) {
        $res = $this->searchResults[$index] ?? null;
        if (!$res) return;

        $this->province = $res['province'];
        $this->city = $res['city'];
        $this->district = $res['district'];
        $this->village = $res['village'];
        
        if (!empty($res['province_id'])) {
            $this->citiesList = $this->getRegions('regency', $res['province_id']);
        }
        if (!empty($res['city_id'])) {
            $this->districtsList = $this->getRegions('district', $res['city_id']);
        }
        if (!empty($res['district_id'])) {
            $this->villagesList = $this->getRegions('village', $res['district_id']);
        }

        $this->searchQuery = '';
        $this->searchResults = [];
        
        $this->notifyParent();
    }
};
?>

<div>
    {{-- Fitur Pencarian Pintar (Livewire) --}}
    <div class="mb-6 relative">
        <flux:input wire:model.live.debounce.500ms="searchQuery" label="Pencarian Pintar Wilayah" placeholder="Ketik nama provinsi, kota, kecamatan, atau desa..." icon="magnifying-glass" />
        
        <div wire:loading wire:target="searchQuery" class="absolute right-3 top-9 text-xs text-zinc-400">Mencari...</div>

        @if(count($searchResults) > 0)
        <div class="absolute z-50 w-full mt-1 bg-white dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 rounded-lg shadow-lg max-h-60 overflow-y-auto">
            @foreach($searchResults as $index => $res)
                <div wire:click="selectResult({{ $index }})" class="px-4 py-3 cursor-pointer hover:bg-zinc-50 dark:hover:bg-zinc-700 border-b border-zinc-100 dark:border-zinc-700 last:border-0 transition-colors">
                    <div class="flex items-center gap-2 mb-1">
                        <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider {{ 
                            $res['type'] === 'Provinsi' ? 'bg-purple-100 text-purple-700 dark:bg-purple-900/30 dark:text-purple-400' :
                            ($res['type'] === 'Kota/Kabupaten' ? 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400' :
                            ($res['type'] === 'Kecamatan' ? 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400' :
                            'bg-zinc-100 text-zinc-700 dark:bg-zinc-800 dark:text-zinc-400')) 
                        }}">{{ $res['type'] }}</span>
                        <div class="font-bold text-sm text-zinc-900 dark:text-zinc-100">
                            {{ $res['village'] ?: ($res['district'] ?: ($res['city'] ?: $res['province'])) }}
                        </div>
                    </div>
                    <div class="text-xs text-zinc-500 dark:text-zinc-400 mt-1">
                        @if($res['type'] === 'Provinsi')
                            Tingkat Provinsi
                        @elseif($res['type'] === 'Kota/Kabupaten')
                            Prov. {{ $res['province'] }}
                        @elseif($res['type'] === 'Kecamatan')
                            {{ $res['city'] }}, Prov. {{ $res['province'] }}
                        @else
                            Kec. {{ $res['district'] }}, {{ $res['city'] }}, {{ $res['province'] }}
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
        @elseif(strlen($searchQuery) >= 3)
        <div class="absolute z-50 w-full mt-1 bg-white dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 rounded-lg shadow-lg p-3 text-center text-sm text-zinc-500" wire:loading.remove wire:target="searchQuery">
            Wilayah tidak ditemukan. Silakan gunakan pilihan manual di bawah.
        </div>
        @endif
    </div>

    <div class="space-y-4">
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-4">
            <div>
                <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-1">Provinsi</label>
                <select wire:model.live="province" class="w-full h-10 px-3 py-2 border border-zinc-200 dark:border-zinc-700 rounded-lg text-sm bg-white dark:bg-zinc-900 text-zinc-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-blue-500 disabled:opacity-50">
                    <option value="">-- Pilih Provinsi --</option>
                    @foreach($provincesList as $p)
                        <option value="{{ $p['name'] }}">{{ $p['name'] }}</option>
                    @endforeach
                </select>
            </div>
            
            <div>
                <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-1">Kota/Kabupaten</label>
                <select wire:model.live="city" @disabled(empty($citiesList)) class="w-full h-10 px-3 py-2 border border-zinc-200 dark:border-zinc-700 rounded-lg text-sm bg-white dark:bg-zinc-900 text-zinc-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-blue-500 disabled:opacity-50">
                    <option value="">-- Pilih Kota --</option>
                    @foreach($citiesList as $c)
                        <option value="{{ $c['name'] }}">{{ $c['name'] }}</option>
                    @endforeach
                </select>
            </div>
        </div>
        
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-1">Kecamatan</label>
                <select wire:model.live="district" @disabled(empty($districtsList)) class="w-full h-10 px-3 py-2 border border-zinc-200 dark:border-zinc-700 rounded-lg text-sm bg-white dark:bg-zinc-900 text-zinc-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-blue-500 disabled:opacity-50">
                    <option value="">-- Pilih Kecamatan --</option>
                    @foreach($districtsList as $d)
                        <option value="{{ $d['name'] }}">{{ $d['name'] }}</option>
                    @endforeach
                </select>
            </div>
            
            <div>
                <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-1">Kelurahan/Desa</label>
                <select wire:model.live="village" @disabled(empty($villagesList)) class="w-full h-10 px-3 py-2 border border-zinc-200 dark:border-zinc-700 rounded-lg text-sm bg-white dark:bg-zinc-900 text-zinc-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-blue-500 disabled:opacity-50">
                    <option value="">-- Pilih Desa --</option>
                    @foreach($villagesList as $v)
                        <option value="{{ $v['name'] }}">{{ $v['name'] }}</option>
                    @endforeach
                </select>
            </div>
        </div>
    </div>
</div>
