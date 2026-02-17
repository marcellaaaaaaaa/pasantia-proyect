---
name: add-laravel-endpoint
description: Guía paso a paso para agregar un nuevo endpoint CRUD al proyecto Laravel + Inertia.js + React. Úsalo cuando necesites agregar una nueva entidad o feature al sistema, desde la base de datos hasta el componente React.
---

# Agregar un Nuevo Endpoint en Laravel + Inertia.js

Guía completa para agregar una nueva feature CRUD al proyecto.

## Checklist de implementación

```
□ 1. Migración de base de datos
□ 2. Modelo Eloquent
□ 3. Factory y Seeder (opcional pero recomendado)
□ 4. Form Requests (Store + Update)
□ 5. Controller
□ 6. Rutas en web.php
□ 7. Páginas React (Index + Create/Edit)
□ 8. Tipos TypeScript
□ 9. Rutas Wayfinder
□ 10. Tests
```

---

## Paso 1: Migración

```bash
./vendor/bin/sail artisan make:migration create_products_table
```

```php
// database/migrations/YYYY_MM_DD_create_products_table.php
public function up(): void
{
    Schema::create('products', function (Blueprint $table) {
        $table->id();
        $table->foreignId('user_id')->constrained()->cascadeOnDelete();
        $table->string('name');
        $table->text('description')->nullable();
        $table->decimal('price', 10, 2);
        $table->integer('stock')->default(0);
        $table->boolean('is_active')->default(true);
        $table->timestamps();
        $table->softDeletes();
    });
}

public function down(): void
{
    Schema::dropIfExists('products');
}
```

```bash
./vendor/bin/sail artisan migrate
```

---

## Paso 2: Modelo

```bash
./vendor/bin/sail artisan make:model Product
```

```php
// app/Models/Product.php
class Product extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['user_id', 'name', 'description', 'price', 'stock', 'is_active'];
    protected $casts = ['price' => 'decimal:2', 'is_active' => 'boolean'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
```

---

## Paso 3: Form Requests

```bash
./vendor/bin/sail artisan make:request StoreProductRequest
./vendor/bin/sail artisan make:request UpdateProductRequest
```

Ver `laravel-validation` skill para el detalle de reglas.

---

## Paso 4: Controller

```bash
./vendor/bin/sail artisan make:controller ProductController --resource
```

```php
// app/Http/Controllers/ProductController.php
class ProductController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('products/index', [
            'products' => Product::latest()->paginate(15),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('products/create');
    }

    public function store(StoreProductRequest $request): RedirectResponse
    {
        $request->user()->products()->create($request->validated());
        return to_route('products.index')->with('status', 'product-created');
    }

    public function edit(Product $product): Response
    {
        $this->authorize('update', $product);
        return Inertia::render('products/edit', ['product' => $product]);
    }

    public function update(UpdateProductRequest $request, Product $product): RedirectResponse
    {
        $product->update($request->validated());
        return to_route('products.index')->with('status', 'product-updated');
    }

    public function destroy(Product $product): RedirectResponse
    {
        $this->authorize('delete', $product);
        $product->delete();
        return to_route('products.index')->with('status', 'product-deleted');
    }
}
```

---

## Paso 5: Rutas

```php
// routes/web.php
Route::middleware(['auth', 'verified'])->group(function () {
    Route::resource('products', ProductController::class)
         ->except(['show']);  // si no necesitas vista de detalle individual
});
```

---

## Paso 6: Tipos TypeScript

```typescript
// resources/js/types/product.ts
export interface Product {
    id: number;
    user_id: number;
    name: string;
    description: string | null;
    price: number;
    stock: number;
    is_active: boolean;
    created_at: string;
    updated_at: string;
}

export interface PaginatedProducts {
    data: Product[];
    links: {
        first: string;
        last: string;
        prev: string | null;
        next: string | null;
    };
    meta: {
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
    };
}
```

---

## Paso 7: Rutas Wayfinder

```typescript
// resources/js/routes/products/index.ts
import { makeUrl } from '@/wayfinder';

export const index = () => makeUrl('/products');
export const create = () => makeUrl('/products/create');
export const edit = (id: number) => makeUrl(`/products/${id}/edit`);
export const destroy = (id: number) => makeUrl(`/products/${id}`, { method: 'DELETE' });
```

---

## Paso 8: Página Index

```tsx
// resources/js/pages/products/index.tsx
import AppLayout from '@/layouts/app-layout';
import { Head, Link, router } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import type { BreadcrumbItem } from '@/types';
import type { PaginatedProducts } from '@/types/product';
import { create, edit } from '@/routes/products';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Productos', href: '/products' }];

export default function ProductsIndex({ products }: { products: PaginatedProducts }) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Productos" />
            <div className="flex justify-between items-center mb-6">
                <h1 className="text-2xl font-bold">Productos</h1>
                <Button asChild>
                    <Link href={create().url}>Nuevo Producto</Link>
                </Button>
            </div>
            {/* tabla de productos */}
        </AppLayout>
    );
}
```

---

## Paso 9: Página Create/Edit

```tsx
// resources/js/pages/products/create.tsx
import AppLayout from '@/layouts/app-layout';
import { Head, useForm } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import InputError from '@/components/input-error';

export default function CreateProduct() {
    const { data, setData, post, processing, errors } = useForm({
        name: '',
        price: '',
        stock: 0,
    });

    return (
        <AppLayout breadcrumbs={[{ title: 'Nuevo Producto', href: '/products/create' }]}>
            <Head title="Nuevo Producto" />
            <form onSubmit={e => { e.preventDefault(); post('/products'); }} className="space-y-4 max-w-xl">
                <div>
                    <Label htmlFor="name">Nombre</Label>
                    <Input id="name" value={data.name} onChange={e => setData('name', e.target.value)} />
                    <InputError message={errors.name} />
                </div>
                <div>
                    <Label htmlFor="price">Precio</Label>
                    <Input id="price" type="number" step="0.01" value={data.price}
                           onChange={e => setData('price', e.target.value)} />
                    <InputError message={errors.price} />
                </div>
                <Button type="submit" disabled={processing}>Crear</Button>
            </form>
        </AppLayout>
    );
}
```

---

## Paso 10: Tests (Pest PHP)

```php
// tests/Feature/ProductTest.php
<?php

use App\Models\Product;
use App\Models\User;

test('authenticated user can view products list', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
         ->get('/products')
         ->assertInertia(fn ($page) => $page->component('products/index'));
});

test('user can create a product', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
         ->post('/products', [
             'name'  => 'Test Product',
             'price' => 29.99,
             'stock' => 10,
         ])
         ->assertRedirect('/products');

    $this->assertDatabaseHas('products', ['name' => 'Test Product']);
});
```

```bash
# Ejecutar tests
./vendor/bin/sail php vendor/bin/pest
./vendor/bin/sail php vendor/bin/pest --filter=ProductTest
```
