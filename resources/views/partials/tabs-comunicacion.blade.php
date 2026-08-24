{{--
    Selector de pestañas compartido entre mensajes/bandeja.blade.php y
    anuncios/todos.blade.php — el sidebar solo tiene una entrada
    ("Mensajes") que lleva a la bandeja; este selector es lo que permite
    moverse a Anuncios sin agregar una segunda sección al sidebar.
--}}
<div class="flex gap-2 mb-6">
    <a href="{{ route('mensajes.bandeja') }}"
       class="px-4 py-2 rounded-lg text-sm font-medium {{ request()->routeIs('mensajes.*') ? 'bg-dyl-orange-600 text-white' : 'bg-white text-gray-700 border hover:bg-gray-50' }}">
        Mensajes
    </a>
    <a href="{{ route('anuncios.todos') }}"
       class="px-4 py-2 rounded-lg text-sm font-medium {{ request()->routeIs('anuncios.todos') ? 'bg-dyl-orange-600 text-white' : 'bg-white text-gray-700 border hover:bg-gray-50' }}">
        Anuncios
    </a>
</div>
