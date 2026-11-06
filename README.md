# Speedtest HST

**Pemasangan Ubuntu + Docker + aaPanel + GitHub:** baca [DEPLOY-AAPANEL.md](DEPLOY-AAPANEL.md). Skrip instalasi: `bash scripts/deploy.sh`; pembaruan: `bash scripts/deploy.sh --update`.

Aplikasi PHP 8.3 + MariaDB dengan mesin LibreSpeed yang disertakan pada versi commit di `public/vendor/VERSION`. Tampilan publik dan dashboard admin berbahasa Indonesia. Halaman publik langsung menampilkan tombol tes tanpa pilihan jenis pengguna atau isian SKPD; hasil baru dicatat dengan kategori Umum. Tidak memakai angka simulasi. Hasil dari browser merupakan laporan pengguna dan tidak boleh dianggap pengukuran SLA terverifikasi.

## Status pemeriksaan lokal

Diuji: sintaks PHP/JavaScript, halaman HTTP, worker LibreSpeed, tes nyata dari browser sampai tersimpan di MariaDB, login dashboard, ekspor CSV, penolakan CSRF dan sesi tes tidak valid. Pengukuran lokal tidak mewakili bandwidth server Pemkab. Database lokal berisi hasil uji browser; paket distribusi tidak menyertakan data atau kredensial lokal.

## Menjalankan

1. Pasang Docker dengan Compose di server Linux.
2. Salin `.env.example` menjadi `.env`. Isi tiga password berbeda, acak dan panjang: `DB_PASSWORD`, `DB_ROOT_PASSWORD`, `ADMIN_PASSWORD`. Jangan unggah `.env` ke Git.
3. Jalankan `docker compose up -d --build` dari direktori ini.
4. Buka `http://localhost:8093`. Dashboard: `/admin.php`, gunakan `ADMIN_PASSWORD`.
5. Jalankan pengujian, pastikan indikator hasil tersimpan muncul, masuk dashboard lalu cocokkan hasil. Verifikasi ekspor CSV.

Database hanya di jaringan Docker, tidak membuka port MySQL. Volume `database` menyimpan data permanen. Jangan menjalankan `docker compose down -v` jika ingin mempertahankan hasil. SQL inisialisasi hanya berjalan pada volume database yang masih kosong.

## Domain produksi

Arahkan A record `speedtest.hstkab.go.id` ke IP publik server. Siapkan TLS di reverse proxy, adaptasi `nginx.conf.example`, ubah `APP_HTTPS=1` lalu `docker compose up -d`. Domain belum otomatis terpasang oleh paket ini. Gunakan DNS-only tanpa CDN cache/proxy pada endpoint pengujian. Matikan cache, kompresi, buffering respons dan buffering upload agar tidak mendistorsi pengukuran. Buka 80/443 sesuai kebutuhan. Jangan membuka port database.

Tes mengukur koneksi ke server pemasangan, bukan semua tujuan internet. Server pada jaringan internal Pemkab dapat mengukur jalur lokal; gunakan titik eksternal untuk menilai konektivitas keluar. Versi awal ini satu titik pengujian. Lakukan kalibrasi terhadap koneksi yang diketahui, cek beban CPU, NIC dan bandwidth saat tes serentak sebelum penggunaan resmi. Tes dapat memakai banyak data.

## Data dan akses

Disimpan: ID acak, waktu UTC, nama SKPD atau Umum, download/upload Mbps, ping/jitter ms. Versi terbaru menyimpan IP, ISP (ASN offline), nama/lokasi server dan mode koneksi bersama hasil. User-agent tidak disimpan. Tautan `/result/<id-acak>` dapat dibagikan; IP penuh hanya terlihat bagi browser pemilik atau admin. Web server tetap dapat memiliki access log. Dashboard dilindungi password dari environment. API tulis memakai sesi dan CSRF, jeda tes per sesi, validasi rentang angka dan ID idempoten. Tidak ada hasil contoh di database.

Terapkan pembatasan permintaan/login per IP pada reverse proxy sebelum membuka layanan ke publik; pembatasan sesi saja dapat dilewati dengan sesi baru. Tinjau kebijakan retensi hasil dan log sesuai kebutuhan organisasi. Semua admin berbagi satu password pada versi awal. Ganti password dan restart layanan untuk mengganti kredensial; hapus sesi aktif pada container bila diperlukan.

Backup dengan `docker compose exec -T db sh -c 'mariadb-dump -u speedtest -p"$MARIADB_PASSWORD" speedtest' > backup.sql`. Simpan backup di lokasi akses terbatas dan uji pemulihan secara berkala.

Ekspor CSV dibatasi 10.000 baris per berkas, dilindungi login dan menetralkan awalan formula spreadsheet. Filter dan tanggal dashboard memakai UTC.

## Struktur

- `public/index.php`, `style.css`, `app.js`: halaman tes.
- `public/api.php`: sesi tes dan penyimpanan dengan prepared statements.
- `public/admin.php`: login, rekap, filter, grafik dan ekspor.
- `app/bootstrap.php`: sesi, CSRF dan koneksi PDO; berada di luar document root.
- `database/001_schema.sql`: skema tabel.
- `public/vendor`: LibreSpeed beserta lisensi LGPL-3.0. Pertahankan atribusi dan lisensi saat distribusi; sumber upstream https://github.com/librespeed/speedtest.

Paket ini ditujukan ke hosting PHP milik Pemkab. Runtime Sites memakai Cloudflare Workers sehingga tidak menjalankan backend PHP ini. Publikasi domain memerlukan akses server dan DNS dari pengelola.

## Riwayat dan server (pembaruan)

Riwayat `/history.php` dikaitkan ke cookie acak HttpOnly selama satu tahun; database menyimpan hash token, bukan token mentah. Menghapus cookie atau menggunakan browser lain memutus akses ke daftar; simpan tautan hasil jika perlu. Hasil lama tanpa cookie kepemilikan tetap ada pada dashboard admin.

Pada instalasi yang sudah memiliki database versi awal, jalankan `docker compose exec -T db sh -c 'mariadb -u speedtest -p"$MARIADB_PASSWORD" speedtest' < database/002_result_details.sql` **satu kali** sebelum memperbarui aplikasi. Jangan mengulang migrasi yang sudah diterapkan. Instalasi baru menerapkan semua berkas SQL otomatis.

Server ada di `app/servers.json`. Entri berisi `id`, `name`, `city`, `base`. `base` harus endpoint LibreSpeed milik pengelola, berakhiran `/`, menyediakan `garbage.php`, `empty.php`, `getIP.php`. Server eksternal wajib HTTPS, mendukung CORS (termasuk parameter `cors=true`), diuji manual sebelum ditambahkan. Daftar server Ookla/Faznet tidak kompatibel otomatis. Nama/lokasi server adalah konfigurasi pengelola, bukan geolokasi otomatis. Multi memakai 6 koneksi download / 3 upload; Single memakai 1/1.

Deteksi ISP memakai ASN database bawaan LibreSpeed; nama legal jaringan dapat berbeda dari merek dagang. Basis data perlu diperbarui berkala. Tidak ada pengiriman IP ke API pihak ketiga. Pada reverse proxy, atur `TRUSTED_PROXIES` ke **IP tepat proxy** yang terlihat oleh container dan gunakan contoh header `X-Real-IP` dari Nginx. Jangan percaya header dari sembarang alamat. Tanpa konfigurasi ini, aplikasi menampilkan IP peer jaringan lokal, bukan IP publik pengguna.

### Kebijakan server domestik

Pilihan Frankfurt dan Los Angeles sudah dikeluarkan sesuai preferensi domestik Indonesia. Hasil historisnya tetap disimpan dengan nama server yang benar. Saat ini hanya endpoint aplikasi sendiri yang aktif, tanpa klaim kota atau lokasi fisik; lokasi mengikuti server tempat aplikasi dipasang.

Pengecekan daftar publik LibreSpeed pada 6 November 2026 tidak menghasilkan server Indonesia yang terverifikasi untuk integrasi. Jangan menambahkan server hanya karena berlabel Indonesia; pastikan lokasi, kompatibilitas LibreSpeed, izin pengelola, HTTPS dan CORS, serta pengujian download/upload. Untuk layanan domestik, pasang aplikasi pada server Pemkab di Indonesia atau siapkan titik LibreSpeed pada server Indonesia milik pengelola. Server dalam jaringan yang sama mengukur jalur lokal, jadi titik domestik eksternal dibutuhkan untuk pembanding internet keluar.
