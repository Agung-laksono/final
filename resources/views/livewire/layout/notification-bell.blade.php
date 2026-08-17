<?php

use Livewire\Volt\Component;
use Livewire\Attributes\On;
use Illuminate\Notifications\DatabaseNotification;

new class extends Component {
    public $unreadCount = 0;
    public $authId = null;

    public function mount() {
        $this->authId = auth()->id();
        if (auth()->check()) {
            $this->unreadCount = auth()->user()->unreadNotifications()->count();
        }
    }

    public function with() {
        return [
            'notifications' => $this->getNotifications()
        ];
    }

    public function getNotifications() {
        if (!auth()->check()) {
            return collect([]);
        }
        
        $user = auth()->user();
        $this->unreadCount = $user->unreadNotifications()->count();
        
        return $user->notifications()->take(5)->get();
    }

    public function markAsRead($notificationId) {
        if (!auth()->check()) return;
        
        $notification = auth()->user()->notifications()->find($notificationId);
        if ($notification) {
            $notification->markAsRead();
            $this->unreadCount = auth()->user()->unreadNotifications()->count();
            \App\Livewire\Support\NotificationSync::syncToOthers(auth()->id(), $this->unreadCount);
        }
    }

    public function markAsReadAndRedirect($notificationId, $url) {
        $this->markAsRead($notificationId);
        if ($url && $url !== '#') {
            return $this->redirect($url, navigate: true);
        }
    }

    public function markAllAsRead() {
        if (!auth()->check()) return;
        
        auth()->user()->unreadNotifications->markAsRead();
        $this->unreadCount = 0;
        \App\Livewire\Support\NotificationSync::syncToOthers(auth()->id(), 0);
    }

    #[On('echo-private:App.Models.User.{authId},.Illuminate\\Notifications\\Events\\BroadcastNotificationCreated')]
    public function onNotificationReceived($notification) {
        $this->unreadCount = auth()->user()->unreadNotifications()->count();
        $this->js("document.getElementById('notif-sound') && document.getElementById('notif-sound').play().catch(() => {})");
        
        $this->dispatch('notification-popup', [
            'id' => $notification['id'] ?? null,
            'title' => $notification['title'] ?? 'Notifikasi Baru',
            'message' => $notification['message'] ?? '',
            'action_type' => $notification['action_type'] ?? null,
            'order_id' => $notification['order_id'] ?? null,
            'customer_name' => $notification['customer_name'] ?? '',
            'total_amount' => $notification['total_amount'] ?? 0,
            'items_summary' => $notification['items_summary'] ?? '',
        ]);
    }

    #[On('echo-private:App.Models.User.{authId},.NotificationsRead')]
    public function onNotificationsRead($event) {
        $this->unreadCount = $event['unreadCount'];
        $this->dispatch('$refresh');
    }

    #[On('notifications-updated')]
    public function onNotificationsUpdated() {
        if (auth()->check()) {
            $this->unreadCount = auth()->user()->unreadNotifications()->count();
        }
    }

    public function approveSalesOrder($orderId, $notificationId = null) {
        abort_unless(auth()->user()->can('sales.approve.update'), 403);
        
        $order = \Modules\Sales\Models\SalesOrder::with('items')->find($orderId);
        if ($order && $order->status === 'pending_approval') {
            $hasDeficit = false;
            foreach ($order->items as $item) {
                $itemModel = \Modules\Inventory\Models\Item::find($item->item_id);
                $atp = $itemModel ? $itemModel->getATP() : 0;
                if ($item->qty > $atp) {
                    $deficit = $item->qty - $atp;
                    $existingRequest = \Modules\Inventory\Models\InventoryRequest::where('item_id', $item->item_id)
                        ->where('source_type', 'sales')
                        ->where('reference_number', $order->so_number)
                        ->exists();
                    if (!$existingRequest) {
                        \Modules\Inventory\Models\InventoryRequest::create([
                            'item_id' => $item->item_id,
                            'source_type' => 'sales',
                            'reference_number' => $order->so_number,
                            'requested_qty' => $deficit,
                            'notes' => 'Defisit stok untuk pesanan pelanggan (ATP: ' . $atp . ', Dipesan: ' . $item->qty . ')' . 
                                       (!empty($item->custom_attributes) || !empty($item->custom_attachments) ? ' [CUSTOM]' : ''),
                            'status' => 'draft',
                        ]);
                        $hasDeficit = true;
                    }
                }
            }
            
            $order->status = 'processing';
            $order->save();

            if ($order->created_by) {
                $creator = \App\Models\User::find($order->created_by);
                if ($creator && $creator->id !== auth()->id()) {
                    $creator->sendSalesOrderNotification($order, auth()->user());
                }
            }

            \App\Events\KanbanUpdated::safeDispatch('sales_order');
            if ($hasDeficit) {
                \App\Events\KanbanUpdated::safeDispatch('inventory_request');
            }
            \Flux::toast('Pesanan disetujui, lanjut ke pemrosesan gudang.', variant: 'success');
            
            if ($notificationId) {
                $this->markAsRead($notificationId);
            }
        }
    }

    public function rejectSalesOrder($orderId, $notificationId = null) {
        abort_unless(auth()->user()->can('sales.approve.update'), 403);
        
        $order = \Modules\Sales\Models\SalesOrder::find($orderId);
        if ($order && $order->status === 'pending_approval') {
            $order->status = 'rejected';
            $order->save();

            if ($order->created_by) {
                $creator = \App\Models\User::find($order->created_by);
                if ($creator && $creator->id !== auth()->id()) {
                    $creator->sendSalesOrderNotification($order, auth()->user());
                }
            }

            \App\Events\KanbanUpdated::safeDispatch('sales_order');
            \Flux::toast('Pesanan ditolak.', variant: 'danger');
            
            if ($notificationId) {
                $this->markAsRead($notificationId);
            }
        }
    }
};
?>

<div {{ $attributes }}>
    <audio id="notif-sound" src="/notification.mp3" preload="auto"></audio>
    <flux:dropdown position="top" align="start">
        <flux:sidebar.item class="relative cursor-pointer w-full text-start {{ $unreadCount > 0 ? 'bell-has-unread' : '' }}" data-flux-sidebar-action>
            <x-slot:icon>
                <div class="relative">
                    <flux:icon.bell class="size-4 text-zinc-500 dark:text-white/80 group-hover:text-zinc-800 dark:group-hover:text-white" />
                    @if ($unreadCount > 0)
                        <span class="absolute -left-1.5 -top-2 flex h-3.5 w-3.5 items-center justify-center rounded-full bg-red-500 border border-white dark:border-zinc-900 text-[9px] font-bold text-white shadow pointer-events-none z-10">
                            {{ $unreadCount > 9 ? '9+' : $unreadCount }}
                        </span>
                    @endif
                </div>
            </x-slot:icon>
            {{ __('Notifikasi') }}
        </flux:sidebar.item>

        <!-- pop up notifikasi -->
        <flux:menu class="w-80 sm:w-96 p-0 overflow-hidden bg-gray-100">
            {{-- Header --}}
            <div class="px-4 py-3 border-b border-zinc-200 dark:border-zinc-800 flex justify-between items-center bg-zinc-50 dark:bg-zinc-800/50">
                <h3 class="font-semibold text-sm text-zinc-800 dark:text-zinc-200">{{ __('Notifikasi') }}</h3>
                @if ($unreadCount > 0)
                    <div class="bg-blue-100 dark:bg-blue-500/20 text-blue-600 dark:text-blue-400 text-[10px] font-bold px-2 py-0.5 rounded-full">{{ $unreadCount }} Baru</div>
                @endif
            </div>

            {{-- Scrollable Container --}}
            <div class="max-h-[28rem] overflow-y-auto relative p-2 flex flex-col gap-1.5">
                @forelse ($notifications as $notification)
                    <div wire:click="markAsReadAndRedirect('{{ $notification->id }}', '{{ $notification->data['url'] ?? '#' }}')" 
                         wire:target="markAsReadAndRedirect('{{ $notification->id }}', '{{ $notification->data['url'] ?? '#' }}')"
                         wire:loading.class="opacity-50 pointer-events-none"
                         class="cursor-pointer group relative flex items-center gap-2.5 p-2.5 rounded-lg border transition-all duration-200 {{ $notification->read_at ? 'bg-white dark:bg-zinc-900/50 border-zinc-100 dark:border-zinc-800/50 opacity-70 hover:opacity-100 hover:border-zinc-200 dark:hover:border-zinc-700' : 'bg-blue-50/50 dark:bg-blue-900/20 border-blue-100 dark:border-blue-800/50 hover:border-blue-200 dark:hover:border-blue-700 shadow-sm' }}">

                        {{-- Icon Container --}}
                        <div class="shrink-0">
                            <div class="relative h-8 w-8 inline-flex">
                                @if(isset($notification->data['avatar']))
                                    <img src="{{ $notification->data['avatar'] }}" alt="Avatar" class="h-8 w-8 rounded-full object-cover ring-2 ring-white dark:ring-zinc-900 shadow-sm" />
                                    @if(isset($notification->data['icon']))
                                        <div class="absolute -bottom-1 -right-1 h-4 w-4 rounded-full bg-white dark:bg-zinc-800 flex items-center justify-center shadow-sm ring-1 ring-zinc-200 dark:ring-zinc-700 z-10">
                                            <flux:icon :icon="$notification->data['icon']" class="h-2.5 w-2.5 {{ $notification->data['color'] ?? 'text-zinc-500' }}" />
                                        </div>
                                    @endif
                                @else
                                    <div class="flex h-8 w-8 items-center justify-center rounded-full bg-zinc-100 dark:bg-zinc-800 ring-1 ring-zinc-200 dark:ring-zinc-700 shadow-sm">
                                        @if(isset($notification->data['icon']))
                                            <flux:icon :icon="$notification->data['icon']" class="h-4 w-4 {{ $notification->data['color'] ?? 'text-zinc-500' }}" />
                                        @else
                                            <flux:icon.bell class="h-4 w-4 text-zinc-500" />
                                        @endif
                                    </div>
                                @endif
                            </div>
                        </div>
                        
                        {{-- Content Container --}}
                        <div class="flex-1 min-w-0 flex flex-col gap-1">
                            <div class="text-xs leading-snug text-zinc-700 dark:text-zinc-300 break-words">
                                {!! $notification->data['message'] ?? '' !!}
                            </div>
                            
                            {{-- Context Badge --}}
                            @if(isset($notification->data['title']))
                                <div class="flex items-center gap-1.5 mt-0.5">
                                    <div class="bg-white dark:bg-zinc-900 text-zinc-500 dark:text-zinc-400 text-[9px] font-semibold px-1.5 py-0.5 rounded flex items-center gap-1 border border-zinc-200 dark:border-zinc-700 shadow-sm">
                                        <flux:icon :icon="$notification->data['icon'] ?? 'bell'" class="w-2.5 h-2.5 {{ $notification->data['color'] ?? 'text-zinc-500' }}" />
                                        {{ $notification->data['title'] }}
                                    </div>
                                </div>
                            @endif

                            <div class="flex items-center justify-between mt-0.5">
                                <p class="text-[10px] text-zinc-400 dark:text-zinc-500 font-medium">
                                    {{ $notification->created_at->diffForHumans() }}
                                </p>
                                
                                @if (!$notification->read_at)
                                    <button wire:click.stop="markAsRead('{{ $notification->id }}')" 
                                            wire:target="markAsRead('{{ $notification->id }}')"
                                            wire:loading.class="opacity-50 pointer-events-none"
                                            class="opacity-0 group-hover:opacity-100 p-0.5 rounded-full hover:bg-zinc-200 dark:hover:bg-zinc-700 text-zinc-400 hover:text-zinc-600 dark:hover:text-zinc-300 transition-all" title="Tandai sudah dibaca">
                                        <flux:icon.check wire:loading.remove wire:target="markAsRead('{{ $notification->id }}')" class="w-3 h-3" />
                                        <svg wire:loading wire:target="markAsRead('{{ $notification->id }}')" class="w-3 h-3 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                                    </button>
                                @endif
                            </div>
                        </div>
                        
                        {{-- Unread Indicator Dot --}}
                        @if (!$notification->read_at)
                            <div class="absolute right-2 top-2 w-1.5 h-1.5 bg-blue-500 rounded-full"></div>
                        @endif
                    </div>
                @empty
                    <div class="py-8 text-center flex flex-col items-center justify-center">
                        <div class="w-10 h-10 rounded-full bg-zinc-50 dark:bg-zinc-800/50 flex items-center justify-center mb-2">
                            <flux:icon.bell-slash class="h-4 w-4 text-zinc-400 dark:text-zinc-500" />
                        </div>
                        <p class="text-xs font-medium text-zinc-600 dark:text-zinc-400">Tidak ada notifikasi baru</p>
                    </div>
                @endforelse
            </div>
            
            {{-- Footer --}}
            <div class="border-t border-zinc-200 dark:border-zinc-800 p-3 bg-zinc-50 dark:bg-zinc-800/50 flex justify-between items-center">
                @if ($unreadCount > 0)
                    <button wire:click="markAllAsRead" class="text-[11px] font-semibold text-blue-600 hover:text-blue-700 dark:text-blue-400 dark:hover:text-blue-300 transition-colors px-1">
                        Tandai semua dibaca
                    </button>
                @else
                    <div></div>
                @endif
                <a href="{{ route('notifications.index') }}" wire:navigate class="text-[11px] font-semibold text-zinc-600 dark:text-zinc-400 hover:text-zinc-900 dark:hover:text-zinc-200 transition-colors px-1">
                    Lihat semua &rarr;
                </a>
            </div>
        </flux:menu>
    </flux:dropdown>

    <!-- Custom Real-time Notification Pop-up (Pusher Beam Style) -->
    <div x-data="{
             show: false,
             id: null,
             title: '',
             message: '',
             actionType: null,
             orderId: null,
             customerName: '',
             totalAmount: 0,
             itemsSummary: '',
             
             showNotification(data) {
                 this.id = data.id || null;
                 this.title = data.title || 'Notifikasi Baru';
                 this.message = data.message || '';
                 this.actionType = data.action_type || null;
                 this.orderId = data.order_id || null;
                 this.customerName = data.customer_name || '';
                 this.totalAmount = data.total_amount || 0;
                 this.itemsSummary = data.items_summary || '';
                 
                 this.show = true;
                 
                 // Auto hide after 8s if no action needed
                 if (!this.actionType) {
                     setTimeout(() => this.show = false, 8000);
                 }
             },
             
             approve() {
                 $wire.approveSalesOrder(this.orderId, this.id);
                 this.show = false;
             },
             reject() {
                 $wire.rejectSalesOrder(this.orderId, this.id);
                 this.show = false;
             }
         }"
         @notification-popup.window="showNotification($event.detail)"
         class="fixed bottom-4 right-4 z-[9999]">
         
         <div x-show="show" 
              x-transition:enter="transition ease-out duration-300"
              x-transition:enter-start="opacity-0 translate-y-4"
              x-transition:enter-end="opacity-100 translate-y-0"
              x-transition:leave="transition ease-in duration-200"
              x-transition:leave-start="opacity-100 translate-y-0"
              x-transition:leave-end="opacity-0 translate-y-4"
              class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 shadow-2xl rounded-xl p-4 w-80 flex flex-col gap-3"
              style="display: none;">
              
              <div class="flex justify-between items-start gap-2">
                  <div class="flex items-start gap-3 flex-1">
                      <div class="w-8 h-8 rounded-full bg-blue-100 text-blue-600 flex items-center justify-center shrink-0 mt-0.5">
                          <flux:icon.bell class="w-4 h-4" />
                      </div>
                      <div class="flex-1">
                          <h4 class="font-bold text-sm text-zinc-900 dark:text-zinc-100" x-text="title"></h4>
                          <p class="text-[11px] text-zinc-500 mt-0.5 leading-snug" x-html="message"></p>
                      </div>
                  </div>
                  <button @click="show = false" class="text-zinc-400 hover:text-zinc-600 shrink-0"><flux:icon.x-mark class="w-4 h-4" /></button>
              </div>
              
              <!-- Context Box -->
              <template x-if="actionType === 'sales_approval'">
                  <div class="bg-zinc-50 dark:bg-zinc-800 rounded-lg p-2.5 border border-zinc-100 dark:border-zinc-700 flex flex-col gap-1.5 mt-1">
                      <div class="flex justify-between items-center">
                          <span class="text-[10px] font-bold text-zinc-500 tracking-wider">PEMBELI</span>
                          <span class="text-xs font-semibold text-zinc-900 dark:text-zinc-100" x-text="customerName"></span>
                      </div>
                      <div class="flex justify-between items-center">
                          <span class="text-[10px] font-bold text-zinc-500 tracking-wider">TOTAL</span>
                          <span class="text-xs font-black text-amber-600 dark:text-amber-500">Rp <span x-text="new Intl.NumberFormat('id-ID').format(totalAmount)"></span></span>
                      </div>
                      <div class="flex flex-col gap-0.5 mt-1 pt-1 border-t border-zinc-200 dark:border-zinc-700">
                          <span class="text-[9px] font-bold text-zinc-500 tracking-wider">PRODUK</span>
                          <span class="text-[10px] text-zinc-700 dark:text-zinc-300 leading-tight" x-text="itemsSummary"></span>
                      </div>
                  </div>
              </template>
              
              <!-- Actions -->
              <template x-if="actionType === 'sales_approval'">
                  <div class="flex gap-2 w-full mt-1">
                      <button @click="reject" class="flex-1 py-2 px-3 bg-red-50 text-red-600 hover:bg-red-100 border border-red-200 rounded-lg text-xs font-bold transition-colors text-center shadow-sm">Tolak</button>
                      <button @click="approve" class="flex-1 py-2 px-3 bg-blue-600 text-white hover:bg-blue-700 border border-transparent rounded-lg text-xs font-bold transition-colors text-center shadow-sm">Setujui</button>
                  </div>
              </template>
         </div>
    </div>
</div>
