<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

/**
 * Registrasi mandiri mahasiswa & dosen.
 * - Mahasiswa: akun dibuat role 'mahasiswa' status PENDING, lalu disetujui dosen.
 * - Dosen: akun dibuat role 'dosen' status PENDING, lalu disetujui admin.
 * Opsional (mahasiswa): centang "sebagai penguji" + isi nama pembimbing (maks 3).
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
            'role' => ['required', 'in:mahasiswa,dosen'],
            'as_examiner' => ['nullable', 'boolean'],
            // Nama pembimbing yang diuji (maks 3), hanya jika role mahasiswa & as_examiner.
            'supervisor_1' => ['nullable', 'string', 'max:255'],
            'supervisor_2' => ['nullable', 'string', 'max:255'],
            'supervisor_3' => ['nullable', 'string', 'max:255'],
        ]);

        $role = $validated['role'] ?? 'mahasiswa';

        $supervisors = [];
        if ($role === 'mahasiswa' && $request->boolean('as_examiner')) {
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
        $user->syncRoles([$role]);

        $approver = $role === 'dosen' ? 'admin' : 'dosen';

        return redirect()->route('login')
            ->with('status', "Pendaftaran dikirim. Menunggu persetujuan {$approver}.");
    }
}
