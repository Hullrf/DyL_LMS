{{--
    Vista personalizada de diglactic/laravel-breadcrumbs (config('breadcrumbs.view') = 'breadcrumbs::tailwind').
    Laravel resuelve automáticamente esta ruta antes que la del paquete (namespace 'breadcrumbs').

    Reemplaza la plantilla original del paquete (que trae su propio <nav>, padding y fondo
    bg-gray-300 pensados para ocupar una fila completa) por marcado "desnudo": el contenedor
    <nav aria-label="Breadcrumb"> ya lo aporta quien incluye esta vista (topbar.blade.php),
    así evitamos <nav> anidados y que el fondo/padding del paquete choque con el topbar blanco.
--}}
@unless ($breadcrumbs->isEmpty())
    <ol class="flex flex-wrap items-center gap-1">
        @foreach ($breadcrumbs as $breadcrumb)

            @if ($breadcrumb->url && !$loop->last)
                <li class="truncate max-w-[10rem]">
                    <a href="{{ $breadcrumb->url }}" class="text-dyl-graphite-500 hover:text-dyl-graphite-900 hover:underline focus:text-dyl-graphite-900 focus:underline">
                        {{ $breadcrumb->title }}
                    </a>
                </li>
            @else
                <li class="text-dyl-graphite-700 font-medium truncate max-w-[14rem]">
                    {{ $breadcrumb->title }}
                </li>
            @endif

            @unless($loop->last)
                <li class="text-dyl-graphite-300 px-1" aria-hidden="true">
                    /
                </li>
            @endunless

        @endforeach
    </ol>
@endunless
