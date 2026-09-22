# STC Torneos — Sistema base de trabajo

**Carpeta oficial:** `SISTEMA-26-AGOSTO/stc-torneos-system`  
**Respaldo origen:** `respaldos/stc-torneos-tal-cual-2026-08-26-1738.zip`  
**Local:** http://127.0.0.1:8002/login  
**Login demo:** `admin@stctorneos.demo` / `stcdemo`

## Plan acordado

1. **Ahora:** enriquecer datos demo y verificar módulos (Admin, operación, documentación, fixture).
2. **Después:** retoques de diseño sobre esta base (sin cambiar flujos de golpe).
3. **Luego:** subir al VPS de hosting cuando la demo local esté validada.

## Comandos útiles

```powershell
cd SISTEMA-26-AGOSTO\stc-torneos-system

# Servidor local
php artisan serve --host=127.0.0.1 --port=8002

# Recargar demo completa (MySQL local stc_torneos_system)
php artisan migrate:fresh --seed

# Solo enriquecer datos de presentación
php artisan db:seed --class=DemoPresentationSeeder

# Tests
php artisan test
```

## Qué incluye la demo enriquecida

- 2+ torneos (Santa Teresita Cup + Copa Invierno STC + Buenos Aires)
- 15 categorías con equipos y planteles (~12 jugadores por equipo)
- Partidos en vivo, programados y finalizados
- Bandeja documental con casos pendientes/observados (Mateo, Lucas, Thiago)
- Comunicaciones, notificaciones y auditoría

## VPS (pendiente)

No subir hasta confirmar local. Paquete: `stc-torneos.tgz` + `scripts/stc-update.sh` en el servidor `66.94.102.53`.
