# STC Torneos

Sistema de gestión de torneos deportivos (Laravel) + app móvil.

**Producción:** https://stctorneos.com  
**Repo:** https://github.com/cesarcardiet/stctorneos

## Estructura

| Carpeta | Qué es |
|---------|--------|
| `stc-torneos-system/` | Web Operación + Admin (Laravel) — **única fuente de deploy** |
| `stc-mobile/` | App móvil Flutter |
| `docs-stc/` | Documentación del sistema |
| `scripts/` | Scripts auxiliares |

## Requisitos

- PHP 8.2+, Composer, Node.js, MySQL
- Para móvil: Flutter SDK

## Desarrollo local (web)

```powershell
cd stc-torneos-system
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
npm install
npm run build
php artisan serve --host=127.0.0.1 --port=8002
```

Demo: `admin@stctorneos.demo` / `stcdemo`

## Deploy a VPS

Empaquetar **solo** desde esta carpeta (`SISTEMA-26-AGOSTO` en el workspace local) y usar:

`stc-torneos-system/scripts/stc-update.sh`

No desplegar copias viejas ni carpetas `_clausuradas`.

## Importante

Cualquier otra carpeta `stc-torneos-system` fuera de aquí es **clausurada** y no debe usarse para producción ni estilos.
