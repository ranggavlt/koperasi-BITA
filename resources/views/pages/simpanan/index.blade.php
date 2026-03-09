@extends('layout.main')

@section('content')

<div class="w-full px-6 py-6 mx-auto">

    {{-- Header --}}
    <div class="flex items-center justify-between mb-6">

        <h2 class="text-xl font-bold text-slate-700">
            Transaksi Simpanan
        </h2>

        <x-button href="{{ route('simpanan.create') }}">
            + Tambah Simpanan
        </x-button>

    </div>


    <x-card title="Daftar Transaksi Simpanan">

        <x-table>

            <x-slot:head>

                <tr>

                    <th>Anggota</th>

                    <th>Jenis Simpanan</th>

                    <th class="text-center">Tanggal</th>

                    <th class="text-center">Jumlah</th>

                    <th>Keterangan</th>

                    <th class="text-center">Aksi</th>

                </tr>

            </x-slot:head>


            @forelse($data as $item)

            <tr class="hover:bg-gray-50 transition">

                <td class="font-semibold text-slate-700">
                    {{ $item->karyawan->nama }}
                </td>

                <td>
                    {{ $item->jenisSimpanan->nama_jenis }}
                </td>

                <td class="text-center">
                    {{ $item->tanggal }}
                </td>

                <td class="text-center font-semibold text-slate-700">
                    Rp {{ number_format($item->jumlah,0,',','.') }}
                </td>

                <td>
                    {{ $item->keterangan ?? '-' }}
                </td>

                <td>

                    <form
                        action="{{ route('simpanan.destroy',$item->id) }}"
                        method="POST"
                        onsubmit="return confirm('Yakin ingin menghapus transaksi ini?')"
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
                    Data simpanan belum tersedia
                </td>

            </tr>

            @endforelse

        </x-table>

    </x-card>

</div>

@endsection