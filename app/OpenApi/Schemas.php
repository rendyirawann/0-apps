<?php

declare(strict_types=1);

namespace App\OpenApi;

use OpenApi\Attributes as OA;

/**
 * Definisi schema bersama untuk Swagger. Kelas ini tidak pernah dieksekusi,
 * hanya dibaca oleh swagger-php saat generate dokumentasi.
 */
#[OA\Schema(
    schema: 'User',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'name', type: 'string', example: 'Bayu Apriansah'),
        new OA\Property(property: 'email', type: 'string', format: 'email', example: 'superadmin@taksasi.test'),
        new OA\Property(property: 'role', description: 'superadmin | petugas', type: 'string', example: 'petugas'),
        new OA\Property(property: 'role_label', type: 'string', example: 'Petugas'),
        new OA\Property(property: 'is_superadmin', type: 'boolean', example: false),
        new OA\Property(property: 'is_petugas', type: 'boolean', example: true),
        new OA\Property(property: 'jabatan', type: 'string', nullable: true, example: 'Pelaksana Lapangan'),
        new OA\Property(property: 'alamat', type: 'string', nullable: true),
        new OA\Property(property: 'izin', description: 'Ringkasan hak akses; dipakai aplikasi untuk menentukan tombol yang ditampilkan', type: 'object'),
        new OA\Property(property: 'phone', type: 'string', nullable: true),
        new OA\Property(property: 'is_active', type: 'boolean', example: true),
        new OA\Property(property: 'biometric_enabled', type: 'boolean', example: false),
        new OA\Property(property: 'biometric_device_name', type: 'string', nullable: true),
        new OA\Property(property: 'biometric_expires_at', type: 'string', format: 'date-time', nullable: true),
        new OA\Property(property: 'password_changed_at', type: 'string', format: 'date-time', nullable: true),
        new OA\Property(property: 'last_login_at', type: 'string', format: 'date-time', nullable: true),
    ],
    type: 'object',
)]
#[OA\Schema(
    schema: 'TaksasiHasil',
    description: 'Hasil perhitungan transaksi. Semua nilai uang adalah rupiah bulat.',
    properties: [
        new OA\Property(property: 'pagu', type: 'integer', example: 400000000),
        new OA\Property(property: 'ppn', type: 'integer', example: 44000000),
        new OA\Property(property: 'pph', type: 'integer', example: 7000000),
        new OA\Property(property: 'netto', description: 'pagu - ppn - pph', type: 'integer', example: 349000000),
        new OA\Property(property: 'rencana_pelaksanaan', type: 'integer', example: 209400000),
        new OA\Property(property: 'biaya_kewajiban', type: 'integer', example: 41880000),
        new OA\Property(property: 'pelaksanaan_real', type: 'integer', example: 209400000),
        new OA\Property(property: 'biaya_administrasi', type: 'integer', example: 3490000),
        new OA\Property(property: 'biaya_perusahaan', type: 'integer', example: 5235000),
        new OA\Property(property: 'profit_kotor', type: 'integer', example: 88995000),
        new OA\Property(property: 'bagi_hasil_investor', type: 'integer', example: 44497500),
        new OA\Property(property: 'profit_bersih', type: 'integer', example: 44497500),
        new OA\Property(property: 'hasil_bersih_per_owner', description: 'Jatah SATU owner', type: 'integer', example: 14832500),
        new OA\Property(property: 'sisa_pembulatan', description: 'Sisa rupiah yang tidak terbagi rata, masuk kas perusahaan', type: 'integer', example: 0),
        new OA\Property(property: 'jml_owner', type: 'integer', example: 3),
        new OA\Property(property: 'persen_pelaksanaan_real', type: 'number', format: 'float', example: 60),
        new OA\Property(property: 'persen_profit_kotor', type: 'number', format: 'float', example: 25.5),
        new OA\Property(property: 'selisih_rencana_real', description: 'rencana - real; negatif berarti over budget', type: 'integer', example: 0),
        new OA\Property(property: 'total_persen_beban', type: 'number', format: 'float', example: 74.5),
        new OA\Property(property: 'sisa_persen', type: 'number', format: 'float', example: 25.5),
        new OA\Property(property: 'is_rugi', type: 'boolean', example: false),
    ],
    type: 'object',
)]
#[OA\Schema(
    schema: 'BreakdownRow',
    description: 'Satu baris breakdown, siap dirender di tabel/laporan.',
    properties: [
        new OA\Property(property: 'key', type: 'string', example: 'profit_kotor'),
        new OA\Property(property: 'label', type: 'string', example: 'Profit Kotor'),
        new OA\Property(property: 'persen', type: 'string', nullable: true, example: '25,5%'),
        new OA\Property(property: 'nilai', type: 'integer', example: 88995000),
        new OA\Property(property: 'nilai_formatted', type: 'string', example: 'Rp88.995.000'),
        new OA\Property(property: 'tipe', description: 'dasar | pengurang | beban | rencana | hasil', type: 'string', example: 'hasil'),
        new OA\Property(property: 'catatan', type: 'string', nullable: true),
    ],
    type: 'object',
)]
#[OA\Schema(
    schema: 'Kegiatan',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'kode', type: 'string', nullable: true, example: 'KG-2026-001'),
        new OA\Property(property: 'nama', type: 'string', example: 'A'),
        new OA\Property(property: 'keterangan', type: 'string', nullable: true),
        new OA\Property(property: 'lokasi', type: 'string', nullable: true),
        new OA\Property(property: 'sumber_dana', type: 'string', nullable: true),
        new OA\Property(property: 'tanggal_mulai', type: 'string', format: 'date', nullable: true),
        new OA\Property(property: 'tanggal_selesai', type: 'string', format: 'date', nullable: true),
        new OA\Property(property: 'status', description: 'draft | berjalan | selesai | batal', type: 'string', example: 'berjalan'),
        new OA\Property(property: 'status_label', type: 'string', example: 'Berjalan'),
        new OA\Property(property: 'pagu', type: 'integer', example: 400000000),
        new OA\Property(property: 'pagu_formatted', type: 'string', example: 'Rp400.000.000'),
        new OA\Property(property: 'netto', type: 'integer', example: 349000000),
        new OA\Property(property: 'profit_kotor', type: 'integer', example: 88995000),
        new OA\Property(property: 'profit_bersih', type: 'integer', example: 44497500),
        new OA\Property(property: 'hasil_bersih_per_owner', type: 'integer', example: 14832500),
        new OA\Property(property: 'hasil_bersih_per_owner_formatted', type: 'string', example: 'Rp14.832.500'),
        new OA\Property(property: 'jml_owner', type: 'integer', example: 3),
        new OA\Property(property: 'is_rugi', type: 'boolean', example: false),
    ],
    type: 'object',
)]
#[OA\Schema(
    schema: 'KegiatanDetail',
    description: 'Kegiatan lengkap dengan rate, breakdown transaksi, dan ringkasan kas.',
    allOf: [
        new OA\Schema(ref: '#/components/schemas/Kegiatan'),
        new OA\Schema(properties: [
            new OA\Property(property: 'rates', type: 'object'),
            new OA\Property(property: 'pelaksanaan_real_input', type: 'integer', nullable: true),
            new OA\Property(property: 'pelaksanaan_real_sumber', description: 'manual | kas | proyeksi_rencana', type: 'string'),
            new OA\Property(property: 'pelaksanaan_real_dari_kas', type: 'integer'),
            new OA\Property(property: 'taksasi', ref: '#/components/schemas/TaksasiHasil'),
            new OA\Property(property: 'breakdown', type: 'array', items: new OA\Items(ref: '#/components/schemas/BreakdownRow')),
            new OA\Property(property: 'ringkasan_kas', type: 'object'),
        ], type: 'object'),
    ],
)]
#[OA\Schema(
    schema: 'CashFlow',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'kegiatan_id', type: 'integer', example: 1),
        new OA\Property(property: 'tanggal', type: 'string', format: 'date', example: '2026-09-03'),
        new OA\Property(property: 'jenis', description: 'masuk | keluar', type: 'string', example: 'keluar'),
        new OA\Property(property: 'jenis_label', type: 'string', example: 'Kas Keluar'),
        new OA\Property(property: 'kategori', type: 'string', example: 'bahan'),
        new OA\Property(property: 'kategori_label', type: 'string', example: 'Belanja Bahan'),
        new OA\Property(property: 'nominal', type: 'integer', example: 15000000),
        new OA\Property(property: 'nominal_formatted', type: 'string', example: 'Rp15.000.000'),
        new OA\Property(property: 'nominal_bertanda', description: 'Positif untuk masuk, negatif untuk keluar', type: 'integer', example: -15000000),
        new OA\Property(property: 'uraian', type: 'string', example: 'Pembelian besi beton'),
        new OA\Property(property: 'keterangan', type: 'string', nullable: true),
        new OA\Property(property: 'metode', description: 'kas | transfer', type: 'string', example: 'transfer'),
        new OA\Property(property: 'no_bukti', type: 'string', nullable: true),
    ],
    type: 'object',
)]
#[OA\Schema(
    schema: 'ValidationError',
    properties: [
        new OA\Property(property: 'message', type: 'string', example: 'The given data was invalid.'),
        new OA\Property(property: 'errors', type: 'object', example: ['pagu' => ['Pagu wajib diisi.']]),
    ],
    type: 'object',
)]
#[OA\Schema(
    schema: 'BahanBakuItem',
    description: 'Satu baris rincian bahan baku. Subtotal dihitung server dari qty x harga_satuan; klien tidak pernah mengirimkannya.',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'kegiatan_id', type: 'integer', example: 1),
        new OA\Property(property: 'nama', type: 'string', example: 'Besi beton 12mm'),
        new OA\Property(property: 'satuan', type: 'string', example: 'batang'),
        new OA\Property(property: 'qty', description: 'Boleh pecahan, mis. 4.5 m3', type: 'number', format: 'float', example: 250),
        new OA\Property(property: 'qty_formatted', type: 'string', example: '250'),
        new OA\Property(property: 'harga_satuan', type: 'integer', example: 145000),
        new OA\Property(property: 'harga_satuan_formatted', type: 'string', example: 'Rp145.000'),
        new OA\Property(property: 'subtotal', type: 'integer', example: 36250000),
        new OA\Property(property: 'subtotal_formatted', type: 'string', example: 'Rp36.250.000'),
        new OA\Property(property: 'tanggal_beli', type: 'string', format: 'date', nullable: true),
        new OA\Property(property: 'no_struk', type: 'string', nullable: true, example: 'INV/BJ/1207'),
        new OA\Property(property: 'toko', type: 'string', nullable: true, example: 'TB Sumber Jaya'),
        new OA\Property(property: 'keterangan', type: 'string', nullable: true),
        new OA\Property(property: 'urutan', type: 'integer', example: 1),
    ],
    type: 'object',
)]
#[OA\Schema(
    schema: 'Lampiran',
    description: 'Bukti berkas (foto struk belanja / dokumen). Isi berkasnya diambil lewat url_berkas yang memeriksa token, bukan URL publik.',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'kegiatan_id', type: 'integer', example: 1),
        new OA\Property(property: 'konteks', type: 'string', enum: ['biaya_pelaksanaan', 'administrasi', 'lain'], example: 'biaya_pelaksanaan'),
        new OA\Property(property: 'nama_asli', type: 'string', example: 'struk-belanja.jpg'),
        new OA\Property(property: 'mime', type: 'string', example: 'image/jpeg'),
        new OA\Property(property: 'is_gambar', type: 'boolean', example: true),
        new OA\Property(property: 'ukuran', type: 'integer', example: 245678),
        new OA\Property(property: 'ukuran_label', type: 'string', example: '240 KB'),
        new OA\Property(property: 'keterangan', type: 'string', nullable: true),
        new OA\Property(property: 'url_berkas', type: 'string', example: '/api/lampiran/1/berkas'),
    ],
    type: 'object',
)]
#[OA\Schema(
    schema: 'ActivityLog',
    description: 'Satu baris jejak aktivitas. Nama dan peran disalin saat kejadian, sehingga jejak lama tetap benar walau akunnya berubah kemudian.',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'user_id', type: 'integer', nullable: true, example: 2),
        new OA\Property(property: 'user_nama', type: 'string', nullable: true, example: 'Sinta Pratiwi'),
        new OA\Property(property: 'user_role', type: 'string', nullable: true, example: 'petugas'),
        new OA\Property(property: 'aksi', type: 'string', example: 'Menambah item bahan baku'),
        new OA\Property(property: 'modul', type: 'string', enum: ['auth', 'kegiatan', 'bahan_baku', 'kas', 'lampiran', 'pengguna', 'pengaturan', 'lain']),
        new OA\Property(property: 'modul_label', type: 'string', example: 'Bahan Baku'),
        new OA\Property(property: 'subjek_tipe', type: 'string', nullable: true, example: 'BahanBakuItem'),
        new OA\Property(property: 'subjek_id', type: 'integer', nullable: true),
        new OA\Property(property: 'subjek_label', type: 'string', nullable: true, example: 'Besi beton 12mm'),
        new OA\Property(property: 'metode', type: 'string', example: 'POST'),
        new OA\Property(property: 'path', type: 'string', example: '/api/kegiatan/1/bahan-baku'),
        new OA\Property(property: 'status', type: 'integer', example: 201),
        new OA\Property(property: 'berhasil', type: 'boolean', example: true),
        new OA\Property(property: 'payload', description: 'Isi permintaan tanpa password/token/berkas', type: 'object', nullable: true),
        new OA\Property(property: 'ip', type: 'string', nullable: true),
        new OA\Property(property: 'durasi_ms', type: 'integer', nullable: true),
        new OA\Property(property: 'waktu', type: 'string', example: '03 Sep 2026, 06:44:09'),
    ],
    type: 'object',
)]
final class Schemas
{
    //
}
