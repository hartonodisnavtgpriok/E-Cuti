<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 9px; color: #111; }
        h1 { font-size: 13px; text-align: center; margin: 14px 0 4px; }
        h2 { font-size: 10px; margin: 10px 0 4px; }
        table { border-collapse: collapse; width: 100%; }
        td, th { border: 1px solid #444; padding: 5px; vertical-align: top; }
        .right { text-align: right; }
        .center { text-align: center; }
        .sign { height: 45px; }
    </style>
</head>
<body>
    <p class="right">Kepada Yth. Kepala Distrik Navigasi Tipe B Tg. Priok<br>di Jakarta</p>
    <h1>FORMULIR PERMINTAAN DAN PEMBERIAN CUTI</h1>
    <p class="center">NOMOR: {{ $leave->letter_number }} &nbsp; TANGGAL: {{ $issuedAt->format('d-m-Y') }}</p>
    <h2>I. DATA PEGAWAI</h2>
    <table>
        <tr><td>Nama: {{ $leave->employee_name }}</td><td>NIP: {{ $leave->employee_nip }}</td></tr>
        <tr><td>Jabatan: {{ $leave->position_is_group ? 'Tidak tersedia' : ($leave->employee_position ?? 'Tidak tersedia') }}</td><td>Masa kerja: Tidak tersedia</td></tr>
        <tr><td colspan="2">Unit kerja: {{ $leave->employee_unit ?? 'Tidak tersedia' }}</td></tr>
    </table>
    <h2>II. JENIS CUTI YANG DIAMBIL</h2>
    <p>{{ $leave->leave_type }}</p>
    <h2>III. ALASAN CUTI</h2>
    <p>{{ $leave->reason }}</p>
    <h2>IV. LAMANYA CUTI</h2>
    <p>{{ $leave->working_days }} hari kerja, {{ $leave->start_date }} s.d. {{ $leave->end_date }}</p>
    <h2>V. CATATAN CUTI TAHUNAN</h2>
    @if ($annual)
        <table>
            <tr><th>Tahun</th><th>Saldo awal</th><th>Terpakai</th><th>Sisa</th></tr>
            @foreach ($annual['remaining'] as $year => $remaining)
                <tr><td>{{ $year }}</td><td>{{ $annual['opening'][$year] }}</td><td>{{ $annual['used'][$year] }}</td><td>{{ $remaining }}</td></tr>
            @endforeach
        </table>
    @else
        <p>Data saldo tahunan tidak tersedia untuk periode ini.</p>
    @endif
    <h2>VI. ALAMAT SELAMA MENJALANKAN CUTI</h2>
    <p>{{ $leave->address }} &nbsp; TELP: {{ $leave->phone ?: 'Tidak tersedia' }}</p>
    <p class="right">Hormat saya,<br><br><br>{{ $leave->employee_name }}<br>NIP. {{ $leave->employee_nip }}</p>
    <h2>VII. PERSETUJUAN</h2>
    @if ($changesAfterApproval->isNotEmpty())
        <p>Permohonan diubah oleh admin setelah persetujuan; persetujuan dan nomor tetap berlaku.
        Alasan: {{ $changesAfterApproval->pluck('note')->join('; ') }}</p>
    @endif
    <table>
        <tr>
            @foreach ([1, 2] as $stage)
                <th>Tahap {{ $stage }}</th>
            @endforeach
        </tr>
        <tr>
            @foreach ([1, 2] as $stage)
                @php($decision = $decisions->get($stage))
                <td>
                    @if ($decision)
                        Disetujui<br>{{ $decision['position'] }}<br>
                        @if ($decision['simulated'] ?? false) Simulasi oleh admin sistem, bukan keputusan pejabat.<br> @endif
                        <div class="sign"></div>
                        {{ $decision['name'] }}<br>NIP. {{ $decision['nip'] }}
                    @else
                        Tidak diperlukan
                    @endif
                </td>
            @endforeach
        </tr>
    </table>
    <p>Keterangan: ruang kosong disediakan untuk tanda tangan basah.</p>
</body>
</html>
