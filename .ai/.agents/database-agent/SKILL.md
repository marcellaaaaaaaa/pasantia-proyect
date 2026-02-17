---
name: database-agent
description: Diseña esquemas de base de datos, crea migraciones Laravel, define relaciones Eloquent e índices PostgreSQL. Úsalo cuando necesites modelar datos, optimizar queries, o diseñar la estructura de tablas antes de implementar features.
compatibility: Designed for Claude Code with Laravel 12 + PostgreSQL 17 + Eloquent ORM
allowed-tools: Bash(./vendor/bin/sail artisan make:migration:*) Bash(./vendor/bin/sail artisan migrate:*) Read Write Edit Glob Grep
---

# Database Agent

Agente especializado en diseño de base de datos PostgreSQL 17 + Eloquent ORM para este proyecto.

## Cuándo activar

- Diseñar el esquema de una nueva entidad o módulo
- Crear migraciones de base de datos
- Agregar columnas a tablas existentes
- Definir relaciones entre modelos
- Optimizar queries con índices
- Decidir entre soft deletes vs hard deletes
- Diseñar tablas pivot para relaciones N:M

## Tablas existentes en el proyecto

| Tabla | Descripción |
|-------|-------------|
| `users` | Usuarios del sistema |
| `cache` | Cache de Laravel |
| `jobs` | Queue jobs |
| `(two_factor en users)` | Columnas 2FA agregadas a users |

## Comandos disponibles

```bash
# Crear migración
./vendor/bin/sail artisan make:migration create_X_table
./vendor/bin/sail artisan make:migration add_column_to_X_table
./vendor/bin/sail artisan make:migration create_X_Y_table  # tabla pivot

# Ejecutar migraciones
./vendor/bin/sail artisan migrate
./vendor/bin/sail artisan migrate:status
./vendor/bin/sail artisan migrate:rollback
./vendor/bin/sail artisan migrate:rollback --step=1
./vendor/bin/sail artisan migrate:fresh --seed  # ¡CUIDADO! borra todo y re-seed
```

## Tipos de columnas PostgreSQL en Laravel

```php
// Strings
$table->string('name', 255);        // VARCHAR(255) — default
$table->text('description');         // TEXT — sin límite
$table->char('code', 3);             // CHAR(3) fijo

// Números
$table->integer('quantity');         // INT
$table->unsignedInteger('stock');    // INT sin signo
$table->bigInteger('views');         // BIGINT
$table->decimal('price', 10, 2);     // DECIMAL(10,2) — para dinero
$table->float('rating', 3, 1);       // FLOAT(3,1)
$table->double('score');             // DOUBLE

// Booleanos
$table->boolean('is_active');

// Fechas
$table->date('birth_date');
$table->datetime('published_at');    // DATETIME
$table->timestamp('verified_at');    // TIMESTAMP
$table->timestamps();                // created_at + updated_at
$table->softDeletes();               // deleted_at

// JSON
$table->json('metadata');            // JSON/JSONB en PostgreSQL

// UUID
$table->uuid('uuid')->unique();
$table->ulid('ulid')->unique();

// Foreign Keys
$table->foreignId('user_id')->constrained()->cascadeOnDelete();
$table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
```

## Patrones de migración

### Crear tabla

```php
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();                          // BIGINT auto-increment PK
            $table->foreignId('user_id')           // FK a users
                  ->constrained()
                  ->cascadeOnDelete();
            $table->foreignId('category_id')
                  ->nullable()
                  ->constrained()
                  ->nullOnDelete();

            $table->string('name');
            $table->text('description')->nullable();
            $table->decimal('price', 10, 2);
            $table->unsignedInteger('stock')->default(0);
            $table->boolean('is_active')->default(true);
            $table->json('metadata')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Índices para queries frecuentes
            $table->index(['user_id', 'is_active']);
            $table->index('category_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
```

### Agregar columnas

```php
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('sku', 50)->unique()->after('name');
            $table->unsignedInteger('views')->default(0)->after('stock');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['sku', 'views']);
        });
    }
};
```

### Tabla pivot (N:M)

```php
// Migración
Schema::create('product_tag', function (Blueprint $table) {
    $table->foreignId('product_id')->constrained()->cascadeOnDelete();
    $table->foreignId('tag_id')->constrained()->cascadeOnDelete();
    $table->primary(['product_id', 'tag_id']);
    // No timestamps en tabla pivot simple
});

// En los modelos:
class Product extends Model {
    public function tags(): BelongsToMany {
        return $this->belongsToMany(Tag::class);
    }
}
```

## Relaciones Eloquent

```php
// Uno a Muchos (hasMany / belongsTo)
// User hasMany Products
public function products(): HasMany
{
    return $this->hasMany(Product::class);
}

// Product belongsTo User
public function user(): BelongsTo
{
    return $this->belongsTo(User::class);
}

// Muchos a Muchos (belongsToMany)
public function tags(): BelongsToMany
{
    return $this->belongsToMany(Tag::class)
                ->withTimestamps()  // si la tabla pivot tiene timestamps
                ->withPivot('quantity');  // si hay datos extra en pivot
}

// Uno a Uno (hasOne / belongsTo)
public function profile(): HasOne
{
    return $this->hasOne(UserProfile::class);
}

// Has Many Through
// Country hasMany Posts through Users
public function posts(): HasManyThrough
{
    return $this->hasManyThrough(Post::class, User::class);
}

// Polimórfico (morphMany / morphTo)
// Comments pueden ser de Products o Posts
public function comments(): MorphMany
{
    return $this->morphMany(Comment::class, 'commentable');
}
// En Comment:
public function commentable(): MorphTo
{
    return $this->morphTo();
}
```

## Índices PostgreSQL

```php
// Índice simple
$table->index('name');

// Índice único
$table->unique('email');
$table->unique(['user_id', 'product_id']);  // único compuesto

// Índice compuesto (para queries con WHERE multi-columna)
$table->index(['status', 'created_at']);  // para: WHERE status = ? ORDER BY created_at

// Índice de texto completo (PostgreSQL)
// Usar en raw migration si es necesario
DB::statement('CREATE INDEX products_search_idx ON products USING gin(to_tsvector(\'spanish\', name || \' \' || COALESCE(description, \'\')))');
```

## Cuándo usar Soft Deletes vs Hard Deletes

**Usar `SoftDeletes` cuando**:
- Los registros tienen relaciones importantes (orders, invoices, users)
- Se necesita historial o auditoría
- El negocio requiere posibilidad de restaurar
- Hay referencias desde otras tablas

**No usar SoftDeletes cuando**:
- Son registros temporales o de sistema (logs, cache)
- El espacio en disco es una preocupación
- Las referencias se eliminan en cascada de todas formas

## Decisiones de diseño comunes

| Caso | Recomendación |
|------|--------------|
| ID de usuario | `foreignId('user_id')->constrained()` |
| Campo de texto largo | `text()` no `string()` |
| Dinero | `decimal(10, 2)` nunca `float` |
| Estado/tipo | `string('status')` + Rule::in() o enum |
| Slug | `string('slug')->unique()` + índice |
| Orden personalizado | `unsignedInteger('order')->default(0)` |
| Datos flexibles | `json('metadata')->nullable()` |
