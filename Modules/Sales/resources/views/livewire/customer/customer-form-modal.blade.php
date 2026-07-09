<?php

use Livewire\Volt\Component;
use Livewire\WithFileUploads;
use Livewire\Attributes\On;
use Livewire\Attributes\Rule;
use Modules\Sales\Models\Customer;
use Illuminate\Support\Str;
use Flux\Flux;

new class extends Component {
    use WithFileUploads;

    public $customer_id = null;
    
    #[Rule('required|string|max:255')]
    public $name = '';
    
    #[Rule('nullable|string|max:255')]
    public $company = '';
    
    #[Rule('required|string|max:50')]
    public $phone = '';
    
    #[Rule('required|string|max:100')]
    public $type = 'Reguler';
    
    #[Rule('nullable|string|max:100')]
    public $custom_type = '';
    
    public $customOptions = [];
    
    #[Rule('nullable|string')]
    public $address = '';
    
    #[Rule('nullable|string|max:255')]
    public $province = '';
    
    #[Rule('nullable|string|max:255')]
    public $city = '';
    
    #[Rule('nullable|string|max:255')]
    public $district = '';
    
    #[Rule('nullable|string|max:255')]
    public $village = '';
    
    #[Rule('nullable')]
    public $image = null;

    #[On('open-customer-modal')]
    public function openModal($id = null)
    {
        $this->resetValidation();
        
        if ($id) {
            $customer = Customer::findOrFail($id);
            $this->customer_id = $customer->id;
            $this->name = $customer->name;
            $this->company = $customer->company;
            $this->phone = $customer->phone;
            $this->address = $customer->address;
            $this->province = $customer->province;
            $this->city = $customer->city;
            $this->district = $customer->district;
            $this->village = $customer->village;
            $this->image = $customer->image;
            
            if (!in_array($customer->type, ['Reguler', 'VIP', 'Grosir', 'Distributor'])) {
                $this->customOptions = [$customer->type];
                $this->type = $customer->type;
            } else {
                $this->type = $customer->type;
                $this->customOptions = [];
            }
            $this->custom_type = '';
        } else {
            $this->customer_id = null;
            $this->name = '';
            $this->company = '';
            $this->phone = '';
            $this->address = '';
            $this->province = '';
            $this->city = '';
            $this->district = '';
            $this->village = '';
            $this->image = null;
            $this->type = 'Reguler';
            $this->custom_type = '';
            $this->customOptions = [];
        }
        
        $this->dispatch('hydrate-wilayah-customer-form', 
            province: $this->province, 
            city: $this->city, 
            district: $this->district, 
            village: $this->village
        );
        
        Flux::modal('customer-form-modal')->show();
    }

    public function addCustomType()
    {
        $type = trim($this->custom_type);
        if (!empty($type) && !in_array($type, ['Reguler', 'VIP', 'Grosir', 'Distributor'])) {
            if (!in_array($type, $this->customOptions)) {
                $this->customOptions[] = $type;
            }
            $this->type = $type;
            $this->custom_type = '';
        }
    }

    #[On('wilayah-updated-customer-form')]
    public function updateWilayah($province, $city, $district, $village) {
        $this->province = $province;
        $this->city = $city;
        $this->district = $district;
        $this->village = $village;
    }

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
            
            $filename = 'customers/' . uniqid() . '.webp';
            \Illuminate\Support\Facades\Storage::disk('public')->put($filename, $imageData);
            
            $validated['image'] = $filename;
        } elseif ($this->image === null) {
            $validated['image'] = null;
        } else {
            unset($validated['image']);
        }

        if ($this->customer_id) {
            $customer = Customer::find($this->customer_id);
            
            // Delete old image if a new one is uploaded or if it's cleared
            if ($customer->image && (array_key_exists('image', $validated) || $this->image === null)) {
                \Illuminate\Support\Facades\Storage::disk('public')->delete($customer->image);
            }
            
            $customer->update($validated);
            $actionType = 'diperbarui';
        } else {
            $customer = Customer::create($validated);
            $actionType = 'ditambahkan';
        }

        Flux::modal('customer-form-modal')->close();
        $this->dispatch('customer-saved', customerId: $customer->id);
    }
};
?>

<div>
    <flux:modal name="customer-form-modal" class="md:max-w-3xl space-y-6 px-3">
        <div>
            <flux:heading size="lg">{{ $customer_id ? 'Edit Pelanggan' : 'Tambah Pelanggan Baru' }}</flux:heading>
        </div>
        
        <form wire:submit="save" class="flex flex-col h-full">
            <div class="grid grid-cols-1 md:grid-cols-12 gap-x-8 gap-y-6">
                
                {{-- KOLOM KIRI: Foto (Lebar 4/12) --}}
                <div class="md:col-span-4 flex flex-col items-center justify-start pt-2">
                    <div class="w-full flex flex-col items-center justify-center">
                        <div class="w-48 aspect-square">
                            <x-image-cropper id="customer-cropper" wire:model="image" :image="$image" accept="image/*" />
                        </div>
                        <span class="text-xs text-zinc-500 mt-3 font-medium text-center">Foto/Logo Pelanggan <span class="text-zinc-400 font-normal">(Opsional)</span></span>
                    </div>
                </div>

                {{-- KOLOM KANAN: Data Dasar (Lebar 8/12) --}}
                <div class="md:col-span-8">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div class="sm:col-span-2" x-data="contactPickerData()">
                            <div class="flex items-end gap-2">
                                <div class="flex-1">
                                    <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-1">Nama Lengkap <span class="text-red-500">*</span></label>
                                    <flux:input wire:model="name" placeholder="Contoh: Budi Santoso" required />
                                </div>
                                <div x-show="supported" x-cloak class="shrink-0 mb-[2px]">
                                    <flux:button type="button" @click="pickContact()" icon="users" class="h-10 w-10 p-0" title="Pilih dari Kontak HP" />
                                </div>
                            </div>
                        </div>

                        <div x-data="{ selectedType: @entangle('type') }">
                            <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-1">Tipe Pelanggan <span class="text-red-500">*</span></label>
                            <flux:select wire:model.live="type" required>
                                <flux:select.option value="Reguler">Reguler</flux:select.option>
                                <flux:select.option value="VIP">VIP</flux:select.option>
                                <flux:select.option value="Grosir">Grosir</flux:select.option>
                                <flux:select.option value="Distributor">Distributor</flux:select.option>
                                @foreach($customOptions as $opt)
                                    <flux:select.option value="{{ $opt }}">{{ $opt }}</flux:select.option>
                                @endforeach
                                <flux:select.option value="Lainnya">Lainnya (Ketik Manual)</flux:select.option>
                            </flux:select>
                            
                            <div x-show="selectedType === 'Lainnya'" x-cloak x-transition class="mt-2">
                                <div class="flex items-center gap-2">
                                    <div class="flex-1">
                                        <flux:input wire:model="custom_type" wire:keydown.enter.prevent="addCustomType" placeholder="Ketik tipe baru..." />
                                    </div>
                                    <flux:button wire:click="addCustomType" variant="primary" icon="check" class="shrink-0 h-10 w-10 p-0 flex items-center justify-center" title="Tambahkan ke daftar" />
                                </div>
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-1">Perusahaan / Toko</label>
                            <flux:input wire:model="company" placeholder="Contoh: PT. Maju Jaya" icon="building-office" />
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-1">Nomor Telepon/WA <span class="text-red-500">*</span></label>
                            <flux:input wire:model="phone" placeholder="Contoh: 08123456789" icon="phone" required />
                        </div>

                    </div>
                </div>

                {{-- FULL WIDTH: Alamat & Wilayah --}}
                <div class="md:col-span-12 mt-2 pt-6 border-t border-zinc-200 dark:border-zinc-800">
                    <div class="flex items-center gap-2 mb-4">
                        <flux:icon.map-pin class="w-5 h-5 text-zinc-500" />
                        <h3 class="text-base font-semibold text-zinc-800 dark:text-zinc-200">Informasi Alamat & Wilayah</h3>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <livewire:global.wilayah-selector scope="customer-form" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-1">Jalan/Detail Alamat</label>
                            <flux:textarea wire:model="address" placeholder="Contoh: Jl. Sudirman No. 123..." rows="7" />
                        </div>
                    </div>
                </div>
            </div>

            {{-- FOOTER --}}
            <div class="flex justify-end mt-8 pt-4 border-t border-zinc-200 dark:border-zinc-800 gap-2">
                <flux:modal.close>
                    <flux:button variant="ghost" class="w-full sm:w-auto"> Batal </flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="primary" wire:loading.attr="disabled" class="w-full sm:w-auto">
                    <span wire:loading.remove wire:target="save">{{ $customer_id ? 'Simpan Perubahan' : 'Simpan Pelanggan' }}</span>
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
                    const properties = ['name', 'tel', 'email', 'icon'];
                    const opts = { multiple: false };
                    const contacts = await navigator.contacts.select(properties, opts);
                    if (contacts.length > 0) {
                        if (contacts[0].name && contacts[0].name.length > 0) {
                            this.$wire.name = contacts[0].name[0];
                        }
                        if (contacts[0].tel && contacts[0].tel.length > 0) {
                            this.$wire.phone = contacts[0].tel[0].replace(/[^0-9+]/g, '');
                        }
                        if (contacts[0].email && contacts[0].email.length > 0) {
                            this.$wire.email = contacts[0].email[0];
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
