@extends('layout.main')
@section('content')
@php
    $fixed = [
        'persen_dana_cadangan' => ['Dana Cadangan', 30],
        'persen_shu_anggota' => ['SHU Anggota', 40],
        'persen_pengurus' => ['Pengurus', 10],
        'persen_pengawas' => ['Pengawas', 5],
        'persen_pembina' => ['Pembina', 5],
        'persen_dana_sosial' => ['Dana Sosial', 10],
    ];
@endphp
<div class="kbsm-business-page">
    @if(session('success'))<div class="kbsm-business-alert kbsm-business-alert--success">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="kbsm-business-alert kbsm-business-alert--danger"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    <header class="kbsm-business-header"><div><p class="kbsm-business-eyebrow">SHU & Dana Sosial</p><h1 class="kbsm-business-title">Pengaturan SHU</h1><p class="kbsm-business-subtitle">Setiap simpan membuat versi audit baru. Enam kategori utama bersifat final; Dana Pendidikan hanya histori dan bernilai 0%.</p></div></header>

    <section class="kbsm-business-panel">
        <div class="kbsm-business-panel__header"><h2 class="kbsm-business-panel__title">Buat Versi Baru</h2><p class="kbsm-business-panel__copy">Tetapkan dasar RAT dan porsi Jasa Modal versus Jasa Usaha.</p></div>
        <form method="POST" action="{{ route('shu-config.store') }}" class="kbsm-form-grid" id="shu-config-form">@csrf
            <div class="kbsm-field"><label>Berlaku Mulai</label><input type="date" name="berlaku_mulai" value="{{ old('berlaku_mulai', now()->startOfYear()->format('Y-m-d')) }}" required></div>
            <div class="kbsm-field kbsm-field--wide"><label>Dasar Keputusan / RAT</label><input name="dasar_keputusan" value="{{ old('dasar_keputusan') }}" maxlength="255" required></div>
            @foreach($fixed as $name => [$label, $value])
                <div class="kbsm-field"><label>{{ $label }} (%)</label><input type="number" name="{{ $name }}" value="{{ $value }}" readonly></div>
            @endforeach
            <div class="kbsm-field"><label>Jasa Modal dari SHU Anggota (%)</label><input class="shu-member-percent" type="number" name="persen_jasa_modal" value="{{ old('persen_jasa_modal', 50) }}" min="0" max="100" step="0.01" required></div>
            <div class="kbsm-field"><label>Jasa Usaha dari SHU Anggota (%)</label><input class="shu-member-percent" type="number" name="persen_jasa_usaha" value="{{ old('persen_jasa_usaha', 50) }}" min="0" max="100" step="0.01" required></div>
            <div class="kbsm-field kbsm-field--wide"><div class="kbsm-business-note">Kategori utama: <strong>100%</strong> · Porsi Anggota: <strong id="shu-member-total">100,00%</strong></div></div>
            <div class="kbsm-field kbsm-field--wide"><button id="shu-config-submit" class="kbsm-btn kbsm-btn--navy">Simpan Versi Pengaturan</button></div>
        </form>
    </section>

    <section class="kbsm-business-panel">
        <div class="kbsm-business-panel__header"><h2 class="kbsm-business-panel__title">Riwayat Versi</h2><p class="kbsm-business-panel__copy">Versi tersimpan tidak dapat diedit atau dihapus.</p></div>
        <div class="kbsm-business-table-wrap"><table class="kbsm-business-table"><thead><tr><th>Versi</th><th>Berlaku</th><th>Dasar Keputusan</th><th>Pembagian Utama</th><th>Porsi Anggota</th><th>Dibuat Oleh</th></tr></thead><tbody>
            @forelse($configs as $config)
                <tr><td><span class="kbsm-business-code">v{{ $config->versi }}</span></td><td>{{ $config->berlaku_mulai->format('d/m/Y') }}</td><td>{{ $config->dasar_keputusan }}</td><td class="kbsm-business-muted">Cadangan 30% · Anggota 40% · Pengurus 10% · Pengawas 5% · Pembina 5% · Sosial 10%</td><td>Modal {{ $config->persen_jasa_modal }}% · Usaha {{ $config->persen_jasa_usaha }}%</td><td>{{ $config->creator?->name ?? '–' }}</td></tr>
            @empty<tr><td colspan="6" class="kbsm-business-empty">Belum ada versi pengaturan SHU.</td></tr>@endforelse
        </tbody></table></div><div class="kbsm-business-pagination">{{ $configs->links() }}</div>
    </section>
</div>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const inputs = [...document.querySelectorAll('.shu-member-percent')];
    const button = document.getElementById('shu-config-submit');
    const refresh = () => {
        const total = inputs.reduce((sum, input) => sum + (Number(input.value) || 0), 0);
        document.getElementById('shu-member-total').textContent = total.toLocaleString('id-ID', {minimumFractionDigits: 2, maximumFractionDigits: 2}) + '%';
        button.disabled = Math.abs(total - 100) > 0.001;
    };
    inputs.forEach(input => input.addEventListener('input', refresh)); refresh();
});
</script>
@endsection
