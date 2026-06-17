# Configuración de API Keys para Pagos Reales

## Stripe

1. Crea una cuenta en [Stripe](https://dashboard.stripe.com)
2. Ve a "Developers" > "API Keys"
3. Copia tu clave pública (Public Key) y clave secreta (Secret Key)
4. Agrega a `.env`:
```
STRIPE_SECRET_KEY=sk_live_XXXXXXXXXXXX
```

## PayPal

1. Crea una cuenta de negocio en [PayPal Developer](https://developer.paypal.com)
2. Ve a "Dashboard" > "Apps & Credentials"
3. Copia tu Client ID
4. Agrega a `.env`:
```
PAYPAL_CLIENT_ID=YOUR_CLIENT_ID
PAYPAL_SECRET=YOUR_SECRET
```

## Mercado Pago

1. Crea una cuenta en [Mercado Pago](https://www.mercadopago.com.ar)
2. Ve a "Configuración" > "Credenciales"
3. Copia tu Access Token
4. Agrega a `.env`:
```
MERCADOPAGO_ACCESS_TOKEN=APP_USR_XXXXXXXXXXXX
```

## Archivo .env (Backend)

Crea un archivo `.env` en la carpeta `backend/` con tus credenciales.

El backend ignorará automáticamente estos valores si están vacíos y usará modo demo.

## Frontend

El frontend ya no necesita claves hardcodeadas para Stripe en `index.html`.

- Stripe: la redirección sale desde el backend al crear la Checkout Session.
- PayPal y Mercado Pago siguen pendientes de integración real si decides activarlos.
