@extends('layout.main')
@section('content')
@php
    $money = fn($value) => 'Rp ' . number_format((int) $value, 0, ',', '.');
    $groups = ['anggota' => 'Anggota', 'pengurus' => 'Pengurus', 'pengawas' => 'Pengawas', 'pembina' => 'Pembina'];
    $pools = [
        'Cadangan' => [$shu->persen_dana_cadangan, $shu->nominal_dana_cadangan],
        'Anggota' => [$shu->persen_shu_anggota, $shu->nominal_shu_anggota],
        'Pengurus' => [$shu->persen_pengurus, $shu->nominal_pengurus],
        'Pengawas' => [$shu->persen_pengawas, $shu->nominal_pengawas],
        'Pembina' => [$shu->persen_pembina, $shu->nominal_pembina],
        'Dana Sosial' => [$shu->persen_dana_sosial, $shu->nominal_dana_sosial],
    ];
    $reasonLabels = [
        'keputusan_rat' => 'Keputusan RAT', 'pertimbangan_pengurus' => 'Pertimbangan Pengurus',
        'aktivitas_data_di_luar_sistem' => 'Aktivitas/Data di Luar Sistem',
        'koreksi_data_anggota' => 'Koreksi Data Anggota', 'lainnya' => 'Lainnya',
    ];
@endphp
<div class="kbsm-business-page">
    @if(session('success'))<div class="kbsm-business-alert kbsm-business-alert--success">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="kbsm-business-alert kbsm-business-alert--danger"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    <header class="kbsm-business-header">
        <div><a href="{{ route('shu-koperasi.index') }}" class="kbsm-business-back">← Pembagian SHU Tahunan</a><p class="kbsm-business-eyebrow">{{ $shu->periode->kode }}</p><h1 class="kbsm-business-title">{{ $shu->judul }}</h1><p class="kbsm-business-subtitle">{{ $shu->tanggal_mulai->format('d/m/Y') }}–{{ $shu->tanggal_selesai->format('d/m/Y') }} · Pengaturan v{{ $shu->config->versi }}</p></div>
        <span class="kbsm-status {{ $shu->status === 'approved' ? 'kbsm-status--green' : 'kbsm-status--gold' }}">{{ $shu->status_label }}</span>
    </header>

    <section class="kbsm-business-summary">
        <article class="kbsm-business-summary-card"><p class="kbsm-business-summary-label">Laba Resmi</p><p class="kbsm-business-summary-value">{{ $money($shu->shu_total) }}</p></article>
        <article class="kbsm-business-summary-card"><p class="kbsm-business-summary-label">Sudah Dibayar</p><p class="kbsm-business-summary-value">{{ $money($shu->total_dibayar) }}</p></article>
        <article class="kbsm-business-summary-card"><p class="kbsm-business-summary-label">Belum Dibayar</p><p class="kbsm-business-summary-value">{{ $money($shu->total_belum_dibayar) }}</p></article>
        <article class="kbsm-business-summary-card kbsm-business-summary-card--green"><p class="kbsm-business-summary-label">Dana Sosial</p><p class="kbsm-business-summary-value">{{ $money($shu->nominal_dana_sosial) }}</p></article>
    </section>

    @if($shu->status !== 'approved')
        <section class="kbsm-business-panel"><div class="kbsm-business-panel__header"><h2 class="kbsm-business-panel__title">Kontrol Rancangan</h2><p class="kbsm-business-panel__copy">Hitung ulang dari data final, sesuaikan Hak Final dengan alasan audit, lalu tandai Siap Disetujui.</p></div><div class="kbsm-business-actions">
            <form method="POST" action="{{ route('shu-koperasi.calculate', $shu) }}">@csrf<button class="kbsm-btn kbsm-btn--outline-slate">Hitung Ulang</button></form>
            @if($shu->status === 'draft')<form method="POST" action="{{ route('shu-koperasi.submit', $shu) }}">@csrf<button class="kbsm-btn kbsm-btn--navy">Tandai Siap Disetujui</button></form>@endif
            @if($shu->status === 'ready_for_approval')<form method="POST" action="{{ route('shu-koperasi.approve', $shu) }}" onsubmit="return confirm('Setujui dan kunci SHU secara permanen?')">@csrf<button class="kbsm-btn kbsm-btn--navy">Setujui & Kunci</button></form>@endif
        </div></section>
    @endif

    <section class="kbsm-business-panel"><div class="kbsm-business-panel__header"><h2 class="kbsm-business-panel__title">Enam Kategori Final</h2><p class="kbsm-business-panel__copy">Jumlah seluruh kategori tepat sama dengan laba resmi. Dana Pendidikan tidak digunakan pada runtime final.</p></div><div class="shu-recipient-groups">
        @foreach($pools as $label => [$percent, $amount])<article><span>{{ $label }} · {{ number_format((float) $percent, 0) }}%</span><strong>{{ $money($amount) }}</strong></article>@endforeach
    </div></section>

    @foreach($groups as $group => $label)
        @php
            $recipients = $shu->recipients->where('jenis_penerima', $group);
            $pool = (int) data_get($shu, $group === 'anggota' ? 'nominal_shu_anggota' : 'nominal_' . $group);
            $finalTotal = $recipients->where('diikutkan', true)->sum(fn($row) => $row->finalRight());
        @endphp
        <section class="kbsm-business-panel">
            <div class="kbsm-business-panel__header"><div><h2 class="kbsm-business-panel__title">Hak SHU {{ $label }}</h2><p class="kbsm-business-panel__copy">Hak Final {{ $money($finalTotal) }} dari pool {{ $money($pool) }} · {{ $finalTotal === $pool ? 'Seimbang' : 'Belum seimbang' }}</p></div></div>
            <div class="shu-final-grid">
                @forelse($recipients as $recipient)
                    <article class="shu-final-card {{ !$recipient->diikutkan ? 'shu-final-card--muted' : '' }}">
                        <div class="shu-final-card__head"><div><strong>{{ $recipient->nama_snapshot }}</strong><small>{{ $recipient->jabatan_snapshot }}@if($recipient->nomor_anggota_snapshot) · {{ $recipient->nomor_anggota_snapshot }}@endif</small></div><span class="kbsm-status {{ $recipient->status_pembayaran === 'dibayar' ? 'kbsm-status--green' : 'kbsm-status--gold' }}">{{ $recipient->status_label }}</span></div>
                        @if($group === 'anggota')
                            <dl class="shu-final-card__facts"><div><dt>Wajib</dt><dd>{{ $money($recipient->simpanan_wajib_dihitung) }}</dd></div><div><dt>Manasuka</dt><dd>{{ $money($recipient->simpanan_manasuka_dihitung) }}</dd></div><div><dt>Modal</dt><dd>{{ $money($recipient->basis_jasa_modal) }}</dd></div><div><dt>Waserba Final</dt><dd>{{ $money($recipient->basis_jasa_usaha) }}</dd></div></dl>
                        @endif
                        <dl class="shu-final-card__facts"><div><dt>Hitungan Sistem</dt><dd>{{ $money($recipient->hitungan_sistem) }}</dd></div><div><dt>Hak Final</dt><dd>{{ $money($recipient->finalRight()) }}</dd></div><div><dt>Selisih</dt><dd>{{ $money($recipient->finalRight() - (int) $recipient->hitungan_sistem) }}</dd></div></dl>
                        @if($shu->status !== 'approved' && $recipient->diikutkan)
                            <details><summary class="kbsm-btn kbsm-btn--outline-slate kbsm-btn--sm">Atur Hak Final</summary><form method="POST" action="{{ route('shu-koperasi.final-right', $recipient) }}" class="kbsm-inline-form">@csrf @method('PATCH')<input type="number" name="hak_final" min="0" value="{{ $recipient->finalRight() }}" required><select name="alasan_hak_final" required><option value="">Pilih alasan</option>@foreach($reasonLabels as $code => $reason)<option value="{{ $code }}">{{ $reason }}</option>@endforeach</select><input name="detail_alasan_hak_final" placeholder="Detail bila diperlukan"><button class="kbsm-btn kbsm-btn--navy kbsm-btn--sm">Simpan</button></form></details>
                        @endif
                        @if($group === 'anggota' && $shu->status !== 'approved')
                            <details><summary class="kbsm-btn kbsm-btn--outline-slate kbsm-btn--sm">{{ $recipient->diikutkan ? 'Keluarkan' : 'Ikutkan' }}</summary><form method="POST" action="{{ route('shu-koperasi.eligibility', $recipient) }}" class="kbsm-inline-form">@csrf @method('PATCH')<input type="hidden" name="diikutkan" value="{{ $recipient->diikutkan ? 0 : 1 }}"><input name="alasan_eligibility" minlength="5" placeholder="Alasan keputusan" required><button class="kbsm-btn kbsm-btn--navy kbsm-btn--sm">Konfirmasi</button></form></details>
                        @endif
                        @if($shu->status === 'approved' && $recipient->diikutkan && $recipient->finalRight() > 0 && !$recipient->pembayaran)
                            <details><summary class="kbsm-btn kbsm-btn--navy kbsm-btn--sm">Bayar Penuh</summary><form method="POST" action="{{ route('shu-koperasi.pay', $recipient) }}" class="kbsm-inline-form">@csrf<select name="metode" required><option value="tunai">Tunai</option><option value="transfer_bank">Transfer Bank</option></select><select name="dompet_id" required><option value="">Pilih Kas/Bank</option>@foreach($wallets as $wallet)<option value="{{ $wallet->id }}">{{ $wallet->nama_dompet }} · {{ $money($wallet->saldo) }}</option>@endforeach</select><input type="date" name="tanggal_bayar" value="{{ now()->format('Y-m-d') }}" required><input name="nomor_referensi" placeholder="Nomor referensi"><input name="catatan" placeholder="Catatan"><button class="kbsm-btn kbsm-btn--navy kbsm-btn--sm">Bayar {{ $money($recipient->finalRight()) }}</button></form></details>
                        @elseif($recipient->pembayaran?->status === 'paid')
                            <details><summary class="kbsm-btn kbsm-btn--outline-slate kbsm-btn--sm">Reversal Pembayaran</summary><form method="POST" action="{{ route('shu-koperasi.payment.reverse', $recipient->pembayaran) }}" class="kbsm-inline-form">@csrf<input type="date" name="tanggal" value="{{ now()->format('Y-m-d') }}" required><input name="reversal_reason" minlength="5" placeholder="Alasan reversal" required><button class="kbsm-btn kbsm-btn--outline-slate kbsm-btn--sm">Buat Jurnal Lawan</button></form></details>
                        @endif
                    </article>
                @empty<div class="kbsm-business-empty">Belum ada penerima efektif untuk kelompok ini.</div>@endforelse
            </div>
        </section>
    @endforeach
</div>
@endsection
