<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $page === 'persetujuan' ? 'Persetujuan Cuti' : ($page === 'kelola' ? 'Pengelolaan Cuti' : 'Pengajuan Cuti Saya') }} - DISNAV</title><link rel="stylesheet" href="{{ asset('dashboard.css') }}">
</head>
<body class="leave-page">
    <header class="topbar">@include('partials.topbar-brand', ['pageLabel' => $page === 'persetujuan' ? 'Persetujuan Cuti' : ($page === 'kelola' ? 'Pengelolaan Cuti' : 'Pengajuan Cuti Saya')])<a href="{{ route('dashboard') }}">Dashboard</a></header>
    <main class="container leave-shell">
        @if (session('success')) <div class="notice" role="status">{{ session('success') }}</div> @endif
        <div class="leave-heading">
            <div>
                <p class="leave-eyebrow">{{ $page === 'persetujuan' ? 'PERSETUJUAN' : ($page === 'kelola' ? 'PENGELOLAAN' : 'PENGAJUAN') }}</p>
                <h1>{{ $page === 'persetujuan' ? 'Persetujuan cuti' : ($page === 'kelola' ? 'Pengelolaan pengajuan cuti' : 'Pengajuan cuti saya') }}</h1>
            </div>
            <div class="leave-heading-actions">
                @if ($page === 'pengajuan')
                    <a class="button-link" href="{{ route('leave.create') }}">+ Ajukan cuti</a>
                @endif
                @if ($page === 'kelola' && (auth()->user()->is_admin || auth()->user()->is_system_admin))
                    <a class="button-link button-outline" href="{{ route('admin.leave-export') }}">Ekspor riwayat cuti ke Excel</a>
                @endif
            </div>
        </div>
        <div class="leave-list">
        @forelse ($leaves as $leave)
            @php($tone = match ($leave->status) {
                'released' => 'ok', 'revision' => 'warn', 'deferred_1', 'deferred_2' => 'warn',
                default => 'info',
            })
            <article class="card employee leave-item">
                <div class="leave-item-main">
                    <h3><a href="{{ route('leave.show', ['id' => $leave->id, 'from' => $page]) }}">{{ $leave->leave_type }} - {{ $leave->employee_name }}</a></h3>
                    <p>{{ $leave->start_date }} s.d. {{ $leave->end_date }} &middot; {{ $leave->working_days }} hari kerja</p>
                </div>
                <span class="status status-{{ $tone }}">{{ \App\Domain\Leave\LeaveStatus::label($leave->status) }}</span>
            </article>
        @empty
            <div class="empty">{{ $page === 'persetujuan' ? 'Belum ada cuti yang menunggu persetujuan Anda.' : 'Belum ada pengajuan yang dapat Anda lihat.' }}</div>
        @endforelse
        </div>
    </main>
</body>
</html>
