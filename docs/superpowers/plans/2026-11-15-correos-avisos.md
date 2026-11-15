# Avisos por correo con opt-out por usuario — Plan de implementación

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** que los avisos de vencimientos, calificaciones, anuncios y certificados también lleguen por correo, con interruptores por categoría en el Perfil de cada usuario.

**Architecture:** `Notificacion` escucha su evento `created` y delega en `AvisosPorCorreoService`, que decide si corresponde y encola un `AvisoLms` (Mailable en cola). La preferencia vive en `users.correo_desactivados` (JSON). La cola `database` la procesa el scheduler existente cada 5 minutos.

**Tech Stack:** Laravel 12, PHP 8.2, MySQL (tests: sqlite en memoria), Blade + Alpine + Tailwind, `Mail::fake()` en tests.

**Spec:** `docs/superpowers/specs/2026-11-15-correos-avisos-design.md`

## Global Constraints
- Todo en español: columnas, textos de UI y del correo, nombres de clases de dominio.
- Migración solo aditiva y reversible (llega a producción de Railway en el siguiente deploy); nunca `migrate:fresh`.
- No tocar: `CalificacionService`, `NotaCursoService`, `CertificadoService` (elegibilidad), `IsAdmin`, `IsInstructor`, `TwoFactorMiddleware`, `railway.toml`, `railway.cron.toml`, `nginx.template.conf`. Los 9 sitios que llaman `Notificacion::crear` y el comando `notificaciones:vencimientos` **no se modifican**.
- Categorías (clave → tipos): `vencimientos` → `vencimiento`; `calificaciones` → `calificacion`, `intento_extra`; `anuncios` → `anuncio`; `certificados` → `certificado`. No salen por correo: `foro`, `mensaje`, `entrega`.
- Remitente y SMTP (Zoho) se configuran en el `.env` de Hostinger, no en el repo.
- UI: componentes existentes (`card`, `card-body`, `form-label`, `btn-primary`), tokens de color existentes, modo oscuro y móvil verificados con Playwright.
- Tests: `php artisan test` (sqlite en memoria). `git add` solo de archivos puntuales; rama `worktree-correos-avisos`.

## Review Focus
1. Un fallo del correo (Zoho caído) no debe impedir crear el aviso ni la acción que lo origina → Task 3.
2. Usuario inactivo, eliminado (soft delete) o sin correo no recibe nada → Task 3.
3. Correr `notificaciones:vencimientos` dos veces no manda el correo dos veces → Task 3.
4. Aviso sin enlace (`data` nulo) y título/mensaje con HTML (`<script>`) → el correo se genera sin romperse y escapa el HTML → Task 2.
5. Guardar el formulario sin ninguna casilla marcada apaga las 4 categorías, y una categoría inventada se rechaza → Task 4.

---

### Task 1: Categorías y preferencia en `User`

**Files:**
- Create: `database/migrations/2026_11_15_000001_add_correo_desactivados_to_users_table.php`
- Create: `app/Services/AvisosPorCorreoService.php`
- Modify: `app/Models/User.php` (cast + método)
- Test: `tests/Feature/AvisosCorreoCategoriasTest.php`

**Interfaces:**
- Produces: `AvisosPorCorreoService::CATEGORIAS` (array clave→etiqueta: `vencimientos`=>"Vencimientos de entregas", `calificaciones`=>"Calificaciones", `anuncios`=>"Anuncios", `certificados`=>"Certificados"); `AvisosPorCorreoService::TIPOS` (tipo→clave, según Global Constraints); `AvisosPorCorreoService::categoriaDe(string $tipo): ?string`; `User::recibeCorreo(string $categoria): bool`; atributo `users.correo_desactivados` (json nullable, cast `array`).

- [ ] **Step 0: Preparar el entorno del worktree** — copiar `.env` desde el checkout principal (`..\..\..\.env`), `composer install`, `npm ci`. Verificar con `php artisan test --filter=NotificarVencimientosTest` → pasa.
- [ ] **Step 1: Escribir los tests** en `AvisosCorreoCategoriasTest` (usa `RefreshDatabase`):
  - `test_categoria_de_mapea_los_tipos_incluidos`: `vencimiento→'vencimientos'`, `calificacion→'calificaciones'`, `intento_extra→'calificaciones'`, `anuncio→'anuncios'`, `certificado→'certificados'`; `foro`, `mensaje`, `entrega`, `'otro'` → `null`.
  - `test_recibe_correo_por_defecto_en_todas_las_categorias`: `new User()` → `recibeCorreo($c)` true para cada clave de `CATEGORIAS`.
  - `test_recibe_correo_respeta_categorias_desactivadas`: `correo_desactivados = ['anuncios']` → `recibeCorreo('anuncios')` false y las otras tres true.
  - `test_la_columna_existe_y_acepta_null`: `Schema::hasColumn('users','correo_desactivados')` y un usuario creado con la fábrica tiene el atributo `null`.
- [ ] **Step 2: Ejecutar** `php artisan test --filter=AvisosCorreoCategoriasTest` → falla (clase/columna inexistente).
- [ ] **Step 3: Implementar** la migración (`json('correo_desactivados')->nullable()` en `users`, `down` hace `dropColumn`), el servicio con las constantes y `categoriaDe`, y en `User` el cast `'correo_desactivados' => 'array'` más `recibeCorreo(string $categoria): bool` (`!in_array($categoria, $this->correo_desactivados ?? [], true)`).
- [ ] **Step 4: Ejecutar** el mismo filtro → pasa.
- [ ] **Step 5: Commit** — `git add` de los 4 archivos; `feat: categorías de avisos por correo y preferencia por usuario`.

### Task 2: Correo `AvisoLms` y su plantilla

**Files:**
- Create: `app/Mail/AvisoLms.php`, `resources/views/emails/aviso.blade.php`
- Test: `tests/Feature/AvisoLmsMailTest.php`

**Interfaces:**
- Consumes: modelo `Notificacion` (`titulo`, `mensaje`, `data['url'] ?? null`).
- Produces: `new AvisoLms(Notificacion $notificacion)`: `implements ShouldQueue`, `public int $tries = 3`, asunto = `$notificacion->titulo`, vista `emails.aviso` con `titulo`, `mensaje`, `url` (?string) y `perfilUrl` = `route('profile.edit')`. Método público de prueba: `$mail->render()` (de `Mailable`).

- [ ] **Step 1: Escribir los tests** (`RefreshDatabase`, `Notificacion::crear(...)` con `Mail::fake()` para no encolar nada aún):
  - `test_asunto_es_el_titulo_del_aviso`: `envelope()->subject === 'Actividad calificada'`.
  - `test_el_cuerpo_incluye_titulo_mensaje_boton_y_enlace_al_perfil`: `render()` contiene título, mensaje, el texto `Abrir en el campus`, el `url` del aviso y `route('profile.edit')`.
  - `test_sin_enlace_no_muestra_el_boton`: aviso con `data` nulo → `render()` no contiene `Abrir en el campus` y no lanza.
  - `test_escapa_el_html_del_titulo_y_del_mensaje`: título `<script>alert(1)</script>` → `render()` no contiene `<script>` y sí `&lt;script&gt;`.
- [ ] **Step 2: Ejecutar** `--filter=AvisoLmsMailTest` → falla.
- [ ] **Step 3: Implementar** el Mailable y la vista: HTML de correo con tablas y estilos en línea; logo `asset('images/logo/logo-icon-dark-256.png')`, botón con `#C2410C` (naranja de marca), texto sobrio; pie: "Recibes este correo porque tienes una cuenta en D&L Quality Campus. Elige qué avisos recibir en tu perfil." enlazando `perfilUrl`. Toda interpolación con `{{ }}` (escapada).
- [ ] **Step 4: Ejecutar** el filtro → pasa.
- [ ] **Step 5: Commit** — `feat: correo AvisoLms con plantilla de marca`.

### Task 3: Enganche en `Notificacion`

**Files:**
- Modify: `app/Services/AvisosPorCorreoService.php`, `app/Models/Notificacion.php`
- Test: `tests/Feature/AvisosCorreoEnvioTest.php`

**Interfaces:**
- Consumes: `categoriaDe`, `User::recibeCorreo`, `new AvisoLms($notificacion)`.
- Produces: `AvisosPorCorreoService::enviarSiCorresponde(Notificacion $n): bool` (true = se encoló; nunca lanza: captura `Throwable`, registra `Log::warning('No se pudo encolar el aviso por correo', ...)` y devuelve false); `Notificacion::booted()` registra `static::created` que lo invoca. Reglas para enviar: tipo con categoría, usuario existente (no eliminado), `estado === 'activo'`, correo no vacío, `recibeCorreo(categoria)`; envío con `Mail::to($user->email)->queue(new AvisoLms($n))`.

- [ ] **Step 1: Escribir los tests** (`RefreshDatabase`, `ArmaCursoCompleto`, `ArmaActividades`, `Mail::fake()` salvo el de fallo):
  - `test_encola_un_correo_por_cada_tipo_incluido` (data provider: `vencimiento`, `calificacion`, `intento_extra`, `anuncio`, `certificado`): `Notificacion::crear($u->id, $tipo, 'T', 'M', 'https://x.test')` → `Mail::assertQueued(AvisoLms::class, fn ($m) => $m->hasTo($u->email))`.
  - `test_no_encola_foro_mensaje_ni_entrega`: `Mail::assertNothingQueued()`.
  - `test_respeta_el_opt_out_de_la_categoria`: `correo_desactivados=['anuncios']` → `anuncio` no se encola, `calificacion` sí.
  - `test_no_envia_a_usuario_inactivo` (`estado='inactivo'`) y `test_no_envia_a_usuario_eliminado` (soft delete) → nada encolado.
  - `test_un_fallo_del_correo_no_rompe_la_creacion_del_aviso` (sin `Mail::fake`): `Mail::shouldReceive('to')->andThrow(new \RuntimeException('Zoho caído'))`; `Notificacion::crear(...)` no lanza y `assertDatabaseHas('notificaciones', ['tipo' => 'anuncio'])`.
  - `test_vencimientos_manda_un_solo_correo_aunque_el_comando_corra_dos_veces`: `cursoInscrito` + `actividadEn($curso, ['fecha_cierre' => now()->addHours(12)])`; `artisan('notificaciones:vencimientos')` dos veces → `Mail::assertQueued(AvisoLms::class, 1)`.
- [ ] **Step 2: Ejecutar** `--filter=AvisosCorreoEnvioTest` → falla.
- [ ] **Step 3: Implementar** `enviarSiCorresponde` y el `booted()` del modelo según Interfaces.
- [ ] **Step 4: Ejecutar** el filtro, más `--filter=NotificarVencimientosTest` y `--filter=CertificadoServiceTipoTest` (consumen `Notificacion`) → pasan.
- [ ] **Step 5: Commit** — `feat: encolar correo al crear avisos de las categorías incluidas`.

### Task 4: Sección "Avisos por correo" en Perfil

**Files:**
- Modify: `routes/web.php` (junto a las rutas `profile.*`, ~línea 199), `app/Http/Controllers/ProfileController.php`, `resources/views/profile/edit.blade.php`
- Create: `resources/views/profile/partials/update-avisos-correo-form.blade.php`
- Test: `tests/Feature/PerfilAvisosCorreoTest.php`

**Interfaces:**
- Consumes: `AvisosPorCorreoService::CATEGORIAS`, `User::correo_desactivados`.
- Produces: ruta `PATCH /profile/avisos-correo` nombre `profile.avisos-correo`; `ProfileController::actualizarAvisosCorreo(Request $request): RedirectResponse` (valida `avisos` nullable array y `avisos.*` con `Rule::in(array_keys(CATEGORIAS))`; guarda con `forceFill` `correo_desactivados` = categorías no marcadas en el orden de `CATEGORIAS`, o `null` si no hay ninguna desactivada; redirige a `profile.edit` con `status` = `avisos-correo-updated`); campos `avisos[]` con `value="{clave}"` seguido de `@checked(...)` en la misma etiqueta.

- [ ] **Step 1: Escribir los tests** (`RefreshDatabase`, `actingAs(User::factory()->create())`):
  - `test_el_perfil_muestra_las_cuatro_categorias`: `get(route('profile.edit'))` ve las 4 etiquetas.
  - `test_el_formulario_refleja_lo_guardado`: con `['anuncios']` desactivado, el HTML cumple `assertDoesNotMatchRegularExpression('/value="anuncios"[^>]*checked/')` y `assertMatchesRegularExpression('/value="vencimientos"[^>]*checked/')`.
  - `test_guarda_solo_las_categorias_marcadas`: `patch(..., ['avisos' => ['anuncios']])` → `correo_desactivados === ['vencimientos','calificaciones','certificados']`.
  - `test_todas_marcadas_guarda_null`.
  - `test_sin_ninguna_marcada_desactiva_las_cuatro` (sin campo `avisos`).
  - `test_rechaza_categorias_inventadas`: `['avisos' => ['spam']]` → `assertSessionHasErrors('avisos.0')` y nada guardado.
  - `test_invitado_es_redirigido_al_login`.
- [ ] **Step 2: Ejecutar** `--filter=PerfilAvisosCorreoTest` → falla.
- [ ] **Step 3: Implementar** ruta, método y parcial. Parcial al estilo de `update-password-form` (encabezado `font-display text-2xl`, texto de ayuda, casillas `class="rounded text-dyl-orange-700"` con su `form-label` y una línea de explicación por categoría, botón `btn-primary` "Guardar preferencias", mensaje "Preferencias guardadas." con el patrón Alpine existente). Incluirlo en `edit.blade.php` como nueva `card` después de "Cambiar contraseña".
- [ ] **Step 4: Ejecutar** el filtro → pasa; `--filter=ProfileTest` (si existe) y `--filter=LmsAuthTest` → pasan.
- [ ] **Step 5: Commit** — `feat: preferencias de avisos por correo en el perfil`.

### Task 5: Cola programada y documentación de configuración

**Files:**
- Modify: `routes/console.php`, `docs/HOSTINGER.md`, `docs/superpowers/specs/2026-11-15-correos-avisos-design.md` (estado → aprobado)
- Test: `tests/Feature/ColaProgramadaTest.php`

- [ ] **Step 1: Escribir el test** `test_el_scheduler_procesa_la_cola_cada_cinco_minutos`: `artisan('schedule:list')->expectsOutputToContain('queue:work')->assertSuccessful()`.
- [ ] **Step 2: Ejecutar** `--filter=ColaProgramadaTest` → falla.
- [ ] **Step 3: Implementar** en `routes/console.php`: `Schedule::command('queue:work --stop-when-empty --max-time=240')->everyFiveMinutes()->withoutOverlapping();`. En `docs/HOSTINGER.md` añadir la sección "Correo (Zoho SMTP) y cola" con las variables del `.env` (`MAIL_MAILER=smtp`, `MAIL_HOST=smtp.zoho.com`, `MAIL_PORT=465`, `MAIL_SCHEME=smtps`, `MAIL_USERNAME` y `MAIL_FROM_ADDRESS=campus@dylqualityconsulting.com`, `MAIL_PASSWORD`=contraseña de aplicación de Zoho, `QUEUE_CONNECTION=database`), el comando para recachear (`php artisan config:cache`) y una prueba de envío (`php artisan tinker` → `Mail::raw('Prueba', fn ($m) => $m->to('…')->subject('Prueba LMS'))`).
- [ ] **Step 4: Ejecutar** el filtro → pasa.
- [ ] **Step 5: Commit** — `feat: procesar la cola desde el scheduler y documentar SMTP`.

### Task 6: Verificación y PR

- [ ] **Step 1: Suite completa** — `php artisan test` → todo en verde.
- [ ] **Step 2: Build** — `npm run build` (por clases Tailwind nuevas) → sin errores.
- [ ] **Step 3: Correo en navegador** — generar el HTML con `tinker` (`(new \App\Mail\AvisoLms($n))->render()`) a un archivo temporal y abrirlo con Playwright a 1280 px y 390 px; revisar logo, botón y pie.
- [ ] **Step 4: Perfil en navegador** — `php artisan migrate` (local, nunca `fresh`), `php artisan serve`; con Playwright: iniciar sesión (el usuario inicia sesión en la ventana), abrir Perfil, apagar una categoría, guardar y recargar; revisar escritorio, móvil y modo oscuro, y consola sin errores.
- [ ] **Step 5: PR** — `git push -u origin worktree-correos-avisos` y `gh pr create` (el usuario hace el merge: toca modelo, migración y rutas). Dejar en el PR el checklist de pasos manuales: crear buzón/alias y contraseña de aplicación en Zoho, actualizar el `.env` de Hostinger, `config:cache`, correo de prueba.
