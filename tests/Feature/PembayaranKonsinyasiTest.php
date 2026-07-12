<?php

namespace Tests\Feature;

use App\Models\DetailPenjualan;
use App\Models\DompetKoperasi;
use App\Models\KategoriProduk;
use App\Models\Karyawan;
use App\Models\PembayaranKonsinyasi;
use App\Models\Penjualan;
use App\Models\Produk;
use App\Models\Reseller;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PembayaranKonsinyasiTest extends TestCase
{
    use RefreshDatabase;

    public function test_pembayaran_konsinyasi_page_can_be_rendered(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'kasir']));
        $this->seedMinimalKonsinyasiData();

        $this->get(route('pembayaran-konsinyasi.index'))
            ->assertOk()
            ->assertSee('Pembayaran Konsinyasi');
    }

    public function test_pembayaran_konsinyasi_creates_cash_outflow_and_marks_debt_as_paid(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'kasir']));
        $data = $this->seedMinimalKonsinyasiData();

        $response = $this->post(route('pembayaran-konsinyasi.store'), [
            'reseller_id' => $data['reseller']->id,
            'dompet_id' => $data['dompet']->id,
            'tanggal_bayar' => '2026-03-11',
            'keterangan' => 'Pembayaran reseller test',
        ]);

        $response->assertRedirect(route('pembayaran-konsinyasi.index', ['reseller_id' => $data['reseller']->id]));

        $this->assertDatabaseHas('pembayaran_konsinyasi', [
            'reseller_id' => $data['reseller']->id,
            'dompet_id' => $data['dompet']->id,
            'total_qty' => 3,
            'total_jual' => 45000,
            'total_bayar' => 30000,
            'total_margin' => 15000,
        ]);

        $payment = PembayaranKonsinyasi::query()->firstOrFail();

        $this->assertDatabaseHas('hutang_reseller', [
            'reseller_id' => $data['reseller']->id,
            'detail_penjualan_id' => $data['detailPenjualan']->id,
            'status' => 'sudah_dibayar',
            'pembayaran_konsinyasi_id' => $payment->id,
        ]);

        $this->assertDatabaseHas('mutasi_kas', [
            'dompet_id' => $data['dompet']->id,
            'tipe' => 'keluar',
            'jumlah' => 30000,
            'referensi_tipe' => PembayaranKonsinyasi::class,
            'referensi_id' => $payment->id,
            'tanggal' => '2026-03-11',
        ]);

        $this->assertSame(70000.0, (float) $data['dompet']->fresh()->saldo);
    }

    private function seedMinimalKonsinyasiData(): array
    {
        $dompet = DompetKoperasi::create([
            'nama_dompet' => 'Kas Utama',
            'saldo' => 100000,
        ]);

        $reseller = Reseller::create([
            'nama_reseller' => 'Reseller Konsinyasi',
            'telepon' => '08123456789',
            'alamat' => 'Jl. Reseller',
        ]);

        $kategori = KategoriProduk::create([
            'nama_kategori' => 'Snack',
            'deskripsi' => 'Test kategori',
        ]);

        $produk = Produk::create([
            'nama_produk' => 'Keripik Singkong',
            'kategori_id' => $kategori->id,
            'harga_beli' => 0,
            'harga_jual' => 15000,
            'stok' => 7,
            'konsinyasi' => true,
            'reseller_id' => $reseller->id,
            'harga_setor' => 10000,
        ]);

        $karyawan = Karyawan::create([
            'nama' => 'Bita Tester',
            'email' => 'bita.tester@example.test',
            'telepon' => '08111',
            'jabatan' => 'Anggota',
        ]);

        $penjualan = Penjualan::create([
            'kode_transaksi' => 'PJL-777',
            'karyawan_id' => $karyawan->id,
            'total_harga' => 45000,
            'diskon' => 0,
            'grand_total' => 45000,
        ]);

        $detailPenjualan = DetailPenjualan::create([
            'penjualan_id' => $penjualan->id,
            'produk_id' => $produk->id,
            'qty' => 3,
            'harga' => 15000,
            'subtotal' => 45000,
            'konsinyasi' => true,
            'reseller_id' => $reseller->id,
            'harga_setor' => 10000,
            'subtotal_setor' => 30000,
        ]);

        return compact('detailPenjualan', 'dompet', 'kategori', 'karyawan', 'penjualan', 'produk', 'reseller');
    }
}
