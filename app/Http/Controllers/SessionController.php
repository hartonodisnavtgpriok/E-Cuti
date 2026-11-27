<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class SessionController extends Controller
{
    public function create(): View
    {
        return view('login');
    }

    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'nip' => ['required', 'string', 'digits:18'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::attempt($credentials + ['active' => true])) {
            return back()->withErrors(['nip' => 'NIP atau kata sandi tidak sesuai.'])->onlyInput('nip');
        }

        $request->session()->regenerate();
        $request->session()->put('auth_version', Auth::user()->auth_version);

        return redirect()->intended(route('dashboard'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
