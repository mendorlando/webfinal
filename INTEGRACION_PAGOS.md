# Integración de Pagos Reales - NotasFlow

## Estado Actual

La aplicación está configurada para procesar pagos reales a través de:
- **Stripe**: Pagos con tarjeta de crédito
- **PayPal**: Pagos con cuenta PayPal
- **Mercado Pago**: Pagos populares en LATAM

Actualmente funciona en **modo demo** cuando no están configuradas las credenciales.

## Instrucciones de Integración

### 1. Configurar Backend

Copia el archivo `.env.example` a `.env`:

```bash
cd backend
cp .env.example .env
```

Edita `.env` y agrega tus credenciales reales de cada proveedor.

### 2. Instalar dependencias necesarias

Cada proveedor requiere librerías específicas. Instálalas con Composer:

```bash
cd backend
composer require stripe/stripe-php paypalrestsdk/paypalrestsdk mercadopago/sdk
```

### 3. Configurar Frontend

En [frontend/index.html](../frontend/index.html), reemplaza:

```html
<!-- Stripe -->
<script src="https://js.stripe.com/v3/"></script>

<!-- PayPal -->
<script src="https://www.paypal.com/sdk/js?client-id=YOUR_PAYPAL_CLIENT_ID&currency=USD"></script>

<!-- Mercado Pago -->
<script src="https://secure.mlstatic.com/sdk/javascript/v1/mercadopago.js"></script>
```

Con tus claves públicas reales.

También en [frontend/src/App.jsx](../frontend/src/App.jsx), reemplaza:
- Línea ~290: `APP_USR_YOUR_MERCADO_PAGO_PUBLIC_KEY` con tu clave de Mercado Pago

### 4. Flujos de Pago

#### Stripe
1. El usuario selecciona "Stripe"
2. Completa el formulario
3. Se redirige a Stripe Hosted Checkout
4. El pago se procesa en los servidores de Stripe

#### PayPal
1. El usuario selecciona "PayPal"
2. Aparecen botones de PayPal
3. Autoriza el pago en PayPal
4. Vuelve a tu aplicación

#### Mercado Pago
1. El usuario selecciona "Mercado Pago"
2. Se redirige a Mercado Pago Checkout
3. El usuario paga y vuelve a tu app

## Prueba Local (Sin Credenciales Reales)

Si no tienes credenciales configuradas, la aplicación:
- Genera IDs de transacción ficticios
- Registra los intentos en la base de datos
- Muestra mensajes en modo demo

Esto es perfecto para demostración y desarrollo.

## Archivos Modificados

- [backend/src/bootstrap.php](../backend/src/bootstrap.php): Funciones de pago para cada proveedor
- [frontend/src/App.jsx](../frontend/src/App.jsx): Manejadores de pago integrados
- [frontend/index.html](../frontend/index.html): Scripts de SDK de proveedores
- [backend/.env.example](../backend/.env.example): Variables de configuración

## Próximos Pasos

1. Crear cuentas en los proveedores
2. Obtener credenciales de prueba (sandbox)
3. Configurar `.env` en el backend
4. Instalar dependencias con Composer
5. Actualizar claves públicas en el frontend
6. Probar flujos de pago en ambiente de sandbox
7. Migrara producción cuando esté listo
