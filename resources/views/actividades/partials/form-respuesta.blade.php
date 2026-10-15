<form id="form-respuesta" action="{{ $respuesta ? route('respuestas.update', $actividad) : route('respuestas.store', $actividad) }}" method="POST" enctype="multipart/form-data">
    @csrf
    @if($respuesta) @method('PUT') @endif
    <div class="bg-white rounded-lg shadow p-6">
        <label class="block text-sm font-medium text-gray-700 mb-2">Tu respuesta</label>
        <textarea name="respuesta" rows="8"
                  class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-dyl-orange-600"
                  placeholder="Escribe tu respuesta aquí...">{{ old('respuesta', $respuesta?->respuesta) }}</textarea>
        @error('respuesta')<p class="text-dyl-graphite-900 font-semibold text-sm mt-1">{{ $message }}</p>@enderror
    </div>

    @php($maxArchivos = $actividad->max_archivos_adjuntos)
    <div class="bg-white rounded-lg shadow p-6" x-data="{ nombres: [], errorArchivo: '', maxArchivos: {{ $maxArchivos }} }">
        @if($respuesta && $respuesta->archivos->isNotEmpty())
            <p class="text-xs text-gray-500 mb-3">
                Archivos actuales: {{ $respuesta->archivos->pluck('nombre_original')->implode(', ') }}.
                Si subes archivos nuevos, reemplazarán a todos los anteriores (sube aquí todos los que necesites).
            </p>
        @endif
        <p class="text-sm font-medium text-gray-700 mb-3">
            @if($maxArchivos > 1)
                Adjuntar archivos
                <span class="text-gray-400 font-normal">(opcional, hasta {{ $maxArchivos }})</span>
            @else
                Adjuntar archivo
                <span class="text-gray-400 font-normal">(opcional)</span>
            @endif
        </p>
        <label class="flex flex-col items-center justify-center w-full min-h-28 py-4 border-2 border-dashed rounded-xl cursor-pointer transition-colors"
               :class="nombres.length && !errorArchivo
                   ? 'border-dyl-orange-400 bg-dyl-orange-50/40 hover:bg-dyl-orange-50'
                   : errorArchivo
                       ? 'border-dyl-graphite-400 bg-dyl-graphite-100'
                       : 'border-gray-300 bg-gray-50/40 hover:border-dyl-orange-400 hover:bg-dyl-orange-50/30'">
            <div x-show="!nombres.length && !errorArchivo" class="flex flex-col items-center gap-1.5 text-gray-400 pointer-events-none">
                <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                          d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/>
                </svg>
                <p class="text-sm">Haz clic para seleccionar</p>
                <p class="text-xs">Imagen, PDF, Word, video — máx. 50 MB{{ $maxArchivos > 1 ? " c/u, hasta {$maxArchivos} archivos" : '' }}</p>
            </div>
            <div x-show="nombres.length && !errorArchivo" class="flex flex-col items-start gap-1 px-4 text-dyl-orange-700 pointer-events-none w-full">
                <template x-for="nombre in nombres" :key="nombre">
                    <div class="flex items-center gap-2">
                        <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        <span class="text-sm font-medium truncate max-w-xs" x-text="nombre"></span>
                    </div>
                </template>
            </div>
            <div x-show="errorArchivo" class="flex items-center gap-2 px-4 text-dyl-graphite-900 font-semibold pointer-events-none">
                <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <span class="text-sm font-medium" x-text="errorArchivo"></span>
            </div>
            <input type="file" name="archivos_adjuntos[]" class="sr-only"
                   @if($maxArchivos > 1) multiple @endif
                   accept="image/*,video/*,.pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.zip"
                   @change="
                       errorArchivo = '';
                       const archivos = Array.from($event.target.files);
                       if (archivos.length > maxArchivos) {
                           errorArchivo = `Puedes adjuntar hasta ${maxArchivos} archivo(s).`;
                           $event.target.value = '';
                           nombres = [];
                       } else if (archivos.some(f => f.size > 50 * 1024 * 1024)) {
                           errorArchivo = 'Uno o más archivos superan el límite de 50 MB.';
                           $event.target.value = '';
                           nombres = [];
                       } else {
                           nombres = archivos.map(f => f.name);
                       }
                   ">
        </label>
        <p x-show="errorArchivo" x-text="errorArchivo" class="text-dyl-graphite-900 font-semibold text-xs mt-1"></p>
        @error('archivos_adjuntos')<p class="text-dyl-graphite-900 font-semibold text-sm mt-1">{{ $message }}</p>@enderror
        @error('archivos_adjuntos.*')<p class="text-dyl-graphite-900 font-semibold text-sm mt-1">{{ $message }}</p>@enderror
    </div>

    <div class="mt-6 flex justify-end">
        <button type="submit" class="bg-dyl-orange-600 text-white px-8 py-3 rounded-lg hover:bg-dyl-orange-700 font-medium">
            {{ $respuesta ? 'Guardar cambios' : 'Enviar respuesta' }}
        </button>
    </div>
</form>
