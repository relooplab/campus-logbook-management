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
        $request->validate(['email' => ['required', 'email']]);

        // Pesan disamakan terlepas dari status pengiriman, agar endpoint ini
        // tidak bisa dipakai untuk menebak email mana saja yang terdaftar.
        Password::sendResetLink($request->only('email'));

        return back()->with('status', 'Jika email terdaftar, tautan reset password telah dikirim.');
    }
}
