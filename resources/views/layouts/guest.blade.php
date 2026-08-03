<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>{{ config('app.name', 'LMS D&amp;L') }}</title>
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet" />
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        <style>[x-cloak]{display:none!important}</style>
    </head>
    <body class="h-full font-sans antialiased">

        <div class="min-h-screen flex">

            {{-- Panel izquierdo decorativo (oculto en móvil) --}}
            <div class="hidden lg:flex lg:w-1/2 bg-dyl-graphite-900 flex-col justify-between p-12 relative overflow-hidden">
                {{-- Patrón de fondo --}}
                <div class="absolute inset-0 opacity-5">
                    <div class="absolute top-10 left-10 w-64 h-64 rounded-full border-[40px] border-white"></div>
                    <div class="absolute bottom-20 right-10 w-96 h-96 rounded-full border-[60px] border-white"></div>
                    <div class="absolute top-1/2 left-1/3 w-48 h-48 rounded-full border-[30px] border-white"></div>
                </div>

                {{-- Logo --}}
                <a href="/" class="flex items-center gap-3 relative z-10">
                    <div class="bg-dyl-orange-500 rounded-xl flex items-center justify-center px-2.5 h-10">
                        <span class="text-dyl-graphite-900 font-bold text-base tracking-tight">D&amp;L</span>
                    </div>
                    <span class="text-white font-bold text-xl tracking-tight">LMS</span>
                </a>

                {{-- Mensaje central --}}
                <div class="relative z-10">
                    <blockquote>
                        <p class="text-2xl font-semibold text-white leading-snug mb-4">
                            "La calidad nunca es un accidente;<br>
                            siempre es el resultado de un<br>
                            esfuerzo inteligente."
                        </p>
                        <footer class="text-white/50 text-sm">— John Ruskin</footer>
                    </blockquote>
                </div>

                {{-- Features --}}
                <div class="space-y-3 relative z-10">
                    @foreach(['Cursos de normas ISO y gestión de calidad', 'Certificados verificables al completar', 'Seguimiento de progreso en tiempo real'] as $feat)
                    <div class="flex items-center gap-3 text-white/70 text-sm">
                        <svg class="w-5 h-5 text-dyl-orange-400 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                        </svg>
                        {{ $feat }}
                    </div>
                    @endforeach
                </div>
            </div>

            {{-- Panel derecho: formulario --}}
            <div class="flex-1 flex flex-col justify-center items-center px-6 sm:px-12 lg:px-16 bg-gray-50">

                {{-- Logo móvil --}}
                <a href="/" class="flex items-center gap-2 mb-8 lg:hidden">
                    <div class="bg-dyl-orange-500 rounded-lg flex items-center justify-center px-2 h-9">
                        <span class="text-dyl-graphite-900 font-bold tracking-tight">D&amp;L</span>
                    </div>
                    <span class="text-dyl-graphite-900 font-bold text-lg">LMS</span>
                </a>

                <div class="w-full max-w-md">
                    {{ $slot }}
                </div>
            </div>
        </div>

    </body>
</html>
