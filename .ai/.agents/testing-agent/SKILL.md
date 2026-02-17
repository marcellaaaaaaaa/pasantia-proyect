---
name: testing-agent
description: Escribe tests para el proyecto. Backend usa PHPUnit/Pest en tests/Feature/ y tests/Unit/. Frontend usa React Testing Library. Actívalo cuando el usuario diga "escribe tests", "agrega tests para", "quiero probar", o cuando se termine de implementar una feature.
compatibility: Designed for Claude Code with Laravel 12 + PHPUnit/Pest + React Testing Library
allowed-tools: Bash(./vendor/bin/sail artisan:*) Bash(./vendor/bin/sail php:*) Read Write Edit Glob Grep
---

# Testing Agent

Agente especializado en escribir tests para este proyecto Laravel 12 + React 19.

## Cuándo activar

- El usuario dice "escribe tests", "agrega tests para", "quiero probar"
- Se acaba de implementar una nueva feature
- Se necesita verificar el comportamiento de un endpoint
- Se quiere añadir tests a código existente

## Estructura de tests

```
tests/
├── Feature/              # Tests de integración (HTTP, DB)
│   └── Settings/         # Tests existentes como referencia
├── Unit/                 # Tests unitarios (Services, Models)
├── Pest.php              # Configuración de Pest
└── TestCase.php          # Base class
```

## Tests de Feature (Pest PHP) — patrón principal

```php
<?php

// tests/Feature/ProductTest.php
use App\Models\Product;
use App\Models\User;

// Arrange-Act-Assert con Pest
test('authenticated user can view products list', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
         ->get('/products')
         ->assertStatus(200)
         ->assertInertia(fn ($page) => $page->component('products/index'));
});

test('user can create a product', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
         ->post('/products', [
             'name'  => 'Laptop Pro',
             'price' => 1299.99,
             'stock' => 10,
         ])
         ->assertRedirect('/products');

    $this->assertDatabaseHas('products', [
        'name'    => 'Laptop Pro',
        'user_id' => $user->id,
    ]);
});

test('user cannot update another users product', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $product = Product::factory()->for($owner)->create();

    $this->actingAs($other)
         ->patch("/products/{$product->id}", ['name' => 'Hack'])
         ->assertForbidden();
});

test('validation fails without required fields', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
         ->post('/products', [])
         ->assertSessionHasErrors(['name', 'price', 'stock']);
});

test('guest cannot access products', function () {
    $this->get('/products')
         ->assertRedirect('/login');
});
```

## Tests de Unit (Pest PHP) — para Services y Models

```php
<?php

// tests/Unit/ProductServiceTest.php
use App\Models\Product;
use App\Models\User;
use App\Services\ProductService;

test('product service creates product correctly', function () {
    $user = User::factory()->create();
    $service = new ProductService();

    $product = $service->create([
        'user_id' => $user->id,
        'name'    => 'Test Product',
        'price'   => 29.99,
        'stock'   => 5,
    ]);

    expect($product)->toBeInstanceOf(Product::class)
        ->and($product->name)->toBe('Test Product')
        ->and($product->price)->toBe(29.99);
});

test('product scope active filters correctly', function () {
    Product::factory()->create(['is_active' => true]);
    Product::factory()->create(['is_active' => false]);

    $activeProducts = Product::active()->get();

    expect($activeProducts)->toHaveCount(1);
});
```

## Factories — cómo crearlas

```bash
./vendor/bin/sail artisan make:factory ProductFactory --model=Product
```

```php
// database/factories/ProductFactory.php
<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProductFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id'     => User::factory(),
            'name'        => fake()->words(3, true),
            'description' => fake()->sentence(),
            'price'       => fake()->randomFloat(2, 1, 1000),
            'stock'       => fake()->numberBetween(0, 100),
            'is_active'   => true,
        ];
    }

    // Estado: producto inactivo
    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }

    // Estado: sin stock
    public function outOfStock(): static
    {
        return $this->state(['stock' => 0]);
    }
}

// Uso en tests:
Product::factory()->create();
Product::factory()->inactive()->create();
Product::factory(5)->create();
Product::factory()->for($user)->create();
```

## Ejecutar tests

```bash
# Todos los tests
./vendor/bin/sail php vendor/bin/pest

# Solo un archivo
./vendor/bin/sail php vendor/bin/pest tests/Feature/ProductTest.php

# Con filtro por nombre
./vendor/bin/sail php vendor/bin/pest --filter="user can create"

# Con cobertura
./vendor/bin/sail php vendor/bin/pest --coverage

# En modo verbose
./vendor/bin/sail php vendor/bin/pest -v

# Solo tests de Feature
./vendor/bin/sail php vendor/bin/pest tests/Feature/
```

## Helpers de Pest disponibles

```php
// Grupos y datasets
it('does something', function () { ... });
test('something happens', function () { ... });

// Assertions de Pest
expect($value)->toBe('expected');
expect($collection)->toHaveCount(5);
expect($object)->toBeInstanceOf(User::class);
expect($string)->toContain('hello');
expect($value)->toBeNull();
expect($value)->not->toBeNull();
expect($value)->toBeTrue();
expect($value)->toBeFalse();

// Assertions de Laravel (en Feature tests)
$this->assertDatabaseHas('products', ['name' => 'Test']);
$this->assertDatabaseMissing('products', ['name' => 'Test']);
$this->assertModelMissing($product);  // soft-delete check
```

## Tests de Inertia.js

```php
use Inertia\Testing\AssertableInertia as Assert;

test('products page has correct data', function () {
    $user = User::factory()->create();
    $products = Product::factory(3)->for($user)->create();

    $this->actingAs($user)
         ->get('/products')
         ->assertInertia(fn (Assert $page) => $page
             ->component('products/index')
             ->has('products.data', 3)
             ->has('products.data.0', fn (Assert $item) => $item
                 ->has('id')
                 ->has('name')
                 ->has('price')
             )
         );
});
```

## RefreshDatabase

```php
// tests/Feature/ProductTest.php
uses(Tests\TestCase::class, Illuminate\Foundation\Testing\RefreshDatabase::class)->in('Feature');

// O en cada test file:
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);
```

El `Pest.php` ya configura esto para todos los tests de Feature.
