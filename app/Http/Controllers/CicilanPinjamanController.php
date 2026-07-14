<?php

namespace App\Http\Controllers;

use App\Models\DompetKoperasi;
use App\Models\JadwalCicilanPinjaman;

class CicilanPinjamanController extends Controller
{
    public function index()
    {
        $jadwalCicilan = JadwalCicilanPinjaman::query()
            ->with(['pinjaman.anggota.karyawan', 'cicilanPembayaran.dompet'])
            ->latest()
            ->paginate(10);

        $dompetRefund = DompetKoperasi::query()->with('akun')->orderBy('nama_dompet')->get();

        return view('pages.cicilan-pinjaman.index', compact('jadwalCicilan', 'dompetRefund'));
    }
}
