<?php

namespace App\Events;

use App\Models\LogbookEntry;
use App\Models\PdfComment;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PdfCommentCreated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public PdfComment $comment,
    ) {
    }

    public function broadcastOn(): array
    {
        $channels = [];
        $ownerId = $this->comment->entry->mahasiswaTa?->user_id;
        if ($ownerId) {
            $channels[] = new PrivateChannel('user.'.$ownerId);
        }
        $dosen = $this->comment->entry->reviewDosen();
        if ($dosen) {
            $channels[] = new PrivateChannel('user.'.$dosen->id);
        }

        return $channels;
    }

    public function broadcastAs(): string
    {
        return 'pdf.comment.created';
    }
}
