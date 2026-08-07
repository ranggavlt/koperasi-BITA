<?php

namespace App\Http\Controllers;

use App\Models\ShuConfig;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class ShuConfigController extends Controller
{
    public function index()
    {
        return view('pages.shu-config.index', ['configs' => ShuConfig::query()->with('creator')->latest('versi')->paginate(15)]);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'berlaku_mulai' => ['required', 'date'],
            'dasar_keputusan' => ['required', 'string', 'max:255'],
            'persen_dana_cadangan' => ['required', 'numeric', Rule::in([30, '30', '30.00'])],
            'persen_shu_anggota' => ['required', 'numeric', Rule::in([40, '40', '40.00'])],
            'persen_pengurus' => ['required', 'numeric', Rule::in([10, '10', '10.00'])],
            'persen_pengawas' => ['required', 'numeric', Rule::in([5, '5', '5.00'])],
            'persen_pembina' => ['required', 'numeric', Rule::in([5, '5', '5.00'])],
            'persen_dana_sosial' => ['required', 'numeric', Rule::in([10, '10', '10.00'])],
            'persen_jasa_modal' => ['required', 'numeric', 'min:0', 'max:100'],
            'persen_jasa_usaha' => ['required', 'numeric', 'min:0', 'max:100'],
        ], ['in' => 'Persentase kategori utama sudah ditetapkan oleh kebijakan final dan tidak dapat diubah.']);
        $validator->after(function ($validator) use ($request): void {
            if (abs((float) $request->input('persen_jasa_modal') + (float) $request->input('persen_jasa_usaha') - 100) > 0.001) {
                $validator->errors()->add('persen_jasa_modal', 'Total Jasa Modal dan Jasa Usaha harus tepat 100%.');
            }
        });
        $data = $validator->validate();
        $data['persen_dana_pendidikan'] = 0;
        DB::transaction(function () use ($data, $request): void {
            $version = (int) ShuConfig::query()->lockForUpdate()->max('versi') + 1;
            ShuConfig::query()->create([...$data, 'versi' => $version, 'created_by' => $request->user()->id]);
        });
        return back()->with('success', 'Versi Pengaturan SHU berhasil disimpan.');
    }
}
