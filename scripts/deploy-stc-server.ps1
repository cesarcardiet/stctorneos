# Despliega stc-torneos-system al VPS stctorneos.com
# Requiere: SSH configurado hacia root@104.207.73.163

$ErrorActionPreference = "Stop"
$Root = Split-Path -Parent $PSScriptRoot
$Backend = Join-Path $Root "stc-torneos-system"
$Archive = Join-Path $env:TEMP "stc-torneos.tgz"
$Remote = "root@104.207.73.163"

Write-Host "Empaquetando backend..."
if (Test-Path $Archive) { Remove-Item $Archive -Force }
tar -czf $Archive -C (Split-Path $Backend -Parent) "stc-torneos-system"

Write-Host "Subiendo a $Remote ..."
scp $Archive "${Remote}:/tmp/stc-torneos.tgz"
scp (Join-Path $Backend "scripts/stc-update.sh") "${Remote}:/tmp/stc-update.sh"

Write-Host "Ejecutando deploy remoto..."
ssh $Remote "chmod +x /tmp/stc-update.sh && bash /tmp/stc-update.sh"

Write-Host "Verificando API..."
curl -s "https://stctorneos.com/api/v1/home" | Select-Object -First 1
Write-Host "`nDEPLOY COMPLETADO. Probar: https://stctorneos.com/api/v1/home"
