<?php

namespace App\Http\Controllers;

use App\Services\Shipping\ShippingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ShipmentWebhookController extends Controller
{
    public function __invoke(Request $request, string $provider, ShippingService $shippingService): JsonResponse
    {
        $configuredToken = (string) config('shipping.webhook_token', '');
        if ($configuredToken !== '') {
            $incomingToken = (string) $request->header('X-Webhook-Token', '');
            if (! hash_equals($configuredToken, $incomingToken)) {
                return response()->json(['message' => 'Unauthorized webhook token'], 401);
            }
        }

        $shipment = $shippingService->processWebhook($provider, (array) $request->all());
        if (! $shipment) {
            return response()->json(['message' => 'Shipment not found'], 404);
        }

        return response()->json([
            'message' => 'Webhook processed',
            'shipment_id' => $shipment->id,
            'status_code' => $shipment->status_code,
        ]);
    }
}
