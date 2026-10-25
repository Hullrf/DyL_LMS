@if($intentoEnProgreso)
    <form id="form-respuesta" action="{{ route('respuestas.store', $actividad) }}" method="POST" enctype="multipart/form-data">
        @csrf

        @if($segundosRestantes !== null)
        <div x-data="{
                // Ancladas a una hora límite absoluta (no a un contador que se decrementa)
                // para que el tiempo mostrado sea siempre correcto sin importar si el
                // navegador pausó el setInterval mientras la pestaña estuvo en segundo
                // plano o suspendida.
                deadline: Date.now() + {{ $segundosRestantes }} * 1000,
                segundos: {{ $segundosRestantes }},
                intervalo: null,
                actualizar() {
                    this.segundos = Math.max(0, Math.round((this.deadline - Date.now()) / 1000));
                    if (this.segundos <= 0 && this.intervalo) {
                        clearInterval(this.intervalo);
                        this.intervalo = null;
                        const form = document.getElementById('form-respuesta');
                        form.noValidate = true;
                        form.dataset.autoenvio = '1';
                        form.requestSubmit();
                    }
                },
                init() {
                    this.intervalo = setInterval(() => this.actualizar(), 1000);
                    // Al volver a esta pestaña (tras estar suspendida/en segundo plano),
                    // recalculamos de inmediato en vez de esperar al siguiente tick.
                    document.addEventListener('visibilitychange', () => {
                        if (!document.hidden) this.actualizar();
                    });
                },
                get mmss() {
                    const total = Math.max(0, Math.floor(this.segundos));
                    const m = Math.floor(total / 60);
                    const s = total % 60;
                    return String(m).padStart(2, '0') + ':' + String(s).padStart(2, '0');
                }
             }"
             class="sticky top-4 z-10 mb-4 flex items-center justify-center gap-2 px-4 py-2 rounded-lg font-mono text-lg font-bold"
             :class="segundos <= 60 ? 'bg-dyl-graphite-50 border-2 border-dyl-orange-300 text-dyl-graphite-900 font-bold animate-pulse' : 'bg-dyl-graphite-50 text-dyl-graphite-700'">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            <span x-text="mmss"></span>
        </div>
        @endif

        @include('actividades.partials.formulario-cuestionario')

        <div class="mt-6 flex justify-end">
            <button type="submit" class="btn-primary btn-lg">
                Enviar respuesta
            </button>
        </div>
    </form>
@else
    @php $totalPreguntas = $actividad->preguntas()->count(); @endphp
    <div class="card p-8 text-center">
        <svg class="w-12 h-12 text-dyl-graphite-400 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
        </svg>
        <div class="flex justify-center flex-wrap gap-6 text-sm text-gray-600 mb-6">
            @php($intentosPermitidosEstudiante = $actividad->intentosPermitidosPara(auth()->id()))
            <span>
                <strong class="block font-display text-2xl font-semibold text-gray-900 tabular-nums">{{ $intentosPermitidosEstudiante }}</strong>
                {{ $intentosPermitidosEstudiante === 1 ? 'intento permitido' : 'intentos permitidos' }}
                @if($actividad->permiteMultiplesIntentos(auth()->id()))
                    <span class="block text-xs text-gray-400">(ya usaste {{ $intentosUsados }} de {{ $intentosPermitidosEstudiante }})</span>
                @endif
            </span>
            @if($actividad->duracion_minutos)
            <span>
                <strong class="block font-display text-2xl font-semibold text-gray-900 tabular-nums">{{ $actividad->duracion_minutos }}</strong>
                minutos
            </span>
            @endif
            <span>
                <strong class="block font-display text-2xl font-semibold text-gray-900 tabular-nums">{{ $totalPreguntas }}</strong>
                {{ $totalPreguntas === 1 ? 'pregunta' : 'preguntas' }}
            </span>
        </div>
        <form action="{{ route('actividades.iniciarIntento', $actividad) }}" method="POST">
            @csrf
            <button type="submit" class="btn-primary btn-lg">
                {{ $respuesta ? 'Reintentar' : 'Iniciar cuestionario' }}
            </button>
        </form>
    </div>
@endif
