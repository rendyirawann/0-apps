# Transaksi Pekerjaan — API

API taksasi pekerjaan konstruksi: menghitung bagi hasil dari nilai pekerjaan
(pagu) sampai jatah bersih per owner, mencatat arus kas dan rincian belanja
per kegiatan, lalu menerbitkan laporannya sebagai PDF.

Menggantikan spreadsheet "Taksasi Pekerjaan". Dipakai oleh aplikasi mobile
Flutter (repo terpisah) yang seluruh datanya berasal dari API ini.

- **Laravel 13** · PHP 8.3 · PostgreSQL
- Autentikasi **Laravel Sanctum** (bearer token) + login sidik jari
- Dokumentasi **Swagger / OpenAPI** — 33 path, 46 operasi
- **65 test**, 331 assertion

---

## Rumus perhitungan

Seluruh perhitungan berada di satu kelas, `App\Services\TaksasiCalculator`,
dan dikunci oleh 15 unit test. Aplikasi mobile tidak pernah menghitung sendiri
— ia memanggil `POST /api/kegiatan/preview` — sehingga tidak mungkin ada dua
versi rumus yang berbeda.

```
PPN            = pagu × rate_ppn %          ← basis PAGU
PPh            = pagu × rate_pph %          ← basis PAGU
Netto          = pagu − PPN − PPh

Rencana        = netto × rate_rencana %
Kewajiban      = netto × rate_kewajiban %
Administrasi   = netto × rate_administrasi %
B. Perusahaan  = netto × rate_perusahaan %

Profit Kotor   = netto − Kewajiban − Pelaksanaan Real − Administrasi − B. Perusahaan
Investor       = profit_kotor × rate_investor %
Profit Bersih  = profit_kotor − Investor
Per Owner      = profit_bersih ÷ jml_owner
```

**Contoh terverifikasi** (baris "A" pada spreadsheet asli): pagu 400.000.000 →
netto 349.000.000 → profit kotor 88.995.000 → **per owner Rp14.832.500**.

Tiga keputusan yang perlu diketahui:

1. **PPN & PPh dari pagu, sisanya dari netto.** 60 + 12 + 1 + 1,5 = 74,5%
   beban, sisa 25,5% profit kotor — totalnya tepat 100% dari netto, yang
   membuktikan netto adalah basis tunggalnya.
2. **Profit bersih adalah selisih, bukan pembulatan ulang.**
   `investor + profit_bersih` selalu sama dengan `profit_kotor`.
3. **Pembagian ke owner memakai `intdiv()`, bukan `floor()`.** Untuk angka
   positif hasilnya sama, tetapi saat rugi `floor(-100/3) = -34` membuat
   3 × -34 = -102 — kerugian yang dibagikan jadi lebih besar dari kerugian
   sebenarnya. `intdiv()` memotong ke arah nol; sisa 0–2 rupiah masuk kas
   perusahaan sebagai `sisa_pembulatan`.

Semua nilai uang adalah `numeric(18,0)` — rupiah bulat, tanpa sen.

### Biaya Pelaksanaan Real — mode hibrida

Kolom ini bisa diisi manual **atau** dibiarkan kosong. Kalau kosong:

```
Biaya Pelaksanaan Real = Σ rincian bahan baku + Σ kas kategori "upah"
```

Kalau keduanya masih nol, dipakai nilai Rencana sebagai proyeksi agar kolom
profit tetap terisi saat kegiatan masih draft. Sumber yang aktif terlihat di
`pelaksanaan_real_sumber` (`manual` / `realisasi` / `proyeksi_rencana`).

Bahan baku **tidak** dicatat sebagai arus kas — angkanya berasal dari tabel
`bahan_baku_items`, satu baris per barang, dengan `subtotal` yang selalu
dihitung server dari `qty × harga_satuan`. Karena itu kategori kas `bahan`
dikeluarkan dari daftar kategori yang bisa dipilih dan validasi menolaknya.
Setiap perubahan item memicu `Kegiatan::recalculate()`, jadi taksasi tidak
pernah tertinggal dari rinciannya.

---

## Peran & hak akses

Dua peran, ditegakkan di satu tempat (`App\Support\Izin`) lalu dikirim ke
klien sebagai objek `izin` pada setiap respons profil dan detail kegiatan.
Aplikasi **tidak** menyimpulkan hak akses dari nama peran — ia membaca `izin`
itu — sehingga tombol yang tampil selalu sama dengan yang benar-benar
diizinkan server.

| Tindakan | Superadmin | Petugas |
|---|---|---|
| Melihat semua data (kegiatan, kas, laporan, PDF) | ya | ya |
| Membuat / mengubah / menghapus kegiatan | ya | — |
| Rincian bahan baku + unggah struk | ya | **ya** |
| Kas kategori `upah` & `administrasi` | ya | **ya** |
| Kas kategori lain (termin, kewajiban, pajak, bagi hasil) | ya | — |
| Mengubah default rate / pengaturan | ya | — |
| Mengelola akun petugas | ya | — |
| Melihat jejak aktivitas akun lain | ya | — |
| Melihat jejak aktivitas sendiri | ya | ya |

**Superadmin hanya satu, dijaga secara struktural, bukan dengan validasi:**
`UserRequest::dataPetugas()` selalu menetapkan `role = petugas` sehingga field
`role` dari klien diabaikan, dan `show`/`update`/`destroy` pada
`/api/pengguna/{id}` menolak akun superadmin dengan 403. Satu-satunya cara
membuat superadmin adalah lewat seeder.

## Jejak aktivitas

Middleware `CatatAktivitas` dipasang ke seluruh grup `api`, bukan dipanggil
per controller — jadi endpoint yang ditambahkan nanti ikut tercatat tanpa
perlu diingat penulisnya. Setiap permintaan non-GET dicatat lengkap dengan
pelaku, modul, subjek, status HTTP, durasi, dan IP. Percobaan yang **ditolak**
(403) juga tercatat, karena justru itu yang perlu terlihat.

Nama dan peran pelaku **disalin** ke baris log, tidak di-join ke tabel `users`,
supaya jejak lama tetap benar walau akunnya berganti nama atau dihapus.

Payload permintaan disimpan setelah disaring: `password`,
`password_confirmation`, `current_password`, `biometric_token`,
`access_token`, dan `token` dibuang; berkas unggahan diganti penanda
`[berkas: nama, N KB]`; payload di atas 8000 byte dipotong.

---

## Menjalankan

### Prasyarat

- PHP **8.3+** (Laravel 13 mensyaratkannya)
- PostgreSQL — proyek ini memakai port **5433**
- Composer 2.x

### Langkah

```bash
composer install
cp .env.example .env
php artisan key:generate

# Sesuaikan DB_* di .env, lalu:
createdb -p 5433 o_taksasi        # atau buat lewat pgAdmin
php artisan migrate --seed

php artisan serve --host=127.0.0.1 --port=8000
```

| Keperluan | URL |
|---|---|
| Dokumentasi Swagger | http://127.0.0.1:8000/api/documentation |
| Spesifikasi OpenAPI (JSON) | http://127.0.0.1:8000/docs |
| Cek kesehatan API | http://127.0.0.1:8000/api/health |

Regenerate dokumentasi setelah mengubah anotasi:

```bash
php artisan l5-swagger:generate
```

### Akun hasil seeder

> **Hanya untuk pengembangan lokal.** Ganti sebelum dipakai di server mana
> pun — password di bawah ada di repo publik ini.

| Peran | Nama | Email | Password |
|---|---|---|---|
| Superadmin | Bayu Apriansah | `superadmin@taksasi.test` | `password123` |
| Petugas | Sinta Pratiwi | `sinta@taksasi.test` | `password123` |
| Petugas | Rudi Hartono | `rudi@taksasi.test` | `password123` |

Seeder juga membuat 3 kegiatan contoh: satu persis contoh spreadsheet, satu
dengan rate berbeda (PPh 2,65%, 2 owner, real manual), dan satu draft yang
masih memakai proyeksi rencana.

### Perintah berguna

```bash
php artisan test                  # 65 test, 331 assertion
./vendor/bin/pint                 # format kode
php artisan route:list --path=api # daftar endpoint
```

---

## Ringkasan endpoint

Semua respons memakai envelope tunggal:

```json
{ "success": true, "message": "...", "data": {}, "meta": {} }
```

Error memakai bentuk yang sama dengan `success: false`, plus `code`
(`INVALID_CREDENTIALS`, `VALIDATION_ERROR`, `MODEL_NOT_FOUND`,
`UNAUTHENTICATED`, `FORBIDDEN`, `LAMPIRAN_DUPLIKAT`, …) dan `errors` per kolom
pada 422.

| Metode | Path | Keterangan |
|---|---|---|
| GET | `/api/health` | Cek kesehatan (publik) |
| POST | `/api/auth/login` | Login email + password |
| POST | `/api/auth/biometric/login` | Login dengan token sidik jari |
| POST | `/api/auth/biometric/enable` | Daftarkan sidik jari (butuh password) |
| DELETE | `/api/auth/biometric` | Cabut token sidik jari |
| GET | `/api/auth/me` | Profil pengguna aktif + `izin` |
| PUT | `/api/auth/profil` | Lengkapi profil sendiri |
| POST | `/api/auth/change-password` | Ganti password |
| POST | `/api/auth/logout` | Cabut access token |
| GET | `/api/referensi` | Semua enum + default rate |
| GET/PUT | `/api/referensi/default-rates` | Default persentase |
| GET | `/api/kegiatan` | Daftar (cari, filter, urut, paginasi) |
| POST | `/api/kegiatan` | Tambah — cukup `nama` + `pagu` |
| POST | `/api/kegiatan/preview` | **Hitung tanpa simpan** (pratinjau form) |
| GET/PUT/PATCH/DELETE | `/api/kegiatan/{id}` | Detail / ubah / hapus |
| GET/POST | `/api/kegiatan/{id}/bahan-baku` | Rincian bahan baku + totalnya |
| PUT/DELETE | `/api/bahan-baku/{id}` | Ubah / hapus satu item |
| GET/POST | `/api/kegiatan/{id}/lampiran` | Bukti belanja (multipart, maks 8 MB) |
| GET | `/api/lampiran/{id}/berkas` | Ambil isi berkas (butuh token) |
| DELETE | `/api/lampiran/{id}` | Hapus lampiran |
| GET/POST | `/api/kegiatan/{id}/cash-flows` | Kas satu kegiatan |
| GET | `/api/kegiatan/{id}/cash-flows/rekap` | Rekap kas per kategori |
| GET | `/api/cash-flows` | Kas lintas kegiatan |
| GET/PUT/DELETE | `/api/cash-flows/{id}` | Detail / ubah / hapus |
| GET/POST | `/api/pengguna` | Daftar / buat akun petugas — superadmin |
| GET/PUT/DELETE | `/api/pengguna/{id}` | Detail / ubah / nonaktifkan petugas |
| GET | `/api/aktivitas/saya` | Jejak aktivitas sendiri |
| GET | `/api/aktivitas` | Jejak seluruh akun — superadmin |
| GET | `/api/aktivitas/ringkasan` | Hitungan per modul & per akun — superadmin |
| GET | `/api/laporan/ringkasan` | Angka dashboard + tren kas |
| GET | `/api/laporan/rekap-kegiatan` | Tabel rekap + baris TOTAL |
| GET | `/api/laporan/kegiatan/{id}/pdf` | PDF taksasi satu kegiatan |
| GET | `/api/laporan/rekap-kegiatan/pdf` | PDF rekap (landscape) |
| GET | `/api/laporan/arus-kas/pdf` | PDF buku kas (landscape) |

Tiga hal yang perlu diketahui saat memakai endpoint ini:

- **Membuat kegiatan cukup `nama` + `pagu`.** Persentase yang tidak dikirim
  diisi server dari default pengaturan (`RateDefaults::all()`), bukan nol —
  kalau nol, taksasi kegiatan baru langsung menampilkan profit yang salah.
  `PUT`/`PATCH` memakai aturan `sometimes`, jadi sisa datanya bisa dilengkapi
  sebagian-sebagian.

- **Endpoint bahan baku membalas rincian lengkap, bukan hanya item yang
  berubah.** `POST`, `PUT`, dan `DELETE` semuanya mengembalikan
  `total_bahan_baku`, `total_upah`, dan `total_pelaksanaan` terbaru, sehingga
  klien tidak perlu menjumlah ulang sendiri. `subtotal` tidak boleh dikirim
  klien — server menghitungnya dari `qty × harga_satuan`.

- **Berkas lampiran tidak punya URL publik.** `url_berkas` menunjuk
  `/api/lampiran/{id}/berkas`, yang memeriksa token. Berkas identik pada
  kegiatan yang sama ditolak `409 LAMPIRAN_DUPLIKAT` berdasarkan hash SHA-256.

---

## Yang masih perlu diputuskan

Tiga hal diimplementasikan dengan asumsi yang bisa diubah cepat:

1. **Bagi hasil investor** — apakah persentasenya sudah termasuk pengembalian
   modal, atau modal dikembalikan terpisah? Saat ini `rate_investor` murni
   persentase dari profit kotor, dan setoran modal dicatat sebagai kas masuk
   kategori `modal_investor` tanpa memengaruhi rumus.
2. **Kondisi rugi** — saat ini investor ikut menanggung sesuai persentasenya.
   Kalau seharusnya tidak, yang perlu diubah hanya satu baris di
   `TaksasiCalculator::hitung()`.
3. **Sisa pembulatan** — sisa 0–2 rupiah saat ini masuk kas perusahaan. Kalau
   seharusnya ke owner pertama, ubah pembagiannya di kalkulator yang sama.

Belum termasuk: notifikasi push, ekspor Excel, dan persetujuan berjenjang
(mis. rincian bahan baku petugas perlu disetujui superadmin sebelum ikut
menghitung).
