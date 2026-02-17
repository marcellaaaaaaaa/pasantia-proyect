---
name: laravel-patterns
description: Patrones de arquitectura Laravel 12 para este proyecto. Usa este skill cuando diseñes controllers, services, models, o cualquier lógica backend. Cubre el flujo Controller → Form Request → Service → Model, integración con Inertia.js, Eloquent ORM, Fortify, y autorización con Policies.
---

# Laravel 12 Patterns

Patrones y convenciones del backend Laravel para este proyecto.

## Flujo principal: Controller → Form Request → Service → Model

```
Request HTTP
    ↓
routes/web.php  (define la ruta + middleware)
    ↓
FormRequest     (validación y autorización)
    ↓
Controller      (orquesta, delega a Service)
    ↓
Service         (lógica de negocio)
    ↓
Model/Eloquent  (acceso a datos)
    ↓
Inertia::render() o redirect()
```

## Estructura de directorios

```
app/
├── Actions/Fortify/          # Acciones de autenticación (Fortify)
├── Concerns/                 # Traits reutilizables (ej: PasswordValidationRules)
├── Http/
│   ├── Controllers/          # Controladores (ej: Settings/ProfileController.php)
│   ├── Middleware/           # Middleware (HandleInertiaRequests, HandleAppearance)
│   └── Requests/             # Form Requests (ej: Settings/ProfileUpdateRequest.php)
├── Models/                   # Modelos Eloquent
├── Providers/                # Service Providers
└── Services/                 # Clases de lógica de negocio (crear según necesidad)
```

## Patrón de Controller con Inertia.js

```php
<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreProductRequest;
use App\Models\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProductController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('products/index', [
            'products' => Product::latest()->paginate(15),
        ]);
    }

    public function store(StoreProductRequest $request): RedirectResponse
    {
        Product::create($request->validated());

        return to_route('products.index')->with('status', 'product-created');
    }

    public function update(UpdateProductRequest $request, Product $product): RedirectResponse
    {
        $product->update($request->validated());

        return to_route('products.index');
    }

    public function destroy(Product $product): RedirectResponse
    {
        $product->delete();

        return to_route('products.index');
    }
}
```

## Patrón de Form Request

```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreProductRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('create', Product::class);
        // O simplemente: return true; si no hay autorización especial
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'name'        => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'price'       => ['required', 'numeric', 'min:0'],
            'stock'       => ['required', 'integer', 'min:0'],
            'category_id' => ['required', 'exists:categories,id'],
        ];
    }
}
```

## Patrón de Service

```php
<?php

namespace App\Services;

use App\Models\Product;
use Illuminate\Support\Facades\DB;

class ProductService
{
    public function create(array $data): Product
    {
        return DB::transaction(function () use ($data) {
            $product = Product::create($data);
            // lógica adicional: eventos, notificaciones, etc.
            return $product;
        });
    }

    public function update(Product $product, array $data): Product
    {
        $product->update($data);
        return $product->fresh();
    }

    public function delete(Product $product): void
    {
        $product->delete();
    }
}
```

## Modelos Eloquent

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'description',
        'price',
        'stock',
        'category_id',
        'user_id',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'stock' => 'integer',
    ];

    // Relaciones
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('stock', '>', 0);
    }

    // Accessors
    public function getFormattedPriceAttribute(): string
    {
        return '$' . number_format($this->price, 2);
    }
}
```

## Rutas en web.php (con Inertia.js)

```php
<?php

use App\Http\Controllers\ProductController;
use Illuminate\Support\Facades\Route;

// Rutas de recursos (CRUD completo)
Route::middleware(['auth', 'verified'])->group(function () {
    Route::resource('products', ProductController::class);
    // Genera: products.index, products.create, products.store,
    //         products.show, products.edit, products.update, products.destroy
});

// Ruta individual
Route::get('/dashboard', function () {
    return Inertia::render('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');
```

## Inertia.js desde Laravel

```php
// Render una página React
return Inertia::render('products/index', [
    'products' => $products,
    'filters'  => request()->only(['search', 'status']),
]);

// Compartir datos globales (en HandleInertiaRequests middleware)
public function share(Request $request): array
{
    return [
        ...parent::share($request),
        'auth' => [
            'user' => $request->user(),
        ],
        'flash' => [
            'status' => $request->session()->get('status'),
        ],
    ];
}
```

## Autenticación con Fortify (ya implementada)

- Login, Register, Password Reset están en `Laravel\Fortify`
- Configuración en `app/Providers/FortifyServiceProvider.php`
- Actions en `app/Actions/Fortify/`
- No modificar a menos que sea necesario extender el flujo de auth

## Políticas (Policies)

```php
// Crear: ./vendor/bin/sail artisan make:policy ProductPolicy --model=Product
<?php

namespace App\Policies;

use App\Models\Product;
use App\Models\User;

class ProductPolicy
{
    public function viewAny(User $user): bool { return true; }
    public function view(User $user, Product $product): bool { return true; }
    public function create(User $user): bool { return $user->is_admin; }
    public function update(User $user, Product $product): bool
    {
        return $user->id === $product->user_id || $user->is_admin;
    }
    public function delete(User $user, Product $product): bool
    {
        return $user->id === $product->user_id || $user->is_admin;
    }
}
```

## Concerns (Traits)

```php
// En app/Concerns/ - ver PasswordValidationRules.php y ProfileValidationRules.php
trait ProductValidationRules
{
    protected function productRules(?int $ignoreId = null): array
    {
        return [
            'name' => ['required', 'string', 'max:255', Rule::unique('products')->ignore($ignoreId)],
        ];
    }
}
```

## Comandos Artisan útiles

```bash
./vendor/bin/sail artisan make:model Product -mfsc   # Model + migration + factory + seeder + controller
./vendor/bin/sail artisan make:request StoreProductRequest
./vendor/bin/sail artisan make:policy ProductPolicy --model=Product
./vendor/bin/sail artisan make:service ProductService  # No existe por defecto, crear manualmente
./vendor/bin/sail artisan migrate
./vendor/bin/sail artisan migrate:rollback
./vendor/bin/sail artisan db:seed
```
