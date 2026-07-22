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
    <body class="min-h-screen bg-zinc-50 dark:bg-zinc-800 overflow-hidden" x-data="dockApp()" x-on:livewire:navigated.window="recordVisit()">
    @include('layouts.app.global-loader')
        
        {{-- dock Detectors --}}
        <div x-data="dockHandler()" 
             class="w-full relative touch-pan-y"
             @touchstart="handleTouchStart"
             @touchmove="handleTouchMove"
             @touchend="handleTouchEnd">
             
            @php
                $navItems = \App\Models\NavigationItem::all();
                $iconSvgs = [];
                $urlMappings = [];
                
                foreach($navItems as $item) {
                    if(\Illuminate\Support\Facades\Route::has($item->route_name)) {
                        $url = parse_url(route($item->route_name), PHP_URL_PATH);
                        
                        $gradient = match($item->section) {
                            'INVENTORY' => 'from-blue-500 to-cyan-400',
                            'PEMBELIAN' => 'from-purple-500 to-pink-500',
                            'PRODUKSI' => 'from-orange-500 to-amber-400',
                            'PENJUALAN' => 'from-emerald-500 to-teal-400',
                            'KEUANGAN' => 'from-indigo-500 to-blue-600',
                            default => 'from-zinc-500 to-zinc-400',
                        };
                        
                        $urlMappings[$url] = [
                            'icon' => $item->icon,
                            'gradient' => $gradient,
                        ];
                        
                        if(!isset($iconSvgs[$item->icon])) {
                            $iconSvgs[$item->icon] = \Illuminate\Support\Facades\Blade::render('<flux:icon icon="'.$item->icon.'" class="size-5 md:size-6" />');
                        }
                    }
                }
                $urlMappings['/dashboard'] = [
                    'icon' => 'home',
                    'gradient' => 'from-zinc-500 to-zinc-400',
                ];
                $iconSvgs['home'] = \Illuminate\Support\Facades\Blade::render('<flux:icon icon="home" class="size-5 md:size-6" />');
                $iconSvgs['document-text'] = \Illuminate\Support\Facades\Blade::render('<flux:icon icon="document-text" class="size-5 md:size-6" />');
            @endphp

            <script>
                window.AppUrlMappings = {!! json_encode($urlMappings) !!};
                window.AppIconSvgs = {!! json_encode($iconSvgs) !!};
            </script>
            
            {{-- Tab System (App Shell) --}}
            <div class="relative w-full min-h-screen">
                {{-- Default Slot (The App Shell Page) --}}
                <div x-show="activeTabUrl === 'dashboard' || activeTabUrl === window.location.pathname" class="w-full min-h-screen">
                    {{ $slot }}
                </div>

                {{-- Open Tabs (Iframes) --}}
                <template x-for="tab in openTabs" :key="tab.url">
                    <div x-show="activeTabUrl === tab.url" class="absolute inset-0 z-[5] bg-white dark:bg-zinc-900 w-full min-h-screen">
                        
                        {{-- Loading Indicator Overlay --}}
                        <div x-show="!tab.loaded" class="absolute inset-0 z-10 flex flex-col items-center justify-center bg-white/80 dark:bg-zinc-900/80 backdrop-blur-sm">
                            <flux:icon.arrow-path class="w-8 h-8 text-indigo-500 animate-spin mb-4" />
                            <div class="text-sm font-semibold text-zinc-500 dark:text-zinc-400 animate-pulse" x-text="'Memuat ' + tab.title + '...'"></div>
                        </div>

                        <iframe :src="tab.url + (tab.url.includes('?') ? '&' : '?') + 'iframe=1'" 
                                @load="tab.loaded = true"
                                allow="camera; microphone; fullscreen; clipboard-read; clipboard-write; display-capture"
                                class="w-full h-full border-0"></iframe>
                    </div>
                </template>
            </div>
        </div>

        {{-- Backdrop --}}
        <div class="fixed inset-0 bg-black/40 pointer-events-auto z-[9998]" 
             x-show="menuState > 0" 
             @click="menuState = 0"
             x-transition.opacity.duration.300ms
             x-cloak></div>
             
        {{-- Multi-State Bottom Sheet (App Drawer) --}}
        <div class="fixed inset-x-0 bottom-0 mx-auto max-w-md md:max-w-2xl xl:max-w-4xl z-[10000] bg-zinc-50 dark:bg-zinc-900 rounded-t-3xl shadow-[0_-10px_40px_rgba(0,0,0,0.15)] transition-transform flex flex-col"
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
                 @click="menuState = 0">
                <div class="w-12 h-1.5 bg-zinc-300 dark:bg-zinc-600 rounded-full"></div>
            </div>
            
            {{-- Menu Content (Grid) --}}
            <div class="flex-1 overflow-y-auto hide-scrollbar touch-pan-y relative z-10"
                 :style="{ paddingBottom: `${currentTranslateY}px` }"
                 @touchstart.passive="contentTouchStart"
                 @touchmove="contentTouchMove"
                 @touchend="contentTouchEnd">
                <x-layouts.app.dock-menu-grid />
            </div>
        </div>
        
        {{-- Bottom Tab Bar (Recent Pages & Drag Handle) --}}
        <div class="fixed bottom-0 md:bottom-6 left-0 right-0 md:left-1/2 md:right-auto md:-translate-x-1/2 w-full md:w-max md:min-w-[400px] xl:min-w-[448px] md:max-w-[95vw] md:rounded-[24px] md:border md:px-3 xl:px-4 md:py-1.5 xl:py-2 min-h-[64px] md:min-h-[56px] xl:min-h-[84px] bg-white/60 dark:bg-zinc-900/60 backdrop-blur-3xl border-t md:border-t-0 border-zinc-200/50 dark:border-zinc-700/50 z-[9999] flex flex-col justify-center shadow-[0_-4px_25px_rgba(0,0,0,0.08)] md:shadow-[0_15px_40px_rgba(0,0,0,0.2)] md:dark:shadow-zinc-900/60 pb-safe transition-all duration-300"
             @pointerenter="if($event.pointerType === 'mouse') { clearTimeout(dockTimer); isDockHidden = false; }"
             @pointerleave="if($event.pointerType === 'mouse') { resetDockTimer(); }"
             @touchstart.passive="if(!isDockHidden) resetDockTimer()"
             @click="if(isDockHidden) { isDockHidden = false; resetDockTimer(); } else { toggleEditMode($event); }"
             @click.outside="editMode = false"
             @contextmenu.prevent="editMode = !editMode"
             :class="[
                 (menuState > 0 || (isDragging && currentY < windowHeight - 20)) 
                    ? 'translate-y-32 opacity-0 pointer-events-none' 
                    : (isDockHidden && window.innerWidth >= 768)
                        ? 'translate-y-[calc(100%+10px)] opacity-50 hover:opacity-100 cursor-pointer'
                        : 'translate-y-0 opacity-100'
             ]">
            
            {{-- Integrated Drag Handle --}}
            <div class="w-full h-4 flex justify-center items-start pt-1.5 cursor-pointer touch-none z-10"
                 x-show="menuState === 0"
                 @touchstart.passive="if(!isDockHidden) startMenuDrag($event)"
                 @touchmove.passive="if(!isDockHidden) onMenuDrag($event)"
                 @touchend="if(!isDockHidden) endMenuDrag($event)"
                 @click.stop="if(isDockHidden) { isDockHidden = false; resetDockTimer(); } else { menuState = 2; }">
                 <div class="w-12 h-1 bg-zinc-300 dark:bg-zinc-600 rounded-full hover:bg-zinc-400 transition-colors"></div>
            </div>
            
            {{-- Placeholder for Drag Handle when menu is open so height doesn't shift --}}
            <div class="w-full h-4" x-show="menuState > 0" x-cloak></div>

            {{-- Tabs Container --}}
            <div class="flex-1 flex items-center justify-center md:gap-2 px-2 relative">
                <template x-for="tab in openTabs" :key="tab.url">
                    <div class="relative group flex-1 md:flex-none"
                         :style="draggedTabUrl === tab.url ? `transform: translateY(${tabCurrentY}px); opacity: ${Math.max(0, 1 - (Math.abs(tabCurrentY)/100))};` : ''"
                         @mousedown="startTabDrag($event, tab.url)"
                         @mousemove.window="onTabDrag($event)"
                         @mouseup.window="endTabDrag($event)"
                         @touchstart.passive="startTabDrag($event, tab.url)"
                         @touchmove.passive="onTabDrag($event)"
                         @touchend.window="endTabDrag($event)">
                        
                        {{-- Delete Button (Visible when editMode is active) --}}
                        <button @click.prevent.stop="closeTab(tab.url)"
                                class="absolute top-0 right-0 w-5 h-5 flex items-center justify-center bg-zinc-200/90 text-zinc-600 hover:bg-red-500 hover:text-white dark:bg-zinc-700/90 dark:text-zinc-300 dark:hover:bg-red-500 dark:hover:text-white rounded-full z-[60] transition-all duration-200 shadow-sm border border-white dark:border-zinc-800"
                                :class="editMode ? 'opacity-100 scale-100 pointer-events-auto animate-bounce' : 'opacity-0 scale-75 pointer-events-none'">
                            <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>

                        <a :href="tab.url" @click.prevent="if(editMode) { $event.preventDefault(); } else { openTab(tab.url); }" 
                           class="flex flex-col items-center justify-center w-full md:w-[50px] lg:w-[60px] xl:w-[86px] h-[64px] md:h-[48px] lg:h-[50px] xl:h-[76px] gap-1 md:gap-0.5 xl:gap-1 px-1 relative select-none md:rounded-xl xl:rounded-2xl transition-all duration-300 md:border group hover:z-50"
                           :class="[
                               activeTabUrl === tab.url 
                                ? 'text-indigo-600 dark:text-indigo-400 md:bg-indigo-50/70 md:dark:bg-indigo-900/40 md:shadow-[0_4px_16px_rgba(79,70,229,0.25)] md:border-indigo-200/60 md:dark:border-indigo-500/40' 
                                : 'text-zinc-500 dark:text-zinc-400 hover:text-zinc-900 dark:hover:text-zinc-100 md:border-transparent md:hover:bg-zinc-100/50 md:dark:hover:bg-zinc-800/50',
                               draggedTabUrl === tab.url ? '' : 'transition-transform duration-300',
                               editMode ? 'animate-pulse opacity-70' : ''
                           ]"
                           @click="if(tabWasDragged) { $event.preventDefault(); $event.stopPropagation(); }">
                            
                            {{-- Active Indicator (Garis Atas) - Hanya untuk HP/Mobile --}}
                            <div x-show="activeTabUrl === tab.url" class="absolute top-0 w-8 h-1 bg-indigo-600 dark:bg-indigo-400 rounded-b-full md:hidden"></div>
                            
                            <div class="relative flex justify-center group-active:scale-[1.1] transition-all duration-300 ease-out xl:group-hover:scale-[1.4] xl:group-hover:-translate-y-4">
                                <div x-html="getSvgIcon(tab.icon, activeTabUrl === tab.url, tab.url)"></div>
                                <template x-if="badges[tab.url]">
                                    <div x-html="badges[tab.url]" class="absolute -top-1 -right-1"></div>
                                </template>
                            </div>
                            
                            {{-- Full Text for Mobile (< md) and PC (>= xl) --}}
                            <span class="block md:hidden xl:block text-[10px] xl:text-[11px] font-medium truncate w-14 xl:w-20 text-center pointer-events-none xl:group-hover:text-indigo-600 xl:dark:group-hover:text-indigo-400 xl:group-hover:-translate-y-1 transition-all duration-300" x-text="tab.title"></span>
                            
                            {{-- 1-Word Text for Tablet (md to lg) to save extreme vertical space --}}
                            <span class="hidden md:block xl:hidden text-[9px] lg:text-[10px] font-medium truncate w-full text-center pointer-events-none opacity-80" x-text="tab.title.split(' ')[0]"></span>
                        </a>
                    </div>
                </template>
            </div>
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

            function dockApp() {
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
                    
                    isDockHidden: false,
                    dockTimer: null,
                    badges: {},
                    
                    // Edit mode for deleting tabs (3 clicks)
                    editMode: false,
                    clickCount: 0,
                    clickTimer: null,
                    
                    // Tab System (Workspace)
                    openTabs: [],
                    activeTabUrl: 'dashboard',
                    
                    toggleEditMode(e) {
                        this.clickCount++;
                        clearTimeout(this.clickTimer);
                        
                        if (this.clickCount >= 3) {
                            this.editMode = true;
                            this.clickCount = 0;
                            if (navigator.vibrate) navigator.vibrate(100);
                        } else {
                            this.clickTimer = setTimeout(() => {
                                this.clickCount = 0;
                            }, 500); // 500ms time window for 3 clicks
                        }
                    },
                    
                    openTab(url, title = null, icon = null) {
                        this.menuState = 0; // Close drawer
                        
                        if (!title) title = this.determineTitle(url, 'Dashboard');
                        if (!icon) icon = this.determineIcon(url);
                        
                        if (url === '/' || url === '/dashboard') {
                            this.activeTabUrl = 'dashboard';
                            this.recordVisitExplicit(url, title, icon);
                            return;
                        }
                        
                        let existing = this.openTabs.find(t => t.url === url);
                        if (!existing) {
                            if (this.openTabs.length >= 10) {
                                this.openTabs.shift(); // Auto-trim oldest tab to save RAM
                            }
                            this.openTabs.push({ url: url, title: title, icon: icon, loaded: false });
                        }
                        this.activeTabUrl = url;
                        this.recordVisitExplicit(url, title, icon);
                    },
                    
                    closeTab(url) {
                        this.openTabs = this.openTabs.filter(t => t.url !== url);
                        if (this.activeTabUrl === url) {
                            this.activeTabUrl = this.openTabs.length > 0 ? this.openTabs[this.openTabs.length - 1].url : 'dashboard';
                        }
                    },
                    
                    init() {
                        this.resetDockTimer();
                        
                        // Load saved tabs
                        try {
                            const saved = localStorage.getItem('dock_recent_tabs');
                            if (saved) {
                                this.recentTabs = JSON.parse(saved);
                            }
                        } catch(e) {}
                        
                        // Sync badges continuously
                        setInterval(() => {
                            this.syncBadges();
                        }, 2000);
                        
                        this.recordVisit();
                    },

                    syncBadges() {
                        const newBadges = {};
                        document.querySelectorAll('#drawer-menu-container a[data-drawer-url]').forEach(el => {
                            const url = el.getAttribute('data-drawer-url');
                            const badgeSpan = el.querySelector('div[style="display: contents;"] > span.absolute');
                            if (badgeSpan) {
                                // Clone and adjust position for the dock (top right of icon container)
                                const cloned = badgeSpan.cloneNode(true);
                                cloned.className = cloned.className.replace('-left-2', 'right-1/4 translate-x-2 lg:right-1/3').replace('-top-1.5', '-top-1.5');
                                newBadges[url] = cloned.outerHTML;
                            }
                        });
                        this.badges = newBadges;
                    },
                    
                    resetDockTimer() {
                        clearTimeout(this.dockTimer);
                        if (window.innerWidth >= 768) {
                            this.isDockHidden = false;
                            this.dockTimer = setTimeout(() => {
                                this.isDockHidden = true;
                            }, 3500);
                        }
                    },
                    
                    // variables for tab dragging to dismiss
                    draggedTabUrl: null,
                    tabStartY: 0,
                    tabCurrentY: 0,
                    tabWasDragged: false,
                    
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
                    
                    startTabDrag(e, url) {
                        // Prevent starting drag if it's right-click
                        if (e.button === 2) return;
                        
                        this.draggedTabUrl = url;
                        this.tabStartY = e.touches ? e.touches[0].clientY : e.clientY;
                        this.tabCurrentY = 0;
                        this.tabWasDragged = false;
                    },

                    onTabDrag(e) {
                        if (!this.draggedTabUrl) return;
                        
                        let clientY = e.touches ? e.touches[0].clientY : e.clientY;
                        let deltaY = clientY - this.tabStartY;
                        
                        // Only allow dragging UP
                        if (deltaY < 0) {
                            this.tabCurrentY = deltaY;
                            if (deltaY < -10) {
                                this.tabWasDragged = true;
                            }
                        }
                    },

                    endTabDrag(e) {
                        if (!this.draggedTabUrl) return;
                        
                        // If dragged up by more than 40px, throw it away
                        if (this.tabCurrentY < -40) {
                            this.removeTab(this.draggedTabUrl);
                        }
                        
                        this.draggedTabUrl = null;
                        this.tabCurrentY = 0;
                        
                        // Reset flag after a tiny delay so click event can be blocked
                        setTimeout(() => {
                            this.tabWasDragged = false;
                        }, 50);
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
                        const saved = localStorage.getItem('dock_recent_tabs');
                        if (saved) {
                            try { this.recentTabs = JSON.parse(saved); } catch(e){}
                        }
                        
                        if (this.recentTabs.length === 0) {
                            this.recentTabs = [];
                        } else {
                            this.recentTabs = this.trimTabs(this.recentTabs);
                        }
                        
                        // Hydrate openTabs from saved state so they don't disappear on reload
                        this.openTabs = this.recentTabs.map(t => ({...t, loaded: false}));
                        
                        // Set activeTabUrl based on current URL
                        if (window.location.pathname === '/' || window.location.pathname === '/dashboard') {
                            this.activeTabUrl = 'dashboard';
                        } else {
                            let exists = this.openTabs.find(t => t.url === window.location.pathname);
                            if (exists) {
                                this.activeTabUrl = window.location.pathname;
                            } else if (this.openTabs.length > 0) {
                                this.activeTabUrl = this.openTabs[this.openTabs.length - 1].url;
                            } else {
                                this.activeTabUrl = 'dashboard';
                            }
                        }
                        
                        this.recordVisit();
                        this.resetDockTimer();
                        
                        // Observe drawer for badge updates
                        const drawerContainer = document.getElementById('drawer-menu-container');
                        if (drawerContainer) {
                            const observer = new MutationObserver(() => this.syncBadges());
                            observer.observe(drawerContainer, { childList: true, subtree: true, characterData: true });
                        }
                        
                        setTimeout(() => this.syncBadges(), 200);
                        
                        // Listen for window resize to trim if screen gets smaller
                        window.addEventListener('resize', () => {
                            let newTabs = this.trimTabs([...this.recentTabs]);
                            if (newTabs.length !== this.recentTabs.length) {
                                this.recentTabs = newTabs;
                                localStorage.setItem('dock_recent_tabs', JSON.stringify(this.recentTabs));
                            }
                        });
                    },

                    handleTouchStart(e) {
                        this.touchStartX = e.touches[0].clientX;
                        this.touchStartY = e.touches[0].clientY;
                    },
                    handleTouchMove(e) {
                        // Tidak perlu prevent default, biarkan scroll alami
                    },
                    handleTouchEnd(e) {
                        // Old swipe-from-left dock disabled to prevent conflict with Android Back dock
                    },
                    
                    getSvgIcon(iconName, isActive, url = null) {
                        let map = window.AppUrlMappings[url];
                        let svg = window.AppIconSvgs[iconName] || window.AppIconSvgs['document-text'];
                        
                        // Menghapus kotak gradien, hanya menampilkan SVG asli
                        let classes = isActive ? 'text-indigo-600 dark:text-indigo-400 stroke-[2.5]' : 'text-zinc-600 dark:text-zinc-400 stroke-[1.5]';
                        
                        return `<div class="w-6 h-6 md:w-5 md:h-5 lg:w-5 lg:h-5 xl:w-9 xl:h-9 flex items-center justify-center transition-colors ${classes}">
                                   ${svg}
                                </div>`;
                    },

                    determineIcon(url) {
                        const path = url.replace(window.location.origin, '');
                        
                        let map = window.AppUrlMappings[path];
                        if (map) return map.icon;
                        
                        return 'document-text';
                    },

                    determineTitle(url, docTitle) {
                        // Coba cari label asli dari menu drawer agar konsisten
                        const drawerLink = document.querySelector(`#drawer-menu-container a[data-drawer-url="${url}"]`);
                        if (drawerLink) {
                            const labelSpan = drawerLink.querySelector('span.leading-tight');
                            if (labelSpan && labelSpan.innerText.trim()) {
                                return labelSpan.innerText.trim();
                            }
                        }

                        let shortTitle = docTitle.split('-')[0].trim();
                        if (shortTitle.toLowerCase() === 'laravel' || !shortTitle) {
                            let pathParts = url.split('/').filter(p => p.length > 0);
                            if (pathParts.length > 0) {
                                let lastPart = pathParts[pathParts.length - 1];
                                shortTitle = lastPart.charAt(0).toUpperCase() + lastPart.slice(1).replace(/[-_]/g, ' ');
                            } else {
                                shortTitle = 'Menu';
                            }
                        }
                        
                        if (shortTitle.length > 12) shortTitle = shortTitle.substring(0, 10) + '..';
                        return shortTitle;
                    },

                    trimTabs(tabs) {
                        // Batas disesuaikan dengan kapasitas lebar layar, maksimal 10.
                        let maxTabs = Math.min(10, Math.max(2, Math.floor((window.innerWidth - 32) / 82))); 
                        
                        while (tabs.length > maxTabs) {
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
                        return tabs;
                    },

                    recordVisitExplicit(url, title, icon) {
                        if (url === '/' || url === '/dashboard') return;
                        
                        let tabs = [...this.recentTabs];
                        let existingTab = tabs.find(t => t.url === url);
                        
                        if (!existingTab) {
                            tabs.push({ url: url, title: title, icon: icon, timestamp: Date.now() });
                        } else {
                            existingTab.title = title;
                            existingTab.icon = icon;
                            existingTab.timestamp = Date.now();
                        }
                        
                        tabs = this.trimTabs(tabs);
                        
                        this.recentTabs = tabs;
                        localStorage.setItem('dock_recent_tabs', JSON.stringify(tabs));
                    },

                    recordVisit() {
                        setTimeout(() => {
                            this.currentUrl = window.location.pathname;
                            if (this.currentUrl === '/' || this.currentUrl === '/dashboard') return;
                            
                            let title = this.determineTitle(this.currentUrl, document.title);
                            let icon = this.determineIcon(this.currentUrl);
                            
                            this.recordVisitExplicit(this.currentUrl, title, icon);
                        }, 50);
                    },
                    
                    removeTab(url) {
                        this.recentTabs = this.recentTabs.filter(t => t.url !== url);
                        localStorage.setItem('dock_recent_tabs', JSON.stringify(this.recentTabs));
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

