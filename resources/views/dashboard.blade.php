<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Elektronik Cuti Distrik Navigasi Tipe B Tanjung Priok</title>
    <link rel="stylesheet" href="{{ asset('dashboard.css') }}">
</head>
<body class="dashboard-page">
    <header class="topbar">
        @include('partials.topbar-brand', ['pageLabel' => null])
        <form method="post" action="{{ route('logout') }}">@csrf <button class="secondary" type="submit">Keluar</button></form>
    </header>
    <main class="container dashboard-shell">
        <section class="dashboard-welcome" aria-labelledby="dashboard-greeting">
            <h1 id="dashboard-greeting">Halo, {{ auth()->user()->name }}</h1>
            <p class="eyebrow">DASHBOARD PEGAWAI</p>
            <p>Kelola pengajuan dan persetujuan serta lihat informasi hak cuti Anda.</p>
        </section>

        <nav class="dashboard-actions" aria-label="Menu cuti">
            <a class="dashboard-action dashboard-action-subtle" href="{{ route('password.edit') }}">
                <span>Ubah kata sandi</span><span aria-hidden="true">&rarr;</span>
            </a>
            <a class="dashboard-action dashboard-action-primary" href="{{ route('leave.index') }}">
                <span>Pengajuan cuti saya</span><span aria-hidden="true">&rarr;</span>
            </a>
            @if ($canApprove)
                <a class="dashboard-action dashboard-action-primary" href="{{ route('leave.approvals') }}">
                    <span>Persetujuan cuti</span><span aria-hidden="true">&rarr;</span>
                </a>
            @endif
            @if (auth()->user()->is_admin || auth()->user()->is_system_admin || auth()->user()->is_correspondence)
                <a class="dashboard-action dashboard-action-subtle" href="{{ route('leave.manage') }}">
                    <span>Pengelolaan pengajuan cuti</span><span aria-hidden="true">&rarr;</span>
                </a>
            @endif
            @if (auth()->user()->is_admin || auth()->user()->is_system_admin)
                <a class="dashboard-action dashboard-action-subtle" href="{{ route('admin.index') }}">
                    <span>Pengelolaan admin</span><span aria-hidden="true">&rarr;</span>
                </a>
            @endif
        </nav>
        <section class="dashboard-balances" aria-labelledby="balance-heading">
            <h2 id="balance-heading">Hak dan sisa cuti</h2>
            <h3>Cuti Tahunan &mdash; {{ now()->year }}</h3>
            @if ($annualError)
                <div class="notice error" role="alert">{{ $annualError }}</div>
            @elseif (! $annual)
                <div class="empty">Saldo awal cuti tahunan belum tersedia. Hubungi admin.</div>
            @else
                <div class="grid">
                    @foreach ($annual['remaining'] as $year => $remaining)
                        <article class="card">
                            <div class="card-heading"><h3>{{ $year === now()->year ? 'N' : ($year === now()->year - 1 ? 'N-1' : 'N-2') }}</h3><span>{{ $year }}</span></div>
                            <p class="label">Sisa cuti</p>
                            <p class="amount">{{ $remaining }} <small>hari</small></p>
                            <dl>
                                <div><dt>Saldo awal periode</dt><dd>{{ $annual['opening'][$year] }} hari</dd></div>
                                <div><dt>Terpakai pada periode</dt><dd>{{ $annual['used'][$year] }} hari</dd></div>
                            </dl>
                        </article>
                    @endforeach
                </div>
            @endif
            <h3>Jenis cuti lainnya</h3>
            <div class="grid">
                @foreach ($otherTypes as $type)
                    @forelse ($balances->where('leave_type', $type) as $balance)
                        <article class="card">
                            <div class="card-heading"><h3>{{ $balance->leave_type }}</h3><span>{{ $balance->year }}</span></div>
                            <p class="label">Sisa cuti</p>
                            @if ($balance->initial_days !== null && $balance->used_days !== null && $balance->used_days <= $balance->initial_days)
                                <p class="amount">{{ $balance->initial_days - $balance->used_days }} <small>hari</small></p>
                            @elseif ($balance->initial_days !== null && $balance->used_days !== null)
                                <p class="amount unavailable">Data saldo tidak valid. Hubungi admin.</p>
                            @else
                                <p class="amount unavailable">Tidak tersedia</p>
                            @endif
                            <dl>
                                <div><dt>Hak awal</dt><dd>{{ $balance->initial_days === null ? 'Tidak tersedia' : $balance->initial_days.' hari' }}</dd></div>
                                <div><dt>Terpakai</dt><dd>{{ $balance->used_days === null ? 'Tidak tersedia' : $balance->used_days.' hari' }}</dd></div>
                                <div><dt>Berlaku hingga</dt><dd>{{ $balance->expires_on ?? 'Tidak tersedia' }}</dd></div>
                            </dl>
                        </article>
                    @empty
                        <article class="card"><h3>{{ $type }}</h3><p>Data hak cuti tidak tersedia.</p></article>
                    @endforelse
                @endforeach
            </div>
        </section>
        <section class="dashboard-rules" aria-labelledby="rules-heading">
            <h2 id="rules-heading">Aturan cuti</h2>
            <div class="card rules-card">
                <h3>Dasar hukum</h3>
                <p>Aturan mengenai cuti PNS merujuk secara resmi pada Peraturan Pemerintah (PP) Nomor 11 Tahun 2017 tentang Manajemen PNS (sebagaimana telah diubah dengan PP Nomor 17 Tahun 2020) serta secara teknis dijabarkan melalui Peraturan BKN Nomor 24 Tahun 2017 yang diperbarui dalam Peraturan BKN Nomor 7 Tahun 2021.</p>
                <p>Berdasarkan dasar hukum tersebut, jenis cuti bagi PNS dibagi menjadi 7 kategori utama:</p>
                <details>
                    <summary>1. Cuti Tahunan</summary>
                    <ul>
                        <li><strong>Syarat:</strong> Diberikan kepada PNS atau CPNS yang telah bekerja minimal 1 (satu) tahun secara terus-menerus.</li>
                        <li><strong>Durasi:</strong> Hak cuti tahunan adalah 12 hari kerja.</li>
                        <li><strong>Catatan:</strong> Jika tidak diambil pada tahun berjalan, sisa cuti dapat diakumulasikan ke tahun berikutnya untuk paling lama 18 hari kerja (termasuk cuti tahunan berjalan). Khusus untuk guru dan dosen yang mendapat liburan sesuai kalender akademik, hak cuti tahunan ini dikecualikan.</li>
                    </ul>
                </details>
                <details>
                    <summary>2. Cuti Besar</summary>
                    <ul>
                        <li><strong>Syarat:</strong> PNS yang telah mengabdi paling singkat 5 tahun secara terus-menerus.</li>
                        <li><strong>Durasi:</strong> Maksimal 3 bulan.</li>
                        <li><strong>Catatan:</strong> PNS yang menggunakan hak cuti besar tidak berhak lagi atas cuti tahunan di tahun yang berjalan. Biasanya digunakan untuk keperluan ibadah keagamaan (seperti Haji), melahirkan anak ke-4 dan seterusnya, atau kepentingan keluarga yang mendesak.</li>
                    </ul>
                </details>
                <details>
                    <summary>3. Cuti Sakit</summary>
                    <ul>
                        <li><strong>Syarat:</strong> PNS yang mengalami sakit dan tidak dapat menjalankan tugas.</li>
                        <li><strong>Durasi:</strong> Jika sakit 1 hingga 14 hari, cukup melampirkan surat keterangan dokter. Jika lebih dari 14 hari, harus mengajukan permintaan cuti sakit dengan melampirkan surat keterangan dokter pemerintah/tim penguji kesehatan. Hak cuti sakit diberikan paling lama 1 tahun dan dapat diperpanjang maksimal 6 bulan jika belum sembuh.</li>
                    </ul>
                </details>
                <details>
                    <summary>4. Cuti Melahirkan</summary>
                    <ul>
                        <li><strong>Syarat:</strong> Diberikan kepada PNS perempuan untuk persalinan anak pertama, kedua, dan ketiga.</li>
                        <li><strong>Durasi:</strong> Paling lama 3 bulan.</li>
                        <li><strong>Catatan:</strong> Untuk persalinan anak keempat dan seterusnya, PNS perempuan dapat mengajukan menggunakan hak Cuti Besar.</li>
                    </ul>
                </details>
                <details>
                    <summary>5. Cuti Karena Alasan Penting (CAP)</summary>
                    <ul>
                        <li><strong>Syarat:</strong> Diberikan jika ada kedaruratan pribadi, seperti:
                            <ul>
                                <li>Keluarga inti (ibu, bapak, istri/suami, anak, adik, kakak, mertua, atau menantu) sakit keras atau meninggal dunia.</li>
                                <li>PNS melangsungkan pernikahan yang pertama.</li>
                                <li>Mengalami musibah bencana alam.</li>
                            </ul>
                        </li>
                        <li><strong>Durasi:</strong> Paling lama 1 bulan.</li>
                    </ul>
                </details>
                <details>
                    <summary>6. Cuti Bersama</summary>
                    <ul>
                        <li><strong>Syarat:</strong> Ditetapkan secara resmi melalui Keputusan Presiden (Kepres).</li>
                        <li><strong>Catatan:</strong> Cuti bersama PNS tidak memotong hak cuti tahunan. Bagi PNS yang karena tugasnya tidak bisa mengambil cuti bersama (misalnya pelayanan medis atau keamanan), hak cuti tahunannya akan ditambah secara otomatis.</li>
                    </ul>
                </details>
                <details>
                    <summary>7. Cuti di Luar Tanggungan Negara (CLTN)</summary>
                    <ul>
                        <li><strong>Syarat:</strong> Hanya bisa diajukan oleh PNS yang telah bekerja minimal 3 tahun karena alasan pribadi yang sangat mendesak (misal: mendampingi suami/istri tugas belajar/kerja di luar negeri, program kehamilan, atau merawat orang tua yang uzur).</li>
                        <li><strong>Durasi:</strong> Paling lama 3 tahun dan dapat diperpanjang maksimal 1 tahun.</li>
                        <li><strong>Catatan:</strong> Selama menjalankan CLTN, PNS dilepaskan dari jabatannya dan tidak menerima penghasilan (gaji serta tunjangan) dari negara.</li>
                    </ul>
                </details>
            </div>
        </section>
        @if ($socialLinks->isNotEmpty())
            <section class="dashboard-social">
                <h2>Media sosial</h2>
                <div class="dashboard-social-links">@foreach ($socialLinks as $link) <a href="{{ $link->url }}" target="_blank" rel="noopener noreferrer">{{ $link->label }}</a> @endforeach</div>
            </section>
        @endif
    </main>
</body>
</html>
