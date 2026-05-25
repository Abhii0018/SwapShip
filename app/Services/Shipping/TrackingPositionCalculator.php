<?php

namespace App\Services\Shipping;

use App\Models\Shipment;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * Calculates a believable courier position along a route based on the
 * shipment's status and time elapsed. Pure function — no I/O, no
 * side effects. Safe to call from anywhere.
 */
class TrackingPositionCalculator
{
    /**
     * Progress anchors per status_code. Values are 0.0 (sender) → 1.0 (receiver).
     *
     * @var array<string, array{min: float, max: float}>
     */
    private const STATUS_ANCHORS = [
        'order_placed'     => ['min' => 0.00, 'max' => 0.05],
        'pickup_scheduled' => ['min' => 0.02, 'max' => 0.08],
        'picked_up'        => ['min' => 0.08, 'max' => 0.20],
        'in_transit'       => ['min' => 0.20, 'max' => 0.85],
        'out_for_delivery' => ['min' => 0.85, 'max' => 0.97],
        'delivered'        => ['min' => 1.00, 'max' => 1.00],
        'failed'           => ['min' => 0.50, 'max' => 0.50],
        'cancelled'        => ['min' => 0.00, 'max' => 0.00],
    ];

    /**
     * @return array{
     *     progress: float,
     *     status_code: string,
     *     status_label: string,
     *     position_lat: float|null,
     *     position_lng: float|null,
     *     eta: string|null
     * }
     */
    public function compute(Shipment $shipment, ?array $senderCoords, ?array $receiverCoords, ?array $routePolyline = null): array
    {
        $statusCode = (string) ($shipment->status_code ?: $this->fallbackStatusCode((string) $shipment->status));
        $progress = $this->progressForStatus($statusCode, $shipment);

        $positionLat = null;
        $positionLng = null;

        if ($senderCoords && $receiverCoords) {
            if (is_array($routePolyline) && ! empty($routePolyline)) {
                [$positionLat, $positionLng] = $this->interpolateAlongPolyline($routePolyline, $progress);
            } else {
                [$positionLat, $positionLng] = $this->interpolateStraightLine($senderCoords, $receiverCoords, $progress);
            }
        } elseif ($senderCoords) {
            $positionLat = (float) $senderCoords['lat'];
            $positionLng = (float) $senderCoords['lng'];
        }

        return [
            'progress' => $progress,
            'status_code' => $statusCode,
            'status_label' => (string) ($shipment->status_label ?: $shipment->status ?: 'Order Placed'),
            'position_lat' => $positionLat,
            'position_lng' => $positionLng,
            'eta' => $shipment->estimated_delivery_at
                ? $shipment->estimated_delivery_at->toIso8601String()
                : null,
        ];
    }

    private function progressForStatus(string $statusCode, Shipment $shipment): float
    {
        $anchor = self::STATUS_ANCHORS[$statusCode] ?? self::STATUS_ANCHORS['order_placed'];

        if ($anchor['min'] === $anchor['max']) {
            return $anchor['min'];
        }

        $referenceStart = $this->referenceStart($shipment);
        $referenceEnd = $this->referenceEnd($shipment, $referenceStart);

        $totalSeconds = max(1, $referenceEnd->diffInSeconds($referenceStart));
        $elapsedSeconds = max(0, min($totalSeconds, Carbon::now()->diffInSeconds($referenceStart, false)));
        $ratio = $totalSeconds > 0 ? ($elapsedSeconds / $totalSeconds) : 0.0;

        return $anchor['min'] + ($anchor['max'] - $anchor['min']) * max(0.0, min(1.0, $ratio));
    }

    private function referenceStart(Shipment $shipment): CarbonInterface
    {
        if ($shipment->pickup_scheduled_at instanceof CarbonInterface) {
            return $shipment->pickup_scheduled_at;
        }

        return $shipment->created_at instanceof CarbonInterface
            ? $shipment->created_at
            : Carbon::now();
    }

    private function referenceEnd(Shipment $shipment, CarbonInterface $start): CarbonInterface
    {
        if ($shipment->estimated_delivery_at instanceof CarbonInterface) {
            return $shipment->estimated_delivery_at;
        }

        $defaultHours = (int) config('shipping.tracking.eta_hours_default', 72);

        return $start->copy()->addHours(max(1, $defaultHours));
    }

    private function fallbackStatusCode(string $statusLabel): string
    {
        $map = [
            'Order Placed' => 'order_placed',
            'Picked Up' => 'picked_up',
            'In Transit' => 'in_transit',
            'Out For Delivery' => 'out_for_delivery',
            'Delivered' => 'delivered',
        ];

        return $map[$statusLabel] ?? 'order_placed';
    }

    /**
     * @param  array{lat: float, lng: float}  $from
     * @param  array{lat: float, lng: float}  $to
     * @return array{0: float, 1: float}
     */
    private function interpolateStraightLine(array $from, array $to, float $progress): array
    {
        $progress = max(0.0, min(1.0, $progress));

        return [
            (float) $from['lat'] + ((float) $to['lat'] - (float) $from['lat']) * $progress,
            (float) $from['lng'] + ((float) $to['lng'] - (float) $from['lng']) * $progress,
        ];
    }

    /**
     * @param  array<int, array{lat: float, lng: float}>  $polyline
     * @return array{0: float, 1: float}
     */
    private function interpolateAlongPolyline(array $polyline, float $progress): array
    {
        $progress = max(0.0, min(1.0, $progress));
        $count = count($polyline);
        if ($count === 0) {
            return [0.0, 0.0];
        }
        if ($count === 1 || $progress <= 0.0) {
            return [(float) $polyline[0]['lat'], (float) $polyline[0]['lng']];
        }
        if ($progress >= 1.0) {
            return [(float) $polyline[$count - 1]['lat'], (float) $polyline[$count - 1]['lng']];
        }

        $segmentLengths = [];
        $totalLength = 0.0;
        for ($i = 1; $i < $count; $i++) {
            $segLength = $this->haversineMeters($polyline[$i - 1], $polyline[$i]);
            $segmentLengths[] = $segLength;
            $totalLength += $segLength;
        }

        if ($totalLength <= 0) {
            return [(float) $polyline[0]['lat'], (float) $polyline[0]['lng']];
        }

        $targetLength = $totalLength * $progress;
        $accum = 0.0;
        for ($i = 0, $iMax = count($segmentLengths); $i < $iMax; $i++) {
            $segLength = $segmentLengths[$i];
            if ($accum + $segLength >= $targetLength || $i === $iMax - 1) {
                $remaining = max(0.0, $targetLength - $accum);
                $segProgress = $segLength > 0 ? ($remaining / $segLength) : 0.0;

                return $this->interpolateStraightLine(
                    $polyline[$i],
                    $polyline[$i + 1],
                    $segProgress
                );
            }
            $accum += $segLength;
        }

        $last = $polyline[$count - 1];

        return [(float) $last['lat'], (float) $last['lng']];
    }

    private function haversineMeters(array $a, array $b): float
    {
        $earthRadius = 6371000.0;
        $lat1 = deg2rad((float) $a['lat']);
        $lat2 = deg2rad((float) $b['lat']);
        $deltaLat = deg2rad((float) $b['lat'] - (float) $a['lat']);
        $deltaLng = deg2rad((float) $b['lng'] - (float) $a['lng']);

        $h = sin($deltaLat / 2) ** 2 + cos($lat1) * cos($lat2) * sin($deltaLng / 2) ** 2;

        return 2 * $earthRadius * asin(min(1.0, sqrt($h)));
    }
}
