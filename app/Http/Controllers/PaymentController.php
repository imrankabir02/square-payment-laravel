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
            // Log incoming request
            Log::info('Payment request received', $request->all());

            // Validate request
            $validated = $request->validate([
                'sourceId' => 'required|string',
                'amount' => 'required|numeric|min:0.01'
            ]);

            Log::info('Validation passed', $validated);

            DB::beginTransaction();

            // Create order
            $order = Order::create([
                'amount' => $validated['amount'],
                'status' => 'pending'
            ]);

            Log::info('Order created', ['order_id' => $order->id]);

            // Process payment
            try {
                $payment = $this->squareService->processPayment(
                    order: $order,
                    sourceId: $validated['sourceId']
                );

                Log::info('Payment processed', [
                    'payment_id' => $payment->id,
                    'square_payment_id' => $payment->payment_id
                ]);

            } catch (\Exception $e) {
                Log::error('Square payment failed', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
                throw $e;
            }

            // Update order status
            $order->update(['status' => 'completed']);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Payment processed successfully',
                'data' => [
                    'order_id' => $order->id,
                    'payment_id' => $payment->payment_id,
                    'amount' => $validated['amount']
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Payment processing failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            // Return more detailed error for debugging
            return response()->json([
                'success' => false,
                'error' => config('app.debug') ? $e->getMessage() : 'Payment processing failed',
                'debug' => config('app.debug') ? [
                    'message' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine()
                ] : null
            ], 500);
        }
    }
}
