<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Masuk - Elektronik Cuti DISNAV</title>
    <link rel="stylesheet" href="{{ asset('dashboard.css') }}">
</head>
<body class="login-page">
    <main class="login-card">
        <div class="login-logos">
            <img class="login-logo" src="{{ asset('logo-e-cuti.jpeg') }}" alt="Logo e-Cuti Distrik Navigasi">
            <img class="login-partner-logo" src="{{ asset('logo-gabungan.png') }}" alt="Logo BLU NAV dan Aksi Prima">
        </div>
        <p class="eyebrow">DISTRIK NAVIGASI</p>
        <h1>Elektronik Cuti</h1>
        <p>Masuk menggunakan akun yang disediakan administrator.</p>
        @if ($errors->any())
            <div class="notice error" role="alert">{{ $errors->first() }}</div>
        @endif
        <form method="post" action="{{ url('/login') }}">
            @csrf
            <label for="nip">NIP</label>
            <input id="nip" name="nip" type="text" inputmode="numeric" maxlength="18" value="{{ old('nip') }}" autocomplete="username" required>
            <label for="password">Kata sandi</label>
            <input id="password" name="password" type="password" autocomplete="current-password" required>
            <button type="submit">Masuk</button>
        </form>
    </main>
</body>
</html>
