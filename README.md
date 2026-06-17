# NotasFlow

Proyecto escolar de notas con frontend en React y backend API en PHP.

## Stack

- Frontend: React + Vite
- Backend: PHP con API REST sencilla
- Base de datos: SQLite con PDO
- Pagos: **Stripe, PayPal y Mercado Pago** integrados con APIs reales (modo demo por defecto)

## Estructura

- `frontend/`: interfaz en React
- `backend/public/index.php`: punto de entrada de la API
- `backend/src/bootstrap.php`: logica, validaciones y acceso a SQLite
- `backend/data/`: base de datos local
- `docs/`: manual de usuario y notas para la entrega

## Como correr el proyecto

### 1. Backend

```bash
cd backend
php -S 127.0.0.1:8000 router.php
```

El backend queda en `http://127.0.0.1:8000`.

Prueba rapida:

```bash
http://127.0.0.1:8000/api/health
```

### 2. Frontend

```bash
cd frontend
npm install
npm run dev
```

El frontend queda en `http://127.0.0.1:5173`.

## Despliegue en Render

Este proyecto se despliega mejor en dos servicios:

- `frontend/` como `Static Site`
- `backend/` como `Web Service` con Docker

### Por que asi

Render no ofrece PHP dentro de sus runtimes nativos. Sus runtimes nativos incluyen Node.js/Bun, Python, Ruby, Go, Rust y Elixir, asi que para este backend PHP conviene usar Docker. Render tambien soporta `Static Sites` para frontends como React y `Persistent Disks` para conservar archivos entre deploys. En este proyecto eso importa porque la base SQLite vive en `backend/data/notes.sqlite`.

### Archivos incluidos

- `backend/Dockerfile`: imagen para correr la API PHP en Render
- `render.yaml`: blueprint base para frontend y backend

### Opcion 1. Desde el Dashboard de Render

#### Backend

1. Crea un `Web Service`
2. Conecta tu repositorio
3. Elige `Docker` como runtime
4. Usa este `Dockerfile Path`:

```text
backend/Dockerfile
```

5. Agrega estas variables de entorno:

```text
FRONTEND_ORIGIN=https://TU-FRONTEND.onrender.com
STRIPE_SECRET_KEY=...
PAYPAL_CLIENT_ID=...
PAYPAL_SECRET=...
PAYPAL_BASE_URL=https://api-m.sandbox.paypal.com
PAYPAL_CURRENCY=USD
MERCADOPAGO_ACCESS_TOKEN=...
MERCADOPAGO_BASE_URL=https://api.mercadopago.com
MERCADOPAGO_CURRENCY=MXN
```

6. Agrega un `Persistent Disk`
7. Usa este `mount path`:

```text
/app/backend/data
```

#### Frontend

1. Crea un `Static Site`
2. Usa `frontend` como `Root Directory`
3. Usa este `Build Command`:

```bash
npm install && npm run build
```

4. Usa este `Publish Directory`:

```text
dist
```

5. Agrega esta variable:

```text
VITE_API_URL=https://TU-BACKEND.onrender.com/api
```

### Opcion 2. Con Blueprint

Tambien puedes usar el archivo `render.yaml` incluido y luego ajustar los nombres de dominio generados por Render.

### Importante

- Si subes esto a un repo, no subas `backend/.env`
- Rota las claves reales de Stripe, PayPal y Mercado Pago que ya expusiste durante las pruebas
- Si usas SQLite en Render, necesitas el `Persistent Disk`; sin eso la base se pierde en reinicios o deploys

## Funcionalidades incluidas

- Crear, editar, listar y eliminar notas
- Marcar notas como fijadas
- Organizar notas con etiquetas
- Mostrar metricas basicas del sistema
- Visualizar metodos de pago requeridos
- Crear checkouts demo para Stripe, PayPal y Mercado Pago

## Nota importante

El backend viejo en Node fue retirado para que la entrega quede consistente con el requisito de `API con PHP`.

## Sobre los pagos

La aplicación ya tiene **integradas las APIs reales** de:
- **Stripe**: Pagos con tarjeta
- **PayPal**: Pagos con cuenta PayPal
- **Mercado Pago**: Pagos en LATAM

Funciona en **modo demo** cuando no hay credenciales configuradas (perfecto para demostración). 

Para activar pagos reales, agrega las credenciales en `backend/.env`. Ver [INTEGRACION_PAGOS.md](INTEGRACION_PAGOS.md) para detalles completos.

## Entregables pendientes

- URL publica funcional
- Ajustes visuales finales
- Capturas o video corto para la exposicion
- Reforzar el manual si tu profesor pide mas detalle
