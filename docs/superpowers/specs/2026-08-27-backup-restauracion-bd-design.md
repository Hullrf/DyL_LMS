# Diseño: Backup y Restauración Manual de la Base de Datos (producción)

**Fecha:** 2026-08-27
**Proyecto:** LMS DyL Quality Consulting
**Estado:** Aprobado

---

## Resumen

Punto de falla identificado: no existe ninguna forma de respaldar la base de datos de producción (Railway MySQL), ni de restaurarla ante un desastre (borrado accidental, migración destructiva, corrupción de datos). Este diseño agrega, exclusivamente para el rol Administrador, una pantalla en `/admin/backups` con dos acciones manuales:

1. **Crear backup** — genera un dump SQL completo de la base actual y lo entrega como descarga directa al navegador.
2. **Restaurar backup** — sube un archivo `.sql` previamente descargado y reemplaza el contenido de la base actual con él.

No incluye automatización, programación (cron) ni almacenamiento externo (S3, Google Drive, etc.). Es explícitamente un paso 1 manual; automatizarlo queda fuera de alcance y sería un paso 2 natural si se decide más adelante.

---

## 1. Hallazgo técnico que condiciona el diseño

Se verificó por conexión directa al contenedor de producción (`railway ssh`) que **no existen los binarios `mysqldump` ni `mysql` CLI** en la imagen desplegada — solo el intérprete de PHP. Esto descarta cualquier enfoque que dependa de invocar esas herramientas vía `Process`/`shell_exec`, tanto para crear como para restaurar backups desde dentro de la aplicación.

Por lo tanto, tanto el dump como la restauración deben implementarse en PHP puro, usando la conexión PDO que Laravel ya mantiene con la base de datos.

---

## 2. Crear backup

### Dependencia

Paquete Composer `ifsnop/mysqldump-php`: dump 100% en PHP, reutiliza una conexión PDO existente (no abre una nueva ni depende de binarios), soporta todas las estructuras relevantes (tablas, índices, auto_increment, triggers si los hubiera). Es el estándar de facto para este caso de uso en el ecosistema PHP cuando no se puede depender de `mysqldump`.

### Flujo

- Ruta `GET /admin/backups` → vista con dos secciones: "Crear backup" y "Restaurar backup".
- Botón "Descargar backup ahora" → `POST /admin/backups/crear`.
- El controlador genera el dump completo usando la conexión activa de Laravel (mismas credenciales que ya usa la app — no requiere configuración adicional) y lo **transmite directo como respuesta de descarga** (`StreamedResponse`), con nombre de archivo `backup_dyl_lms_{fecha}_{hora}.sql`.
- **No se persiste nada en el servidor**: ni en `storage/`, ni en un disco S3, ni en ningún sitio. El archivo existe únicamente en la respuesta HTTP y termina en la carpeta de descargas de quien lo pidió.
- Solo accesible bajo `middleware(['auth', 'admin'])`, mismo patrón que el resto de `/admin/*`.

### Por qué streaming y no un job en background

Dado el tamaño actual de la base de datos (proyecto pequeño, pocos cursos/usuarios), generar el dump dentro del ciclo de vida del request HTTP es aceptable y evita la complejidad de colas/jobs para una operación manual y poco frecuente. Si la base creciera lo suficiente como para acercarse a los límites de tiempo de ejecución de PHP o memoria, este es el primer punto a revisar — está documentado como límite conocido, no se resuelve en esta iteración (YAGNI).

---

## 3. Restaurar backup

### Flujo

- En la misma pantalla `/admin/backups`, sección "Restaurar backup":
  1. Input de archivo, acepta solo `.sql`.
  2. **Paso obligatorio antes de habilitar el resto del formulario**: un botón separado "Descargar backup de seguridad del estado actual" debe haberse clickeado en esa misma carga de página (se rastrea con un flag en el estado del formulario vía Alpine, no persistido) antes de que el campo de confirmación se habilite. Esto asegura que, si el archivo subido resulta ser el incorrecto o algo sale mal, existe un backup fresco del estado previo para poder volver atrás.
  3. Campo de texto donde hay que escribir literalmente `RESTAURAR` (mayúsculas) para habilitar el botón de envío final.
  4. Botón "Restaurar base de datos" → `POST /admin/backups/restaurar`, con el archivo subido y el texto de confirmación.
- El controlador:
  - Valida que el campo de confirmación sea exactamente `RESTAURAR` (si no, rechaza con error, sin tocar la base).
  - Valida que el archivo subido tenga extensión `.sql` y no esté vacío.
  - Lee el contenido del archivo y separa las sentencias SQL individuales (split por `;` respetando cadenas de texto, siguiendo el mismo enfoque que usan los dumps generados por `ifsnop/mysqldump-php`, que no incluyen `;` dentro de valores sin escapar).
  - Ejecuta las sentencias en orden vía `DB::unprepared()`. Como los dumps de `mysqldump-php` incluyen `DROP TABLE IF EXISTS` antes de cada `CREATE TABLE`, el resultado es un **reemplazo completo** de las tablas incluidas en el archivo — no un merge selectivo.
  - Si una sentencia falla a mitad de camino, se reporta el mensaje de error específico devuelto por MySQL y en qué sentencia ocurrió (número de línea/orden), para poder diagnosticar. No hay rollback automático más allá de lo que MySQL haga por sí solo con las tablas ya completadas — es una operación irreversible por diseño; la única red de seguridad es el backup previo descargado en el paso 2.
- Misma restricción de acceso: `middleware(['auth', 'admin'])`.

### Por qué no transacción completa

MySQL con el motor InnoDB soporta transacciones, pero sentencias DDL (`CREATE TABLE`, `DROP TABLE`) hacen *commit implícito* en MySQL — no se pueden envolver en una transacción reversible junto con las demás sentencias. Por eso el diseño acepta que la restauración es irreversible y compensa exigiendo el backup de seguridad previo en vez de prometer un rollback que MySQL no puede dar.

---

## 4. Seguridad y auditoría

- Ambas rutas exclusivamente bajo `middleware(['auth', 'admin'])`.
- Cada backup creado y cada restauración ejecutada quedan registrados (usuario, timestamp, y en el caso de restauración, nombre del archivo subido) vía `Log::channel('...')` o una tabla simple `backups_log` — a definir en el plan de implementación cuál de las dos, priorizando lo más simple que ya encaje con el resto del proyecto.
- El límite de tamaño de archivo subido para restaurar debe ser generoso pero explícito (validación `max:` en la request) para evitar timeouts silenciosos; el valor concreto se ajusta en el plan según el tamaño real de la base actual.

---

## 5. Testing

Nuevo `tests/Feature/Admin/BackupControllerTest.php`:

1. Un administrador puede descargar un backup — la respuesta contiene al menos un `CREATE TABLE` de una tabla conocida (p. ej. `users`).
2. Un usuario no-administrador (instructor o estudiante) recibe 403 en ambas rutas (`/admin/backups`, crear, restaurar).
3. Subir un `.sql` válido con datos de prueba reemplaza correctamente el contenido de una tabla verificable.
4. Subir un archivo con extensión distinta a `.sql` es rechazado con error de validación, sin tocar la base.
5. Enviar el formulario de restauración sin escribir `RESTAURAR` exactamente es rechazado, sin tocar la base.

---

## Fuera de alcance (explícito)

- Backups automáticos o programados (cron/scheduler) — Railway no tiene ningún mecanismo de scheduler configurado hoy en este proyecto; agregarlo es trabajo aparte.
- Almacenamiento externo del archivo de backup (S3, Google Drive, etc.) — el archivo vive donde el usuario lo descargue.
- Notificaciones o alertas de éxito/fallo de backup.
- Backups parciales o selectivos por tabla — siempre es la base completa.
- Restauración de la base de datos **local** de desarrollo — ese caso ya está cubierto por los seeders existentes (`DatabaseSeeder`, `CursosDemoSeeder`).
