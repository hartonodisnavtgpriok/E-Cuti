<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureActive
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()->active || $request->session()->get('auth_version') !== $request->user()->auth_version) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors(['nip' => 'Akun tidak aktif atau sesi sudah berakhir. Silakan masuk kembali.']);
        }

        return $next($request);
    }
}
