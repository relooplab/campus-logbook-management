<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

/**
 * Registrasi mandiri mahasiswa (mode individual — jalur utama).
 * Akun dibuat role 'mahasiswa' status PENDING, lalu disetujui dosen.
 * Opsional: centang "sebagai penguji" + isi nama pembimbing (maks 3).
 */
class RegisterController extends Controller
{
    public function showRegisterForm(): View
    {
        return view('auth.register');
    }

    public function register(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:users,email'],
            'password' => ['required', 'string', 'min:6', 'confirmed'],
            'as_examiner' => ['nullable', 'boolean'],
            // Nama pembimbing yang diuji (maks 3), hanya jika as_examiner.
            'supervisor_1' => ['nullable', 'string', 'max:255'],
            'supervisor_2' => ['nullable', 'string', 'max:255'],
            'supervisor_3' => ['nullable', 'string', 'max:255'],
        ]);

        $supervisors = [];
        if ($request->boolean('as_examiner')) {
            foreach (['supervisor_1', 'supervisor_2', 'supervisor_3'] as $f) {
                $v = trim((string) ($validated[$f] ?? ''));
                if ($v !== '') {
                    $supervisors[] = $v;
                }
            }
            $supervisors = array_slice($supervisors, 0, 3);
        }

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'registration_status' => 'pending',
            'examiner_supervisor_names' => $supervisors ?: null,
        ]);
        $user->syncRoles(['mahasiswa']);

        return redirect()->route('login')
            ->with('status', 'Pendaftaran dikirim. Menunggu persetujuan dosen.');
    }
}
