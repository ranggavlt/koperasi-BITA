@extends('layout.main')

@section('content')

<div class="w-full px-6 py-6 mx-auto">

    <div class="flex items-center justify-between mb-6">

        <h2 class="text-xl font-bold text-slate-700">
            Cicilan Pinjaman
        </h2>

        <x-button href="{{ route('cicilan-pinjaman.create') }}">
            + Tambah Cicilan
        </x-button>

    </div>


    <x-card title="Daftar Cicilan Pinjaman">

        <x-table>

            <x-slot:head>

                <tr>

                    <th>Pinjaman ID</th>

                    <th class="text-center">Jumlah Cicilan</th>

                    <th class="text-center">Periode</th>

                    <th class="text-center">Status</th>

                    <th class="text-center">Tanggal Bayar</th>

                    <th class="text-center">Aksi</th>

                </tr>

            </x-slot:head>


            @forelse($data as $item)

            <tr class="hover:bg-gray-50 transition">

                <td class="font-semibold text-slate-700">
                    #{{ $item->pinjaman_id }}
                </td>

                <td class="text-center font-semibold text-slate-700">
                    Rp {{ number_format($item->jumlah_cicilan,0,',','.') }}
                </td>

                <td class="text-center">
                    {{ $item->periode }}
                </td>

                <td class="text-center">

                    @if($item->status == 'sudah_bayar')
                        <x-badge color="green">Sudah Bayar</x-badge>
                    @else
                        <x-badge color="gray">Belum Bayar</x-badge>
                    @endif

                </td>

                <td class="text-center">
                    {{ $item->tanggal_bayar ?? '-' }}
                </td>

                <td>

                    <form
                        action="{{ route('cicilan-pinjaman.destroy',$item->id) }}"
                        method="POST"
                        onsubmit="return confirm('Yakin ingin menghapus data ini?')"
                    >

                        @csrf
                        @method('DELETE')

                        <x-button variant="delete" size="sm">
                            Hapus
                        </x-button>

                    </form>

                </td>

            </tr>

            @empty

            <tr>

                <td colspan="6" class="text-center text-slate-400 py-6">
                    Data cicilan belum tersedia
                </td>

            </tr>

            @endforelse

        </x-table>

    </x-card>

</div>

@endsection