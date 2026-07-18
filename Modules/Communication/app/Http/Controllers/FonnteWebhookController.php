<?php

namespace Modules\Communication\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use Modules\Communication\Models\WaConversation;
use Modules\Communication\Models\WaMessage;

class FonnteWebhookController extends Controller
{
    /**
     * Handle incoming webhook from Fonnte.
     */
    public function handle(Request $request)
    {
        // Validasi payload (opsional, tergantung keamanan yang diinginkan)
        $sender = $request->input('sender'); // Hanya ada saat pesan masuk
        $messageText = $request->input('message');
        $name = $request->input('name');
        
        $status = $request->input('status'); // sent, delivered, read, failed (ada di status update)
        $fonnteId = $request->input('id');   // ID pesan Fonnte
        $stateId = $request->input('stateid'); // ID pesan WhatsApp dari Fonnte
        $state = $request->input('state'); // Terkadang Fonnte pakai 'state' = 2 untuk Read
        
        Log::info('Fonnte Webhook Received: ', $request->all());

        // 1. Tangani Laporan Status (Read Receipt / Delivery Report)
        if ($fonnteId || $stateId) {
            $lastOutboundMsg = null;
            
            // Coba cari berdasarkan Fonnte ID (biasanya muncul saat pertama kali sent)
            if ($fonnteId) {
                $lastOutboundMsg = WaMessage::where('fonnte_id', (string)$fonnteId)->first();
            }
            
            // Jika tidak ketemu pakai Fonnte ID, coba pakai State ID (biasanya muncul saat read)
            if (!$lastOutboundMsg && $stateId) {
                $lastOutboundMsg = WaMessage::where('state_id', (string)$stateId)->first();
            }

            if ($lastOutboundMsg) {
                $updateData = [];
                
                // Jika webhook ini membawa stateid, simpan agar webhook berikutnya bisa melacak
                if ($stateId && !$lastOutboundMsg->state_id) {
                    $updateData['state_id'] = (string)$stateId;
                }
                
                // Cek status baru dari field 'status' (string eksplisit dari Fonnte)
                if ($status) {
                    // Fonnte mengirimkan: "sent", "delivered", "read", "failed"
                    $updateData['status'] = strtolower($status);
                } elseif ($state !== null) {
                    // Fonnte mengirimkan 'state' dalam 2 format:
                    // 1. Numerik: 0=pending, 1=sent, 2=delivered
                    // 2. String: "sent", "delivered", "read", "failed"
                    if (is_numeric($state)) {
                        $stateMap = [
                            0 => 'pending',
                            1 => 'sent',
                            2 => 'delivered', // Sampai di HP tapi BELUM dibaca (centang abu-abu)
                        ];
                        if (isset($stateMap[(int)$state])) {
                            $updateData['status'] = $stateMap[(int)$state];
                        }
                    } else {
                        // State berupa string: "sent", "delivered", "read", "failed"
                        $validStates = ['sent', 'delivered', 'read', 'failed', 'pending'];
                        $stateStr = strtolower((string)$state);
                        if (in_array($stateStr, $validStates)) {
                            $updateData['status'] = $stateStr;
                        }
                    }
                }

                if (!empty($updateData)) {
                    $lastOutboundMsg->update($updateData);
                    // Broadcast agar UI terupdate real-time
                    broadcast(new \Modules\Communication\Events\NewWhatsAppMessage($lastOutboundMsg))->toOthers();
                }
            }
            
            // Jika pesan ini memang hanya berisi update status/state, hentikan di sini
            if ($status || $state) {
                return response()->json(['status' => 'success (status update)'], 200);
            }
        }

        // 2. Tangani Pesan Masuk / Pesan Keluar (dari HP)
        if (!$sender && !$request->has('target')) {
            return response()->json(['status' => 'ignored', 'reason' => 'No sender or target'], 200);
        }

        $deviceNumber = preg_replace('/[^0-9]/', '', $request->input('device', ''));
        $pengirim = preg_replace('/[^0-9]/', '', $request->input('pengirim', ''));
        
        $isFromMe = false;
        $phoneNumber = preg_replace('/[^0-9]/', '', $sender);
        
        // Deteksi apakah pesan dikirim oleh staff dari HP (Outgoing Message dari Device)
        // Fonnte menggunakan flag "quick" = true jika pesan ditangkap oleh fitur Quick Reply (balasan dari HP sendiri)
        if ($request->boolean('fromMe') || $request->boolean('is_from_me') || $request->boolean('isfromme') || $request->boolean('quick')) {
            $isFromMe = true;
        } elseif ($pengirim && $deviceNumber && $pengirim === $deviceNumber) {
            $isFromMe = true; // Pengirim adalah nomor device kita sendiri
        } elseif ($phoneNumber === $deviceNumber && $request->has('target')) {
            $isFromMe = true;
            $phoneNumber = preg_replace('/[^0-9]/', '', $request->input('target')); // Lawan bicara adalah target
        }

        try {
            // 1. Cari atau buat percakapan
            $conversation = WaConversation::firstOrCreate(
                ['phone_number' => $phoneNumber],
                ['name' => $name ?? $phoneNumber]
            );

            // Update nama jika ada dan sebelumnya kosong atau berbeda
            if ($name && $conversation->name !== $name && $conversation->name === $phoneNumber) {
                $conversation->name = $name;
            }

            // Cegah Duplikasi Pesan Keluar (Echo dari Fonnte)
            if ($isFromMe) {
                // Hapus watermark Fonnte untuk pencocokan yang akurat
                $cleanMessageText = trim(str_replace('> _Sent via fonnte.com_', '', $messageText));
                
                // Cari pesan keluar yang isinya mirip (untuk menghindari gagal match karena ada enter tambahan)
                $recentDuplicate = $conversation->messages()
                    ->where('direction', 'out')
                    ->where('message', 'like', $cleanMessageText . '%')
                    ->where('created_at', '>=', now()->subSeconds(15))
                    ->first();

                if ($recentDuplicate) {
                    return response()->json(['status' => 'ignored', 'reason' => 'Echoed outbound message detected'], 200);
                }
            }

            $conversation->last_message_at = now();
            
            // Jika pesan masuk (dari klien), tambah unread count
            if (!$isFromMe) {
                $conversation->increment('unread_count'); // Otomatis save
            } else {
                $conversation->save(); // Simpan last_message_at
            }

            // 2. Simpan pesan
            $messageType = 'text';
            if ($request->has('url')) {
                $messageType = 'media';
            }

            $waMessage = $conversation->messages()->create([
                'fonnte_id' => $request->input('id'),
                'direction' => $isFromMe ? 'out' : 'in', // Deteksi arah pesan
                'message_type' => $messageType,
                'message' => $messageText,
                'media_url' => $request->input('url'),
                'status' => $isFromMe ? 'sent' : 'delivered',
            ]);

            // 3. Trigger Broadcast Event untuk UI Chat Real-time
            broadcast(new \Modules\Communication\Events\NewWhatsAppMessage($waMessage))->toOthers();

            return response()->json(['status' => 'success'], 200);

        } catch (\Exception $e) {
            Log::error('Fonnte Webhook Error: ' . $e->getMessage());
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }
}
