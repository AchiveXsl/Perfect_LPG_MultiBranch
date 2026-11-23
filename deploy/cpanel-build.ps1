# Builds a cPanel upload zip (Layout B: app in ~/perfect-lpg, web files in ~/public_html).
# Run through CpanelBuild.bat. Output: dist\perfect-lpg-cpanel-<yyyyMMdd-HHmm>.zip
$ErrorActionPreference = 'Stop'
$root  = Split-Path -Parent $PSScriptRoot
$stamp = Get-Date -Format 'yyyyMMdd-HHmm'
$work  = Join-Path $env:TEMP "lpg-cpanel-$stamp"
$app   = Join-Path $work 'perfect-lpg'
$web   = Join-Path $work 'public_html'
$dist  = Join-Path $root 'dist'
$zip   = Join-Path $dist "perfect-lpg-cpanel-$stamp.zip"

function Step($m) { Write-Host "`n== $m" -ForegroundColor Cyan }
function Copy-Dir($from, $to) {
    # robocopy exit codes below 8 mean success
    robocopy $from $to /E /NFL /NDL /NJH /NJS /NP /XD .git node_modules | Out-Null
    if ($LASTEXITCODE -ge 8) { throw "Copy failed: $from" }
}

Step 'Preparing work folder'
New-Item -ItemType Directory -Force $app, $web, $dist | Out-Null

Step 'Copying application files'
foreach ($d in 'app', 'system', 'database') { Copy-Dir (Join-Path $root $d) (Join-Path $app $d) }
foreach ($f in 'spark', 'preload.php', 'composer.json', 'composer.lock', 'env') {
    if (Test-Path (Join-Path $root $f)) { Copy-Item (Join-Path $root $f) $app }
}

# writable: folders and protection files only, so extracting never overwrites server logs, sessions or uploads
foreach ($f in Get-ChildItem (Join-Path $root 'writable') -Recurse -File -Include 'index.html', '.htaccess' -Force) {
    $dest = Join-Path $app ($f.FullName.Substring($root.Length + 1))
    New-Item -ItemType Directory -Force (Split-Path $dest) | Out-Null
    Copy-Item $f.FullName $dest
}

# .env template (the real .env is never packaged)
@"
CI_ENVIRONMENT = production
app.baseURL = 'https://YOUR-DOMAIN/'

database.default.hostname = localhost
database.default.database = CPANELUSER_lpg
database.default.username = CPANELUSER_lpguser
database.default.password = CHANGE_ME
database.default.DBDriver = MySQLi
database.default.port = 3306

encryption.key =
"@ | Set-Content -Encoding ascii (Join-Path $app '.env.example')

Step 'Installing production vendor (composer --no-dev)'
$composer = Get-Command composer -ErrorAction SilentlyContinue
if ($composer) {
    Push-Location $app
    try {
        & composer install --no-dev --optimize-autoloader --no-interaction --no-progress --no-scripts
        if ($LASTEXITCODE -ne 0) { throw 'composer install failed' }
    } finally { Pop-Location }
} elseif (Test-Path (Join-Path $root 'vendor')) {
    Write-Warning 'Composer not found on PATH: copying the local vendor folder (may include dev packages).'
    Copy-Dir (Join-Path $root 'vendor') (Join-Path $app 'vendor')
} else { throw 'Composer not found and no vendor folder exists. Install Composer (and PHP) first.' }

Step 'Preparing public_html'
Copy-Dir (Join-Path $root 'public') $web
$index = Join-Path $web 'index.php'
$text  = [IO.File]::ReadAllText($index)
$new   = $text.Replace("FCPATH . '../app/Config/Paths.php'", "FCPATH . '../perfect-lpg/app/Config/Paths.php'")
if ($new -eq $text) { throw 'Could not point public_html/index.php at ../perfect-lpg (line not found).' }
[IO.File]::WriteAllText($index, $new)

@"
PERFECT LPG - cPanel PACKAGE ($stamp)
=====================================
perfect-lpg/  -> extract to /home/CPANELUSER/perfect-lpg   (outside public_html)
public_html/  -> extract to /home/CPANELUSER/public_html   (index.php already points to ../perfect-lpg)

FIRST INSTALL
 1. Upload this zip to /home/CPANELUSER and Extract (File Manager, Show Hidden Files ON).
 2. PHP 8.2+ with intl, mbstring, mysqli, zip, curl, openssl.
 3. Create the MySQL database and user (ALL PRIVILEGES).
 4. Rename perfect-lpg/.env.example to .env and fill in domain + database.
 5. Permissions: folders 755, files 644 (never 777).
 6. Open https://YOUR-DOMAIN/installation

UPDATE
 1. Upload and Extract over the old files (your .env, logs, sessions and uploads are not in the zip and stay).
 2. Log in as admin > Installation page > Execute Pending Migrations (if any).
 3. Refresh the browser (Ctrl+F5).
See docs/cpanel-deployment.md for details.
"@ | Set-Content -Encoding ascii (Join-Path $work 'README-DEPLOY.txt')

Step 'Creating zip'
if (Test-Path $zip) { Remove-Item $zip -Force }
# tar.exe writes forward-slash paths, which cPanel extracts correctly
& tar.exe -a -c -f $zip -C $work perfect-lpg public_html README-DEPLOY.txt
if ($LASTEXITCODE -ne 0) { throw 'tar failed' }
Remove-Item $work -Recurse -Force

$mb = [math]::Round((Get-Item $zip).Length / 1MB, 1)
Write-Host "`nDone: $zip ($mb MB)" -ForegroundColor Green
