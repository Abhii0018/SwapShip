<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class PaymentController extends Controller
{
    public function checkout(Request $request, Order $order): View
    {
        $user = $request->user();
        abort_unless($user && ($user->isAdmin() || $user->id === $order->buyer_id), 403);

        $razorpayKey = (string) config('payments.razorpay.key_id', '');
        $gateway = (string) ($order->gateway ?: config('payments.default_gateway', 'razorpay'));
        $stage = $this->resolveActiveStage($order);
        $amountToPay = $this->stageAmount($order, $stage);
        $stageLabel = $stage === 'remaining' ? 'Final doorstep payment' : 'Upfront payment';
        $gatewayOrderId = null;
        $gatewayInitFailed = false;

        if ($gateway === 'razorpay' && $order->payment_method === 'escrow' && $stage !== 'none' && $user->id === $order->buyer_id) {
            $gatewayOrderId = $this->createOrReuseRazorpayOrder($order, $stage);
            $gatewayInitFailed = !empty($razorpayKey) && empty($gatewayOrderId);
        }

        return view('payments.checkout', compact('order', 'razorpayKey', 'gatewayOrderId', 'stage', 'amountToPay', 'stageLabel', 'gatewayInitFailed'));
    }

    public function pay(Request $request, Order $order)
    {
        $user = $request->user();
        abort_unless($user && $user->id === $order->buyer_id, 403);

        if ($order->payment_method !== 'escrow') {
            return back()->with('error', 'Online payment is only for escrow orders.');
        }

        $gateway = (string) ($order->gateway ?: config('payments.default_gateway', 'razorpay'));
        $stage = $this->resolveActiveStage($order);
        if ($stage === 'none') {
            return back()->with('success', 'Payment is already completed for this order.');
        }

        $expectedGatewayOrderId = $stage === 'remaining'
            ? ($order->remaining_gateway_order_id ?: null)
            : ($order->upfront_gateway_order_id ?: $order->gateway_order_id);

        if ($gateway === 'razorpay') {
            $validated = $request->validate([
                'razorpay_payment_id' => 'required|string',
                'razorpay_order_id' => 'required|string',
                'razorpay_signature' => 'required|string',
            ]);

            if (! $expectedGatewayOrderId || ! hash_equals($expectedGatewayOrderId, $validated['razorpay_order_id'])) {
                return back()->with('error', 'Invalid Razorpay order reference.');
            }

            $secret = (string) config('payments.razorpay.key_secret', '');
            if ($secret === '') {
                return back()->with('error', 'Razorpay is not configured on server.');
            }

            $payload = $validated['razorpay_order_id'].'|'.$validated['razorpay_payment_id'];
            $expected = hash_hmac('sha256', $payload, $secret);
            if (! hash_equals($expected, $validated['razorpay_signature'])) {
                return back()->with('error', 'Payment verification failed.');
            }
        }

        $paymentRef = $request->input('razorpay_payment_id', 'PAY-'.strtoupper(substr(md5((string) now()->timestamp.$order->id), 0, 12)));
        $this->markStagePaid($order, $stage, $paymentRef, $gateway);

        return redirect()->route('shipments.index')->with(
            'success',
            $stage === 'remaining'
                ? 'Final payment completed successfully. Reference: '.$paymentRef
                : 'Upfront payment completed successfully. Reference: '.$paymentRef
        );
    }

    public function webhookRazorpay(Request $request): JsonResponse
    {
        $webhookSecret = (string) config('payments.razorpay.webhook_secret', '');
        if ($webhookSecret === '') {
            return response()->json(['ok' => false, 'message' => 'Webhook secret missing'], 500);
        }

        $signature = (string) $request->header('X-Razorpay-Signature', '');
        $rawBody = (string) $request->getContent();
        $expected = hash_hmac('sha256', $rawBody, $webhookSecret);

        if ($signature === '' || ! hash_equals($expected, $signature)) {
            return response()->json(['ok' => false, 'message' => 'Invalid signature'], 401);
        }

        $event = (string) $request->input('event', '');
        if ($event !== 'payment.captured') {
            return response()->json(['ok' => true, 'message' => 'Ignored event']);
        }

        $paymentEntity = $request->input('payload.payment.entity', []);
        $gatewayOrderId = (string) data_get($paymentEntity, 'order_id', '');
        $paymentId = (string) data_get($paymentEntity, 'id', '');
        $status = (string) data_get($paymentEntity, 'status', '');

        if ($gatewayOrderId === '' || $paymentId === '' || $status !== 'captured') {
            return response()->json(['ok' => false, 'message' => 'Invalid payment payload'], 422);
        }

        $order = Order::query()
            ->where('gateway', 'razorpay')
            ->where(function ($query) use ($gatewayOrderId) {
                $query->where('gateway_order_id', $gatewayOrderId)
                    ->orWhere('upfront_gateway_order_id', $gatewayOrderId)
                    ->orWhere('remaining_gateway_order_id', $gatewayOrderId);
            })
            ->first();

        if (! $order) {
            return response()->json(['ok' => false, 'message' => 'Order not found'], 404);
        }

        $stage = $this->stageForGatewayOrder($order, $gatewayOrderId);
        $this->markStagePaid($order, $stage, $paymentId, 'razorpay');

        return response()->json(['ok' => true]);
    }

    private function createOrReuseRazorpayOrder(Order $order, string $stage): ?string
    {
        $existing = $stage === 'remaining'
            ? ($order->remaining_gateway_order_id ?: null)
            : ($order->upfront_gateway_order_id ?: $order->gateway_order_id);
        if ($existing) {
            return $existing;
        }

        $keyId = (string) config('payments.razorpay.key_id', '');
        $keySecret = (string) config('payments.razorpay.key_secret', '');
        if ($keyId === '' || $keySecret === '') {
            return null;
        }

        $amountPaise = (int) round($this->stageAmount($order, $stage) * 100);
        $primaryPayload = [
            'amount' => $amountPaise,
            'currency' => 'INR',
            'receipt' => 'order_'.$order->id.'_'.$stage,
            'notes' => [
                'swapship_order_id' => (string) $order->id,
                'payment_stage' => $stage,
            ],
        ];
        $response = $this->postRazorpayOrder($keyId, $keySecret, $primaryPayload);
        $isOk = $response && $response['status'] >= 200 && $response['status'] < 300;

        if (! $isOk) {
            if ($response) {
                Log::warning('Razorpay order creation failed (primary)', [
                    'order_id' => $order->id,
                    'stage' => $stage,
                    'status' => $response['status'],
                    'body' => $response['body'],
                ]);
            }

            $fallbackPayload = [
                'amount' => $amountPaise,
                'currency' => 'INR',
                'receipt' => 'order_'.$order->id.'_'.$stage.'_'.now()->timestamp,
            ];
            $response = $this->postRazorpayOrder($keyId, $keySecret, $fallbackPayload);
            $isOk = $response && $response['status'] >= 200 && $response['status'] < 300;
        }

        if (! $isOk) {
            if ($response) {
                Log::warning('Razorpay order creation failed (fallback)', [
                    'order_id' => $order->id,
                    'stage' => $stage,
                    'status' => $response['status'],
                    'body' => $response['body'],
                ]);
            }
            return null;
        }

        $gatewayOrderId = (string) ($response['json']['id'] ?? '');
        if ($gatewayOrderId === '') {
            return null;
        }

        $payload = ['gateway' => 'razorpay'];
        if ($stage === 'remaining') {
            $payload['remaining_gateway_order_id'] = $gatewayOrderId;
        } else {
            $payload['upfront_gateway_order_id'] = $gatewayOrderId;
            $payload['gateway_order_id'] = $gatewayOrderId;
        }
        $order->update($payload);

        return $gatewayOrderId;
    }

    // Direct cURL is used (instead of Laravel Http facade) to avoid inheriting
    // shell-level proxy env vars that some local sandboxes inject and that block
    // outbound CONNECT to api.razorpay.com.
    private function postRazorpayOrder(string $keyId, string $keySecret, array $payload): ?array
    {
        $ch = curl_init('https://api.razorpay.com/v1/orders');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => [
                'Accept: application/json',
                'Content-Type: application/json',
                'User-Agent: SwapShip/1.0 (PHP cURL)',
            ],
            CURLOPT_USERPWD => $keyId.':'.$keySecret,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_FAILONERROR => false,
        ]);
        $body = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);

        if ($status === 0 || $body === false) {
            Log::warning('Razorpay cURL transport error', ['error' => $err]);
            return null;
        }

        return [
            'status' => $status,
            'body' => is_string($body) ? $body : '',
            'json' => is_string($body) ? json_decode($body, true) : null,
        ];
    }

    private function resolveActiveStage(Order $order): string
    {
        if ($order->payment_method !== 'escrow') {
            return 'none';
        }
        $remainingDue = (float) ($order->remaining_amount ?? 0) > 0.0001;
        if (! $order->upfront_paid_at) {
            return 'upfront';
        }
        if ($remainingDue && ! $order->remaining_paid_at) {
            return 'remaining';
        }

        return 'none';
    }

    private function stageAmount(Order $order, string $stage): float
    {
        if ($stage === 'remaining') {
            return (float) ($order->remaining_amount ?? 0);
        }
        if ($stage === 'upfront') {
            $upfront = (float) ($order->upfront_amount ?? 0);
            return $upfront > 0 ? $upfront : (float) $order->total_amount;
        }

        return 0.0;
    }

    private function stageForGatewayOrder(Order $order, string $gatewayOrderId): string
    {
        if ($order->remaining_gateway_order_id && hash_equals($order->remaining_gateway_order_id, $gatewayOrderId)) {
            return 'remaining';
        }

        return 'upfront';
    }

    private function markStagePaid(Order $order, string $stage, string $paymentReference, string $gateway): void
    {
        $now = now();
        $payload = ['gateway' => $gateway];

        if ($stage === 'remaining') {
            $payload['remaining_paid_at'] = $order->remaining_paid_at ?: $now;
            $payload['remaining_payment_reference'] = $order->remaining_payment_reference ?: $paymentReference;
        } else {
            $payload['upfront_paid_at'] = $order->upfront_paid_at ?: $now;
            $payload['upfront_payment_reference'] = $order->upfront_payment_reference ?: $paymentReference;
            $payload['payment_reference'] = $order->payment_reference ?: $paymentReference;
        }

        $upfrontPaid = $order->upfront_paid_at || isset($payload['upfront_paid_at']);
        $remainingRequired = (float) ($order->remaining_amount ?? 0) > 0.0001;
        $payload['second_payment_required_before_otp'] = $remainingRequired;
        $remainingPaid = $order->remaining_paid_at || isset($payload['remaining_paid_at']);
        $fullyPaid = $upfrontPaid && (! $remainingRequired || $remainingPaid);

        $payload['payment_status'] = $fullyPaid ? 'paid' : 'pending';
        if ($fullyPaid && ! $order->paid_at) {
            $payload['paid_at'] = $now;
        }

        $order->update($payload);
    }
}
