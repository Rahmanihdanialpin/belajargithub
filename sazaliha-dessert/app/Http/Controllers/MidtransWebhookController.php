<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class MidtransWebhookController extends Controller
{
    /**
     * Handle Midtrans notification webhook.
     */
public function handle(Request $request): Response
    {
        abort(404, 'Midtrans payment feature removed');
        $payload = $request->all();

        Log::info('Midtrans Webhook Received', $payload);

        // Validate required fields
        if (empty($payload['order_id']) || empty($payload['status_code']) || empty($payload['signature_key'])) {
            Log::warning('Midtrans Webhook: Missing required fields');
            return response('Bad Request', 400);
        }

        // Verify signature key
        if (!$this->verifySignatureKey($payload)) {
            Log::warning('Midtrans Webhook: Invalid signature key', [
                'order_id' => $payload['order_id'],
            ]);
            return response('Invalid Signature', 403);
        }

        // Find order by midtrans_order_id or order_number
        $order = Order::where('midtrans_order_id', $payload['order_id'])
            ->orWhere('order_number', $payload['order_id'])
            ->first();

        if (!$order) {
            Log::warning('Midtrans Webhook: Order not found', [
                'order_id' => $payload['order_id'],
            ]);
            return response('Order Not Found', 404);
        }

        $transactionStatus = $payload['transaction_status'] ?? '';
        $fraudStatus       = $payload['fraud_status'] ?? '';

        // Map Midtrans status to order status
        $this->updateOrderStatus($order, $transactionStatus, $fraudStatus, $payload);

        Log::info('Midtrans Webhook: Order updated', [
            'order_id'           => $order->id,
            'transaction_status' => $transactionStatus,
            'new_status'         => $order->fresh()->status,
        ]);

        return response('OK', 200);
    }

    /**
     * Verify Midtrans signature key.
     */
    private function verifySignatureKey(array $payload): bool
    {
        $serverKey   = config('services.midtrans.server_key');
        $orderId     = $payload['order_id'];
        $statusCode  = $payload['status_code'];
        $grossAmount = $payload['gross_amount'];
        $signature   = $payload['signature_key'];

        $expectedSignature = hash('sha512', $orderId . $statusCode . $grossAmount . $serverKey);

        return hash_equals($expectedSignature, $signature);
    }

    /**
     * Update order status based on Midtrans transaction status.
     */
    private function updateOrderStatus(Order $order, string $transactionStatus, string $fraudStatus, array $payload): void
    {
        $updateData = [
            'payment_status' => $transactionStatus,
            'payment_type'   => $payload['payment_type'] ?? null,
            'payment_payload'=> $payload,
        ];

        switch ($transactionStatus) {
            case 'capture':
                // For credit card, check fraud status
                if ($fraudStatus === 'challenge') {
                    $updateData['status'] = 'pending';
                } elseif ($fraudStatus === 'accept') {
                    $updateData['status'] = 'processing';
                    $updateData['paid_at'] = now();
                }
                break;

            case 'settlement':
                // Payment successful
                $updateData['status'] = 'processing';
                $updateData['paid_at'] = now();
                break;

            case 'pending':
                $updateData['status'] = 'pending';
                break;

            case 'deny':
            case 'expire':
            case 'cancel':
                $updateData['status'] = 'cancelled';
                break;

            case 'refund':
            case 'partial_refund':
                // Keep current status but record refund info
                break;

            default:
                Log::warning('Midtrans Webhook: Unknown transaction status', [
                    'status' => $transactionStatus,
                ]);
                break;
        }

        // Don't overwrite completed orders
        if ($order->status === 'completed') {
            unset($updateData['status']);
        }

        $order->update($updateData);
    }
}

