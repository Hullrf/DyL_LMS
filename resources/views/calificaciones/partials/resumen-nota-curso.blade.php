@if($nota['filas']->isNotEmpty())
<details class="mb-4 rounded-lg border border-gray-200 bg-white">
    <summary class="cursor-pointer px-4 py-3 text-sm font-medium text-dyl-orange-700">Cómo se calcula tu nota</summary>
    <div class="px-4 pb-4">
        <p class="text-xs text-gray-500 mb-3">
            @if($nota['modo'] === 'ponderado')
                Cada actividad pesa lo que definió tu instructor.
            @else
                Cada actividad pesa según su puntaje máximo.
            @endif
        </p>
        @if($nota['promedio_a_la_fecha'] !== null)
            <p class="text-sm font-medium text-gray-900 mb-3">Promedio a la fecha: {{ $nota['promedio_a_la_fecha'] }} %</p>
        @endif
        <table class="min-w-full text-sm">
            <thead>
                <tr class="text-left text-xs uppercase text-gray-500">
                    <th class="py-1 pr-4">Actividad</th>
                    <th class="py-1 pr-4 text-right">Peso</th>
                    <th class="py-1 pr-4 text-right">Nota</th>
                    <th class="py-1 text-right">Aporta</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @foreach($nota['filas'] as $f)
                <tr>
                    <td class="py-1.5 pr-4 text-gray-900">{{ $f->actividad->tituloCompleto() }}</td>
                    <td class="py-1.5 pr-4 text-right text-gray-700">{{ rtrim(rtrim(number_format($f->peso, 2), '0'), '.') }}%</td>
                    <td class="py-1.5 pr-4 text-right text-gray-700">
                        @if($f->estado === 'calificada') {{ number_format($f->nota, 2) }} / {{ number_format($f->max, 2) }}
                        @elseif($f->estado === 'en_revision') En revisión
                        @else — @endif
                    </td>
                    <td class="py-1.5 text-right font-medium text-gray-900">
                        {{ $f->aporta === null ? '—' : number_format($f->aporta, 1) . '%' }}
                    </td>
                </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr class="border-t border-gray-200 font-semibold">
                    <td class="py-2 pr-4" colspan="3">
                        Total del curso
                        @if($nota['peso_calificado'] < $nota['peso_total'] - 0.01)
                            <span class="font-normal text-xs text-gray-500">(parcial: faltan actividades por calificar)</span>
                        @endif
                    </td>
                    <td class="py-2 text-right">{{ number_format($nota['acumulado'], 1) }} / 100</td>
                </tr>
            </tfoot>
        </table>

        @if($nota['requerido'] !== null)
            <p class="mt-3 rounded-lg px-3 py-2 text-sm {{ $nota['alcanzable'] ? 'bg-dyl-orange-50 text-dyl-orange-800' : 'bg-dyl-graphite-100 text-dyl-graphite-700' }}">
                @if($nota['requerido'] <= 0)
                    Ya alcanzaste la nota mínima ({{ $nota['aprobatoria'] }}%).
                @elseif($nota['alcanzable'])
                    Necesitas al menos {{ rtrim(rtrim(number_format($nota['requerido'], 1), '0'), '.') }}% en las actividades pendientes para llegar a la nota mínima ({{ $nota['aprobatoria'] }}%).
                @else
                    Con las actividades pendientes ya no alcanzas la nota mínima ({{ $nota['aprobatoria'] }}%). Habla con tu instructor.
                @endif
            </p>
        @endif
    </div>
</details>
@endif
