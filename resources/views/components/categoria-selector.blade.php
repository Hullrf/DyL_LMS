{{--
    Selector de categoría con autocompletado + creación inline.
    - Escribe para filtrar las categorías existentes.
    - Si el texto no coincide con ninguna, el botón "Crear" se habilita y crea
      la categoría al instante (POST /categorias) sin recargar la página.
    - El id real seleccionado viaja en un input oculto `categoria_id`, así que
      el formulario que envuelve este componente no necesita cambios.
--}}
@props(['categorias', 'selectedId' => null])

@php
    $categoriaSeleccionada = $selectedId ? $categorias->firstWhere('id', (int) $selectedId) : null;
@endphp

<div
    x-data="categoriaSelector({
        categorias: {{ \Illuminate\Support\Js::from($categorias->map(fn($c) => ['id' => $c->id, 'nombre' => $c->nombre])->values()) }},
        selectedId: {{ \Illuminate\Support\Js::from($categoriaSeleccionada?->id) }},
        selectedNombre: {{ \Illuminate\Support\Js::from($categoriaSeleccionada?->nombre ?? '') }},
        crearUrl: {{ \Illuminate\Support\Js::from(route('categorias.store')) }},
    })"
    x-on:click.outside="open = false"
    class="relative"
>
    <input type="hidden" name="categoria_id" x-bind:value="selectedId ?? ''">

    <div class="flex gap-2">
        <div class="relative flex-1">
            <input
                type="text"
                x-model="query"
                x-on:input="onInput"
                x-on:focus="open = true"
                x-on:keydown.escape="open = false"
                autocomplete="off"
                placeholder="Buscar o escribir nueva categoría..."
                class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-dyl-orange-600 focus:border-transparent"
            >
            <div
                x-show="open && filtradas.length > 0"
                x-cloak
                class="absolute z-10 mt-1 w-full bg-white border border-gray-200 rounded-lg shadow-lg max-h-48 overflow-y-auto"
            >
                <template x-for="cat in filtradas" :key="cat.id">
                    <button
                        type="button"
                        x-on:click="seleccionar(cat)"
                        class="block w-full text-left px-3 py-2 text-sm hover:bg-gray-50"
                        x-text="cat.nombre"
                    ></button>
                </template>
            </div>
        </div>
        <button
            type="button"
            x-on:click="crear"
            x-bind:disabled="!puedeCrear"
            class="px-3 py-2 text-sm rounded-lg border border-dyl-orange-600 text-dyl-orange-600 disabled:opacity-40 disabled:cursor-not-allowed hover:bg-dyl-orange-50 whitespace-nowrap"
        >
            <span x-show="!creando">Crear</span>
            <span x-show="creando" x-cloak>Creando...</span>
        </button>
    </div>

    <p x-show="error" x-text="error" x-cloak class="form-error text-sm mt-1"></p>
</div>
