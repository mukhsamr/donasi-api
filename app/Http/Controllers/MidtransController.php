<?php

namespace App\Http\Controllers;

use App\Models\Transaksi;
use App\Traits\HasTryCatch;
use Illuminate\Http\Request;
use Midtrans\Config;

class MidtransController extends Controller
{
    use HasTryCatch;

    public function snap()
    {
        return response()->json([
            'link' => env('SNAP_LINK'),
            'clientKey' => env('MIDTRANS_CLIENT')
        ]);
    }

    public function token(Request $request)
    {
        Config::$serverKey = env('MIDTRANS_SERVER');
        Config::$isProduction = env('MIDTRANS_PROD');
        Config::$isSanitized = true;
        Config::$is3ds = true;

        $snapToken = \Midtrans\Snap::getSnapToken($request->all());
        return $snapToken;
    }

    public function store(Request $request)
    {
        $message = $this::execute(
            try: fn () => Transaksi::create($request->all()),
            message: 'simpan transaksi'
        );

        return $message;
    }

    public function handler(Request $request)
    {
        $signature = hash('sha512', $request->order_id . $request->status_code . $request->gross_amount . env('MIDTRANS_SERVER'));

        if ($request->signature_key != $signature) {
            return response()->json([
                'message' => 'Invalid signature'
            ]);
        }

        $message = $this::execute(
            try: fn () => Transaksi::where('transaction_id', $request->transaction_id)
                ->update([
                    'status_code' => $request->status_code,
                    'transaction_status' => $request->transaction_status
                ]),
            message: 'update transaksi'
        );

        return $message;
    }
}
