@props([
    'title' => null
])

<div class="relative flex flex-col min-w-0 mb-6 break-words bg-white border-0 shadow-soft-xl rounded-2xl bg-clip-border">

    {{-- HEADER --}}
    @if($title)
    <div class="p-6 pb-0 mb-0 bg-white border-b-0 rounded-t-2xl">
        <h6 class="font-bold text-slate-700">
            {{ $title }}
        </h6>
    </div>
    @endif

    {{-- BODY --}}
    <div class="flex-auto px-6 pt-4 pb-6">

        {{ $slot }}

    </div>

</div>