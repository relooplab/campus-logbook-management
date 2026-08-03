<?php

namespace App\Http\Controllers;

use App\Models\LogbookEntry;
use App\Models\PdfComment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PdfCommentController extends Controller
{
    public function resolve(Request $request, PdfComment $comment): JsonResponse
    {
        $this->authorize('view', $comment->entry);

        $status = $comment->resolution_status ?: ($comment->is_resolved ? PdfComment::STATUS_RESOLVED : PdfComment::STATUS_OPEN);
        if ($request->user()->isDosen()) {
            $next = $status === PdfComment::STATUS_RESOLVED
                ? PdfComment::STATUS_OPEN
                : PdfComment::STATUS_RESOLVED;
        } elseif ($comment->entry->mahasiswaTa?->user_id === $request->user()->id && $status !== PdfComment::STATUS_RESOLVED) {
            $next = $status === PdfComment::STATUS_ADDRESSED
                ? PdfComment::STATUS_OPEN
                : PdfComment::STATUS_ADDRESSED;
        } else {
            abort(403);
        }

        $comment->setResolutionStatus($next);
        $comment->save();

        // Badge "Responsif" saat semua komentar PDF resolved.
        if ($owner = $comment->entry->mahasiswaTa?->mahasiswa) {
            app(\App\Services\AchievementService::class)->evaluateForUser($owner);
        }

        return response()->json([
            'ok' => true,
            'is_resolved' => $comment->is_resolved,
            'resolution_status' => $comment->resolution_status,
        ]);
    }

    public function destroy(Request $request, PdfComment $comment): JsonResponse
    {
        $this->authorize('view', $comment->entry);

        // Hanya pembuat atau dosen yang boleh menghapus.
        if ($request->user()->id !== $comment->user_id && !$request->user()->isDosen()) {
            abort(403);
        }

        $comment->delete();

        return response()->json(['ok' => true]);
    }
}
