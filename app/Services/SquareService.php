<?php

namespace App\Services;

use Square\SquareClient;
use Square\Environment;
use Square\Models\CreatePaymentRequest;
use Square\Models\Money;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Support\Facades\Log;

class SquareService
{
    private readonly SquareClient $client;

    public function __construct()
    {
        $this->client = new SquareClient([
            'accessToken' => config('square.access_token'),
            'environment' => config('square.environment') === 'production'
                ? Environment::PRODUCTION
                : Environment::SANDBOX,
        ]);
    }

    public function processPayment(Order $order, string $sourceId): Payment
    {
        try {
            Log::info('Processing Square payment', [
                'order_id' => $order->id,
                'amount' => $order->amount
            ]);

            $paymentsApi = $this->client->getPaymentsApi();

            // Convert to cents
            $amountCents = (int)($order->amount * 100);

            Log::info('Creating Money object', ['amount_cents' => $amountCents]);

            // Create Money object
            $money = new Money();
            $money->setAmount($amountCents);
            $money->setCurrency('USD');

            Log::info('Creating payment request');

            // Create payment request
            $request = new CreatePaymentRequest(
                $sourceId,          // source_id
                uniqid(),          // idempotency_key
                $money            // amount_money
            );

            Log::info('Sending payment request to Square');

            $response = $paymentsApi->createPayment($request);

            if ($response->isSuccess()) {
                $squarePayment = $response->getResult()->getPayment();

                Log::info('Payment successful', [
                    'square_payment_id' => $squarePayment->getId()
                ]);

                return Payment::create([
                    'order_id' => $order->id,
                    'payment_id' => $squarePayment->getId(),
                    'amount' => $order->amount,
                    'currency' => 'USD',
                    'status' => $squarePayment->getStatus(),
                    'meta' => [
                        'square_payment' => $squarePayment->toArray()
                    ]
                ]);
            }

            $errors = $response->getErrors();
            Log::error('Square API Error', ['errors' => $errors]);
            throw new \Exception($errors[0]->getDetail());

        } catch (\Exception $e) {
            Log::error('Square payment processing failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }
}
