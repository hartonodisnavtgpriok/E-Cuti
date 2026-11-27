<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Detail Pengajuan - DISNAV</title><link rel="stylesheet" href="{{ asset('dashboard.css') }}">
</head>
<body>
    @php($backRoute = match (request()->query('from')) {
        'persetujuan' => 'leave.approvals',
        'kelola' => 'leave.manage',
        'pengajuan' => 'leave.index',
        default => $leave->user_id === auth()->id() ? 'leave.index' : ((auth()->user()->is_admin || auth()->user()->is_system_admin || auth()->user()->is_correspondence) ? 'leave.manage' : 'leave.approvals'),
    })
    <header class="topbar">@include('partials.topbar-brand', ['pageLabel' => 'Detail Pengajuan'])<a href="{{ route($backRoute) }}">Kembali</a></header>
    <main class="container">
        @if (session('success')) <div class="notice" role="status">{{ session('success') }}</div> @endif
        @if ($errors->any()) <div class="notice error" role="alert"><ul>@foreach ($errors->all() as $error) <li>{{ $error }}</li> @endforeach</ul></div> @endif
        <h1>{{ $leave->leave_type }}</h1>
        <section class="card">
            <dl>
                <div><dt>Pemohon</dt><dd>{{ $leave->employee_name }}</dd></div>
                <div><dt>Periode</dt><dd>{{ $leave->start_date }} s.d. {{ $leave->end_date }}</dd></div>
                <div><dt>Hari kerja</dt><dd>{{ $leave->working_days }}</dd></div>
                <div><dt>Alasan</dt><dd>{{ $leave->reason }}</dd></div>
                <div><dt>Alamat</dt><dd>{{ $leave->address }}</dd></div>
                <div><dt>No. WhatsApp</dt><dd>{{ $leave->phone ?: 'Tidak tersedia' }}</dd></div>
                @if ($attachments->isNotEmpty())
                    <div><dt>Lampiran</dt><dd>@foreach ($attachments as $file)<a href="{{ route('leave.attachment', [$leave->id, $file->id]) }}" target="_blank" rel="noopener">{{ $file->original_name }}</a>@if (! $loop->last), @endif @endforeach</dd></div>
                @endif
                <div><dt>Status</dt><dd>{{ \App\Domain\Leave\LeaveStatus::label($leave->status) }}</dd></div>
                <div><dt>Nomor surat</dt><dd>{{ $leave->letter_number ?? 'Belum diberikan' }}</dd></div>
            </dl>
        </section>
        @if ($leave->user_id === auth()->id() && $leave->status === 'revision')
            <p><a class="button-link" href="{{ route('leave.edit', $leave->id) }}">Revisi dan kirim ulang</a></p>
        @endif
        @if ($leave->status !== 'released' && (auth()->user()->is_admin || auth()->user()->is_system_admin))
            <p><a href="{{ route('leave.admin-edit', $leave->id) }}">Ubah permohonan sebagai admin</a></p>
        @endif
        @if (auth()->user()->is_correspondence && $leave->status === 'numbering')
            <section class="card">
                <h2>Nomor surat</h2>
                <form method="post" action="{{ route('leave.number', $leave->id) }}">
                    @csrf
                    <label for="letter_number">Nomor surat</label>
                    <input id="letter_number" name="letter_number" maxlength="255" value="{{ old('letter_number') }}" required>
                    <button type="submit">Simpan nomor</button>
                </form>
            </section>
        @endif
        @if ($leave->status === 'ready' && (auth()->user()->is_admin || auth()->user()->is_system_admin))
            <section class="card">
                <h2>Rilis surat</h2>
                <p>Rilis akan menyimpan PDF dan mengurangi saldo jika jenis cutinya adalah cuti tahunan.</p>
                <form method="post" action="{{ route('leave.release', $leave->id) }}">
                    @csrf <button type="submit">Rilis surat</button>
                </form>
            </section>
        @endif
        @if ($leave->status === 'released')
            <p><a class="button-link" href="{{ route('leave.download', $leave->id) }}">Unduh surat PDF</a></p>
        @endif
        @if ($canDecide || $canPreviewDecision)
            <section class="card">
                <h2>{{ $canPreviewDecision ? 'Pratinjau keputusan penyetuju' : 'Keputusan penyetuju' }}</h2>
                @if ($canPreviewDecision) <p>Hanya pratinjau. Keputusan nyata harus diberikan oleh pejabat penyetuju yang berwenang.</p> @endif
                <form method="post" action="{{ route('leave.decide', $leave->id) }}">
                    @csrf
                    <fieldset @disabled($canPreviewDecision)>
                    <label for="decision">Keputusan</label>
                    <select id="decision" name="decision" required>
                        <option value="disetujui">Disetujui</option>
                        <option value="perubahan">Perubahan</option>
                        <option value="ditangguhkan">Ditangguhkan</option>
                        <option value="tidak_disetujui">Tidak disetujui</option>
                    </select>
                    <label for="note">Catatan (wajib kecuali disetujui)</label><textarea id="note" name="note" maxlength="2000">{{ old('note') }}</textarea>
                    <button type="submit">Simpan keputusan</button>
                    </fieldset>
                </form>
            </section>
        @endif
        <h2>Riwayat</h2>
        @foreach ($events as $event)
            <div class="card employee"><strong>{{ str_replace('_', ' ', $event->action) }}</strong> &mdash; {{ $event->actor }} <small>{{ $event->created_at }}</small>
                @if ($event->note) <p>{{ $event->note }}</p> @endif
                @if (in_array($event->action, ['disetujui', 'perubahan', 'ditangguhkan', 'tidak_disetujui'], true) && $event->snapshot && (json_decode($event->snapshot, true, flags: JSON_THROW_ON_ERROR)['simulated'] ?? false))
                    <p>Keputusan simulasi oleh admin sistem.</p>
                @endif
                @if (in_array($event->action, ['diubah_admin', 'saldo_dihitung_ulang'], true) && $event->snapshot)
                    @php($changes = json_decode($event->snapshot, true, flags: JSON_THROW_ON_ERROR))
                    @php($labels = ['leave_type' => 'Jenis cuti', 'start_date' => 'Tanggal mulai', 'end_date' => 'Tanggal selesai', 'reason' => 'Alasan', 'address' => 'Alamat', 'phone' => 'Telepon', 'working_days' => 'Hari kerja'])
                    <details>
                        <summary>Lihat data sebelum dan sesudah</summary>
                        @foreach (['before' => 'Sebelum', 'after' => 'Sesudah'] as $side => $heading)
                            <h3>{{ $heading }}</h3>
                            @if (count($changes[$side]) === 0)
                                <p>Tidak ada alokasi.</p>
                            @else
                                <dl>
                                    @foreach ($changes[$side] as $key => $value)
                                        <div><dt>{{ $event->action === 'saldo_dihitung_ulang' ? 'Tahun '.$key : ($labels[$key] ?? $key) }}</dt><dd>{{ $value ?? 'Tidak tersedia' }}</dd></div>
                                    @endforeach
                                </dl>
                            @endif
                        @endforeach
                    </details>
                @endif
            </div>
        @endforeach
    </main>
</body>
</html>
