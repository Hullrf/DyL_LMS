<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>{{ config('app.name', 'D&amp;L Quality Campus') }}</title>
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700|newsreader:500,600,700&display=swap" rel="stylesheet" />
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
                <div class="relative z-10 max-w-md">
                    <blockquote>
                        <p class="font-display text-4xl font-medium text-white leading-tight mb-5">
                            La calidad nunca es un accidente; siempre es el resultado de un esfuerzo inteligente.
                        </p>
                        <footer class="text-white/70 text-sm">John Ruskin</footer>
                    </blockquote>
                </div>

                {{-- Qué ofrece el campus --}}
                <ul class="relative z-10 text-white/80 text-sm divide-y divide-white/15 border-y border-white/15 max-w-md">
                    @foreach(['Cursos de normas ISO y gestión de calidad', 'Certificados verificables al completar', 'Seguimiento de progreso en tiempo real'] as $feat)
                        <li class="py-3">{{ $feat }}</li>
                    @endforeach
                </ul>
            </div>

            {{-- Panel derecho: formulario --}}
            <div class="flex-1 flex flex-col justify-center items-center px-6 sm:px-12 lg:px-16 bg-gray-50">

                {{-- Logo móvil --}}
                <a href="/" class="flex items-center gap-2 mb-8 lg:hidden">
                    <img src="{{ asset('images/logo/logo-icon-light-256.png') }}" alt="" class="w-16 h-16 dark:hidden" aria-hidden="true">
                    <img src="{{ asset('images/logo/logo-icon-dark-256.png') }}" alt="" class="w-16 h-16 hidden dark:block" aria-hidden="true">
                    <span class="text-dyl-graphite-900 font-bold text-lg">Quality Campus</span>
                </a>

                <div class="w-full max-w-md">
                    {{ $slot }}
                </div>
            </div>
        </div>

    </body>
</html>
