@if($intentoEnProgreso)
    <form id="form-respuesta" action="{{ route('respuestas.store', $actividad) }}" method="POST" enctype="multipart/form-data">
        @csrf

        @if($segundosRestantes !== null)
        <div x-data="{
                segundos: {{ $segundosRestantes }},
                intervalo: null,
                init() {
                    this.intervalo = setInterval(() => {
                        this.segundos--;
                        if (this.segundos <= 0) {
                            clearInterval(this.intervalo);
                            const form = document.getElementById('form-respuesta');
                            form.noValidate = true;
                            form.dataset.autoenvio = '1';
                            form.requestSubmit();
                        }
                    }, 1000);
                },
                get mmss() {
                    const total = Math.max(0, Math.floor(this.segundos));
                    const m = Math.floor(total / 60);
                    const s = total % 60;
                    return String(m).padStart(2, '0') + ':' + String(s).padStart(2, '0');
                }
             }"
             class="sticky top-4 z-10 mb-4 flex items-center justify-center gap-2 px-4 py-2 rounded-lg font-mono text-lg font-bold"
             :class="segundos <= 60 ? 'bg-red-50 text-red-700 animate-pulse' : 'bg-blue-50 text-blue-700'">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            <span x-text="mmss"></span>
        </div>
        @endif

        @include('actividades.partials.formulario-cuestionario')

        <div class="mt-6 flex justify-end">
            <button type="submit" class="bg-blue-600 text-white px-8 py-3 rounded-lg hover:bg-blue-700 font-medium">
                Enviar respuesta
            </button>
        </div>
    </form>
@else
    @php $totalPreguntas = $actividad->preguntas()->count(); @endphp
    <div class="bg-white rounded-lg shadow p-8 text-center">
        <svg class="w-12 h-12 text-blue-400 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
        </svg>
        <div class="flex justify-center flex-wrap gap-6 text-sm text-gray-600 mb-6">
            <span>
                <strong class="block text-gray-900 text-lg">{{ $actividad->intentos_permitidos }}</strong>
                {{ $actividad->intentos_permitidos === 1 ? 'intento permitido' : 'intentos permitidos' }}
                @if($actividad->permiteMultiplesIntentos())
                    <span class="block text-xs text-gray-400">(ya usaste {{ $intentosUsados }} de {{ $actividad->intentos_permitidos }})</span>
                @endif
            </span>
            @if($actividad->duracion_minutos)
            <span>
                <strong class="block text-gray-900 text-lg">{{ $actividad->duracion_minutos }}</strong>
                minutos
            </span>
            @endif
            <span>
                <strong class="block text-gray-900 text-lg">{{ $totalPreguntas }}</strong>
                {{ $totalPreguntas === 1 ? 'pregunta' : 'preguntas' }}
            </span>
        </div>
        <form action="{{ route('actividades.iniciarIntento', $actividad) }}" method="POST">
            @csrf
            <button type="submit" class="bg-blue-600 text-white px-8 py-3 rounded-lg hover:bg-blue-700 font-medium">
                {{ $respuesta ? 'Reintentar' : 'Iniciar cuestionario' }}
            </button>
        </form>
    </div>
@endif
