<?php

declare(strict_types=1);

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Isi halaman akar server.
 *
 * Halaman ini sengaja tidak bergantung pada satu pun berkas luar. Itu bukan
 * soal selera: server API bisa berada di jaringan tanpa akses internet, dan
 * halaman yang menunggu gaya dari CDN akan tampil rusak justru saat dipakai
 * memastikan servernya hidup.
 */
class HalamanStatusTest extends TestCase
{
    private function isi(): string
    {
        return $this->get('/')->assertOk()->getContent();
    }

    #[Test]
    public function tidak_memuat_satu_pun_berkas_luar(): void
    {
        $isi = $this->isi();

        // Yang dicari atribut pemuat berkas (src/href) ke alamat http.
        // Ikon favicon berupa data URI, jadi tidak ikut terhitung.
        $this->assertSame(
            0,
            preg_match_all('/(?:src|href)="https?:\/\//i', $isi),
            'halaman status tidak boleh mengunduh apa pun dari luar',
        );
    }

    #[Test]
    public function dinosaurus_piksel_ada_dan_bisa_dicapai_papan_ketik(): void
    {
        $isi = $this->isi();

        $this->assertStringContainsString('class="dino"', $isi);

        // <button>, bukan <div>: yang bisa diketuk harus bisa dicapai lewat
        // papan ketik juga, dan button memberi itu tanpa kode tambahan.
        $this->assertMatchesRegularExpression('/<button[^>]*class="dino"/', $isi);
        $this->assertStringContainsString('aria-label="Dinosaurus piksel', $isi);
    }

    #[Test]
    public function dinosaurus_digambar_sendiri_bukan_gambar_unduhan(): void
    {
        $isi = $this->isi();

        // Kotak-kotak SVG, bukan <img>. Selain tidak meminjam aset milik
        // orang lain, ini juga tidak menambah satu pun permintaan berkas.
        $this->assertGreaterThan(10, substr_count($isi, '<rect'));
        $this->assertStringNotContainsString('<img', $isi);
    }

    #[Test]
    public function dua_pose_kaki_ada_supaya_terlihat_berlari(): void
    {
        $isi = $this->isi();

        $this->assertStringContainsString('kaki-1', $isi);
        $this->assertStringContainsString('kaki-2', $isi);
        $this->assertStringContainsString('@keyframes langkah', $isi);

        // Tanpa garis tanah yang bergeser, dinonya hanya menggerakkan kaki
        // di tempat dan tidak terbaca sebagai berlari.
        $this->assertStringContainsString('@keyframes geser', $isi);
    }

    #[Test]
    public function bisa_melompat(): void
    {
        $this->assertStringContainsString('@keyframes lompat', $this->isi());
    }

    #[Test]
    public function gerak_berulang_dihentikan_bila_pengguna_memintanya(): void
    {
        // Animasi tanpa henti memicu rasa mual pada sebagian orang, dan
        // sistem operasi sudah punya setelan untuk menyatakannya.
        $this->assertStringContainsString('prefers-reduced-motion', $this->isi());
    }

    #[Test]
    public function tidak_ada_lagi_nama_aplikasi_dan_paragraf_pengantar(): void
    {
        $isi = $this->isi();
        $badan = substr($isi, (int) strpos($isi, '<body'));

        $this->assertStringNotContainsString('Ini alamat API', $badan);

        // Diperiksa pada BADAN halaman saja. Nama aplikasi tetap ada di
        // <title> dan memang harus: itu yang membedakan tab ini dari tab
        // lain saat beberapa server dibuka bersamaan.
        $this->assertStringNotContainsString(config('app.name'), $badan);
        $this->assertStringContainsString(config('app.name'), $isi);
    }

    #[Test]
    public function halaman_tetap_ringan(): void
    {
        // Seluruh gaya dan skripnya sebaris, jadi ukurannya perlu dijaga.
        // 32 KB masih jauh lebih ringan daripada satu berkas CSS kerangka
        // kerja mana pun, dan tidak butuh permintaan kedua.
        $this->assertLessThan(32 * 1024, strlen($this->isi()));
    }
}
