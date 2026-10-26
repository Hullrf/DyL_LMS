@if($actividades->isEmpty())
    <p class="mt-3 text-sm text-gray-500">Este curso aún no tiene actividades para enlazar.</p>
@else
    <fieldset class="mt-3 min-w-0">
        <legend class="text-sm font-medium text-gray-700">Actividades que la evidencian</legend>
        <div class="mt-1 grid gap-1 sm:grid-cols-2">
            @foreach($actividades as $a)
                <label class="flex items-center gap-2 text-sm text-gray-700 min-w-0 break-words">
                    <input type="checkbox" name="actividad_ids[]" value="{{ $a->id }}" class="rounded"
                           @checked(in_array($a->id, $marcadas, true))>
                    <span class="truncate">{{ $a->tituloCompleto() }}</span>
                </label>
            @endforeach
        </div>
    </fieldset>
@endif
