<?php

namespace App\Events;

use App\Models\Message;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class NewMessageSent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $message;

    /**
     * Create a new event instance.
     */
    public function __construct(Message $message)
    {
        $this->message = $message->load('sender');
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        // Używamy kanału prywatnego, aby tylko autoryzowani użytkownicy
        // mogli nasłuchiwać na wiadomości w danym zgłoszeniu.
        return [
            new PrivateChannel('ticket.' . $this->message->ticket_id),
        ];
    }

    /**
     * Nazwa, pod jaką zdarzenie będzie rozgłaszane.
     */
    public function broadcastAs(): string
    {
        return 'new.message';
    }
}
