<?php

namespace App\Http\Controllers;

use App\Services\MaterialsReviewQueue;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Halaman antrean review bahan mahasiswa yang belum ditinjau.
 * Dosen diarahkan ke sini oleh penjaga (gate) sebelum mengakses area lain
 * selama masih ada bahan pending (logbook/revisi/seminar belum dibaca).
 */
class MaterialsReviewController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        abort_unless($user->isDosen(), 403);

        [$logbook, $seminar] = array_values(app(MaterialsReviewQueue::class)->pendingFor($user));

        return view('materials-review.index', compact('logbook', 'seminar'));
    }
}
