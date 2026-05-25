<?php

namespace App\Events;

use App\Models\Shipment;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ShipmentTracked implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public int $shipmentId;

    public string $statusCode;

    public string $statusLabel;

    public ?string $updatedAt;

    public function __construct(Shipment $shipment)
    {
        $this->shipmentId = (int) $shipment->id;
        $this->statusCode = (string) ($shipment->status_code ?? '');
        $this->statusLabel = (string) ($shipment->status_label ?? $shipment->status ?? '');
        $this->updatedAt = $shipment->updated_at?->toIso8601String();
    }

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('shipment.'.$this->shipmentId);
    }

    public function broadcastAs(): string
    {
        return 'shipment.tracked';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'shipment_id' => $this->shipmentId,
            'status_code' => $this->statusCode,
            'status_label' => $this->statusLabel,
            'updated_at' => $this->updatedAt,
        ];
    }
}
