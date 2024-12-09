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
            'squareVersion' => '2024-11-20'
        ]);
    }

    public function processPayment(Order $order, string $sourceId): Payment
    {
        try {
            Log::debug('Processing payment', [
                'order_amount' => $order->amount,
                'source_id' => $sourceId
            ]);

            // Convert amount to cents
            $amountCents = (int)round($order->amount * 100);

            // Create Money object
            $money = new Money();
            $money->setAmount($amountCents);
            $money->setCurrency('USD');

            // Create the payment request
            $request = new CreatePaymentRequest(
                $sourceId,
                uniqid('sq_', true)
            );

            // Set the amount money
            $request->setAmountMoney($money);
            $request->setLocationId(config('square.location_id'));

            Log::debug('Square payment request', [
                'amount_money' => [
                    'amount' => $money->getAmount(),
                    'currency' => $money->getCurrency()
                ],
                'source_id' => $sourceId,
                'idempotency_key' => $request->getIdempotencyKey(),
                'location_id' => $request->getLocationId()
            ]);

            $response = $this->client->getPaymentsApi()->createPayment($request);

            if ($response->isSuccess()) {
                $squarePayment = $response->getResult()->getPayment();

                Log::info('Payment successful', [
                    'square_payment_id' => $squarePayment->getId()
                ]);

                // Convert Square payment data to array safely
                $paymentData = [];
                if (method_exists($squarePayment, 'toArray')) {
                    $paymentData = $squarePayment->toArray();
                } else {
                    // Manual conversion of essential payment data
                    $paymentData = [
                        'id' => $squarePayment->getId(),
                        'status' => $squarePayment->getStatus(),
                        'amount_money' => [
                            'amount' => $squarePayment->getAmountMoney()->getAmount(),
                            'currency' => $squarePayment->getAmountMoney()->getCurrency()
                        ],
                        'created_at' => $squarePayment->getCreatedAt(),
                    ];
                }

                return Payment::create([
                    'order_id' => $order->id,
                    'payment_id' => $squarePayment->getId(),
                    'amount' => $order->amount,
                    'currency' => 'USD',
                    'status' => $squarePayment->getStatus(),
                    'meta' => $paymentData
                ]);
            }

            $errors = $response->getErrors();
            Log::error('Square API Error', [
                'errors' => array_map(function($error) {
                    return [
                        'code' => $error->getCode(),
                        'category' => $error->getCategory(),
                        'detail' => $error->getDetail()
                    ];
                }, $errors)
            ]);

            throw new \Exception($errors[0]->getDetail() ?? 'Payment failed');

        } catch (\Exception $e) {
            Log::error('Payment processing failed', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            throw $e;
        }
    }
}
