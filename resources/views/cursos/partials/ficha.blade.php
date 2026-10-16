@php
    $f = $curso->ficha;
    $hayFicha = $f && (filled($f->justificacion) || filled($f->resultado_aprendizaje) || !empty($f->resultados_especificos)
        || $f->creditos !== null || $f->semestre !== null || filled($f->prerrequisitos) || filled($f->programa) || filled($f->facultad));
    $hayBiblio = $curso->bibliografias->isNotEmpty();
    $soporte = config('lms.soporte');
    $haySoporte = filled($soporte['correo'] ?? null) || filled($soporte['url'] ?? null) || filled($soporte['horario'] ?? null);
@endphp
@if($hayFicha || $hayBiblio)
<details class="mt-8 rounded-xl bg-white shadow">
    <summary class="cursor-pointer px-6 py-4 text-lg font-bold text-gray-900">Información del curso</summary>
    <div class="space-y-6 px-6 pb-6 text-sm text-gray-700">
        @if($f && filled($f->justificacion))
            <section><h3 class="font-semibold text-gray-900 mb-1">Justificación</h3><p class="whitespace-pre-line">{{ $f->justificacion }}</p></section>
        @endif
        @if($f && filled($f->resultado_aprendizaje))
            <section><h3 class="font-semibold text-gray-900 mb-1">Resultado esperado de aprendizaje</h3><p class="whitespace-pre-line">{{ $f->resultado_aprendizaje }}</p></section>
        @endif
        @if($f && !empty($f->resultados_especificos))
            <section><h3 class="font-semibold text-gray-900 mb-1">Resultados específicos</h3>
                <ul class="list-disc pl-5 space-y-1">@foreach($f->resultados_especificos as $r)<li>{{ $r }}</li>@endforeach</ul></section>
        @endif
        @if($f && ($f->creditos !== null || $f->semestre !== null || filled($f->prerrequisitos) || filled($f->programa) || filled($f->facultad)))
            <section><h3 class="font-semibold text-gray-900 mb-1">Identificación</h3>
                <dl class="grid grid-cols-1 gap-x-6 gap-y-1 sm:grid-cols-2">
                    @if($f->creditos !== null)<div><dt class="inline font-medium">Créditos:</dt> <dd class="inline">{{ $f->creditos }}</dd></div>@endif
                    @if($f->semestre !== null)<div><dt class="inline font-medium">Semestre:</dt> <dd class="inline">{{ $f->semestre }}</dd></div>@endif
                    @if(filled($f->programa))<div><dt class="inline font-medium">Programa:</dt> <dd class="inline">{{ $f->programa }}</dd></div>@endif
                    @if(filled($f->facultad))<div><dt class="inline font-medium">Facultad:</dt> <dd class="inline">{{ $f->facultad }}</dd></div>@endif
                    @if(filled($f->prerrequisitos))<div class="sm:col-span-2"><dt class="inline font-medium">Prerrequisitos:</dt> <dd class="inline">{{ $f->prerrequisitos }}</dd></div>@endif
                </dl></section>
        @endif
        @if($hayBiblio)
            <section><h3 class="font-semibold text-gray-900 mb-1">Bibliografía</h3>
                <table class="min-w-full text-left text-xs">
                    <thead class="text-gray-500"><tr><th class="py-1 pr-3">Autor</th><th class="py-1 pr-3">Año</th><th class="py-1 pr-3">Título</th><th class="py-1">Editorial</th></tr></thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach($curso->bibliografias as $b)
                            <tr><td class="py-1 pr-3">{{ $b->autor }}</td><td class="py-1 pr-3">{{ $b->anio ?? '—' }}</td><td class="py-1 pr-3">{{ $b->titulo }}</td><td class="py-1">{{ $b->editorial ?? '—' }}</td></tr>
                        @endforeach
                    </tbody>
                </table></section>
        @endif
        <section class="grid gap-4 border-t border-gray-100 pt-4 sm:grid-cols-2">
            <div><h3 class="font-semibold text-gray-900 mb-1">Contacte a su profesor</h3>
                <p>{{ $curso->creador->name }}@if(filled($curso->creador->email)) · <a class="text-dyl-orange-700 hover:underline" href="mailto:{{ $curso->creador->email }}">{{ $curso->creador->email }}</a>@endif</p></div>
            @if($haySoporte)
            <div><h3 class="font-semibold text-gray-900 mb-1">Soporte técnico</h3>
                @if(filled($soporte['correo'] ?? null))<p><a class="text-dyl-orange-700 hover:underline" href="mailto:{{ $soporte['correo'] }}">{{ $soporte['correo'] }}</a></p>@endif
                @if(filled($soporte['url'] ?? null))<p><a class="text-dyl-orange-700 hover:underline" href="{{ $soporte['url'] }}" target="_blank" rel="noopener">Centro de soporte</a></p>@endif
                @if(filled($soporte['horario'] ?? null))<p class="text-xs text-gray-500">Horario de atención: {{ $soporte['horario'] }}</p>@endif</div>
            @endif
        </section>
    </div>
</details>
@endif
