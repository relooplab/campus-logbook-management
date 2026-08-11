<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\View\View;

class ForgotPasswordController extends Controller
{
    public function showLinkRequestForm(): View
    {
        return view('auth.forgot-password');
    }

    public function sendResetLink(Request $request): RedirectResponse
    {
        // Boleh berupa email, NIM (mahasiswa), atau NIDN (dosen).
        $validated = $request->validate([
            'email' => ['required', 'string', 'max:255'],
        ]);

        // Jika bukan email (berisi '@'), anggap NIM/NIDN → resolve ke email pemiliknya.
        $login = trim($validated['email']);
        $recipientEmail = str_contains($login, '@')
            ? $login
            : (\App\Models\User::findByIdentifier($login)?->email);

        // Pesan disamakan terlepas dari status pengiriman, agar endpoint ini
        // tidak bisa dipakai untuk menebak email mana saja yang terdaftar.
        Password::sendResetLink(['email' => $recipientEmail]);

        return back()->with('status', 'Jika email terdaftar, tautan reset password telah dikirim.');
    }
}
