<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Existing workspace references also need a retained name when the file is removed.
        DB::table('messages')
            ->join('workspace_files', 'workspace_files.id', '=', 'messages.attachable_id')
            ->where('messages.attachable_type', \App\Models\WorkspaceFile::class)
            ->select('messages.id', 'workspace_files.id as file_id', 'workspace_files.original_name')
            ->orderBy('messages.id')
            ->chunk(200, function ($messages) {
                foreach ($messages as $message) {
                    DB::table('message_workspace_files')->insert([
                        'message_id' => $message->id,
                        'workspace_file_id' => $message->file_id,
                        'original_name' => $message->original_name,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            });
    }

    public function down(): void
    {
        // A restored reference should not remove attachments created after migration.
    }
};