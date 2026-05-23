<?php

use App\Models\Ticket;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

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

// Autoryzacja dla kanału prywatnego zgłoszenia
Broadcast::channel('ticket.{ticketId}', function (User $user, $ticketId) {
    $ticket = Ticket::find($ticketId);

    // Używamy polityki TicketPolicy do sprawdzenia uprawnień.
    return $ticket && $user->can('view', $ticket);
});
