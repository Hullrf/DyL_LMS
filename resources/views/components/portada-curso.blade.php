@props(['curso'])
{{-- Portada de respaldo: anillos concéntricos que repiten el aro del logo. La posición y el
     arco naranja salen del id del curso, así cada curso se reconoce sin subir imagen. --}}
@php
    $cx = 250 + ($curso->id * 47) % 140;
    $cy = 60 + ($curso->id * 31) % 80;
    $giro = ($curso->id * 53) % 360;
@endphp
<svg {{ $attributes->merge(['class' => 'block w-full h-full']) }} viewBox="0 0 400 200" preserveAspectRatio="xMidYMid slice" role="img" aria-label="Portada de {{ $curso->titulo }}">
    <rect width="400" height="200" fill="#080E1C"/>
    <g fill="none" stroke="#F97316" transform="rotate({{ $giro }} {{ $cx }} {{ $cy }})">
        @foreach([30, 52, 76, 102, 130, 160] as $i => $r)
            <circle cx="{{ $cx }}" cy="{{ $cy }}" r="{{ $r }}" stroke-opacity="{{ number_format(0.5 - $i * 0.07, 2) }}" stroke-width="1"/>
        @endforeach
        <circle cx="{{ $cx }}" cy="{{ $cy }}" r="76" stroke-width="5" stroke-linecap="round" stroke-dasharray="150 330"/>
    </g>
</svg>
