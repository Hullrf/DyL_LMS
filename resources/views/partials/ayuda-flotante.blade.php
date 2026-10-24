@php $s = config('lms.soporte'); @endphp
<div x-data="{
        abierto: false,
        texto: '',
        // ponytail: coincidencia por palabras clave; si se necesitan respuestas más finas, mover a backend/IA
        faq: [
            { q: 'Ver mis calificaciones', k: ['calificacion', 'nota', 'puntaje', 'resultado'], a: 'Entra a «Mis calificaciones» desde el menú. Las notas aparecen cuando el instructor las publica.' },
            { q: 'Entregar una actividad', k: ['entreg', 'enviar', 'subir', 'tarea', 'actividad'], a: 'Abre tu curso, entra a la actividad y pulsa el botón de enviar/completar. Revisa la fecha límite en el Calendario.' },
            { q: 'Fechas y calendario', k: ['fecha', 'calendario', 'plazo', 'limite', 'vence'], a: 'En «Calendario» y «Línea de tiempo» ves las fechas de entrega de todos tus cursos.' },
            { q: 'Descargar mi certificado', k: ['certificado', 'diploma', 'constancia'], a: 'Cuando completes un curso, tu certificado aparece en «Mis certificados» para descargarlo.' },
            { q: 'No puedo ver un curso', k: ['curso', 'acceso', 'inscri', 'matricul', 'no veo', 'no aparece'], a: 'Es posible que aún no estés inscrito. Escribe a tu instructor o al soporte para que te inscriban.' },
            { q: 'Cambiar mi contraseña', k: ['contrasena', 'clave', 'password', 'olvide', 'acceder', 'iniciar'], a: 'Entra a tu perfil (menú de usuario) para cambiarla. Si no puedes iniciar sesión, usa «¿Olvidaste tu contraseña?» en el login.' },
            { q: 'Hablar con una persona', k: ['persona', 'humano', 'agente', 'soporte', 'contacto', 'correo', 'telefono', 'hablar'], a: 'Con gusto. Usa los datos de contacto que aparecen abajo.' },
        ],
        msgs: [{ de: 'bot', t: '¡Hola! Soy el asistente de ayuda. Elige una pregunta o escribe la tuya.' }],
        norm(s) { return s.toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, ''); },
        responder(q, a) {
            this.msgs.push({ de: 'yo', t: q });
            this.msgs.push({ de: 'bot', t: a });
            this.$nextTick(() => { this.$refs.log.scrollTop = this.$refs.log.scrollHeight; });
        },
        enviar() {
            const q = this.texto.trim();
            if (!q) return;
            this.texto = '';
            const n = this.norm(q);
            const hit = this.faq.find(f => f.k.some(w => n.includes(w)));
            this.responder(q, hit ? hit.a : 'No encontré una respuesta exacta. Prueba con otra palabra o contacta a soporte con los datos de abajo.');
        },
    }" class="fixed bottom-4 right-4 z-30 print:hidden">
    <div x-show="abierto" x-cloak @keydown.escape.window="abierto = false"
         role="dialog" aria-label="Ayuda y soporte"
         class="mb-2 w-80 max-w-[calc(100vw-2rem)] rounded-xl bg-white shadow-xl border border-gray-200 text-sm text-gray-700">
        <p class="font-semibold text-gray-900 px-4 pt-4 pb-2">¿Necesitas ayuda?</p>

        <div x-ref="log" aria-live="polite" class="max-h-64 overflow-y-auto px-4 space-y-2">
            <template x-for="(m, i) in msgs" :key="i">
                <div :class="m.de === 'yo' ? 'flex justify-end' : 'flex justify-start'">
                    <span x-text="m.t"
                          :class="m.de === 'yo' ? 'bg-dyl-orange-700 text-white' : 'bg-gray-100 text-gray-800'"
                          class="rounded-lg px-3 py-1.5 max-w-[85%]"></span>
                </div>
            </template>
        </div>

        <div class="px-4 pt-3 flex flex-wrap gap-1.5">
            <template x-for="f in faq.slice(0, 5)" :key="f.q">
                <button type="button" @click="responder(f.q, f.a)" x-text="f.q"
                        class="rounded-full border border-gray-300 px-2.5 py-1 text-xs text-gray-700 hover:bg-gray-50"></button>
            </template>
        </div>

        <form @submit.prevent="enviar()" class="px-4 pt-3 flex gap-2">
            <input x-model="texto" type="text" maxlength="200" placeholder="Escribe tu pregunta…" aria-label="Escribe tu pregunta"
                   class="flex-1 min-w-0 rounded-lg border-gray-300 text-sm focus:border-dyl-orange-600 focus:ring-dyl-orange-600">
            <button type="submit" class="rounded-lg bg-dyl-orange-700 px-3 py-1.5 text-sm font-medium text-white hover:bg-dyl-orange-800">Enviar</button>
        </form>

        <ul class="space-y-1.5 border-t border-gray-100 mt-3 px-4 py-3">
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
            class="ml-auto flex items-center gap-1.5 rounded-full bg-dyl-orange-700 px-4 py-2 text-sm font-medium text-white shadow-lg hover:bg-dyl-orange-800 focus:outline-none focus:ring-2 focus:ring-dyl-orange-600 focus:ring-offset-2">
        <span aria-hidden="true">?</span> Ayuda
    </button>
</div>
