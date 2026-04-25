<?php

namespace App\Policies;

use App\Models\Exchange;
use App\Models\Shipment;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class ShipmentPolicy
{
    use HandlesAuthorization;

    public function view(User $user, Shipment $shipment)
    {
        return $user->id === $shipment->exchange->requester_id || $user->id === $shipment->exchange->accepter_id || $user->isAdmin();
    }

    public function createShipment(User $user, Exchange $exchange)
    {
        return $user->id === $exchange->requester_id || $user->id === $exchange->accepter_id;
    }

    public function update(User $user, Shipment $shipment)
    {
        return $user->isAdmin();
    }

    public function delete(User $user, Shipment $shipment)
    {
        return $user->isAdmin();
    }
}
