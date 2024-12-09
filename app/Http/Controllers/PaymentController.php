<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Services\SquareService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PaymentController extends Controller
{
    private SquareService $squareService;

    public function __construct(SquareService $squareService)
    {
        $this->squareService = $squareService;
    }

    public function showPaymentForm()
    {
        return view('payment.form', [
            'applicationId' => config('square.application_id'),
            'locationId' => config('square.location_id')
        ]);
    }

    public function processPayment(Request $request)
    {
        try {
            // Debug incoming request
            Log::debug('Payment request received', [
                'request_data' => $request->all(),
                'square_config' => [
                    'location_id' => config('square.location_id'),
                    'environment' => config('square.environment')
                ]
            ]);

            // Validate request
            $validated = $request->validate([
                'sourceId' => 'required|string',
                'amount' => 'required|numeric|min:0.01|max:999999.99'
            ]);

            Log::debug('Validation passed', $validated);

            DB::beginTransaction();

            // Create order
            $order = new Order([
                'amount' => $validated['amount'],
                'status' => Order::STATUS_PENDING
            ]);

            // Debug order data before save
            Log::debug('Order data', [
                'amount' => $order->amount,
                'status' => $order->status
            ]);

            $order->save();

            // Process payment
            try {
                $payment = $this->squareService->processPayment(
                    $order,
                    $validated['sourceId']
                );

                Log::debug('Payment record created', [
                    'payment_id' => $payment->id,
                    'square_payment_id' => $payment->payment_id,
                    'amount' => $payment->amount
                ]);

                $order->update(['status' => Order::STATUS_COMPLETED]);
                DB::commit();

                return response()->json([
                    'success' => true,
                    'message' => 'Payment processed successfully',
                    'data' => [
                        'order_id' => $order->id,
                        'payment_id' => $payment->payment_id,
                        'amount' => $payment->amount
                    ]
                ]);

            } catch (\Exception $e) {
                Log::error('Payment processing failed', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);

                $order->update(['status' => Order::STATUS_FAILED]);
                throw $e;
            }

        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Payment failed', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);

            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 422);
        }
    }
}
