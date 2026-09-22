# STC Torneos — DNS, VPS y SSL (stctorneos.com)

**VPS:** `104.207.73.163` · `server1.stctorneos.com` · AlmaLinux 9  
**Dominio:** `stctorneos.com` (Namecheap)

---

## 1. DNS en Namecheap (Advanced DNS)

Hoy el dominio apunta a **Render** (`stc-torneos.onrender.com`). Hay que cambiarlo al VPS.

### Borrar (icono papelera)
| Type | Host | Value |
|------|------|-------|
| CNAME | @ | stc-torneos.onrender.com |
| CNAME | www | stc-torneos.onrender.com |

### Agregar (Add New Record)
| Type | Host | Value | TTL |
|------|------|-------|-----|
| **A Record** | `@` | `104.207.73.163` | Automatic |
| **A Record** | `www` | `104.207.73.163` | Automatic |

Guardar y esperar **5–30 minutos** (a veces hasta 2 h).

### Verificar desde tu PC
```powershell
nslookup stctorneos.com
nslookup www.stctorneos.com
```
Debe responder **104.207.73.163**.

---

## 2. Servidor VPS (ya preparado con script)

En el VPS se ejecuta una sola vez:
```bash
bash /tmp/vps-bootstrap.sh
```

Instala: Nginx, PHP 8.2, MariaDB, Composer, Certbot.  
Credenciales MySQL: `/root/stc-vps-credentials.txt`

---

## 3. Subir la aplicación

Desde Windows (PowerShell en `app1a`):
```powershell
cd SISTEMA-26-AGOSTO\stc-torneos-system
npm run build
cd ..\..
tar -czf $env:TEMP\stc-torneos.tgz --exclude=stc-torneos-system/vendor --exclude=stc-torneos-system/node_modules --exclude=stc-torneos-system/.env stc-torneos-system
# scp con SSH al VPS: tgz + scripts/stc-update.sh
ssh root@104.207.73.163 "bash /tmp/stc-update.sh"
```

---

## 4. SSL (HTTPS) con Let's Encrypt

**Solo cuando DNS ya apunte al VPS** (paso 1 verificado):

```bash
certbot --nginx -d stctorneos.com -d www.stctorneos.com \
  --non-interactive --agree-tos -m operacion@stctorneos.com --redirect
```

Renovación automática: `certbot renew --dry-run`

Resultado:
- https://stctorneos.com
- https://www.stctorneos.com (redirige a HTTPS)

---

## 5. Cambiar contraseña root (recomendado)

```bash
passwd root
```

Panel VPS: pestaña **Root/Admin Password**.

---

## Orden correcto

1. DNS Namecheap → IP del VPS  
2. Bootstrap en VPS (una vez)  
3. Subir app (`stc-update.sh`)  
4. Certbot SSL  
5. Probar login demo en producción  
