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

## Autodeploy (GitHub Actions)
`.github/workflows/deploy-hostinger.yml` despliega solo en cada push a `master` (también a mano: Actions → *Deploy a Hostinger* → Run workflow). Pasos: tests → `npm run build` y `composer install --no-dev` en GitHub (el servidor no tiene Node ni `proc_open`) → `rsync` por SSH → `migrate --force` + recache → healthcheck `/up`. Si fallan los tests no se despliega nada.

- **Qué no toca nunca:** `.env`, `storage/`, `bootstrap/cache/`, `.git/` ni los archivos que el servidor genera (`public/certificados`, `public/fonts`…). El borrado de archivos viejos (`rsync --delete`) solo se aplica a `app config database resources routes lang vendor public/build`.
- **Sin secretos el job pasa en verde sin desplegar** (deja un aviso en el log), así que mergear el workflow es seguro.
- Railway sigue desplegando por su cuenta; ambos reciben el mismo `master`.

### Activarlo (una sola vez)
1. Generar una llave solo para deploy: `ssh-keygen -t ed25519 -f hostinger_deploy -C "github-actions-deploy" -N ""`.
2. hPanel → Avanzado → **SSH Access** → pegar la llave pública (`hostinger_deploy.pub`). Ahí mismo están el host/IP y el puerto (normalmente `65002`). La API de Hostinger no permite autorizar llaves de login, por eso es manual.
3. Obtener `known_hosts`: `ssh-keyscan -p 65002 <host>`.
4. Crear estos *secrets* del repo (Settings → Secrets and variables → Actions, o `gh secret set NOMBRE`):

| Secreto | Valor |
|---|---|
| `HOSTINGER_SSH_KEY` | contenido de la llave **privada** `hostinger_deploy` |
| `HOSTINGER_KNOWN_HOSTS` | salida de `ssh-keyscan` |
| `HOSTINGER_HOST` | host/IP del paso 2 |
| `HOSTINGER_PORT` | puerto SSH (p. ej. `65002`) |
| `HOSTINGER_USER` | `u777249992` |
| `HOSTINGER_PATH` | ruta absoluta de la app, p. ej. `/home/u777249992/domains/<sitio>/public_html/laravel` |

5. (Opcional) variable `HOSTINGER_URL` si cambia el dominio (cutover); por defecto usa el dominio temporal.
6. Borrar la llave privada local tras guardarla como secreto.

### Despliegue manual (si el autodeploy está caído)
El `git pull` en el servidor **ya no es el camino**: el workflow sube los archivos con rsync y el directorio puede quedar con cambios respecto a `git`. Para un deploy manual, ejecutar el workflow a mano desde GitHub. Solo si GitHub Actions no está disponible: `git pull` + `composer install` (con `php -d disable_functions=`) + `migrate --force` + recache en el servidor, y subir `public/build` compilado en local.

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
