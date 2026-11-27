<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Riwayat Perubahan - DISNAV</title><link rel="stylesheet" href="{{ asset('dashboard.css') }}">
</head>
<body>
    <header class="topbar">@include('partials.topbar-brand', ['pageLabel' => 'Riwayat Perubahan'])<a href="{{ route('admin.index') }}">Kembali</a></header>
    <main class="container">
        <h1>{{ $employee->name }}</h1>
        <h2>Saldo awal dan koreksi</h2>
        @forelse ($balanceHistory as $event)
            <section class="card employee">
                <strong>{{ $event->actor_name }}</strong> &middot; {{ $event->created_at }}
                <p>Alasan: {{ $event->reason }}</p>
                <p>Sebelum: {{ $event->before ?? 'Belum ada' }}<br>Sesudah: {{ $event->after }}</p>
            </section>
        @empty
            <p>Belum ada riwayat saldo.</p>
        @endforelse
        <h2>Perubahan data pegawai</h2>
        @forelse ($employeeHistory as $event)
            <section class="card employee">
                <strong>{{ $event->actor_name }}</strong> &middot; {{ $event->created_at }}
                <p>Sebelum: {{ $event->before ?? 'Belum ada' }}<br>Sesudah: {{ $event->after }}</p>
            </section>
        @empty
            <p>Belum ada riwayat perubahan data.</p>
        @endforelse
    </main>
</body>
</html>
