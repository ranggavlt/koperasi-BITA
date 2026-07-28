@props([
    'variant' => 'primary',
    'size' => 'md',
    'href' => null,
    'type' => 'button'
])

@php

$base = "inline-flex items-center justify-center font-semibold rounded-lg transition-all duration-200 focus:outline-none";

$variants = [

    'primary' => 'kbsm-button-primary !text-white shadow-soft-md',

    'secondary' => 'bg-gray-200 hover:bg-gray-300 !text-gray-700',

    'success' => 'bg-green-500 hover:bg-green-600 !text-white',

    'edit' => 'bg-yellow-400 hover:bg-yellow-500 !text-white',

    'delete' => 'bg-red-500 hover:bg-red-600 !text-white',

    'outline' => 'border border-gray-300 hover:bg-gray-100 !text-gray-700'

];

$sizes = [

    'sm' => 'px-3 py-1 text-xs',

    'md' => 'px-4 py-2 text-sm',

    'lg' => 'px-6 py-3 text-sm'

];

$class = $base.' '.$variants[$variant].' '.$sizes[$size];

@endphp


@if($href)

<a href="{{ $href }}" {{ $attributes->merge(['class' => $class]) }}>
    
    {{ $slot }}

</a>

@else

<button type="{{ $type }}" {{ $attributes->merge(['class' => $class]) }}>
    
    {{ $slot }}

</button>

@endif
