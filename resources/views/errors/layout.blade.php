{{-- Layout independiente a propósito: un 500 puede venir de la BD o de la sesión, y layouts.app
     consulta ambas (campana de notificaciones). Sin sidebar ni consultas, así nunca falla. --}}
<!DOCTYPE html>
<html lang="es" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('titulo') — D&amp;L Quality Campus</title>
    <script>
        try {
            var t = localStorage.getItem('dyl_theme');
            if (t === 'dark' || (t === null && window.matchMedia('(prefers-color-scheme: dark)').matches)) document.documentElement.classList.add('dark');
        } catch (e) {}
    </script>
    @vite(['resources/css/app.css'])
</head>
<body class="h-full bg-gray-50 flex items-center justify-center px-6 font-sans">
    <main class="text-center max-w-md py-12">
        <img src="{{ asset('images/logo/logo-icon-light-256.png') }}" alt="" class="w-20 h-20 mx-auto dark:hidden" aria-hidden="true">
        <img src="{{ asset('images/logo/logo-icon-dark-256.png') }}" alt="" class="w-20 h-20 mx-auto hidden dark:block" aria-hidden="true">
        <p class="mt-6 text-sm font-semibold tracking-widest text-dyl-orange-700">ERROR @yield('codigo')</p>
        <h1 class="mt-2 text-3xl font-bold text-gray-900">@yield('titulo')</h1>
        <p class="mt-3 text-gray-600">@yield('mensaje')</p>
        <div class="mt-8 flex flex-col sm:flex-row gap-3 justify-center">
            <a href="{{ url('/') }}" class="btn btn-primary">Ir al inicio</a>
            @hasSection('accion')
                @yield('accion')
            @endif
        </div>
    </main>
</body>
</html>
