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

        $this->provincesList = $this->getRegions('provinces.csv');
        $this->citiesList = [];
        $this->districtsList = [];
        $this->villagesList = [];

        if ($this->province) {
            $provId = $this->getIdByName('provinces.csv', $this->province);
            $this->citiesList = $this->getRegions('regencies.csv', $provId);
        }
        if ($this->city) {
            $cityId = $this->getIdByName('regencies.csv', $this->city);
            $this->districtsList = $this->getRegions('districts.csv', $cityId);
        }
        if ($this->district) {
            $distId = $this->getIdByName('districts.csv', $this->district);
            $this->villagesList = $this->getRegions('villages.csv', $distId);
        }
    }

    // --- REGION CSV HELPERS ---

    private function getRegions($filename, $parentId = null) {
        $path = public_path('api-wilayah/data/' . $filename);
        if (!file_exists($path)) return [];
        
        $data = [];
        if (($handle = fopen($path, "r")) !== FALSE) {
            while (($row = fgetcsv($handle, 1000, ",")) !== FALSE) {
                if (count($row) >= 2) {
                    if ($parentId === null) {
                        if (count($row) == 2) {
                            $data[] = ['id' => trim($row[0]), 'name' => trim($row[1])];
                        }
                    } else {
                        if (count($row) >= 3 && trim($row[1]) == $parentId) {
                            $data[] = ['id' => trim($row[0]), 'parent_id' => trim($row[1]), 'name' => trim($row[2])];
                        }
                    }
                }
            }
            fclose($handle);
        }
        
        usort($data, function($a, $b) {
            return strcmp($a['name'], $b['name']);
        });
        
        return $data;
    }

    private function getIdByName($filename, $name) {
        if (!$name) return null;
        $path = public_path('api-wilayah/data/' . $filename);
        if (!file_exists($path)) return null;
        
        if (($handle = fopen($path, "r")) !== FALSE) {
            while (($row = fgetcsv($handle, 1000, ",")) !== FALSE) {
                $rowName = count($row) >= 3 ? trim($row[2]) : trim($row[1]);
                if (strcasecmp($rowName, trim($name)) === 0) {
                    fclose($handle);
                    return trim($row[0]);
                }
            }
            fclose($handle);
        }
        return null;
    }

    public function updatedProvince($value) {
        $this->city = '';
        $this->district = '';
        $this->village = '';
        $this->citiesList = [];
        $this->districtsList = [];
        $this->villagesList = [];
        
        if ($value) {
            $provId = $this->getIdByName('provinces.csv', $value);
            if ($provId) {
                $this->citiesList = $this->getRegions('regencies.csv', $provId);
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
            $cityId = $this->getIdByName('regencies.csv', $value);
            if ($cityId) {
                $this->districtsList = $this->getRegions('districts.csv', $cityId);
            }
        }
        $this->notifyParent();
    }

    public function updatedDistrict($value) {
        $this->village = '';
        $this->villagesList = [];
        
        if ($value) {
            $distId = $this->getIdByName('districts.csv', $value);
            if ($distId) {
                $this->villagesList = $this->getRegions('villages.csv', $distId);
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

    public function updatedSearchQuery($value) {
        if (strlen($value) < 3) {
            $this->searchResults = [];
            return;
        }

        $keywords = explode(' ', strtolower(trim($value)));
        $results = [];
        
        $villagesPath = public_path('api-wilayah/data/villages.csv');
        $districtsPath = public_path('api-wilayah/data/districts.csv');
        $regenciesPath = public_path('api-wilayah/data/regencies.csv');
        $provincesPath = public_path('api-wilayah/data/provinces.csv');

        // 1. Scan Provinces
        $provMap = [];
        if (($handle = fopen($provincesPath, "r")) !== FALSE) {
            while (($row = fgetcsv($handle, 1000, ",")) !== FALSE) {
                if (count($row) >= 2) {
                    $name = trim($row[1]);
                    $provMap[trim($row[0])] = ['name' => $name];
                    
                    $fullString = strtolower($name);
                    $match = true;
                    foreach ($keywords as $kw) {
                        if (strpos($fullString, $kw) === false) { $match = false; break; }
                    }
                    if ($match) {
                        $results[] = [
                            'type' => 'Provinsi', 'village' => '', 'district' => '', 'city' => '', 'province' => $name
                        ];
                    }
                }
            }
            fclose($handle);
        }

        // 2. Scan Regencies
        $regMap = [];
        if (($handle = fopen($regenciesPath, "r")) !== FALSE) {
            while (($row = fgetcsv($handle, 1000, ",")) !== FALSE) {
                if (count($row) >= 3) {
                    $name = trim($row[2]);
                    $parentId = trim($row[1]);
                    $regMap[trim($row[0])] = ['parent_id' => $parentId, 'name' => $name];
                    
                    $prov = $provMap[$parentId] ?? null;
                    if ($prov) {
                        $fullString = strtolower($name . ' ' . $prov['name']);
                        $match = true;
                        foreach ($keywords as $kw) {
                            if (strpos($fullString, $kw) === false) { $match = false; break; }
                        }
                        if ($match) {
                            $results[] = [
                                'type' => 'Kota/Kabupaten', 'village' => '', 'district' => '', 'city' => $name, 'province' => $prov['name']
                            ];
                        }
                    }
                }
            }
            fclose($handle);
        }

        // 3. Scan Districts
        $distMap = [];
        if (($handle = fopen($districtsPath, "r")) !== FALSE) {
            while (($row = fgetcsv($handle, 1000, ",")) !== FALSE) {
                if (count($row) >= 3) {
                    $name = trim($row[2]);
                    $parentId = trim($row[1]);
                    $distMap[trim($row[0])] = ['parent_id' => $parentId, 'name' => $name];
                    
                    if (count($results) < 20) {
                        $reg = $regMap[$parentId] ?? null;
                        if ($reg) {
                            $prov = $provMap[$reg['parent_id']] ?? null;
                            if ($prov) {
                                $fullString = strtolower($name . ' ' . $reg['name'] . ' ' . $prov['name']);
                                $match = true;
                                foreach ($keywords as $kw) {
                                    if (strpos($fullString, $kw) === false) { $match = false; break; }
                                }
                                if ($match) {
                                    $results[] = [
                                        'type' => 'Kecamatan', 'village' => '', 'district' => $name, 'city' => $reg['name'], 'province' => $prov['name']
                                    ];
                                }
                            }
                        }
                    }
                }
            }
            fclose($handle);
        }

        // 4. Scan Villages (only if we need more results)
        if (count($results) < 20) {
            if (($handle = fopen($villagesPath, "r")) !== FALSE) {
                while (($row = fgetcsv($handle, 1000, ",")) !== FALSE) {
                    if (count($row) >= 3) {
                        $name = trim($row[2]);
                        $distId = trim($row[1]);
                        $dist = $distMap[$distId] ?? null;
                        if ($dist) {
                            $reg = $regMap[$dist['parent_id']] ?? null;
                            if ($reg) {
                                $prov = $provMap[$reg['parent_id']] ?? null;
                                if ($prov) {
                                    $fullString = strtolower($name . ' ' . $dist['name'] . ' ' . $reg['name'] . ' ' . $prov['name']);
                                    $match = true;
                                    foreach ($keywords as $kw) {
                                        if (strpos($fullString, $kw) === false) { $match = false; break; }
                                    }
                                    if ($match) {
                                        $results[] = [
                                            'type' => 'Desa/Kelurahan', 'village' => $name, 'district' => $dist['name'], 'city' => $reg['name'], 'province' => $prov['name']
                                        ];
                                        if (count($results) >= 20) break;
                                    }
                                }
                            }
                        }
                    }
                }
                fclose($handle);
            }
        }

        $this->searchResults = $results;
    }

    public function selectResult($index) {
        $res = $this->searchResults[$index] ?? null;
        if (!$res) return;

        // Auto-fill and cascade
        $this->province = $res['province'];
        $this->updatedProvince($this->province);
        
        $this->city = $res['city'];
        $this->updatedCity($this->city);
        
        $this->district = $res['district'];
        $this->updatedDistrict($this->district);
        
        $this->village = $res['village'];

        $this->searchQuery = '';
        $this->searchResults = [];
        
        $this->notifyParent();
    }
};
?>

<div>
    {{-- Fitur Pencarian Pintar (Livewire) --}}
    <div class="mb-6 relative">
        <flux:input wire:model.live.debounce.500ms="searchQuery" label="Pencarian Pintar Wilayah (Opsional)" placeholder="Ketik nama provinsi, kota, kecamatan, atau desa..." icon="magnifying-glass" />
        
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
                <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-1">Provinsi <span class="text-red-500">*</span></label>
                <select required wire:model.live="province" class="w-full h-10 px-3 py-2 border border-zinc-200 dark:border-zinc-700 rounded-lg text-sm bg-white dark:bg-zinc-900 text-zinc-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-blue-500 disabled:opacity-50">
                    <option value="">-- Pilih Provinsi --</option>
                    @foreach($provincesList as $p)
                        <option value="{{ $p['name'] }}">{{ $p['name'] }}</option>
                    @endforeach
                </select>
            </div>
            
            <div>
                <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-1">Kota/Kabupaten <span class="text-red-500">*</span></label>
                <select required wire:model.live="city" @disabled(empty($citiesList)) class="w-full h-10 px-3 py-2 border border-zinc-200 dark:border-zinc-700 rounded-lg text-sm bg-white dark:bg-zinc-900 text-zinc-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-blue-500 disabled:opacity-50">
                    <option value="">-- Pilih Kota --</option>
                    @foreach($citiesList as $c)
                        <option value="{{ $c['name'] }}">{{ $c['name'] }}</option>
                    @endforeach
                </select>
            </div>
        </div>
        
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-1">Kecamatan <span class="text-zinc-400 font-normal">(Opsional)</span></label>
                <select wire:model.live="district" @disabled(empty($districtsList)) class="w-full h-10 px-3 py-2 border border-zinc-200 dark:border-zinc-700 rounded-lg text-sm bg-white dark:bg-zinc-900 text-zinc-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-blue-500 disabled:opacity-50">
                    <option value="">-- Pilih Kecamatan --</option>
                    @foreach($districtsList as $d)
                        <option value="{{ $d['name'] }}">{{ $d['name'] }}</option>
                    @endforeach
                </select>
            </div>
            
            <div>
                <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-1">Kelurahan/Desa <span class="text-zinc-400 font-normal">(Opsional)</span></label>
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
