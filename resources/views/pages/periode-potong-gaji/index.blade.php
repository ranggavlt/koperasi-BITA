@extends('layout.main')

@section('content')
@php
  use App\Models\LimitPotongGajiAnggota;
  use App\Models\OverrideLimitPotongGajiAnggota;
  use App\Models\PemakaianPotongGaji;
  use App\Models\Pinjaman;
  use Carbon\CarbonImmutable;

  $fmt = fn ($value) => 'Rp ' . number_format((float) $value, 0, ',', '.');
  $periodeLabel = $selectedPeriode ? $selectedPeriode->periode->locale('id')->translatedFormat('F Y') : '-';
  $nextPolicyStart = CarbonImmutable::now(config('app.timezone', 'Asia/Jakarta'))->startOfMonth()->addMonthNoOverflow()->toDateString();
  $selectedPeriodDate = $selectedPeriode?->periode?->toDateString() ?? CarbonImmutable::now(config('app.timezone', 'Asia/Jakarta'))->startOfMonth()->toDateString();
@endphp

<div class="kbsm-business-page">
  @if (session('success'))
    <div class="kbsm-business-alert kbsm-business-alert--success">
      {{ session('success') }}
    </div>
  @endif

  @if ($errors->any())
    <div class="kbsm-business-alert kbsm-business-alert--danger">
      <ul>
        @foreach ($errors->all() as $error)
          <li>{{ $error }}</li>
        @endforeach
      </ul>
    </div>
  @endif

  @if (! empty($generationWarnings))
    <div class="kbsm-business-alert kbsm-business-alert--warning">
      <p class="mb-2 font-bold">Warning generate limit otomatis:</p>
      <ul>
        @foreach($generationWarnings as $warning)
          <li>{{ $warning }}</li>
        @endforeach
      </ul>
    </div>
  @endif

  <div class="kbsm-business-header">
    <div>
      <p class="kbsm-business-eyebrow">Payroll & Cicilan</p>
      <h1 class="kbsm-business-title">Periode Potong Gaji</h1>
      <p class="kbsm-business-subtitle">Kelola limit umum Rp1.500.000, limit khusus anggota, snapshot perusahaan, dan status kredit Waserba per periode.</p>
    </div>
    <div class="kbsm-business-actions">
      <a href="{{ route('periode-potong-gaji.create') }}" class="kbsm-btn kbsm-btn--navy">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
        Buka Periode Baru
      </a>
    </div>
  </div>

  <section class="kbsm-business-panel mb-6">
    <div class="kbsm-business-panel__header">
      <div>
        <h2 class="kbsm-business-panel__title">Kebijakan Limit Umum</h2>
        <p class="kbsm-business-panel__copy">Limit umum berlaku untuk seluruh anggota aktif yang tidak mempunyai limit khusus. Nilai pada periode lama disimpan sebagai snapshot.</p>
      </div>
    </div>
    <div class="p-6 grid gap-6 lg:grid-cols-2">
      <div class="rounded-2xl border border-emerald-100 bg-emerald-50/40 p-5">
        <p class="mb-1 text-xs font-bold uppercase tracking-wider text-emerald-700">Limit Umum Aktif</p>
        <h3 class="mb-1 text-2xl font-black text-slate-800">{{ $activePolicy ? $fmt($activePolicy->nominal_limit) : 'Belum tersedia' }}</h3>
        <p class="mb-0 text-sm text-slate-500">
          Berlaku mulai:
          <span class="font-bold text-slate-700">{{ $activePolicy?->berlaku_mulai_periode?->format('d M Y') ?? '-' }}</span>
        </p>
      </div>

      <form method="POST" action="{{ route('periode-potong-gaji.kebijakan-limit.update') }}" class="grid gap-4 sm:grid-cols-2">
        @csrf
        @method('PATCH')
        <div>
          <label class="mb-2 block text-xs font-bold uppercase text-slate-600">Nominal Limit Umum</label>
          <input type="number" min="0" name="nominal_limit" value="{{ old('nominal_limit', $activePolicy ? (int) $activePolicy->nominal_limit : 1500000) }}" class="kbsm-focus w-full rounded-xl border border-slate-200 px-4 py-3 text-sm">
        </div>
        <div>
          <label class="mb-2 block text-xs font-bold uppercase text-slate-600">Berlaku Mulai Periode</label>
          <input type="date" name="berlaku_mulai_periode" value="{{ old('berlaku_mulai_periode', $nextPolicyStart) }}" class="kbsm-focus w-full rounded-xl border border-slate-200 px-4 py-3 text-sm">
        </div>
        <div class="sm:col-span-2">
          <label class="mb-2 block text-xs font-bold uppercase text-slate-600">Alasan Perubahan</label>
          <textarea name="alasan" rows="2" class="kbsm-focus w-full rounded-xl border border-slate-200 px-4 py-3 text-sm" required>{{ old('alasan', 'Perubahan kebijakan limit umum periode berikutnya.') }}</textarea>
        </div>
        <div class="sm:col-span-2 flex justify-end">
          <button class="kbsm-btn kbsm-btn--emerald">Ubah Limit Umum</button>
        </div>
      </form>
    </div>
  </section>

  <section class="kbsm-business-panel mb-6">
    <div class="kbsm-business-panel__header">
      <h2 class="kbsm-business-panel__title">Pilih Periode</h2>
      <p class="kbsm-business-panel__copy">Generate otomatis dapat diulang secara idempotent; limit yang sudah ada tidak dibuat ganda.</p>
    </div>
    <div class="p-4 border-b border-slate-100 flex flex-wrap items-center gap-3">
      @foreach($periodeList as $periode)
        <a href="{{ route('periode-potong-gaji.index', ['periode_id' => $periode->id] + request()->only(['search', 'perusahaan_id', 'status'])) }}"
          class="px-4 py-2 text-xs font-bold uppercase rounded-lg border {{ $selectedPeriode && $selectedPeriode->id === $periode->id ? 'bg-emerald-600 border-emerald-600 text-white shadow-md' : 'bg-white border-slate-200 text-slate-600 hover:bg-slate-50' }} transition-colors">
          {{ $periode->periode->format('M Y') }}
          <span class="ml-1 px-1.5 py-0.5 rounded-full text-[10px] {{ $selectedPeriode && $selectedPeriode->id === $periode->id ? 'bg-emerald-700' : 'bg-slate-100' }}">{{ $periode->status }}</span>
        </a>
      @endforeach
    </div>
    @if($periodeList->hasPages())
      <div class="p-3 bg-slate-50 border-b border-slate-100">{{ $periodeList->links() }}</div>
    @endif

    @if($selectedPeriode)
      <div class="p-6 space-y-6">
        <div class="grid gap-4 md:grid-cols-4">
          <div class="rounded-2xl border border-slate-100 bg-white p-4 shadow-sm">
            <p class="mb-1 text-xs font-bold uppercase text-slate-400">Limit Umum</p>
            <p class="mb-0 text-2xl font-black text-slate-800">{{ $summary['limit_umum'] }}</p>
          </div>
          <div class="rounded-2xl border border-amber-100 bg-amber-50/50 p-4 shadow-sm">
            <p class="mb-1 text-xs font-bold uppercase text-amber-700">Limit Khusus</p>
            <p class="mb-0 text-2xl font-black text-slate-800">{{ $summary['limit_khusus'] }}</p>
          </div>
          <div class="rounded-2xl border border-red-100 bg-red-50 p-4 shadow-sm">
            <p class="mb-1 text-xs font-bold uppercase text-red-700">Kredit Waserba Nonaktif</p>
            <p class="mb-0 text-2xl font-black text-slate-800">{{ $summary['kredit_waserba_nonaktif'] }}</p>
          </div>
          <div class="rounded-2xl border border-blue-100 bg-blue-50 p-4 shadow-sm">
            <p class="mb-1 text-xs font-bold uppercase text-blue-700">Belum Dibuatkan Limit</p>
            <p class="mb-0 text-2xl font-black text-slate-800">{{ $summary['belum_limit'] }}</p>
          </div>
        </div>

        <div class="rounded-2xl border border-slate-100 bg-slate-50/60 p-4">
          <form method="GET" action="{{ route('periode-potong-gaji.index') }}" class="grid gap-4 lg:grid-cols-2">
            <input type="hidden" name="periode_id" value="{{ $selectedPeriode->id }}">
            <div>
              <label class="mb-2 block text-xs font-bold uppercase text-slate-600">Cari Anggota</label>
              <input type="text" name="search" value="{{ $filters['search'] }}" class="kbsm-focus w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm" placeholder="Nomor, nama, atau email">
            </div>
            <div>
              <label class="mb-2 block text-xs font-bold uppercase text-slate-600">Perusahaan</label>
              <select name="perusahaan_id" class="kbsm-focus w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm">
                <option value="">Semua Perusahaan</option>
                @foreach($perusahaanList as $perusahaan)
                  <option value="{{ $perusahaan->id }}" @selected((string) $filters['perusahaan_id'] === (string) $perusahaan->id)>{{ $perusahaan->kode }} — {{ $perusahaan->nama }}</option>
                @endforeach
              </select>
            </div>
            <div>
              <label class="mb-2 block text-xs font-bold uppercase text-slate-600">Status</label>
              <select name="status" class="kbsm-focus w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm">
                <option value="">Semua Status</option>
                <option value="limit_umum" @selected($filters['status'] === 'limit_umum')>Memakai Limit Umum</option>
                <option value="limit_khusus" @selected($filters['status'] === 'limit_khusus')>Memakai Limit Khusus</option>
                <option value="kredit_nonaktif" @selected($filters['status'] === 'kredit_nonaktif')>Kredit Waserba Nonaktif</option>
                <option value="belum_limit" @selected($filters['status'] === 'belum_limit')>Belum Dibuatkan Limit</option>
              </select>
            </div>
            <div class="flex items-end gap-3">
              <button class="kbsm-btn kbsm-btn--navy">Filter</button>
              <a href="{{ route('periode-potong-gaji.index', ['periode_id' => $selectedPeriode->id]) }}" class="kbsm-btn kbsm-btn--outline-slate">Reset</a>
            </div>
          </form>
        </div>

        <div class="flex flex-wrap items-center justify-between gap-3">
          <div>
            <h3 class="font-bold text-slate-800 text-lg mb-1">Limit Anggota: {{ $periodeLabel }}</h3>
            <p class="mb-0 text-sm text-slate-500">Prioritas pemakaian tetap: Cicilan Pinjaman → Simpanan Wajib → Waserba kredit.</p>
          </div>
          <div class="flex flex-wrap gap-2">
            <form action="{{ route('periode-potong-gaji.bulk-generate', $selectedPeriode) }}" method="POST">
              @csrf
              <button class="kbsm-btn kbsm-btn--emerald">Bulk Generate Limit</button>
            </form>
            <form action="{{ route('periode-potong-gaji.bulk-activate', $selectedPeriode) }}" method="POST">
              @csrf
              <button class="kbsm-btn kbsm-btn--navy">Bulk Activate</button>
            </form>
          </div>
        </div>

        <div class="overflow-x-auto rounded-xl border border-slate-200">
          <table class="w-full text-left text-sm text-slate-600 border-collapse">
            <thead class="bg-slate-100 text-xs uppercase tracking-wider text-slate-500">
              <tr>
                <th class="px-4 py-3 font-semibold border-b border-slate-200">Anggota</th>
                <th class="px-4 py-3 font-semibold border-b border-slate-200">Snapshot Limit</th>
                <th class="px-4 py-3 font-semibold border-b border-slate-200">Pemakaian</th>
                <th class="px-4 py-3 font-semibold border-b border-slate-200">Override & Kredit Waserba</th>
                <th class="px-4 py-3 font-semibold border-b border-slate-200 text-right">Lifecycle</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
              @forelse($anggotaAktif as $anggota)
                @php
                  $limit = $limits->get($anggota->id);
                  $setting = $anggota->overrideLimitPotongGaji;
                  $activePinjaman = $anggota->pinjaman->firstWhere('status', Pinjaman::STATUS_AKTIF);
                  $jadwalPeriode = $activePinjaman?->jadwalCicilan?->first(fn ($jadwal) => $jadwal->periode->toDateString() === $selectedPeriode->periode->toDateString());
                  $reserved = $limit ? $limit->pemakaian->where('status', PemakaianPotongGaji::STATUS_RESERVED)->sum('nominal') : 0;
                  $consumed = $limit ? $limit->pemakaian->where('status', PemakaianPotongGaji::STATUS_CONSUMED)->sum('nominal') : 0;
                  $activeUsed = $reserved + $consumed;
                  $sisa = $limit ? ((float) $limit->limit_nominal - (float) $activeUsed) : null;
                  $sourceLabel = match ($limit?->sumber_limit) {
                    LimitPotongGajiAnggota::SUMBER_OVERRIDE_ANGGOTA => 'Limit Khusus',
                    LimitPotongGajiAnggota::SUMBER_LIMIT_UMUM => 'Limit Umum',
                    LimitPotongGajiAnggota::SUMBER_MANUAL => 'Manual Legacy',
                    default => $limit ? 'Belum Snapshot' : 'Belum Ada Limit',
                  };
                  $sourceClass = $limit?->sumber_limit === LimitPotongGajiAnggota::SUMBER_OVERRIDE_ANGGOTA
                    ? 'bg-amber-100 text-amber-700'
                    : ($limit ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-600');
                  $creditEnabled = $setting?->kredit_waserba_enabled ?? true;
                @endphp
                <tr class="hover:bg-slate-50/50 transition-colors align-top">
                  <td class="px-4 py-4">
                    <p class="mb-0 font-bold text-slate-800">{{ $anggota->nomor_anggota }}</p>
                    <p class="mb-0 text-sm font-medium text-slate-600">{{ $anggota->karyawan->nama ?? '-' }}</p>
                    <p class="mb-0 text-xs text-slate-400 mt-1">{{ $anggota->karyawan->perusahaan?->kode ?? '-' }} / {{ $anggota->karyawan->perusahaan?->nama ?? 'Tanpa perusahaan' }}</p>
                  </td>

                  <td class="px-4 py-4">
                    @if($limit)
                      <span class="inline-flex items-center px-2 py-1 rounded-md text-[10px] font-bold uppercase tracking-wider {{ $sourceClass }}">{{ $sourceLabel }}</span>
                      <p class="mt-2 mb-0 text-xl font-black text-slate-800">{{ $fmt($limit->limit_nominal) }}</p>
                      <p class="mb-0 text-xs text-slate-400">Perusahaan snapshot: {{ $limit->perusahaan_kode_snapshot ?? '-' }} — {{ $limit->perusahaan_nama_snapshot ?? '-' }}</p>
                      <p class="mb-0 text-xs text-slate-400">Generated: {{ $limit->generated_at?->format('d M Y H:i') ?? '-' }}</p>
                    @else
                      <span class="inline-flex items-center px-2 py-1 rounded-md text-[10px] font-bold uppercase tracking-wider bg-amber-100 text-amber-700">Belum berhasil dibuatkan limit</span>
                      <p class="mt-2 mb-0 text-xs text-slate-400">Klik Bulk Generate setelah data perusahaan lengkap.</p>
                    @endif
                  </td>

                  <td class="px-4 py-4">
                    <div class="flex flex-col gap-1.5 text-xs">
                      <div class="flex justify-between gap-4">
                        <span class="text-slate-500">Cicilan Bln Ini</span>
                        <span class="font-medium text-slate-700">{{ $jadwalPeriode ? $fmt($jadwalPeriode->nominal_sisa ?? $jadwalPeriode->nominal_pokok) : '-' }}</span>
                      </div>
                      <div class="flex justify-between gap-4">
                        <span class="text-slate-500">Sisa Limit</span>
                        <span class="font-bold text-emerald-600">{{ $limit ? $fmt($sisa) : '-' }}</span>
                      </div>
                      <div class="flex justify-between gap-4 pt-1 border-t border-slate-100">
                        <span class="text-slate-400">Reserved</span>
                        <span class="text-slate-600">{{ $fmt($reserved) }}</span>
                      </div>
                      <div class="flex justify-between gap-4">
                        <span class="text-slate-400">Consumed</span>
                        <span class="text-slate-600">{{ $fmt($consumed) }}</span>
                      </div>
                    </div>
                  </td>

                  <td class="px-4 py-4">
                    <form action="{{ route('periode-potong-gaji.anggota.override.store', $anggota) }}" method="POST" class="grid gap-2 md:grid-cols-2">
                      @csrf
                      <input type="hidden" name="berlaku_mulai_periode" value="{{ $selectedPeriodDate }}">
                      <input type="number" name="nominal_override" min="0" value="{{ $setting?->status === OverrideLimitPotongGajiAnggota::STATUS_ACTIVE ? (int) $setting->nominal_override : '' }}" placeholder="Limit khusus" class="kbsm-focus rounded-lg border border-slate-200 px-3 py-2 text-xs">
                      <input type="text" name="alasan" value="Penyesuaian limit khusus {{ $periodeLabel }}" class="kbsm-focus rounded-lg border border-slate-200 px-3 py-2 text-xs">
                      <button class="kbsm-btn kbsm-btn--sm kbsm-btn--outline-slate md:col-span-2">Simpan Limit Khusus</button>
                    </form>

                    @if($setting?->status === OverrideLimitPotongGajiAnggota::STATUS_ACTIVE)
                      <form action="{{ route('periode-potong-gaji.anggota.override.reset', $anggota) }}" method="POST" class="mt-2">
                        @csrf
                        <input type="hidden" name="alasan" value="Kembali ke limit umum aktif.">
                        <button class="kbsm-btn kbsm-btn--sm kbsm-btn--emerald w-full">Kembalikan ke Limit Umum</button>
                      </form>
                    @endif

                    <div class="mt-3 rounded-xl border {{ $creditEnabled ? 'border-emerald-100 bg-emerald-50/50' : 'border-red-100 bg-red-50' }} p-3">
                      <p class="mb-2 text-xs font-bold uppercase {{ $creditEnabled ? 'text-emerald-700' : 'text-red-700' }}">
                        Kredit Waserba: {{ $creditEnabled ? 'Aktif' : 'Nonaktif' }}
                      </p>
                      @if($creditEnabled)
                        <form action="{{ route('periode-potong-gaji.anggota.kredit-waserba.disable', $anggota) }}" method="POST" class="grid gap-2">
                          @csrf
                          <input type="text" name="alasan" required placeholder="Alasan nonaktifkan kredit" class="kbsm-focus rounded-lg border border-slate-200 px-3 py-2 text-xs">
                          <button class="kbsm-btn kbsm-btn--sm kbsm-btn--outline-slate">Nonaktifkan Kredit Waserba</button>
                        </form>
                      @else
                        <form action="{{ route('periode-potong-gaji.anggota.kredit-waserba.enable', $anggota) }}" method="POST">
                          @csrf
                          <input type="hidden" name="alasan" value="Kredit Waserba diaktifkan kembali.">
                          <button class="kbsm-btn kbsm-btn--sm kbsm-btn--emerald w-full">Aktifkan Kredit Waserba</button>
                        </form>
                      @endif
                    </div>
                  </td>

                  <td class="px-4 py-4 text-right">
                    @if($limit)
                      <div class="flex flex-col items-end gap-2">
                        <span class="inline-flex items-center px-2 py-1 rounded-md text-[10px] font-bold uppercase tracking-wider
                          {{ $limit->status === 'draft' ? 'bg-slate-100 text-slate-600' : '' }}
                          {{ $limit->status === 'active' ? 'bg-emerald-100 text-emerald-700' : '' }}
                          {{ str_contains($limit->status, 'closed') ? 'bg-blue-100 text-blue-700' : '' }}
                          {{ $limit->status === 'confirmed' ? 'bg-green-100 text-green-700' : '' }}
                        ">{{ str_replace('_', ' ', $limit->status) }}</span>

                        @if($limit->status === LimitPotongGajiAnggota::STATUS_DRAFT)
                          <form action="{{ route('periode-potong-gaji.limit.activate', $limit) }}" method="POST">@csrf @method('PATCH')
                            <button class="kbsm-btn kbsm-btn--sm kbsm-btn--emerald !text-[10px] !py-1 !px-2">Aktifkan Limit</button>
                          </form>
                        @endif
                        @if($limit->status === LimitPotongGajiAnggota::STATUS_ACTIVE)
                          <form action="{{ route('periode-potong-gaji.limit.payoff-payroll', $limit) }}" method="POST">@csrf
                            <button class="kbsm-btn kbsm-btn--sm kbsm-btn--navy !text-[10px] !py-1 !px-2 mb-1">Bayar via Payroll</button>
                          </form>
                          <form action="{{ route('periode-potong-gaji.limit.close', $limit) }}" method="POST">@csrf @method('PATCH')
                            <button class="kbsm-btn kbsm-btn--sm kbsm-btn--outline-slate !text-[10px] !py-1 !px-2">Tutup Limit</button>
                          </form>
                        @endif
                        @if($limit->status === LimitPotongGajiAnggota::STATUS_CLOSED_PENDING_CONFIRMATION)
                          <form action="{{ route('periode-potong-gaji.limit.confirm', $limit) }}" method="POST">@csrf @method('PATCH')
                            <button class="kbsm-btn kbsm-btn--sm kbsm-btn--emerald !text-[10px] !py-1 !px-2">Konfirmasi Selesai</button>
                          </form>
                        @endif
                      </div>
                    @else
                      <span class="text-xs text-slate-400 italic">Belum ada lifecycle limit</span>
                    @endif
                  </td>
                </tr>
              @empty
                <tr>
                  <td colspan="5" class="px-4 py-10 text-center text-slate-400">Tidak ada anggota sesuai filter.</td>
                </tr>
              @endforelse
            </tbody>
          </table>
        </div>
      </div>
    @endif
  </section>
</div>
@endsection
