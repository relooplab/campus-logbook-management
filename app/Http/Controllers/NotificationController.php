<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\View\View;

class NotificationController extends Controller
{
    /**
     * Halaman daftar notifikasi (baca & belum baca).
     */
    public function index(Request $request): View
    {
        $notifications = $request->user()
            ->notifications()
            ->latest()
            ->paginate(20);

        return view('notifications.index', compact('notifications'));
    }

    /**
     * Tandai satu notifikasi sebagai sudah dibaca, lalu arahkan ke link.
     */
    public function show(Request $request, DatabaseNotification $notification): RedirectResponse
    {
        abort_if($notification->notifiable_id !== $request->user()->id, 403);

        if ($notification->unread()) {
            $notification->markAsRead();
        }

        $url = data_get($notification->data, 'url');

        return $url ? redirect($url) : back();
    }

    /**
     * Tandai semua notifikasi sudah dibaca.
     */
    public function markAllRead(Request $request): RedirectResponse
    {
        $request->user()->unreadNotifications->markAsRead();

        return back()->with('success', 'Semua notifikasi ditandai sudah dibaca.');
    }

    /**
     * JSON untuk dropdown realtime (10 terbaru + jumlah belum dibaca).
     */
    public function dropdown(Request $request): \Illuminate\Http\JsonResponse
    {
        $items = $request->user()->notifications()->latest()->take(10)->get();
        $unread = $request->user()->unreadNotifications()->count();

        return response()->json([
            'unread' => $unread,
            'items' => $items->map(function ($n) {
                return [
                    'id' => $n->id,
                    'message' => data_get($n->data, 'message'),
                    'url' => data_get($n->data, 'url'),
                    'read_at' => $n->read_at,
                    'created_at' => $n->created_at?->diffForHumans(),
                ];
            }),
        ]);
    }
}
