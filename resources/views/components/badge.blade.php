@props([
    'color' => 'gray'
])

@php

$base = "px-2 py-1 text-xs font-semibold rounded-lg inline-block";

$colors = [

    'green' => 'bg-green-100 text-green-700',

    'blue' => 'bg-blue-100 text-blue-700',

    'yellow' => 'bg-yellow-100 text-yellow-700',

    'red' => 'bg-red-100 text-red-700',

    'gray' => 'bg-gray-100 text-gray-700',

];

$class = $base . ' ' . $colors[$color];

@endphp

<span {{ $attributes->merge(['class' => $class]) }}>

    {{ $slot }}

</span>