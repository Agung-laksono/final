<?php

use Livewire\Volt\Component;
use Livewire\WithFileUploads;
use Livewire\Attributes\On;
use Livewire\Attributes\Rule;
use Modules\Purchase\Models\Vendor;
use Illuminate\Support\Str;
use Flux\Flux;

new class extends Component {
    use WithFileUploads;

    public $vendor_id = null;
    
    #[Rule('required|string|max:255')]
    public $name = '';
    
    #[Rule('required|string|max:50')]
    public $phone = '';
    
    #[Rule('nullable|string')]
    public $address = '';
    
    #[Rule('required|string|max:255')]
    public $province = '';
    
    #[Rule('required|string|max:255')]
    public $city = '';
    
    #[Rule('nullable|string|max:255')]
    public $district = '';
    
    #[Rule('nullable|string|max:255')]
    public $village = '';
    
    // Arrays for Livewire dropdowns
    public $provincesList = [];
    public $citiesList = [];
    public $districtsList = [];
    public $villagesList = [];
    
    // Livewire Search Properties
    public $searchQuery = '';
    public $searchResults = [];
    
    #[Rule('nullable|url')]
    public $maps_link = '';
    
    #[Rule('nullable')]
    public $image = null;
    
    #[Rule('required|string|max:100')]
    public $type = 'Supplier';

    #[Rule('nullable|string|max:100')]
    public $custom_type = '';

    public $customOptions = [];

    #[On('open-vendor-modal')]
    public function openModal($id = null)
    {
        $this->resetValidation();
        
        if ($id) {
            $vendor = Vendor::findOrFail($id);
            $this->vendor_id = $vendor->id;
            $this->name = $vendor->name;
            $this->phone = $vendor->phone;
            $this->address = $vendor->address;
            $this->province = $vendor->province;
            $this->city = $vendor->city;
            $this->district = $vendor->district;
            $this->village = $vendor->village;
            $this->maps_link = $vendor->maps_link;
            
            if (!in_array($vendor->type, ['Supplier', 'Pengrajin', 'Ekspedisi', 'Lainnya'])) {
                $this->customOptions = [$vendor->type];
                $this->type = $vendor->type;
            } else {
                $this->type = $vendor->type;
                $this->customOptions = [];
            }
            $this->custom_type = '';
            $this->image = $vendor->image;
            
            // Re-populate lists so dropdowns aren't empty!
            $this->provincesList = $this->getRegions('provinces.csv');
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
        } else {
            $this->vendor_id = null;
            $this->name = '';
            $this->phone = '';
            $this->address = '';
            $this->province = '';
            $this->city = '';
            $this->district = '';
            $this->village = '';
            $this->maps_link = '';
            $this->type = 'Supplier';
            $this->custom_type = '';
            $this->customOptions = [];
            $this->image = null;
            
            $this->provincesList = $this->getRegions('provinces.csv');
            $this->citiesList = [];
            $this->districtsList = [];
            $this->villagesList = [];
        }
        
        $this->searchQuery = '';
        $this->searchResults = [];
        
        Flux::modal('vendor-form-modal')->show();
    }

    public function addCustomType()
    {
        $type = trim($this->custom_type);
        if (!empty($type) && !in_array($type, ['Supplier', 'Pengrajin', 'Ekspedisi', 'Lainnya'])) {
            if (!in_array($type, $this->customOptions)) {
                $this->customOptions[] = $type;
            }
            $this->type = $type;
            $this->custom_type = '';
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
    }

    public function updatedSearchQuery($value) {
        if (strlen($value) < 3) {
            $this->searchResults = [];
            return;
        }

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
                    if (stripos($name, $value) !== false) {
                        $results[] = [
                            'type' => 'Provinsi',
                            'village' => '',
                            'district' => '',
                            'city' => '',
                            'province' => $name
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
                    if (stripos($name, $value) !== false) {
                        $prov = $provMap[$parentId] ?? null;
                        if ($prov) {
                            $results[] = [
                                'type' => 'Kota/Kabupaten',
                                'village' => '',
                                'district' => '',
                                'city' => $name,
                                'province' => $prov['name']
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
                    if (count($results) < 20 && stripos($name, $value) !== false) {
                        $reg = $regMap[$parentId] ?? null;
                        if ($reg) {
                            $prov = $provMap[$reg['parent_id']] ?? null;
                            if ($prov) {
                                $results[] = [
                                    'type' => 'Kecamatan',
                                    'village' => '',
                                    'district' => $name,
                                    'city' => $reg['name'],
                                    'province' => $prov['name']
                                ];
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
                        if (stripos($name, $value) !== false) {
                            $distId = trim($row[1]);
                            $dist = $distMap[$distId] ?? null;
                            if ($dist) {
                                $reg = $regMap[$dist['parent_id']] ?? null;
                                if ($reg) {
                                    $prov = $provMap[$reg['parent_id']] ?? null;
                                    if ($prov) {
                                        $results[] = [
                                            'type' => 'Desa/Kelurahan',
                                            'village' => $name,
                                            'district' => $dist['name'],
                                            'city' => $reg['name'],
                                            'province' => $prov['name']
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
    }

    // --- END REGION CSV HELPERS ---

    public function save() {
        $validated = $this->validate();

        if ($validated['type'] === 'Lainnya') {
            $this->addError('type', 'Silakan masukkan tipe manual dan klik ikon centang.');
            return;
        }
        unset($validated['custom_type']);

        // Handle base64 image from x-image-cropper
        if (is_string($this->image) && str_starts_with($this->image, 'data:image/webp;base64,')) {
            $base64Image = substr($this->image, strpos($this->image, ',') + 1);
            $imageData = base64_decode($base64Image);
            
            $filename = 'vendors/' . uniqid() . '.webp';
            \Illuminate\Support\Facades\Storage::disk('public')->put($filename, $imageData);
            
            $validated['image'] = $filename;
        } elseif ($this->image === null) {
            $validated['image'] = null;
        } else {
            unset($validated['image']);
        }

        if ($this->vendor_id) {
            $vendor = Vendor::find($this->vendor_id);
            
            // Delete old image if a new one is uploaded or if it's cleared
            if ($vendor->image && (array_key_exists('image', $validated) || $this->image === null)) {
                \Illuminate\Support\Facades\Storage::disk('public')->delete($vendor->image);
            }
            
            $vendor->update($validated);
            $actionType = 'diperbarui';
        } else {
            $vendor = Vendor::create($validated);
            $actionType = 'ditambahkan';
        }

        Flux::modal('vendor-form-modal')->close();
        $this->dispatch('vendor-saved', vendorId: $vendor->id);
        
        \App\Events\VendorUpdated::safeDispatch("Data vendor {$validated['name']} berhasil {$actionType}");
    }
};
?>

<div>
    <flux:modal name="vendor-form-modal" class="md:max-w-3xl space-y-6 px-3">
        <div>
            <flux:heading size="lg">{{ $vendor_id ? 'Edit Vendor' : 'Tambah Vendor Baru' }}</flux:heading>
        </div>
        
        <form wire:submit="save" class="flex flex-col h-full">
            <div class="grid grid-cols-1 md:grid-cols-12 gap-x-8 gap-y-6">
                
                {{-- KOLOM KIRI: Foto (Lebar 4/12) --}}
                <div class="md:col-span-4 space-y-6 flex flex-col items-center">
                    
                    {{-- Area Foto (Dibatasi proporsional) --}}
                    <div class="w-full flex flex-col items-center justify-center pt-2 pb-2">
                        <div class="w-48 aspect-square">
                            <x-image-cropper wire:model="image" :image="$image" accept="image/*" />
                        </div>
                        <span class="text-xs text-zinc-500 mt-3 font-medium text-center">Foto/Logo Vendor <span class="text-zinc-400 font-normal">(Opsional)</span></span>
                    </div>

                    <div class="w-full space-y-3" x-data="contactPickerData()">
                        <div class="flex items-end gap-2">
                            <div class="flex-1">
                                <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-1">Nama Vendor / Supplier <span class="text-red-500">*</span></label>
                                <flux:input wire:model="name" placeholder="Contoh: PT. Abadi Makmur" required />
                            </div>
                            
                            {{-- Native Contact Picker Button (Hanya muncul jika didukung browser/HP) --}}
                            <div x-show="supported" x-cloak class="shrink-0 mb-[2px]">
                                <flux:button type="button" @click="pickContact()" icon="users" class="h-10 w-10 p-0" title="Pilih dari Kontak HP" />
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-1">Nomor Telepon/WA <span class="text-red-500">*</span></label>
                            <flux:input wire:model="phone" placeholder="Contoh: 08123456789" icon="phone" required />
                        </div>
                    </div>

                    <div class="w-full space-y-3" x-data="{ selectedType: @entangle('type') }">
                        <div>
                            <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-1">Tipe Vendor <span class="text-red-500">*</span></label>
                            <flux:select wire:model.live="type" required>
                                <flux:select.option value="Supplier">Supplier</flux:select.option>
                            <flux:select.option value="Pengrajin">Pengrajin</flux:select.option>
                            <flux:select.option value="Ekspedisi">Ekspedisi</flux:select.option>
                            @foreach($customOptions as $opt)
                                <flux:select.option value="{{ $opt }}">{{ $opt }}</flux:select.option>
                            @endforeach
                            <flux:select.option value="Lainnya">Lainnya (Ketik Manual)</flux:select.option>
                        </flux:select>
                        </div>
                        
                        <div x-show="selectedType === 'Lainnya'" x-cloak x-transition>
                            <div class="flex items-center gap-2">
                                <div class="flex-1">
                                    <flux:input wire:model="custom_type" wire:keydown.enter.prevent="addCustomType" placeholder="Ketik tipe baru..." />
                                </div>
                                <flux:button wire:click="addCustomType" variant="primary" icon="check" class="shrink-0 h-10 w-10 p-0 flex items-center justify-center" title="Tambahkan ke daftar" />
                            </div>
                        </div>
                    </div>
                </div>

                {{-- KOLOM KANAN: Data Profil & Alamat (Lebar 8/12) --}}
                <div class="md:col-span-8 space-y-5">
                    
                    <flux:separator text="Alamat Lengkap" />

                    {{-- Alamat --}}
                    <div>
                        <flux:textarea wire:model="address" label="Jalan/Detail Alamat (Opsional)" placeholder="Contoh: Jl. Sudirman No. 123" rows="2" />
                    </div>

                    <!-- WILAYAH SECTION -->
                    <div class="mt-4 pt-4 border-t border-zinc-200 dark:border-zinc-800">
                        <div class="flex items-center gap-2 mb-4">
                            <flux:icon.map-pin class="w-4 h-4 text-zinc-500" />
                            <h3 class="text-sm font-semibold text-zinc-800 dark:text-zinc-200">Informasi Wilayah</h3>
                        </div>

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

                    <div>
                        <flux:input wire:model="maps_link" label="Tautan Google Maps (Opsional)" placeholder="Contoh: https://goo.gl/maps/..." icon="map-pin" />
                    </div>
                </div>
            </div>

            {{-- FOOTER --}}
            <div class="flex justify-end mt-8 pt-4 border-t border-zinc-200 dark:border-zinc-800 gap-2">
                <flux:modal.close>
                    <flux:button variant="ghost" class="w-full sm:w-auto">Batal</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="primary" wire:loading.attr="disabled" class="w-full sm:w-auto">
                    <span wire:loading.remove wire:target="save">{{ $vendor_id ? 'Simpan Perubahan' : 'Simpan Vendor' }}</span>
                    <span wire:loading wire:target="save" class="flex items-center gap-2">
                        <svg class="animate-spin w-4 h-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                        Menyimpan...
                    </span>
                </flux:button>
            </div>
        </form>
    </flux:modal>

<script>
    document.addEventListener('livewire:initialized', () => {
        // Fallback or additional logic if needed
    });

    window.contactPickerData = function() {
        return {
            supported: 'contacts' in navigator && 'ContactsManager' in window,
            async pickContact() {
                try {
                    const properties = ['name', 'tel', 'icon'];
                    const opts = { multiple: false };
                    const contacts = await navigator.contacts.select(properties, opts);
                    if (contacts.length > 0) {
                        if (contacts[0].name && contacts[0].name.length > 0) {
                            this.$wire.name = contacts[0].name[0];
                        }
                        if (contacts[0].tel && contacts[0].tel.length > 0) {
                            this.$wire.phone = contacts[0].tel[0].replace(/[^0-9+]/g, '');
                        }
                        if (contacts[0].icon && contacts[0].icon.length > 0) {
                            const blob = contacts[0].icon[0];
                            const reader = new FileReader();
                            reader.onload = (e) => {
                                const img = new Image();
                                img.onload = () => {
                                    const canvas = document.createElement('canvas');
                                    canvas.width = img.width;
                                    canvas.height = img.height;
                                    const ctx = canvas.getContext('2d');
                                    ctx.drawImage(img, 0, 0);
                                    this.$wire.set('image', canvas.toDataURL('image/webp', 0.8));
                                };
                                img.src = e.target.result;
                            };
                            reader.readAsDataURL(blob);
                        }
                    }
                } catch (ex) {
                    console.log('Contact picker error:', ex);
                }
            }
        };
    };
</script>
</div>
