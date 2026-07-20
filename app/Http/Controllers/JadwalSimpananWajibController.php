<?php

namespace App\Http\Controllers;

use App\Models\Anggota;
use App\Models\JadwalSimpananWajib;
use App\Services\SimpananWajibService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class JadwalSimpananWajibController extends Controller
{
    public function index(Request $request, SimpananWajibService $service): View
    {
        $filters = $request->validate([
            'anggota_id' => ['nullable', 'exists:anggota,id'],
            'status' => ['nullable', 'in:outstanding,reserved,settled'],
            'periode_mulai' => ['nullable', 'date'],
            'periode_selesai' => ['nullable', 'date', 'after_or_equal:periode_mulai'],
        ]);

        $data = $service->outstandingSummary($filters);
        $jadwal = $data['query']
            ->orderByDesc('periode')
            ->orderBy('anggota_id')
            ->paginate(15)
            ->withQueryString();

        return view('pages.simpanan-wajib.index', [
            'jadwal' => $jadwal,
            'summary' => $data['summary'],
            'filters' => $filters,
            'anggotaOptions' => Anggota::query()->with('karyawan')->orderBy('nomor_anggota')->get(),
            'statusOptions' => [
                JadwalSimpananWajib::STATUS_OUTSTANDING => 'Tunggakan/Belum Dialokasikan',
                JadwalSimpananWajib::STATUS_RESERVED => 'Sudah Dialokasikan',
                JadwalSimpananWajib::STATUS_SETTLED => 'Sudah Dibayar',
            ],
        ]);
    }
}
