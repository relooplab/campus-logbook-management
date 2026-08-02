<?php

namespace App\Events;

use App\Models\WorkspaceFile;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class WorkspaceFileUploaded implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public WorkspaceFile $file,
        public string $message,
    ) {
    }

    public function broadcastOn(): array
    {
        $channels = [];
        $ta = $this->file->mahasiswaTa;

        if ($ta) {
            if ($ta->user_id) {
                $channels[] = new PrivateChannel('user.'.$ta->user_id);
            }
            if ($ta->pembimbing_1_id) {
                $channels[] = new PrivateChannel('user.'.$ta->pembimbing_1_id);
            }
            if ($ta->pembimbing_2_id) {
                $channels[] = new PrivateChannel('user.'.$ta->pembimbing_2_id);
            }
        }

        return $channels;
    }

    public function broadcastAs(): string
    {
        return 'workspace.file.uploaded';
    }
}
