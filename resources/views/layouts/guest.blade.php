<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>{{ config('app.name', 'D&amp;L Quality Campus') }}</title>
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet" />
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        <script>
    (function () {
        try {
            var t = localStorage.getItem('dyl_theme');
            if (t === 'dark' || (t === null && window.matchMedia('(prefers-color-scheme: dark)').matches)) document.documentElement.classList.add('dark');
        } catch (e) {}
    })();
    document.addEventListener('DOMContentLoaded', function () {
        var d = document.documentElement.classList.contains('dark');
        document.querySelectorAll('[data-theme-toggle]').forEach(function (b) { b.setAttribute('aria-pressed', d); });
    });
    // Otra pestaña cambió el tema: reflejarlo aquí.
    window.addEventListener('storage', function (e) {
        if (e.key !== 'dyl_theme') return;
        var d = e.newValue === 'dark';
        document.documentElement.classList.toggle('dark', d);
        document.querySelectorAll('[data-theme-toggle]').forEach(function (b) { b.setAttribute('aria-pressed', d); });
        window.dispatchEvent(new Event('themechange'));
    });
    function dylToggleTheme() {
        var d = document.documentElement.classList.toggle('dark');
        try { localStorage.setItem('dyl_theme', d ? 'dark' : 'light'); } catch (e) {}
        window.dispatchEvent(new Event('themechange'));
        document.querySelectorAll('[data-theme-toggle]').forEach(function (b) { b.setAttribute('aria-pressed', d); });
    }
    </script>
    <style>[x-cloak]{display:none!important}</style>
    </head>
    <body class="h-full font-sans antialiased">

        <div class="min-h-screen flex">

            {{-- Panel izquierdo decorativo (oculto en móvil) --}}
            <div class="hidden lg:flex lg:w-1/2 bg-dyl-graphite-900 flex-col justify-between p-12 relative overflow-hidden">
                {{-- Imagen de fondo + degradado para legibilidad --}}
                <img src="{{ asset('images/login-hero.png') }}" alt="" class="absolute inset-0 w-full h-full object-cover" aria-hidden="true">
                <div class="absolute inset-0 bg-gradient-to-b from-dyl-graphite-900/80 via-dyl-graphite-900/40 to-dyl-graphite-900/90"></div>

                {{-- Logo --}}
                <a href="/" class="flex items-center gap-3 relative z-10">
                    <img src="{{ asset('images/logo/logo-icon-dark-256.png') }}" alt="" class="w-20 h-20" aria-hidden="true">
                    <span class="text-white font-bold text-xl tracking-tight">Quality Campus</span>
                </a>

                {{-- Mensaje central --}}
                <div class="relative z-10">
                    <blockquote>
                        <p class="text-2xl font-semibold text-white leading-snug mb-4">
                            "La calidad nunca es un accidente;<br>
                            siempre es el resultado de un<br>
                            esfuerzo inteligente."
                        </p>
                        <footer class="text-white/70 text-sm">— John Ruskin</footer>
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
                    <img src="{{ asset('images/logo/logo-icon-light-256.png') }}" alt="" class="w-16 h-16" aria-hidden="true">
                    <span class="text-dyl-graphite-900 font-bold text-lg">Quality Campus</span>
                </a>

                <div class="w-full max-w-md">
                    {{ $slot }}
                </div>
            </div>
        </div>

    </body>
</html>
