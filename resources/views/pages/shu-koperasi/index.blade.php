@extends('layout.main')

@section('content')
@php
    $money = fn ($value, $dash = false) => $dash && (float) $value === 0.0 ? '–' : 'Rp ' . number_format((float) $value, 0, ',', '.');
@endphp
<div class="kbsm-business-page">
    @if(session('success'))<div class="kbsm-business-alert kbsm-business-alert--success">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="kbsm-business-alert kbsm-business-alert--danger">{{ $errors->first() }}</div>@endif

    <header class="kbsm-business-header">
        <div>
            <p class="kbsm-business-eyebrow">SHU & Dana Sosial</p>
            <h1 class="kbsm-business-title">Pembagian SHU Tahunan</h1>
            <p class="kbsm-business-subtitle">Pilih tahun buku yang sudah ditutup. Laba bersih dan konfigurasi diambil otomatis dari pencatatan resmi.</p>
        </div>
        <a href="{{ route('shu-config.index') }}" class="kbsm-btn kbsm-btn--outline-slate">Pengaturan SHU</a>
    </header>

    <section class="kbsm-business-panel">
        <div class="kbsm-business-panel__header">
            <h2 class="kbsm-business-panel__title">Terapkan Periode</h2>
            <p class="kbsm-business-panel__copy">Tombol ini memuat laba, pengaturan yang berlaku, penerima aktif, dan rancangan pembagian. Belum ada approval atau pembayaran pada tahap ini.</p>
        </div>
        <form method="POST" action="{{ route('shu-koperasi.store') }}" class="shu-period-picker" id="shu-period-form">
            @csrf
            <div class="kbsm-field">
                <label for="periode_id">Tahun buku yang siap dibagikan</label>
                <select id="periode_id" name="periode_id" required>
                    <option value="">Pilih periode tertutup</option>
                    @foreach($availablePeriods as $period)
                        <option value="{{ $period->id }}"
                            data-name="{{ $period->nama }}"
                            data-range="{{ $period->tanggal_mulai->translatedFormat('j F Y') }} – {{ $period->tanggal_selesai->translatedFormat('j F Y') }}"
                            data-revenue="{{ (float) $period->total_pendapatan }}"
                            data-expense="{{ (float) $period->total_beban }}"
                            data-profit="{{ (float) $period->laba_bersih }}"
                            {{ (string) old('periode_id') === (string) $period->id ? 'selected' : '' }}>
                            {{ $period->nama }} · {{ $period->tanggal_mulai->format('d/m/Y') }} – {{ $period->tanggal_selesai->format('d/m/Y') }}
                        </option>
                    @endforeach
                </select>
            </div>
            <button class="kbsm-btn kbsm-btn--navy" {{ $availablePeriods->isEmpty() ? 'disabled' : '' }}>Terapkan Periode</button>
        </form>
        <div class="shu-period-preview" id="shu-period-preview" hidden>
            <div><span>Periode</span><strong id="preview-period">–</strong><small id="preview-range">–</small></div>
            <div><span>Pendapatan</span><strong id="preview-revenue">–</strong></div>
            <div><span>Beban</span><strong id="preview-expense">–</strong></div>
            <div><span>Laba Bersih</span><strong id="preview-profit">–</strong></div>
        </div>
        @if($availablePeriods->isEmpty())
            <div class="kbsm-business-empty">Belum ada periode tertutup yang belum mempunyai pembagian SHU. Tutup tahun buku di menu Periode & Tutup Buku terlebih dahulu.</div>
        @endif
    </section>

    <section class="kbsm-business-panel">
        <div class="kbsm-business-panel__header">
            <h2 class="kbsm-business-panel__title">Riwayat Pembagian</h2>
            <p class="kbsm-business-panel__copy">Satu baris mewakili satu periode pembukuan dan menyimpan konfigurasi serta penerimanya sebagai histori.</p>
        </div>
        <div class="kbsm-business-table-wrap">
            <table class="kbsm-business-table">
                <thead><tr><th>Tahun Buku</th><th class="kbsm-business-table__right">Laba Bersih</th><th class="kbsm-business-table__right">Total SHU</th><th>Status</th><th>Tanggal Disetujui</th><th>Aksi</th></tr></thead>
                <tbody>
                @forelse($processes as $shu)
                    <tr>
                        <td><span class="kbsm-business-code">{{ $shu->periode?->kode }}</span><div class="kbsm-business-muted">{{ $shu->periode?->nama }} · {{ $shu->tanggal_mulai->format('d/m/Y') }} – {{ $shu->tanggal_selesai->format('d/m/Y') }}</div></td>
                        <td class="kbsm-business-amount">{{ $money($shu->shu_total) }}</td>
                        <td class="kbsm-business-amount">{{ $money($shu->shu_total) }}</td>
                        <td><span class="kbsm-status {{ $shu->status === 'completed' ? 'kbsm-status--green' : 'kbsm-status--gold' }}">{{ $shu->status_label }}</span></td>
                        <td>{{ $shu->approved_at?->format('d/m/Y H:i') ?? '–' }}</td>
                        <td><a class="kbsm-btn kbsm-btn--outline-slate kbsm-btn--sm" href="{{ route('shu-koperasi.show', $shu) }}">Lihat Detail</a></td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="kbsm-business-empty">Belum ada pembagian SHU yang diterapkan.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="kbsm-business-pagination">{{ $processes->links() }}</div>
    </section>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const select = document.getElementById('periode_id');
    const panel = document.getElementById('shu-period-preview');
    const rupiah = value => Number(value || 0).toLocaleString('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 });
    const refresh = () => {
        const option = select.options[select.selectedIndex];
        panel.hidden = !option?.value;
        if (!option?.value) return;
        document.getElementById('preview-period').textContent = option.dataset.name;
        document.getElementById('preview-range').textContent = option.dataset.range;
        document.getElementById('preview-revenue').textContent = rupiah(option.dataset.revenue);
        document.getElementById('preview-expense').textContent = rupiah(option.dataset.expense);
        document.getElementById('preview-profit').textContent = rupiah(option.dataset.profit);
    };
    select?.addEventListener('change', refresh);
    refresh();
});
</script>
@endsection
