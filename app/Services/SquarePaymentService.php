<?php

namespace App\Services;

use Square\SquareClient;
use Square\Models\CreatePaymentRequest;
use Square\Models\Money;

class SquarePaymentService
{
    protected $squareClient;

    public function __construct(SquareClient $squareClient)
    {
        $this->squareClient = $squareClient;
    }

    public function processPayment(string $sourceId, int $amountInCents, string $currency = 'USD')
    {
        $money = new Money();
        $money->setAmount($amountInCents);
        $money->setCurrency($currency);

        $request = new CreatePaymentRequest(
            $sourceId,
            uniqid('txn_'),
            $money
        );

        $request->setLocationId(config('square.location_id'));

        try {
            $response = $this->squareClient->getPaymentsApi()->createPayment($request);

            if ($response->isSuccess()) {
                return [
                    'success' => true,
                    'payment' => $response->getResult()->getPayment(),
                ];
            } else {
                return [
                    'success' => false,
                    'errors' => $response->getErrors(),
                ];
            }
        } catch (\Exception $e) {
            return [
                'success' => false,
                'errors' => [$e->getMessage()],
            ];
        }
    }
}
