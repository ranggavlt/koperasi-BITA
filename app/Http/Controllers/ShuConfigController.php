<?php
namespace App\Http\Controllers;
use App\Models\ShuConfig;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
class ShuConfigController extends Controller
{
 public function index(){return view('pages.shu-config.index',['configs'=>ShuConfig::query()->with('creator')->latest('versi')->paginate(15)]);}
 public function store(Request $request){$validator=Validator::make($request->all(),['berlaku_mulai'=>'required|date','dasar_keputusan'=>'required|string|max:255','persen_dana_cadangan'=>'required|numeric|min:0|max:100','persen_shu_anggota'=>'required|numeric|min:0|max:100','persen_pengurus'=>'required|numeric|min:0|max:100','persen_pengawas'=>'required|numeric|min:0|max:100','persen_pembina'=>'required|numeric|min:0|max:100','persen_dana_sosial'=>'required|numeric|min:0|max:100','persen_dana_pendidikan'=>'required|numeric|min:0|max:100','persen_jasa_modal'=>'required|numeric|min:0|max:100','persen_jasa_usaha'=>'required|numeric|min:0|max:100']);$validator->after(function($v)use($request){$category=collect(['persen_dana_cadangan','persen_shu_anggota','persen_pengurus','persen_pengawas','persen_pembina','persen_dana_sosial','persen_dana_pendidikan'])->sum(fn($key)=>(float)$request->input($key));$member=(float)$request->input('persen_jasa_modal')+(float)$request->input('persen_jasa_usaha');if(abs($category-100)>.001)$v->errors()->add('persen_dana_cadangan','Total pembagian SHU harus tepat 100%.');if(abs($member-100)>.001)$v->errors()->add('persen_jasa_modal','Total porsi jasa Anggota harus tepat 100%.');});$data=$validator->validate();DB::transaction(function()use($data,$request){$version=(int)ShuConfig::query()->lockForUpdate()->max('versi')+1;ShuConfig::query()->create([...$data,'versi'=>$version,'created_by'=>$request->user()->id]);});return back()->with('success','Versi Pengaturan SHU berhasil disimpan.');}
}
