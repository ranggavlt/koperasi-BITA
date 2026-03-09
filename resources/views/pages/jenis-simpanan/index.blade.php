@extends('layout.main')

@section('content')

<div class="w-full px-6 py-6 mx-auto">

    {{-- Header Page --}}
    <div class="flex items-center justify-between mb-6">

        <h2 class="text-xl font-bold text-slate-700">
            Jenis Simpanan
        </h2>

        <x-button href="{{ route('jenis-simpanan.create') }}">
            + Tambah Jenis
        </x-button>

    </div>


    {{-- Card --}}
    <x-card title="Daftar Jenis Simpanan">

        <x-table>

            {{-- Table Head --}}
            <x-slot:head>

                <tr>

                    <th>Nama Simpanan</th>

                    <th class="text-center">
                        Status
                    </th>

                    <th class="text-center">
                        Nominal Default
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
                <td>

                    <div class="flex flex-col">

                        <span class="font-semibold text-slate-700">
                            {{ $item->nama_jenis }}
                        </span>

                        @if($item->keterangan)

                        <span class="text-xs text-slate-400">
                            {{ $item->keterangan }}
                        </span>

                        @endif

                    </div>

                </td>


                {{-- Status --}}
                <td class="text-center">

                    @if($item->wajib)

                        <x-badge color="green">
                            Wajib
                        </x-badge>

                    @else

                        <x-badge color="gray">
                            Sukarela
                        </x-badge>

                    @endif

                </td>


                {{-- Nominal --}}
                <td class="text-center font-semibold text-slate-700">

                    @if($item->nominal_default)

                        Rp {{ number_format($item->nominal_default,0,',','.') }}

                    @else

                        -

                    @endif

                </td>


                {{-- Aksi --}}
                <td>

                    <div class="flex justify-center gap-2">

                        {{-- Edit --}}
                        <x-button
                            variant="edit"
                            size="sm"
                            href="{{ route('jenis-simpanan.edit',$item->id) }}"
                        >
                            Edit
                        </x-button>

                        {{-- Delete --}}
                        <form
                            action="{{ route('jenis-simpanan.destroy',$item->id) }}"
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

                <td colspan="4" class="text-center text-slate-400 py-6">

                    Data jenis simpanan belum tersedia

                </td>

            </tr>

            @endforelse


        </x-table>

    </x-card>

</div>

@endsection