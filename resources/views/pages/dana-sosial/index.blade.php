@extends('layout.main')
@section('content')
@php $money = fn($value) => 'Rp ' . number_format((int) $value, 0, ',', '.'); @endphp
<div class="kbsm-business-page">
    @if(session('success'))<div class="kbsm-business-alert kbsm-business-alert--success">{{ session('success') }}</div>@endif
    @if(session('warning'))<div class="kbsm-business-alert kbsm-business-alert--warning">{{ session('warning') }}</div>@endif
    @if($errors->any())<div class="kbsm-business-alert kbsm-business-alert--danger"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    <header class="kbsm-business-header"><div><p class="kbsm-business-eyebrow">SHU & Dana Sosial</p><h1 class="kbsm-business-title">Dana Sosial & Klaim</h1><p class="kbsm-business-subtitle">Sumber aktif hanya alokasi SHU yang disetujui. Klaim disetujui mereservasi saldo sampai dibayar penuh.</p></div><div class="kbsm-business-total"><span>Saldo Bebas Reservasi</span><strong>{{ $money($socialSummary['available']) }}</strong></div></header>
    <div class="kbsm-business-metrics"><article><span>Total Alokasi SHU</span><strong>{{ $money($socialSummary['allocation']) }}</strong></article><article><span>Saldo Sumber</span><strong>{{ $money($socialSummary['fund_balance']) }}</strong></article><article><span>Direservasi</span><strong>{{ $money($socialSummary['reserved']) }}</strong></article><article><span>Tersedia</span><strong>{{ $money($socialSummary['available']) }}</strong><small>Sudah dibayar: {{ $money($socialSummary['paid']) }}</small></article></div>

    <section class="kbsm-business-panel"><div class="kbsm-business-panel__header"><h2 class="kbsm-business-panel__title">Versi Kebijakan Manfaat</h2><p class="kbsm-business-panel__copy">Lima manfaat final; setiap perubahan dibuat sebagai versi baru berdasarkan tanggal berlaku.</p></div>
        <form method="POST" action="{{ route('dana-sosial.policy') }}" class="kbsm-form-grid">@csrf
            <div class="kbsm-field"><label>Jenis Manfaat</label><select name="jenis_manfaat_id" required><option value="">Pilih manfaat</option>@foreach($benefits as $benefit)<option value="{{ $benefit->id }}">{{ $benefit->kode }} · {{ $benefit->nama }}</option>@endforeach</select></div>
            <div class="kbsm-field"><label>Batas Maksimal</label><input type="number" name="batas_maksimal" min="1" required></div>
            <div class="kbsm-field"><label>Berlaku Mulai</label><input type="date" name="berlaku_mulai" value="{{ now()->format('Y-m-d') }}" required></div>
            <div class="kbsm-field"><label>Dasar Keputusan</label><input name="dasar_keputusan" required></div>
            <div class="kbsm-field"><label>Dokumen Diperlukan</label><input name="dokumen_diperlukan"></div>
            <div class="kbsm-field"><label>Deskripsi</label><input name="deskripsi"></div>
            <div class="kbsm-field kbsm-field--wide"><button class="kbsm-btn kbsm-btn--navy">Simpan Versi Kebijakan</button></div>
        </form>
        <div class="shu-final-grid">@forelse($policies as $policy)<article class="shu-final-card"><div class="shu-final-card__head"><div><strong>{{ $policy->jenisManfaat->nama }}</strong><small>{{ $policy->jenisManfaat->kode }} · berlaku {{ $policy->berlaku_mulai->format('d/m/Y') }}</small></div><strong>{{ $money($policy->batas_maksimal) }}</strong></div><p>{{ $policy->dasar_keputusan }}</p><small>{{ $policy->dokumen_diperlukan ?: 'Tidak ada dokumen khusus' }}</small></article>@empty<div class="kbsm-business-empty">Belum ada kebijakan manfaat.</div>@endforelse</div>
    </section>

    <section class="kbsm-business-panel"><div class="kbsm-business-panel__header"><h2 class="kbsm-business-panel__title">Ajukan Klaim</h2><p class="kbsm-business-panel__copy">Kebijakan efektif dipilih otomatis berdasarkan jenis manfaat dan tanggal kejadian.</p></div>
        <form method="POST" action="{{ route('dana-sosial.claim') }}" enctype="multipart/form-data" class="kbsm-form-grid">@csrf
            <div class="kbsm-field"><label>Anggota</label><select name="anggota_id" required><option value="">Pilih anggota</option>@foreach($members as $member)<option value="{{ $member->id }}">{{ $member->nomor_anggota }} · {{ $member->karyawan?->nama }}</option>@endforeach</select></div>
            <div class="kbsm-field"><label>Penerima Manfaat</label><input name="penerima_manfaat" required></div>
            <div class="kbsm-field"><label>Hubungan dengan Anggota</label><input name="hubungan_penerima" placeholder="Diri sendiri / anak / pasangan / keluarga" required></div>
            <div class="kbsm-field"><label>Jenis Manfaat</label><select name="jenis_manfaat_id" required><option value="">Pilih manfaat</option>@foreach($benefits as $benefit)<option value="{{ $benefit->id }}">{{ $benefit->nama }}</option>@endforeach</select></div>
            <div class="kbsm-field"><label>Tanggal Kejadian</label><input type="date" name="tanggal_kejadian" required></div>
            <div class="kbsm-field"><label>Nominal Diajukan</label><input type="number" name="nominal_diajukan" min="1" required></div>
            <div class="kbsm-field"><label>Dokumen</label><input type="file" name="dokumen" accept=".pdf,.jpg,.jpeg,.png"></div>
            <div class="kbsm-field"><label>Catatan</label><input name="catatan"></div>
            <div class="kbsm-field kbsm-field--wide"><button class="kbsm-btn kbsm-btn--navy">Ajukan Klaim</button></div>
        </form>
    </section>

    <section class="kbsm-business-panel"><div class="kbsm-business-panel__header"><h2 class="kbsm-business-panel__title">Riwayat Klaim</h2></div><div class="shu-final-grid">
        @forelse($claims as $claim)
            <article class="shu-final-card"><div class="shu-final-card__head"><div><strong>{{ $claim->kode_klaim }} · {{ $claim->penerima_manfaat }}</strong><small>{{ $claim->nama_manfaat_snapshot ?? $claim->kategori }} · {{ $claim->hubungan_penerima }}</small></div><span class="kbsm-status {{ $claim->status === 'paid' ? 'kbsm-status--green' : 'kbsm-status--gold' }}">{{ $claim->status_label }}</span></div>
                <dl class="shu-final-card__facts"><div><dt>Diajukan</dt><dd>{{ $money($claim->nominal_diajukan) }}</dd></div><div><dt>Disetujui</dt><dd>{{ $money($claim->nominal_disetujui) }}</dd></div><div><dt>Batas</dt><dd>{{ $money($claim->batas_nominal_snapshot) }}</dd></div><div><dt>Kejadian</dt><dd>{{ $claim->tanggal_kejadian?->format('d/m/Y') }}</dd></div></dl>
                @if($claim->status === 'submitted')
                    <details><summary class="kbsm-btn kbsm-btn--navy kbsm-btn--sm">Setujui</summary><form method="POST" action="{{ route('dana-sosial.claim.approve', $claim) }}" class="kbsm-inline-form">@csrf<input type="number" name="nominal_disetujui" min="1" max="{{ min((int) $claim->nominal_diajukan, (int) $claim->batas_nominal_snapshot) }}" value="{{ min((int) $claim->nominal_diajukan, (int) $claim->batas_nominal_snapshot) }}" required><input name="catatan_persetujuan" minlength="5" placeholder="Catatan persetujuan" required><button class="kbsm-btn kbsm-btn--navy kbsm-btn--sm">Setujui & Reservasi</button></form></details>
                    <details><summary class="kbsm-btn kbsm-btn--outline-slate kbsm-btn--sm">Tolak</summary><form method="POST" action="{{ route('dana-sosial.claim.reject', $claim) }}" class="kbsm-inline-form">@csrf<input name="alasan_penolakan" minlength="5" placeholder="Alasan penolakan" required><button class="kbsm-btn kbsm-btn--outline-slate kbsm-btn--sm">Tolak</button></form></details>
                @elseif(in_array($claim->status, ['approved', 'waiting_funds'], true))
                    <details><summary class="kbsm-btn kbsm-btn--navy kbsm-btn--sm">Bayar Penuh</summary><form method="POST" action="{{ route('dana-sosial.claim.pay', $claim) }}" class="kbsm-inline-form">@csrf<select name="metode_pembayaran"><option value="tunai">Tunai</option><option value="transfer_bank">Transfer Bank</option></select><select name="dompet_id" required><option value="">Pilih Kas/Bank</option>@foreach($wallets as $wallet)<option value="{{ $wallet->id }}">{{ $wallet->nama_dompet }} · {{ $money($wallet->saldo) }}</option>@endforeach</select><input type="date" name="tanggal_bayar" value="{{ now()->format('Y-m-d') }}" required><input name="nomor_referensi" placeholder="Nomor referensi"><input name="catatan_pencairan" placeholder="Catatan pencairan"><button class="kbsm-btn kbsm-btn--navy kbsm-btn--sm">Bayar {{ $money($claim->nominal_disetujui) }}</button></form></details>
                @elseif($claim->status === 'paid')
                    <details><summary class="kbsm-btn kbsm-btn--outline-slate kbsm-btn--sm">Reversal</summary><form method="POST" action="{{ route('dana-sosial.claim.reverse', $claim) }}" class="kbsm-inline-form">@csrf<input type="date" name="tanggal" value="{{ now()->format('Y-m-d') }}" required><input name="reversal_reason" minlength="5" placeholder="Alasan reversal" required><button class="kbsm-btn kbsm-btn--outline-slate kbsm-btn--sm">Buat Koreksi</button></form></details>
                @endif
            </article>
        @empty<div class="kbsm-business-empty">Belum ada klaim Dana Sosial.</div>@endforelse
    </div><div class="kbsm-business-pagination">{{ $claims->links() }}</div></section>

    <section class="kbsm-business-panel"><div class="kbsm-business-panel__header"><h2 class="kbsm-business-panel__title">Sumber Aktif dari SHU</h2><p class="kbsm-business-panel__copy">Tertaut ke periode, konfigurasi, SHU, jurnal alokasi, maker, dan checker.</p></div><div class="kbsm-business-table-wrap"><table class="kbsm-business-table"><thead><tr><th>Kode</th><th>Periode / SHU</th><th>Konfigurasi</th><th>Jurnal</th><th>Nilai</th><th>Saldo</th><th>Maker / Checker</th></tr></thead><tbody>@forelse($sources as $source)<tr><td>{{ $source->kode_sumber }}</td><td>{{ $source->periode?->kode }} · {{ $source->shu?->judul }}</td><td>v{{ $source->config?->versi }}</td><td>{{ $source->allocationJournal?->nomor_bukti }}</td><td>{{ $money($source->jumlah) }}</td><td>{{ $money($source->saldo_tersedia) }}</td><td>{{ $source->creator?->name ?? $source->created_by }} / {{ $source->approver?->name ?? $source->approved_by }}</td></tr>@empty<tr><td colspan="7" class="kbsm-business-empty">Belum ada alokasi SHU yang disetujui.</td></tr>@endforelse</tbody></table></div>
        @if($legacySources->isNotEmpty())<details><summary>Histori sumber non-SHU (baca saja)</summary><ul>@foreach($legacySources as $source)<li>{{ $source->kode_sumber }} · {{ $money($source->jumlah ?? $source->nominal_awal) }} · Legacy</li>@endforeach</ul></details>@endif
    </section>
</div>
@endsection
