---
name: laravel-validation
description: Validación en Laravel 12 usando Form Requests. Úsalo cuando necesites definir reglas de validación para endpoints, manejar mensajes de error, validación condicional, o crear Concerns de validación reutilizables.
---

# Laravel Validation Patterns

Validación con Form Requests en Laravel 12 para este proyecto.

## Form Request básico

```bash
./vendor/bin/sail artisan make:request StoreProductRequest
```

```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        // true = cualquier usuario autenticado puede
        // O verificar policy: return $this->user()->can('create', Product::class);
        return true;
    }

    public function rules(): array
    {
        return [
            'name'        => ['required', 'string', 'max:255'],
            'email'       => ['required', 'email', 'unique:users,email'],
            'price'       => ['required', 'numeric', 'min:0', 'max:99999.99'],
            'stock'       => ['required', 'integer', 'min:0'],
            'category_id' => ['required', 'exists:categories,id'],
            'description' => ['nullable', 'string', 'max:1000'],
            'is_active'   => ['boolean'],
            'tags'        => ['array'],
            'tags.*'      => ['string', 'max:50'],
        ];
    }

    // Opcional: mensajes personalizados
    public function messages(): array
    {
        return [
            'name.required'   => 'El nombre es obligatorio.',
            'email.unique'    => 'Este email ya está registrado.',
            'price.min'       => 'El precio no puede ser negativo.',
        ];
    }

    // Opcional: atributos humanizados para mensajes de error
    public function attributes(): array
    {
        return [
            'category_id' => 'categoría',
            'is_active'   => 'estado activo',
        ];
    }
}
```

## Form Request para UPDATE (con unique ignorando el registro actual)

```php
class UpdateProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('product'));
    }

    public function rules(): array
    {
        $product = $this->route('product'); // Model binding

        return [
            'name'  => ['required', 'string', 'max:255',
                        Rule::unique('products')->ignore($product->id)],
            'price' => ['required', 'numeric', 'min:0'],
            'stock' => ['required', 'integer', 'min:0'],
        ];
    }
}
```

## Concern de validación reutilizable (patrón del proyecto)

```php
// app/Concerns/ProductValidationRules.php
// Ver ProfileValidationRules.php y PasswordValidationRules.php como referencia

<?php

namespace App\Concerns;

use Illuminate\Validation\Rule;

trait ProductValidationRules
{
    protected function productRules(?int $ignoreId = null): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('products', 'name')->ignore($ignoreId),
            ],
            'price' => ['required', 'numeric', 'min:0'],
            'stock' => ['required', 'integer', 'min:0'],
        ];
    }
}

// Uso en Form Request:
class StoreProductRequest extends FormRequest
{
    use ProductValidationRules;

    public function rules(): array
    {
        return $this->productRules(); // sin ignore en create
    }
}

class UpdateProductRequest extends FormRequest
{
    use ProductValidationRules;

    public function rules(): array
    {
        return $this->productRules($this->route('product')->id);
    }
}
```

## Reglas de validación comunes

```php
// Strings
'name'     => ['required', 'string', 'max:255'],
'slug'     => ['required', 'string', 'alpha_dash', 'max:255'],
'content'  => ['nullable', 'string'],

// Números
'price'    => ['required', 'numeric', 'min:0', 'decimal:0,2'],
'quantity' => ['required', 'integer', 'between:1,999'],
'rating'   => ['required', 'numeric', 'min:1', 'max:5'],

// Emails y URLs
'email'    => ['required', 'email:rfc,dns', 'max:255'],
'website'  => ['nullable', 'url', 'max:255'],

// Fechas
'starts_at' => ['required', 'date', 'after:today'],
'ends_at'   => ['required', 'date', 'after:starts_at'],

// Relaciones
'user_id'     => ['required', 'exists:users,id'],
'category_id' => ['nullable', 'exists:categories,id'],

// Archivos
'avatar'  => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
'document'=> ['required', 'file', 'mimes:pdf', 'max:10240'],

// Booleanos y enums
'is_active' => ['boolean'],
'status'    => ['required', Rule::in(['active', 'inactive', 'pending'])],

// Arrays
'items'    => ['required', 'array', 'min:1'],
'items.*'  => ['required', 'exists:products,id'],
```

## Validación condicional

```php
public function rules(): array
{
    return [
        'type'        => ['required', Rule::in(['individual', 'company'])],
        // Solo requerido si type === 'company'
        'company_name'=> [Rule::requiredIf($this->type === 'company'), 'string', 'max:255'],
        'tax_id'      => [Rule::requiredIf($this->type === 'company'), 'string'],
    ];
}
```

## Respuestas de error en Inertia.js

Inertia.js maneja los errores de validación automáticamente (HTTP 422):
- Los errores se devuelven al frontend como `errors` en los props de la página
- El componente `InputError` ya está en el proyecto (`resources/js/components/input-error.tsx`)
- No necesitas manejar manualmente los errores 422 en el frontend
