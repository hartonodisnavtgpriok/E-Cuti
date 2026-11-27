# Dashboard Cuti DISNAV

Aplikasi Laravel ini masih dalam pengembangan. Database nyata lokal telah
disiapkan atas permintaan pengguna, tetapi **belum siap untuk penggunaan
operasional**: pemetaan, saldo, aturan instansi, dan format surat masih harus
diverifikasi oleh DISNAV. MySQL belum diuji di lingkungan proyek ini.

## Fitur

- Akun berdasarkan NIP, nominatif, jabatan, unit, dan peran admin/persuratan.
- Pratinjau dan impor Excel nominatif dengan header `NO`, `NAMA`, `NIP`,
  `JABATAN` pada baris 2 kolom E-H. NIP harus berupa teks Excel. Akun baru
  langsung aktif dengan kata sandi awal sama dengan NIP sesuai permintaan.
  Akun dengan NIP yang sudah ada tidak diubah saat impor ulang.
- Pegawai dapat mengganti kata sandi kapan saja dengan memasukkan kata sandi
  saat ini. Kata sandi baru minimal 12 karakter dan tidak boleh sama dengan
  NIP atau kata sandi sebelumnya. Sesi lain dibatalkan setelah perubahan.
  **NIP mudah diketahui dan bukan kata sandi aman**: minta setiap pegawai
  segera menggantinya. Pergantian kata sandi saat pertama masuk tidak diwajibkan.
- Baris tanpa NIP diperlakukan sebagai posisi kosong. Jika nama jabatannya
  tidak tersedia, baris ditampilkan di pratinjau tetapi tidak dibuat sebagai
  jabatan. Kelompok pegawai tanpa jabatan memakai kelompok unit, bukan
  mengarang jabatan resmi.
- Pemetaan awal mengikuti tabel alur yang disepakati dan tidak menimpa
  pemetaan admin. Pemetaan tetap harus diperiksa dan pejabat terkait harus
  diaktifkan. Cuti Kepala Distrik diproses di luar sistem.
- Pengajuan, dua tahap atau satu tahap persetujuan, penangguhan, revisi,
  penomoran oleh persuratan, rilis oleh admin, dan unduh PDF. Penyetuju
  mengikuti pejabat aktif yang sedang menempati jabatan; jika tidak tepat satu,
  keputusan atau pengajuan diblokir.
- Pengajuan pribadi dan persetujuan ditampilkan terpisah. Admin dan persuratan
  tetap memiliki daftar pengelolaan seluruh pengajuan sesuai hak aksesnya.
- Edit admin sebelum rilis mempertahankan persetujuan dan nomor yang ada,
  sesuai keputusan pengguna. Perubahan dan alasannya dicatat; PDF menandai
  perubahan setelah persetujuan. Surat yang telah dirilis tidak dapat diubah.
- Hari kerja mengecualikan Sabtu, Minggu, dan tanggal libur yang diinput admin.
  Jika kalender berubah setelah pengajuan, rilis ditahan sampai admin memeriksa
  dan menyimpan ulang permohonan.
- Saldo tahunan N-2, N-1, dan N memakai saldo awal yang ditetapkan admin,
  hak tahun baru 12 hari, dan sisa masing-masing tahun sebelumnya maksimal
  6 hari. Rilis mengurangi saldo tertua terlebih dahulu. Rilis terlambat
  menghitung ulang alokasi tahun berikutnya; rilis diblokir jika menyebabkan
  saldo untuk surat yang sudah dirilis tidak cukup. PDF yang sudah dirilis
  tetap merupakan dokumen pada saat penerbitan dan tidak diubah kembali.
- Riwayat perubahan pegawai dan saldo, serta ekspor nominatif dan riwayat
  cuti ke Excel. Saldo jenis cuti selain tahunan tidak dikurangi otomatis.
- Tautan media sosial oleh admin sistem dan petunjuk untuk membuka lingkungan
  uji terpisah.

Belum ada integrasi sistem kepegawaian eksternal karena nama sistem dan API
belum diketahui. Aturan tanggal kedaluwarsa khusus selain pergantian tahun
N-2/N-1/N belum diterapkan. PDF mengikuti struktur contoh, tetapi bukan tanda
tangan elektronik; ruang tanda tangan basah harus diperiksa dan ditandatangani
sesuai prosedur instansi.

## Database nyata lokal

Konfigurasi lokal menggunakan SQLite terpisah di `database\real.sqlite`,
bukan database mode uji. Server dibuka hanya pada `127.0.0.1:8000`; batasi
akses komputer dan file database karena berisi data pegawai. Siapkan cadangan
yang aman sebelum perubahan operasional. Data yang diimpor belum memiliki
saldo awal: admin harus menetapkan saldo dan memeriksa pemetaan penyetuju.

## Persiapan lokal dengan MySQL

Pasang PHP 8.3 atau lebih baru, ekstensi PHP yang dibutuhkan Laravel,
PhpSpreadsheet, Dompdf, `pdo_mysql`, `pdo_sqlite`, Composer, dan MySQL. Dalam
PowerShell di folder proyek:

```powershell
composer install
Copy-Item .env.example .env
```

Isi konfigurasi `DB_*` di `.env` dengan database MySQL kosong yang telah
dibuat, lalu:

```powershell
php artisan key:generate
php artisan migrate
php artisan cuti:create-admin
php -S 127.0.0.1:8000 -t public
```

Perintah pembuatan admin meminta NIP dan kata sandi secara interaktif; jangan
menulis kata sandi di kode atau membagikan `.env`. Setelah masuk, impor Excel
melalui pratinjau, periksa pemetaan dan akun yang aktif, dan
tetapkan saldo awal. N-2, N-1, dan N pada saldo awal adalah **sisa pada tahun
awal pencatatan sebelum penggunaan di aplikasi**, bukan jumlah hak asli.
Penggunaan sebelum tahun awal pencatatan tidak dapat dirilis melalui sistem.

Admin sistem yang bukan penyetuju hanya dapat melihat pratinjau keputusan;
keputusan tetap harus diberikan oleh pejabat yang berwenang.

## Pengujian

```powershell
php artisan test
```

Pengujian otomatis menggunakan SQLite sementara, bukan MySQL. Dokumen contoh
dan file Excel asli yang diberikan pengguna tidak disalin ke repositori.
