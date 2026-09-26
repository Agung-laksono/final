                    {{-- 6. Activity & Comments --}}
                    <div class="flex items-start gap-4">
                        <div class="mt-1 p-2 bg-zinc-100 dark:bg-zinc-800/80 rounded-xl border border-zinc-200 dark:border-zinc-700 text-zinc-500 dark:text-zinc-400 shadow-sm">
                            <flux:icon.list-bullet class="w-5 h-5" />
                        </div>
                        <div class="flex-1 space-y-6">
                            <div class="flex items-center justify-between mb-2">
                                <h3 class="text-lg font-bold text-zinc-900 dark:text-zinc-100 tracking-tight">Activity</h3>
                            </div>
                            
                            {{-- Activity List --}}
                            <div class="space-y-5 pt-2 mb-8">
                                @forelse($selectedProject['comments'] ?? [] as $comment)
                                    <div wire:key="comment-{{ $comment['id'] }}" id="comment-{{ $comment['id'] }}" class="flex gap-3 group transition-colors duration-700 rounded-xl -mx-2 p-2">
                                        <div class="shrink-0 w-9 h-9 rounded-full bg-zinc-100 dark:bg-zinc-800 border-2 border-white dark:border-zinc-900 shadow-sm flex items-center justify-center font-bold text-sm text-zinc-600 dark:text-zinc-400 mt-0.5 overflow-hidden">
                                            @if(!empty($comment['user']['avatar']))
                                                <img src="{{ \Illuminate\Support\Facades\Storage::url($comment['user']['avatar']) }}" class="w-full h-full object-cover">
                                            @else
                                                {{ substr($comment['user']['name'] ?? 'U', 0, 1) }}
                                            @endif
                                        </div>
                                        <div class="flex-1 space-y-1"
                                             x-data="{ editing: false, editContent: {{ json_encode($comment['content']) }} }">
                                            <div class="flex items-baseline gap-2">
                                                <span class="font-bold text-sm text-zinc-900 dark:text-zinc-100">{{ $comment['user']['name'] ?? 'Unknown' }}</span>
                                                <span class="text-[11px] text-zinc-500 font-medium">{{ \Carbon\Carbon::parse($comment['created_at'])->diffForHumans() }}</span>
                                                @if(($comment['updated_at'] ?? '') !== ($comment['created_at'] ?? ''))
                                                    <span class="text-[10px] text-zinc-400 italic">(edited)</span>
                                                @endif
                                            </div>

                                            {{-- View mode --}}
                                            <div x-show="!editing">
                                                @if(!empty($comment['parent_id']) && !empty($comment['parent']))
                                                    <div @click="let el = document.getElementById('comment-{{ $comment['parent_id'] }}'); if(el) { el.scrollIntoView({ behavior: 'smooth', block: 'center' }); el.classList.add('bg-indigo-50/80', 'dark:bg-indigo-900/30'); setTimeout(() => el.classList.remove('bg-indigo-50/80', 'dark:bg-indigo-900/30'), 2000); }" class="mb-2 pl-3 border-l-2 border-indigo-300 dark:border-indigo-500/50 text-xs text-zinc-500 dark:text-zinc-400 cursor-pointer hover:bg-zinc-50 dark:hover:bg-zinc-800/50 p-1.5 rounded-r-md transition-colors" title="Lihat komentar asli">
                                                        <div class="font-semibold text-indigo-600 dark:text-indigo-400 mb-0.5">{{ $comment['parent']['user']['name'] ?? 'Unknown' }}</div>
                                                        <div class="truncate italic">{!! Str::limit(strip_tags($this->formatCommentContent($comment['parent']['content'])), 60) !!}</div>
                                                    </div>
                                                @endif
                                                
                                                @php
                                                    $refType = null;
                                                    $refId = null;
                                                    $refTitle = null;
                                                    $contentToDisplay = $comment['content'];
                                                    
                                                    if (preg_match('/^\[REF:([^:]+):([^|]+)\|([^\]]+)\]\s*/', $contentToDisplay, $matches)) {
                                                        $refType = $matches[1];
                                                        $refId = $matches[2];
                                                        $refTitle = $matches[3];
                                                        $contentToDisplay = preg_replace('/^\[REF:[^\]]+\]\s*/', '', $contentToDisplay);
                                                    }
                                                @endphp
                                                
                                                @if($refType)
                                                    <div @click="let el = document.getElementById('{{ $refId }}'); if(el) { el.scrollIntoView({ behavior: 'smooth', block: 'center' }); el.classList.add('ring-4', 'ring-amber-500/50'); setTimeout(() => el.classList.remove('ring-4', 'ring-amber-500/50'), 2000); }" class="mb-2 pl-3 border-l-2 border-amber-300 dark:border-amber-500/50 text-xs text-zinc-500 dark:text-zinc-400 cursor-pointer hover:bg-zinc-50 dark:hover:bg-zinc-800/50 p-1.5 rounded-r-md transition-colors" title="Lihat referensi asli">
                                                        <div class="font-semibold text-amber-600 dark:text-amber-400 mb-0.5"><flux:icon.link class="inline-block w-3 h-3 mr-1" /> Mereferensikan {{ ucfirst($refType) }}</div>
                                                        <div class="truncate italic">{{ $refTitle }}</div>
                                                    </div>
                                                @endif
                                                
                                                <div class="bg-white dark:bg-zinc-800/80 p-3.5 rounded-xl shadow-sm border border-zinc-200 dark:border-zinc-700 inline-block max-w-full">
                                                    <p class="text-sm text-zinc-700 dark:text-zinc-300 whitespace-pre-line leading-relaxed">{!! $this->formatCommentContent($contentToDisplay) !!}</p>
                                                </div>
                                                <div class="flex gap-3 mt-1.5 text-[11px] text-zinc-400 font-semibold uppercase tracking-wider opacity-0 group-hover:opacity-100 transition-opacity">
                                                    <button wire:click="$set('replyToCommentId', {{ $comment['id'] }})" x-on:click="setTimeout(() => $refs.textarea.focus(), 100)" class="hover:text-indigo-600 dark:hover:text-indigo-400 hover:underline underline-offset-2">Reply</button>
                                                    @if(auth()->id() === ($comment['user_id'] ?? null))
                                                        <button @click="editing = true; editContent = {{ json_encode($comment['content']) }}" class="hover:text-zinc-700 dark:hover:text-zinc-300 hover:underline underline-offset-2">Edit</button>
                                                        <button wire:click="deleteComment({{ $comment['id'] }})" wire:confirm="Hapus komentar ini?" class="hover:text-red-600 dark:hover:text-red-400 hover:underline underline-offset-2">Delete</button>
                                                    @endif
                                                </div>
                                            </div>

                                            {{-- Edit mode --}}
                                            <div x-show="editing" x-transition class="space-y-2">
                                                <div class="bg-white dark:bg-zinc-900 border border-indigo-300 dark:border-indigo-500/50 rounded-xl shadow-sm overflow-hidden ring-4 ring-indigo-500/10">
                                                    <textarea x-model="editContent" rows="3" class="w-full border-none focus:ring-0 focus:outline-none text-sm bg-transparent p-4 resize-none text-zinc-800 dark:text-zinc-200 placeholder:text-zinc-400"></textarea>
                                                    <div class="bg-zinc-50/80 dark:bg-zinc-800/50 p-2.5 flex justify-end items-center gap-2 border-t border-zinc-100 dark:border-zinc-800">
                                                        <flux:button size="sm" variant="ghost" @click="editing = false">Batal</flux:button>
                                                        <flux:button size="sm" variant="primary" class="rounded-lg font-semibold"
                                                            @click="$wire.editComment({{ $comment['id'] }}, editContent).then(() => { editing = false })"
                                                            x-bind:disabled="!editContent.trim()">
                                                            Simpan
                                                        </flux:button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                @empty
                                    <div class="text-center py-6 text-zinc-400 dark:text-zinc-500 text-sm font-medium">
                                        No comments yet. Be the first to start the conversation!
                                    </div>
                                @endforelse
                            </div>

                            {{-- Add Comment Form --}}
                            @once
                                <style>
                                    .tribute-container { z-index: 99999 !important; }
                                    .tribute-container ul { background: white; border-radius: 8px; box-shadow: 0 10px 15px -3px rgb(0 0 0 / 0.1); border: 1px solid #e4e4e7; padding: 4px; }
                                    .dark .tribute-container ul { background: #18181b; border-color: #3f3f46; }
                                    .tribute-container li { padding: 8px 12px; border-radius: 6px; font-size: 14px; cursor: pointer; }
                                    .dark .tribute-container li { color: #e4e4e7; }
                                    .tribute-container li.highlight { background: #4f46e5; color: white; }
                                    emoji-picker { --background: white; --border-color: #e4e4e7; width: 100%; max-width: 320px; }
                                    .dark emoji-picker { --background: #18181b; --border-color: #3f3f46; --category-font-color: #a1a1aa; }
                                </style>
                            @endonce

                            <div class="sticky bottom-0 bg-white dark:bg-zinc-800 pt-3 pb-2 z-10 border-t border-transparent" x-data="{ isScrolled: false }" @scroll.window="isScrolled = (window.scrollY > 10)">
                                <div class="flex items-start gap-3"
                                     @reply-comment.window="insertMention($event.detail.name)"
                                     x-data='{
                                         showEmojiPicker: false,
                                     showMentionPicker: false,
                                     tribute: null,
                                     users: @json($selectedProject['assignees'] ?? []),
                                     
                                     init() {
                                         // Load Emoji Picker
                                         if (!customElements.get("emoji-picker")) {
                                             let s = document.createElement("script");
                                             s.type = "module";
                                             s.src = "https://cdn.jsdelivr.net/npm/emoji-picker-element@1/index.js";
                                             document.head.appendChild(s);
                                         }
                                         
                                         // Load Tribute
                                         let loadTribute = () => {
                                             if (!window.Tribute) {
                                                 let s = document.createElement("script");
                                                 s.src = "https://unpkg.com/tributejs";
                                                 s.onload = this.setupTribute.bind(this);
                                                 document.head.appendChild(s);
                                                 
                                                 let l = document.createElement("link");
                                                 l.rel = "stylesheet";
                                                 l.href = "https://unpkg.com/tributejs/dist/tribute.css";
                                                 document.head.appendChild(l);
                                             } else {
                                                 this.setupTribute();
                                             }
                                         };
                                         loadTribute();
                                         
                                         // Setup Emoji Listener
                                         this.$refs.emojiPicker.addEventListener("emoji-click", event => {
                                             let textarea = this.$refs.textarea;
                                             let start = textarea.selectionStart;
                                             let end = textarea.selectionEnd;
                                             let text = textarea.value;
                                             textarea.value = text.slice(0, start) + event.detail.unicode + text.slice(end);
                                             textarea.dispatchEvent(new Event("input", { bubbles: true }));
                                             this.showEmojiPicker = false;
                                         });
                                     },
                                     
                                     setupTribute() {
                                         this.tribute = new Tribute({
                                             values: this.users.map(u => ({ key: u.name, value: u.name })),
                                             selectTemplate: function (item) { return `@` + item.original.value; },
                                             menuItemTemplate: function (item) { return `<span class="font-medium">` + item.original.value + `</span>`; },
                                             requireLeadingSpace: false,
                                         });
                                         this.tribute.attach(this.$refs.textarea);
                                         
                                         this.$refs.textarea.addEventListener("tribute-replaced", (e) => {
                                             this.$refs.textarea.dispatchEvent(new Event("input", { bubbles: true }));
                                         });
                                     },
                                     
                                     insertMention(name) {
                                         let ta = this.$refs.textarea;
                                         let text = ta.value;
                                         let start = ta.selectionStart;
                                         let end = ta.selectionEnd;
                                         
                                         let prefix = (start > 0 && text[start - 1] !== " ") ? " @" : "@";
                                         let insertion = prefix + name + " ";
                                         
                                         ta.value = text.slice(0, start) + insertion + text.slice(end);
                                         ta.focus();
                                         ta.dispatchEvent(new Event("input", { bubbles: true }));
                                         this.showMentionPicker = false;
                                     }
                                 }'>
                                <div class="shrink-0 w-9 h-9 rounded-full bg-gradient-to-br from-indigo-100 to-purple-100 dark:from-indigo-500/20 dark:to-purple-500/20 border-2 border-white dark:border-zinc-900 shadow-sm flex items-center justify-center text-sm font-bold text-indigo-700 dark:text-indigo-300 mt-0.5 overflow-hidden">
                                    @if(auth()->user()->avatar)
                                        <img src="{{ \Illuminate\Support\Facades\Storage::url(auth()->user()->avatar) }}" class="w-full h-full object-cover">
                                    @else
                                        {{ substr(auth()->user()->name, 0, 1) }}
                                    @endif
                                </div>
                                <div class="flex-1 bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-xl shadow-sm focus-within:border-indigo-300 focus-within:ring-4 focus-within:ring-indigo-500/10 transition-all relative">
                                    @if($replyToCommentId)
                                        @php $parentComment = collect($selectedProject['comments'] ?? [])->firstWhere('id', $replyToCommentId); @endphp
                                        @if($parentComment)
                                            <div class="px-4 py-2.5 bg-zinc-50 dark:bg-zinc-800/80 border-b border-zinc-100 dark:border-zinc-800 flex justify-between items-start rounded-t-xl text-sm">
                                                <div class="flex-1 truncate pr-4">
                                                    <span class="text-xs font-semibold text-indigo-600 dark:text-indigo-400 block mb-0.5">Membalas {{ $parentComment['user']['name'] ?? 'Seseorang' }}</span>
                                                    <span class="text-zinc-500 dark:text-zinc-400 italic text-xs block truncate">{!! Str::limit(strip_tags($this->formatCommentContent($parentComment['content'])), 60) !!}</span>
                                                </div>
                                                <button wire:click="cancelReply" class="shrink-0 p-1 text-zinc-400 hover:text-zinc-600 dark:hover:text-zinc-200 transition-colors rounded-lg"><flux:icon.x-mark class="w-4 h-4"/></button>
                                            </div>
                                        @endif
                                    @endif

                                    @if($referenceItem)
                                        <div class="px-4 py-2.5 bg-indigo-50/50 dark:bg-indigo-800/30 border-b border-indigo-100 dark:border-indigo-800 flex justify-between items-start rounded-t-xl text-sm">
                                            <div class="flex-1 truncate pr-4">
                                                <span class="text-xs font-semibold text-indigo-600 dark:text-indigo-400 block mb-0.5">
                                                    <flux:icon.link class="inline-block w-3 h-3 mr-1" /> Mereferensikan {{ ucfirst($referenceItem['type']) }}
                                                </span>
                                                <span class="text-zinc-600 dark:text-zinc-300 italic text-xs block truncate">{{ $referenceItem['title'] }}</span>
                                            </div>
                                            <button wire:click="cancelReference" class="shrink-0 p-1 text-zinc-400 hover:text-zinc-600 dark:hover:text-zinc-200 transition-colors rounded-lg"><flux:icon.x-mark class="w-4 h-4"/></button>
                                        </div>
                                    @endif

                                    @if($commentAttachmentFile)
                                        <div class="px-4 py-2 bg-indigo-50/50 dark:bg-indigo-900/10 border-b border-indigo-100 dark:border-indigo-800/30 flex items-center justify-between text-sm">
                                            <div class="flex items-center gap-2 text-indigo-700 dark:text-indigo-400">
                                                <flux:icon.document class="w-4 h-4"/>
                                                <span class="truncate max-w-[200px] font-medium">{{ $commentAttachmentFile->getClientOriginalName() }}</span>
                                            </div>
                                            <button wire:click="$set('commentAttachmentFile', null)" type="button" class="p-1 hover:bg-indigo-100 dark:hover:bg-indigo-800/50 rounded-lg text-indigo-400 hover:text-indigo-600 dark:hover:text-indigo-300 transition-colors">
                                                <flux:icon.x-mark class="w-4 h-4"/>
                                            </button>
                                        </div>
                                    @endif

                                    <textarea x-ref="textarea" 
                                        @focus-comment-textarea.window="
                                            $el.focus();
                                            $el.scrollIntoView({behavior: 'smooth', block: 'center'});
                                        "
                                        wire:model="newComment" placeholder="Write a comment... (Type @ to mention)" class="w-full border-none !border-transparent focus:!border-transparent focus:!ring-0 focus:outline-none text-sm bg-transparent p-4 min-h-[90px] resize-none text-zinc-800 dark:text-zinc-200 placeholder:text-zinc-400 shadow-none"></textarea>
                                    
                                    {{-- Emoji Picker Dropdown --}}
                                    <div x-show="showEmojiPicker" @click.away="showEmojiPicker = false" x-transition class="absolute bottom-12 left-2 z-[99999] shadow-xl rounded-xl overflow-hidden border border-zinc-200 dark:border-zinc-700">
                                        <emoji-picker x-ref="emojiPicker"></emoji-picker>
                                    </div>
                                    
                                    {{-- Mention Picker Dropdown --}}
                                    <div x-show="showMentionPicker" @click.away="showMentionPicker = false" x-transition class="absolute bottom-12 left-10 z-[99999] w-56 bg-white dark:bg-zinc-900 shadow-xl rounded-xl overflow-hidden border border-zinc-200 dark:border-zinc-700">
                                        <div class="px-3 py-2 border-b border-zinc-100 dark:border-zinc-800 text-xs font-semibold text-zinc-500 bg-zinc-50 dark:bg-zinc-800/50">Mention User</div>
                                        <ul class="max-h-48 overflow-y-auto p-1">
                                            <template x-for="user in users" :key="user.id">
                                                <li>
                                                    <button type="button" @click="insertMention(user.name)" class="w-full text-left px-3 py-2 rounded-lg text-sm text-zinc-700 dark:text-zinc-300 hover:bg-zinc-100 dark:hover:bg-zinc-800 transition-colors flex items-center gap-2">
                                                        <div class="shrink-0 w-6 h-6 rounded-full bg-indigo-100 dark:bg-indigo-900/50 flex items-center justify-center text-indigo-700 dark:text-indigo-400 font-bold text-[10px]" x-text="user.name.substring(0, 1)"></div>
                                                        <span x-text="user.name" class="truncate font-medium"></span>
                                                    </button>
                                                </li>
                                            </template>
                                        </ul>
                                    </div>

                                    <div class="bg-zinc-50/80 dark:bg-zinc-800/50 p-2.5 flex justify-between items-center border-t border-zinc-100 dark:border-zinc-800 rounded-b-xl">
                                        <div class="flex gap-1 text-zinc-400">
                                            <input type="file" wire:model.live="commentAttachmentFile" id="comment-attachment" class="hidden">
                                            <button onclick="document.getElementById('comment-attachment').click()" type="button" class="p-1.5 hover:bg-zinc-200 dark:hover:bg-zinc-700 hover:text-zinc-700 dark:hover:text-zinc-200 rounded-lg transition-colors" title="Upload Attachment"><flux:icon.paper-clip class="w-4 h-4" /></button>
                                            <button @click="showMentionPicker = !showMentionPicker; showEmojiPicker = false" type="button" class="p-1.5 hover:bg-zinc-200 dark:hover:bg-zinc-700 hover:text-zinc-700 dark:hover:text-zinc-200 rounded-lg transition-colors" :class="showMentionPicker ? 'bg-zinc-200 dark:bg-zinc-700 text-zinc-700 dark:text-zinc-200' : ''"><flux:icon.at-symbol class="w-4 h-4" /></button>
                                            <button @click="showEmojiPicker = !showEmojiPicker; showMentionPicker = false" type="button" class="p-1.5 hover:bg-zinc-200 dark:hover:bg-zinc-700 hover:text-zinc-700 dark:hover:text-zinc-200 rounded-lg transition-colors" :class="showEmojiPicker ? 'bg-zinc-200 dark:bg-zinc-700 text-zinc-700 dark:text-zinc-200' : ''"><flux:icon.face-smile class="w-4 h-4" /></button>
                                            <button @click="let ta = $refs.textarea; let start = ta.selectionStart; let end = ta.selectionEnd; let text = ta.value; let insertion = '[Judul Link](https://)'; ta.value = text.slice(0, start) + insertion + text.slice(end); ta.focus(); ta.dispatchEvent(new Event('input', {bubbles: true}))" type="button" class="p-1.5 hover:bg-zinc-200 dark:hover:bg-zinc-700 hover:text-zinc-700 dark:hover:text-zinc-200 rounded-lg transition-colors" title="Insert Link"><flux:icon.link class="w-4 h-4" /></button>
                                        </div>
                                        <flux:button wire:click="addComment" variant="primary" size="sm" class="rounded-lg shadow-sm font-semibold">Save</flux:button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                    {{-- Mobile View Logs Button --}}
                    <div class="mt-8 md:hidden w-full pb-8">
                        <flux:button class="w-full justify-center text-zinc-600 dark:text-zinc-300 bg-zinc-100 dark:bg-zinc-800 hover:bg-zinc-200 dark:hover:bg-zinc-700 font-semibold rounded-xl" icon="clock" x-on:click="$flux.modal('project-log').show()">
                            View Activity Log
                        </flux:button>
                    </div>
