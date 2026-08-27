# Backup/restauración de BD: hallazgos pendientes de la revisión final

**Fecha:** 2026-08-27
**Estado:** Pendiente — documentado, no resuelto
**Contexto:** `docs/superpowers/specs/2026-08-27-backup-restauracion-bd-design.md` y
`docs/superpowers/plans/2026-08-27-backup-restauracion-bd.md` (feature: `app/Services/BackupService.php`,
`app/Http/Controllers/Admin/BackupController.php`, mergeada en PR #4).

Una revisión final de todo el branch (post-merge) encontró 6 hallazgos "Important" (0 Critical). Los 3 más
urgentes — opciones SSL/PDO faltantes en `crearDump()`, corrupción silenciosa de emojis por charset
incorrecto, y restauración "exitosa" de archivos vacíos/corruptos — ya se corrigieron en PR #5
(commit `82d1abf`). Quedan estos 3 sin resolver, considerados de menor probabilidad hoy pero reales:

---

## 1. Timeouts pueden dejar una restauración a medias, sin ningún error visible

**Dónde:** `app/Http/Controllers/Admin/BackupController.php` (`restaurar()`), `public/.user.ini`
(`max_execution_time = 120`), `nginx.template.conf` (sin `fastcgi_read_timeout` explícito → default de 60s).

**Problema:** ni `crear()` ni `restaurar()` llaman `set_time_limit(0)` / `ignore_user_abort(true)`. Una
restauración que tarde más de 60s puede recibir un 504 de nginx mientras PHP sigue ejecutando en segundo
plano; una que pase los 120s de PHP es matada a mitad del loop de `DB::unprepared()`. En cualquiera de los
dos casos, el `catch` del controller nunca se alcanza: no queda log, no se muestra error al admin, y la base
de datos queda parcialmente restaurada (algunas tablas con `DROP`+`CREATE`+datos nuevos, otras aún con el
estado viejo).

**Por qué importa más de lo habitual:** esta es una herramienta de recuperación ante desastres — se usa
justo cuando los datos son más grandes o la base está más lenta, el peor momento para un timeout silencioso.

**Fix sugerido:** dos líneas en `restaurar()` antes de llamar a `restaurarDesdeArchivo()`:
```php
set_time_limit(0);
ignore_user_abort(true);
```

---

## 2. Un trigger futuro rompería el parser, y el manejo de error podría fallar también

**Dónde:** `app/Services/BackupService.php` (`crearDump()`, settings de `Mysqldump`).

**Problema (verificado contra el código de `druidfi/mysqldump-php`):**
- `skip-triggers` no está seteado (default `false`), y la librería envuelve los triggers en bloques
  `DELIMITER ;;` — `DELIMITER` es una directiva del cliente `mysql` CLI, no SQL válido; `DB::unprepared()`
  la rechazaría con un error de sintaxis. Hoy no hay triggers en el esquema (verificado en
  `database/migrations/`), así que esto está latente — pero se activa el día que alguien agregue uno,
  **después** de que los `DROP TABLE` ya corrieron.
- Compuesto con lo anterior: `add-locks` no está seteado (default `true`), así que el dump emite
  `LOCK TABLES … WRITE;`. Si una sentencia falla entre un `LOCK` y su `UNLOCK`, la conexión sigue con el
  lock activo. Como `SESSION_DRIVER=database` (en `.env`/`.env.example`), el siguiente intento de escribir
  la sesión (para mostrar el error vía `back()->withErrors(...)`) puede fallar con
  *"Table 'sessions' was not locked with LOCK TABLES"* — reemplazando el diagnóstico "Falló la sentencia N
  de M" por un 500 crudo, justo en el escenario para el que existe ese diagnóstico.

**Fix sugerido:** `'add-locks' => false` en el array de settings de `crearDump()` (no aporta nada útil para
un restore de un solo cliente), más idealmente rechazar de forma preventiva cualquier línea `DELIMITER` al
validar el archivo en `restaurarDesdeArchivo()` (mismo lugar donde ya se valida `-- Dump completed` /
`CREATE TABLE`).

---

## 3. El log de "backup creado" se escribe antes de confirmar que el dump terminó

**Dónde:** `app/Http/Controllers/Admin/BackupController.php` (`crear()`).

**Problema:** `Log::info('Backup de base de datos creado', ...)` se ejecuta antes del `streamDownload()`
callback que corre `crearDump()` — así que si el dump falla a mitad de camino (por ejemplo, por el hallazgo
SSL ya corregido en otro escenario, o por cualquier otro corte de conexión), el log igual registra un
"backup creado" que en realidad no se completó.

**Fix sugerido:** mover el `Log::info` adentro del callback de `streamDownload()`, después de que
`crearDump()` retorne sin excepción — o, si se prefiere mantenerlo afuera por simplicidad, cambiar el
mensaje a algo como "Backup de base de datos solicitado" para no afirmar un resultado que no se confirmó.
