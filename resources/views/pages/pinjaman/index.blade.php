@extends('layout.main')

@section('content')

<div class="w-full px-6 py-6 mx-auto">

    {{-- Header --}}
    <div class="flex items-center justify-between mb-6">

        <h2 class="text-xl font-bold text-slate-700">
            Transaksi Pinjaman
        </h2>

        <x-button href="{{ route('pinjaman.create') }}">
            + Tambah Pinjaman
        </x-button>

    </div>


    <x-card title="Daftar Pinjaman">

        <x-table>

            <x-slot:head>

                <tr>

                    <th>Anggota</th>

                    <th class="text-center">Tanggal</th>

                    <th class="text-center">Jumlah</th>

                    <th class="text-center">Bunga</th>

                    <th class="text-center">Tenor</th>

                    <th class="text-center">Sisa Pinjaman</th>

                    <th class="text-center">Status</th>

                    <th>Keterangan</th>

                    <th class="text-center">Aksi</th>

                </tr>

            </x-slot:head>


            @forelse($data as $item)

            <tr class="hover:bg-gray-50 transition">

                <td class="font-semibold text-slate-700">
                    {{ $item->karyawan->nama }}
                </td>

                <td class="text-center">
                    {{ $item->tanggal_pinjaman }}
                </td>

                <td class="text-center font-semibold text-slate-700">
                    Rp {{ number_format($item->jumlah_pinjaman,0,',','.') }}
                </td>

                <td class="text-center">
                    {{ $item->bunga_persen }} %
                </td>

                <td class="text-center">
                    {{ $item->tenor_bulan }} Bulan
                </td>

                <td class="text-center font-semibold text-slate-700">
                    Rp {{ number_format($item->sisa_pinjaman,0,',','.') }}
                </td>

                <td class="text-center">

                    @if($item->status == 'aktif')
                        <x-badge color="green">Aktif</x-badge>
                    @else
                        <x-badge color="gray">Lunas</x-badge>
                    @endif

                </td>

                <td>
                    {{ $item->keterangan ?? '-' }}
                </td>

                <td>

                    <form
                        action="{{ route('pinjaman.destroy',$item->id) }}"
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

                <td colspan="9" class="text-center text-slate-400 py-6">
                    Data pinjaman belum tersedia
                </td>

            </tr>

            @endforelse

        </x-table>

    </x-card>

</div>

@endsection