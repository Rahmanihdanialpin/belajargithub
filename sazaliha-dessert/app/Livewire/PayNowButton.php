<?php

namespace App\Livewire;

use App\Models\Order;
use Illuminate\Support\Facades\Log;
use Livewire\Component;
use Midtrans\Config;
use Midtrans\Snap;

class PayNowButton extends Component
{
    public Order $order;
    public ?string $snapToken = null;
    public ?string $clientKey = null;
    public bool $loading = false;
    public ?string $error = null;

    public function mount(Order $order)
    {
        $this->order = $order;
        $this->clientKey = config('services.midtrans.client_key');
    }

    /**
     * Generate Snap Token and emit event to open modal.
     */
public function generateToken(): void
    {
        abort(404, 'Midtrans payment feature removed');
        $this->loading = true;
        $this->error = null;

        // Authorization check
        if (!auth()->check()) {
            $this->error = 'Silakan login terlebih dahulu.';
            $this->loading = false;
            return;
        }

        $user = auth()->user();
        if (!$user->is_admin && $this->order->user_id !== $user->id) {
            $this->error = 'Anda tidak memiliki akses ke pesanan ini.';
            $this->loading = false;
            return;
        }

        // Only pending orders can be paid
        if ($this->order->status !== 'pending') {
            $this->error = 'Pesanan tidak dapat dibayar. Status saat ini: ' . $this->order->status;
            $this->loading = false;
            return;
        }

        try {
            Config::$serverKey    = config('services.midtrans.server_key');
            Config::$isProduction = config('services.midtrans.is_production');
            Config::$isSanitized  = config('services.midtrans.is_sanitized');
            Config::$is3ds        = config('services.midtrans.is_3ds');

            $midtransOrderId = $this->order->midtrans_order_id ?? $this->order->order_number;

            $params = [
                'transaction_details' => [
                    'order_id'     => $midtransOrderId,
                    'gross_amount' => (int) $this->order->total_amount,
                ],
                'customer_details' => [
                    'first_name' => $this->order->customer_name,
                    'email'      => $this->order->customer_email,
                    'phone'      => $this->order->customer_phone,
                ],
                'item_details' => $this->buildItemDetails(),
            ];

            $this->snapToken = Snap::getSnapToken($params);

            // Save token to order
            $this->order->update([
                'payment_token'     => $this->snapToken,
                'midtrans_order_id' => $midtransOrderId,
            ]);

            $this->dispatch('snap-token-ready', token: $this->snapToken);
        } catch (\Exception $e) {
            Log::error('Midtrans Snap Token Error', [
                'order_id' => $this->order->id,
                'message'  => $e->getMessage(),
            ]);
            $this->error = 'Gagal membuat token pembayaran. Silakan coba lagi.';
        }

        $this->loading = false;
    }

    /**
     * Build item details for Midtrans from order items.
     */
    private function buildItemDetails(): array
    {
        $items = [];

        foreach ($this->order->items as $item) {
            $items[] = [
                'id'       => (string) $item->product_id,
                'price'    => (int) $item->price,
                'quantity' => (int) $item->quantity,
                'name'     => substr($item->product->name, 0, 50),
            ];
        }

        return $items;
    }

    public function render()
    {
        return view('livewire.pay-now-button');
    }
}
