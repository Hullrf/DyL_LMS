<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            width: 297mm;
            height: 210mm;
            font-family: Helvetica, Arial, sans-serif;
            background: #fff;
            color: #1F2937;
            position: relative;
            overflow: hidden;
        }

        .encabezado {
            position: absolute;
            top: 14mm;
            left: 14mm;
        }
        .encabezado table {
            border-collapse: collapse;
        }
        .encabezado td {
            vertical-align: top;
            padding: 0;
        }

        .logo-circulos {
            width: 32mm;
            display: block;
        }

        .wordmark {
            padding-top: 4mm;
            padding-left: 8mm;
        }
        .dl {
            font-size: 26pt;
            font-weight: bold;
            color: #1F2937;
            letter-spacing: 1px;
        }
        .amp { color: #16A34A; }
        .sub {
            font-size: 10pt;
            letter-spacing: 4px;
            color: #4B5563;
            text-transform: uppercase;
        }

        .decoracion {
            position: absolute;
            top: -40mm;
            right: -40mm;
            width: 160mm;
            opacity: 0.12;
        }

        .contenido {
            position: absolute;
            top: 50mm;
            left: 20mm;
            right: 55mm;
        }

        .hace-constar {
            font-size: 13pt;
            color: #374151;
            margin-bottom: 8mm;
        }

        .nombre-estudiante {
            font-size: 24pt;
            font-weight: bold;
            color: #1F2937;
            text-transform: uppercase;
            margin-bottom: 2mm;
        }

        .cedula {
            font-size: 11pt;
            color: #4B5563;
            margin-bottom: 10mm;
        }

        .texto-completo {
            font-size: 12pt;
            color: #374151;
            margin-bottom: 4mm;
        }

        .nombre-curso {
            font-size: 20pt;
            font-weight: bold;
            color: #16A34A;
            text-transform: uppercase;
            margin-bottom: 10mm;
        }

        .datos-fila {
            font-size: 11pt;
            color: #374151;
            margin-bottom: 16mm;
        }
        .datos-fila span { margin-right: 20mm; }
        .datos-fila strong { color: #1F2937; }

        .firma-bloque {
            width: 70mm;
        }
        .firma-img {
            width: 40mm;
            margin-bottom: -3mm;
        }
        .firma-linea {
            border-top: 1px solid #9CA3AF;
            margin-bottom: 1.5mm;
        }
        .firma-nombre {
            font-size: 10pt;
            font-weight: bold;
            color: #1F2937;
        }
        .firma-cargo {
            font-size: 9pt;
            color: #6B7280;
        }

        .pie {
            position: absolute;
            bottom: 10mm;
            left: 20mm;
            right: 20mm;
            font-size: 9pt;
            color: #16A34A;
            font-weight: bold;
        }
        .pie .contacto {
            font-size: 8pt;
            color: #6B7280;
            font-weight: normal;
        }
        .pie .contacto a { color: #6B7280; text-decoration: none; }
    </style>
</head>
<body>

<img class="decoracion" src="{{ public_path('images/certificados/logo-circulos.jpg') }}">

<div class="encabezado">
    <table>
        <tr>
            <td><img class="logo-circulos" src="{{ public_path('images/certificados/logo-circulos.jpg') }}"></td>
            <td class="wordmark">
                <div class="dl">D<span class="amp">&amp;</span>L</div>
                <div class="sub">Quality Consulting</div>
            </td>
        </tr>
    </table>
</div>

<div class="contenido">
    <p class="hace-constar">Hace Constar Que:</p>

    <p class="nombre-estudiante">{{ $certificado->usuario->name }}</p>
    @if($certificado->usuario->numero_documento)
        <p class="cedula">C.C. {{ $certificado->usuario->numero_documento }}</p>
    @endif

    <p class="texto-completo">Completó con éxito la formación y evaluación de</p>
    <p class="nombre-curso">{{ $certificado->curso->titulo }}</p>

    <div class="datos-fila">
        <span>Fecha Finalización: <strong>{{ \Carbon\Carbon::parse($certificado->fecha_emision)->format('Y/m/d') }}</strong></span>
        <span>Intensidad: <strong>{{ $certificado->curso->duracion_horas }} horas</strong></span>
    </div>

    <div class="firma-bloque">
        <img class="firma-img" src="{{ public_path('images/certificados/firma-sandra-fajardo.jpg') }}">
        <div class="firma-linea"></div>
        <p class="firma-nombre">Sandra Marcela Fajardo</p>
        <p class="firma-cargo">Directora de Formación</p>
    </div>
</div>

<div class="pie">
    <div>www.dylqualityconsulting.com</div>
    <div class="contacto">contacto.dylltda@gmail.com &middot; 310 349 1201 &middot; Calle 143 No. 46-55 &middot; N° {{ $certificado->numero_certificado }}</div>
</div>

</body>
</html>
