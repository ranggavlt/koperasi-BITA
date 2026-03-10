@extends('layout.main')

@section('content')

<div class="w-full px-6 py-6 mx-auto">

    {{-- HEADER --}}
    <div class="flex items-center justify-between mb-6">

        <h6 class="text-lg font-bold text-slate-700">
            Jenis Simpanan
        </h6>

        <a href="{{ route('jenis-simpanan.create') }}"
           class="px-6 py-3 text-xs font-bold text-white uppercase transition-all rounded-lg shadow-soft-md bg-gradient-to-tl from-purple-700 to-pink-500 hover:shadow-soft-lg">

            + Tambah Jenis

        </a>

    </div>


    {{-- CARD --}}
    <div class="flex flex-wrap -mx-3">

        <div class="flex-none w-full max-w-full px-3">

            <div class="relative flex flex-col min-w-0 mb-6 break-words bg-white border-0 shadow-soft-xl rounded-2xl bg-clip-border">

                {{-- CARD HEADER --}}
                <div class="p-6 pb-0 mb-0 bg-white rounded-t-2xl">

                    <h6 class="font-semibold text-slate-700">
                        Daftar Jenis Simpanan
                    </h6>

                    <p class="text-sm text-slate-400">
                        Master data jenis simpanan koperasi
                    </p>

                </div>


                {{-- CARD BODY --}}
                <div class="flex-auto px-0 pt-0 pb-2">

                    <div class="p-0 overflow-x-auto">

                        <table class="items-center w-full mb-0 align-top border-gray-200 text-slate-500">

                            {{-- TABLE HEAD --}}
                            <thead class="align-bottom">

                                <tr>

                                    <th class="px-6 py-3 text-left text-xxs font-bold uppercase text-slate-400 opacity-70">
                                        Nama Simpanan
                                    </th>

                                    <th class="px-6 py-3 text-center text-xxs font-bold uppercase text-slate-400 opacity-70">
                                        Status
                                    </th>

                                    <th class="px-6 py-3 text-center text-xxs font-bold uppercase text-slate-400 opacity-70">
                                        Nominal Default
                                    </th>

                                    <th class="px-6 py-3 text-center text-xxs font-bold uppercase text-slate-400 opacity-70">
                                        Aksi
                                    </th>

                                </tr>

                            </thead>


                            {{-- TABLE BODY --}}
                            <tbody>

                                @forelse($data as $item)

                                <tr class="border-b">

                                    {{-- NAMA --}}
                                    <td class="p-4 align-middle bg-transparent whitespace-nowrap">

                                        <div class="flex flex-col">

                                            <span class="text-sm font-semibold text-slate-700">
                                                {{ $item->nama_jenis }}
                                            </span>

                                            @if($item->keterangan)

                                            <span class="text-xs text-slate-400">
                                                {{ $item->keterangan }}
                                            </span>

                                            @endif

                                        </div>

                                    </td>


                                    {{-- STATUS --}}
                                    <td class="p-4 text-center align-middle">

                                        @if($item->wajib)

                                            <span class="px-3 py-1 text-xs font-semibold text-green-700 border border-green-500 rounded-lg bg-green-50">

                                                WAJIB

                                            </span>

                                        @else

                                            <span class="px-3 py-1 text-xs font-semibold border rounded-lg text-slate-700 border-slate-400 bg-slate-100">

                                                SUKARELA

                                            </span>

                                        @endif

                                    </td>


                                    {{-- NOMINAL --}}
                                    <td class="p-4 text-center align-middle">

                                        <span class="text-sm font-semibold text-slate-600">

                                            @if($item->nominal_default)

                                                Rp {{ number_format($item->nominal_default,0,',','.') }}

                                            @else

                                                -

                                            @endif

                                        </span>

                                    </td>


                                    {{-- AKSI --}}
                                    <td class="p-4 align-middle">

                                        <div class="flex justify-center gap-2">

                                            {{-- EDIT --}}
                                            <a href="{{ route('jenis-simpanan.edit',$item->id) }}"
                                               class="px-3 py-1 text-xs font-semibold text-blue-600 transition border border-blue-500 rounded-lg hover:bg-blue-50">

                                                Edit

                                            </a>


                                            {{-- DELETE --}}
                                            <form
                                                action="{{ route('jenis-simpanan.destroy',$item->id) }}"
                                                method="POST"
                                                onsubmit="return confirm('Yakin ingin menghapus data ini?')"
                                            >

                                                @csrf
                                                @method('DELETE')

                                                <button
                                                    type="submit"
                                                    class="px-3 py-1 text-xs font-semibold text-red-600 transition border border-red-500 rounded-lg hover:bg-red-50">

                                                    Hapus

                                                </button>

                                            </form>

                                        </div>

                                    </td>

                                </tr>

                                @empty

                                <tr>

                                    <td colspan="4" class="py-6 text-sm text-center text-slate-400">

                                        Data jenis simpanan belum tersedia

                                    </td>

                                </tr>

                                @endforelse

                            </tbody>

                        </table>

                    </div>

                </div>

            </div>

        </div>

    </div>

</div>

@endsectionj