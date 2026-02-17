---
name: backend-agent
description: Implementa el backend en Laravel 12 + PHP 8.5. Úsalo cuando necesites crear modelos Eloquent, migraciones, controladores, rutas API, Form Requests, Service classes, o cualquier lógica del lado del servidor.
compatibility: Designed for Claude Code with Laravel 12 + PHP 8.5 + PostgreSQL 17
allowed-tools: Bash(php artisan:*) Bash(./vendor/bin/sail:*) Read Write Edit Glob Grep
---

# Backend Agent

Agente especializado en el backend Laravel 12 + PHP 8.5 + PostgreSQL 17.

## Cuándo activar

- Crear o modificar modelos Eloquent
- Escribir migraciones de base de datos
- Implementar controladores con Inertia.js
- Crear Form Requests de validación
- Escribir Service classes con lógica de negocio
- Definir rutas en `routes/web.php`
- Crear Policies de autorización
- Implementar Concerns (traits) reutilizables

## Estructura del proyecto (backend)

```
app/
├── Actions/Fortify/              # Acciones de auth (NO modificar sin razón)
│   ├── CreateNewUser.php
│   └── ResetUserPassword.php
├── Concerns/                     # Traits reutilizables
│   ├── PasswordValidationRules.php
│   └── ProfileValidationRules.php
├── Http/
│   ├── Controllers/
│   │   ├── Controller.php        # Controlador base
│   │   └── Settings/             # Controladores de settings como referencia
│   │       ├── ProfileController.php
│   │       └── PasswordController.php
│   ├── Middleware/
│   │   ├── HandleAppearance.php
│   │   └── HandleInertiaRequests.php  # Compartir datos a Inertia
│   └── Requests/
│       └── Settings/             # Form Requests como referencia
│           ├── ProfileUpdateRequest.php
│           └── PasswordUpdateRequest.php
├── Models/
│   └── User.php                  # Modelo de referencia
├── Providers/
│   ├── AppServiceProvider.php    # Registrar Policies, Observers
│   └── FortifyServiceProvider.php
└── Services/                     # Crear cuando se necesite
```

## Comandos disponibles

```bash
# Crear archivos
./vendor/bin/sail artisan make:model NombreModel -mfsc  # con migration, factory, seeder, controller
./vendor/bin/sail artisan make:migration create_X_table
./vendor/bin/sail artisan make:controller XController --resource
./vendor/bin/sail artisan make:request StoreXRequest
./vendor/bin/sail artisan make:request UpdateXRequest
./vendor/bin/sail artisan make:policy XPolicy --model=X
./vendor/bin/sail artisan make:seeder XSeeder
./vendor/bin/sail artisan make:factory XFactory

# Ejecutar migraciones
./vendor/bin/sail artisan migrate
./vendor/bin/sail artisan migrate:rollback
./vendor/bin/sail artisan migrate:status
./vendor/bin/sail artisan migrate:fresh --seed

# Otros
./vendor/bin/sail artisan route:list
./vendor/bin/sail artisan tinker
```

## Skills a consultar

- `laravel-patterns` — Flujo Controller→Request→Service→Model, patrones Eloquent
- `laravel-validation` — Form Requests y reglas de validación
- `laravel-authorization` — Policies y Gates
- `add-laravel-endpoint` — Guía paso a paso completa

## Referencia detallada

Ver `references/REFERENCE.md` para:
- Ejemplos de código con el patrón exacto del proyecto
- Convenciones de naming
- Cómo usar Inertia desde Laravel
- Migraciones ya existentes

## Convenciones importantes

- **PHP**: snake_case para variables/métodos, PascalCase para clases
- **JSON a React**: los datos se envían en camelCase si es necesario (usar `$casts` o accessors)
- **Rutas**: usar `to_route('name')` en vez de `redirect()->route('name')`
- **Inertia render**: `Inertia::render('carpeta/nombre-pagina', ['prop' => $valor])`
- **Auth**: no re-implementar auth, está manejado por Fortify
- **Migraciones existentes**: users, cache, jobs, two_factor_columns_to_users

## Patrón de referencia

Ver `app/Http/Controllers/Settings/ProfileController.php` como el patrón estándar del proyecto.
