@extends('layout.main')

@section('content')
@php
    $money = fn ($value, $dash = true) => $dash && (float) $value === 0.0 ? '–' : 'Rp ' . number_format((float) $value, 0, ',', '.');
    $mainAlloc = [
        'Dana Cadangan' => ['persen_dana_cadangan', $shu->nominal_dana_cadangan],
        'SHU Anggota' => ['persen_shu_anggota', $shu->nominal_shu_anggota],
        'Pengurus' => ['persen_pengurus', $shu->nominal_pengurus],
        'Pengawas' => ['persen_pengawas', $shu->nominal_pengawas],
        'Pembina' => ['persen_pembina', $shu->nominal_pembina],
        'Dana Sosial' => ['persen_dana_sosial', $shu->nominal_dana_sosial],
        'Dana Pendidikan' => ['persen_dana_pendidikan', $shu->nominal_dana_pendidikan],
    ];
    $recipients = $shu->recipients->groupBy('jenis_penerima');
    $editable = in_array($shu->status, ['draft', 'calculated'], true);
    $typeLabels = ['anggota' => 'Anggota', 'pengurus' => 'Pengurus', 'pengawas' => 'Pengawas', 'pembina' => 'Pembina'];
@endphp
<div class="kbsm-business-page">
    @if(session('success'))<div class="kbsm-business-alert kbsm-business-alert--success">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="kbsm-business-alert kbsm-business-alert--danger">{{ $errors->first() }}</div>@endif

    <header class="kbsm-business-header">
        <div>
            <a href="{{ route('shu-koperasi.index') }}" class="kbsm-business-back">← Pembagian SHU Tahunan</a>
            <p class="kbsm-business-eyebrow">{{ $shu->periode?->kode }}</p>
            <h1 class="kbsm-business-title">{{ $shu->periode?->nama ?? $shu->judul }}</h1>
            <p class="kbsm-business-subtitle">{{ $shu->tanggal_mulai->translatedFormat('j F Y') }} – {{ $shu->tanggal_selesai->translatedFormat('j F Y') }} · Pengaturan v{{ $shu->config_snapshot['versi'] ?? $shu->config?->versi }}</p>
        </div>
        <span class="kbsm-status {{ $shu->status === 'completed' ? 'kbsm-status--green' : 'kbsm-status--gold' }}">{{ $shu->status_label }}</span>
    </header>

    @if(str_contains((string) ($shu->config_snapshot['dasar_keputusan'] ?? ''), 'Data demonstrasi'))
        <div class="kbsm-business-alert kbsm-business-alert--warning">Pengaturan yang dipakai merupakan data contoh presentasi dan dapat diganti dengan versi baru sesuai keputusan RAT resmi.</div>
    @endif

    <div class="kbsm-business-metrics shu-summary-metrics">
        <article><span>Pendapatan</span><strong>{{ $money($shu->total_pendapatan) }}</strong></article>
        <article><span>Beban</span><strong>{{ $money($shu->total_biaya) }}</strong></article>
        <article><span>Laba Bersih</span><strong>{{ $money($shu->shu_total) }}</strong></article>
        <article><span>Sudah Dibayar</span><strong>{{ $money($shu->total_dibayar) }}</strong></article>
        <article><span>Belum Dibayar</span><strong>{{ $money($shu->total_belum_dibayar) }}</strong></article>
    </div>

    <section class="kbsm-business-panel">
        <div class="kbsm-business-panel__header">
            <h2 class="kbsm-business-panel__title">Tahap Proses</h2>
            <p class="kbsm-business-panel__copy">Terapkan hanya memperbarui rancangan. Approval mengunci periode, konfigurasi, penerima, jabatan, bobot, basis, dan nominal.</p>
        </div>
        <div class="kbsm-business-actions">
            @if($editable)
                <form method="POST" action="{{ route('shu-koperasi.period', $shu) }}" class="shu-period-change">@csrf<select name="periode_id" aria-label="Ganti periode SHU" required>@foreach($alternativePeriods as $period)<option value="{{ $period->id }}" {{ $period->id === $shu->periode_akuntansi_id ? 'selected' : '' }}>{{ $period->nama }} · {{ $period->tanggal_mulai->format('d/m/Y') }} – {{ $period->tanggal_selesai->format('d/m/Y') }}</option>@endforeach</select><button class="kbsm-btn kbsm-btn--outline-slate">Ganti & Terapkan Periode</button></form>
                <form method="POST" action="{{ route('shu-koperasi.calculate', $shu) }}">@csrf<button class="kbsm-btn kbsm-btn--outline-slate">Terapkan Ulang Data Periode</button></form>
                <form method="POST" action="{{ route('shu-koperasi.weights.reset', $shu) }}">@csrf<button class="kbsm-btn kbsm-btn--outline-slate">Kembalikan ke Bobot Sama Rata</button></form>
            @endif
            @if($shu->status === 'calculated')
                <form method="POST" action="{{ route('shu-koperasi.submit', $shu) }}" onsubmit="return confirm('Ajukan rancangan ini untuk disetujui Admin lain?')">@csrf<button class="kbsm-btn kbsm-btn--navy">Ajukan Persetujuan</button></form>
            @endif
            @if($shu->status === 'submitted')
                <form method="POST" action="{{ route('shu-koperasi.approve', $shu) }}" onsubmit="return confirm('Setujui dan kunci pembagian SHU ini?')">@csrf<button class="kbsm-btn kbsm-btn--navy">Setujui & Kunci Pembagian</button></form>
            @endif
            @if(in_array($shu->status, ['ready_to_pay', 'completed']))
                <span class="kbsm-business-muted">Disetujui {{ $shu->approved_at?->format('d/m/Y H:i') }} oleh {{ $shu->approver?->name ?? 'Admin' }}</span>
            @endif
        </div>
    </section>

    <section class="kbsm-business-panel">
        <div class="kbsm-business-panel__header">
            <h2 class="kbsm-business-panel__title">Alokasi Berdasarkan Pengaturan</h2>
            <p class="kbsm-business-panel__copy">{{ $shu->config_snapshot['dasar_keputusan'] ?? $shu->config?->dasar_keputusan }}</p>
        </div>
        <div class="kbsm-business-table-wrap">
            <table class="kbsm-business-table">
                <thead><tr><th>Kategori</th><th class="kbsm-business-table__right">Persentase</th><th class="kbsm-business-table__right">Nominal</th></tr></thead>
                <tbody>@foreach($mainAlloc as $label => [$field, $amount])<tr><td>{{ $label }}</td><td class="kbsm-business-amount">{{ number_format((float) $shu->{$field}, 2, ',', '.') }}%</td><td class="kbsm-business-amount">{{ $money($amount) }}</td></tr>@endforeach</tbody>
            </table>
        </div>
    </section>

    <nav class="shu-tabs" aria-label="Bagian detail SHU">
        @foreach(['anggota' => 'Anggota', 'pengurus' => 'Pengurus', 'pengawas' => 'Pengawas', 'pembina' => 'Pembina', 'nonpersonal' => 'Dana Non-Personal', 'approval' => 'Riwayat Approval', 'pembayaran' => 'Pembayaran'] as $key => $label)
            <button type="button" class="shu-tab {{ $loop->first ? 'is-active' : '' }}" data-shu-tab="{{ $key }}">{{ $label }}</button>
        @endforeach
    </nav>

    <section class="kbsm-business-panel shu-tab-panel" data-shu-panel="anggota">
        <div class="kbsm-business-panel__header"><h2 class="kbsm-business-panel__title">Pembagian Anggota</h2><p class="kbsm-business-panel__copy">Jasa Modal {{ $shu->persen_jasa_modal }}% berdasarkan Simpanan; Jasa Usaha {{ $shu->persen_jasa_usaha }}% berdasarkan pembelian Waserba.</p></div>
        <div class="kbsm-business-toolbar"><input class="shu-table-search" data-target="shu-member-table" type="search" placeholder="Cari nama anggota…"></div>
        <div class="kbsm-business-table-wrap"><table class="kbsm-business-table" id="shu-member-table"><thead><tr><th>Anggota</th><th class="kbsm-business-table__right">Basis Simpanan</th><th class="kbsm-business-table__right">Basis Waserba</th><th class="kbsm-business-table__right">Jasa Modal</th><th class="kbsm-business-table__right">Jasa Usaha</th><th class="kbsm-business-table__right">Total SHU</th><th>Status</th></tr></thead><tbody>
            @forelse($recipients->get('anggota', collect()) as $recipient)
                <tr><td>{{ $recipient->nama_snapshot }}<div class="kbsm-business-muted">{{ $recipient->anggota?->nomor_anggota ?? '–' }}</div></td><td class="kbsm-business-amount">{{ $money($recipient->basis_jasa_modal) }}</td><td class="kbsm-business-amount">{{ $money($recipient->basis_jasa_usaha) }}</td><td class="kbsm-business-amount">{{ $money($recipient->nominal_jasa_modal) }}</td><td class="kbsm-business-amount">{{ $money($recipient->nominal_jasa_usaha) }}</td><td class="kbsm-business-amount">{{ $money($recipient->nominal_hak) }}</td><td><span class="kbsm-status {{ $recipient->status_pembayaran === 'dibayar' ? 'kbsm-status--green' : 'kbsm-status--gold' }}">{{ $recipient->status_label }}</span></td></tr>
            @empty<tr><td colspan="7" class="kbsm-business-empty">Tidak ada Anggota dengan basis Simpanan atau pembelian Waserba pada periode ini.</td></tr>@endforelse
        </tbody></table></div>
    </section>

    @foreach(['pengurus' => 'Pengurus', 'pengawas' => 'Pengawas', 'pembina' => 'Pembina'] as $group => $label)
        <section class="kbsm-business-panel shu-tab-panel" data-shu-panel="{{ $group }}" hidden>
            <div class="kbsm-business-panel__header"><h2 class="kbsm-business-panel__title">Pembagian {{ $label }}</h2><p class="kbsm-business-panel__copy">Nominal = bobot penerima ÷ total bobot kelompok × pool {{ $label }}. Bobot awal setiap orang adalah 1.</p></div>
            <form method="POST" action="{{ route('shu-koperasi.weights', $shu) }}">@csrf
                <div class="kbsm-business-table-wrap"><table class="kbsm-business-table"><thead><tr><th>Nama</th><th>Jabatan</th><th class="kbsm-business-table__right">Bobot RAT</th><th>Dasar Hitung</th><th class="kbsm-business-table__right">Nominal</th><th>Status</th></tr></thead><tbody>
                    @forelse($recipients->get($group, collect()) as $recipient)
                        <tr><td>{{ $recipient->nama_snapshot }}</td><td>{{ $recipient->jabatan_snapshot }}</td><td class="kbsm-business-amount">@if($editable)<input class="shu-weight-input" type="number" name="bobot[{{ $recipient->id }}]" value="{{ rtrim(rtrim(number_format((float) $recipient->bobot, 3, '.', ''), '0'), '.') }}" min="0.001" max="99999" step="0.001" required>@else{{ number_format((float) $recipient->bobot, 3, ',', '.') }}@endif</td><td class="kbsm-business-muted">{{ number_format((float) $recipient->bobot, 3, ',', '.') }} dari {{ number_format((float) data_get($recipient->formula_snapshot, 'total_bobot_kelompok', 0), 3, ',', '.') }}</td><td class="kbsm-business-amount">{{ $money($recipient->nominal_hak) }}</td><td><span class="kbsm-status {{ $recipient->status_pembayaran === 'dibayar' ? 'kbsm-status--green' : 'kbsm-status--gold' }}">{{ $recipient->status_label }}</span></td></tr>
                    @empty<tr><td colspan="6" class="kbsm-business-empty">Belum ada penerima aktif untuk kelompok {{ $label }}.</td></tr>@endforelse
                </tbody></table></div>
                @if($editable && $recipients->get($group, collect())->isNotEmpty())<div class="kbsm-business-actions"><button class="kbsm-btn kbsm-btn--navy">Terapkan Bobot {{ $label }}</button><span class="kbsm-business-muted">Terapkan hanya menyimpan rancangan dan memperbarui preview nominal; belum menyetujui atau membayar.</span></div>@endif
            </form>
        </section>
    @endforeach

    <section class="kbsm-business-panel shu-tab-panel" data-shu-panel="nonpersonal" hidden>
        <div class="kbsm-business-panel__header"><h2 class="kbsm-business-panel__title">Dana Non-Personal</h2><p class="kbsm-business-panel__copy">Alokasi berikut tidak dibayarkan sebagai hak pribadi penerima.</p></div>
        <div class="shu-recipient-groups"><article><span>Dana Cadangan</span><strong>{{ $money($shu->nominal_dana_cadangan) }}</strong><small>Penguatan modal koperasi</small></article><article><span>Dana Sosial</span><strong>{{ $money($shu->nominal_dana_sosial) }}</strong><small>{{ $shu->socialFund ? 'Sumber Dana Sosial sudah tercatat' : 'Dibentuk satu kali saat approval' }}</small></article><article><span>Dana Pendidikan</span><strong>{{ $money($shu->nominal_dana_pendidikan) }}</strong><small>Program pendidikan koperasi</small></article></div>
    </section>

    <section class="kbsm-business-panel shu-tab-panel" data-shu-panel="approval" hidden>
        <div class="kbsm-business-panel__header"><h2 class="kbsm-business-panel__title">Riwayat Approval</h2><p class="kbsm-business-panel__copy">Pemisahan tugas dapat ditelusuri dari pembuat sampai penyetuju.</p></div>
        <div class="kbsm-business-table-wrap"><table class="kbsm-business-table"><thead><tr><th>Tahap</th><th>Diproses Oleh</th><th>Waktu</th><th>Hasil</th></tr></thead><tbody>
            <tr><td>Periode diterapkan</td><td>{{ $shu->creator?->name ?? '–' }}</td><td>{{ $shu->created_at?->format('d/m/Y H:i') ?? '–' }}</td><td>Rancangan dibuat</td></tr>
            <tr><td>Pembagian diterapkan</td><td>{{ $shu->calculator?->name ?? '–' }}</td><td>{{ $shu->dihitung_pada?->format('d/m/Y H:i') ?? '–' }}</td><td>{{ $shu->calculated_by ? 'Preview tersimpan' : 'Belum diproses' }}</td></tr>
            <tr><td>Diajukan</td><td>{{ $shu->submitter?->name ?? '–' }}</td><td>{{ $shu->submitted_at?->format('d/m/Y H:i') ?? '–' }}</td><td>{{ $shu->submitted_by ? 'Menunggu/selesai diperiksa' : 'Belum diajukan' }}</td></tr>
            <tr><td>Disetujui</td><td>{{ $shu->approver?->name ?? '–' }}</td><td>{{ $shu->approved_at?->format('d/m/Y H:i') ?? '–' }}</td><td>{{ $shu->approved_by ? 'Nominal dikunci' : 'Belum disetujui' }}</td></tr>
        </tbody></table></div>
    </section>

    <section class="kbsm-business-panel shu-tab-panel" data-shu-panel="pembayaran" hidden>
        <div class="kbsm-business-panel__header"><h2 class="kbsm-business-panel__title">Pembayaran per Penerima</h2><p class="kbsm-business-panel__copy">Nominal berasal dari hasil approval dan tidak dapat diedit saat pembayaran.</p></div>
        <div class="kbsm-business-toolbar shu-payment-filter"><input id="shu-payment-search" type="search" placeholder="Cari nama penerima…"><select id="shu-payment-status"><option value="">Semua status</option><option value="belum_dibayar">Belum Dibayar</option><option value="dibayar">Sudah Dibayar</option></select></div>
        <div class="kbsm-business-table-wrap"><table class="kbsm-business-table" id="shu-payment-table"><thead><tr><th>Penerima</th><th>Jenis</th><th>Jabatan</th><th class="kbsm-business-table__right">Nominal Hak</th><th>Status</th><th>Pembayaran</th></tr></thead><tbody>
            @forelse($shu->recipients->where('nominal_hak', '>', 0) as $recipient)
                <tr data-status="{{ $recipient->status_pembayaran }}"><td>{{ $recipient->nama_snapshot }}</td><td>{{ $typeLabels[$recipient->jenis_penerima] ?? ucfirst($recipient->jenis_penerima) }}</td><td>{{ $recipient->jabatan_snapshot ?? '–' }}</td><td class="kbsm-business-amount">{{ $money($recipient->nominal_hak) }}</td><td><span class="kbsm-status {{ $recipient->status_pembayaran === 'dibayar' ? 'kbsm-status--green' : 'kbsm-status--gold' }}">{{ $recipient->status_label }}</span></td><td>
                    @if(in_array($shu->status, ['ready_to_pay', 'approved']) && $recipient->status_pembayaran !== 'dibayar')
                        <details class="rental-action"><summary class="kbsm-btn kbsm-btn--navy kbsm-btn--sm">Catat Pembayaran</summary><form method="POST" action="{{ route('shu-koperasi.pay', $recipient) }}" class="kbsm-inline-form">@csrf<select name="metode" required><option value="tunai">Tunai</option><option value="transfer_bank">Transfer Bank</option></select><select name="dompet_id" required><option value="">Pilih Kas/Bank</option>@foreach($wallets as $wallet)<option value="{{ $wallet->id }}">{{ $wallet->nama_dompet }} · {{ $money($wallet->saldo, false) }}</option>@endforeach</select><input type="date" name="tanggal_bayar" value="{{ now()->format('Y-m-d') }}" required><input name="nomor_referensi" placeholder="Nomor referensi (opsional)"><button class="kbsm-btn kbsm-btn--navy kbsm-btn--sm">Simpan Pembayaran</button></form></details>
                    @elseif($recipient->pembayaran)
                        <span class="kbsm-business-muted">{{ $recipient->pembayaran->tanggal_bayar?->format('d/m/Y') }} · {{ $recipient->pembayaran->dompet?->nama_dompet }} · {{ $recipient->pembayaran->creator?->name }}</span>
                    @else<span class="kbsm-business-muted">Belum siap dibayar</span>@endif
                </td></tr>
            @empty<tr><td colspan="6" class="kbsm-business-empty">Belum ada hak personal yang perlu dibayar.</td></tr>@endforelse
        </tbody></table></div>
    </section>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const activate = key => {
        document.querySelectorAll('[data-shu-tab]').forEach(button => button.classList.toggle('is-active', button.dataset.shuTab === key));
        document.querySelectorAll('[data-shu-panel]').forEach(panel => panel.hidden = panel.dataset.shuPanel !== key);
    };
    document.querySelectorAll('[data-shu-tab]').forEach(button => button.addEventListener('click', () => activate(button.dataset.shuTab)));
    document.querySelectorAll('.shu-table-search').forEach(input => input.addEventListener('input', () => {
        document.querySelectorAll(`#${input.dataset.target} tbody tr`).forEach(row => row.hidden = !row.textContent.toLowerCase().includes(input.value.toLowerCase()));
    }));
    const paymentSearch = document.getElementById('shu-payment-search');
    const paymentStatus = document.getElementById('shu-payment-status');
    const filterPayments = () => document.querySelectorAll('#shu-payment-table tbody tr[data-status]').forEach(row => {
        row.hidden = !row.textContent.toLowerCase().includes(paymentSearch.value.toLowerCase()) || (paymentStatus.value && row.dataset.status !== paymentStatus.value);
    });
    paymentSearch?.addEventListener('input', filterPayments);
    paymentStatus?.addEventListener('change', filterPayments);
});
</script>
@endsection
