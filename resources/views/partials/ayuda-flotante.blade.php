@php $s = config('lms.soporte'); @endphp
<div x-data="{ abierto: false }" class="fixed bottom-4 right-4 z-30 print:hidden">
    <div x-show="abierto" x-cloak @keydown.escape.window="abierto = false"
         role="dialog" aria-label="Ayuda y soporte"
         class="mb-2 w-72 rounded-xl bg-white shadow-xl border border-gray-200 p-4 text-sm text-gray-700">
        <p class="font-semibold text-gray-900 mb-2">¿Necesitas ayuda?</p>
        <ul class="space-y-1.5">
            @if(!empty($s['url']))
                <li><a href="{{ $s['url'] }}" target="_blank" rel="noopener" class="text-dyl-orange-700 hover:underline">Centro de soporte</a></li>
            @endif
            @if(!empty($s['correo']))
                <li><a href="mailto:{{ $s['correo'] }}" class="text-dyl-orange-700 hover:underline">{{ $s['correo'] }}</a></li>
            @endif
            @if(!empty($s['faq_url']))
                <li><a href="{{ $s['faq_url'] }}" target="_blank" rel="noopener" class="text-dyl-orange-700 hover:underline">Preguntas frecuentes</a></li>
            @endif
            @if(!empty($s['horario']))
                <li class="text-xs text-gray-500 pt-1">Horario de atención: {{ $s['horario'] }}</li>
            @endif
            @if(empty($s['url']) && empty($s['correo']) && empty($s['faq_url']) && empty($s['horario']))
                <li class="text-gray-500">Escribe a tu instructor desde Mensajes.</li>
            @endif
        </ul>
    </div>
    <button type="button" @click="abierto = !abierto" :aria-expanded="abierto"
            class="ml-auto flex items-center gap-1.5 rounded-full bg-dyl-orange-600 px-4 py-2 text-sm font-medium text-white shadow-lg hover:bg-dyl-orange-700 focus:outline-none focus:ring-2 focus:ring-dyl-orange-600 focus:ring-offset-2">
        <span aria-hidden="true">?</span> Ayuda
    </button>
</div>
