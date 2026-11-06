# Pemasangan Speedtest HST — Ubuntu, Docker, aaPanel, GitHub

Target: `https://speedtest.hstkab.go.id`, server on-premise Hulu Sungai Tengah. Nginx aaPanel menangani TLS, container aplikasi mendengarkan **127.0.0.1:8093**, MariaDB hanya di jaringan Docker. Domain dan IP publik tidak disetel otomatis oleh paket ini.

## 1. Siapkan repository GitHub khusus

Gunakan repository khusus (disarankan private), dengan isi **direktori speedtest-hst sebagai root repository**, sehingga `compose.yaml` ada langsung di root. Jangan mengunggah seluruh folder kerja ChatGPT. `.env`, backups, dan data volume tidak boleh masuk Git. Paket ZIP sudah mengecualikannya.

Jika mengunggah dari komputer pengembangan dengan GitHub CLI: login melalui `gh auth login`, lalu setelah repository tujuan ditentukan lakukan init/commit/push **hanya** di direktori proyek ini. Jangan menjalankan init/commit pada direktori induk yang berisi proyek lain. Repository tujuan: https://github.com/ezakifuad/speedtest-hst.

## 2. Siapkan server

Gunakan terminal Ubuntu/aaPanel dengan akun yang memiliki izin Docker. Pastikan Docker Engine, Compose plugin, Git, OpenSSL, curl, gzip dan util-linux tersedia:

```bash
docker version
docker compose version
git --version
openssl version
command -v flock
```

Jika Docker sudah dikelola aaPanel, gunakan instalasi tersebut; jangan memasang daemon kedua. Untuk Ubuntu tanpa Docker, ikuti dokumentasi resmi https://docs.docker.com/engine/install/ubuntu/ dan Compose https://docs.docker.com/compose/install/linux/.

Sediakan IP publik atau NAT yang meneruskan 80/443 ke Nginx aaPanel. Arahkan DNS A `speedtest.hstkab.go.id` ke IP publik. Jangan membuka 8093 atau port database ke publik. Gunakan DNS-only jika memakai Cloudflare; jangan menempatkan proxy CDN di jalur pengukuran. Untuk akses dari LAN, sesuaikan split DNS/hairpin NAT dengan pengelola jaringan.

## 3. Hubungkan server ke GitHub

Untuk repository private, buat deploy key khusus pada server, dengan akun yang nanti melakukan clone/update:

```bash
ssh-keygen -t ed25519 -f ~/.ssh/speedtest_hst_deploy -C speedtest-hst-server
cat ~/.ssh/speedtest_hst_deploy.pub
```

Hanya kunci **.pub** yang disalin ke GitHub → repository → Settings → Deploy keys → Add deploy key. Biarkan **Allow write access tidak dicentang**. Private key tetap di server. Jika memakai passphrase, muat melalui ssh-agent sebelum pull. Jangan menaruh token dalam URL Git.

Tambahkan blok berikut ke `~/.ssh/config` akun tersebut (pertahankan konfigurasi lain):

```sshconfig
Host github-speedtest-hst
    HostName github.com
    User git
    IdentityFile ~/.ssh/speedtest_hst_deploy
    IdentitiesOnly yes
```

Saat pertama tersambung, cocokkan fingerprint host GitHub dengan dokumentasi https://docs.github.com/en/authentication/keeping-your-account-and-data-secure/githubs-ssh-key-fingerprints. Jangan menonaktifkan pemeriksaan host key.

Clone repository proyek:

```bash
git clone git@github-speedtest-hst:ezakifuad/speedtest-hst.git /opt/speedtest-hst
cd /opt/speedtest-hst
bash scripts/deploy.sh
```

`/opt/speedtest-hst` berada di luar document root website aaPanel. Pastikan akun deploy dapat menulis di lokasi tersebut. Skrip membuat `.env` hanya jika belum ada, menjalankan database, memperbarui skema bila diperlukan, mengatur IP gateway proxy yang dipercaya, membangun aplikasi dan menunggu status sehat. Kata sandi tidak dicetak ke terminal. Instalasi baru memakai APP_HTTPS=1. Untuk checkout lama pastikan APP_HTTPS=1 pada `.env` sebelum produksi.

Periksa:

```bash
docker compose ps
curl -fsS http://127.0.0.1:8093/health.php
```

Harus menghasilkan `{"status":"ok"}`. Halaman HTTP lokal hanya untuk pemeriksaan layanan; buka aplikasi pengguna melalui HTTPS setelah langkah berikut.

## 4. Hubungkan domain melalui aaPanel

Gunakan Nginx aaPanel. Buka Website → Proxy Project → Add (nama menu dapat berbeda menurut versi):

- Domain: `speedtest.hstkab.go.id`.
- Proxy target: `http://127.0.0.1:8093`.
- Aktifkan sertifikat SSL/Let's Encrypt setelah DNS dan validasi domain tersedia.
- Aktifkan pengalihan HTTP ke HTTPS setelah sertifikat terbit.
- Matikan cache/kompresi untuk situs speedtest.

Konfigurasi yang disediakan:

- `deploy/aapanel/http-rate-limit.conf`: masukkan **sekali** di dalam konteks `http {}` konfigurasi Nginx utama. Ini mendefinisikan zona pembatas login.
- `deploy/aapanel/proxy-location.conf`: sesuaikan di dalam blok `server {}` HTTPS situs ini, mengganti location proxy yang sama. Jangan menempelkan satu blok `server` baru di dalam blok yang sudah ada. Pertahankan sertifikat, lokasi ACME dan pengaturan SSL aaPanel.

Pastikan `gzip off`, `proxy_buffering off`, `proxy_request_buffering off`, `proxy_cache off` dan batas upload 64 MB diterapkan. aaPanel WAF/CDN/anti-bot yang menyisipkan halaman HTML atau membatasi ukuran upload dapat mengganggu tes; sesuaikan hanya aturan situs ini berdasarkan hasil pemeriksaan. Jangan mematikan perlindungan global.

Uji konfigurasi dan reload melalui aaPanel. Jika Nginx memakai lokasi instalasi standar aaPanel, pemeriksaan terminal biasanya:

```bash
/www/server/nginx/sbin/nginx -t
```

Jangan reload bila pemeriksaan gagal. Jika port 8093 sudah terpakai, ubah mapping Compose dan semua target proxy/pemeriksaan secara konsisten.

## 5. Pemeriksaan setelah pemasangan

1. Dari ponsel dengan data seluler, buka HTTPS domain dan jalankan tes. Pastikan hasil tersimpan dan tautan `/result/...` bisa dibuka.
2. Cocokkan IP di halaman koneksi dengan IP publik pengguna yang diketahui. `TRUSTED_PROXIES` diatur ke gateway Docker oleh skrip; jika arsitektur proxy berbeda, sesuaikan dengan IP peer **yang benar-benar terhubung ke container**, jangan percaya semua alamat. Jangan mengganti nilai ini dengan IP publik pengguna.
3. Periksa Riwayat Saya, mode Single/Multi dan login `/admin.php`. Password admin berada pada `.env` server; lihat lewat editor file yang aksesnya terbatas, jangan menyalinnya ke issue/chat/GitHub.
4. Buka tautan hasil di browser lain: IP harus tersamarkan dan riwayat pribadi tidak ikut terbuka.
5. Pantau CPU/NIC/bandwidth server ketika beberapa tes bersamaan. Pengguna LAN yang satu jaringan mengukur jalur lokal; tes dari luar mengukur jalur internet ke Pemkab. Ini belum menjadi pembanding ke tujuan internet lain.

Setelah lokasi pemasangan terverifikasi, ubah nama dan lokasi server di `app/servers.json` melalui repository, misalnya `Server Pemkab HST` dan `Hulu Sungai Tengah, Indonesia`. Jangan mengubah path backend `/vendor/backend/`. Tampilan pratinjau saat ini sengaja tidak mengklaim lokasi fisik server produksi.

## 6. Pembaruan dari GitHub

Alur awal: commit/push dari komputer pengembangan, lalu jalankan di server:

```bash
cd /opt/speedtest-hst
bash scripts/deploy.sh --update
```

Skrip menolak pembaruan bila ada perubahan lokal, membuat backup DB, melakukan `git pull --ff-only`, memeriksa skema lalu membangun ulang aplikasi. Volume database tetap dipakai; `.env` tidak diganti dan password tidak direset. File backup berada di `backups/` dengan akses terbatas. Buat salinan backup di lokasi terpisah dan uji pemulihan. Jangan menjalankan `docker compose down -v` pada server yang datanya ingin dipertahankan.

Integrasi ini memakai pull dari GitHub secara terkendali. Belum ada webhook publik, GitHub Actions deploy, atau pembaruan otomatis yang berjalan diam-diam. Jika nanti dibutuhkan otomatisasi, tetapkan runner/SSH dan branch deployment terlebih dahulu.

Jika deployment baru gagal, lihat `docker compose logs --tail=100 web` dan `docker compose logs --tail=100 db` di server, tanpa membagikan log yang memuat kredensial/data pribadi. Pemulihan kode: pilih commit yang diketahui baik lalu jalankan deployment; migrasi masa depan mungkin memerlukan rencana pemulihan skema tersendiri. Backup tidak dipulihkan otomatis karena dapat menimpa hasil tes baru.

## Referensi

- aaPanel Proxy Project: https://www.aapanel.com/docs/Function/proxy.html
- aaPanel Docker / Compose: https://www.aapanel.com/docs/Function/Docker.html
- GitHub deploy keys: https://docs.github.com/en/authentication/connecting-to-github-with-ssh/managing-deploy-keys
