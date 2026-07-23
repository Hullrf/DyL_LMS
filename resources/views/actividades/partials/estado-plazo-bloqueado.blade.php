<div class="bg-white rounded-lg shadow p-10 text-center">
    @if($estadoPlazo === 'pendiente')
        <svg class="w-12 h-12 text-yellow-400 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
        </svg>
        <p class="text-gray-700 font-medium">La actividad estará disponible el</p>
        <p class="text-xl font-bold text-yellow-600 mt-1">{{ $actividad->fecha_apertura->format('d/m/Y \a \l\a\s H:i') }}</p>
    @else
        <svg class="w-12 h-12 text-red-400 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 15v2m0 0v2m0-2h2m-2 0H10m2-5V7m0 0V5m0 2h2M12 7H10m10 5a9 9 0 11-18 0 9 9 0 0118 0z"/>
        </svg>
        <p class="text-gray-700 font-medium">El plazo de entrega venció el</p>
        <p class="text-xl font-bold text-red-600 mt-1">{{ $actividad->fecha_cierre->format('d/m/Y H:i') }}</p>
    @endif
</div>
