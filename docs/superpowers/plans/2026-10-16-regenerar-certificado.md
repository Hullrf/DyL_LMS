# Botón "Regenerar certificado" Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Que un admin o el instructor dueño del curso rehaga el PDF de un certificado ya emitido con la plantilla vigente, desde `certificados.show`.

**Architecture:** Una ruta POST nueva en `CertificadoController` que autoriza (admin o `curso.created_by`), llama a `CertificadoService::generarPdf()`, borra el PDF viejo si la ruta cambió y actualiza `archivo_pdf`. Sin migraciones ni policy nueva. El botón vive solo en `certificados/show.blade.php`.

**Tech Stack:** Laravel 12, PHP 8.2, mPDF, PHPUnit (`php artisan test`, sqlite en memoria).

**Spec:** `docs/superpowers/specs/2026-10-16-regenerar-certificado-design.md`

## Global Constraints

- Ruta: `POST /certificados/{certificado}/regenerar`, nombre `certificados.regenerar`, dentro del grupo `auth` existente (no middleware `instructor`).
- Autorización: `esAdmin() || curso->created_by === user->id`, si no `abort(403)`. El estudiante dueño NO pasa.
- El registro `Certificado` no cambia salvo `archivo_pdf` (mismo `numero_certificado`, `fecha_emision`, `aprobado_por_id`).
- Mensajes: éxito `'Certificado regenerado con la plantilla actual.'`; error de diplomado: `"falta el número de documento de {nombre}; pídele que lo complete en su perfil"`.
- Si la generación falla, no se borra ni se modifica nada.
- Tests: NUNCA `migrate:fresh` contra la BD local; solo `php artisan test`.

## Review Focus

- Estudiante dueño del certificado intenta POST → 403 (el `autorizar()` actual lo dejaría pasar).
- Instructor de otro curso → 403.
- Diplomado sin `numero_documento` → error y PDF anterior intacto.
- Regenerar en otro año → el PDF viejo se borra, `archivo_pdf` apunta al nuevo.
- Archivo viejo inexistente en disco (Railway efímero) → no falla al intentar borrarlo.

---

### Task 1: Ruta y método `regenerar`

**Files:**
- Modify: `routes/web.php:166` (junto a `certificados.previsualizar`)
- Modify: `app/Http/Controllers/CertificadoController.php` (nuevo método público `regenerar`)
- Test: `tests/Feature/CertificadoRegenerarTest.php` (crear)

**Interfaces:**
- Consumes: `CertificadoService::generarPdf(Certificado): string` (devuelve ruta relativa en disco `public`, lanza `\RuntimeException` si es diplomado sin documento); `User::esAdmin(): bool`.
- Produces: `CertificadoController::regenerar(Certificado $certificado): RedirectResponse`, ruta `certificados.regenerar`.

**Nota de tests:** `generarPdf` escribe en `storage_path('app/public/...')` real, `Storage::fake` no lo intercepta. Los tests de autorización y flujo mockean el servicio (`$this->mock(CertificadoService::class)`); un solo test genera un PDF real y lo borra en `finally`.

- [ ] **Step 1: Escribir los tests que fallan** en `CertificadoRegenerarTest` (`RefreshDatabase`, helper privado `crearCertificado(User $instructor, array $cursoAttrs = [])` como en `CertificadoShowPreviewTest`):
  - `test_estudiante_dueno_recibe_403`: POST como estudiante → `assertForbidden()`.
  - `test_instructor_ajeno_recibe_403`.
  - `test_instructor_dueno_regenera`: servicio mockeado `shouldReceive('generarPdf')->once()->andReturn('certificados/2026/nuevo.pdf')`; `assertRedirect()` + `assertSessionHas('success', 'Certificado regenerado con la plantilla actual.')`; `archivo_pdf` == `'certificados/2026/nuevo.pdf'`.
  - `test_admin_regenera`: igual con admin (rol Administrador como en `AprobarCertificadoTest`).
  - `test_diplomado_sin_documento_conserva_pdf`: mock lanza `\RuntimeException`; `assertSessionHas('error')`; `archivo_pdf` y archivo viejo (`Storage::fake('public')`) intactos.
  - `test_cambio_de_ruta_borra_el_pdf_viejo`: archivo viejo en `Storage::fake('public')` bajo `certificados/2025/...`, mock devuelve ruta `certificados/2026/...`; el viejo ya no existe.
  - `test_viejo_inexistente_no_falla`: `archivo_pdf` apunta a ruta sin archivo; respuesta redirect sin excepción.
  - `test_regenera_pdf_real_con_plantilla_actual`: sin mock, curso tipo `diploma`, admin; assert `file_exists(storage_path('app/public/'.$certificado->fresh()->archivo_pdf))`; borrar el archivo en `finally`.

- [ ] **Step 2: Correr y ver que fallan**

Run: `php artisan test --filter=CertificadoRegenerarTest`
Expected: FAIL (ruta `certificados.regenerar` no definida).

- [ ] **Step 3: Implementar** `public function regenerar(Certificado $certificado): RedirectResponse` en `CertificadoController` y la ruta `Route::post('/certificados/{certificado}/regenerar', [CertificadoController::class, 'regenerar'])->name('certificados.regenerar');`. Orden: autorizar → `try generarPdf` / `catch \RuntimeException` (redirect back con `error`) → si `$ruta !== $certificado->archivo_pdf && $certificado->archivo_pdf` → `Storage::disk('public')->delete($viejo)` (delete ya es silencioso si no existe) → `update(['archivo_pdf' => $ruta])` → redirect back con `success`. La autorización va inline (admin o `created_by`), sin tocar `autorizar()`.

- [ ] **Step 4: Correr y ver que pasan**

Run: `php artisan test --filter=CertificadoRegenerarTest`
Expected: PASS (8 tests).

- [ ] **Step 5: Commit**

```bash
git add routes/web.php app/Http/Controllers/CertificadoController.php tests/Feature/CertificadoRegenerarTest.php
git commit -m "feat: ruta para regenerar el PDF de un certificado (admin/instructor dueño)"
```

### Task 2: Botón en `certificados/show`

**Files:**
- Modify: `resources/views/certificados/show.blade.php` (cabecera, junto a "Descargar PDF")
- Test: `tests/Feature/CertificadoRegenerarTest.php`

**Interfaces:**
- Consumes: ruta `certificados.regenerar` (Task 1).
- Produces: formulario POST con `@csrf` y botón "Regenerar PDF".

- [ ] **Step 1: Escribir los tests que fallan**:
  - `test_boton_visible_para_admin_y_dueno_del_curso`: `get(certificados.show)` como admin y como instructor dueño → `assertSee('Regenerar PDF')` y `assertSee(route('certificados.regenerar', $c), false)`.
  - `test_boton_oculto_para_el_estudiante`: `assertDontSee('Regenerar PDF')`.

- [ ] **Step 2: Correr y ver que fallan**

Run: `php artisan test --filter=CertificadoRegenerarTest`
Expected: FAIL en los dos tests de vista.

- [ ] **Step 3: Implementar** en `show.blade.php`: agrupar "Descargar PDF" y el nuevo formulario en un `div.flex.gap-2`; el formulario va dentro de `@if(auth()->user()->esAdmin() || $certificado->curso->created_by === auth()->id())`, botón secundario (borde gris) con `onsubmit="return confirm('¿Regenerar el PDF con la plantilla actual?')"`, mismo patrón que `celda-certificado.blade.php`. Confirmar que el layout ya muestra `success`/`error` (si no, no tocarlo: el spec dice que sí).

- [ ] **Step 4: Correr la suite de certificados**

Run: `php artisan test --filter=Certificado`
Expected: PASS, sin regresiones en `CertificadoShowPreviewTest`.

- [ ] **Step 5: Commit**

```bash
git add resources/views/certificados/show.blade.php tests/Feature/CertificadoRegenerarTest.php
git commit -m "feat: botón Regenerar PDF en la vista del certificado"
```

---

## Self-Review

- **Cobertura del spec:** ruta, autorización, flujo de 6 pasos, borrado del PDF viejo, caso diplomado, UI en `show` (sin tocar `celda-certificado`), los 7 tests del spec (el 1 como test real con limpieza, el resto con mock) → Tasks 1 y 2.
- **Desviación del spec:** los tests no usan `Storage::fake` para la generación real porque `generarPdf` escribe con `storage_path`; se mockea el servicio y un solo test genera de verdad.
- **Consistencia:** `regenerar`, `certificados.regenerar` y el texto `'Regenerar PDF'` son iguales en ambas tareas.
