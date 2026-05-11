# DEVELOPMENT.md — LMS DyL Quality Consulting

## Prerequisitos

- PHP 8.2+ con extensiones: `gd`, `mbstring`, `pdo_mysql`, `zip`, `redis` (o predis)
- MySQL 8.0+
- Node.js 18+ y npm
- Composer 2.x
- XAMPP (entorno local) o servidor Apache/Nginx

## Setup Local

```bash
# 1. Clonar repositorio
git clone <repo-url> lms-dyl-quality
cd lms-dyl-quality

# 2. Instalar dependencias PHP
composer install

# 3. Copiar y configurar .env
cp .env.example .env
php artisan key:generate
# Editar .env:
#   DB_DATABASE=dyl_lms
#   DB_USERNAME=root
#   DB_PASSWORD=

# 4. Crear base de datos
# En MySQL: CREATE DATABASE dyl_lms;

# 5. Ejecutar migraciones y seeders
php artisan migrate --seed

# 6. Enlace de storage para archivos subidos
php artisan storage:link

# 7. Instalar dependencias JS y compilar
npm install && npm run build

# 8. Iniciar servidor de desarrollo
php artisan serve
```

### Usuarios de prueba

| Email | Password | Rol |
|-------|----------|-----|
| admin@dyl-quality.test | password123 | Administrador |
| instructor@dyl-quality.test | password123 | Instructor |
| student@dyl-quality.test | password123 | Estudiante |

## Arquitectura

### Patrones usados

| Patrón | Dónde |
|--------|-------|
| MVC | Controllers, Models, Blade Views |
| Service Layer | `CalificacionService`, `CertificadoService`, `ReporteService` |
| Policy Pattern | `CursoPolicy`, `LeccionPolicy`, `ActividadPolicy` |
| Middleware de roles | `IsAdmin`, `IsInstructor`, `TwoFactorMiddleware` |
| Form Requests | Validación en `app/Http/Requests/` |

### Modelos y tablas

| Modelo | Tabla | Descripción |
|--------|-------|-------------|
| User | users | Usuarios (SoftDeletes, 2FA) |
| Rol | roles | Administrador / Instructor / Estudiante |
| Curso | cursos | Curso con estado borrador/publicado/archivado |
| Modulo | modulos | Módulo dentro de un curso |
| Leccion | lecciones | Lección con contenido HTML (Quill.js) y video |
| Actividad | actividades | Cuestionario / Ensayo / Tarea / Práctica |
| Pregunta | preguntas | Pregunta de cuestionario (opción múltiple, V/F, respuesta corta) |
| Opcion | opciones | Opción de respuesta |
| RecursoActividad | recurso_actividades | Materiales de apoyo adjuntos a actividades |
| Inscripcion | inscripciones | Relación usuario-curso |
| ProgresoLeccion | progreso_lecciones | Lección completada por usuario |
| RespuestaEstudiante | respuestas_estudiantes | Respuesta a actividad |
| Certificado | certificados | Certificado PDF generado |

### Estructura de Controllers

```
app/Http/Controllers/
├── Admin/
│   ├── UsuarioController.php       # CRUD usuarios (solo admin)
│   └── AuditoriaController.php     # Log de auditoría (solo admin)
├── Auth/
│   ├── TwoFactorController.php     # Setup/verify/enable/disable 2FA
│   └── ... (Breeze controllers)
├── DashboardController.php         # Redirige según rol, datos de charts
├── CursoController.php             # CRUD cursos + inscribirse
├── ModuloController.php            # CRUD módulos, reordenar
├── LeccionController.php           # CRUD lecciones + reproductor + progreso
├── ActividadController.php         # CRUD actividades
├── PreguntaController.php          # CRUD preguntas de cuestionario
├── OpcionController.php            # CRUD opciones de pregunta
├── RecursoActividadController.php  # Materiales de apoyo por actividad
├── RespuestaEstudianteController.php  # Envío respuestas estudiante
├── CalificacionController.php      # Calificación instructor (manual/auto)
├── CertificadoController.php       # Generación y descarga PDF
├── ReporteController.php           # Reportes, CSV, PDF, Excel
└── UploadController.php            # Upload de archivos
```

### Servicios

| Servicio | Responsabilidad |
|----------|----------------|
| `CalificacionService` | Calificación automática de cuestionarios, calificación manual |
| `CertificadoService` | Genera PDF con mPDF, verifica elegibilidad (100% completado) |
| `ReporteService` | KPIs globales, reportes por curso/estudiante, CSV |

### Exportaciones (app/Exports/)

| Clase | Descripción |
|-------|-------------|
| `CursoReporteExport` | Estudiantes de un curso con progreso → .xlsx |
| `UsuariosReporteExport` | Todos los usuarios del sistema → .xlsx |

## Flujo de roles

```
Visitante → /login → Dashboard
    ├── Administrador → /dashboard (admin)
    │       Gestiona usuarios, ve reportes globales, auditoría
    ├── Instructor → /dashboard (instructor)
    │       Crea/edita cursos, califica actividades, ve reportes de sus cursos
    └── Estudiante → /dashboard (estudiante)
            Ve cursos inscritos, realiza actividades, descarga certificados
```

### Middlewares de ruta

- `auth` — Requiere login (Breeze)
- `admin` → `IsAdmin` — Solo Administradores
- `instructor` → `IsInstructor` — Instructores y Administradores
- `2fa` → `TwoFactorMiddleware` — Valida verificación 2FA si está activada

## Cómo agregar una nueva feature

1. `php artisan make:migration add_campo_to_tabla`
2. `php artisan make:model NombreModelo` (si aplica)
3. `php artisan make:controller NombreController`
4. Agregar rutas en `routes/web.php`
5. Crear vistas en `resources/views/`
6. Si el modelo necesita auditoría: implementar `Auditable` interface
7. Agregar breadcrumb en `routes/breadcrumbs.php`
8. Escribir tests en `tests/Feature/`
9. `php artisan test` — verificar que pasan
10. `npm run build` si hay cambios en JS/CSS

## Comandos frecuentes

```bash
# Servidor de desarrollo
php artisan serve
npm run dev          # watch mode (hot reload de assets)

# Base de datos
php artisan migrate
php artisan migrate:fresh --seed    # reset completo con datos de prueba
php artisan db:seed

# Tests
php artisan test
php artisan test --filter NombreTest
php artisan test tests/Feature/Admin/

# Cache
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear

# Storage
php artisan storage:link

# Composer / npm
composer dump-autoload
npm run build
```

## Troubleshooting

**"Class not found" o "Target class does not exist"**
→ Ejecutar `composer dump-autoload`

**Assets no se actualizan**
→ Ejecutar `npm run build` (o `npm run dev` en desarrollo)

**PDF no genera / error de GD**
→ Verificar que `extension=gd` esté habilitado en `C:\xampp\php\php.ini`

**Excel export falla / error de zip**
→ Verificar que `extension=zip` esté habilitado en `C:\xampp\php\php.ini`

**Error CSRF 419**
→ Verificar que el form tenga `@csrf`

**Redis no conecta**
→ Verificar que el servicio Redis esté activo en Windows (Services → Redis)
→ Verificar `REDIS_HOST=127.0.0.1` y `REDIS_PORT=6379` en `.env`

**Error "Column not found: estado"**
→ Ejecutar `php artisan migrate` (puede haber migraciones pendientes)

**2FA loop infinito**
→ Las rutas `/2fa/*` deben estar en grupo `middleware('auth')` SIN el middleware `2fa`
