# CommunityERP

Sistema de gestion de cobros comunitarios para urbanizaciones. Permite administrar familias, inmuebles, servicios, facturacion mensual, cobros en campo y remesas, con un panel administrativo (Filament) y una PWA para cobradores.

## Stack Tecnologico

| Componente | Tecnologia |
|------------|------------|
| Backend | Laravel 12 + PHP 8.5 |
| Frontend | React 19 + TypeScript |
| Routing SPA | Inertia.js 2 |
| Panel Admin | Filament 3 |
| UI Components | shadcn/ui + Radix UI |
| Estilos | Tailwind CSS 4 |
| Base de datos | PostgreSQL 17 |
| Cache/Colas | Redis |
| Contenedores | Docker + Laravel Sail |
| Build Tool | Vite 7 |
| PWA | vite-plugin-pwa + Dexie.js (IndexedDB) |
| PDF | barryvdh/laravel-dompdf |
| Excel | maatwebsite/excel |
| Permisos | spatie/laravel-permission |
| Activity Log | spatie/laravel-activitylog |
| Auth | Laravel Fortify (2FA habilitado) |

## Requisitos Previos

- **Git** - Para clonar el repositorio
- **Docker Desktop** - [Descargar aqui](https://www.docker.com/products/docker-desktop/)
- **Composer** - [Descargar aqui](https://getcomposer.org/) (PHP 8.2+ local es suficiente)

> **Nota:** Node.js local no es estrictamente necesario. El proyecto usa Vite 7 que requiere Node 20.19+, y el contenedor Docker de Sail incluye Node 24. Se recomienda usar `sail npm` en lugar de `npm` local.

### Verificar instalacion

```bash
git --version
docker --version
composer -V
```

## Instalacion Paso a Paso

### 1. Clonar el repositorio

```bash
git clone <URL_DEL_REPOSITORIO>
cd pasantia-proyect
```

### 2. Instalar dependencias de PHP

```bash
composer install
```

> Funciona con PHP 8.2 local. La aplicacion corre con PHP 8.5 dentro de Docker.

### 3. Configurar el archivo de entorno

```bash
cp .env.example .env
```

Edita el archivo `.env` y asegurate de tener esta configuracion:

```env
APP_URL=segu
APP_PORT=8080

DB_CONNECTION=pgsql
DB_HOST=pgsql
DB_PORT=5432
DB_DATABASE=laravel
DB_USERNAME=sail
DB_PASSWORD=password

REDIS_HOST=redis
QUEUE_CONNECTION=redis
```

> **Importante:** Si el puerto 80 esta libre en tu sistema, puedes omitir `APP_PORT` y usar `APP_URL=http://localhost`. El puerto 8080 se usa como alternativa cuando el 80 esta ocupado (ej. Apache).

### 4. Levantar los contenedores Docker

```bash
./vendor/bin/sail up -d
```

La primera vez tardara varios minutos descargando las imagenes. Esto levanta:

| Contenedor | Servicio |
|------------|----------|
| `laravel.test` | Laravel + PHP 8.5 + Node 24 |
| `pgsql` | PostgreSQL 17 |
| `redis` | Redis (cache y colas) |

### 5. Generar la clave de la aplicacion

```bash
./vendor/bin/sail artisan key:generate
```

### 6. Corregir permisos (importante)

Sail ejecuta como usuario `sail` (UID 1337), lo cual puede causar conflictos de permisos con tu usuario local. Ejecuta estos comandos:

```bash
# Permisos de storage y cache
docker exec -u root pasantia-proyect-laravel.test-1 chmod -R 777 /var/www/html/storage /var/www/html/bootstrap/cache

# Permisos del frontend
docker exec -u root pasantia-proyect-laravel.test-1 chown -R sail:sail /var/www/html/resources/js

# Permisos de archivos publicos
chmod -R 777 public/
```

> **Nota:** El nombre del contenedor puede variar. Usa `docker ps` para verificarlo.

### 7. Instalar dependencias de JavaScript

Instalar **dentro del contenedor** para evitar problemas de version de Node:

```bash
docker exec -u root pasantia-proyect-laravel.test-1 npm install --prefix /var/www/html
docker exec -u root pasantia-proyect-laravel.test-1 chown -R sail:sail /var/www/html/node_modules
```

### 8. Ejecutar migraciones

```bash
make migrate
```

### 9. Poblar la base de datos con datos de prueba

```bash
./vendor/bin/sail artisan db:seed
```

Esto ejecuta el `DemoSeeder` que crea:

- 1 tenant (Urb. Los Pinos)
- 4 usuarios con diferentes roles
- 3 sectores (calles)
- 3 servicios (Agua, Aseo, Vigilancia)
- 15 familias con inmuebles y habitantes
- Billings del mes actual y anterior
- 6 pagos de ejemplo en estado `pending_remittance`
- Wallets para los cobradores

### 10. Compilar assets para desarrollo

```bash
./vendor/bin/sail npm run dev
```

### 11. Acceder a la aplicacion

Abre tu navegador en: **http://localhost:8080**

## Credenciales de Acceso

Todos los usuarios usan la contraseña: **`password`**

| Rol | Email | Acceso |
|-----|-------|--------|
| Super Admin | `superadmin@demo.com` | Panel admin completo, todos los tenants |
| Admin | `admin@demo.com` | Panel admin del tenant Urb. Los Pinos |
| Cobrador 1 | `cobrador@demo.com` | PWA cobrador (Calle A y B) |
| Cobrador 2 | `cobrador2@demo.com` | PWA cobrador (Calle C) |

## URLs Principales

| URL | Descripcion | Roles |
|-----|-------------|-------|
| `/login` | Inicio de sesion | Todos |
| `/admin` | Panel administrativo Filament | Super Admin, Admin |
| `/dashboard` | Dashboard general | Todos (autenticado) |
| `/collector` | Dashboard del cobrador (PWA) | Cobradores |
| `/collector/billing/{id}` | Formulario de cobro | Cobradores |
| `/collector/remittance` | Crear/ver remesas | Cobradores |
| `/receipts/{payment}` | Comprobante de pago (PDF, URL firmada) | Enlace firmado |
| `/settings/profile` | Configuracion de perfil | Todos (autenticado) |
| `/settings/password` | Cambiar contraseña | Todos (autenticado) |
| `/settings/two-factor` | Autenticacion de dos factores | Todos (autenticado) |
| `/settings/appearance` | Apariencia (tema) | Todos (autenticado) |

## Panel Administrativo (Filament)

Accesible en `/admin` para usuarios Super Admin y Admin. Incluye:

**Recursos (CRUD):**
- Tenants, Usuarios, Sectores, Familias, Inmuebles
- Servicios, Facturacion (Billings), Pagos (solo lectura)
- Remesas, Wallets, Bovedas (Vaults)
- Log de actividad

**Widgets del Dashboard:**
- Remesas pendientes
- Grafico de ingresos
- Rendimiento de cobradores

**Paginas especiales:**
- Reporte mensual (exporta Excel con 2 hojas)

## PWA del Cobrador

Accesible en `/collector`. Funcionalidades:

- Dashboard con resumen de cobros pendientes y realizados
- Formulario de cobro por billing
- Creacion de remesas
- **Soporte offline** con sincronizacion via IndexedDB (Dexie.js)
- Notificaciones push (hook `use-push-notifications`)

## Comandos Artisan Personalizados

```bash
# Generar facturacion mensual manualmente
./vendor/bin/sail artisan billing:generate {period}
# Ejemplo: ./vendor/bin/sail artisan billing:generate 2026-03

# Importar censo de familias desde Excel
./vendor/bin/sail artisan census:import {archivo}
# Ejemplo: ./vendor/bin/sail artisan census:import storage/app/censo.xlsx
```

## Colas (Queues)

El proyecto usa Redis para las colas. Para procesar jobs en desarrollo:

```bash
# Procesar todas las colas
./vendor/bin/sail artisan queue:work

# Procesar colas especificas
./vendor/bin/sail artisan queue:work --queue=billing
./vendor/bin/sail artisan queue:work --queue=notifications
```

**Jobs disponibles:**
- `GenerateMonthlyBillingsJob` (cola: `billing`) - Genera billings mensuales para todos los tenants activos
- `SendReceiptJob` (cola: `notifications`) - Envia comprobantes de pago

**Tarea programada (Scheduler):**
- Dia 1 de cada mes a las 06:00 se ejecuta `GenerateMonthlyBillingsJob` automaticamente

Para ejecutar el scheduler en desarrollo:

```bash
./vendor/bin/sail artisan schedule:work
```

## Flujo de Trabajo Diario

```bash
# 1. Levantar contenedores
./vendor/bin/sail up -d

# 2. Iniciar Vite (en una terminal separada)
./vendor/bin/sail npm run dev

# 3. (Opcional) Procesar colas en otra terminal
./vendor/bin/sail artisan queue:work

# 4. Trabajar en tu codigo...
#    La app esta en http://localhost:8080
#    Panel admin en http://localhost:8080/admin

# 5. Al terminar
./vendor/bin/sail down
```

## Tests

```bash
# Ejecutar todos los tests (114 tests)
./vendor/bin/sail artisan test

# Ejecutar tests con coverage
./vendor/bin/sail artisan test --coverage

# Ejecutar un test especifico
./vendor/bin/sail artisan test --filter=NombreDelTest
```

## Comandos Utiles

### Docker / Sail

```bash
./vendor/bin/sail up -d              # Levantar contenedores
./vendor/bin/sail down               # Detener contenedores
./vendor/bin/sail down -v            # Detener y borrar volumenes (reset DB)
./vendor/bin/sail restart            # Reiniciar contenedores
./vendor/bin/sail shell              # Shell del contenedor
./vendor/bin/sail psql               # Acceder a PostgreSQL
./vendor/bin/sail logs -f            # Ver logs de todos los servicios
```

### Artisan (Laravel)

```bash
./vendor/bin/sail artisan migrate              # Ejecutar migraciones
./vendor/bin/sail artisan migrate:fresh --seed  # Reset DB + seed
./vendor/bin/sail artisan db:seed              # Solo seed (requiere DB vacia o sin conflictos)
./vendor/bin/sail artisan cache:clear          # Limpiar cache
./vendor/bin/sail artisan config:clear         # Limpiar cache de config
./vendor/bin/sail artisan route:list           # Ver rutas registradas
./vendor/bin/sail artisan filament:upgrade     # Actualizar assets de Filament
```

### NPM (Frontend)

```bash
./vendor/bin/sail npm run dev        # Desarrollo con hot reload
./vendor/bin/sail npm run build      # Compilar para produccion
./vendor/bin/sail npm run types      # Verificar tipos TypeScript
./vendor/bin/sail npm run format     # Formatear codigo (Prettier)
./vendor/bin/sail npm run lint       # Lint (ESLint)
```

## Alias Recomendado

Para evitar escribir `./vendor/bin/sail` cada vez:

```bash
# Agregar a ~/.bashrc o ~/.zshrc
alias sail='./vendor/bin/sail'

# Recargar
source ~/.bashrc
```

Luego puedes usar: `sail up -d`, `sail artisan migrate`, `sail npm run dev`, etc.

## Estructura del Proyecto

```
pasantia-proyect/
├── app/
│   ├── Console/Commands/         # Comandos Artisan (billing:generate, census:import)
│   ├── Exports/                  # Exportaciones Excel (MonthlyReportExport)
│   ├── Filament/
│   │   ├── Pages/                # Paginas Filament (MonthlyReportPage)
│   │   ├── Resources/            # Recursos CRUD (12 recursos)
│   │   └── Widgets/              # Widgets del dashboard (3 widgets)
│   ├── Http/Controllers/
│   │   ├── Api/                  # CollectorController (PWA)
│   │   └── Settings/             # Profile, Password, 2FA
│   ├── Imports/                  # Importacion de censo (FamilyImport)
│   ├── Jobs/                     # Jobs asincrono (Billing, Receipt)
│   ├── Models/                   # Modelos Eloquent
│   └── Services/                 # Logica de negocio (Payment, Receipt, Remittance)
├── config/                       # Configuracion Laravel
├── database/
│   ├── migrations/               # Migraciones de BD
│   └── seeders/                  # DemoSeeder con datos de prueba
├── resources/
│   ├── js/                       # Codigo React/TypeScript
│   │   ├── components/ui/        # Componentes shadcn/ui
│   │   ├── hooks/                # Custom hooks (push notifications, etc.)
│   │   ├── layouts/              # Layouts de la app
│   │   ├── lib/                  # Utilidades + offline-db.ts (Dexie)
│   │   ├── pages/
│   │   │   ├── collector/        # PWA cobrador (dashboard, payment-form, remittance)
│   │   │   ├── settings/         # Paginas de configuracion
│   │   │   └── ...               # Dashboard, Welcome, Auth
│   │   ├── routes/               # Rutas auto-generadas (Wayfinder)
│   │   └── types/                # Tipos TypeScript (collector.ts, etc.)
│   └── views/
│       └── receipts/             # Blade template para PDF de comprobante
├── routes/
│   ├── web.php                   # Rutas web (Inertia + Collector + Receipts)
│   ├── console.php               # Scheduler (billing mensual)
│   └── settings.php              # Rutas de configuracion de usuario
├── tests/                        # Tests (114 tests)
├── compose.yaml                  # Docker Compose (Laravel, PostgreSQL, Redis)
├── vite.config.ts                # Configuracion Vite + PWA
├── .env.example                  # Plantilla de variables de entorno
└── package.json                  # Dependencias npm
```

## Roles y Permisos

| Rol | Descripcion | Acceso |
|-----|-------------|--------|
| `super_admin` | Administrador global | Todo el sistema, todos los tenants |
| `admin` | Administrador de tenant | Panel admin de su tenant |
| `collector` | Cobrador de campo | PWA de cobros, sus sectores asignados |

## Solucion de Problemas

### Error: "Port already in use"

```bash
# Puerto 80 (Apache)
sudo systemctl stop apache2

# Puerto 5432 (PostgreSQL local)
sudo systemctl stop postgresql

# Puerto 6379 (Redis local)
sudo systemctl stop redis

# O usar un puerto alternativo en .env
APP_PORT=8080
```

### Error: Permisos en storage/

```bash
docker exec -u root pasantia-proyect-laravel.test-1 chmod -R 777 /var/www/html/storage /var/www/html/bootstrap/cache
```

### Error: Vite no compila (version de Node)

Usar `sail npm` en lugar de `npm` local:

```bash
# MAL (puede fallar si Node local < 20.19)
npm run dev

# BIEN (usa Node 24 del contenedor)
./vendor/bin/sail npm run dev
```

### Error: "Connection refused" a la base de datos

1. Verificar que los contenedores esten corriendo: `./vendor/bin/sail ps`
2. Verificar que `.env` tenga `DB_HOST=pgsql` (no `localhost`)
3. Reiniciar: `./vendor/bin/sail down && ./vendor/bin/sail up -d`

### Reset completo (empezar de cero)

```bash
./vendor/bin/sail down -v            # Detener y borrar volumenes
rm -rf node_modules vendor           # Borrar dependencias
composer install                      # Reinstalar PHP deps
./vendor/bin/sail up -d              # Levantar contenedores
./vendor/bin/sail artisan key:generate
./vendor/bin/sail artisan migrate
./vendor/bin/sail artisan db:seed

# Reinstalar node_modules dentro del contenedor
docker exec -u root pasantia-proyect-laravel.test-1 npm install --prefix /var/www/html
docker exec -u root pasantia-proyect-laravel.test-1 chown -R sail:sail /var/www/html/node_modules
```

## Variables de Entorno

Las variables clave en `.env`:

```env
# Aplicacion
APP_NAME=Laravel
APP_URL=http://localhost:8080
APP_PORT=8080

# Base de datos (Docker/Sail)
DB_CONNECTION=pgsql
DB_HOST=pgsql
DB_PORT=5432
DB_DATABASE=laravel
DB_USERNAME=sail
DB_PASSWORD=password

# Redis (Docker/Sail)
REDIS_HOST=redis
REDIS_PORT=6379

# Colas con Redis
QUEUE_CONNECTION=redis

# Mail (en desarrollo usa log)
MAIL_MAILER=log
```

## Recursos

- [Laravel 12](https://laravel.com/docs/12.x)
- [Filament 3](https://filamentphp.com/docs/3.x)
- [Inertia.js](https://inertiajs.com/)
- [React](https://react.dev/)
- [shadcn/ui](https://ui.shadcn.com/)
- [Tailwind CSS](https://tailwindcss.com/docs)
- [Laravel Sail](https://laravel.com/docs/12.x/sail)
- [Spatie Laravel Permission](https://spatie.be/docs/laravel-permission)
- [Dexie.js](https://dexie.org/)
