# Despliegue en Hostinger (Cloud Professional)

Runbook del despliegue del LMS en Hostinger, que es **la producción** (el hosting anterior, Railway, ya no se usa). Los secretos (contraseña de BD, `APP_KEY`, token de API) **no** viven en el repo.

## Estado actual (producción)
- Cuenta de Hostinger: la de `formacion@dylqualityconsulting.com` (plan Cloud Professional, usuario SSH `u777249992`).
- URL de producción: `https://campus.dylqualityconsulting.com`, alias del sitio `coral-seahorse-186583.hostingersite.com` (PHP 8.3). La landing (repo aparte, sitio estático) está en `https://dylqualityconsulting.com`.
- DNS del dominio en Hostinger (`ns1/ns2.dns-parking.com`); el correo `@dylqualityconsulting.com` va por Zoho (MX, SPF y DKIM en la zona: no tocarlos sin saber qué se hace).
- Código: clon de `Hullrf/DyL_LMS` rama `master` en `~/domains/<sitio>/public_html/laravel/`.
- Raíz del sitio (`public_html/.htaccess`) reescribe todo a `laravel/public/`; así `.env` y el código quedan inalcanzables por HTTP.
- BD MySQL `u777249992_lms` (host `127.0.0.1` desde la app). `.env` en `laravel/.env`, permisos 600.
- El LMS dejó Railway el 2026-11-15 (ver "Cutover desde Railway" al final).

## Particularidades de Hostinger (compartido/cloud)
- No hay Node en el servidor: los assets de Vite se compilan en local (`npm ci && npm run build`) y se suben `public/build` por el Administrador de archivos.
- `proc_open` está en `disable_functions` (PHP web). Composer lo necesita: ejecutar con `php -d disable_functions= $(which composer) install --no-dev --optimize-autoloader`. No relajar `disable_functions` en la configuración del sitio.
- `storage:link` también necesita `symlink`: `php -d disable_functions= artisan storage:link`.
- No hay un worker de colas permanente. Con `QUEUE_CONNECTION=database` la cola la procesa el scheduler (`queue:work --stop-when-empty` cada 5 min, ver "Correo (Zoho SMTP) y cola"); con `sync` los correos salen dentro de la petición.
- `.env` difiere de `.env.example`: `LOG_CHANNEL=single` (no `stderr`), `MAIL_MAILER=log` hasta tener SMTP real, `APP_URL` del dominio.

## Autodeploy (GitHub Actions)
`.github/workflows/deploy-hostinger.yml` despliega solo en cada push a `master` (también a mano: Actions → *Deploy a Hostinger* → Run workflow). Pasos: tests → `npm run build` y `composer install --no-dev` en GitHub (el servidor no tiene Node ni `proc_open`) → `rsync` por SSH → `migrate --force` + recache → healthcheck `/up`. Si fallan los tests no se despliega nada.

- **Qué no toca nunca:** `.env`, `storage/`, `bootstrap/cache/`, `.git/` ni los archivos que el servidor genera (`public/certificados`, `public/fonts`…). El borrado de archivos viejos (`rsync --delete`) solo se aplica a `app config database resources routes lang vendor public/build`.
- **Sin secretos el job pasa en verde sin desplegar** (deja un aviso en el log), así que mergear el workflow es seguro.
- Es el único despliegue: Railway ya no recibe `master`.

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
Cada 5 minutos:
```
/usr/bin/php -d disable_functions= /home/u777249992/domains/<sitio>/public_html/laravel/artisan schedule:run
```
**Importante:** `schedule:run` lanza cada tarea como un proceso hijo (necesita `proc_open`), y en este servidor `proc_open` está en `disable_functions` también en la consola. Por eso el comando lleva `-d disable_functions=`. Sin esa opción el cron "corre" pero las tareas programadas (vencimientos, backup, cola de correos) fallan. Para comprobarlo por SSH: `php -r "var_dump(function_exists('proc_open'));"` (debe dar `false` sin la opción) y `php -d disable_functions= artisan schedule:run` (debe ejecutar las tareas que toquen sin error).

## Correo (Zoho SMTP) y cola
El LMS manda por correo los avisos de vencimientos, calificaciones, anuncios y certificados (cada usuario puede apagar categorías en Perfil → Avisos por correo; ver `docs/superpowers/specs/2026-11-15-correos-avisos-design.md`). Los correos van en cola y el scheduler (cron de arriba) la procesa cada 5 minutos, así que un correo puede tardar hasta ~5 min en salir.

1. En Zoho Mail: crear el buzón o alias `campus@dylqualityconsulting.com` y generar una **contraseña de aplicación** (Zoho Accounts → Seguridad → Contraseñas de aplicación). Confirmar que el plan de Zoho permite SMTP y su límite diario de envíos.
2. En el `.env` del servidor (por SSH; nunca en el repo):
   ```
   MAIL_MAILER=smtp
   MAIL_SCHEME=smtps
   MAIL_HOST=smtp.zoho.com
   MAIL_PORT=465
   MAIL_USERNAME=campus@dylqualityconsulting.com
   MAIL_PASSWORD="<contraseña de aplicación>"
   MAIL_FROM_ADDRESS=campus@dylqualityconsulting.com
   MAIL_FROM_NAME="D&L Quality Campus"
   QUEUE_CONNECTION=database
   ```
3. `php artisan config:cache`. No hay que reiniciar nada: cada pasada del scheduler arranca un worker de cola nuevo, que ya lee la configuración actualizada.
4. Prueba de envío (el correo sale al momento porque usa `send`, no la cola):
   ```
   php artisan tinker
   >>> Mail::raw('Prueba', fn ($m) => $m->to('tu@correo.com')->subject('Prueba LMS'));
   ```
5. Si algo falla: `storage/logs/laravel.log` (aviso `No se pudo encolar el aviso por correo`) y la tabla `failed_jobs`.

## Cutover desde Railway (histórico: completado el 2026-11-15)
Se conserva como referencia de lo que se hizo y de lo que sigue pendiente (token de API, respaldo de la web vieja de HostGator).
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
