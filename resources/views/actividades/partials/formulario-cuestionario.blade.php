@php
    $preguntas  = $actividad->preguntas()->with('opciones')->orderBy('orden')->get();
    $oldAnswers = old('respuesta') ? (json_decode(old('respuesta'), true) ?? []) : [];
@endphp
<div class="space-y-6">
    @foreach($preguntas as $index => $pregunta)
    @php $oldVal = $oldAnswers[$pregunta->id] ?? null; @endphp
    <div class="bg-white rounded-lg shadow p-6" data-pregunta-id="{{ $pregunta->id }}">
        <p class="font-medium text-gray-900 mb-1">
            {{ $index + 1 }}. {{ $pregunta->pregunta_texto }}
            <span class="text-xs text-gray-400 ml-2">({{ $pregunta->puntaje }} pts)</span>
        </p>

        @if($pregunta->imagen_path)
        <img src="{{ $pregunta->imagenUrl() }}"
             alt="Imagen de apoyo"
             class="my-4 w-full h-64 object-contain rounded-lg border border-gray-200 bg-gray-50">
        @endif

        @if($pregunta->tipo === 'respuesta_corta')
            <input type="text" name="respuesta_{{ $pregunta->id }}"
                   value="{{ old('respuesta_' . $pregunta->id) }}"
                   class="mt-3 w-full px-3 py-2 border border-gray-300 rounded-lg text-sm" required>

        @elseif($pregunta->seleccion_multiple)
            <p class="mt-2 text-xs text-dyl-graphite-600 font-medium">
                Selecciona todas las respuestas correctas.
            </p>
            <div class="mt-2 space-y-2">
                @foreach($pregunta->opciones as $opcion)
                @php $checked = is_array($oldVal) && in_array((string)$opcion->id, array_map('strval', $oldVal)); @endphp
                <label class="flex items-center gap-3 p-3 border border-gray-200 rounded-lg cursor-pointer hover:bg-dyl-orange-50 transition-colors">
                    <input type="checkbox"
                           name="respuesta_{{ $pregunta->id }}[]"
                           value="{{ $opcion->id }}"
                           {{ $checked ? 'checked' : '' }}
                           class="w-4 h-4 rounded text-dyl-orange-600">
                    <span class="text-sm text-gray-800">{{ $opcion->texto }}</span>
                </label>
                @endforeach
            </div>

        @else
            <div class="mt-3 space-y-2">
                @foreach($pregunta->opciones as $opcion)
                @php $checked = $oldVal !== null && (string)$oldVal === (string)$opcion->id; @endphp
                <label class="flex items-center gap-3 p-3 border border-gray-200 rounded-lg cursor-pointer hover:bg-gray-50 transition-colors">
                    <input type="radio" name="respuesta_{{ $pregunta->id }}" value="{{ $opcion->id }}"
                           {{ $checked ? 'checked' : '' }}
                           class="text-dyl-orange-600" required>
                    <span class="text-sm text-gray-800">{{ $opcion->texto }}</span>
                </label>
                @endforeach
            </div>
        @endif
    </div>
    @endforeach
</div>

<input type="hidden" name="respuesta" id="respuesta-json">
<script>
document.getElementById('form-respuesta').addEventListener('submit', function(e) {
    const esAutoenvio = this.dataset.autoenvio === '1';
    const data = {};
    const pendientes = [];

    this.querySelectorAll('[data-pregunta-id]').forEach(function(contenedor) {
        const id = contenedor.dataset.preguntaId;
        const texto = contenedor.querySelector('input[type=text][name="respuesta_' + id + '"]');
        const marcados = contenedor.querySelectorAll('input[type=radio]:checked, input[type=checkbox]:checked');

        contenedor.classList.remove('ring-2', 'ring-dyl-graphite-500');

        if (texto) {
            if (texto.value.trim()) data[id] = texto.value.trim();
            else pendientes.push(id);
        } else if (marcados.length > 0) {
            data[id] = marcados[0].type === 'checkbox'
                ? Array.from(marcados).map(function(el) { return el.value; })
                : marcados[0].value;
        } else {
            pendientes.push(id);
        }
    });

    if (!esAutoenvio && pendientes.length > 0) {
        e.preventDefault();
        let primero = null;
        pendientes.forEach(function(id) {
            const contenedor = document.querySelector('[data-pregunta-id="' + id + '"]');
            if (contenedor) {
                contenedor.classList.add('ring-2', 'ring-dyl-graphite-500');
                if (!primero) primero = contenedor;
            }
        });
        if (primero) primero.scrollIntoView({ behavior: 'smooth', block: 'center' });
        alert('Debes responder todas las preguntas antes de enviar.');
        return;
    }

    if (!esAutoenvio && !confirm('¿Seguro que quieres enviar tus respuestas? Esta acción no se puede deshacer.')) {
        e.preventDefault();
        return;
    }

    document.getElementById('respuesta-json').value = JSON.stringify(data);
});
</script>
