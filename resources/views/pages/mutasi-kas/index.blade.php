@extends('layout.main')

@section('content')

<div class="w-full px-6 py-6 mx-auto">

    <div class="flex items-center justify-between mb-6">

        <h2 class="text-xl font-bold text-slate-700">
            Mutasi Kas
        </h2>

        <x-button href="{{ route('mutasi-kas.create') }}">
            + Tambah Mutasi
        </x-button>

    </div>


    <x-card title="Daftar Mutasi Kas">

        <x-table>

            <x-slot:head>

                <tr>

                    <th>Dompet</th>

                    <th class="text-center">Tipe</th>

                    <th class="text-center">Jumlah</th>

                    <th class="text-center">Tanggal</th>

                    <th>Keterangan</th>

                    <th class="text-center">Aksi</th>

                </tr>

            </x-slot:head>


            @forelse($data as $item)

            <tr class="hover:bg-gray-50 transition">

                <td class="font-semibold text-slate-700">
                    {{ $item->dompet->nama_dompet }}
                </td>

                <td class="text-center">

                    @if($item->tipe == 'masuk')
                        <x-badge color="green">Masuk</x-badge>
                    @else
                        <x-badge color="gray">Keluar</x-badge>
                    @endif

                </td>

                <td class="text-center font-semibold text-slate-700">
                    Rp {{ number_format($item->jumlah,0,',','.') }}
                </td>

                <td class="text-center">
                    {{ $item->tanggal }}
                </td>

                <td>
                    {{ $item->keterangan ?? '-' }}
                </td>

                <td>

                    <form
                        action="{{ route('mutasi-kas.destroy',$item->id) }}"
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
                    Data mutasi kas belum tersedia
                </td>

            </tr>

            @endforelse

        </x-table>

    </x-card>

</div>

@endsection