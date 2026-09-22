# STC Mobile

App Flutter Android-first para **STC Torneos**. Consume la API en producción:

**`https://stctorneos.com/api/v1`**

## Ubicación del proyecto

```
SISTEMA-26-AGOSTO/
├── stc-torneos-system/   ← Backend Laravel (API + admin web)
└── stc-mobile/           ← App móvil Flutter (ESTE proyecto)
```

## Matriz de prueba por rol (demo local / VPS con seed)

Clave común demo: **`stcdemo`**

| Rol | Email | Qué probar en la app |
|-----|-------|----------------------|
| Espectador / Consulta | Registro público o `consulta@stctorneos.demo` | Home, torneos, en vivo, favoritos |
| Delegado | `delegado@stctorneos.demo` | Mi delegación, equipos, roster, inscripciones, nuevo jugador |
| Tutor (ficha pendiente) | `ficha.tutor@stc.test` / `stctutor` | Mis jugadores, wizard ficha |
| Jugador | `joaquin.jugador@stc.test` / `stcjugador` | Mi ficha, credencial |
| Árbitro | `arbitro@stctorneos.demo` | Partidos staff, planilla, eventos, informe |
| Mesa | `mesa@stctorneos.demo` | Partidos staff (mismo módulo) |
| Coordinador | `coordinador@stctorneos.demo` | Partidos staff |
| Admin torneo | `torneo@stctorneos.demo` | Catálogo + Partidos staff |

Seed tutor/jugador ficha:

```powershell
cd stc-torneos-system
php artisan db:seed --class=FichaPlayerAccessDemoSeeder
```

## Invitaciones en la app

| Tipo | Ruta | Estado |
|------|------|--------|
| Staff (delegado, mesa, árbitro…) | `/register/staff` | ✅ |
| Familia / tutor (`kind=guardian`) | `/register/invitation` | ✅ |
| Jugador (`kind=player`) | `/register/invitation` | ✅ |

## Secciones implementadas (Figma ~390×844)

| Sección | Estado |
|---------|--------|
| 1 — Auth, registro, perfiles | ✅ |
| 6 — Nuevo jugador | ✅ |
| 7 — Delegado | ✅ |
| 8 — Tutor / ficha | ✅ |
| 10 — Público / torneo | ✅ |
| 11 — Placas | ✅ |
| 12–13 — Staff | ✅ MVP |
| 14 — Estados vacíos | ✅ |

## Rutas principales

Ver `lib/router.dart`. Destacadas:

- `/home` — accesos rápidos por rol desde `/me.navigation`
- `/staff/matches` — staff operativo
- `/content/plaques` — placas oficiales
- `/reset-password?email=&token=` — completar recuperación de clave

## Desarrollo

```powershell
cd stc-mobile
flutter run
flutter test
```

## Deploy backend (VPS)

```powershell
cd SISTEMA-26-AGOSTO
.\scripts\deploy-stc-server.ps1
```

## Pendiente post-MVP (no bloquea pruebas)

- PDF credencial nativo en dispositivo
- Push notifications (FCM)
- Cierre de planilla staff desde móvil
- Deep links `stc://`
