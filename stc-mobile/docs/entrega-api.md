# Entrega de API — app STC Torneos

**Para:** equipo de la app  
**Fecha:** 13 de noviembre de 2026  
**API en producción:** `https://stctorneos.com/api/v1`  
**Web del mismo sistema:** `https://stctorneos.com`  
**Código de referencia de la app (adelanto Flutter):** carpeta `stc-mobile` de este repositorio

La app muestra las pantallas de Figma. El servidor guarda los datos, los permisos y las reglas. La app no inventa un menú: después del login usa el arreglo `user.navigation` que devuelve `/me`.

## Cómo llamar la API

Todas las respuestas usan este formato.

Éxito:

```json
{ "ok": true, "message": "opcional", "data": {} }
```

Error:

```json
{ "ok": false, "message": "texto para mostrar", "errors": {} }
```

- `Content-Type: application/json`
- `Accept: application/json`
- Las rutas con sesión llevan `Authorization: Bearer {token}`
- El token sale de `POST /auth/login` o de `POST /auth/invitations/accept`
- `401` = token inválido o ausente
- `403` = el rol no puede entrar a esa sección
- `422` = dato inválido; mostrar `message`

## Cuentas de prueba

Clave de staff y consulta: `stcdemo`

| Rol que debe verse | Correo | Qué abre la app |
|---|---|---|
| Consulta | `consulta@stctorneos.demo` | Inicio, favoritos, notificaciones |
| Delegado | `delegado@stctorneos.demo` | Mi delegación, equipos, lista, fichas, jugador nuevo |
| Mesa | `mesa@stctorneos.demo` | Partidos de hoy, en vivo, planillas |
| Admin torneo | `torneo@stctorneos.demo` | Partidos staff. La administración completa sigue en la web |
| Super admin | `admin@stctorneos.demo` | Igual que admin torneo en la app |

Estas dos cuentas existen, pero hoy el servidor las tiene como **asistente de árbitro**, no como árbitro ni coordinador:

- `arbitro@stctorneos.demo`
- `coordinador@stctorneos.demo`

Tutor y jugador se crean al completar una ficha. Clave inicial del tutor: `stctutor`. Clave inicial del jugador: `stcjugador`. El correo del jugador en la ficha es opcional: si va vacío, la ficha se envía igual y no se crea acceso de jugador.

El 13 de noviembre de 2026 se probó el login de las cuentas de la tabla contra producción y respondieron bien.

## Menú según el rol

Cada ítem de `user.navigation` trae `key`, `label`, `enabled` y `route`.

| key | Pantalla | Ruta de la app |
|---|---|---|
| `home` | Inicio | `/home` |
| `favorites` | Favoritos | `/favorites` |
| `notifications` | Notificaciones | `/notifications` |
| `player_portal` | Mi ficha | `/player/portal` |
| `tutor_portal` | Mis jugadores | `/tutor/players` |
| `tutor_ficha` | Completar ficha | `/tutor/players/{id}/ficha` |
| `workspace` | Mi delegación | `/workspace` |
| `delegation_teams` | Mis equipos | `/workspace/teams` |
| `delegation_roster` | Lista de buena fe | `/workspace/roster` |
| `delegation_inscriptions` | Seguimiento de fichas | `/workspace/inscriptions` |
| `new_player` | Nuevo jugador | `/players/new` |
| `staff_matches` | Partidos | `/staff/matches` |
| `staff_live` | En vivo | `/staff/matches?scope=live` |
| `staff_sheet` | Planillas | `/staff/matches?scope=mine` |
| `admin_web` | Administración web | no se arma en la app (`enabled: false`) |

## Entrada

### `POST /auth/login`

```json
{ "email": "delegado@stctorneos.demo", "password": "stcdemo", "device_name": "stc-mobile" }
```

`data` trae `token`, `token_type` (`Bearer`) y `user`.

Si `user.status` no es `active`, responde `403` con un texto (`pendiente`, `suspendido` o `revocado`).

### `GET /me`

Devuelve el mismo objeto `user` del login. Llamarlo al abrir la app si ya hay token.

### `PATCH /me`

Campos opcionales: `name`, `phone`.

### `POST /auth/logout`

Borra el token actual.

### `POST /auth/register/spectator`

Registro público de quien solo mira el torneo.

```json
{ "name": "Ana Pérez", "email": "ana@email.com", "password": "minimo8", "password_confirmation": "minimo8", "device_name": "stc-mobile" }
```

Responde `201` con token y user.

### `POST /auth/invitations/preview`

```json
{ "email": "persona@email.com", "invitation_code": "codigo" }
```

Sirve para staff, tutor y jugador antes de crear la clave.

### `POST /auth/invitations/accept`

```json
{
  "name": "opcional",
  "email": "persona@email.com",
  "invitation_code": "codigo",
  "password": "minimo8",
  "password_confirmation": "minimo8",
  "device_name": "stc-mobile"
}
```

Responde `201` con token y user.

### `POST /auth/forgot-password`

```json
{ "email": "persona@email.com" }
```

### `POST /auth/reset-password`

```json
{ "email": "persona@email.com", "token": "token-del-correo", "password": "minimo8", "password_confirmation": "minimo8" }
```

## Público (sin token)

| Método | Ruta | Uso |
|---|---|---|
| GET | `/home` | Portada: torneo, partidos en vivo, próximos, noticias |
| GET | `/tournaments` | Listado de torneos |
| GET | `/tournaments/{id}` | Torneo y categorías |
| GET | `/categories/{id}` | Categoría |
| GET | `/matches?tournament_id=` | Partidos |
| GET | `/matches/{id}` | Partido |
| GET | `/teams/{id}` | Equipo |
| GET | `/players/{id}` | Jugador |
| GET | `/categories/{id}/standings` | Tabla |
| GET | `/categories/{id}/rankings` | Goleadores y rankings |
| GET | `/categories/{id}/fair-play` | Fair play |
| GET | `/categories/{id}/brackets` | Llaves |
| GET | `/content` | Noticias y placas |
| GET | `/content/{slug}` | Una noticia |
| GET | `/live/sync?since=&tournament_id=` | Marcador en vivo. Consultar cada pocos segundos, no en bucle cerrado |

## Con sesión, cualquier rol

| Método | Ruta | Uso |
|---|---|---|
| GET | `/notifications` | Avisos |
| GET | `/notifications/sync` | Avisos nuevos y cantidad sin leer |
| POST | `/notifications/{id}/read` | Marcar leído |
| GET | `/favorites` | Favoritos |
| POST | `/favorites/teams/{id}` | Marcar o sacar equipo |
| POST | `/favorites/matches/{id}` | Marcar o sacar partido |

## Delegado

| Método | Ruta | Uso |
|---|---|---|
| GET | `/workspace` | Su club |
| GET | `/workspace/teams/{id}` | Un equipo |
| GET | `/workspace/teams/{id}/players` | Plantel |
| GET | `/workspace/inscriptions` | Estado de las fichas |
| GET | `/roster/context` | Datos para el alta de jugador |
| GET | `/categories/{category}/players/{player}/roster` | Ficha corta del jugador |
| POST | `/categories/{category}/players` | Alta de jugador |
| PATCH | `/categories/{category}/players/{player}` | Editar jugador |
| POST | `/categories/{category}/players/{player}/documents` | Subir documento (`multipart`) |
| POST | `/categories/{category}/players/{player}/invite-guardian` | Invitar al tutor |

## Tutor

| Método | Ruta | Uso |
|---|---|---|
| GET | `/tutor/players` | Jugadores a cargo |
| GET | `/tutor/players/{id}` | Un jugador |
| GET | `/tutor/players/{id}/ficha` | Formulario de la ficha, textos legales y documentos |
| POST | `/tutor/players/{id}/ficha` | Enviar la ficha |

El envío lleva `complete=1`, los datos del jugador y del tutor, las tres autorizaciones (`auth[]`: `Autorización`, `Uso de imagen`, `Apto médico`), `consent=1`, fotos de DNI y foto del jugador. La foto del jugador puede ir recortada en `photo_data` (data URL JPEG). El campo `player_email` es opcional.

Si la ficha ya fue enviada, `locked` viene en `true` y un nuevo envío responde `422`.

## Jugador

| Método | Ruta | Uso |
|---|---|---|
| GET | `/player/portal` | Su ficha, credencial y torneo |

## Mesa, árbitro y admin

| Método | Ruta | Uso |
|---|---|---|
| GET | `/staff/matches?scope=today` | Partidos del día. También `live`, `mine`, `all` |
| GET | `/staff/matches/{id}` | Planilla, jugadores y eventos |
| POST | `/staff/matches/{id}/events` | Gol, asistencia o tarjeta |
| POST | `/staff/matches/{id}/report` | Informe del partido |

Evento:

```json
{ "type": "goal", "team_id": 1, "player_id": 10, "minute": 23 }
```

`type` acepta `goal`, `assist`, `yellow`, `red`, `substitution`. Hace falta jugador y equipo. Si la planilla está cerrada, responde `403`.

## Qué arma cada lado

| App (Figma) | Servidor |
|---|---|
| Pantallas, navegación visual, recorte de foto en el teléfono | Login, roles, permisos, fichas, tablas, partidos |
| Mostrar `message` cuando `ok` es `false` | Validar y guardar |
| Usar `navigation` para los accesos del rol | Decidir qué puede ver cada cuenta |

La administración de torneos, categorías, fixture y configuración queda en la web. En la app ese acceso llega apagado (`admin_web`, `enabled: false`).

## Adelanto que ya existe

En `stc-mobile` hay un Flutter de referencia contra esta misma API: login, inicio, torneos, delegado, tutor, jugador y staff. Sirve para ver el flujo. Las vistas finales son las de Figma.

Base usada por esa app: `https://stctorneos.com/api/v1`.
