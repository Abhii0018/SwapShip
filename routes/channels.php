<?php

use Illuminate\Support\Facades\Broadcast;
use App\Models\Exchange;
use App\Models\Shipment;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
|
| Here you may register all of the event broadcasting channels that your
| application supports. The given channel authorization callbacks are
| used to check if an authenticated user can listen to the channel.
|
*/

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('exchange.{exchangeId}', function ($user, $exchangeId) {
    $exchange = Exchange::find($exchangeId);
    return $exchange && ($user->id === $exchange->requester_id || $user->id === $exchange->accepter_id);
});

Broadcast::channel('shipment.{shipmentId}', function ($user, $shipmentId) {
    $shipment = Shipment::with('exchangeRequest')->find($shipmentId);
    if (! $shipment || ! $shipment->exchangeRequest) {
        return false;
    }
    if (method_exists($user, 'isAdmin') && $user->isAdmin()) {
        return true;
    }

    return in_array((int) $user->id, [
        (int) $shipment->exchangeRequest->sender_id,
        (int) $shipment->exchangeRequest->receiver_id,
    ], true);
});
