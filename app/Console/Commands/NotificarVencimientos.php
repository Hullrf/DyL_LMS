<?php

namespace App\Console\Commands;

use App\Models\Actividad;
use App\Models\Inscripcion;
use App\Models\Notificacion;
use App\Models\ProgresoActividad;
use App\Models\RespuestaEstudiante;
use Illuminate\Console\Command;

class NotificarVencimientos extends Command
{
    protected $signature = 'notificaciones:vencimientos';
    protected $description = 'Avisa a los estudiantes con entregas que vencen en las próximas 24 horas (una sola vez por actividad)';

    public function handle(): int
    {
        $creadas = 0;

        $actividades = Actividad::with('leccion.modulo.curso')
            ->whereBetween('fecha_cierre', [now(), now()->addDay()])
            ->whereHas('leccion.modulo.curso', fn($q) => $q->where('estado', 'publicado'))
            ->get();

        foreach ($actividades as $a) {
            $curso = $a->leccion->modulo->curso;

            $inscritos = Inscripcion::where('curso_id', $curso->id)->where('estado', '!=', 'abandonado')->pluck('user_id');
            $yaEntregaron = RespuestaEstudiante::where('actividad_id', $a->id)->pluck('user_id')
                ->merge(ProgresoActividad::where('actividad_id', $a->id)->where('completado', true)->pluck('user_id'));
            $yaAvisados = Notificacion::where('tipo', 'vencimiento')->where('data->actividad_id', $a->id)->pluck('user_id');

            foreach ($inscritos->diff($yaEntregaron)->diff($yaAvisados) as $userId) {
                Notificacion::create([
                    'user_id' => $userId,
                    'tipo'    => 'vencimiento',
                    'titulo'  => 'Una entrega vence pronto',
                    'mensaje' => "«{$a->tituloCompleto()}» ({$curso->titulo}) vence el {$a->fecha_cierre->format('d/m H:i')}.",
                    'data'    => ['url' => route('actividades.show', $a), 'actividad_id' => $a->id],
                ]);
                $creadas++;
            }
        }

        $this->info("Avisos de vencimiento creados: {$creadas}");

        return self::SUCCESS;
    }
}
