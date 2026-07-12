@extends('layout.main')

@section('content')
<div class="w-full px-6 py-6 mx-auto">
  <div class="flex flex-wrap -mx-3">
    <div class="flex-none w-full max-w-full px-3">
      <div class="relative flex flex-col min-w-0 mb-6 break-words bg-white border-0 shadow-soft-xl rounded-2xl bg-clip-border">
        <div class="p-6 pb-0 mb-0 bg-white rounded-t-2xl">
          <h6>Jadwal Cicilan Pinjaman</h6>
          <p class="text-sm text-slate-400">Jadwal otomatis read-only. Reservasi payroll dan pembayaran tunai/payroll dikelola lewat lifecycle resmi.</p>
        </div>

        <div class="flex-auto px-0 pt-0 pb-2">
          <div class="p-0 overflow-x-auto">
            <table class="items-center w-full mb-0 align-top border-gray-200 text-slate-500">
              <thead class="align-bottom">
                <tr>
                  <th class="px-6 py-3 text-left text-xxs font-bold uppercase text-slate-400 opacity-70">Pinjaman</th>
                  <th class="px-6 py-3 text-center text-xxs font-bold uppercase text-slate-400 opacity-70">Angsuran</th>
                  <th class="px-6 py-3 text-center text-xxs font-bold uppercase text-slate-400 opacity-70">Periode</th>
                  <th class="px-6 py-3 text-center text-xxs font-bold uppercase text-slate-400 opacity-70">Nominal</th>
                  <th class="px-6 py-3 text-center text-xxs font-bold uppercase text-slate-400 opacity-70">Status</th>
                  <th class="px-6 py-3 text-center text-xxs font-bold uppercase text-slate-400 opacity-70">Metode</th>
                  <th class="px-6 py-3 text-center text-xxs font-bold uppercase text-slate-400 opacity-70">Pembayaran</th>
                  <th class="px-6 py-3 text-left text-xxs font-bold uppercase text-slate-400 opacity-70">Reversal</th>
                </tr>
              </thead>
              <tbody>
                @forelse($jadwalCicilan as $item)
                  <tr>
                    <td class="p-2 align-middle bg-transparent border-b whitespace-nowrap shadow-transparent">
                      <div class="flex items-center px-4 py-2">
                        <div class="mr-4 flex shrink-0 h-9 w-9 items-center justify-center rounded-xl bg-gradient-to-tl from-emerald-700 to-teal-500 text-xs font-bold text-white">
                          {{ $jadwalCicilan->firstItem() + $loop->index }}
                        </div>

                        <div class="flex flex-col justify-center">
                          <h6 class="mb-0 text-sm leading-normal">{{ $item->pinjaman->kode_pinjaman ?? '-' }}</h6>
                          <p class="mb-0 text-xs leading-tight text-slate-400">
                            {{ $item->pinjaman->anggota->karyawan->nama ?? '-' }}
                          </p>
                        </div>
                      </div>
                    </td>

                    <td class="p-2 text-center align-middle bg-transparent border-b whitespace-nowrap shadow-transparent">{{ $item->angsuran_ke }}</td>
                    <td class="p-2 text-center align-middle bg-transparent border-b whitespace-nowrap shadow-transparent">{{ $item->periode->format('Y-m') }}</td>
                    <td class="p-2 text-center align-middle bg-transparent border-b whitespace-nowrap shadow-transparent">Rp {{ number_format($item->nominal_pokok, 0, ',', '.') }}</td>
                    <td class="p-2 text-center align-middle bg-transparent border-b whitespace-nowrap shadow-transparent">
                      <span class="inline-block rounded-1.8 bg-gradient-to-tl from-blue-700 to-cyan-500 px-2.5 py-1.4 text-xs font-bold uppercase text-white">
                        {{ $item->status }}
                      </span>
                    </td>
                    <td class="p-2 text-center align-middle bg-transparent border-b whitespace-nowrap shadow-transparent">{{ $item->metode_penyelesaian ?: '-' }}</td>
                    <td class="p-2 text-center align-middle bg-transparent border-b whitespace-nowrap shadow-transparent">
                      @if($item->cicilanPembayaran)
                        <p class="mb-0 text-xs font-bold text-slate-600">CIC-{{ $item->cicilanPembayaran->id }}</p>
                        <p class="mb-0 text-xs text-slate-400">{{ $item->cicilanPembayaran->dompet->nama_dompet ?? '-' }}</p>
                      @else
                        -
                      @endif
                    </td>
                    <td class="p-2 align-middle bg-transparent border-b shadow-transparent">
                      @if($item->cicilanPembayaran && $item->cicilanPembayaran->status === 'sudah_bayar' && ! $item->cicilanPembayaran->reversal_transaksi_id)
                        <form method="POST" action="{{ route('cicilan-pinjaman.reversal', $item->cicilanPembayaran) }}" class="space-y-2">
                          @csrf
                          @if($item->cicilanPembayaran->metode_pembayaran !== 'potong_gaji')
                            <select name="dompet_refund_id" class="w-full rounded-lg border border-gray-300 px-2 py-1 text-xs" required>
                              @foreach($dompetRefund as $dompet)
                                <option value="{{ $dompet->id }}" @selected($item->cicilanPembayaran->dompet_id === $dompet->id)>{{ $dompet->nama_dompet }}</option>
                              @endforeach
                            </select>
                          @endif
                          <textarea name="alasan" rows="2" required minlength="5" placeholder="Alasan reversal cicilan"
                            class="w-full rounded-lg border border-gray-300 px-2 py-1 text-xs"></textarea>
                          <button class="rounded-lg bg-red-600 px-3 py-2 text-xs font-bold uppercase text-white">Reversal Penuh</button>
                        </form>
                      @else
                        <span class="text-xs text-slate-400">Tidak eligible</span>
                      @endif
                    </td>
                  </tr>
                @empty
                  <tr>
                    <td colspan="8" class="p-4 text-center text-sm text-slate-400">
                      Belum ada jadwal cicilan.
                    </td>
                  </tr>
                @endforelse
              </tbody>
            </table>
          </div>

          <div class="p-4 border-t border-gray-200">
            {{ $jadwalCicilan->links() }}
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
@endsection
