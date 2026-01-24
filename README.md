# Pasantia Project

Proyecto Laravel 12 con React 19, TypeScript, Inertia.js y Docker.

## Stack Tecnologico

| Componente | Tecnologia |
|------------|------------|
| Backend | Laravel 12 + PHP 8.5 |
| Frontend | React 19 + TypeScript |
| Routing SPA | Inertia.js 2 |
| UI Components | shadcn/ui + Radix UI |
| Estilos | Tailwind CSS 4 |
| Base de datos | PostgreSQL 17 |
| Cache | Redis |
| Contenedores | Docker + Laravel Sail |
| Build Tool | Vite 7 |

## Requisitos Previos

Antes de comenzar, asegurate de tener instalado:

- **Git** - Para clonar el repositorio
- **Docker Desktop** - [Descargar aqui](https://www.docker.com/products/docker-desktop/)
- **Node.js 20+** - [Descargar aqui](https://nodejs.org/)
- **Composer** - [Descargar aqui](https://getcomposer.org/)

### Verificar instalacion

```bash
git --version
docker --version
node -v
npm -v
composer -V
```

## Instalacion Paso a Paso

### Paso 1: Clonar el repositorio

```bash
git clone <URL_DEL_REPOSITORIO>
cd pasantia-proyect
```

### Paso 2: Instalar dependencias de PHP

```bash
composer install
```

### Paso 3: Configurar el archivo de entorno

```bash
cp .env.example .env
```

Luego edita el archivo `.env` con la siguiente configuracion de base de datos:

```env
DB_CONNECTION=pgsql
DB_HOST=pgsql
DB_PORT=5432
DB_DATABASE=laravel
DB_USERNAME=sail
DB_PASSWORD=password

REDIS_HOST=redis
```

### Paso 4: Generar la clave de la aplicacion

```bash
php artisan key:generate
```

### Paso 5: Verificar puertos disponibles

Antes de levantar los contenedores, asegurate de que los siguientes puertos esten libres:

| Puerto | Servicio |
|--------|----------|
| 80 | Aplicacion Laravel |
| 5432 | PostgreSQL |
| 6379 | Redis |
| 5173 | Vite (desarrollo) |

Para verificar si un puerto esta en uso:

```bash
# Linux/Mac
sudo lsof -i :80
sudo lsof -i :5432
sudo lsof -i :6379

# Windows (PowerShell)
netstat -ano | findstr :80
netstat -ano | findstr :5432
netstat -ano | findstr :6379
```

**Si el puerto 80 esta ocupado por Apache:**
```bash
sudo systemctl stop apache2
```

**Si hay contenedores Docker usando los puertos:**
```bash
docker ps  # Ver contenedores activos
docker stop <nombre_contenedor>  # Detener el contenedor
```

**Alternativa: Usar otro puerto para la aplicacion**

Agrega esto a tu `.env`:
```env
APP_PORT=8080
```
Y accede a la app en `http://localhost:8080`

### Paso 6: Levantar los contenedores Docker

```bash
./vendor/bin/sail up -d
```

Este comando levanta:
- `laravel.test` - Aplicacion Laravel + PHP 8.5
- `pgsql` - PostgreSQL 17
- `redis` - Redis (cache)

La primera vez tardara varios minutos mientras descarga las imagenes de Docker.

### Paso 7: Ejecutar las migraciones

```bash
./vendor/bin/sail artisan migrate
```

### Paso 8: Instalar dependencias de JavaScript

```bash
npm install
```

### Paso 9: Compilar assets para desarrollo

```bash
npm run dev
```

### Paso 10: Acceder a la aplicacion

Abre tu navegador en: **http://localhost**

(O `http://localhost:8080` si cambiaste el puerto)

## Comandos Utiles

### Docker / Sail

```bash
# Levantar contenedores (en segundo plano)
./vendor/bin/sail up -d

# Detener contenedores
./vendor/bin/sail down

# Ver logs de la aplicacion
./vendor/bin/sail logs -f laravel.test

# Ver logs de todos los servicios
./vendor/bin/sail logs -f

# Reiniciar contenedores
./vendor/bin/sail restart

# Acceder al shell del contenedor
./vendor/bin/sail shell

# Acceder a PostgreSQL
./vendor/bin/sail psql
```

### Artisan (Laravel)

```bash
# Ejecutar migraciones
./vendor/bin/sail artisan migrate

# Revertir migraciones
./vendor/bin/sail artisan migrate:rollback

# Crear un modelo con migracion
./vendor/bin/sail artisan make:model NombreModelo -m

# Crear un controlador
./vendor/bin/sail artisan make:controller NombreController

# Limpiar cache
./vendor/bin/sail artisan cache:clear
./vendor/bin/sail artisan config:clear
./vendor/bin/sail artisan route:clear

# Ver rutas registradas
./vendor/bin/sail artisan route:list
```

### NPM (Frontend)

```bash
# Instalar dependencias
npm install

# Desarrollo con hot reload
npm run dev

# Compilar para produccion
npm run build

# Verificar tipos TypeScript
npm run types

# Formatear codigo
npm run format

# Lint
npm run lint
```

### Tests

```bash
# Ejecutar todos los tests
./vendor/bin/sail artisan test

# Ejecutar tests con coverage
./vendor/bin/sail artisan test --coverage
```

## Alias Recomendado (Opcional)

Para no escribir `./vendor/bin/sail` cada vez, agrega este alias a tu shell:

**Linux/Mac** - Edita `~/.bashrc` o `~/.zshrc`:
```bash
alias sail='./vendor/bin/sail'
```

Luego recarga:
```bash
source ~/.bashrc  # o source ~/.zshrc
```

Ahora puedes usar:
```bash
sail up -d
sail artisan migrate
sail down
```

## Estructura del Proyecto

```
pasantia-proyect/
├── app/
│   ├── Http/
│   │   └── Controllers/     # Controladores
│   ├── Models/              # Modelos Eloquent
│   └── Actions/             # Acciones de Fortify
├── config/                  # Configuracion de Laravel
├── database/
│   ├── migrations/          # Migraciones de BD
│   └── seeders/             # Seeders
├── public/                  # Archivos publicos
├── resources/
│   └── js/                  # Codigo React/TypeScript
│       ├── components/      # Componentes reutilizables
│       │   └── ui/          # Componentes shadcn/ui
│       ├── hooks/           # Custom React hooks
│       ├── layouts/         # Layouts de la app
│       ├── lib/             # Utilidades
│       ├── pages/           # Paginas (rutas)
│       └── types/           # Tipos TypeScript
├── routes/
│   └── web.php              # Rutas de la aplicacion
├── tests/                   # Tests
├── compose.yaml             # Configuracion Docker
├── .env                     # Variables de entorno (NO commitear)
├── .env.example             # Ejemplo de variables de entorno
├── package.json             # Dependencias npm
├── composer.json            # Dependencias PHP
├── vite.config.ts           # Configuracion Vite
└── tsconfig.json            # Configuracion TypeScript
```

## Solucion de Problemas

### Error: "Port already in use"

**Puerto 80 (Apache):**
```bash
sudo systemctl stop apache2
# o
sudo service apache2 stop
```

**Puerto 5432 (PostgreSQL local):**
```bash
sudo systemctl stop postgresql
# o detener contenedor Docker que use ese puerto
docker stop <nombre_contenedor>
```

**Puerto 6379 (Redis local):**
```bash
sudo systemctl stop redis
# o detener contenedor Docker que use ese puerto
docker stop <nombre_contenedor>
```

### Error: "Connection refused" a la base de datos

1. Verifica que los contenedores esten corriendo:
```bash
./vendor/bin/sail ps
```

2. Verifica la configuracion en `.env`:
```env
DB_HOST=pgsql  # NO uses localhost ni 127.0.0.1
```

3. Reinicia los contenedores:
```bash
./vendor/bin/sail down
./vendor/bin/sail up -d
```

### Error: Permisos en storage/

```bash
chmod -R 775 storage bootstrap/cache
```

### Limpiar todo y empezar de cero

```bash
# Detener y eliminar contenedores y volumenes
./vendor/bin/sail down -v

# Eliminar node_modules y vendor
rm -rf node_modules vendor

# Reinstalar todo
composer install
npm install

# Levantar y migrar
./vendor/bin/sail up -d
./vendor/bin/sail artisan migrate
```

## Variables de Entorno

Copia `.env.example` a `.env` y configura:

```env
# Aplicacion
APP_NAME="Nombre de tu App"
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost

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

# Puerto alternativo (opcional)
# APP_PORT=8080
```

## Flujo de Trabajo Diario

```bash
# 1. Levantar contenedores
./vendor/bin/sail up -d

# 2. Iniciar Vite para desarrollo frontend
npm run dev

# 3. Trabajar en tu codigo...

# 4. Al terminar, detener contenedores
./vendor/bin/sail down
```

## Recursos

- [Documentacion Laravel 12](https://laravel.com/docs/12.x)
- [Documentacion Inertia.js](https://inertiajs.com/)
- [Documentacion React](https://react.dev/)
- [Documentacion shadcn/ui](https://ui.shadcn.com/)
- [Documentacion Tailwind CSS](https://tailwindcss.com/docs)
- [Documentacion Laravel Sail](https://laravel.com/docs/12.x/sail)
