@extends('layout.main')
@section('content')
<div class="kbsm-business-page">
    @if(session('success'))<div class="kbsm-business-alert kbsm-business-alert--success">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="kbsm-business-alert kbsm-business-alert--danger"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    <header class="kbsm-business-header"><div><p class="kbsm-business-eyebrow">SHU & Dana Sosial</p><h1 class="kbsm-business-title">Struktur Koperasi</h1><p class="kbsm-business-subtitle">Master efektif Pengurus, Pengawas, dan Pembina. Anggota maupun penerima eksternal dapat dicatat tanpa mengubah histori lama.</p></div></header>
    <section class="kbsm-business-panel"><div class="kbsm-business-panel__header"><h2 class="kbsm-business-panel__title">Tambah Versi Struktur</h2><p class="kbsm-business-panel__copy">Isi Anggota atau Nama Penerima eksternal, lalu tentukan masa berlaku dan dasar keputusan.</p></div><form method="POST" action="{{ route('struktur-koperasi.store') }}" class="kbsm-form-grid">@csrf
        <div class="kbsm-field"><label>Anggota (opsional)</label><select name="anggota_id"><option value="">Penerima eksternal</option>@foreach($members as $member)<option value="{{ $member->id }}">{{ $member->nomor_anggota }} · {{ $member->karyawan?->nama }}</option>@endforeach</select></div>
        <div class="kbsm-field"><label>Nama Penerima Eksternal</label><input name="nama_penerima"></div>
        <div class="kbsm-field"><label>Kelompok</label><select name="kelompok" required><option value="pengurus">Pengurus</option><option value="pengawas">Pengawas</option><option value="pembina">Pembina</option></select></div>
        <div class="kbsm-field"><label>Jabatan</label><input name="jabatan" required></div>
        <div class="kbsm-field"><label>Mulai Berlaku</label><input type="date" name="tanggal_mulai" required></div>
        <div class="kbsm-field"><label>Selesai Berlaku</label><input type="date" name="tanggal_selesai"></div>
        <div class="kbsm-field kbsm-field--wide"><label>Dasar Keputusan</label><input name="dasar_keputusan" required></div>
        <div class="kbsm-field kbsm-field--wide"><button class="kbsm-btn kbsm-btn--navy">Simpan Versi Struktur</button></div>
    </form></section>
    <section class="kbsm-business-panel"><div class="kbsm-business-panel__header"><h2 class="kbsm-business-panel__title">Riwayat Struktur</h2></div><div class="kbsm-business-table-wrap"><table class="kbsm-business-table"><thead><tr><th>Nama</th><th>Kelompok</th><th>Jabatan</th><th>Masa Berlaku</th><th>Dasar Keputusan</th><th>Dibuat Oleh</th></tr></thead><tbody>@forelse($structures as $row)<tr><td>{{ $row->nama }}@if($row->anggota)<small class="kbsm-business-muted">{{ $row->anggota->nomor_anggota }}</small>@else<small class="kbsm-business-muted">Penerima eksternal</small>@endif</td><td>{{ $row->kelompok_label }}</td><td>{{ $row->jabatan }}</td><td>{{ $row->tanggal_mulai->format('d/m/Y') }}–{{ $row->tanggal_selesai?->format('d/m/Y') ?? 'sekarang' }}</td><td>{{ $row->dasar_keputusan }}</td><td>{{ $row->creator?->name ?? '–' }}</td></tr>@empty<tr><td colspan="6" class="kbsm-business-empty">Belum ada struktur koperasi.</td></tr>@endforelse</tbody></table></div><div class="kbsm-business-pagination">{{ $structures->links() }}</div></section>
</div>
@endsection
