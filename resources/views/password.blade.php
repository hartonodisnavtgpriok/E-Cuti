<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Ubah Kata Sandi - DISNAV</title><link rel="stylesheet" href="{{ asset('dashboard.css') }}">
</head>
<body>
    <header class="topbar">@include('partials.topbar-brand', ['pageLabel' => 'Ubah Kata Sandi'])<a href="{{ route('dashboard') }}">Dashboard</a></header>
    <main class="container">
        <h1>Ubah kata sandi</h1>
        @if (session('success')) <div class="notice" role="status">{{ session('success') }}</div> @endif
        @if ($errors->any()) <div class="notice error" role="alert"><ul>@foreach ($errors->all() as $error) <li>{{ $error }}</li> @endforeach</ul></div> @endif
        <form class="card" method="post" action="{{ route('password.update') }}">
            @csrf
            <label for="current_password">Kata sandi saat ini</label>
            <input id="current_password" name="current_password" type="password" autocomplete="current-password" required>
            <label for="password">Kata sandi baru (minimal 12 karakter dan bukan NIP)</label>
            <input id="password" name="password" type="password" minlength="12" autocomplete="new-password" required>
            <label for="password_confirmation">Ulangi kata sandi baru</label>
            <input id="password_confirmation" name="password_confirmation" type="password" minlength="12" autocomplete="new-password" required>
            <button type="submit">Simpan kata sandi</button>
        </form>
    </main>
</body>
</html>
