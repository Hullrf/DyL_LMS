@php
    $descripciones = [
        'vencimientos'   => 'Cuando una entrega tuya vence en las próximas 24 horas.',
        'calificaciones' => 'Cuando se califica una actividad o un cuestionario, o se habilita un intento extra.',
        'anuncios'       => 'Cuando un instructor publica un anuncio en alguno de tus cursos.',
        'certificados'   => 'Cuando tu certificado está listo o falta completar tu perfil para generarlo.',
    ];
@endphp
<section>
    <header class="mb-6">
        <h2 class="font-display text-2xl font-semibold text-gray-900">Avisos por correo</h2>
        <p class="mt-1 text-sm text-gray-600">
            Elige qué avisos del campus quieres recibir también en {{ $user->email }}.
            Siempre los verás en la campana de notificaciones.
        </p>
    </header>

    <form method="post" action="{{ route('profile.avisos-correo') }}" class="space-y-4">
        @csrf
        @method('patch')

        @foreach (\App\Services\AvisosPorCorreoService::CATEGORIAS as $clave => $etiqueta)
            <label for="avisos_{{ $clave }}" class="flex items-start gap-3 cursor-pointer">
                <input
                    id="avisos_{{ $clave }}"
                    type="checkbox"
                    name="avisos[]"
                    value="{{ $clave }}"
                    @checked($user->recibeCorreo($clave))
                    class="mt-1 rounded text-dyl-orange-700"
                >
                <span>
                    <span class="form-label">{{ $etiqueta }}</span>
                    <span class="block text-sm text-gray-600">{{ $descripciones[$clave] }}</span>
                </span>
            </label>
        @endforeach

        @error('avisos.*')
            <p class="text-sm text-dyl-graphite-900 font-semibold">{{ $message }}</p>
        @enderror

        <div class="flex items-center gap-4 pt-2">
            <button type="submit" class="btn-primary">Guardar preferencias</button>

            @if (session('status') === 'avisos-correo-updated')
                <p
                    x-data="{ show: true }"
                    x-show="show"
                    x-transition
                    x-init="setTimeout(() => show = false, 2500)"
                    class="text-sm text-dyl-orange-700 font-medium"
                >Preferencias guardadas.</p>
            @endif
        </div>
    </form>
</section>
