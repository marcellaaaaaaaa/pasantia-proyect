---
name: laravel-authorization
description: Autorización en Laravel 12 con Policies y Gates. Úsalo cuando necesites controlar quién puede realizar qué acciones en el sistema, proteger rutas, o implementar roles de usuario.
---

# Laravel Authorization Patterns

Autorización con Policies y Gates para este proyecto.

## Policies (recomendado para modelos)

```bash
./vendor/bin/sail artisan make:policy ProductPolicy --model=Product
```

```php
<?php

namespace App\Policies;

use App\Models\Product;
use App\Models\User;

class ProductPolicy
{
    /**
     * Ver listado: cualquier usuario autenticado
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Ver uno: cualquier usuario autenticado
     */
    public function view(User $user, Product $product): bool
    {
        return true;
    }

    /**
     * Crear: solo admins
     */
    public function create(User $user): bool
    {
        return $user->is_admin;
    }

    /**
     * Actualizar: el dueño o un admin
     */
    public function update(User $user, Product $product): bool
    {
        return $user->id === $product->user_id || $user->is_admin;
    }

    /**
     * Eliminar: el dueño o un admin
     */
    public function delete(User $user, Product $product): bool
    {
        return $user->id === $product->user_id || $user->is_admin;
    }

    /**
     * Restaurar soft-deleted: solo admins
     */
    public function restore(User $user, Product $product): bool
    {
        return $user->is_admin;
    }
}
```

## Registrar Policy en AppServiceProvider

```php
// app/Providers/AppServiceProvider.php
use App\Models\Product;
use App\Policies\ProductPolicy;
use Illuminate\Support\Facades\Gate;

public function boot(): void
{
    Gate::policy(Product::class, ProductPolicy::class);
}
```

## Usar Policy en Controller

```php
class ProductController extends Controller
{
    public function store(StoreProductRequest $request): RedirectResponse
    {
        $this->authorize('create', Product::class);  // Usa ProductPolicy::create()

        Product::create($request->validated());
        return to_route('products.index');
    }

    public function update(UpdateProductRequest $request, Product $product): RedirectResponse
    {
        $this->authorize('update', $product);  // Usa ProductPolicy::update()

        $product->update($request->validated());
        return to_route('products.index');
    }

    public function destroy(Product $product): RedirectResponse
    {
        $this->authorize('delete', $product);

        $product->delete();
        return to_route('products.index');
    }
}
```

## Usar Policy en Form Request (recomendado)

```php
class UpdateProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        // La policy se evalúa aquí, antes de la validación
        return $this->user()->can('update', $this->route('product'));
    }
}
```

## Gates (para permisos sin modelo)

```php
// app/Providers/AppServiceProvider.php
use Illuminate\Support\Facades\Gate;

public function boot(): void
{
    Gate::define('access-admin-panel', function (User $user) {
        return $user->is_admin;
    });

    Gate::define('manage-settings', function (User $user) {
        return $user->is_admin || $user->is_manager;
    });
}

// Uso en Controller:
$this->authorize('access-admin-panel');

// Uso en Blade (si usas vistas Blade):
@can('access-admin-panel')
    <a href="/admin">Admin</a>
@endcan
```

## Middleware de auth en rutas

```php
// routes/web.php

// Solo usuarios autenticados
Route::middleware(['auth'])->group(function () {
    Route::resource('products', ProductController::class);
});

// Autenticados + email verificado
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
});

// Con contraseña confirmada (para operaciones sensibles)
Route::middleware(['auth', 'password.confirm'])->group(function () {
    Route::delete('/account', [AccountController::class, 'destroy']);
});
```

## Compartir permisos al frontend (Inertia.js)

```php
// app/Http/Middleware/HandleInertiaRequests.php
public function share(Request $request): array
{
    return [
        ...parent::share($request),
        'auth' => [
            'user' => $request->user(),
        ],
        // Compartir permisos al frontend si se necesitan
        'can' => $request->user() ? [
            'createProducts' => $request->user()->can('create', Product::class),
            'accessAdmin'    => $request->user()->can('access-admin-panel'),
        ] : [],
    ];
}
```

```tsx
// En el frontend React:
const { can } = usePage<SharedData>().props;
{can.createProducts && <Button>Crear producto</Button>}
```

## Roles simples (sin paquete externo)

Si no necesitas un sistema de roles complejo:

```php
// migrations: add is_admin column to users
Schema::table('users', function (Blueprint $table) {
    $table->boolean('is_admin')->default(false)->after('email');
});

// En User model:
public function isAdmin(): bool
{
    return $this->is_admin;
}

// Uso:
if ($user->isAdmin()) {
    // acceso permitido
}
```

Para roles más complejos, considerar el paquete `spatie/laravel-permission`.
