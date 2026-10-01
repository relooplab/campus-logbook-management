<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class LandingController extends Controller
{
    /**
     * Halaman depan publik. User yang sudah masuk langsung diarahkan ke
     * Dashboard — halaman ini hanya untuk pengunjung yang belum masuk, jadi
     * tombol "Daftar" / "Masuk ke Sistem" selalu relevan.
     */
    public function __invoke(): View|RedirectResponse
    {
        if (auth()->check()) {
            return redirect()->route('dashboard');
        }

        return view('landing.index', [
            // Identitas produk tetap "Campus Logbook Management" dan tidak
            // bergantung institusi tertentu (landing bersifat institution-neutral).
            'appName' => 'Campus Logbook Management',
        ]);
    }
}