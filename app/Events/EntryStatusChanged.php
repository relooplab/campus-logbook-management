<?php

namespace App\Events;

use App\Models\LogbookEntry;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class EntryStatusChanged implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public LogbookEntry $entry,
        public string $message,
    ) {
    }

    public function broadcastOn(): array
    {
        $channels = [];
        $ownerId = $this->entry->mahasiswaTa?->user_id;
        if ($ownerId) {
            $channels[] = new PrivateChannel('user.'.$ownerId);
        }
        $dosen = $this->entry->reviewDosen();
        if ($dosen) {
            $channels[] = new PrivateChannel('user.'.$dosen->id);
        }

        return $channels;
    }

    public function broadcastAs(): string
    {
        return 'entry.status.changed';
    }
}
