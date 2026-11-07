<?php

namespace App\Http\Controllers;

use App\Services\MidtransService;
use App\Services\OrderService;
use Illuminate\Http\Request;

class MidtransNotificationController extends Controller
{
    // POST /api/midtrans/notification (dipanggil oleh server Midtrans, tanpa login)
    public function __invoke(Request $request, MidtransService $midtrans, OrderService $orders)
    {
        $payload = $request->all();

        // 1. Tolak request yang bukan dari Midtrans
        if (! $midtrans->isValidSignature($payload)) {
            return response()->json(['message' => 'Signature tidak valid.'], 403);
        }

        // 2. Proses status pembayaran
        $orders->handleNotification($payload);

        // 3. Balas 200 agar Midtrans tidak mengirim ulang notifikasi
        return response()->json(['message' => 'OK']);
    }
}
