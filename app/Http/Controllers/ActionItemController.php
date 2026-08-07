<?php

namespace App\Http\Controllers;

use App\Models\LogbookEntry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ActionItemController extends Controller
{
    /**
     * Tambah action item dari feedback revisi.
     * Boleh dilakukan mahasiswa (owner) maupun dosen pembimbing/reviewer
     * sehingga dosen bisa "mendorong" checklist langsung dari feedback-nya.
     */
    public function store(Request $request, LogbookEntry $logbook): JsonResponse
    {
        $this->authorize('manageActionItems', $logbook);

        $validated = $request->validate([
            'text' => ['required', 'string', 'max:500'],
        ]);

        $item = $logbook->actionItems()->create(['text' => $validated['text']]);

        return response()->json($item, 201);
    }

    /**
     * Toggle selesai action item.
     */
    public function toggle(Request $request, LogbookEntry $logbook, \App\Models\ActionItem $item): JsonResponse
    {
        $this->authorize('update', $logbook);
        abort_unless($item->logbook_entry_id === $logbook->id, 404);
        $item->update(['is_done' => !$item->is_done]);

        // Semua selesai?
        $allDone = $logbook->actionItems()->count() > 0
            && $logbook->actionItems()->where('is_done', false)->count() === 0;

        return response()->json(['ok' => true, 'is_done' => $item->is_done, 'all_done' => $allDone]);
    }

    public function destroy(Request $request, LogbookEntry $logbook, \App\Models\ActionItem $item): JsonResponse
    {
        $this->authorize('manageActionItems', $logbook);
        abort_unless($item->logbook_entry_id === $logbook->id, 404);
        $item->delete();

        return response()->json(['ok' => true]);
    }
}
