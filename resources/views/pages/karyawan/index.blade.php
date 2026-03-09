@extends('layout.main')

@section('content')

<div class="w-full px-6 py-6 mx-auto">

    {{-- Header Page --}}
    <div class="flex items-center justify-between mb-6">

        <h2 class="text-xl font-bold text-slate-700">
            Anggota Koperasi
        </h2>

        <x-button href="{{ route('karyawan.create') }}">
            + Tambah Anggota
        </x-button>

    </div>


    {{-- Card --}}
    <x-card title="Daftar Anggota">

        <x-table>

            {{-- Table Head --}}
            <x-slot:head>

                <tr>

                    <th>Nama</th>

                    <th>Email</th>

                    <th class="text-center">
                        Telepon
                    </th>

                    <th class="text-center">
                        Jabatan
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
                    {{ $item->nama }}
                </td>


                {{-- Email --}}
                <td>
                    {{ $item->email ?? '-' }}
                </td>


                {{-- Telepon --}}
                <td class="text-center">
                    {{ $item->telepon ?? '-' }}
                </td>


                {{-- Jabatan --}}
                <td class="text-center">
                    {{ $item->jabatan ?? '-' }}
                </td>


                {{-- Aksi --}}
                <td>

                    <div class="flex justify-center gap-2">

                        {{-- Edit --}}
                        <x-button
                            variant="edit"
                            size="sm"
                            href="{{ route('karyawan.edit',$item->id) }}"
                        >
                            Edit
                        </x-button>

                        {{-- Delete --}}
                        <form
                            action="{{ route('karyawan.destroy',$item->id) }}"
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

                    Data anggota belum tersedia

                </td>

            </tr>

            @endforelse


        </x-table>

    </x-card>

</div>

@endsection