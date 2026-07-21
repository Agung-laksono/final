<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
        <style>
            .hide-scrollbar::-webkit-scrollbar { display: none; }
            .hide-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
            .pb-safe { padding-bottom: env(safe-area-inset-bottom); }
        </style>
    </head>
    <body class="min-h-screen bg-zinc-50 dark:bg-zinc-800 pb-20" x-data="gestureApp()" x-on:livewire:navigated.window="recordVisit()">
    @include('layouts.app.global-loader')
        
        {{-- Gesture Detectors --}}
        <div class="fixed inset-0 z-0 pointer-events-none"
             @touchstart.passive="handleTouchStart"
             @touchmove.passive="handleTouchMove"
             @touchend="handleTouchEnd">
        </div>
        
        <div class="relative z-10"
             @touchstart.passive="handleTouchStart"
             @touchmove.passive="handleTouchMove"
             @touchend="handleTouchEnd">
            
            <!-- Mobile User Menu -->
            <flux:header class="md:hidden border-b border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 sticky top-0 z-40">
                <x-app-logo href="#" wire:navigate />

                <flux:spacer />
                <livewire:layout.notification-bell />
                <flux:spacer />

                <flux:dropdown position="top" align="end">
                    <flux:profile
                        :initials="auth()->user()->initials()"
                        :avatar="auth()->user()->avatarUrl()"
                        icon-trailing="chevron-down"
                    />
                    <flux:menu>
                        <flux:menu.radio.group>
                            <div class="p-0 text-sm font-normal">
                                <div class="flex items-center gap-2 px-1 py-1.5 text-start text-sm">
                                    <flux:avatar
                                        :name="auth()->user()->name"
                                        :initials="auth()->user()->initials()"
                                        :src="auth()->user()->avatarUrl()"
                                    />
                                    <div class="grid flex-1 text-start text-sm leading-tight">
                                        <flux:heading class="truncate">{{ auth()->user()->name }}</flux:heading>
                                        <flux:text class="truncate">{{ auth()->user()->email }}</flux:text>
                                    </div>
                                </div>
                            </div>
                        </flux:menu.radio.group>
                        <flux:menu.separator />
                        <flux:menu.radio.group>
                            <flux:menu.item :href="route('settings.index')" icon="cog" wire:navigate>
                                {{ __('Settings') }}
                            </flux:menu.item>
                        </flux:menu.radio.group>
                        <flux:menu.separator />
                        <form method="POST" action="{{ route('logout') }}" class="w-full">
                            @csrf
                            <flux:menu.item
                                as="button"
                                type="submit"
                                icon="arrow-right-start-on-rectangle"
                                class="w-full cursor-pointer"
                            >
                                {{ __('Log out') }}
                            </flux:menu.item>
                        </form>
                    </flux:menu>
                </flux:dropdown>
            </flux:header>

            {{ $slot }}
        </div>

        {{-- Backdrop --}}
        <div class="fixed inset-0 bg-black/40 pointer-events-auto z-[9998]" 
             x-show="menuState > 0" 
             @click="menuState = 0"
             x-transition.opacity.duration.300ms
             x-cloak></div>
             
        {{-- Multi-State Bottom Sheet (App Drawer) --}}
        <div class="fixed inset-x-0 bottom-0 z-[10000] bg-zinc-50 dark:bg-zinc-900 rounded-t-3xl shadow-[0_-10px_40px_rgba(0,0,0,0.15)] transition-transform flex flex-col"
             :class="{
                 'duration-300 ease-out': !isDragging,
                 'duration-0': isDragging
             }"
             :style="{
                 transform: `translateY(${currentTranslateY}px)`,
                 height: '90vh'
             }">
            
            {{-- Header & Drag Handle --}}
            <div class="w-full flex flex-col items-center justify-center pt-3 pb-3 flex-shrink-0 relative z-20 touch-none cursor-pointer bg-zinc-50 dark:bg-zinc-900 rounded-t-3xl"
                 @touchstart.passive="startMenuDrag"
                 @touchmove.passive="onMenuDrag"
                 @touchend="endMenuDrag"
                 @click="menuState = (menuState === 1 ? 2 : 1)">
                <div class="w-12 h-1.5 bg-zinc-300 dark:bg-zinc-600 rounded-full"></div>
            </div>
            
            {{-- Menu Content (Grid) --}}
            <div class="flex-1 overflow-y-auto hide-scrollbar touch-pan-y relative z-10"
                 @touchstart.passive="contentTouchStart"
                 @touchmove="contentTouchMove"
                 @touchend="contentTouchEnd">
                <x-layouts.app.gesture-menu-grid />
            </div>
        </div>
        
        {{-- Floating Drag Handle for Bottom Bar (When Closed) --}}
        <div class="fixed bottom-14 left-0 right-0 h-8 z-[9999] flex justify-center items-end pb-2 touch-none cursor-pointer"
             x-show="menuState === 0"
             @touchstart.passive="startMenuDrag"
             @touchmove.passive="onMenuDrag"
             @touchend="endMenuDrag"
             @click="menuState = 1">
            <div class="w-12 h-1.5 bg-zinc-300/80 dark:bg-zinc-600/80 rounded-full shadow-sm drop-shadow backdrop-blur-sm border border-zinc-400/20"></div>
        </div>

        {{-- Bottom Tab Bar (Recent Pages) --}}
        <div class="fixed bottom-0 left-0 right-0 h-14 bg-white/95 dark:bg-zinc-900/95 backdrop-blur-md border-t border-zinc-200 dark:border-zinc-800 z-[9999] flex items-center justify-around px-2 shadow-[0_-4px_6px_-1px_rgba(0,0,0,0.1)] pb-safe">
            <template x-for="tab in recentTabs" :key="tab.url">
                <a :href="tab.url" wire:navigate 
                   class="flex flex-col items-center justify-center w-full h-full gap-0 transition-colors relative"
                   :class="currentUrl === tab.url ? 'text-indigo-600 dark:text-indigo-400' : 'text-zinc-500 dark:text-zinc-400 hover:text-zinc-900 dark:hover:text-zinc-100'">
                    
                    {{-- Active Indicator --}}
                    <div x-show="currentUrl === tab.url" class="absolute top-0 w-8 h-1 bg-indigo-600 dark:bg-indigo-400 rounded-b-full"></div>
                    
                    <div x-html="getSvgIcon(tab.icon, currentUrl === tab.url)"></div>
                    <span class="text-[10px] font-medium truncate w-14 text-center" x-text="tab.title"></span>
                </a>
            </template>
        </div>

        @persist('toast')
            <flux:toast.group>
                <flux:toast />
            </flux:toast.group>
        @endpersist

        @fluxScripts
        @stack('scripts')
        <script>
            document.addEventListener('livewire:init', () => {
                Livewire.hook('request', ({ options }) => {
                    options.headers['X-Tab-Focused'] = document.hasFocus() ? '1' : '0';
                    options.headers['X-Current-Path'] = window.location.pathname;
                });
            });

            function gestureApp() {
                return {
                    menuState: 0, // 0 = closed, 1 = peek (75%), 2 = expanded (100%)
                    isDragging: false,
                    startY: 0,
                    currentY: 0,
                    windowHeight: window.innerHeight,
                    touchStartX: 0,
                    touchStartY: 0,
                    recentTabs: [],
                    currentUrl: window.location.pathname,
                    
                    // variables for content drag detection
                    contentStartY: 0,
                    isContentAtTop: false,
                    isContentAtBottom: false,
                    
                    get currentTranslateY() {
                        if (this.isDragging) {
                            return Math.max(0, this.currentY);
                        }
                        if (this.menuState === 0) return this.windowHeight;
                        if (this.menuState === 1) return this.windowHeight * 0.25; // 75% visible
                        if (this.menuState === 2) return 0; // 100% visible
                        return this.windowHeight;
                    },
                    
                    startMenuDrag(e) {
                        this.isDragging = true;
                        this.startY = e.touches[0].clientY;
                        this.windowHeight = window.innerHeight;
                        
                        if (this.menuState === 0) this.currentY = this.windowHeight;
                        else if (this.menuState === 1) this.currentY = this.windowHeight * 0.25;
                        else if (this.menuState === 2) this.currentY = 0;
                    },
                    
                    onMenuDrag(e) {
                        if (!this.isDragging) return;
                        // Prevent page scrolling while dragging menu
                        if (e.cancelable) e.preventDefault(); 
                        
                        const deltaY = e.touches[0].clientY - this.startY;
                        
                        let startPoint = 0;
                        if (this.menuState === 0) startPoint = this.windowHeight;
                        else if (this.menuState === 1) startPoint = this.windowHeight * 0.25;
                        else if (this.menuState === 2) startPoint = 0;
                        
                        this.currentY = startPoint + deltaY;
                    },
                    
                    endMenuDrag(e) {
                        if (!this.isDragging) return;
                        this.isDragging = false;
                        const deltaY = e.changedTouches[0].clientY - this.startY;
                        
                        if (deltaY < -40) { // Swiped Up
                            if (this.menuState === 0) this.menuState = 1;
                            else if (this.menuState === 1) this.menuState = 2;
                        } else if (deltaY > 40) { // Swiped Down
                            if (this.menuState === 2) this.menuState = 1;
                            else if (this.menuState === 1) this.menuState = 0;
                        } else {
                            // Snap to nearest point
                            const h = this.windowHeight;
                            const d0 = Math.abs(this.currentY - h);
                            const d1 = Math.abs(this.currentY - (h * 0.25));
                            const d2 = Math.abs(this.currentY - 0);
                            
                            const min = Math.min(d0, d1, d2);
                            if (min === d0) this.menuState = 0;
                            else if (min === d1) this.menuState = 1;
                            else this.menuState = 2;
                        }
                    },
                    
                    contentTouchStart(e) {
                        this.contentStartY = e.touches[0].clientY;
                        this.isContentAtTop = e.currentTarget.scrollTop <= 0;
                    },
                    
                    contentTouchMove(e) {
                        if (!this.isDragging) {
                            const deltaY = e.touches[0].clientY - this.contentStartY;
                            
                            // If pulled down when at top
                            if (deltaY > 10 && this.isContentAtTop) {
                                this.startMenuDrag(e);
                            }
                        }
                        
                        if (this.isDragging) {
                            this.onMenuDrag(e);
                        }
                    },
                    
                    contentTouchEnd(e) {
                        if (this.isDragging) {
                            this.endMenuDrag(e);
                        }
                    },
                    
                    init() {
                        const saved = localStorage.getItem('gesture_recent_tabs');
                        if (saved) {
                            try { this.recentTabs = JSON.parse(saved); } catch(e){}
                        }
                        
                        if (this.recentTabs.length === 0) {
                            this.recentTabs = [];
                        }
                        
                        this.recordVisit();
                    },

                    handleTouchStart(e) {
                        this.touchStartX = e.touches[0].clientX;
                        this.touchStartY = e.touches[0].clientY;
                    },
                    handleTouchMove(e) {
                        // Tidak perlu prevent default, biarkan scroll alami
                    },
                    handleTouchEnd(e) {
                        // Old swipe-from-left gesture disabled to prevent conflict with Android Back gesture
                    },
                    
                    getSvgIcon(iconName, isActive) {
                        const classes = isActive ? 'w-5 h-5 stroke-[2.5]' : 'w-5 h-5 stroke-[1.5]';
                        const icons = {
                            'home': `<svg class="${classes}" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12l8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h8.25" /></svg>`,
                            'cube': `<svg class="${classes}" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M21 7.5l-9-5.25L3 7.5m18 0l-9 5.25m9-5.25v9l-9 5.25M3 7.5l9 5.25M3 7.5v9l9 5.25m0-9v9" /></svg>`,
                            'shopping-cart': `<svg class="${classes}" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 3h1.386c.51 0 .955.343 1.087.835l.383 1.437M7.5 14.25a3 3 0 00-3 3h15.75m-12.75-3h11.218c1.121-2.3 2.1-4.684 2.924-7.138a60.114 60.114 0 00-16.536-1.84M7.5 14.25L5.106 5.272M6 20.25a.75.75 0 11-1.5 0 .75.75 0 011.5 0zm12.75 0a.75.75 0 11-1.5 0 .75.75 0 011.5 0z" /></svg>`,
                            'banknotes': `<svg class="${classes}" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18.75a60.07 60.07 0 0115.797 2.101c.727.198 1.453-.342 1.453-1.096V5.946c0-.754-.726-1.294-1.453-1.096A60.07 60.07 0 012.25 5.25v13.5zM15.75 9a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0z" /></svg>`,
                            'document-text': `<svg class="${classes}" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" /></svg>`,
                            'wrench-screwdriver': `<svg class="${classes}" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M11.42 15.17L17.25 21A2.652 2.652 0 0021 17.25l-5.877-5.827M15.17 11.42L21 5.583M5.583 21l5.837-5.837m-2.14-7.466l-5.592 5.592a2.65 2.65 0 01-3.75-3.749l5.59-5.59M11.42 15.17l3.75-3.75M5.583 21l3.75-3.75" /></svg>`,
                            'calculator': `<svg class="${classes}" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 15.75V18m-7.5-6.75h.008v.008H8.25v-.008zm0 2.25h.008v.008H8.25V13.5zm0 2.25h.008v.008H8.25v-.008zm0 2.25h.008v.008H8.25V18zm2.498-6.75h.007v.008h-.007v-.008zm0 2.25h.007v.008h-.007V13.5zm0 2.25h.007v.008h-.007v-.008zm0 2.25h.007v.008h-.007V18zm2.504-6.75h.008v.008h-.008v-.008zm0 2.25h.008v.008h-.008V13.5zm0 2.25h.008v.008h-.008v-.008zm0 2.25h.008v.008h-.008V18zm2.498-6.75h.008v.008h-.008v-.008zm0 2.25h.008v.008h-.008V13.5zM8.25 6h7.5v2.25h-7.5V6zM12 2.25c-1.892 0-3.758.11-5.593.322C5.307 2.7 4.5 3.65 4.5 4.757V19.5a2.25 2.25 0 002.25 2.25h10.5a2.25 2.25 0 002.25-2.25V4.757c0-1.108-.806-2.057-1.907-2.185A48.507 48.507 0 0012 2.25z" /></svg>`
                        };
                        return icons[iconName] || icons['document-text'];
                    },

                    determineIcon(url) {
                        if (url.includes('/inventory')) return 'cube';
                        if (url.includes('/finance')) return 'banknotes';
                        if (url.includes('/purchase')) return 'shopping-cart';
                        if (url.includes('/sales')) return 'calculator';
                        if (url.includes('/production')) return 'wrench-screwdriver';
                        if (url === '/' || url === '/dashboard') return 'home';
                        return 'document-text';
                    },

                    determineTitle(url, docTitle) {
                        let shortTitle = docTitle.split('-')[0].trim();
                        if (url.includes('/inventory/items')) return 'Barang';
                        if (url.includes('/inventory/warehouses')) return 'Gudang';
                        if (url.includes('/purchase/orders')) return 'PO';
                        if (url.includes('/sales/orders')) return 'SO';
                        
                        if (shortTitle.length > 12) shortTitle = shortTitle.substring(0, 10) + '..';
                        return shortTitle;
                    },

                    recordVisit() {
                        setTimeout(() => {
                            this.currentUrl = window.location.pathname;
                            if (this.currentUrl === '/' || this.currentUrl === '/dashboard') return;
                            
                            let title = this.determineTitle(this.currentUrl, document.title);
                            let icon = this.determineIcon(this.currentUrl);
                            
                            let tabs = [...this.recentTabs];
                            let existingTab = tabs.find(t => t.url === this.currentUrl);
                            
                            if (!existingTab) {
                                tabs.push({ url: this.currentUrl, title: title, icon: icon, timestamp: Date.now() });
                                if (tabs.length > 5) {
                                    let oldestIndex = 0;
                                    let oldestTime = tabs[0].timestamp || 0;
                                    for (let i = 1; i < tabs.length; i++) {
                                        let tTime = tabs[i].timestamp || 0;
                                        if (tTime < oldestTime) {
                                            oldestTime = tTime;
                                            oldestIndex = i;
                                        }
                                    }
                                    tabs.splice(oldestIndex, 1);
                                }
                            } else {
                                existingTab.title = title;
                                existingTab.icon = icon;
                                existingTab.timestamp = Date.now();
                            }
                            
                            this.recentTabs = tabs;
                            localStorage.setItem('gesture_recent_tabs', JSON.stringify(tabs));
                        }, 50);
                    }
                }
            }
        </script>
        <x-loading></x-loading>
        @if(!request()->is('chat*'))
        <livewire:ai-chat-widget />
        @endif
    </body>
</html>
