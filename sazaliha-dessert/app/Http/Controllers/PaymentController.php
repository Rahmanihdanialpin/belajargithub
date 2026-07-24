<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Midtrans\Config;
use Midtrans\Snap;

class PaymentController extends Controller
{
    public function __construct()
    {
        Config::$serverKey    = config('services.midtrans.server_key');
        Config::$isProduction = config('services.midtrans.is_production');
        Config::$isSanitized  = config('services.midtrans.is_sanitized');
        Config::$is3ds        = config('services.midtrans.is_3ds');
    }

    /**
     * Generate Snap Token for an order.
     */
public function getSnapToken(Order $order): JsonResponse
    {
        abort(404);
        // Authorization: only order owner or admin can request token
        if (!auth()->check()) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $user = auth()->user();
        if (!$user->is_admin && $order->user_id !== $user->id) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        // Only pending orders can be paid
        if ($order->status !== 'pending') {
            return response()->json([
                'error' => 'Order cannot be paid. Current status: ' . $order->status,
            ], 422);
        }

        try {
            $midtransOrderId = $order->midtrans_order_id ?? $order->order_number;

            $params = [
                'transaction_details' => [
                    'order_id'     => $midtransOrderId,
                    'gross_amount' => (int) $order->total_amount,
                ],
                'customer_details' => [
                    'first_name' => $order->customer_name,
                    'email'      => $order->customer_email,
                    'phone'      => $order->customer_phone,
                ],
                'item_details' => $this->buildItemDetails($order),
            ];

            $snapToken = Snap::getSnapToken($params);

            // Save token to order
            $order->update([
                'payment_token'      => $snapToken,
                'midtrans_order_id'  => $midtransOrderId,
            ]);

            return response()->json([
                'snap_token' => $snapToken,
                'client_key' => config('services.midtrans.client_key'),
            ]);
        } catch (\Exception $e) {
            Log::error('Midtrans Snap Token Error', [
                'order_id' => $order->id,
                'message'  => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Failed to generate payment token. Please try again.',
            ], 500);
        }
    }

    /**
     * Build item details for Midtrans from order items.
     */
    private function buildItemDetails(Order $order): array
    {
        $items = [];

        foreach ($order->items as $item) {
            $items[] = [
                'id'       => (string) $item->product_id,
                'price'    => (int) $item->price,
                'quantity' => (int) $item->quantity,
                'name'     => substr($item->product->name, 0, 50),
            ];
        }

        return $items;
    }
}

