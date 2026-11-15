# Avisos por correo con opt-out por usuario — diseño

Fecha: 2026-11-15 · Estado: aprobado · Tamaño: cambio pequeño-mediano (diseño corto)

## Por qué
El LMS avisa de vencimientos, calificaciones, anuncios y certificados solo dentro de la plataforma (campana de notificaciones). Quien no entra al campus no se entera. Se quiere que esos avisos también lleguen por correo, empezando por los recordatorios de entregas, sin spamear y con control del usuario.

## Qué se construye
1. **Categorías por correo** (las demás siguen solo en la plataforma):

   | Categoría (opt-out) | Tipos de `Notificacion` |
   |---|---|
   | Vencimientos | `vencimiento` |
   | Calificaciones | `calificacion`, `intento_extra` |
   | Anuncios | `anuncio` |
   | Certificados | `certificado` |

   No salen por correo: `foro`, `mensaje`, `entrega` (esta última va al instructor).
2. **Enganche en el modelo.** `Notificacion` escucha su evento `created`; si el tipo pertenece a una categoría, el usuario está `activo`, tiene correo y no desactivó esa categoría, encola un correo. Cubre `Notificacion::crear(...)` (9 sitios) y el `Notificacion::create(...)` de `notificaciones:vencimientos`, sin tocar ninguno de ellos.
3. **Un solo correo** (`Mailable` en cola): asunto = título del aviso; cuerpo = mensaje + botón al enlace del aviso (`data.url`); pie con enlace a *Perfil → Avisos por correo*. Diseño sobrio con la marca de D&L; texto en español.
4. **Opt-out por categoría.** Columna nueva `users.correo_desactivados` (JSON nullable, lista de categorías apagadas; vacío/null = todo activo). Sección nueva en Perfil con 4 interruptores y su guardado (`profile.avisos-correo`). Migración solo aditiva.
5. **Cola.** `QUEUE_CONNECTION=database` en Hostinger (las tablas `jobs`/`failed_jobs` ya existen). Se procesa desde el scheduler ya configurado: `Schedule::command('queue:work --stop-when-empty --max-time=240')->everyFiveMinutes()->withoutOverlapping()`. Un correo puede tardar hasta ~5 min en salir. Reintentos: 3.
6. **SMTP (fuera del repo).** `.env` de Hostinger: `MAIL_MAILER=smtp`, `MAIL_HOST=smtp.zoho.com`, `MAIL_PORT=465`, `MAIL_SCHEME=smtps`, `MAIL_USERNAME`/`MAIL_FROM_ADDRESS=campus@dylqualityconsulting.com`, `MAIL_PASSWORD` = contraseña de aplicación de Zoho, `QUEUE_CONNECTION=database`. SPF/DKIM de Zoho ya están en el DNS.

## Metas / no metas / anti-metas
- **Metas:** el estudiante recibe por correo vencimientos, notas, anuncios y certificados; puede apagar cada categoría; el correo de recuperar contraseña (ya existente en Breeze) empieza a funcionar al configurar SMTP.
- **No metas:** foros/mensajes/entregas por correo; resumen diario; varios recordatorios por actividad (se mantiene el aviso a 24 h); baja con un clic sin iniciar sesión; plantillas por tipo; gestión de rebotes.
- **Anti-metas:** que un fallo de Zoho rompa calificar o publicar un anuncio (el envío va en cola y nunca interrumpe la petición); correos a usuarios no activos; correos duplicados; que el opt-out afecte a correos de seguridad (recuperar contraseña no se puede apagar).

## Restricciones
- Todo en español (tablas, columnas, textos). Lógica fuera de controllers; la de envío vive en el modelo/Mailable, no en `CalificacionService` ni `NotaCursoService` (no se tocan).
- Cada migración llega a producción en el siguiente deploy de Railway: la nueva es aditiva y reversible. En Railway sigue `MAIL_MAILER` sin SMTP configurado (los correos van al log); este cambio no altera eso.
- Modo oscuro: la sección de Perfil usa los tokens semánticos existentes.
- Límites de Zoho por día y la disponibilidad de SMTP en su plan: por confirmar por el usuario.

## Enfoques considerados
1. **Enganche en el modelo + cola (elegido).** Un solo punto, no toca los 9 sitios, cualquier tipo futuro se suma con una línea en el mapa de categorías.
2. Notificaciones de Laravel por tipo: más idiomático, pero reescribe los 9 sitios (incluidos los de calificaciones) por el mismo resultado.
3. Resumen diario: menos correos y menos presión sobre Zoho, pero un recordatorio a 24 h pierde utilidad.

## Lo decidido por el diseñador (corregible)
Mapa tipo→categoría de la tabla anterior; `intento_extra` agrupado en Calificaciones; `entrega` al instructor solo en plataforma; opt-out por categoría (no uno global); remitente `campus@dylqualityconsulting.com`; enlace de baja = enlace al Perfil (requiere sesión).

## Para el constructor
- Pruebas de feature con `Mail::fake()`: se encola para categorías incluidas, no para `foro`/`mensaje`/`entrega`, respeta el opt-out y el estado del usuario, el correo lleva el enlace; el formulario de Perfil guarda/valida; el scheduler registra la tarea de cola.
- Verificar la sección de Perfil en navegador (escritorio, móvil, modo oscuro) y enviar un correo real de prueba por Zoho tras configurar el `.env`.
- Decidir nombres de clases, texto exacto del correo y diseño visual dentro de las restricciones.
