<?php

namespace App\Livewire\Communication;

use Livewire\Component;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Modules\Communication\Models\WaConversation;
use Modules\Communication\Models\WaMessage;
use Modules\Communication\Services\FonnteService;

class ChatApp extends Component
{
    #[Url(history: true)]
    public $activeConversationId = null;
    
    public $messageInput = '';
    public $aiPersona = '';
    public $aiInstruction = '';
    
    // State untuk Chat Baru
    public $showNewChatModal = false;
    public $newChatPhone = '';
    public $newChatName = '';

    public function mount()
    {
    }

    public function startNewChat()
    {
        $this->validate([
            'newChatPhone' => 'required|string|min:10',
            'newChatName' => 'nullable|string|max:255',
        ]);

        // Bersihkan nomor HP (hanya angka)
        $phone = preg_replace('/[^0-9]/', '', $this->newChatPhone);
        
        // Pastikan format internasional (misal 62)
        if (str_starts_with($phone, '0')) {
            $phone = '62' . substr($phone, 1);
        }

        $conversation = WaConversation::firstOrCreate(
            ['phone_number' => $phone],
            ['name' => $this->newChatName ?: $phone]
        );

        $this->showNewChatModal = false;
        $this->newChatPhone = '';
        $this->newChatName = '';
        
        $this->selectConversation($conversation->id);
    }



    public function selectConversation($id)
    {
        $this->activeConversationId = $id;
        
        // Mark as read
        $conversation = WaConversation::find($id);
        if ($conversation && $conversation->unread_count > 0) {
            $conversation->update(['unread_count' => 0]);
        }

        $this->dispatch('chat-scrolled-to-bottom');
    }



    public function sendMessage(FonnteService $fonnte)
    {
        if (trim($this->messageInput) === '' || !$this->activeConversationId) {
            return;
        }

        $conversation = WaConversation::find($this->activeConversationId);
        if (!$conversation) return;

        // Simpan pesan keluar
        $waMessage = $conversation->messages()->create([
            'direction' => 'out',
            'message_type' => 'text',
            'message' => $this->messageInput,
            'status' => 'pending',
        ]);

        $messageText = $this->messageInput;
        $this->messageInput = '';
        $this->dispatch('chat-scrolled-to-bottom');

        // Kirim via Fonnte
        $response = $fonnte->sendMessage($conversation->phone_number, $messageText);

        if ($response && isset($response['status']) && $response['status'] == true) {
            $fonnteId = null;
            if (isset($response['id'])) {
                $fonnteId = is_array($response['id']) ? $response['id'][0] : $response['id'];
            }
            $waMessage->update(['status' => 'sent', 'fonnte_id' => $fonnteId]);
        } else {
            $errorMsg = 'Fonnte API Error';
            if ($response && isset($response['reason'])) {
                $errorMsg = $response['reason'];
            } elseif ($response && isset($response['detail'])) {
                $errorMsg = $response['detail'];
            }
            
            $waMessage->update([
                'status' => 'failed',
                'error_message' => $errorMsg
            ]);
        }
        
        $conversation->update(['last_message_at' => now()]);
    }

    public function generateAiReply(\App\Services\VectorSearchService $vectorService)
    {
        if (!$this->activeConversationId) return;
        
        $conversation = WaConversation::find($this->activeConversationId);
        if (!$conversation) return;

        // Ambil 10 pesan terakhir
        $recentMessages = $conversation->messages()->latest()->take(10)->get()->reverse();
        if ($recentMessages->isEmpty()) return;

        // Cari pesan terakhir dari client untuk query pencarian RAG
        $lastClientMessage = $recentMessages->where('direction', 'in')->last();
        $queryText = $lastClientMessage ? $lastClientMessage->message : $recentMessages->last()->message;

        // Gunakan VectorSearchService untuk mencari konteks
        $searchResults = $vectorService->search($queryText, 3);
        $knowledgeContext = "";
        foreach ($searchResults as $res) {
            $knowledgeContext .= "- " . ($res['content_text'] ?? '') . "\n";
        }

        // Format history pesan untuk Gemini
        $historyForGemini = [];
        foreach ($recentMessages as $msg) {
            $historyForGemini[] = [
                'role' => $msg->direction == 'in' ? 'user' : 'model',
                'parts' => [['text' => $msg->message]]
            ];
        }

        // Fix Edge Case: API Gemini menolak jika pesan terakhir berasal dari 'model'.
        // Jika kebetulan pesan terakhir di database adalah pesan dari agen (kita),
        // maka kita sisipkan pesan 'user' buatan sistem agar Gemini paham perintah selanjutnya.
        if (count($historyForGemini) > 0 && end($historyForGemini)['role'] === 'model') {
            $historyForGemini[] = [
                'role' => 'user',
                'parts' => [['text' => 'Tolong buatkan draf balasan lanjutan (follow-up) dari saya untuk percakapan ini.']]
            ];
        }

        $systemPrompt = "Anda adalah AI Assistant (Customer Service) yang ramah dan profesional.\n";
        
        if (!empty(trim($this->aiPersona))) {
            $systemPrompt .= "KARAKTER & PERSONA ANDA:\n" . $this->aiPersona . "\n\n";
        }
        
        $systemPrompt .= "Gunakan konteks data berikut dari sistem ERP internal untuk menjawab klien secara akurat (jika relevan):\n";
        $systemPrompt .= $knowledgeContext . "\n";
        $systemPrompt .= "Berikan jawaban singkat, padat, ramah, jangan terlalu panjang, dan gunakan bahasa Indonesia yang baik.\n";

        if (!empty(trim($this->aiInstruction))) {
            $systemPrompt .= "\nINSTRUKSI KHUSUS UNTUK PESAN INI (WAJIB DIPATUHI):\n" . $this->aiInstruction . "\n";
        }

        if (!empty(trim($this->messageInput))) {
            $systemPrompt .= "\nPERHATIAN! Agen (pengguna) telah menulis sebuah draf mentah di kotak ketik. Tugas utama Anda adalah MENGUBAH, MEMOLES, ATAU MENERJEMAHKAN teks mentah tersebut menjadi kalimat balasan yang jauh lebih baik dan profesional (sesuai persona & instruksi), NAMUN TETAP MENJAGA INTI PESAN ATAU MAKNANYA.\n";
            $systemPrompt .= "Draf mentah dari agen: \"" . trim($this->messageInput) . "\"\n";
        }

        try {
            $generatedText = $vectorService->generateChatCompletion($systemPrompt, $historyForGemini);
            // Isi ke kotak input
            $this->messageInput = $generatedText;
            
            // Kosongkan instruksi khusus setelah dipakai
            $this->aiInstruction = '';
            
            // Trigger auto resize pada input area jika perlu
            $this->dispatch('chat-input-filled');
        } catch (\Exception $e) {
            $this->dispatch('notify', [
                'type' => 'error',
                'title' => 'Gagal generate AI',
                'message' => $e->getMessage()
            ]);
        }
    }

    public function getListeners()
    {
        // Dengarkan channel global untuk daftar percakapan dan channel spesifik untuk room aktif
        $listeners = [
            'echo:wa.conversations,.MessageReceived' => 'handleNewMessageGlobal',
        ];

        if ($this->activeConversationId) {
            $conversationId = $this->activeConversationId;
            $listeners["echo:wa.conversation.{$conversationId},.MessageReceived"] = 'handleNewMessageInRoom';
        }

        return $listeners;
    }

    public function handleNewMessageGlobal($event)
    {
        // Livewire otomatis re-render saat method ini dipanggil
        // Tidak perlu kode tambahan
    }

    public function handleNewMessageInRoom($event)
    {
        $this->dispatch('chat-scrolled-to-bottom');
        
        // Tandai sudah dibaca karena user sedang buka room ini
        $conversation = WaConversation::find($this->activeConversationId);
        if ($conversation) {
            $conversation->update(['unread_count' => 0]);
        }
    }

    public function render()
    {
        $conversations = WaConversation::with('latestMessage')
            ->orderBy('last_message_at', 'desc')
            ->get();
        
        $messages = collect();
        if ($this->activeConversationId) {
            // Hanya ambil 100 pesan terakhir agar performa tetap cepat
            $messages = WaMessage::where('wa_conversation_id', $this->activeConversationId)
                                       ->latest()
                                       ->take(100)
                                       ->get()
                                       ->reverse();
        }

        return view('livewire.communication.chat-app', [
            'conversations' => $conversations,
            'messages' => $messages,
        ])->layout('layouts.app');
    }
}
