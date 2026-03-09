@extends('layout.main')

@section('content')

<div class="w-full px-6 py-6 mx-auto">

    {{-- Header Page --}}
    <div class="flex items-center justify-between mb-6">

        <h2 class="text-xl font-bold text-slate-700">
            Dompet Koperasi
        </h2>

        <x-button href="{{ route('dompet-koperasi.create') }}">
            + Tambah Dompet
        </x-button>

    </div>


    {{-- Card --}}
    <x-card title="Daftar Dompet Koperasi">

        <x-table>

            {{-- Table Head --}}
            <x-slot:head>

                <tr>

                    <th>Nama Dompet</th>

                    <th class="text-center">
                        Saldo
                    </th>

                    <th class="text-center">
                        Aksi
                    </th>

                </tr>

            </x-slot:head>


            {{-- Table Body --}}
            @forelse($data as $item)

            <tr class="hover:bg-gray-50 transition">

                {{-- Nama Dompet --}}
                <td class="font-semibold text-slate-700">
                    {{ $item->nama_dompet }}
                </td>


                {{-- Saldo --}}
                <td class="text-center font-semibold text-slate-700">
                    Rp {{ number_format($item->saldo,0,',','.') }}
                </td>


                {{-- Aksi --}}
                <td>

                    <div class="flex justify-center gap-2">

                        {{-- Edit --}}
                        <x-button
                            variant="edit"
                            size="sm"
                            href="{{ route('dompet-koperasi.edit',$item->id) }}"
                        >
                            Edit
                        </x-button>


                        {{-- Delete --}}
                        <form
                            action="{{ route('dompet-koperasi.destroy',$item->id) }}"
                            method="POST"
                            onsubmit="return confirm('Yakin ingin menghapus dompet ini?')"
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

                <td colspan="3" class="text-center text-slate-400 py-6">

                    Data dompet koperasi belum tersedia

                </td>

            </tr>

            @endforelse


        </x-table>

    </x-card>

</div>

@endsection