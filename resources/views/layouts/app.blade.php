<!DOCTYPE html>
<html lang="es" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="description" content="@yield('meta_description', 'DyL Quality Consulting — Plataforma de Aprendizaje')">
    <title>@yield('title', 'LMS DyL')</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <link href="https://cdn.quilljs.com/1.3.7/quill.snow.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/katex@0.16.9/dist/katex.min.css" rel="stylesheet">
    @stack('styles')
    <style>[x-cloak]{display:none!important}</style>
    <style>
        .desc-justify { text-align: justify; }
        .desc-justify .ql-align-center { text-align: center; }
        .desc-justify .ql-align-right { text-align: right; }
        .desc-justify .ql-align-justify { text-align: justify; }
    </style>
    <script>
    function traducirQuill(quill) {
        const tb = quill.container.previousElementSibling;
        if (!tb) return;
        const tips = {
            'bold': 'Negrita', 'italic': 'Cursiva', 'underline': 'Subrayado', 'strike': 'Tachado',
            'blockquote': 'Cita', 'code-block': 'Código',
            'link': 'Enlace', 'image': 'Imagen', 'formula': 'Fórmula',
            'clean': 'Limpiar formato',
            'ql-indent-1': 'Aumentar sangría', 'ql-indent+1': 'Aumentar sangría',
            'ql-indent--1': 'Disminuir sangría',
            'ql-align': 'Alinear',
            'ql-script-sub': 'Subíndice', 'ql-script-super': 'Superíndice',
            'ql-list-ordered': 'Lista numerada', 'ql-list-bullet': 'Viñetas',
            'ql-color': 'Color de texto', 'ql-background': 'Resaltado',
            'ql-font': 'Fuente', 'ql-size': 'Tamaño', 'ql-header': 'Encabezado',
        };
        tb.querySelectorAll('button').forEach(btn => {
            for (const [cls, tip] of Object.entries(tips)) {
                if (btn.classList.contains(cls)) { btn.title = tip; break; }
            }
        });
        const fontSel = tb.querySelector('.ql-font');
        if (fontSel) {
            ['Sans Serif', 'Serif', 'Monoespacio'].forEach((en, i) => {
                const opt = fontSel.querySelectorAll('option')[i + 1];
                if (opt) opt.textContent = ['Sans Serif', 'Serif', 'Monoespacio'][i];
            });
        }
        const sizeSel = tb.querySelector('.ql-size');
        if (sizeSel) {
            ['Pequeño', 'Normal', 'Grande', 'Enorme'].forEach((es, i) => {
                const opt = sizeSel.querySelectorAll('option')[i + 1];
                if (opt) opt.textContent = es;
            });
        }
        const hSel = tb.querySelector('.ql-header');
        if (hSel) {
            ['Título 1', 'Título 2', 'Título 3', 'Normal'].forEach((es, i) => {
                const opt = hSel.querySelectorAll('option')[i + 1];
                if (opt) opt.textContent = es;
            });
        }
    }
    </script>
</head>
<body class="h-full bg-gray-50">

    {{-- Skip to main content (accesibilidad) --}}
    <a href="#main-content"
       class="sr-only focus:not-sr-only focus:fixed focus:top-4 focus:left-4 focus:z-50
              focus:bg-dyl-graphite-900 focus:text-white focus:px-4 focus:py-2 focus:rounded-lg focus:text-sm">
        Saltar al contenido principal
    </a>

    <div class="lg:flex lg:min-h-full">

        @include('layouts.partials.sidebar')

        <div class="flex-1 flex flex-col min-w-0 lg:h-screen lg:overflow-y-auto">

            @include('layouts.partials.topbar')

            {{-- ================================================================
                 CONTENIDO PRINCIPAL
            ================================================================ --}}
            <main id="main-content"
                  role="main"
                  class="flex-1 {{ !isset($fullWidth) ? 'max-w-7xl mx-auto w-full py-6 px-4 sm:px-6 lg:px-8' : '' }}">

                {{-- Breadcrumbs --}}
                @hasSection('breadcrumbs')
                <nav aria-label="Breadcrumb" class="mb-4">
                    @yield('breadcrumbs')
                </nav>
                @endif

                {{-- Mensajes globales --}}
                @unless(isset($fullWidth))
                @if(!empty($errors) && $errors->any())
                    <div class="alert alert-error mb-5" role="alert" aria-live="polite">
                        <ul class="list-disc list-inside space-y-0.5">
                            @foreach($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif
                @if(session('success'))
                    <div class="alert alert-success mb-5" role="status" aria-live="polite">
                        {{ session('success') }}
                    </div>
                @endif
                @if(session('error'))
                    <div class="alert alert-error mb-5" role="alert" aria-live="polite">
                        {{ session('error') }}
                    </div>
                @endif
                @endunless

                @yield('content')
            </main>

            {{-- ================================================================
                 FOOTER
            ================================================================ --}}
            <footer class="bg-dyl-graphite-900 mt-auto" role="contentinfo">
                <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
                    <div class="flex flex-col sm:flex-row justify-between items-center gap-4">
                        <div class="flex items-center gap-2.5">
                            <div class="bg-dyl-orange-500 rounded-md flex items-center justify-center px-1.5 h-7">
                                <span class="text-dyl-graphite-900 font-bold text-xs tracking-tight">D&amp;L</span>
                            </div>
                            <span class="text-white/80 font-semibold text-sm">DyL Quality Consulting</span>
                        </div>
                        <p class="text-white/40 text-xs">
                            &copy; {{ date('Y') }} DyL Quality Consulting LTDA. Todos los derechos reservados.
                        </p>
                        <div class="flex gap-4 text-xs text-white/40">
                            <a href="{{ route('cursos.index') }}" class="hover:text-white/70 transition-colors">Cursos</a>
                            <a href="{{ route('dashboard') }}"    class="hover:text-white/70 transition-colors">Dashboard</a>
                        </div>
                    </div>
                </div>
            </footer>

        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/katex@0.16.9/dist/katex.min.js"></script>
    @stack('scripts')
</body>
</html>
