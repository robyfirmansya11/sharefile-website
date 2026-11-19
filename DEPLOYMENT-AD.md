# IIS + Active Directory + SMB

## Status implementasi

Driver `windows` telah ditambahkan. IIS memverifikasi user dan PHP FastCGI impersonation menjalankan operasi filesystem dengan token user tersebut. Laravel tidak menerima password AD, tidak membuat akun lokal dari email domain, dan tidak menjalankan `net use` atau memakai password akun layanan.

**Belum terpasang / teruji di Windows Server yang sebenarnya.** Unit/feature test memakai variabel IIS simulasi untuk menguji kontrol aplikasi, bukan membuktikan token Windows atau ACL SMB. `.env` lokal tetap tidak diubah. Aktifkan produksi hanya setelah uji penerimaan di bawah lulus. Tahap pertama `SMB_WRITES_ENABLED=false` untuk uji list/download.

## Lingkungan

Update 18 November 2026: pengujian aplikasi dilakukan lebih dulu di laptop IT-ASI. Laptop terdeteksi WORKGROUP (PartOfDomain=False), tanpa layanan IIS W3SVC. Halaman `http://127.0.0.1:8000/login` merespons HTTP 200. Mode lokal tetap digunakan; ini belum menguji AD/SMB per user.

Hostname website yang dipilih: `sharefolder.bumimorowaliutama.com`. Ini adalah hostname web, bukan perubahan domain AD. DNS/binding/sertifikat belum dibuat atau diubah. Selama pengembangan laptop, gunakan URL localhost yang sudah berjalan. Untuk uji AD dari web server laptop ke ASI01, topologi berubah menjadi dua host sehingga memerlukan IIS/HTTPS, integrasi domain, serta pengujian delegasi Kerberos ke CIFS; konfigurasi deployment satu server tidak cukup.

Hasil pemeriksaan server dari user: Web-Server, Web-Windows-Auth, Web-CGI Installed; handler PHP site-site yang ada menggunakan `C:\PHP83\php-cgi.exe`, PHP 8.3.32, `C:\PHP83\php.ini`, impersonation Off. Jangan mengubah konfigurasi PHP bersama tanpa memeriksa dampak pada site lain. Sertifikat yang dilaporkan hanya untuk ASI01.artabumisentraindustri.com dan berakhir 28 Januari 2026; perlu sertifikat valid yang mencakup hostname website baru sebelum uji HTTPS AD.

| Komponen | Nilai |
| --- | --- |
| Server web dan file | ASI01, 192.168.3.10 |
| Domain DNS | artabumisentraindustri.com |
| Domain NetBIOS | ASI001 (berdasarkan output nbtstat user; konfirmasi lewat Get-ADDomain) |
| Share | `\\192.168.3.10\SMB` |
| Folder fisik | `D:\SMB` |
| Login | `ASI001\namauser` atau UPN domain yang dikonfigurasi |

Share tetap diakses melalui UNC supaya Share ACL dan NTFS sama-sama berlaku. `D:\SMB` tidak diterima sebagai root oleh driver Windows. Department saat ini dapat berupa subfolder di share ini; root share department terpisah belum didukung.

## 1. Periksa server terlebih dahulu

Jalankan `deployment/iis/Inspect-Server.ps1` pada ASI01 jika membutuhkan pemeriksaan ulang. Script hanya membaca nama/domain server, status role IIS, konfigurasi share dan binding IIS. Script tidak menginstal role atau mengubah ACL/AD. Komponen IIS utama telah dikonfirmasi terpasang.

Output nbtstat yang diberikan menampilkan registrasi domain controller dan interface publik. Pastikan site ini hanya bisa diakses melalui jaringan internal/VPN dan binding/firewall yang sesuai; jangan membuat binding publik. Evaluasi pemisahan web dari domain controller bersama admin sebelum produksi; bila dipisah, konfigurasi delegasi Windows harus dirancang ulang dan diuji, bukan memakai fallback akun layanan.

## 2. Siapkan IIS

- Gunakan site HTTPS dengan hostname internal dan sertifikat valid. Tentukan hostname bersama admin DNS. Akses HTTP ditolak aplikasi pada mode Windows. Jangan mematikan validasi sertifikat/loopback untuk mengatasi kegagalan login.
- Document root menunjuk ke direktori `public` project. Jangan menunjuk ke root project atau `D:\SMB`.
- Siapkan IIS Windows Authentication, CGI/FastCGI, URL Rewrite, dan PHP NTS yang memenuhi composer.lock. Register handler `*.php` ke lokasi `php-cgi.exe` yang benar. Jangan membuka listener FastCGI ke jaringan atau memetakan header HTTP ke LOGON_USER/AUTH_USER.
- Windows Authentication ON; Anonymous dan Basic Authentication OFF untuk seluruh site termasuk endpoint file. Jangan memasang kredensial user tetap pada opsi Connect As virtual directory. Token harus berasal dari user IIS, bukan identity application pool.
- `deployment/iis/web.config.example` adalah template untuk `public/web.config` pada deployment. Periksa dan gabungkan dengan konfigurasi IIS yang sudah ada. Jika bagian authentication terkunci (500.19), atur pada level site melalui IIS Manager; jangan membuka semua section secara global.
- Application pool tidak boleh memakai Administrator, Domain Admin, atau LocalSystem dan tidak boleh menjadi fallback untuk akses data. Browser yang mendukung Integrated Windows Authentication dapat SSO; selain itu IIS dapat meminta kredensial Windows melalui dialog browser. Form login email lokal tidak digunakan dalam mode Windows.

Di **php.ini yang digunakan handler IIS**, bukan hanya PHP CLI:

```ini
fastcgi.impersonate = 1
upload_max_filesize = 25M
post_max_size = 512M
max_file_uploads = 20
```

Recycle pool setelah konfigurasi dilakukan. Periksa mode PHP lewat administrasi server; jangan meninggalkan phpinfo publik. Aplikasi mensyaratkan Windows + `cgi-fcgi`, IIS, `fastcgi.impersonate`, HTTPS, AUTH_TYPE Windows, dan LOGON_USER/AUTH_USER yang cocok. HTTP headers/query/body dengan nama serupa tidak dipercaya.

## 3. Konfigurasi deployment

Gunakan `.env` terpisah di server (jangan menyalin key/password melalui chat):

```dotenv
APP_ENV=production
APP_DEBUG=false
# APP_URL harus URL HTTPS internal yang benar.
SHARE_DRIVER=windows
AD_DOMAIN=artabumisentraindustri.com
AD_NETBIOS_DOMAIN=ASI001
SMB_UNC_ROOT='\\192.168.3.10\SMB'
SMB_WRITES_ENABLED=false
SESSION_SECURE_COOKIE=true
SESSION_DRIVER=database
CACHE_STORE=database
```

Konfigurasikan database aplikasi dengan akun terbatas. Database dipakai session/cache/lock, bukan untuk password domain atau sumber permission. Jalankan migration dan build dalam deployment biasa, lalu `php artisan config:clear` (atau rebuild cache konfigurasi). Jangan menjalankan perintah migrasi destruktif pada database yang sudah berisi data.

FastCGI impersonation juga memengaruhi akses filesystem Laravel. Source/config/vendor hanya Read & Execute sesuai kebutuhan; jangan memberi user Modify pada source, `.env`, atau file executable. Precompile Blade/views dan konfigurasi saat deploy (`php artisan view:cache`, `php artisan config:cache`) dan sediakan strategi runtime storage/log/upload temporary yang tidak memungkinkan user mengganti kode/cache executable atau membaca file sementara user lain. Jika isolasi ini tidak dapat dipenuhi, jangan memaksa deployment dengan Full Control: gunakan gateway Windows terpisah yang membatasi impersonation hanya pada operasi file.

UNC loopback pada server yang sama tetap perlu diuji. Bila autentikasi memakai hostname, admin dapat mengubah SMB_UNC_ROOT ke nama server/share yang benar. Jangan mengubah ke path fisik untuk melewati Share ACL.

## 4. Uji penerimaan pada folder uji terisolasi

1. Gunakan akun domain biasa dengan izin baca, akun read/write, dan akun tanpa akses. Jangan memakai Domain Admin sebagai satu-satunya pengujian.
2. Akses HTTPS `/dashboard`. Identitas pada menu user harus sesuai Windows/browser. Identitas selalu diperiksa ulang dari IIS, bukan cookie login lokal. Email verification/registrasi lokal tidak berlaku untuk identitas Windows.
3. Dalam mode uji baca, list dan download harus mengikuti Share + NTFS. Upload/create/move/copy/delete ditolak untuk semua user selama flag write false.
4. Uji request tanpa Windows auth, domain lain, akun lokal ASI01, dan header identitas palsu; semuanya harus ditolak. Login/register/password/passkey/logout lokal tidak dapat dipakai di mode Windows.
5. Uji dua user pada sesi browser/Windows terpisah; tidak boleh terjadi perpindahan identitas atau isi session. Logout aplikasi tidak dapat menghapus tiket Integrated Windows Authentication; UI memberi penjelasan itu.
6. Setelah baca terbukti, pada lingkungan uji saja ubah `SMB_WRITES_ENABLED=true`, reload konfigurasi dan uji seluruh operasi. Ini tidak memberi izin NTFS baru: user read-only harus tetap gagal menulis, user tanpa izin tetap gagal mengakses, dan user read/write hanya boleh mengubah lokasi yang diizinkan.
7. Verifikasi user/SID pada audit Windows file server, termasuk operasi download dan upload. Uji ACL subfolder, deny ACE, Share ACL lebih ketat, file terkunci, konflik nama, junction/reparse point, upload besar, dan kegagalan di tengah batch. Flag/runtime checks PHP tidak menggantikan pembuktian audit token.

Operasi filesystem tidak transaksional dan cache lock aplikasi tidak mengunci perubahan dari Windows Explorer. Perlindungan path memakai canonical path dan menolak symbolic link, tetapi race reparse point oleh penulis eksternal tetap perlu dibatasi dan diuji. Jangan memberi pihak tidak tepercaya kemampuan mengganti struktur root/reparse point selama operasi. Jika model ancaman membutuhkan jaminan terhadap race ini, gateway Windows dengan operasi berbasis handle diperlukan sebelum write produksi.

## Perubahan kode

- `WindowsIdentity`: verifikasi runtime dan principal IIS/domain.
- `AuthenticateWindows`: identitas per-request tanpa akun/password Laravel; invalidasi session ketika principal berubah; blokir endpoint akun lokal.
- `ShareFiles`: driver UNC tanpa fallback, validasi root dan mode baca.
- UI: identitas Windows, status domain dan mode uji baca; menu akun lokal disembunyikan pada mode Windows.

Tidak ada instalasi IIS, perubahan ACL/domain, atau akses ke file server yang dilakukan dari workspace pengembangan ini.

## Referensi

- [PHP FastCGI impersonation](https://www.php.net/manual/en/ini.core.php#ini.fastcgi.impersonate)
- [IIS Windows Authentication](https://learn.microsoft.com/en-us/iis/configuration/system.webserver/security/authentication/windowsauthentication/)
- [Identitas IIS dan filesystem ACL](https://learn.microsoft.com/en-us/iis/get-started/planning-for-security/secure-content-in-iis-through-file-system-acls)
