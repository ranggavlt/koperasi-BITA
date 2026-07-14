@extends('layout.main')

@section('content')
@php
    $formatRupiah = fn (int $value): string => 'Rp ' . number_format($value, 0, ',', '.');
@endphp

<div class="mkb-page">
    <div class="mkb-page-header">
        <div>
            <p class="mkb-eyebrow">Kas & Bank</p>
            <h2 class="mkb-page-title">Mutasi Kas & Bank</h2>
            <p class="mkb-page-subtitle">
                Laporan read-only dari mutasi kas dan bank yang dibentuk oleh service transaksi resmi.
            </p>
        </div>
    </div>

    @if ($errors->any())
        <div class="mkb-alert mkb-alert--danger">
            {{ $errors->first() }}
        </div>
    @endif

    <section class="mkb-filter-panel" aria-labelledby="mkb-filter-title">
        <div class="mkb-filter-header">
            <h3 id="mkb-filter-title" class="mkb-filter-title">Filter Mutasi</h3>
            <p class="mkb-filter-subtitle">Gunakan rentang tanggal, dompet, tipe, dan sumber untuk membaca mutasi yang sudah tercatat.</p>
        </div>

        <form method="GET" action="{{ route('mutasi-kas.index') }}" class="mkb-filter-form">
            <div class="mkb-field">
                <label for="tanggal_mulai" class="mkb-label">
                    Tanggal Mulai
                </label>
                <input
                    id="tanggal_mulai"
                    type="date"
                    name="tanggal_mulai"
                    value="{{ $filters['tanggal_mulai'] }}"
                    class="mkb-control">
            </div>

            <div class="mkb-field">
                <label for="tanggal_selesai" class="mkb-label">
                    Tanggal Selesai
                </label>
                <input
                    id="tanggal_selesai"
                    type="date"
                    name="tanggal_selesai"
                    value="{{ $filters['tanggal_selesai'] }}"
                    class="mkb-control">
            </div>

            <div class="mkb-field">
                <label for="dompet_id" class="mkb-label">
                    Dompet
                </label>
                <select
                    id="dompet_id"
                    name="dompet_id"
                    class="mkb-control">
                    <option value="">Semua Dompet</option>
                    @foreach ($dompetOptions as $dompet)
                        <option value="{{ $dompet->id }}" @selected((string) $filters['dompet_id'] === (string) $dompet->id)>
                            {{ $dompet->nama_dompet }} ({{ strtoupper($dompet->jenis_dompet ?? '-') }})
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="mkb-field">
                <label for="tipe" class="mkb-label">
                    Tipe
                </label>
                <select
                    id="tipe"
                    name="tipe"
                    class="mkb-control">
                    <option value="">Masuk & Keluar</option>
                    <option value="masuk" @selected(($filters['tipe'] ?? '') === 'masuk')>Masuk</option>
                    <option value="keluar" @selected(($filters['tipe'] ?? '') === 'keluar')>Keluar</option>
                </select>
            </div>

            <div class="mkb-field">
                <label for="sumber" class="mkb-label">
                    Sumber
                </label>
                <select
                    id="sumber"
                    name="sumber"
                    class="mkb-control">
                    <option value="">Semua Sumber</option>
                    @foreach ($sourceOptions as $sourceValue => $sourceLabel)
                        <option value="{{ $sourceValue }}" @selected(($filters['sumber'] ?? '') === $sourceValue)>
                            {{ $sourceLabel }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="mkb-field mkb-filter-actions-field">
                <span class="mkb-label" aria-hidden="true">Aksi</span>
                <div class="mkb-filter-actions">
                    <button type="submit" class="kbsm-btn kbsm-btn--green">
                        Filter
                    </button>
                    <a href="{{ route('mutasi-kas.index') }}" class="kbsm-btn mkb-btn-reset">
                        Reset
                    </a>
                </div>
            </div>
        </form>
    </section>

    <section class="mkb-summary-grid" aria-label="Ringkasan Mutasi Kas dan Bank">
        <article class="mkb-summary-card mkb-summary-card--masuk">
            <span class="mkb-summary-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none">
                    <path d="M12 4v10m0 0 4-4m-4 4-4-4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                    <path d="M5 15.5v1.75A2.75 2.75 0 0 0 7.75 20h8.5A2.75 2.75 0 0 0 19 17.25V15.5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                </svg>
            </span>
            <div class="mkb-summary-content">
                <p class="mkb-summary-label">Total Uang Masuk</p>
                <p class="mkb-summary-value">{{ $formatRupiah($summary['total_masuk']) }}</p>
            </div>
        </article>

        <article class="mkb-summary-card mkb-summary-card--keluar">
            <span class="mkb-summary-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none">
                    <path d="M12 20V10m0 0 4 4m-4-4-4 4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                    <path d="M5 8.5V6.75A2.75 2.75 0 0 1 7.75 4h8.5A2.75 2.75 0 0 1 19 6.75V8.5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                </svg>
            </span>
            <div class="mkb-summary-content">
                <p class="mkb-summary-label">Total Uang Keluar</p>
                <p class="mkb-summary-value">{{ $formatRupiah($summary['total_keluar']) }}</p>
            </div>
        </article>

        <article class="mkb-summary-card mkb-summary-card--neto">
            <span class="mkb-summary-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none">
                    <path d="M7 9.5h10M7 13h10M9 17h6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                    <path d="M6.75 4h10.5A2.75 2.75 0 0 1 20 6.75v10.5A2.75 2.75 0 0 1 17.25 20H6.75A2.75 2.75 0 0 1 4 17.25V6.75A2.75 2.75 0 0 1 6.75 4Z" stroke="currentColor" stroke-width="1.8"/>
                </svg>
            </span>
            <div class="mkb-summary-content">
                <p class="mkb-summary-label">Selisih / Neto</p>
                <p class="mkb-summary-value {{ $summary['neto'] < 0 ? 'mkb-summary-value--negative' : '' }}">
                    {{ $formatRupiah($summary['neto']) }}
                </p>
            </div>
        </article>
    </section>

    <x-card title="Daftar Mutasi Kas & Bank">
        <x-table>
            <x-slot:head>
                <tr>
                    <th>Tanggal</th>
                    <th>Dompet</th>
                    <th class="mkb-center-cell">Masuk/Keluar</th>
                    <th>Sumber</th>
                    <th>Referensi</th>
                    <th class="mkb-money-cell">Nominal</th>
                    <th>Keterangan</th>
                </tr>
            </x-slot:head>

            @forelse($data as $item)
                <tr class="mkb-table-row">
                    <td class="mkb-date-cell">
                        {{ $item->tanggal }}
                    </td>

                    <td class="mkb-dompet-cell">
                        {{ $item->dompet?->nama_dompet ?? 'Dompet tidak ditemukan' }}
                    </td>

                    <td class="mkb-center-cell">
                        @if($item->tipe === 'masuk')
                            <x-badge color="green">Masuk</x-badge>
                        @else
                            <x-badge color="gray">Keluar</x-badge>
                        @endif
                    </td>

                    <td>
                        {{ $item->sumber_label }}
                    </td>

                    <td class="mkb-reference-cell">
                        @if($item->referensi_tipe && $item->referensi_id)
                            {{ class_basename($item->referensi_tipe) }} #{{ $item->referensi_id }}
                        @elseif($item->referensi_id)
                            #{{ $item->referensi_id }}
                        @else
                            -
                        @endif
                    </td>

                    <td class="mkb-money-cell">
                        {{ $formatRupiah((int) $item->jumlah) }}
                    </td>

                    <td>
                        {{ $item->keterangan ?? '-' }}
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" class="mkb-empty-cell">
                        @if(! $hasDompet)
                            Belum ada Dompet Koperasi. Mutasi Kas & Bank akan tampil setelah Dompet dibuat dan transaksi resmi memindahkan uang.
                        @elseif(! $hasAnyMutasi)
                            Dompet sudah tersedia, tetapi belum ada Mutasi Kas & Bank yang tercatat.
                        @else
                            Filter tidak menemukan data Mutasi Kas & Bank.
                        @endif
                    </td>
                </tr>
            @endforelse
        </x-table>

        @if($data->hasPages())
            <div class="mkb-pagination">
                {{ $data->links() }}
            </div>
        @endif
    </x-card>
</div>
@endsection
