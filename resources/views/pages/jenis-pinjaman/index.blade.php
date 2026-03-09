@extends('layout.main')

@section('content')

<div class="w-full px-6 py-6 mx-auto">

    {{-- Header Page --}}
    <div class="flex items-center justify-between mb-6">

        <h2 class="text-xl font-bold text-slate-700">
            Jenis Pinjaman
        </h2>

        <x-button href="{{ route('jenis-pinjaman.create') }}">
            + Tambah Jenis
        </x-button>

    </div>


    {{-- Card --}}
    <x-card title="Daftar Jenis Pinjaman">

        <x-table>

            {{-- Table Head --}}
            <x-slot:head>

                <tr>

                    <th>Nama Pinjaman</th>

                    <th class="text-center">
                        Bunga
                    </th>

                    <th class="text-center">
                        Tenor
                    </th>

                    <th>
                        Keterangan
                    </th>

                    <th class="text-center">
                        Aksi
                    </th>

                </tr>

            </x-slot:head>


            {{-- Table Body --}}
            @forelse($data as $item)

            <tr class="hover:bg-gray-50 transition">

                {{-- Nama --}}
                <td class="font-semibold text-slate-700">
                    {{ $item->nama_pinjaman }}
                </td>


                {{-- Bunga --}}
                <td class="text-center">
                    {{ $item->bunga_persen }} %
                </td>


                {{-- Tenor --}}
                <td class="text-center">
                    {{ $item->tenor_bulan ?? '-' }} Bulan
                </td>


                {{-- Keterangan --}}
                <td>
                    {{ $item->keterangan ?? '-' }}
                </td>


                {{-- Aksi --}}
                <td>

                    <div class="flex justify-center gap-2">

                        {{-- Edit --}}
                        <x-button
                            variant="edit"
                            size="sm"
                            href="{{ route('jenis-pinjaman.edit',$item->id) }}"
                        >
                            Edit
                        </x-button>

                        {{-- Delete --}}
                        <form
                            action="{{ route('jenis-pinjaman.destroy',$item->id) }}"
                            method="POST"
                            onsubmit="return confirm('Yakin ingin menghapus data ini?')"
                        >

                            @csrf
                            @method('DELETE')

                            <x-button variant="delete" size="sm">
                                Hapus
                            </x-button>

                        </form>

                    </div>

                </td>

            </tr>

            @empty

            <tr>

                <td colspan="5" class="text-center text-slate-400 py-6">

                    Data jenis pinjaman belum tersedia

                </td>

            </tr>

            @endforelse


        </x-table>

    </x-card>

</div>

@endsection