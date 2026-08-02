<?php

namespace App\Http\Controllers;

use App\Models\Anggota;
use App\Models\JadwalSimpananWajib;
use Illuminate\Http\Request;
use Illuminate\View\View;

class JadwalSimpananWajibController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'periode_mulai' => ['nullable', 'date_format:Y-m'],
            'periode_selesai' => ['nullable', 'date_format:Y-m', 'after_or_equal:periode_mulai'],
            'anggota_id' => ['nullable', 'integer', 'exists:anggota,id'],
            'status' => ['nullable', 'in:' . implode(',', array_keys($this->statusOptions()))],
        ], [
            'periode_selesai.after_or_equal' => 'Periode selesai tidak boleh sebelum periode mulai.',
        ]);

        $query = JadwalSimpananWajib::query()
            ->with([
                'anggota.karyawan',
                'simpanan.ledger.limit.periodePotongGaji',
                'activeLedger.limit.periodePotongGaji',
            ])
            ->when($filters['periode_mulai'] ?? null, function ($query, string $periode): void {
                $query->whereDate('periode', '>=', $periode . '-01');
            })
            ->when($filters['periode_selesai'] ?? null, function ($query, string $periode): void {
                $query->whereDate('periode', '<=', $periode . '-01');
            })
            ->when($filters['anggota_id'] ?? null, function ($query, int $anggotaId): void {
                $query->where('anggota_id', $anggotaId);
            })
            ->when($filters['status'] ?? null, function ($query, string $status): void {
                $query->where('status', $status);
            });

        $summaryQuery = clone $query;
        $summary = [
            'total_tagihan' => (float) (clone $summaryQuery)->sum('nominal_snapshot'),
            'sudah_dialokasikan' => (float) (clone $summaryQuery)->where('status', JadwalSimpananWajib::STATUS_RESERVED)->sum('nominal_snapshot'),
            'sudah_dibayar' => (float) (clone $summaryQuery)->where('status', JadwalSimpananWajib::STATUS_SETTLED)->sum('nominal_snapshot'),
            'tunggakan' => (float) (clone $summaryQuery)->where('status', JadwalSimpananWajib::STATUS_OUTSTANDING)->sum('nominal_snapshot'),
        ];

        return view('pages.simpanan-wajib.index', [
            'jadwal' => $query
                ->latest('periode')
                ->latest('id')
                ->paginate(15)
                ->withQueryString(),
            'anggotaOptions' => Anggota::query()
                ->with('karyawan')
                ->orderBy('nomor_anggota')
                ->get(),
            'statusOptions' => $this->statusOptions(),
            'filters' => $filters,
            'summary' => $summary,
        ]);
    }

    private function statusOptions(): array
    {
        return [
            JadwalSimpananWajib::STATUS_OUTSTANDING => 'Tunggakan/Belum Dialokasikan',
            JadwalSimpananWajib::STATUS_RESERVED => 'Sudah Dialokasikan',
            JadwalSimpananWajib::STATUS_SETTLED => 'Sudah Dibayar Payroll',
            JadwalSimpananWajib::STATUS_CANCELLED_EXIT => 'Dibatalkan karena Keanggotaan Berakhir',
        ];
    }
}
