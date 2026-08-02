<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\Feature;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Halaman Jadwal Bimbingan.
 *
 * Menampilkan daftar card hyperlink jadwal bimbingan dari dosen yang telah
 * mengisi link di profil mereka. Jika belum ada dosen yang mengisi link,
 * tampilkan informasi agar mahasiswa menghubungi dosen yang dituju secara
 * langsung (WhatsApp / chat).
 */
class SchedulingController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();

        $query = User::role('dosen')
            ->whereNotNull('jadwal_bimbingan_url')
            ->where('jadwal_bimbingan_url', '!=', '');

        // Mode institusi: batasi ke institusi yang sama dengan user.
        if (Feature::isInstitution() && $user->institution_id) {
            $query->where('institution_id', $user->institution_id);
        }

        $dosen = $query->orderBy('name')->get();

        return view('scheduling.index', compact('dosen'));
    }
}