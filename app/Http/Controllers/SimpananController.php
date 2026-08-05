<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSimpananRequest;
use App\Models\Anggota;
use App\Models\DompetKoperasi;
use App\Models\JenisSimpanan;
use App\Models\JadwalSimpananWajib;
use App\Models\Karyawan;
use App\Models\Simpanan;
use App\Services\SimpananManasukaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class SimpananController extends Controller
{
    public function index(Request $request, SimpananManasukaService $service): View
    {
        $filters = $request->validate([
            'anggota_id' => ['nullable', 'integer', 'exists:anggota,id'],
            'kategori' => ['nullable', Rule::in(array_keys(JenisSimpanan::KATEGORI))],
            'jenis_transaksi' => ['nullable', Rule::in([Simpanan::JENIS_SETORAN, Simpanan::JENIS_PENARIKAN])],
            'status' => ['nullable', Rule::in([
                Simpanan::STATUS_PENDING_PAYROLL,
                Simpanan::STATUS_ALLOCATED,
                Simpanan::STATUS_SETTLED,
                Simpanan::STATUS_OUTSTANDING_CASH,
                Simpanan::STATUS_SETTLED_CASH,
                Simpanan::STATUS_REVERSED,
                Simpanan::STATUS_REVERSED_DUE_TO_EXIT,
                Simpanan::STATUS_SETTLED_OFFSET,
            ])],
            'metode_pembayaran' => ['nullable', Rule::in([
                Simpanan::METODE_TUNAI,
                Simpanan::METODE_TRANSFER_BANK,
                Simpanan::METODE_POTONG_GAJI,
            ])],
            'tanggal_mulai' => ['nullable', 'date'],
            'tanggal_selesai' => ['nullable', 'date', 'after_or_equal:tanggal_mulai'],
        ]);

        $simpanan = Simpanan::query()
            ->with(['anggota.karyawan', 'karyawan', 'jenisSimpanan', 'ledger', 'mutasiKas.dompet', 'dompet', 'jurnal', 'reversal'])
            ->when($filters['anggota_id'] ?? null, fn ($query, $anggotaId) => $query->where('anggota_id', $anggotaId))
            ->when($filters['kategori'] ?? null, fn ($query, $kategori) => $query->whereHas('jenisSimpanan', fn ($jenisQuery) => $jenisQuery->where('kategori', $kategori)))
            ->when($filters['jenis_transaksi'] ?? null, fn ($query, $jenis) => $query->where('jenis_transaksi', $jenis))
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($filters['metode_pembayaran'] ?? null, fn ($query, $metode) => $query->where('metode_pembayaran', $metode))
            ->when($filters['tanggal_mulai'] ?? null, fn ($query, $date) => $query->whereDate('tanggal', '>=', $date))
            ->when($filters['tanggal_selesai'] ?? null, fn ($query, $date) => $query->whereDate('tanggal', '<=', $date))
            ->orderByDesc('tanggal')
            ->orderByDesc('id')
            ->paginate(12)
            ->withQueryString();

        $summary = $service->summary($filters);

        $anggota = Anggota::query()
            ->with('karyawan')
            ->orderBy('nomor_anggota')
            ->get();

        return view('pages.simpanan.index', [
            'simpanan' => $simpanan,
            'anggota' => $anggota,
            'filters' => $filters,
            'summary' => $summary,
            'kategoriOptions' => JenisSimpanan::KATEGORI,
            'jenisTransaksiOptions' => [
                Simpanan::JENIS_SETORAN => 'Setoran',
                Simpanan::JENIS_PENARIKAN => 'Penarikan',
            ],
            'statusOptions' => [
                Simpanan::STATUS_PENDING_PAYROLL => 'Pending Payroll',
                Simpanan::STATUS_ALLOCATED => 'Dialokasikan',
                Simpanan::STATUS_SETTLED => 'Posted',
                Simpanan::STATUS_OUTSTANDING_CASH => 'Outstanding Tunai',
                Simpanan::STATUS_SETTLED_CASH => 'Lunas Tunai',
                Simpanan::STATUS_REVERSED => 'Dikoreksi',
                Simpanan::STATUS_REVERSED_DUE_TO_EXIT => 'Dikoreksi Keluar Anggota',
                Simpanan::STATUS_SETTLED_OFFSET => 'Diselesaikan Offset',
            ],
            'metodeOptions' => [
                Simpanan::METODE_TUNAI => 'Tunai',
                Simpanan::METODE_TRANSFER_BANK => 'Transfer Bank',
                Simpanan::METODE_POTONG_GAJI => 'Potong Gaji',
            ],
        ]);
    }

    public function create(): View
    {
        $anggota = Anggota::query()
            ->with(['karyawan', 'siklusAktif'])
            ->aktif()
            ->whereHas('karyawan', fn ($query) => $query->where('status_kerja', Karyawan::STATUS_AKTIF))
            ->whereHas('siklusAktif')
            ->orderBy('nomor_anggota')
            ->get();

        $jenisManasuka = JenisSimpanan::query()
            ->aktif()
            ->where('kategori', JenisSimpanan::KATEGORI_MANASUKA)
            ->first();

        $dompet = DompetKoperasi::query()
            ->with('akun')
            ->whereHas('akun', fn ($query) => $query
                ->where('is_aktif', true)
                ->where('kategori', 'aset')
                ->where('posisi_saldo', 'debit'))
            ->orderBy('nama_dompet')
            ->get();

        return view('pages.simpanan.create', [
            'anggota' => $anggota,
            'jenisManasuka' => $jenisManasuka,
            'dompet' => $dompet,
        ]);
    }

    public function store(StoreSimpananRequest $request, SimpananManasukaService $service): RedirectResponse
    {
        try {
            $data = $request->validated();
            $jenis = JenisSimpanan::query()->findOrFail((int) $data['jenis_simpanan_id']);
            if ($jenis->kode === JenisSimpanan::KODE_SIMPANAN_WAJIB) {
                $wajibService->settleDirect(
                    Anggota::query()->findOrFail((int) $data['anggota_id']),
                    $data,
                    (int) $request->user()?->id
                );
            } else {
                $service->create($data, $request->user()?->id);
            }

            return redirect()
                ->route('simpanan.index')
                ->with('success', 'Transaksi Simpanan berhasil diposting.');
        } catch (ValidationException $exception) {
            return back()
                ->withErrors($exception->errors())
                ->withInput();
        } catch (\Throwable $exception) {
            return back()
                ->withErrors(['simpanan' => $exception->getMessage()])
                ->withInput();
        }
    }

    public function saldoManasuka(Anggota $anggota, SimpananManasukaService $service): JsonResponse
    {
        $anggota->loadMissing('karyawan');

        if ($anggota->status !== Anggota::STATUS_AKTIF || $anggota->karyawan?->status_kerja !== Karyawan::STATUS_AKTIF) {
            return response()->json([
                'message' => 'Anggota nonaktif atau Karyawan berhenti tidak dapat melakukan transaksi Simpanan Manasuka langsung.',
            ], 422);
        }

        $saldo = $service->saldoTersedia($anggota);

        return response()->json([
            'saldo' => $saldo,
            'saldo_formatted' => 'Rp ' . number_format($saldo, 0, ',', '.'),
        ]);
    }
}
