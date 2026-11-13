# Installation

[Versión en español](install.md)

Requirements: **PHP 8.3** with GD (WebP), Fileinfo and EXIF, **MariaDB 10.11**, HTTPS and hosting that allows environment variables in `.htaccess`.

1. Create an empty database and its user.
2. Create a data folder **outside** the web root (for example `~/familia-datos`) and copy `examples/db.env.example` there as `db.env`
   (and, if you want PIN recovery by email, `mail.env.example` as `mail.env`). Permissions `600`.
3. Publish `src/web/` as the web folder and keep `src/app/`, `src/sql/` and `src/herramientas/` one level up, outside it.
4. In `web/.htaccess` add, as the first line, the content of `examples/htaccess.example` with the real path of the data folder.
5. Open the site: the schema and the migrations are applied automatically.
6. Create the users from the command line (it asks for the PIN without echo):
   ```bash
   php src/herramientas/usuario.php alta Name admin    # adult
   php src/herramientas/usuario.php alta Name nino     # child
   ```
   The command and role names are Spanish: `alta` means "create", `nino` means "child".
7. Test first with a different `DB_PREFIJO` (table prefix, for example `prueba_`) so the real data is not touched.

Before updating the site, **back up the database and the code**.
