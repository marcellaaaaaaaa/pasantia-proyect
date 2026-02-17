# Backend Reference

Referencia técnica del backend Laravel 12 para este proyecto.

## Migraciones existentes

| Tabla | Descripción |
|-------|-------------|
| `users` | Usuarios con auth Fortify |
| `cache` | Cache de Laravel |
| `jobs` | Queue jobs |
| (two_factor_columns) | Columnas 2FA en users |

## Patrón completo de Controller

```php
<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreProductRequest;
use App\Http\Requests\UpdateProductRequest;
use App\Models\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProductController extends Controller
{
    /**
     * Listar todos los productos.
     */
    public function index(Request $request): Response
    {
        return Inertia::render('products/index', [
            'products' => Product::query()
                ->when($request->search, fn($q) => $q->where('name', 'like', "%{$request->search}%"))
                ->latest()
                ->paginate(15)
                ->withQueryString(),
            'filters' => $request->only(['search']),
        ]);
    }

    /**
     * Formulario de creación.
     */
    public function create(): Response
    {
        return Inertia::render('products/create');
    }

    /**
     * Guardar nuevo producto.
     */
    public function store(StoreProductRequest $request): RedirectResponse
    {
        $request->user()->products()->create($request->validated());

        return to_route('products.index')
            ->with('status', 'product-created');
    }

    /**
     * Formulario de edición.
     */
    public function edit(Product $product): Response
    {
        $this->authorize('update', $product);

        return Inertia::render('products/edit', [
            'product' => $product,
        ]);
    }

    /**
     * Actualizar producto existente.
     */
    public function update(UpdateProductRequest $request, Product $product): RedirectResponse
    {
        $product->update($request->validated());

        return to_route('products.index')
            ->with('status', 'product-updated');
    }

    /**
     * Eliminar producto.
     */
    public function destroy(Product $product): RedirectResponse
    {
        $this->authorize('delete', $product);

        $product->delete();

        return to_route('products.index')
            ->with('status', 'product-deleted');
    }
}
```

## Patrón de Form Request

```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // o: return $this->user()->can('create', Product::class);
    }

    public function rules(): array
    {
        return [
            'name'        => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'price'       => ['required', 'numeric', 'min:0'],
            'stock'       => ['required', 'integer', 'min:0'],
            'category_id' => ['required', 'exists:categories,id'],
        ];
    }
}
```

## Patrón de Modelo Eloquent

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
        'user_id',
        'name',
        'description',
        'price',
        'stock',
        'is_active',
    ];

    protected $casts = [
        'price'     => 'decimal:2',
        'stock'     => 'integer',
        'is_active' => 'boolean',
    ];

    // Relaciones
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    // Scope
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
```

## Patrón de Migración PostgreSQL

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->decimal('price', 10, 2);
            $table->unsignedInteger('stock')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            // Índices
            $table->index(['user_id', 'is_active']);
            $table->index('name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
```

## Compartir datos a Inertia (HandleInertiaRequests)

```php
// app/Http/Middleware/HandleInertiaRequests.php
public function share(Request $request): array
{
    return [
        ...parent::share($request),
        'auth' => [
            'user' => $request->user(),
        ],
        'flash' => [
            'status' => fn () => $request->session()->get('status'),
        ],
    ];
}
```

## Rutas en web.php

```php
<?php

use App\Http\Controllers\ProductController;
use Illuminate\Support\Facades\Route;

// Grupo de rutas autenticadas
Route::middleware(['auth', 'verified'])->group(function () {
    // CRUD completo
    Route::resource('products', ProductController::class);

    // O solo algunas rutas:
    Route::resource('categories', CategoryController::class)
         ->only(['index', 'store', 'update', 'destroy']);
});
```

## Convenciones de naming

| Concepto | Convención | Ejemplo |
|---------|-----------|---------|
| Clase PHP | PascalCase | `ProductController` |
| Método PHP | camelCase | `findByCategory()` |
| Variable PHP | camelCase | `$productList` |
| Tabla DB | snake_case plural | `products`, `product_categories` |
| Columna DB | snake_case | `category_id`, `is_active` |
| Ruta named | punto separado | `products.index`, `products.store` |
| Vista Inertia | carpeta/kebab-case | `'products/index'`, `'settings/profile'` |
| Form Request | PascalCase + Request | `StoreProductRequest` |

## Inertia response vs Redirect

```php
// Mostrar página React
return Inertia::render('products/index', ['data' => $data]);

// Redirigir con flash
return to_route('products.index')->with('status', 'product-created');

// Redirigir a URL
return redirect('/products');

// Back con flash
return back()->with('status', 'saved');
```
