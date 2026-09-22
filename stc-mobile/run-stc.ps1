# STC Torneos — correr la app correcta (NO service_desk_demo)
Set-Location $PSScriptRoot
Write-Host "Proyecto: stc_mobile (STC Torneos)" -ForegroundColor Cyan
Write-Host "Carpeta:  $PSScriptRoot" -ForegroundColor Cyan
flutter pub get
flutter devices
flutter run -d R5CY34F6BVV
