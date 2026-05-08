<?php

namespace App\Http\Controllers;

use App\Models\Message;
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

        if ($gateway === 'razorpay') {
            $verificationError = $this->verifyRazorpayPaymentRequest($request, $order, $stage);
            if ($verificationError !== null) {
                return back()->with('error', $verificationError);
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

    public function razorpayCallback(Request $request, Order $order)
    {
        $user = $request->user();
        if ($user && ! ($user->isAdmin() || $user->id === $order->buyer_id)) {
            abort(403);
        }

        if ($order->payment_method !== 'escrow') {
            return $this->callbackRedirect($user, 'error', 'Online payment is only for escrow orders.');
        }

        $gateway = (string) ($order->gateway ?: config('payments.default_gateway', 'razorpay'));
        if ($gateway !== 'razorpay') {
            return $this->callbackRedirect($user, 'error', 'Razorpay is not configured for this order.');
        }

        $stage = $this->resolveActiveStage($order);
        if ($stage === 'none') {
            return $this->callbackRedirect($user, 'success', 'Payment is already completed for this order.');
        }

        $verificationError = $this->verifyRazorpayPaymentRequest($request, $order, $stage);
        if ($verificationError !== null) {
            if ($user) {
                return redirect()->route('payments.checkout', $order)->with('error', $verificationError);
            }
            return redirect()->route('home')->with('error', $verificationError);
        }

        $paymentRef = (string) $request->input('razorpay_payment_id', '');
        if ($paymentRef === '') {
            $paymentRef = (string) $request->query('razorpay_payment_id', '');
        }
        if ($paymentRef === '') {
            $paymentRef = 'PAY-'.strtoupper(substr(md5((string) now()->timestamp.$order->id), 0, 12));
        }

        $this->markStagePaid($order, $stage, $paymentRef, $gateway);

        return $this->callbackRedirect(
            $user,
            'success',
            $stage === 'remaining'
                ? 'Final payment completed successfully. Reference: '.$paymentRef
                : 'Upfront payment completed successfully. Reference: '.$paymentRef
        );
    }

    public function initRazorpay(Request $request, Order $order): JsonResponse
    {
        $user = $request->user();
        abort_unless($user && $user->id === $order->buyer_id, 403);

        if ($order->payment_method !== 'escrow') {
            return response()->json([
                'ok' => false,
                'message' => 'Online payment is only for escrow orders.',
            ], 422);
        }

        $gateway = (string) ($order->gateway ?: config('payments.default_gateway', 'razorpay'));
        if ($gateway !== 'razorpay') {
            return response()->json([
                'ok' => false,
                'message' => 'Razorpay is not the configured gateway for this order.',
            ], 422);
        }

        $keyId = trim((string) config('payments.razorpay.key_id', ''));
        if ($keyId === '') {
            return response()->json([
                'ok' => false,
                'message' => 'Razorpay key is missing on server.',
            ], 500);
        }

        $stage = $this->resolveActiveStage($order);
        if ($stage === 'none') {
            return response()->json([
                'ok' => false,
                'message' => 'No pending payment for this order.',
            ], 422);
        }

        $gatewayOrderId = $this->createOrReuseRazorpayOrder($order, $stage);
        if (! $gatewayOrderId) {
            return response()->json([
                'ok' => true,
                'direct_mode' => true,
                'key' => $keyId,
                'order_id' => null,
                'amount' => (int) round($this->stageAmount($order, $stage) * 100),
                'stage' => $stage,
                'stage_label' => $stage === 'remaining' ? 'Final doorstep payment' : 'Upfront payment',
                'message' => 'Proceeding with secure direct checkout mode.',
            ]);
        }

        return response()->json([
            'ok' => true,
            'direct_mode' => false,
            'key' => $keyId,
            'order_id' => $gatewayOrderId,
            'amount' => (int) round($this->stageAmount($order, $stage) * 100),
            'stage' => $stage,
            'stage_label' => $stage === 'remaining' ? 'Final doorstep payment' : 'Upfront payment',
        ]);
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

        $keyId = trim((string) config('payments.razorpay.key_id', ''));
        $keySecret = trim((string) config('payments.razorpay.key_secret', ''));
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

    private function fetchRazorpayPayment(string $paymentId, string $keyId, string $keySecret): ?array
    {
        if ($paymentId === '' || $keyId === '' || $keySecret === '') {
            return null;
        }

        $ch = curl_init('https://api.razorpay.com/v1/payments/'.rawurlencode($paymentId));
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Accept: application/json',
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

        if ($status < 200 || $status >= 300 || $body === false) {
            Log::warning('Razorpay payment fetch failed', [
                'payment_id' => $paymentId,
                'status' => $status,
                'error' => $err,
                'body' => is_string($body) ? $body : '',
            ]);
            return null;
        }

        $json = json_decode((string) $body, true);
        return is_array($json) ? $json : null;
    }

    private function fetchRazorpayPaymentWithRetry(
        string $paymentId,
        string $keyId,
        string $keySecret,
        int $attempts = 3,
        int $sleepMs = 200
    ): ?array {
        $attempts = max(1, $attempts);
        for ($i = 0; $i < $attempts; $i++) {
            $payment = $this->fetchRazorpayPayment($paymentId, $keyId, $keySecret);
            if (is_array($payment)) {
                return $payment;
            }
            if ($i < $attempts - 1) {
                usleep(max(1, $sleepMs) * 1000);
            }
        }

        return null;
    }

    private function verifyRazorpayPaymentRequest(Request $request, Order $order, string $stage): ?string
    {
        $expectedGatewayOrderId = $stage === 'remaining'
            ? ($order->remaining_gateway_order_id ?: null)
            : ($order->upfront_gateway_order_id ?: $order->gateway_order_id);

        $keyId = trim((string) config('payments.razorpay.key_id', ''));
        $paymentId = (string) ($request->input('razorpay_payment_id', $request->query('razorpay_payment_id', '')));
        $orderId = (string) ($request->input('razorpay_order_id', $request->query('razorpay_order_id', '')));
        $signature = (string) ($request->input('razorpay_signature', $request->query('razorpay_signature', '')));
        $directMode = (bool) $request->boolean('razorpay_direct_mode');
        $expectedAmount = (int) round($this->stageAmount($order, $stage) * 100);

        $secret = trim((string) config('payments.razorpay.key_secret', ''));
        if ($secret === '') {
            return 'Razorpay is not configured on server.';
        }

        if ($paymentId === '' && $expectedGatewayOrderId) {
            $fallbackPayment = $this->fetchLatestRazorpayOrderPaymentWithRetry(
                $expectedGatewayOrderId,
                $keyId,
                $secret,
                $expectedAmount,
                8,
                700
            );
            if (is_array($fallbackPayment)) {
                $paymentId = (string) ($fallbackPayment['id'] ?? '');
                if ($paymentId !== '') {
                    $request->merge(['razorpay_payment_id' => $paymentId]);
                }
            }
        }

        if ($paymentId === '') {
            return 'Missing Razorpay payment reference.';
        }

        if ($orderId !== '' && $signature !== '') {
            if (! $directMode && (! $expectedGatewayOrderId || ! hash_equals($expectedGatewayOrderId, $orderId))) {
                return 'Invalid Razorpay order reference.';
            }
            $payload = $orderId.'|'.$paymentId;
            $expected = hash_hmac('sha256', $payload, $secret);
            if (! hash_equals($expected, $signature)) {
                return 'Payment verification failed.';
            }

            return null;
        }

        // Some mobile redirect flows can return only payment_id to callback.
        // In that case, verify by fetching payment details from Razorpay API.
        $payment = $this->fetchRazorpayPaymentWithRetry(
            $paymentId,
            $keyId,
            $secret,
            12,
            800
        );

        if ((! $payment || ! in_array((string) ($payment['status'] ?? ''), ['captured', 'authorized'], true)) && $expectedGatewayOrderId) {
            $payment = $this->fetchLatestRazorpayOrderPaymentWithRetry(
                $expectedGatewayOrderId,
                $keyId,
                $secret,
                $expectedAmount,
                8,
                700
            );
        }

        $gatewayStatus = (string) ($payment['status'] ?? '');
        if (! $payment || ! in_array($gatewayStatus, ['captured', 'authorized'], true)) {
            return 'Payment confirmation is still syncing with Razorpay. Please wait a few seconds and retry.';
        }
        $fetchedOrderId = (string) ($payment['order_id'] ?? '');
        if ($expectedGatewayOrderId && $fetchedOrderId !== '' && ! hash_equals($expectedGatewayOrderId, $fetchedOrderId)) {
            return 'Invalid Razorpay order reference.';
        }
        $paidAmount = (int) ($payment['amount'] ?? 0);
        if ($paidAmount !== $expectedAmount) {
            return 'Paid amount does not match expected stage amount.';
        }

        if ($paymentId === '') {
            $paymentId = (string) ($payment['id'] ?? '');
            if ($paymentId !== '') {
                $request->merge(['razorpay_payment_id' => $paymentId]);
            }
        }

        return null;
    }

    private function fetchLatestRazorpayOrderPaymentWithRetry(
        string $gatewayOrderId,
        string $keyId,
        string $keySecret,
        int $expectedAmountPaise,
        int $attempts = 5,
        int $sleepMs = 500
    ): ?array {
        $attempts = max(1, $attempts);
        for ($i = 0; $i < $attempts; $i++) {
            $payment = $this->fetchLatestRazorpayOrderPayment($gatewayOrderId, $keyId, $keySecret, $expectedAmountPaise);
            if (is_array($payment)) {
                return $payment;
            }
            if ($i < $attempts - 1) {
                usleep(max(1, $sleepMs) * 1000);
            }
        }

        return null;
    }

    private function fetchLatestRazorpayOrderPayment(
        string $gatewayOrderId,
        string $keyId,
        string $keySecret,
        int $expectedAmountPaise
    ): ?array {
        if ($gatewayOrderId === '' || $keyId === '' || $keySecret === '') {
            return null;
        }

        $ch = curl_init('https://api.razorpay.com/v1/orders/'.rawurlencode($gatewayOrderId).'/payments');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Accept: application/json',
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

        if ($status < 200 || $status >= 300 || $body === false) {
            Log::warning('Razorpay order payments fetch failed', [
                'gateway_order_id' => $gatewayOrderId,
                'status' => $status,
                'error' => $err,
                'body' => is_string($body) ? $body : '',
            ]);
            return null;
        }

        $json = json_decode((string) $body, true);
        $items = is_array($json['items'] ?? null) ? $json['items'] : [];
        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }
            $status = (string) ($item['status'] ?? '');
            $amount = (int) ($item['amount'] ?? 0);
            if (in_array($status, ['captured', 'authorized'], true) && $amount === $expectedAmountPaise) {
                return $item;
            }
        }

        return null;
    }

    private function callbackRedirect($user, string $flashType, string $message)
    {
        if ($user) {
            return redirect()->route('shipments.index')->with($flashType, $message);
        }

        return redirect()->route('login')->with($flashType, $message);
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
        $alreadyAtThisStage = $stage === 'remaining'
            ? (bool) $order->remaining_paid_at
            : (bool) $order->upfront_paid_at;

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

        if (! $alreadyAtThisStage) {
            $this->propagatePaymentStatusToExchange($order, $stage, $fullyPaid);
        }
    }

    private function propagatePaymentStatusToExchange(Order $order, string $stage, bool $fullyPaid): void
    {
        $order->loadMissing('shipment.exchangeRequest');
        $exchange = $order->shipment?->exchangeRequest;
        if (! $exchange) {
            return;
        }

        if (in_array($exchange->status, ['Pending', 'Accepted'], true)) {
            $exchange->update(['status' => 'In Progress']);
        }

        $message = $stage === 'remaining'
            ? 'Final doorstep payment received.'
            : 'Upfront payment received.';
        if ($fullyPaid) {
            $message .= ' All payments are now complete.';
        }

        try {
            Message::query()->create([
                'exchange_request_id' => $exchange->id,
                'sender_id' => $order->buyer_id,
                'body' => '[Payment update] '.$message,
            ]);
        } catch (\Throwable $e) {
            Log::warning('Failed to record payment chat update', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
