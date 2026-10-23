$ErrorActionPreference = 'Stop'
$localUrl = 'http://127.0.0.1:8000'
$envFile = Join-Path $PSScriptRoot '.env'
$configCache = Join-Path $PSScriptRoot 'bootstrap\cache\config.php'
$viteHotFile = Join-Path $PSScriptRoot 'public\hot'

$cloudflared = Get-Command cloudflared -ErrorAction SilentlyContinue
if ($null -eq $cloudflared) {
    $installedPath = 'C:\Program Files (x86)\cloudflared\cloudflared.exe'
    if (Test-Path $installedPath) {
        $cloudflaredPath = $installedPath
    }
    else {
        Write-Error 'cloudflared belum terpasang. Jalankan: winget install --id Cloudflare.cloudflared -e, lalu buka PowerShell baru.'
        exit 1
    }
}
else {
    $cloudflaredPath = $cloudflared.Source
}

if (-not (Test-Path $envFile)) {
    Write-Error 'File .env tidak ditemukan. Siapkan environment aplikasi terlebih dahulu.'
    exit 1
}

$debugSetting = Get-Content $envFile | Where-Object { $_ -match '^\s*APP_DEBUG\s*=' } | Select-Object -Last 1
if ($debugSetting -notmatch '^\s*APP_DEBUG\s*=\s*["'']?false["'']?\s*(#.*)?$') {
    Write-Error 'Untuk keamanan, ubah APP_DEBUG=false di .env sebelum membuka tunnel.'
    exit 1
}

if (Test-Path $configCache) {
    Write-Error 'Konfigurasi Laravel masih di-cache. Jalankan php artisan config:clear, lalu restart server Laravel.'
    exit 1
}

if (Test-Path $viteHotFile) {
    Write-Error 'Vite masih berjalan dalam mode development. Hentikan npm run dev, jalankan npm run build, lalu coba lagi.'
    exit 1
}

try {
    Invoke-WebRequest -Uri $localUrl -UseBasicParsing -TimeoutSec 5 | Out-Null
}
catch {
    Write-Error 'Server Laravel tidak merespons di http://127.0.0.1:8000. Jalankan php artisan serve terlebih dahulu.'
    exit 1
}

Write-Host 'Membuka tunnel sementara. Bagikan URL https://...trycloudflare.com yang muncul di bawah.'
Write-Host 'Tekan Ctrl+C untuk menutup tunnel.'
& $cloudflaredPath tunnel --url $localUrl

if ($LASTEXITCODE -ne 0) {
    Write-Error "cloudflared berhenti dengan kode $LASTEXITCODE."
    exit $LASTEXITCODE
}
