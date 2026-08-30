<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: Helvetica, Arial, sans-serif;
            color: #1F2937;
            font-size: 12pt;
            line-height: 1.7;
        }

        .encabezado {
            margin-bottom: 14mm;
        }
        .encabezado table {
            border-collapse: collapse;
        }
        .encabezado td {
            vertical-align: middle;
            padding: 0;
        }
        .encabezado img { width: 22mm; display: block; }
        .encabezado .wordmark {
            padding-left: 6mm;
        }
        .encabezado .wordmark .dl {
            font-size: 16pt;
            font-weight: bold;
            color: #1F2937;
        }
        .encabezado .wordmark .dl .amp { color: #16A34A; }
        .encabezado .wordmark .sub {
            font-size: 7pt;
            letter-spacing: 2px;
            color: #4B5563;
            text-transform: uppercase;
        }

        .parrafo {
            text-align: justify;
            margin-bottom: 14mm;
        }
        .parrafo strong { color: #1F2937; }

        .despedida {
            margin-bottom: 4mm;
        }

        .firma-bloque {
            margin-top: 10mm;
        }
        .firma-bloque img {
            width: 35mm;
            margin-bottom: -2mm;
        }
        .firma-linea {
            border-top: 1px solid #9CA3AF;
            width: 60mm;
            margin-bottom: 1.5mm;
        }
        .firma-nombre {
            font-weight: bold;
            font-size: 11pt;
        }
        .firma-cargo, .firma-empresa {
            font-size: 10pt;
            color: #4B5563;
        }

        .pie {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background: #F97316;
            color: #fff;
            padding: 4mm 10mm;
            font-size: 9pt;
            white-space: nowrap;
        }
        .pie span {
            display: inline-block;
            margin-right: 14mm;
        }
        .pie span:last-child { margin-right: 0; }
        .pie strong { font-weight: bold; }
        .pie .pie-numero {
            margin-top: 1.5mm;
            font-size: 8pt;
            white-space: nowrap;
        }
    </style>
</head>
<body>

<div class="encabezado">
    <table>
        <tr>
            <td><img src="{{ public_path('images/certificados/logo-circulos.jpg') }}"></td>
            <td class="wordmark">
                <div class="dl">D<span class="amp">&amp;</span>L</div>
                <div class="sub">Quality Consulting</div>
            </td>
        </tr>
    </table>
</div>

@php
    $fechaFin = \Carbon\Carbon::parse($certificado->fecha_emision);
    $diaTexto = \App\Support\NumeroEnPalabras::dia((int) $fechaFin->day);
    $mesTexto = \Illuminate\Support\Str::ucfirst($fechaFin->locale('es')->isoFormat('MMMM'));
    // Evita "DIPLOMADO EN DIPLOMADO EN X" cuando el título del curso ya
    // empieza con "Diplomado en".
    $tituloCurso = preg_replace('/^diplomado\s+en\s+/i', '', $certificado->curso->titulo);
@endphp

<p class="parrafo">
    EL PROCESO DE FORMACIÓN DE LA ORGANIZACIÓN D&amp;L QUALITY CONSULTING LTDA. HACE CONSTAR
    Que {{ $certificado->usuario->name }},
    quien se identifica con cédula de ciudadanía número
    <strong>{{ $certificado->usuario->numero_documento }}</strong>
    @if($certificado->usuario->ciudad_expedicion)
        de {{ $certificado->usuario->ciudad_expedicion }},
    @endif
    culminó exitosamente todos los contenidos académicos y aprobó satisfactoriamente la prueba
    de conocimiento del <strong>DIPLOMADO EN {{ mb_strtoupper($tituloCurso) }}</strong>,
    realizado entre el {{ \Carbon\Carbon::parse($inscripcion->fecha_inicio)->format('d/m/Y') }}
    y el {{ \Carbon\Carbon::parse($inscripcion->fecha_fin)->format('d/m/Y') }}
    con una intensidad de <strong>{{ $certificado->curso->duracion_horas }} horas</strong>.
    Se expide a solicitud de la o el interesado a los {{ $diaTexto }} ({{ $fechaFin->day }})
    días del mes de {{ $mesTexto }} del año {{ $fechaFin->year }} en la ciudad de Bogotá D.C.
</p>

<p class="despedida">Atentamente,</p>

<div class="firma-bloque">
    <img src="{{ public_path('images/certificados/firma-sandra-fajardo.jpg') }}">
    <div class="firma-linea"></div>
    <p class="firma-nombre">Sandra Marcela Fajardo Valero</p>
    <p class="firma-cargo">Coordinadora de formación empresarial</p>
    <p class="firma-empresa">D&amp;L QUALITY CONSULTING LTDA</p>
</div>

<div class="pie">
    <span><strong>Contacto:</strong> +57 305 442 2705</span>
    <span><strong>Horario:</strong> L-V 8:00 am - 5:00 pm</span>
    <span><strong>Email:</strong> contacto@dylqualityconsulting.com</span>
    <div class="pie-numero">N° de certificado: {{ $certificado->numero_certificado }}</div>
</div>

</body>
</html>
