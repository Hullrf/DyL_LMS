# Despliegue en Hostinger (Cloud Professional)

Runbook de lo que ya está montado y checklist para el cutover desde Railway. Los secretos (contraseña de BD, `APP_KEY`, token de API) **no** viven en el repo.

## Estado actual (entorno de pruebas)
- Cuenta de Hostinger: la de `formacion@dylqualityconsulting.com` (plan Cloud Professional, usuario SSH `u777249992`).
- Sitio temporal: `coral-seahorse-186583.hostingersite.com` (PHP 8.3).
- Código: clon de `Hullrf/DyL_LMS` rama `master` en `~/domains/<sitio>/public_html/laravel/`.
- Raíz del sitio (`public_html/.htaccess`) reescribe todo a `laravel/public/`; así `.env` y el código quedan inalcanzables por HTTP.
- BD MySQL `u777249992_lms` (host `127.0.0.1` desde la app). `.env` en `laravel/.env`, permisos 600.
- Railway sigue siendo producción hasta el cutover.

## Particularidades de Hostinger (compartido/cloud)
- No hay Node en el servidor: los assets de Vite se compilan en local (`npm ci && npm run build`) y se suben `public/build` por el Administrador de archivos.
- `proc_open` está en `disable_functions` (PHP web). Composer lo necesita: ejecutar con `php -d disable_functions= $(which composer) install --no-dev --optimize-autoloader`. No relajar `disable_functions` en la configuración del sitio.
- `storage:link` también necesita `symlink`: `php -d disable_functions= artisan storage:link`.
- No hay worker de colas: dejar `QUEUE_CONNECTION=sync`, o crear un cron con `queue:work --stop-when-empty`.
- `.env` difiere de `.env.example`: `LOG_CHANNEL=single` (no `stderr`), `MAIL_MAILER=log` hasta tener SMTP real, `APP_URL` del dominio.

## Actualizar el código
```bash
cd ~/domains/<sitio>/public_html/laravel
git pull --ff-only
php -d disable_functions= $(which composer) install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan config:cache && php artisan route:cache && php artisan view:cache
```
Si cambió JS/CSS/Tailwind: recompilar en local y volver a subir `public/build`.

## Cron del scheduler (hPanel → Avanzado → Cron Jobs)
Cada 5 minutos (equivale al servicio scheduler de Railway):
```
cd /home/u777249992/domains/<sitio>/public_html/laravel && php artisan schedule:run >> /dev/null 2>&1
```

## Checklist de cutover (cuando llegue el dominio)
1. Confirmar que la transferencia terminó y el dominio está en la cuenta `formacion@`.
2. Crear el subdominio del LMS (p. ej. `campus.<dominio>`) apuntando al mismo sitio (alias/parked o sitio nuevo) y esperar SSL activo.
3. Cambiar `APP_URL` en `.env` al dominio final; `php artisan config:clear && php artisan config:cache`.
4. Poner Railway en mantenimiento (o avisar a los usuarios) y exportar su MySQL (`mysqldump`) y los archivos de `storage/app/public`.
5. **Vaciar la BD de Hostinger** (borra los usuarios de prueba del seeder) e importar el dump de Railway; subir los archivos de storage. Operación destructiva: confirmarla con el responsable.
6. `php artisan migrate --force` (por si Railway va atrás en migraciones) y recachear.
7. Reconfigurar SMTP real en `.env` (`MAIL_*`).
8. Actualizar DNS/`NEXT_PUBLIC_LMS_URL` de la landing; verificar login, certificados PDF y 2FA en el dominio final.
9. Regenerar el token de API de Hostinger y rotar la contraseña de la BD si se expusieron durante el montaje.
10. Mantener Railway unos días como respaldo antes de apagarlo.
