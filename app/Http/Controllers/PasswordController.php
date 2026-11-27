<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PasswordController extends Controller
{
    public function edit(): View
    {
        return view('password');
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'min:12', 'confirmed'],
        ]);

        $version = DB::transaction(function () use ($request, $data) {
            $user = User::whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
            abort_unless($user->active && $user->auth_version === $request->session()->get('auth_version'), 403);
            if (! Hash::check($data['current_password'], $user->password)) {
                throw ValidationException::withMessages(['current_password' => 'Kata sandi saat ini tidak sesuai.']);
            }
            if ($data['password'] === $user->nip || Hash::check($data['password'], $user->password)) {
                throw ValidationException::withMessages(['password' => 'Kata sandi baru harus berbeda dari NIP dan kata sandi saat ini.']);
            }

            $user->password = $data['password'];
            $user->auth_version++;
            $user->save();

            return $user->auth_version;
        }, attempts: 3);

        $request->user()->refresh();
        $request->session()->regenerate();
        $request->session()->put('auth_version', $version);

        return redirect()->route('password.edit')->with('success', 'Kata sandi berhasil diubah.');
    }
}
