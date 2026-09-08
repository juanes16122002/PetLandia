# PetLandia 🐾

E-commerce para productos de mascotas desarrollado con **PHP, MySQL, HTML5, CSS3 y JavaScript**.

- **Arquitectura**: MVC en capas con capa de servicios (Controllers → Services → Models → PDO/MySQL).
- **Servidor**: Apache / XAMPP.
- **Enfoque de frontend**: HTML estático + `fetch` a una API JSON (sin framework).
- **CSRF**: protección activa en todas las operaciones de mutación.

---

## Índice

- [Requisitos](#requisitos)
- [Instalación y configuración](#instalación-y-configuración)
- [Estructura del proyecto](#estructura-del-proyecto)
- [Vistas públicas (frontend)](#vistas-públicas-frontend)
- [Panel de administración](#panel-de-administración)
- [Uso](#uso)
- [Tarjetas de prueba (pago simulado)](#tarjetas-de-prueba-pago-simulado)
- [Documentación técnica](#documentación-técnica)

---

## Requisitos

- **XAMPP** (Apache + PHP 8.x + MySQL 8+) o stack equivalente.
- PHP con extensiones `pdo_mysql` y `mbstring`.
- Un navegador moderno.

---

## Instalación y configuración

1. **Copiar el proyecto** dentro de `htdocs/` de XAMPP, por ejemplo:
   `C:\xampp\htdocs\petlandia\`

2. **Crear el archivo `.env`** a partir de la plantilla:
   ```bash
   cp .env.example .env
   ```
   Y ajustar las credenciales de tu MySQL (ver `:root:`).

3. **Configurar la base de datos**. Con MySQL en marcha (botón *Admin* de XAMPP o
   `mysql.exe`), importar los scripts en este orden:
   ```bash
   mysql -u root -pTU_CONTRASENA < database/schema.sql
   mysql -u root -pTU_CONTRASENA < database/procedures.sql
   mysql -u root -pTU_CONTRASENA < database/seed.sql
   ```
   Estos scripts crean la base `petlandia`, las tablas, los procedimientos, los
   roles (CLIENTE, ADMINISTRADOR, INVENTARIO), categorías y productos de ejemplo.

4. **Levantar Apache y MySQL** en el panel de XAMPP y acceder a:

   ```
   http://localhost/petlandia/views/home.html
   ```

> ⚠️ El proyecto está pensado para servirse desde un **subdirectorio** de htdocs
> (p. ej. `localhost/petlandia/`). Por eso el frontend usa rutas **relativas**
> (`../api/...`) y no rutas absolutas (`/api/...`), que solo funcionarían si el
> proyecto estuviera en la raíz del host.

---

## Estructura del proyecto

```
petlandia/
├── .env / .env.example     # Variables de entorno (credenciales BD, sesión, app)
├── .htaccess               # Rewrite hacia /public + protección de carpetas
├── app/
│   ├── bootstrap.php        # Carga config, helpers, sesión segura y autoload
│   ├── config/              # config.php, database.php (PDO), loadenv.php
│   ├── controllers/         # Auth, Product, Cart, Order, Payment, Admin
│   ├── services/            # Reglas de negocio + validaciones
│   ├── models/              # Acceso a datos (PDO)
│   ├── middleware/          # Auth, Admin, Inventory, Csrf
│   └── helpers/             # response, request, security, validation
├── database/
│   ├── schema.sql           # Create de la BD y tablas
│   ├── procedures.sql       # Procedimientos almacenados
│   └── seed.sql             # Datos de ejemplo (roles, categorías, productos)
├── public/
│   ├── .htaccess            # Headers de seguridad, bloqueo de archivos ocultos
│   ├── index.php
│   ├── api/                 # Routers: auth, products, cart, orders, payments, admin
│   ├── assets/
│   │   ├── css/             # Estilos por sección
│   │   ├── js/              # Lógica del frontend
│   │   └── images/
│   ├── uploads/products/    # Imágenes de productos
│   └── views/               # Vista públicas y vistas admin
└── storage/logs/           # Logs de la aplicación
```

---

## Vistas públicas (frontend)

| Vista | URL | Descripción |
|---|---|---|
| Inicio | `views/home.html` | Banner carrusel de promociones + carrusel de productos destacados |
| Catálogo | `views/catalog.html` | Listado con filtros (búsqueda, categoría, orden) y paginación |
| Detalle de producto | `views/product.html?id=N` | Ficha con imagen, precio, stock y agregar al carrito |
| Ingreso | `views/login.html` | Inicio de sesión de clientes |
| Registro | `views/register.html` | Alta de nuevos clientes |
| Carrito | `views/cart.html` | Ítems, cantidades, subtotales y total |
| Checkout | `views/checkout.html` | Datos de envío + pago simulado con tarjetas de prueba |
| Mis pedidos | `views/orders.html` | Historial de pedidos del cliente |

> Las rutas relativas entre vistas (`home.html`, `catálogo.html`, etc.) y hacia la
> API (`../api/...`) funcionan porque todas las vistas públicas viven al mismo
> nivel en `views/`.

### Librerías de frontend (`public/assets/js/`)

- `app.js` — Núcleo: `APP.request()` (fetch a la API, CSRF, sesión `same-origin`),
  `loadSession()`/`requireLogin()`, utilidades (`formatPrice`, `formatDate`,
  `estadoBadge`, `escapeHtml`, `renderAuth`, `refreshCartBadge`, `showAlert`, `logout`).
- `products.js` — Módulos `Catalog` y `ProductDetail`.
- `auth.js` — Lógica de login y registro.
- `cart.js` — Ver, cambiar cantidad, eliminar y vaciar carrito.
- `checkout.js` — Creación de pedido y proceso de pago simulado.
- `home.js` — Carruseles del inicio (banner + destacados).

---

## Panel de administración

- **Backend**: 100 % implementado. La API `api/admin.php` y los endpoints protegidos
  de `products.php` y `orders.php` están listos (estadísticas, listados, CRUD de
  productos, stock, gestión de estados de pedidos).
- **Frontend**: pendiente. Las vistas `views/admin/*.html` (`dashboard`, `products`,
  `product-form`, `inventory`, `orders`), `assets/js/admin.js` y `assets/css/admin.css`
  son **esqueletos vacíos** que aún no consumen la API ni tienen markup.

Ver el estado detallado en [docs/PROGRESS.md](docs/PROGRESS.md).

---

## Uso

1. **Registrarse** como cliente desde `views/register.html`.
2. **Iniciar sesión** desde `views/login.html`.
3. Navegar el **catálogo**, ver el **detalle** y **agregar al carrito**.
4. Ir a **checkout**, completar datos de envío, crear el pedido y **pagar** con una
   tarjeta de prueba.
5. Revisar **Mis pedidos** para ver el historial y estados.

---

## Tarjetas de prueba (pago simulado)

El sistema produce pagos simulados y **solo acepta** estas tarjetas:

| Número | Emisor | Resultado |
|---|---|---|
| `4111 1111 1111 1111` | VISA | Aprobado |
| `5555 5555 5555 4444` | Mastercard | Aprobado |
| `4000 0000 0000 0002` | VISA | Rechazado |

El checkout lista estas tarjetas automáticamente (endpoint `payments.php?action=test-cards`).

---

## Documentación técnica

- [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md) — arquitectura, capas y flujos.
- [docs/API.md](docs/API.md) — referencia completa de endpoints de la API.
- [docs/REFERENCIA.md](docs/REFERENCIA.md) — referencia técnica detallada por capa
  (helpers, middlewares, controllers, services, models, esquema de BD y flujos).
- [docs/PROGRESS.md](docs/PROGRESS.md) — qué hay hecho y qué falta (estado del proyecto).
