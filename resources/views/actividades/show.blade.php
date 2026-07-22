@extends('layouts.app')
@section('title', $actividad->titulo . ' - LMS DyL')
@section('breadcrumbs'){{ Breadcrumbs::render('actividades.show', $actividad) }}@endsection
@section('content')
<div class="max-w-3xl mx-auto">
    <div class="mb-4 flex items-center justify-between">
        <a href="{{ route('lecciones.show', $actividad->leccion) }}" class="inline-flex items-center text-blue-600 hover:text-blue-800 text-sm">
            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
            Volver a la lección
        </a>
        <a href="{{ route('cursos.show', $actividad->leccion->modulo->curso) }}" class="text-gray-500 hover:text-gray-700 text-sm">
            Ir al curso
        </a>
    </div>

    {{-- Encabezado de la actividad --}}
    <div class="bg-white rounded-lg shadow p-8 mb-6">
        <div class="flex items-start justify-between mb-4">
            <div>
                <span class="text-xs font-medium uppercase text-gray-500">{{ $actividad->tipo }}</span>
                <h1 class="text-2xl font-bold text-gray-900 mt-1">{{ $actividad->titulo }}</h1>
            </div>
            <div class="text-right">
                @if($actividad->tieneCalificacion())
                    <p class="text-2xl font-bold text-blue-600">{{ $actividad->puntaje_maximo }}</p>
                    <p class="text-xs text-gray-500">puntos</p>
                @else
                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-gray-100 text-gray-600">
                        Sin calificación
                    </span>
                @endif
            </div>
        </div>
        @if($actividad->descripcion)
        <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 text-gray-700 text-sm">
            @include('components.descripcion-render', ['slot' => $actividad->descripcion])
        </div>
        @endif
        @if($actividad->duracion_minutos)
        <p class="text-sm text-gray-500 mt-3">Tiempo límite: {{ $actividad->duracion_minutos }} minutos</p>
        @endif
    </div>

    {{-- Indicador de plazo --}}
    @php $estadoPlazo = $actividad->estadoPlazo(); @endphp
    @if($estadoPlazo !== 'sin_plazo')
    <div class="mb-6 flex items-start gap-3 px-5 py-4 rounded-xl border
        @if($estadoPlazo === 'abierta')   bg-green-50  border-green-200
        @elseif($estadoPlazo === 'pendiente') bg-yellow-50 border-yellow-200
        @else bg-red-50 border-red-200 @endif">
        {{-- Icono --}}
        <svg class="w-5 h-5 mt-0.5 flex-shrink-0
            @if($estadoPlazo === 'abierta') text-green-500
            @elseif($estadoPlazo === 'pendiente') text-yellow-500
            @else text-red-500 @endif"
             fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
        </svg>
        <div class="flex-1 text-sm">
            @if($estadoPlazo === 'abierta')
                <span class="font-semibold text-green-800">Actividad abierta</span>
                @if($actividad->fecha_apertura)
                    <span class="text-green-700"> — disponible desde el {{ $actividad->fecha_apertura->format('d/m/Y H:i') }}</span>
                @endif
                @if($actividad->fecha_cierre)
                    <span class="text-green-700"> · Cierra el <strong>{{ $actividad->fecha_cierre->format('d/m/Y H:i') }}</strong></span>
                @endif
            @elseif($estadoPlazo === 'pendiente')
                <span class="font-semibold text-yellow-800">Aún no disponible</span>
                <span class="text-yellow-700"> — abre el <strong>{{ $actividad->fecha_apertura->format('d/m/Y \a \l\a\s H:i') }}</strong></span>
            @else
                <span class="font-semibold text-red-800">Plazo vencido</span>
                <span class="text-red-700"> — la entrega cerró el <strong>{{ $actividad->fecha_cierre->format('d/m/Y H:i') }}</strong></span>
            @endif
        </div>
    </div>
    @endif

    {{-- Recursos de la actividad --}}
    @php $recursos = $actividad->recursos; @endphp
    @php $descargaPermitida = $actividad->descargaPermitida(); @endphp
    @if($recursos->isNotEmpty())
    <div class="mb-6" x-data="{
         visorAbierto: false, visorTipo: '', visorUrl: '', visorTitulo: '',

         officeLoading: false, officeError: '', officeContent: '',

         async loadOffice(url) {
             this.officeError = '';
             this.officeContent = '';
             this.officeLoading = true;
             try {
                 const ext = url.split('.').pop().toLowerCase().split('?')[0];
                 const resp = await fetch(url);
                 if (!resp.ok) throw new Error('El archivo no se pudo cargar (HTTP ' + resp.status + ')');
                 const buf = await resp.arrayBuffer();
                 if (ext === 'docx') {
                     if (!window.mammoth) await this.loadLib('https://cdn.jsdelivr.net/npm/mammoth@1.6.0/mammoth.browser.min.js', 'mammoth');
                     const result = await mammoth.convertToHtml({arrayBuffer: buf});
                     this.officeContent = result.value;
                 } else if (ext === 'xlsx' || ext === 'xls') {
                     if (!window.XLSX) await this.loadLib('https://cdn.sheetjs.com/xlsx-0.20.0/package/dist/xlsx.full.min.js', 'XLSX');
                     const wb = XLSX.read(new Uint8Array(buf), {type: 'array'});
                     this.officeContent = XLSX.utils.sheet_to_html(wb.Sheets[wb.SheetNames[0]], {editable: false});
                 } else if (ext === 'pptx' || ext === 'ppt') {
                     throw new Error('La vista previa de presentaciones no está disponible. Contacta al instructor.');
                 } else {
                     throw new Error('Formato no soportado para vista previa.');
                 }
             } catch(e) {
                 console.error(e);
                 this.officeError = e.message || 'Error al cargar el documento';
             } finally {
                 this.officeLoading = false;
             }
         },

         loadLib(src, global) {
             return new Promise((resolve, reject) => {
                 if (window[global]) return resolve();
                 const s = document.createElement('script');
                 s.src = src;
                 s.onload = () => resolve();
                 s.onerror = () => reject(new Error('No se pudo cargar el visor de Office'));
                 document.head.appendChild(s);
             });
         },
     }"
          @keydown.window="if(visorAbierto && (($event.ctrlKey || $event.metaKey) && ['s','p','S','P'].includes($event.key))) $event.preventDefault()">
        <h2 class="text-base font-bold text-gray-900 mb-3 flex items-center gap-2">
            <svg class="w-5 h-5 text-dyl-blue" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/>
            </svg>
            Materiales de apoyo
        </h2>
        <div class="space-y-3">
        @foreach($recursos as $recurso)

            {{-- DOCUMENTO --}}
            @if($recurso->tipo === 'documento')
            @php
                $ext = strtolower(pathinfo($recurso->archivoNombre(), PATHINFO_EXTENSION));
                $esPdf = $ext === 'pdf';
                $esOffice = in_array($ext, ['doc','docx','xls','xlsx','ppt','pptx']);
                $esImagenDoc = in_array($ext, ['jpg','jpeg','png','gif','webp','svg','bmp']);
                $urlArchivo = $recurso->archivoUrl();
            @endphp
            <div class="flex items-start gap-4 bg-white border border-gray-200 rounded-xl p-4 hover:border-red-300 hover:shadow-sm transition-all">
                <div class="w-10 h-10 rounded-lg bg-red-50 flex items-center justify-center flex-shrink-0">
                    <svg class="w-5 h-5 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $recurso->iconoTipo() }}"/>
                    </svg>
                </div>
                <div class="flex-1 min-w-0">
                    <p class="font-medium text-gray-900">{{ $recurso->titulo }}</p>
                    @if($recurso->descripcion)<p class="text-xs text-gray-500 mt-0.5">{{ $recurso->descripcion }}</p>@endif
                </div>
                @if($descargaPermitida)
                <a href="{{ route('recursos.descargar', $recurso) }}"
                   class="btn-outline btn-sm flex-shrink-0">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                    </svg>
                    Descargar
                </a>
                @else
                    @if($esPdf)
                    <button type="button" @click="visorTipo='pdf'; visorUrl='{{ $urlArchivo }}'; visorTitulo='{{ e($recurso->titulo) }}'; visorAbierto=true"
                       class="btn-outline btn-sm flex-shrink-0 text-blue-600 border-blue-300 hover:bg-blue-50">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                        </svg>
                        Ver
                    </button>
                    @elseif($esOffice)
                    <button type="button" @click="visorTipo='office'; visorUrl='{{ $urlArchivo }}'; visorTitulo='{{ e($recurso->titulo) }}'; visorAbierto=true"
                       class="btn-outline btn-sm flex-shrink-0 text-blue-600 border-blue-300 hover:bg-blue-50">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                        </svg>
                        Ver
                    </button>
                    @elseif($esImagenDoc)
                    <button type="button" @click="visorTipo='imagen'; visorUrl='{{ $urlArchivo }}'; visorTitulo='{{ e($recurso->titulo) }}'; visorAbierto=true"
                       class="btn-outline btn-sm flex-shrink-0 text-blue-600 border-blue-300 hover:bg-blue-50">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                        </svg>
                        Ver
                    </button>
                    @else
                    <span class="text-xs text-gray-400 italic flex-shrink-0 self-center">Vista previa no disponible</span>
                    @endif
                @endif
            </div>

            {{-- IMAGEN --}}
            @elseif($recurso->tipo === 'imagen')
            <div class="bg-white border border-gray-200 rounded-xl overflow-hidden hover:shadow-sm transition-all">
                <div class="p-4 flex items-center gap-3 border-b border-gray-100">
                    <div class="w-9 h-9 rounded-lg bg-orange-50 flex items-center justify-center flex-shrink-0">
                        <svg class="w-5 h-5 text-orange-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $recurso->iconoTipo() }}"/>
                        </svg>
                    </div>
                    <div>
                        <p class="font-medium text-gray-900 text-sm">{{ $recurso->titulo }}</p>
                        @if($recurso->descripcion)<p class="text-xs text-gray-500">{{ $recurso->descripcion }}</p>@endif
                    </div>
                </div>
                @if($descargaPermitida)
                <a href="{{ $recurso->archivoUrl() }}" target="_blank" class="block bg-gray-50 p-2">
                    <img src="{{ $recurso->archivoUrl() }}"
                         alt="{{ $recurso->titulo }}"
                         class="w-full max-h-96 object-contain rounded-lg mx-auto"
                         loading="lazy">
                </a>
                @else
                <button type="button" @click="visorTipo='imagen'; visorUrl='{{ $recurso->archivoUrl() }}'; visorTitulo='{{ e($recurso->titulo) }}'; visorAbierto=true"
                        class="block bg-gray-50 p-2 w-full cursor-pointer hover:bg-gray-100 transition-colors">
                    <img src="{{ $recurso->archivoUrl() }}"
                         alt="{{ $recurso->titulo }}"
                         class="w-full max-h-96 object-contain rounded-lg mx-auto"
                         loading="lazy">
                </button>
                @endif
            </div>

            {{-- VIDEO --}}
            @elseif($recurso->tipo === 'video')
            <div class="bg-white border border-gray-200 rounded-xl overflow-hidden">
                <div class="p-4 flex items-center gap-3 border-b border-gray-100">
                    <div class="w-9 h-9 rounded-lg bg-purple-50 flex items-center justify-center flex-shrink-0">
                        <svg class="w-5 h-5 text-purple-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $recurso->iconoTipo() }}"/>
                        </svg>
                    </div>
                    <div>
                        <p class="font-medium text-gray-900 text-sm">{{ $recurso->titulo }}</p>
                        @if($recurso->descripcion)<p class="text-xs text-gray-500">{{ $recurso->descripcion }}</p>@endif
                    </div>
                </div>
                @php $embed = $recurso->embedUrl(); @endphp
                @if($embed && (str_contains($embed, 'youtube.com/embed') || str_contains($embed, 'player.vimeo.com')))
                    <div class="aspect-video">
                        <iframe src="{{ $embed }}" class="w-full h-full" frameborder="0"
                                allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                                allowfullscreen></iframe>
                    </div>
                @else
                    <div class="p-4">
                        <video src="{{ $embed }}" controls class="w-full rounded-lg"></video>
                    </div>
                @endif
            </div>

            {{-- TEXTO --}}
            @elseif($recurso->tipo === 'texto')
            <div class="bg-white border border-gray-200 rounded-xl overflow-hidden">
                <div class="px-5 py-3 bg-blue-50 border-b border-blue-100 flex items-center gap-2">
                    <svg class="w-4 h-4 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $recurso->iconoTipo() }}"/>
                    </svg>
                    <span class="font-medium text-blue-800 text-sm">{{ $recurso->titulo }}</span>
                    @if($recurso->descripcion)<span class="text-xs text-blue-500 ml-1">— {{ $recurso->descripcion }}</span>@endif
                </div>
                <div class="px-5 py-4 prose prose-sm max-w-none text-gray-700 leading-relaxed">
                    {!! nl2br(e($recurso->contenido)) !!}
                </div>
            </div>

            {{-- ENLACE EXTERNO --}}
            @elseif($recurso->tipo === 'enlace')
            <a href="{{ $recurso->url }}" target="_blank" rel="noopener noreferrer"
               class="flex items-center gap-4 bg-white border border-gray-200 rounded-xl p-4 hover:border-green-400 hover:shadow-sm transition-all group">
                <div class="w-10 h-10 rounded-lg bg-green-50 flex items-center justify-center flex-shrink-0 group-hover:bg-green-100 transition-colors">
                    <svg class="w-5 h-5 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $recurso->iconoTipo() }}"/>
                    </svg>
                </div>
                <div class="flex-1 min-w-0">
                    <p class="font-medium text-gray-900 group-hover:text-green-700 transition-colors">{{ $recurso->titulo }}</p>
                    @if($recurso->descripcion)<p class="text-xs text-gray-500 mt-0.5">{{ $recurso->descripcion }}</p>@endif
                    <p class="text-xs text-gray-400 mt-0.5 truncate">{{ $recurso->url }}</p>
                </div>
                <svg class="w-4 h-4 text-gray-400 group-hover:text-green-500 flex-shrink-0 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
                </svg>
            </a>
            @endif

        @endforeach
        </div>

        {{-- Modal visor de documentos --}}
        <div x-show="visorAbierto" x-cloak
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0"
             x-transition:enter-end="opacity-100"
             x-transition:leave="transition ease-in duration-150"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0"
             class="fixed inset-0 z-50 flex items-center justify-center p-4"
             @click.self="visorAbierto = false"
             @keydown.escape.window="visorAbierto = false">
            <div class="absolute inset-0 bg-black/60"></div>
            <div class="relative bg-white rounded-xl shadow-2xl w-full max-w-5xl max-h-[90vh] flex flex-col z-10">
                <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200">
                    <h3 class="text-lg font-semibold text-gray-900 truncate pr-4" x-text="visorTitulo"></h3>
                    <button @click="visorAbierto = false"
                            class="p-2 text-gray-400 hover:text-gray-600 hover:bg-gray-100 rounded-lg transition-colors"
                            aria-label="Cerrar visor">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>
                <div class="flex-1 overflow-auto" @contextmenu.prevent>
                    {{-- PDF — iframe nativo + marca de agua --}}
                    <div x-show="visorTipo === 'pdf'" class="w-full min-h-[75vh] select-none relative" @contextmenu.prevent>
                        <iframe :src="visorTipo === 'pdf' ? visorUrl + '#toolbar=0&navpanes=0' : ''"
                                class="w-full h-full min-h-[75vh]" frameborder="0"
                                @load="try { const d = $el.contentDocument || $el.contentWindow.document; d.addEventListener('contextmenu', e => e.preventDefault(), {capture: true}); d.addEventListener('keydown', e => { if((e.ctrlKey||e.metaKey)&&['s','p','S','P'].includes(e.key)) e.preventDefault(); }, {capture: true}); } catch(e) {}"></iframe>
                        <div class="absolute inset-0 pointer-events-none select-none overflow-hidden"
                             style="background-image: repeating-linear-gradient(35deg, rgba(0,0,0,0.04) 0px, rgba(0,0,0,0.04) 1px, transparent 1px, transparent 100px), repeating-linear-gradient(145deg, rgba(0,0,0,0.03) 0px, rgba(0,0,0,0.03) 1px, transparent 1px, transparent 100px);">
                        </div>
                        <div class="absolute inset-0 pointer-events-none select-none flex items-center justify-center overflow-hidden">
                            <span class="text-gray-900/5 text-[10px] font-mono rotate-[-30deg] whitespace-nowrap select-none" style="text-shadow: 0 0 40px rgba(0,0,0,0.03);">
                                {{ auth()->user()->name }} · {{ auth()->user()->email }} · {{ now()->format('d/m/Y') }}
                            </span>
                        </div>
                    </div>
                    {{-- Office — renderizado client-side con mammoth.js / SheetJS + marca de agua --}}
                    <div x-show="visorTipo === 'office'"
                         x-effect="if(visorTipo === 'office' && visorAbierto && visorUrl) { loadOffice(visorUrl); }"
                         class="w-full min-h-[75vh] select-none relative" @contextmenu.prevent>

                        {{-- Loading --}}
                        <div x-show="officeLoading" class="flex items-center justify-center py-20">
                            <svg class="animate-spin h-8 w-8 text-blue-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                            </svg>
                        </div>

                        {{-- Error --}}
                        <div x-show="officeError" class="flex flex-col items-center justify-center py-16 px-6">
                            <svg class="w-12 h-12 text-red-400 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            <p class="text-red-600 text-sm font-medium text-center" x-text="officeError"></p>
                        </div>

                        {{-- Contenido renderizado --}}
                        <div x-show="officeContent" class="p-6 max-w-3xl mx-auto">
                            <style>
                                .office-render table { border-collapse: collapse; width: 100%; font-size: 0.875rem; }
                                .office-render th, .office-render td { border: 1px solid #d1d5db; padding: 0.5rem 0.75rem; text-align: left; }
                                .office-render th { background: #f3f4f6; font-weight: 600; }
                                .office-render tr:nth-child(even) { background: #f9fafb; }
                                .office-render h1 { font-size: 1.5rem; font-weight: 700; margin-bottom: 0.75rem; }
                                .office-render h2 { font-size: 1.25rem; font-weight: 600; margin-bottom: 0.5rem; }
                                .office-render h3 { font-size: 1.1rem; font-weight: 600; margin-bottom: 0.5rem; }
                                .office-render p { margin-bottom: 0.5rem; line-height: 1.6; }
                                .office-render ul, .office-render ol { margin-bottom: 0.5rem; padding-left: 1.5rem; }
                                .office-render li { margin-bottom: 0.25rem; }
                                .office-render img { max-width: 100%; height: auto; }
                            </style>
                            <div class="office-render bg-white rounded-lg shadow-sm border border-gray-200 p-6 overflow-x-auto"
                                 x-html="officeContent"></div>
                        </div>

                        {{-- Marca de agua --}}
                        <div class="absolute inset-0 pointer-events-none select-none overflow-hidden"
                             style="background-image: repeating-linear-gradient(35deg, rgba(0,0,0,0.04) 0px, rgba(0,0,0,0.04) 1px, transparent 1px, transparent 100px), repeating-linear-gradient(145deg, rgba(0,0,0,0.03) 0px, rgba(0,0,0,0.03) 1px, transparent 1px, transparent 100px);">
                        </div>
                        <div class="absolute inset-0 pointer-events-none select-none flex items-center justify-center overflow-hidden">
                            <span class="text-gray-900/5 text-[10px] font-mono rotate-[-30deg] whitespace-nowrap select-none" style="text-shadow: 0 0 40px rgba(0,0,0,0.03);">
                                {{ auth()->user()->name }} · {{ auth()->user()->email }} · {{ now()->format('d/m/Y') }}
                            </span>
                        </div>
                    </div>
                    {{-- Imagen --}}
                    <div x-show="visorTipo === 'imagen'"
                         class="flex items-center justify-center p-4 min-h-[50vh] select-none relative"
                         @contextmenu.prevent>
                        <img :src="visorUrl" :alt="visorTitulo"
                             class="max-w-full max-h-[80vh] object-contain rounded-lg shadow-lg">
                        {{-- Marca de agua --}}
                        <div class="absolute inset-0 pointer-events-none select-none overflow-hidden opacity-[0.03]"
                             style="background-image: repeating-linear-gradient(35deg, #000 0px, #000 1px, transparent 1px, transparent 80px), repeating-linear-gradient(145deg, #000 0px, #000 1px, transparent 1px, transparent 80px);">
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>
    @endif

    {{-- Estado de completado de la actividad --}}
    @if($actividadCompletada)
    <div class="mb-6 flex items-center gap-3 px-5 py-4 bg-green-50 border border-green-200 rounded-xl">
        <svg class="w-5 h-5 text-green-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
        </svg>
        <span class="text-sm font-medium text-green-700">Actividad completada</span>
    </div>
    @elseif(!$actividad->tieneCalificacion())
    <div class="mb-6">
        <form action="{{ route('actividades.completar', $actividad) }}" method="POST">
            @csrf
            <button type="submit"
                    class="w-full sm:w-auto bg-green-600 text-white px-8 py-3 rounded-lg hover:bg-green-700 font-medium transition-colors flex items-center gap-2">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                </svg>
                Marcar como completada
            </button>
        </form>
    </div>
    @endif

    {{-- Tabla de rúbrica (solo tarea con rúbrica activa) --}}
    @if($actividad->usa_rubrica && $criteriosRubrica->isNotEmpty())
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 mb-6 overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-100 bg-gray-50">
            <h2 class="font-bold text-gray-900 flex items-center gap-2">
                <svg class="w-5 h-5 text-dyl-blue" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                </svg>
                Criterios de calificación
            </h2>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="bg-gray-50 border-b border-gray-200">
                        <th class="text-left px-4 py-3 font-semibold text-gray-700 min-w-40">Criterio</th>
                        @foreach($criteriosRubrica->first()->niveles as $nivelHeader)
                        <th class="text-center px-3 py-3 font-semibold text-gray-500 text-xs min-w-32">
                            Nivel {{ $loop->iteration }}<br>
                            <span class="text-green-600 font-bold">{{ number_format($nivelHeader->puntos, 2) }} pts</span>
                        </th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach($criteriosRubrica as $criterio)
                    <tr class="border-b border-gray-100 {{ $loop->even ? 'bg-gray-50/50' : '' }}">
                        <td class="px-4 py-4 font-semibold text-gray-800 align-top">{{ $criterio->nombre }}</td>
                        @foreach($criterio->niveles as $nivel)
                        @php $estaSeleccionado = $seleccionesMap->get($criterio->id) == $nivel->id; @endphp
                        <td class="px-3 py-4 align-top text-xs text-gray-600 leading-relaxed
                            {{ $estaSeleccionado ? 'bg-green-50 border-l-2 border-green-400' : '' }}">
                            @if($estaSeleccionado)
                                <span class="inline-block mb-1 text-green-600 font-semibold text-[10px] uppercase tracking-wide">✓ Nivel obtenido</span><br>
                            @endif
                            {{ $nivel->descripcion }}
                            <span class="block mt-2 font-bold text-sm {{ $estaSeleccionado ? 'text-green-600' : 'text-gray-400' }}">
                                {{ number_format($nivel->puntos, 2) }} puntos
                            </span>
                        </td>
                        @endforeach
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="px-6 py-3 bg-gray-50 border-t border-gray-100 text-right text-sm text-gray-500">
            Nota máxima: <strong class="text-gray-800">{{ number_format($actividad->puntaje_maximo, 2) }} pts</strong>
        </div>
    </div>
    @endif

    @if($actividad->tieneCalificacion())
        {{-- Resultado si ya respondió --}}
        @if($respuesta)
        <div class="bg-green-50 border border-green-200 rounded-lg p-6 mb-6">
            <h2 class="font-bold text-green-800 mb-2">Ya respondiste esta actividad</h2>
            @if($respuesta->calificacion !== null)
                <p class="text-2xl font-bold text-green-700">{{ $respuesta->calificacion }}/{{ $actividad->puntaje_maximo }} puntos</p>
            @else
                <p class="text-gray-600">Tu respuesta está pendiente de calificación.</p>
            @endif
            @if($respuesta->feedback)
                <div class="mt-3 pt-3 border-t border-green-200">
                    <p class="text-sm font-medium text-gray-700 mb-1">Retroalimentación:</p>
                    <p class="text-sm text-gray-600">{{ $respuesta->feedback }}</p>
                </div>
            @endif
            <div class="mt-4">
                <a href="{{ route('lecciones.show', $actividad->leccion) }}" class="inline-flex items-center bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors">
                    <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                    Volver a la lección
                </a>
            </div>
        </div>

        @elseif(!$actividad->estaAbierta())
        {{-- Actividad cerrada o pendiente: no se puede responder --}}
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

        @else
        {{-- Formulario de respuesta --}}
        <form id="form-respuesta" action="{{ route('respuestas.store', $actividad) }}" method="POST" enctype="multipart/form-data">
            @csrf

            @if($actividad->tipo === 'cuestionario')
                @php
                    $preguntas  = $actividad->preguntas()->with('opciones')->orderBy('orden')->get();
                    $oldAnswers = old('respuesta') ? (json_decode(old('respuesta'), true) ?? []) : [];
                @endphp
                <div class="space-y-6">
                    @foreach($preguntas as $index => $pregunta)
                    @php $oldVal = $oldAnswers[$pregunta->id] ?? null; @endphp
                    <div class="bg-white rounded-lg shadow p-6">
                        <p class="font-medium text-gray-900 mb-1">
                            {{ $index + 1 }}. {{ $pregunta->pregunta_texto }}
                            <span class="text-xs text-gray-400 ml-2">({{ $pregunta->puntaje }} pts)</span>
                        </p>

                        @if($pregunta->imagen_path)
                        <img src="{{ $pregunta->imagenUrl() }}"
                             alt="Imagen de apoyo"
                             class="my-4 w-full h-64 object-contain rounded-lg border border-gray-200 bg-gray-50">
                        @endif

                        @if($pregunta->tipo === 'respuesta_corta')
                            <input type="text" name="respuesta_{{ $pregunta->id }}"
                                   value="{{ old('respuesta_' . $pregunta->id) }}"
                                   class="mt-3 w-full px-3 py-2 border border-gray-300 rounded-lg text-sm" required>

                        @elseif($pregunta->seleccion_multiple)
                            <p class="mt-2 text-xs text-blue-600 font-medium">
                                Selecciona todas las respuestas correctas.
                            </p>
                            <div class="mt-2 space-y-2">
                                @foreach($pregunta->opciones as $opcion)
                                @php $checked = is_array($oldVal) && in_array((string)$opcion->id, array_map('strval', $oldVal)); @endphp
                                <label class="flex items-center gap-3 p-3 border border-gray-200 rounded-lg cursor-pointer hover:bg-blue-50 transition-colors">
                                    <input type="checkbox"
                                           name="respuesta_{{ $pregunta->id }}[]"
                                           value="{{ $opcion->id }}"
                                           {{ $checked ? 'checked' : '' }}
                                           class="w-4 h-4 rounded text-blue-600">
                                    <span class="text-sm text-gray-800">{{ $opcion->texto }}</span>
                                </label>
                                @endforeach
                            </div>

                        @else
                            <div class="mt-3 space-y-2">
                                @foreach($pregunta->opciones as $opcion)
                                @php $checked = $oldVal !== null && (string)$oldVal === (string)$opcion->id; @endphp
                                <label class="flex items-center gap-3 p-3 border border-gray-200 rounded-lg cursor-pointer hover:bg-gray-50 transition-colors">
                                    <input type="radio" name="respuesta_{{ $pregunta->id }}" value="{{ $opcion->id }}"
                                           {{ $checked ? 'checked' : '' }}
                                           class="text-blue-600">
                                    <span class="text-sm text-gray-800">{{ $opcion->texto }}</span>
                                </label>
                                @endforeach
                            </div>
                        @endif
                    </div>
                    @endforeach
                </div>

                {{-- Serializar respuestas en JSON --}}
                <input type="hidden" name="respuesta" id="respuesta-json">
                <script>
                document.getElementById('form-respuesta').addEventListener('submit', function(e) {
                    const data = {};

                    // Radios y texto (una sola respuesta)
                    this.querySelectorAll('[name^="respuesta_"]:not([type=checkbox])').forEach(function(el) {
                        if (el.type === 'radio' && !el.checked) return;
                        if (!el.value) return;
                        const id = el.name.replace('respuesta_', '');
                        data[id] = el.value;
                    });

                    // Checkboxes (selección múltiple) — agrupados por pregunta_id
                    this.querySelectorAll('[name^="respuesta_"][type=checkbox]:checked').forEach(function(el) {
                        const id = el.name.replace('respuesta_', '').replace('[]', '');
                        if (!data[id]) data[id] = [];
                        data[id].push(el.value);
                    });

                    document.getElementById('respuesta-json').value = JSON.stringify(data);
                });
                </script>

            @else
                <div class="bg-white rounded-lg shadow p-6">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Tu respuesta</label>
                    <textarea name="respuesta" rows="8"
                              class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                              placeholder="Escribe tu respuesta aquí...">{{ old('respuesta') }}</textarea>
                    @error('respuesta')<p class="text-red-600 text-sm mt-1">{{ $message }}</p>@enderror
                </div>

                {{-- Adjunto opcional --}}
                <div class="bg-white rounded-lg shadow p-6" x-data="{ nombre: null, errorArchivo: '' }">
                    <p class="text-sm font-medium text-gray-700 mb-3">
                        Adjuntar archivo
                        <span class="text-gray-400 font-normal">(opcional)</span>
                    </p>
                    <label class="flex flex-col items-center justify-center w-full h-28 border-2 border-dashed rounded-xl cursor-pointer transition-colors"
                           :class="nombre && !errorArchivo
                               ? 'border-green-400 bg-green-50/40 hover:bg-green-50'
                               : errorArchivo
                                   ? 'border-red-400 bg-red-50/40'
                                   : 'border-gray-300 bg-gray-50/40 hover:border-blue-400 hover:bg-blue-50/30'">
                        <div x-show="!nombre && !errorArchivo" class="flex flex-col items-center gap-1.5 text-gray-400 pointer-events-none">
                            <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                      d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/>
                            </svg>
                            <p class="text-sm">Haz clic para seleccionar</p>
                            <p class="text-xs">Imagen, PDF, Word, video — máx. 50 MB</p>
                        </div>
                        <div x-show="nombre && !errorArchivo" class="flex items-center gap-2 px-4 text-green-700 pointer-events-none">
                            <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            <span class="text-sm font-medium truncate max-w-xs" x-text="nombre"></span>
                        </div>
                        <div x-show="errorArchivo" class="flex items-center gap-2 px-4 text-red-600 pointer-events-none">
                            <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            <span class="text-sm font-medium" x-text="errorArchivo"></span>
                        </div>
                        <input type="file" name="archivo_adjunto" class="sr-only"
                               accept="image/*,video/*,.pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.zip"
                               @change="
                                   errorArchivo = '';
                                   const f = $event.target.files[0];
                                   if (f) {
                                       if (f.size > 50 * 1024 * 1024) {
                                           errorArchivo = 'El archivo supera el límite de 50 MB.';
                                           $event.target.value = '';
                                           nombre = null;
                                       } else {
                                           nombre = f.name;
                                       }
                                   } else {
                                       nombre = null;
                                   }
                               ">
                    </label>
                    <p x-show="errorArchivo" x-text="errorArchivo" class="text-red-600 text-xs mt-1"></p>
                    @error('archivo_adjunto')<p class="text-red-600 text-sm mt-1">{{ $message }}</p>@enderror
                </div>
            @endif

            <div class="mt-6 flex justify-end">
                <button type="submit" class="bg-blue-600 text-white px-8 py-3 rounded-lg hover:bg-blue-700 font-medium">
                    Enviar respuesta
                </button>
            </div>
        </form>
        @endif

    @else
        {{-- Actividad sin calificación: solo consulta --}}
        <div class="bg-gray-50 border border-gray-200 rounded-xl p-6 flex items-start gap-4">
            <svg class="w-6 h-6 text-gray-400 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            <div>
                <p class="font-medium text-gray-700">Esta actividad es de consulta</p>
                <p class="text-sm text-gray-500 mt-1">No requiere entrega ni tiene calificación. Revisa los recursos disponibles arriba.</p>
            </div>
            <a href="{{ route('lecciones.show', $actividad->leccion) }}" class="ml-auto inline-flex items-center text-blue-600 hover:text-blue-800 text-sm font-medium shrink-0">
                Continuar <svg class="w-4 h-4 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"/></svg>
            </a>
        </div>
    @endif
</div>
@endsection