<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\Payment\PaymentFactory;
use App\Services\Payment\PaymentService;
use App\Models\Order;
use Illuminate\Support\Facades\Log;

class PaymentWebhookController extends Controller
{
    protected PaymentService $paymentService;

    public function __construct(PaymentService $paymentService)
    {
        $this->paymentService = $paymentService;
    }

    /**
     * Webhook endpoint for various payment providers.
     */
    public function handle(Request $request, string $provider)
    {
        try {
            $paymentProvider = PaymentFactory::create($provider);
            
            // Validate webhook signature (specific to provider)
            $signature = $request->header('X-Signature', '');
            if (!$paymentProvider->validateWebhook($request->all(), $signature)) {
                return response()->json(['error' => 'Invalid signature'], 403);
            }

            // Extract result
            $result = $paymentProvider->processWebhook($request->all());

            if ($result->isSuccess()) {
                $order = Order::find($result->orderId);
                if ($order) {
                    $this->paymentService->confirmPayment($order, $result->transactionId, $provider);
                }
            }

            return response()->json(['status' => 'success']);
        } catch (\Exception $e) {
            Log::error("Webhook Error ({$provider}): " . $e->getMessage());
            return response()->json(['error' => 'Webhook processing failed'], 500);
        }
    }

    /**
     * Simulated Gateway endpoint (for test environment)
     */
    public function simulateGateway(Request $request)
    {
        $orderId = $request->query('order_id');
        $amount = $request->query('amount');

        // Dans un environnement réel, on redirigerait vers une page de paiement hébergée (Stripe Checkout).
        // Ici, on valide automatiquement le paiement pour la simulation.
        
        // Simuler le payload webhook
        $payload = [
            'order_id' => $orderId,
            'status' => 'success',
            'transaction_id' => 'sim_' . uniqid()
        ];
        
        // Appeler le traitement manuel pour la simulation
        $request->merge($payload);
        $this->handle($request, 'simulated');

        // Rediriger vers le frontend (Paiement Reussi)
        return redirect(env('FRONTEND_URL', 'http://localhost:5173') . '/paiement/reussi?order_id=' . $orderId);
    }
}
