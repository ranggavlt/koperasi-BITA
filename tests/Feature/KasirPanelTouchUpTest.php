<?php

namespace Tests\Feature;

use App\Models\KategoriProduk;
use App\Models\Karyawan;
use App\Models\Produk;
use App\Models\User;
use Database\Seeders\AkunSeeder;
use Database\Seeders\KoperasiDummySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class KasirPanelTouchUpTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_redirect_berdasarkan_role_tanpa_mengaktifkan_register(): void
    {
        $admin = $this->user('admin', 'admin-login@kbsm.test');
        $kasir = $this->user('kasir', 'kasir-login@kbsm.test');
        $karyawan = Karyawan::factory()->create(['email' => 'karyawan-login@kbsm.test']);
        $employee = $this->user('karyawan', 'karyawan-login-user@kbsm.test', [
            'name' => $karyawan->nama,
            'karyawan_id' => $karyawan->id,
        ]);

        $this->post(route('login.submit'), [
            'email' => $kasir->email,
            'password' => 'Kbsm12345!',
        ])->assertRedirect(route('penjualan.index'));
        $this->post(route('logout'));

        $this->post(route('login.submit'), [
            'email' => $admin->email,
            'password' => 'Kbsm12345!',
        ])->assertRedirect(route('pages.dashboard'));
        $this->post(route('logout'));

        $this->post(route('login.submit'), [
            'email' => $employee->email,
            'password' => 'Kbsm12345!',
        ])->assertRedirect(route('pages.dashboard'));

        $this->assertFalse(Route::has('register'));
        $this->get('/register')->assertNotFound();
    }

    public function test_layout_tidak_memuat_teks_literal_livewire_dan_akses_role_tetap(): void
    {
        $admin = $this->user('admin', 'admin-layout@kbsm.test');
        $kasir = $this->user('kasir', 'kasir-layout@kbsm.test');

        $this->actingAs($admin)
            ->get(route('pages.dashboard'))
            ->assertOk()
            ->assertDontSee('@livewireStyles', false)
            ->assertDontSee('@livewireScripts', false);

        $this->actingAs($kasir)
            ->get(route('penjualan.index'))
            ->assertOk()
            ->assertDontSee('@livewireStyles', false)
            ->assertDontSee('@livewireScripts', false);

        $this->actingAs($admin)->get(route('users.index'))->assertOk();
        $this->actingAs($kasir)->get(route('users.index'))->assertForbidden();
    }

    public function test_produk_demo_memiliki_mapping_gambar_lokal_deterministik(): void
    {
        $this->seed(AkunSeeder::class);
        $this->seed(KoperasiDummySeeder::class);

        foreach ($this->expectedDemoPhotos() as $name => $path) {
            $produk = Produk::query()->where('nama_produk', $name)->firstOrFail();

            $this->assertSame($path, $produk->foto);
            $this->assertFileExists(public_path($path));
            $this->assertStringEndsWith('/' . $path, $produk->foto_url);
        }
    }

    public function test_resolver_foto_menangani_upload_manual_kosong_dan_file_hilang(): void
    {
        Storage::fake('public');

        Storage::disk('public')->put('produk_foto/manual.svg', '<svg xmlns="http://www.w3.org/2000/svg"></svg>');

        $manual = new Produk(['foto' => 'produk_foto/manual.svg']);
        $empty = new Produk(['foto' => null]);
        $missing = new Produk(['foto' => 'produk_foto/hilang.jpg']);

        $this->assertSame(Storage::disk('public')->url('produk_foto/manual.svg'), $manual->foto_url);
        $this->assertStringEndsWith('/' . Produk::FALLBACK_PHOTO_PATH, $empty->foto_url);
        $this->assertStringEndsWith('/' . Produk::FALLBACK_PHOTO_PATH, $missing->foto_url);
    }

    public function test_master_produk_dan_pos_merender_url_foto_dari_resolver(): void
    {
        $kategori = KategoriProduk::query()->create([
            'nama_kategori' => 'Sembako Test',
            'deskripsi' => 'Kategori test foto produk.',
        ]);

        $produk = Produk::query()->create([
            'nama_produk' => 'Beras Premium BITA 5kg',
            'foto' => Produk::DEMO_PHOTO_PREFIX . 'beras-premium.svg',
            'kategori_id' => $kategori->id,
            'harga_beli' => 69000,
            'harga_jual' => 76000,
            'stok' => 10,
            'konsinyasi' => false,
            'reseller_id' => null,
            'harga_setor' => 0,
        ]);

        $this->actingAs($this->user('admin', 'admin-produk-foto@kbsm.test'))
            ->get(route('produk.index'))
            ->assertOk()
            ->assertSee($produk->foto_url, false)
            ->assertSee('Foto Beras Premium BITA 5kg', false);

        $this->actingAs($this->user('kasir', 'kasir-produk-foto@kbsm.test'))
            ->get(route('penjualan.index'))
            ->assertOk()
            ->assertSee($produk->foto_url, false)
            ->assertSee('Foto Beras Premium BITA 5kg', false);
    }

    public function test_upload_foto_produk_menolak_tipe_dan_ukuran_tidak_valid(): void
    {
        $kategori = KategoriProduk::query()->create([
            'nama_kategori' => 'Upload Test',
            'deskripsi' => 'Kategori test upload.',
        ]);

        $admin = $this->user('admin', 'admin-upload@kbsm.test');

        $basePayload = [
            'nama_produk' => 'Produk Upload Invalid',
            'kategori_id' => $kategori->id,
            'harga_beli' => 1000,
            'harga_jual' => 1500,
            'stok' => 5,
            'konsinyasi' => '0',
            'reseller_id' => null,
            'harga_setor' => 0,
        ];

        $this->actingAs($admin)
            ->post(route('produk.store'), $basePayload + [
                'foto' => UploadedFile::fake()->create('payload.txt', 1, 'text/plain'),
            ])
            ->assertSessionHasErrors('foto');

        $this->actingAs($admin)
            ->post(route('produk.store'), $basePayload + [
                'foto' => UploadedFile::fake()->create('terlalu-besar.jpg', 2049, 'image/jpeg'),
            ])
            ->assertSessionHasErrors('foto');

        $this->assertSame(0, Produk::query()->where('nama_produk', 'Produk Upload Invalid')->count());
    }

    private function user(string $role, string $email, array $overrides = []): User
    {
        return User::factory()->create($overrides + [
            'name' => ucfirst($role) . ' Touch Up',
            'email' => $email,
            'password' => Hash::make('Kbsm12345!'),
            'role' => $role,
            'is_active' => true,
            'must_change_password' => false,
        ]);
    }

    /**
     * @return array<string, string>
     */
    private function expectedDemoPhotos(): array
    {
        return [
            'Beras Premium BITA 5kg' => Produk::DEMO_PHOTO_PREFIX . 'beras-premium.svg',
            'Gula Pasir Kristal 1kg' => Produk::DEMO_PHOTO_PREFIX . 'gula-pasir.svg',
            'Minyak Goreng Hemat 1L' => Produk::DEMO_PHOTO_PREFIX . 'minyak-goreng.svg',
            'Air Mineral 600ml' => Produk::DEMO_PHOTO_PREFIX . 'air-mineral.svg',
            'Kopi Mix 10 Sachet' => Produk::DEMO_PHOTO_PREFIX . 'kopi-mix.svg',
            'Biskuit Cokelat Keluarga' => Produk::DEMO_PHOTO_PREFIX . 'biskuit-cokelat.svg',
            'Buku Tulis 38 Lembar' => Produk::DEMO_PHOTO_PREFIX . 'buku-tulis.svg',
            'Sabun Cuci Piring 800ml' => Produk::DEMO_PHOTO_PREFIX . 'sabun-cuci-piring.svg',
            'Brownies Kukus Cokelat' => Produk::DEMO_PHOTO_PREFIX . 'brownies-cokelat.svg',
            'Roti Sisir Keju' => Produk::DEMO_PHOTO_PREFIX . 'roti-sisir-keju.svg',
            'Keripik Pisang Original' => Produk::DEMO_PHOTO_PREFIX . 'keripik-pisang.svg',
            'Sambal Botol Rumahan 200ml' => Produk::DEMO_PHOTO_PREFIX . 'sambal-botol.svg',
        ];
    }
}
