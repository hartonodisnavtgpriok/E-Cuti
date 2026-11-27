<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Formulir Cuti - DISNAV</title><link rel="stylesheet" href="{{ asset('dashboard.css') }}">
</head>
<body class="leave-page">
    <header class="topbar">@include('partials.topbar-brand', ['pageLabel' => 'Formulir Cuti'])<a href="{{ route($adminEdit ? 'leave.manage' : 'leave.index') }}">Kembali</a></header>
    <main class="container leave-shell">
        <div class="leave-heading">
            <div>
                <p class="leave-eyebrow">FORMULIR CUTI</p>
                <h1>{{ $adminEdit ? 'Ubah permohonan sebagai admin' : ($leave ? 'Revisi pengajuan' : 'Ajukan cuti') }}</h1>
            </div>
        </div>
        @if ($errors->any()) <div class="notice error" role="alert"><ul>@foreach ($errors->all() as $error) <li>{{ $error }}</li> @endforeach</ul></div> @endif
        <form class="card leave-form" method="post" enctype="multipart/form-data" action="{{ $adminEdit ? route('leave.admin-update', $leave->id) : ($leave ? route('leave.update', $leave->id) : route('leave.store')) }}">
            @csrf
            <fieldset>
                <legend>Jenis dan jadwal cuti</legend>
                <label for="leave_type">Jenis cuti</label>
                <select id="leave_type" name="leave_type" required>
                    <option value="">-- Pilih jenis cuti --</option>
                    @foreach ($types as $type) <option value="{{ $type }}" @selected(old('leave_type', $leave?->leave_type) === $type)>{{ $type }}</option> @endforeach
                </select>
                <div class="leave-form-row">
                    <div><label for="start_date">Tanggal mulai</label><input id="start_date" type="date" name="start_date" value="{{ old('start_date', $leave?->start_date) }}" required></div>
                    <div><label for="end_date">Tanggal akhir</label><input id="end_date" type="date" name="end_date" value="{{ old('end_date', $leave?->end_date) }}" required></div>
                </div>
                <p class="leave-hint">Hari kerja dihitung tanpa Sabtu, Minggu, dan tanggal libur yang telah dimasukkan admin.</p>
            </fieldset>
            <fieldset>
                <legend>Keterangan</legend>
                <label for="reason">Alasan cuti</label><textarea id="reason" name="reason" maxlength="2000" rows="3" required>{{ old('reason', $leave?->reason) }}</textarea>
                <label for="address">Alamat selama cuti</label><textarea id="address" name="address" maxlength="2000" rows="3" required>{{ old('address', $leave?->address) }}</textarea>
                <label for="phone">Nomor WhatsApp (wajib)</label><input id="phone" name="phone" maxlength="30" inputmode="tel" autocomplete="tel" placeholder="Contoh: 081234567890" value="{{ old('phone', $leave?->phone) }}" required>
                <p class="leave-hint">Nomor ini dipakai untuk notifikasi (push note) status pengajuan.</p>
            </fieldset>
            @unless ($adminEdit)
                <fieldset>
                    <legend>Lampiran (opsional)</legend>
                    <p class="leave-hint">Unggah surat dokter atau dokumen pendukung, atau foto langsung dengan kamera. Format JPG, PNG, atau PDF, maksimal 5 MB per berkas.</p>
                    <div class="upload-actions">
                        <label class="upload-button" for="attach_file">Unggah file</label>
                        <label class="upload-button" for="attach_camera">Ambil foto</label>
                    </div>
                    <input class="upload-input" id="attach_file" type="file" name="attachments[]" accept="image/jpeg,image/png,application/pdf" multiple>
                    <input class="upload-input" id="attach_camera" type="file" name="attachments[]" accept="image/*" capture="environment">
                    <ul class="upload-list" id="upload-list" aria-live="polite"></ul>
                </fieldset>
            @endunless
            @if ($adminEdit)
                <fieldset>
                    <legend>Catatan admin</legend>
                    <div class="notice">Persetujuan dan nomor surat tetap berlaku. Perubahan dan alasannya dicatat dalam riwayat.</div>
                    <label for="admin_note">Alasan perubahan</label><textarea id="admin_note" name="admin_note" maxlength="2000" rows="3" required>{{ old('admin_note') }}</textarea>
                </fieldset>
            @endif
            <button type="submit">{{ $adminEdit ? 'Simpan perubahan' : ($leave ? 'Kirim ulang' : 'Kirim pengajuan') }}</button>
        </form>
    </main>
    <script>
        const uploadList = document.getElementById('upload-list');
        const showFiles = () => {
            if (!uploadList) return;
            uploadList.replaceChildren(...[...document.querySelectorAll('.upload-input')].flatMap((input) => [...input.files]).map((file) => {
                const item = document.createElement('li');
                item.textContent = file.name + ' (' + Math.max(1, Math.round(file.size / 1024)) + ' KB)';
                return item;
            }));
        };
        document.querySelectorAll('.upload-input').forEach((input) => input.addEventListener('change', showFiles));
    </script>
</body>
</html>
