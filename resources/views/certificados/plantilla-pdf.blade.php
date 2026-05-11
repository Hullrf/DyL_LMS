<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            width: 297mm;
            height: 210mm;
            font-family: 'Times New Roman', Times, serif;
            background: #fff;
            color: #1a1a2e;
            position: relative;
            overflow: hidden;
        }

        /* Marco exterior dorado */
        .marco-exterior {
            position: absolute;
            top: 8mm;
            left: 8mm;
            right: 8mm;
            bottom: 8mm;
            border: 3px solid #C9A227;
        }

        /* Marco interior */
        .marco-interior {
            position: absolute;
            top: 11mm;
            left: 11mm;
            right: 11mm;
            bottom: 11mm;
            border: 1px solid #C9A227;
        }

        /* Fondo decorativo */
        .fondo-degradado {
            position: absolute;
            top: 0; left: 0; right: 0; bottom: 0;
            background: linear-gradient(135deg, #f8f4e8 0%, #ffffff 50%, #f8f4e8 100%);
        }

        /* Esquinas decorativas */
        .esquina {
            position: absolute;
            width: 20mm;
            height: 20mm;
            border-color: #C9A227;
        }
        .esquina-tl { top: 13mm; left: 13mm; border-top: 2px solid; border-left: 2px solid; }
        .esquina-tr { top: 13mm; right: 13mm; border-top: 2px solid; border-right: 2px solid; }
        .esquina-bl { bottom: 13mm; left: 13mm; border-bottom: 2px solid; border-left: 2px solid; }
        .esquina-br { bottom: 13mm; right: 13mm; border-bottom: 2px solid; border-right: 2px solid; }

        /* Contenido centrado */
        .contenido {
            position: absolute;
            top: 18mm;
            left: 18mm;
            right: 18mm;
            bottom: 18mm;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-align: center;
        }

        .logo-empresa {
            font-size: 13pt;
            font-weight: bold;
            color: #1e3a5f;
            letter-spacing: 3px;
            text-transform: uppercase;
            margin-bottom: 3mm;
        }

        .linea-dorada {
            width: 80mm;
            height: 0.5mm;
            background: #C9A227;
            margin: 0 auto 5mm;
        }

        .titulo-certificado {
            font-size: 28pt;
            color: #C9A227;
            letter-spacing: 5px;
            text-transform: uppercase;
            margin-bottom: 4mm;
            font-weight: normal;
        }

        .subtitulo {
            font-size: 11pt;
            color: #555;
            letter-spacing: 2px;
            margin-bottom: 6mm;
        }

        .nombre-estudiante {
            font-size: 26pt;
            color: #1e3a5f;
            font-style: italic;
            font-weight: bold;
            border-bottom: 1px solid #C9A227;
            padding-bottom: 2mm;
            margin-bottom: 5mm;
            min-width: 120mm;
        }

        .texto-por-completar {
            font-size: 10pt;
            color: #444;
            margin-bottom: 3mm;
        }

        .nombre-curso {
            font-size: 16pt;
            color: #1e3a5f;
            font-weight: bold;
            margin-bottom: 3mm;
        }

        .calificacion-box {
            display: inline-block;
            border: 1px solid #C9A227;
            padding: 1mm 5mm;
            font-size: 10pt;
            color: #555;
            margin-bottom: 6mm;
        }

        .firmas {
            display: flex;
            justify-content: space-around;
            width: 100%;
            margin-top: 4mm;
        }

        .firma-bloque {
            text-align: center;
            width: 60mm;
        }

        .firma-linea {
            border-top: 1px solid #555;
            margin-bottom: 1mm;
        }

        .firma-nombre {
            font-size: 9pt;
            font-weight: bold;
            color: #1a1a2e;
        }

        .firma-cargo {
            font-size: 8pt;
            color: #666;
        }

        .pie-certificado {
            position: absolute;
            bottom: 14mm;
            left: 0;
            right: 0;
            text-align: center;
            font-size: 7pt;
            color: #999;
            letter-spacing: 1px;
        }
    </style>
</head>
<body>

<div class="fondo-degradado"></div>
<div class="marco-exterior"></div>
<div class="marco-interior"></div>
<div class="esquina esquina-tl"></div>
<div class="esquina esquina-tr"></div>
<div class="esquina esquina-bl"></div>
<div class="esquina esquina-br"></div>

<div class="contenido">

    <div class="logo-empresa">DyL Quality Consulting</div>
    <div class="linea-dorada"></div>

    <div class="titulo-certificado">Certificado</div>
    <div class="subtitulo">DE FINALIZACIÓN</div>

    <div class="texto-por-completar">Este certificado se otorga a</div>

    <div class="nombre-estudiante">{{ $certificado->usuario->name }}</div>

    <div class="texto-por-completar">por haber completado satisfactoriamente el curso</div>

    <div class="nombre-curso">{{ $certificado->curso->titulo }}</div>

    <div class="calificacion-box">
        Calificación final: {{ $certificado->calificacion_final }}% &nbsp;·&nbsp;
        Duración: {{ $certificado->curso->duracion_horas }} horas &nbsp;·&nbsp;
        Fecha: {{ \Carbon\Carbon::parse($certificado->fecha_emision)->locale('es')->isoFormat('D [de] MMMM [de] YYYY') }}
    </div>

    <div class="firmas">
        <div class="firma-bloque">
            <div class="firma-linea"></div>
            <div class="firma-nombre">{{ $certificado->curso->creador->name }}</div>
            <div class="firma-cargo">Instructor del Curso</div>
        </div>
        <div class="firma-bloque">
            <div class="firma-linea"></div>
            <div class="firma-nombre">DyL Quality Consulting</div>
            <div class="firma-cargo">Dirección Académica</div>
        </div>
    </div>

</div>

<div class="pie-certificado">
    N° {{ $certificado->numero_certificado }} &nbsp;·&nbsp;
    Verificar en: {{ url('/verificar-certificado/' . $certificado->numero_certificado) }}
</div>

</body>
</html>
