{{--
    Selector de pestañas compartido entre mensajes/bandeja.blade.php y
    anuncios/todos.blade.php — el sidebar solo tiene una entrada
    ("Mensajes") que lleva a la bandeja; este selector es lo que permite
    moverse a Anuncios sin agregar una segunda sección al sidebar.
--}}
<nav class="flex gap-6 border-b border-gray-200 mb-6" aria-label="Comunicación">
    <a href="{{ route('mensajes.bandeja') }}"
       @if(request()->routeIs('mensajes.*')) aria-current="page" @endif
       class="-mb-px pb-3 text-sm font-medium border-b-2 {{ request()->routeIs('mensajes.*') ? 'border-dyl-orange-700 text-dyl-orange-700' : 'border-transparent text-gray-600 hover:text-gray-900 hover:border-gray-300' }}">
        Mensajes
    </a>
    <a href="{{ route('anuncios.todos') }}"
       @if(request()->routeIs('anuncios.todos')) aria-current="page" @endif
       class="-mb-px pb-3 text-sm font-medium border-b-2 {{ request()->routeIs('anuncios.todos') ? 'border-dyl-orange-700 text-dyl-orange-700' : 'border-transparent text-gray-600 hover:text-gray-900 hover:border-gray-300' }}">
        Anuncios
    </a>
</nav>
