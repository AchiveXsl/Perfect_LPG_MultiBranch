# Deploying Perfect LPG on cPanel, Hostinger and other web hosting

This guide puts the app on a normal web hosting account: cPanel (Namecheap, Bluehost, A2, HostGator and most others), Hostinger hPanel, and similar panels such as Plesk and DirectAdmin.
For Railway see [railway-deployment.md](railway-deployment.md). For the Windows desktop installer see `installer/README.md`.

The app is a CodeIgniter 4 PHP application with a MySQL database. You need:

- the hosting control panel login, and
- this repository on your PC, with PHP and Composer (or the copies that `installer\build.ps1` downloads into `installer\.tools`).

---

## 1. Check the hosting plan

| Requirement | Value | Where to check |
|---|---|---|
| PHP | **8.2 or newer** (8.4 / 8.5 recommended; the desktop installer uses 8.5) | cPanel → *Select PHP Version* or *MultiPHP Manager*; Hostinger → *Advanced → PHP Configuration* |
| PHP extensions | `intl`, `mbstring`, `mysqli`, `zip`, `curl`, `openssl` (also `gd`, `fileinfo`, `sodium` if offered) | cPanel → *Select PHP Version → Extensions*; Hostinger → *PHP Configuration → PHP Extensions* |
| Database | MySQL 8.0 or newer (tested on 8.4). MariaDB 10.6+ usually works; run the checks in step 9 after installing | cPanel → *MySQL Databases*; Hostinger → *Databases → Management* |
| Apache `mod_rewrite` / `.htaccess` | Required (standard on cPanel and Hostinger) | — |
| SSL certificate | Required for a public site | cPanel → *SSL/TLS Status* (AutoSSL); Hostinger → *Security → SSL* |
| SSH access | Optional, but makes updates and migrations easier | cPanel → *Terminal* / *SSH Access*; Hostinger → *Advanced → SSH Access* (Premium plan and above) |

The app checks the PHP extensions when it starts. If one is missing it shows a page naming the extension instead of a blank error.

---

## 2. Build the upload package on your PC

The server needs the `vendor` folder, which is not in git. Build it once on your PC:

```powershell
cd C:\data\Perfect_LPG_MultiBranch
composer install --no-dev --optimize-autoloader
```

Then make a zip containing only what the server needs:

```powershell
$items = 'app','public','system','vendor','writable','database','spark','preload.php','composer.json','composer.lock','env'
Compress-Archive -Path $items -DestinationPath ..\perfect-lpg-upload.zip -Force
```

**Do not upload** `.git`, `node_modules`, `tests`, `installer`, `docs`, your local `.env` or `writable\logs\*.log`.
Your local `.env` points at your PC's database; the server gets its own `.env` in step 5.

---

## 3. Upload the files — choose a folder layout

Only the `public` folder may be reachable from the web. `app`, `system`, `vendor`, `writable`, `database` and `.env` must stay outside the web root, so nobody can download them.

### Layout A — app outside `public_html` (recommended)

Use this for a subdomain (e.g. `lpg.example.com`), an addon domain, or any domain whose document root you can change.

1. In *File Manager*, create a folder next to `public_html`, e.g. `/home/USERNAME/perfect-lpg`.
2. Upload `perfect-lpg-upload.zip` into it and choose *Extract*.
3. Point the domain at the app's `public` folder:
   - **cPanel:** *Domains* (or *Subdomains* / *Addon Domains*) → *Manage* → **Document Root** = `perfect-lpg/public`.
   - **Hostinger:** for a subdomain, *Domains → Subdomains* → set the custom folder to `perfect-lpg/public`. For the main domain, use Layout B (Hostinger fixes it to `public_html`).

Result:

```
/home/USERNAME/
├── perfect-lpg/
│   ├── app/  system/  vendor/  writable/  database/
│   ├── .env
│   └── public/        ← document root of the domain
└── public_html/       (untouched)
```

### Layout B — the domain must use `public_html` (Hostinger main domain, some cPanel plans)

1. Extract the zip into `/home/USERNAME/perfect-lpg` (outside `public_html`), as in Layout A.
2. Move **the contents** of `perfect-lpg/public/` (including the hidden `.htaccess`) into `public_html/`.
3. Edit `public_html/index.php` and change the line

   ```php
   require FCPATH . '../app/Config/Paths.php';
   ```

   to

   ```php
   require FCPATH . '../perfect-lpg/app/Config/Paths.php';
   ```

4. Remember: on every update, copy the new `public/` files into `public_html/` again and keep this edit.

### Layout C — everything inside `public_html` (avoid)

Only if the host gives no access outside `public_html`. Upload the project into `public_html/perfect-lpg/`, set `app.baseURL` to `https://example.com/perfect-lpg/public/`, and add this `.htaccess` in `public_html/perfect-lpg/` so only `public/` can be reached:

```apache
RewriteEngine On
RewriteRule ^(app|system|vendor|writable|database|tests)(/|$) - [F,L]
RewriteRule ^\.env - [F,L]
```

Then test that `https://example.com/perfect-lpg/.env` returns **403 Forbidden** before using the site.

---

## 4. Create the database

The database user on shared hosting usually cannot create databases, so create the database in the panel first. The setup step will then fill it.

**cPanel → MySQL® Databases** (or *MySQL Database Wizard*):

1. *Create New Database*, e.g. `lpg` → cPanel names it `USERNAME_lpg`.
2. *Add New User* with a strong password → e.g. `USERNAME_lpguser`.
3. *Add User To Database* → tick **ALL PRIVILEGES**.

**Hostinger → Databases → Management:** enter database name, user and password, then *Create*. Hostinger shows the full names (e.g. `u123456789_lpg`).

Use host **`localhost`** unless the panel shows a different MySQL host name.

---

## 5. Create the `.env` file on the server

In the app folder (`perfect-lpg/`, next to `app/`), copy `env` to **`.env`** and edit it. In File Manager, enable *Show Hidden Files* (dotfiles) to see it.

```ini
CI_ENVIRONMENT = production

app.baseURL = 'https://lpg.example.com/'
app.forceGlobalSecureRequests = true
app.companyName = 'Faizi Traders LPG'

database.default.hostname = localhost
database.default.database = USERNAME_lpg
database.default.username = USERNAME_lpguser
database.default.password = 'the-database-password'
database.default.DBDriver = MySQLi
database.default.port = 3306

# A long random secret. Keep it the same on every update.
encryption.key = hex2bin:PASTE_64_HEX_CHARACTERS_HERE

# Only true during the first installation (step 6).
FRESH_DATABASE_SETUP = false
```

- `app.baseURL` must be the exact address users type, with `https://` and the trailing `/`. A mismatch (http vs https, www vs no www) causes "The action you requested is not allowed" (CSRF) errors and missing CSS.
- To make an encryption key, run `php spark key:generate --show` on your PC (or in the hosting Terminal) and paste the result.
- Protect the file: *File Manager → .env → Permissions → 600* (or 640 if the site then cannot read it).

---

## 6. Install the database tables

Pick one method.

### Method 1 — the built-in installer (new, empty database)

1. Set `FRESH_DATABASE_SETUP = true` in `.env`.
2. Open the site. The login page redirects to **Installation Setup**.
3. Check the database name, type `INSTALL DATABASE` in the confirmation box and click **Run Installation Setup**. It runs `database/schema.sql` and then every numbered migration, including `044_20261119_multi_branch.sql`.
4. Sign in with **admin / admin123** and **change the password at once** (*Configuration → Users / Roles*).
5. Set `FRESH_DATABASE_SETUP = false` again.

### Method 2 — phpMyAdmin import

1. cPanel / Hostinger → **phpMyAdmin** → select the database → *Import* → choose `database/schema.sql` → *Go*.
2. Open the site. The login page lists pending migrations; click **Execute Pending Migrations**.
   With SSH you can instead run `php spark migrations:run` in the app folder.
3. Sign in with **admin / admin123** and change the password.

### Moving an existing shop (live data) to the hosting

1. On the current system: *Configuration → Database Backup & Restore → Create Database Backup*, and download the `.sql` file.
2. On the hosting: import that file with phpMyAdmin into the new, empty database (Method 2 step 1, using the backup instead of `schema.sql`). For files larger than the upload limit, use *Database Backup & Restore → Restore* in the app, or ask the host to import it.
3. Open the site and run the pending migrations from the login page. Migration 044 turns the existing branch into the **Head Office**; all old data stays with it.

---

## 7. Folder permissions

| Path | Permission |
|---|---|
| Folders | 755 |
| Files | 644 |
| `writable/` and everything inside (`cache`, `logs`, `session`, `uploads`, `debugbar`) | 755 folders / 644 files, owned by your account (the web server must be able to write here) |
| `.env` | 600 (or 640) |

Never use 777. If the site shows "Unable to write session" or "Cache unable to write", fix the owner/permissions of `writable/`.

---

## 8. PHP settings

**cPanel → Select PHP Version → Options** (or *MultiPHP INI Editor*), **Hostinger → Advanced → PHP Configuration → PHP Options**. These match the desktop installer:

| Setting | Value |
|---|---|
| `memory_limit` | 512M |
| `max_execution_time` | 300 |
| `upload_max_filesize` | 64M or more (backups and Excel imports) |
| `post_max_size` | a little above `upload_max_filesize` |
| `date.timezone` | `Asia/Karachi` |

---

## 9. Check the installation

1. `https://your-domain/.env` and `https://your-domain/app/` return **403 / 404**, never file contents.
2. Sign in as admin; the dashboard loads; *Configuration → Branches* shows the Head Office.
3. Create a test branch and test user and work through [multibranch_testcase.md](../multibranch_testcase.md) (at least MB-01 to MB-08 and one stock transfer).
4. Post one POS sale and one purchase, then void them (or use a test customer).
5. *Configuration → Database Backup & Restore → Create Database Backup* works and the file downloads.
6. On MariaDB, also check that Reports and Inventory Detail open without errors.

---

## 10. Backups, email and scheduled jobs

- **Backups:** the in-app backup uses `mysqldump` when the host allows it, and otherwise a built-in PHP export, so it works on shared hosting. Also turn on the host's own backups (cPanel *Backup* / JetBackup, Hostinger *Files → Backups*).
- **Backup email:** use an email account from the hosting (cPanel *Email Accounts*, Hostinger *Emails*). Enter its SMTP host, port 465 (SSL) or 587 (TLS), username and password in the app's backup email settings, then use *Test*.
- **Cron:** the app needs no cron job. To copy a local shop database to this online one on a schedule, run `php spark sync:online` from the local PC (*Online Database Sync* screen), not on the hosting.

---

## 11. Updating to a new version

1. Take a backup (*Create Database Backup* in the app, and in the hosting panel).
2. On your PC: `git pull`, `composer install --no-dev --optimize-autoloader`, build the zip (step 2).
3. Upload and extract over the old files. **Keep** the server's `.env` and `writable/` folder. Layout B: copy the new `public/` contents into `public_html/` again and redo the `index.php` edit.
4. Empty `writable/cache/` (leave its `index.html`).
5. Sign out and open the login page: run the pending migrations it lists (or `php spark migrations:run` over SSH).
6. Sign in and do a quick check (step 9, points 2 and 4).

### Optional: deploy with Git (cPanel *Git™ Version Control* / Hostinger *Advanced → Git*)

This works only if you can run Composer on the server (SSH). Clone the repository outside `public_html`, run `composer install --no-dev --optimize-autoloader` there, create `.env`, and point the document root at its `public/` folder (Layout A). To update, *Pull*, then run Composer and the migrations again.

---

## 12. Troubleshooting

| Symptom | Cause and fix |
|---|---|
| **500 Internal Server Error** | Read `writable/logs/log-YYYY-MM-DD.log` and the hosting *Errors* / `error_log`. For more detail, temporarily set `CI_ENVIRONMENT = development`, reload, then set it back. |
| Page names a missing PHP extension | Enable it in the PHP version selector (step 1). |
| Home page works, other pages give **404** | `.htaccess` was not uploaded (hidden file) or `mod_rewrite` is off; or the document root is not the `public` folder. |
| Pages without styling, or links go to the wrong address | Wrong `app.baseURL` (must match the address in the browser, ending in `/`). |
| "The action you requested is not allowed" | CSRF / cookie mismatch: http vs https or www vs no-www. Fix `app.baseURL`, force HTTPS, and always use the same address. |
| "Unable to connect to the database" | Wrong full database or user name (with the `USERNAME_` / `u123_` prefix), password, or host. On most hosts the host is `localhost`. |
| Login says migrations are pending / blocked | Run them from the login page. If one fails, the message names the file. Fix it (or restore the backup) before using the site. |
| "You switched to branch … in another tab" | Expected: the page was opened under a different branch. Reload the page. |
| Sessions lost / logged out at random | `writable/session` is not writable, or several domain names point to the same site. |
| Large backup or Excel import fails | Raise `upload_max_filesize`, `post_max_size` and `max_execution_time` (step 8). |

---

## Other platforms

- **Plesk:** *Websites & Domains → Hosting Settings → Document root* = `perfect-lpg/public`; PHP version and extensions under *PHP Settings*; database under *Databases*. Otherwise follow this guide.
- **DirectAdmin:** create the database under *MySQL Management*; for the document root use a subdomain pointed at `perfect-lpg/public`, or Layout B.
- **VPS (Ubuntu + Apache/Nginx):** install PHP 8.4+, the extensions above and MySQL 8, put the app in `/var/www/perfect-lpg`, point the virtual host at `/var/www/perfect-lpg/public`. For Nginx, use `try_files $uri $uri/ /index.php?$args;`. Make `writable/` writable by the web server user (`chown -R www-data writable`).
- **Railway:** see [railway-deployment.md](railway-deployment.md).
