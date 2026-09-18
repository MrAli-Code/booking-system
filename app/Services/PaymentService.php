<?php
namespace BBS\App\Services;

use BBS\Core\App;
use BBS\App\Models\Payment;
use BBS\App\Models\Booking;
use BBS\App\Models\Invoice;

class PaymentService
{
    private array $gateways = [];

    public function __construct()
    {
        $this->registerDefaultGateways();
    }

    private function registerDefaultGateways(): void
    {
        $this->gateways = [
            'zarinpal' => [
                'name' => 'Zarinpal',
                'class' => Gateway\ZarinpalGateway::class,
                'currencies' => ['IRR', 'IRT'],
            ],
            'nextpay' => [
                'name' => 'NextPay',
                'class' => Gateway\NextpayGateway::class,
                'currencies' => ['IRR', 'IRT'],
            ],
            'idpay' => [
                'name' => 'IDPay',
                'class' => Gateway\IdpayGateway::class,
                'currencies' => ['IRR', 'IRT'],
            ],
            'card_to_card' => [
                'name' => 'Card to Card',
                'class' => Gateway\CardToCardGateway::class,
                'currencies' => ['IRR', 'IRT', 'USD', 'EUR'],
            ],
            'wallet' => [
                'name' => 'Wallet',
                'class' => Gateway\WalletGateway::class,
                'currencies' => ['IRR', 'IRT', 'USD', 'EUR'],
            ],
            'crypto' => [
                'name' => 'Cryptocurrency',
                'class' => Gateway\CryptoGateway::class,
                'currencies' => ['BTC', 'USDT', 'ETH'],
            ],
        ];
    }

    public function processPayment(int $bookingId, string $method, array $data = []): array
    {
        $booking = Booking::find($bookingId);
        if (!$booking) return ['success' => false, 'error' => 'Booking not found'];

        $customer = $booking->customer();
        $amount = $data['amount'] ?? $booking->total_price;
        $currency = $data['currency'] ?? $booking->currency;

        $payment = Payment::create([
            'tenant_id' => $booking->tenant_id,
            'booking_id' => $booking->id,
            'customer_id' => $booking->customer_id,
            'uuid' => Payment::generateUuid(),
            'amount' => $amount,
            'currency' => $currency,
            'payment_method' => $method,
            'payment_type' => $data['payment_type'] ?? 'full',
            'status' => 'pending',
        ]);

        return match ($method) {
            'zarinpal', 'nextpay', 'idpay' => $this->processGatewayPayment($payment, $method, $data),
            'card_to_card' => $this->processCardToCard($payment, $data),
            'wallet' => $this->processWalletPayment($payment, $booking, $customer),
            'crypto' => $this->processCryptoPayment($payment, $data),
            'cash' => $this->processCashPayment($payment),
            default => ['success' => false, 'error' => 'Unsupported payment method'],
        };
    }

    private function processGatewayPayment(Payment $payment, string $gateway, array $data): array
    {
        $callbackUrl = $data['callback_url'] ?? App::getInstance()->config('app.url', '') . '/payment/callback';
        $apiKey = $this->getGatewayApiKey($payment->tenant_id, $gateway);

        $gatewayData = [
            'amount' => $payment->amount,
            'currency' => $payment->currency,
            'callback_url' => $callbackUrl,
            'transaction_id' => $payment->uuid,
            'description' => "Booking #{$payment->booking_id}",
        ];

        // Gateway-specific implementation would go here
        // For now, return the payment URL structure
        $payment->update(
            ['gateway_data' => json_encode(['gateway' => $gateway, 'request' => $gatewayData])],
            'id = ?',
            [$payment->id]
        );

        return [
            'success' => true,
            'payment' => $payment->toArray(),
            'redirect_url' => route('payment.gateway', ['gateway' => $gateway, 'id' => $payment->uuid]),
            'payment_id' => $payment->id,
        ];
    }

    public function verifyGatewayPayment(string $gateway, array $data): array
    {
        $payment = Payment::findBy('uuid', $data['transaction_id'] ?? $data['id'] ?? '');
        if (!$payment) return ['success' => false, 'error' => 'Payment not found'];

        if ($data['status'] === 'OK') {
            $payment->status = 'completed';
            $payment->transaction_id = $data['ref_id'] ?? $data['transaction_id'] ?? null;
            $payment->verification_data = json_encode($data);
            $payment->verified_at = date('Y-m-d H:i:s');
            $payment->save();

            // Update booking status
            $booking = Booking::find($payment->booking_id);
            if ($booking && $booking->status === 'pending') {
                $booking->status = 'confirmed';
                $booking->save();
            }

            // Generate invoice
            $this->generateInvoice($payment);

            return ['success' => true, 'payment' => $payment->toArray()];
        }

        $payment->status = 'failed';
        $payment->fail_reason = $data['error'] ?? 'Payment verification failed';
        $payment->save();

        return ['success' => false, 'error' => $payment->fail_reason];
    }

    private function processCardToCard(Payment $payment, array $data): array
    {
        $payment->card_to_card_info = json_encode([
            'receipt_image' => $data['receipt_image'] ?? null,
            'card_number' => $data['card_number'] ?? null,
            'sender_name' => $data['sender_name'] ?? null,
            'transfer_date' => $data['transfer_date'] ?? null,
            'amount' => $data['amount'] ?? $payment->amount,
        ]);
        $payment->status = 'pending';
        $payment->save();

        return [
            'success' => true,
            'payment' => $payment->toArray(),
            'message' => 'Payment receipt uploaded, awaiting manual verification.',
        ];
    }

    public function verifyCardToCard(int $paymentId, int $userId, string $status = 'completed'): array
    {
        $payment = Payment::find($paymentId);
        if (!$payment) return ['success' => false, 'error' => 'Payment not found'];

        $payment->status = $status;
        $payment->verified_by = $userId;
        $payment->verified_at = date('Y-m-d H:i:s');
        $payment->save();

        if ($status === 'completed') {
            $booking = Booking::find($payment->booking_id);
            if ($booking && $booking->status === 'pending') {
                $booking->status = 'confirmed';
                $booking->save();
            }
            $this->generateInvoice($payment);
        }

        return ['success' => true, 'payment' => $payment->toArray()];
    }

    private function processWalletPayment(Payment $payment, Booking $booking, $customer): array
    {
        $walletService = new WalletService();
        $balance = $walletService->getBalance($booking->customer_id);

        if ($balance < $payment->amount) {
            $payment->status = 'failed';
            $payment->fail_reason = 'Insufficient wallet balance';
            $payment->save();
            return ['success' => false, 'error' => 'Insufficient wallet balance'];
        }

        $tx = $customer->deductWalletBalance(
            $payment->amount,
            'payment',
            "Payment for booking #{$booking->booking_code}"
        );

        if (!$tx) {
            $payment->status = 'failed';
            $payment->fail_reason = 'Transaction failed';
            $payment->save();
            return ['success' => false, 'error' => 'Transaction failed'];
        }

        $payment->status = 'completed';
        $payment->verified_at = date('Y-m-d H:i:s');
        $payment->save();

        $booking->status = 'confirmed';
        $booking->save();

        $this->generateInvoice($payment);

        return ['success' => true, 'payment' => $payment->toArray()];
    }

    private function processCryptoPayment(Payment $payment, array $data): array
    {
        $walletAddresses = [
            'BTC' => '1A1zP1eP5QGefi2DMPTfTL5SLmv7DivfNa',
            'USDT' => '0x742d35Cc6634C0532925a3b844Bc9e7595f2bD18',
            'ETH' => '0x742d35Cc6634C0532925a3b844Bc9e7595f2bD18',
        ];

        $currency = $payment->currency;
        $address = $walletAddresses[$currency] ?? '';

        $payment->crypto_info = json_encode([
            'wallet_address' => $address,
            'currency' => $currency,
            'expected_amount' => $payment->amount,
            'network' => $data['network'] ?? ($currency === 'BTC' ? 'Bitcoin' : 'ERC20'),
        ]);
        $payment->status = 'pending';
        $payment->save();

        return [
            'success' => true,
            'payment' => $payment->toArray(),
            'wallet_address' => $address,
            'expected_amount' => $payment->amount,
            'currency' => $currency,
        ];
    }

    private function processCashPayment(Payment $payment): array
    {
        $payment->status = 'pending';
        $payment->save();

        return [
            'success' => true,
            'payment' => $payment->toArray(),
            'message' => 'Cash payment registered, awaiting confirmation.',
        ];
    }

    private function generateInvoice(Payment $payment): Invoice
    {
        $booking = Booking::find($payment->booking_id);

        $invoice = Invoice::create([
            'tenant_id' => $payment->tenant_id,
            'booking_id' => $payment->booking_id,
            'customer_id' => $payment->customer_id,
            'invoice_number' => 'INV-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -6)),
            'uuid' => Invoice::generateUuid(),
            'subtotal' => $booking->subtotal ?? $payment->amount,
            'discount_amount' => $booking->discount_amount ?? 0,
            'total_amount' => $payment->amount,
            'paid_amount' => $payment->amount,
            'due_amount' => 0,
            'currency' => $payment->currency,
            'status' => 'paid',
            'paid_at' => date('Y-m-d H:i:s'),
        ]);

        return $invoice;
    }

    private function getGatewayApiKey(int $tenantId, string $gateway): string
    {
        $result = App::getInstance()->getDatabase()->fetch(
            "SELECT config FROM {prefix}payment_gateways WHERE tenant_id = ? AND gateway = ? AND is_active = 1",
            [$tenantId, $gateway]
        );
        if ($result) {
            $config = json_decode($result->config, true);
            return $config['api_key'] ?? $config['merchant_id'] ?? '';
        }
        return '';
    }

    public function getAvailableGateways(int $tenantId): array
    {
        $active = App::getInstance()->getDatabase()->fetchAll(
            "SELECT gateway, config FROM {prefix}payment_gateways WHERE tenant_id = ? AND is_active = 1 ORDER BY sort_order",
            [$tenantId]
        );
        $result = [];
        foreach ($active as $g) {
            $result[] = [
                'code' => $g->gateway,
                'name' => $this->gateways[$g->gateway]['name'] ?? $g->gateway,
                'currencies' => $this->gateways[$g->gateway]['currencies'] ?? ['IRR'],
            ];
        }
        return $result;
    }
}
