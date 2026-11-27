<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Pengajuan Khusus - DISNAV</title><link rel="stylesheet" href="{{ asset('dashboard.css') }}">
</head>
<body>
    <header class="topbar">@include('partials.topbar-brand', ['pageLabel' => 'Pengajuan Khusus'])<a href="{{ route('leave.index') }}">Kembali</a></header>
    <main class="container">
        <h1>Pengajuan cuti Kepala Distrik</h1>
        <div class="notice">Pengajuan cuti Kepala Distrik diproses secara khusus di luar sistem ini.</div>
    </main>
</body>
</html>
