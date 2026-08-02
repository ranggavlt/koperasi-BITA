<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreAnggotaRequest;
use App\Http\Requests\UpdateAnggotaRequest;
use App\Models\Anggota;
use App\Models\Karyawan;
use App\Services\ManasukaRutinService;
use App\Services\MasterDataKoperasiService;
use App\Services\PayrollPolicyService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AnggotaController extends Controller
{
    public function index()
    {
        return $this->renderIndex();
    }

    public function store(StoreAnggotaRequest $request, MasterDataKoperasiService $service)
    {
        $service->createAnggota($request->validated());

        return redirect()->route('anggota.index')
            ->with('success', 'Karyawan berhasil didaftarkan sebagai Anggota aktif.');
    }

    public function edit(Anggota $anggota)
    {
        return $this->renderIndex($anggota->load('karyawan'));
    }

    public function update(
        UpdateAnggotaRequest $request,
        Anggota $anggota,
        MasterDataKoperasiService $service
    ) {
        $service->updateAnggota($anggota, $request->validated());

        return redirect()->route('anggota.index')
            ->with('success', 'Data Anggota berhasil diperbarui.');
    }

    public function deactivate(Anggota $anggota, MasterDataKoperasiService $service)
    {
        $service->deactivateAnggota($anggota);

        return redirect()->route('anggota.index')
            ->with('success', 'Anggota dan jabatan Pengurus aktif terkait berhasil dinonaktifkan.');
    }

    public function activate(Anggota $anggota, MasterDataKoperasiService $service)
    {
        $service->activateAnggota($anggota);

        return redirect()->route('anggota.index')
            ->with('success', 'Anggota berhasil diaktifkan kembali.');
    }

    public function destroy(Anggota $anggota, MasterDataKoperasiService $service)
    {
        $service->deleteUnusedAnggota($anggota);

        return redirect()->route('anggota.index')
            ->with('success', 'Data Anggota yang belum digunakan berhasil dihapus.');
    }

    public function updatePayrollPolicy(Request $request, Anggota $anggota, PayrollPolicyService $service)
    {
        $data = $request->validate([
            'limit_override_nominal' => ['nullable', 'integer', 'min:0'],
            'kredit_waserba_aktif' => ['required', 'boolean'],
            'alasan' => ['required', 'string', 'min:5', 'max:1000'],
        ]);
        $service->scheduleMemberSetting($anggota, $data['limit_override_nominal'], (bool) $data['kredit_waserba_aktif'], $data['alasan'], $request->user()->id);

        return redirect()->route('anggota.edit', $anggota)->with('success', 'Perubahan payroll dijadwalkan mulai periode bulan berikutnya dan akan persisten.');
    }

    public function resetPayrollPolicy(Request $request, Anggota $anggota, PayrollPolicyService $service)
    {
        $data = $request->validate(['kredit_waserba_aktif' => ['required', 'boolean'], 'alasan' => ['required', 'string', 'min:5', 'max:1000']]);
        $service->scheduleResetToGeneral($anggota, (bool) $data['kredit_waserba_aktif'], $data['alasan'], $request->user()->id);

        return redirect()->route('anggota.edit', $anggota)->with('success', 'Reset ke limit umum dijadwalkan mulai periode bulan berikutnya.');
    }

    private function renderIndex(?Anggota $data = null)
    {
        if ($data) {
            $data->loadMissing(['karyawan', 'siklusAktif']);
        }

        $anggota = Anggota::query()
            ->with(['karyawan', 'pengurusAktif'])
            ->latest('id')
            ->paginate(10);

        $karyawanTersedia = Karyawan::query()
            ->aktif()
            ->whereDoesntHave('anggota')
            ->orderBy('nama')
            ->get();

        $manasukaConfig = $data
            ? app(ManasukaRutinService::class)->latestScheduled($data, $data->siklusAktif?->id)
            : null;
        $manasukaEffectivePeriod = app(ManasukaRutinService::class)->nextEffectivePeriod();
        $manasukaIdempotencyKey = (string) Str::uuid();
        $payrollNextPeriod = now()->addMonthNoOverflow()->startOfMonth();
        $payrollResolved = $data ? app(PayrollPolicyService::class)->resolveFor($data, $payrollNextPeriod) : null;

        return view('pages.anggota.index', compact(
            'anggota',
            'karyawanTersedia',
            'data',
            'manasukaConfig',
            'manasukaEffectivePeriod',
            'manasukaIdempotencyKey',
            'payrollNextPeriod',
            'payrollResolved'
        ));
    }
}
