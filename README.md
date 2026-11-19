# Sharefolder Web

Laravel 13 + Vue 3 + Inertia. File manager tersedia di `/dashboard`, sesudah login dan verifikasi email menggunakan autentikasi starter kit.

## Status

Mode lokal tersedia untuk pengembangan. Driver `windows` untuk IIS Windows Authentication + FastCGI impersonation sudah diimplementasikan, tetapi **belum diuji terhadap server AD/SMB nyata**. `.env` pengembangan tetap memakai mode lokal. Integrasi deployment dan batas pengujian dijelaskan di [DEPLOYMENT-AD.md](DEPLOYMENT-AD.md).

Mode lokal menyimpan file di `storage/app/share-demo`; semua akun lokal terverifikasi menggunakan folder demo yang sama. Jangan mengubah root demo menjadi share perusahaan. Mode Windows memerlukan HTTPS IIS, identitas domain yang valid, dan root UNC; tidak ada fallback akun layanan. Awali deployment dengan akses baca saja.

Fitur: daftar/navigasi folder tanpa reload, pencarian pada folder aktif, unduhan untuk membaca file, upload banyak file, drag/drop file dari komputer, pembuatan folder/file teks, copy/cut/paste antar-folder, multi-select dengan checkbox/Ctrl, menu klik kanan, shortcut Ctrl+A/C/X/V, dan Recycle Bin dengan pemulihan serta hapus permanen terpisah. Clipboard adalah clipboard aplikasi; bukan clipboard Windows Explorer. Upload folder dan preview Office belum tersedia. Preview PDF tersedia sampai 10 MB. Preview gambar PNG/JPEG/GIF/WebP (maksimal 5 MB dan 40 megapiksel) serta teks UTF-8 TXT/MD/CSV/JSON/LOG (maksimal 1 MB) tersedia melalui klik nama file. Format yang tidak didukung tetap bisa diunduh. Batas upload 20 file, 25 MB per file; sesuaikan juga `upload_max_filesize`, `post_max_size`, dan batas HTTP server. Tidak ada overwrite otomatis.

## Pengembangan

Gunakan environment lokal, database aplikasi yang sudah dikonfigurasi, lalu:

```sh
php artisan migrate
npm run dev
```

Buka aplikasi melalui Herd atau `php artisan serve`. Registrasi/login memakai akun aplikasi lokal, bukan akun AD. Jangan memasukkan password AD ke form login lokal ini. Akun harus memiliki email terverifikasi melalui alur starter kit. File demo dibuat saat endpoint pertama diakses; tidak ada data contoh yang mengaku berasal dari server perusahaan.

Pemeriksaan:

```sh
npm run types:check
npm run build
php vendor/bin/pest --compact
```

PHP CLI di mesin ini belum mengaktifkan SQLite. Pengujian dapat dijalankan tanpa mengubah php.ini:

```sh
php -d extension=pdo_sqlite -d extension=sqlite3 vendor/bin/pest --compact
```

## Integrasi AD per user

Konfigurasi dan langkah pengujian ada di [DEPLOYMENT-AD.md](DEPLOYMENT-AD.md). Contoh konfigurasi ada di `.env.example` dan `deployment/iis/web.config.example`. Script pemeriksaan server yang hanya membaca konfigurasi tersedia di `deployment/iis/Inspect-Server.ps1`.

Password AD tidak disimpan dalam aplikasi. IIS memverifikasi user dan FastCGI melakukan impersonation. Status login Laravel lokal tidak memberikan akses Windows. Domain NetBIOS yang dilaporkan adalah ASI001, domain DNS artabumisentraindustri.com, dan root share `\\192.168.3.10\SMB`.

## Batas operasional

Validasi menolak traversal, path absolut, alternate data stream, nama perangkat Windows, symbolic link dan benturan nama. Operasi web diserialisasi dengan cache lock. Ini bukan jaminan transaksi filesystem: kegagalan I/O di tengah batch/rekursi dapat menyisakan hasil sebagian. Lock tidak mengunci perubahan oleh proses eksternal. Mode demo tidak untuk direktori yang dapat dimodifikasi oleh proses tidak tepercaya. Gateway produksi memerlukan pengamanan reparse point, kontrol konkurensi, audit user, dan pengujian ACL di server yang sebenarnya.

Detail lingkungan yang sudah dikonfirmasi dan rencana pengujian server ada di [DEPLOYMENT-AD.md](DEPLOYMENT-AD.md).

## Fitur pengembangan lokal terbaru

- Ganti nama satu file/folder melalui toolbar, menu klik kanan, atau F2. Nama duplikat ditolak tanpa overwrite; nama sama persis menjadi no-op. Perubahan huruf besar/kecil saja pada Windows dapat ditolak sebagai konflik.
- Klik nama file untuk membuka preview. Teks ditampilkan sebagai teks biasa, bukan HTML aktif. Gambar diperiksa berdasarkan isi file; SVG/HTML tidak dijalankan sebagai preview. Tombol unduh tetap tersedia.
- Upload browser memakai antrean berurutan, maksimal 20 file dan 25 MB/file. Setiap file mempunyai progress transfer dan status penyimpanan sendiri. 100% transfer belum berarti tersimpan: tunggu Selesai. File yang gagal tidak menghentikan seluruh antrean. Setelah timeout/koneksi putus, muat ulang sebelum mencoba kembali karena server mungkin sudah menyimpan file.
- Koneksi aplikasi MySQL Laragon telah diperiksa dengan SELECT 1; data user tidak diubah. Test otomatis tetap memakai SQLite in-memory dan folder pengujian sementara agar tidak menyentuh database/file demo pengguna.

## Recycle Bin lokal

Tombol Hapus sekarang memindahkan item ke `.sharefolder-trash` di dalam root folder lokal yang aktif, dengan ID unik dan metadata lokasi asal/waktu/user. Folder ini disembunyikan dari listing aplikasi dan ditolak dari endpoint file biasa. Penyimpanan pada volume yang sama memungkinkan rename tanpa menyalin isi file. Jangan mengubah atau menghapus folder internal ini melalui Explorer; sertakan folder tersebut dalam backup jika data pemulihan perlu disimpan.

Buka tombol Recycle Bin di toolbar. Setiap akun hanya melihat, memulihkan, atau menghapus permanen item yang dihapusnya sendiri. Metadata pemulihan disimpan bersama payload pada filesystem, tidak memerlukan migration MySQL. Ini merupakan pembatasan di aplikasi, bukan pengganti ACL Windows untuk user yang bisa mengakses disk secara langsung.

Restore tidak menimpa nama yang sudah ada. Jika folder induk hilang, pulihkan/buat folder induk terlebih dahulu. Tidak ada auto-cleanup atau retensi otomatis. Penghapusan permanen memerlukan konfirmasi terpisah. Item yang sudah terhapus permanen sebelum fitur ini dibuat tidak bisa dipulihkan.

Fitur ini hanya diaktifkan untuk driver lokal; operasi delete pada driver Windows ditolak sampai desain recycle bin yang menghormati ACL AD selesai. Data pengguna yang sudah ada tidak dipindahkan oleh proses instalasi fitur. Test menggunakan folder sementara, bukan folder uji pengguna.

Metadata ditulis sebelum pemindahan sehingga kegagalan penyimpanan tidak sengaja menghapus file asal. Gangguan proses dapat meninggalkan direktori metadata tanpa payload; direktori tersebut tidak ditampilkan sebagai item yang dapat dipulihkan. Kegagalan batch/IO dapat menghasilkan sebagian operasi yang berhasil; muat ulang daftar untuk memastikan kondisi terakhir.

## Riwayat aktivitas

Tombol Aktivitas saya menampilkan riwayat akun saat ini untuk root penyimpanan saat ini, dengan filter tindakan dan pagination 20 item/halaman. Tabel `file_activities` menyimpan identitas user, waktu, tindakan, path relatif/sumber/tujuan, status HTTP dan hasil request. Tidak ada endpoint hapus/edit riwayat. Administrator database tetap dapat mengubah data sehingga ini bukan audit log tahan manipulasi.

Catatan dimulai sejak migration fitur diterapkan. Password, isi file, token, absolute root dan dump request tidak dicatat. Identitas dan root diambil dari server; parameter user tidak dapat memilih riwayat user lain. Registrasi/login tidak termasuk cakupan riwayat file ini.

Satu baris mewakili satu request, sehingga batch gagal bisa sudah memproses sebagian file. Status sukses berarti handler selesai; unduhan menggunakan status prepared karena pengiriman ke browser belum tentu selesai. Status processing berarti hasil akhir belum tercatat (misalnya proses berhenti atau database terputus). Bila pencatatan awal gagal, operasi tidak dijalankan; bila pencatatan akhir gagal, hasil operasi tidak diubah dan baris awal tetap tersedia untuk pemeriksaan. Pengaturan retensi/pembersihan otomatis belum dibuat.

Untuk deployment baru jalankan migration biasa, termasuk `2026_11_19_000000_create_file_activities_table.php`. Migration ini sudah diterapkan pada MySQL lokal project tanpa mengubah tabel atau data lama.

## Preview PDF

Klik nama file PDF untuk membaca di dialog preview, berpindah halaman, dan zoom 50–200%. Batas file 10 MB; PDF berpassword/rusak menampilkan pesan dan tombol unduh. Preview menggunakan canvas PDF.js, tidak mengaktifkan scripting PDF, XFA, lampiran, link, atau formulir interaktif. Ini preview visual; unduh dokumen untuk pencarian/salin teks dan akses pembaca layar. Pemeriksaan header server adalah pemeriksaan awal, bukan validasi struktur penuh; parser PDF.js menangani struktur dokumen.

PDF.js dan worker dibundel secara lokal. `npm run dev`, `npm run build`, dan `npm run build:ssr` menyiapkan font/CMap/decoder di `public/pdfjs` melalui `scripts/prepare-pdf-assets.mjs`. Sertakan direktori hasil ini bersama `public/build` ketika deployment; file pengguna tidak dikirim ke CDN atau layanan preview. Library dimuat saat preview PDF dibuka. PHP masih membaca file di bawah autentikasi dan pembatasan root yang sama.

Jalankan `node scripts/check-pdf-preview.mjs` untuk smoke test renderer dengan PDF dua halaman sintetis dalam memory (tidak membaca dokumen pengguna). Pengujian ini memeriksa parser dan rasterisasi pada 100%/200%; belum menggantikan pengujian browser pada dokumen nyata.

## Pendaftaran staf oleh IT

Menu **Pendaftaran staf** (`/admin/staff`) hanya tersedia untuk akun lokal dengan `is_it_admin = true` yang sudah memverifikasi email. Middleware memeriksa setiap GET/POST; menyembunyikan menu saja tidak menjadi mekanisme pengamanan. Pendaftaran publik `/register` dinonaktifkan. Akun baru selalu pengguna biasa, password di-hash, dan sesi admin tetap aktif. Form tidak dapat memberi role admin atau menandai email terverifikasi.

Migration `2026_11_19_010000_add_it_admin_to_users_table.php` menambahkan flag dengan default false; tidak otomatis memberi hak admin kepada akun lama. Bootstrap eksplisit: `php artisan staff:admin email@perusahaan.com` untuk akun yang sudah ada. Pencabutan: tambahkan `--revoke`. Jangan memberi akses shell/command ini kepada staf biasa. Akun `it@artabumisentraindustri.com` telah ditetapkan sebagai admin IT pada database lokal atas instruksi pengguna.

Pendaftaran memicu verifikasi email Laravel. Dalam konfigurasi lokal `MAIL_MAILER=log`, pesan dicatat ke log, bukan dikirim ke inbox; atur SMTP sebelum onboarding nyata. Jika proses email gagal, akun tetap ada dan halaman memberi pesan agar tidak mendaftar ulang. Staf dapat masuk dan meminta pengiriman ulang verifikasi. Fitur ini tidak membuat akun AD atau memberi permission SMB/NTFS; pada driver Windows endpoint admin lokal tetap tidak tersedia.

## Bahasa antarmuka (konfigurasi)

Pemilih bahasa di login dan header dashboard mendukung Indonesia (default), English, dan Mandarin sederhana (`zh-CN`). Pilihan disimpan per browser melalui localStorage dan cookie `app_locale` selama satu tahun; tidak membutuhkan migration MySQL. Cookie hanya berisi kode bahasa dan divalidasi dengan daftar yang diizinkan. PHP menggunakannya untuk pesan operasi, validasi, dan autentikasi. Nama file/folder, isi preview, dan identitas user tidak diterjemahkan.

Terjemahan UI berada di `resources/js/i18n/messages.json`, dengan helper reaktif, interpolasi parameter, dan format Intl di `resources/js/i18n/index.ts`. Pesan server menggunakan katalog `lang/*.json` dan berkas validasi/auth di direktori locale. Saat menambah pesan operasi, perbarui katalog frontend dan backend bersama-sama. Ruang lingkup awal mencakup login, pendaftaran/pemulihan akun, dashboard file, pencarian, preview, upload, Recycle Bin dan riwayat. Halaman pengaturan akun lanjutan bawaan starter kit belum seluruhnya diterjemahkan.

Jalankan `node scripts/check-locales.mjs` untuk memeriksa kelengkapan katalog, parameter, penyimpanan pilihan, fallback, dan format angka. `tests/Feature/LocaleTest.php` memeriksa pesan operasi/validasi/login serta memastikan nama dan isi file tetap asli.
