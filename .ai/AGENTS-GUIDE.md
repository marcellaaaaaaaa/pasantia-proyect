# Guía de Uso de Agentes AI

Guía práctica para trabajar con los agentes de este proyecto de forma eficiente.

---

## Mapa general de agentes

```
┌─────────────────────────────────────────────────────────┐
│                    planning-agent                        │
│          (punto de entrada para features nuevas)         │
└────────────────────┬────────────────────────────────────┘
                     │ divide el trabajo en
          ┌──────────┴──────────┐
          ▼                     ▼
  ┌───────────────┐     ┌────────────────┐
  │ database-agent│     │  backend-agent │
  │  (esquema DB) │────▶│ (Laravel PHP)  │
  └───────────────┘     └───────┬────────┘
                                │ entrega rutas y datos
                                ▼
                        ┌────────────────┐
                        │ frontend-agent │
                        │ (React/Inertia)│
                        └───────┬────────┘
                                │ cuando todo está listo
                                ▼
                        ┌────────────────┐
                        │ testing-agent  │
                        │ (Pest PHP)     │
                        └────────────────┘
```

---

## Flujo recomendado para implementar una feature nueva

### Paso 1 — Planificar con `planning-agent`

**Cuándo**: Siempre que quieras agregar algo nuevo al sistema.

**Cómo invocarlo**:
```
@planning-agent quiero agregar un CRUD de categorías de productos
```
```
@planning-agent necesito implementar un sistema de notificaciones para los usuarios
```
```
@planning-agent planifica cómo estructurar el módulo de inventario
```

**Qué produce**:
- Lista de tablas y columnas a crear
- Lista de archivos PHP a crear (modelos, controladores, requests)
- Lista de archivos React a crear (páginas, componentes, tipos)
- Orden de implementación sugerido
- Identifica qué skills aplican a cada tarea

**Tip**: No implementes nada hasta revisar el plan. Si algo del plan no te convence, dile al agente que lo ajuste antes de continuar.

---

### Paso 2 — Diseñar la base de datos con `database-agent`

**Cuándo**: Antes de escribir cualquier código PHP o React. El esquema de datos es la base de todo.

**Cómo invocarlo**:
```
@database-agent diseña el esquema para la tabla de categorías con relación a productos
```
```
@database-agent crea la migración para agregar el campo "stock_minimo" a la tabla products
```
```
@database-agent necesito una tabla pivot entre products y tags
```

**Qué produce**:
- Migraciones Laravel listas para ejecutar
- Definición de relaciones Eloquent entre modelos
- Índices recomendados para queries frecuentes
- Decisiones documentadas (soft delete sí/no, nullable sí/no, etc.)

**Tip**: Siempre ejecuta `./vendor/bin/sail artisan migrate` y verifica con `migrate:status` antes de pasar al siguiente paso.

---

### Paso 3 — Implementar el backend con `backend-agent`

**Cuándo**: Después de que la migración está aplicada y el esquema confirmado.

**Cómo invocarlo**:
```
@backend-agent implementa el ProductController con CRUD completo usando Inertia.js
```
```
@backend-agent crea el StoreProductRequest con validación de nombre único y precio positivo
```
```
@backend-agent agrega la ruta resource de products en web.php con middleware auth y verified
```

**Qué produce**:
- Modelos Eloquent con relaciones, casts y scopes
- Form Requests con reglas de validación
- Controllers que usan `Inertia::render()` y `to_route()`
- Rutas en `routes/web.php`
- Policies de autorización si aplica

**Tip**: Pídele de a una cosa por vez. Primero el modelo, luego los requests, luego el controller. Es más fácil revisar cambios pequeños que un dump de 5 archivos a la vez.

---

### Paso 4 — Implementar el frontend con `frontend-agent`

**Cuándo**: Cuando el backend ya está funcionando (rutas verificadas con `php artisan route:list`).

**Cómo invocarlo**:
```
@frontend-agent crea la página products/index con tabla de productos y botón de crear
```
```
@frontend-agent implementa el formulario de creación de producto con validación server-side
```
```
@frontend-agent agrega los tipos TypeScript para Product y PaginatedProducts
```
```
@frontend-agent crea las rutas Wayfinder para el resource products
```

**Qué produce**:
- Páginas `.tsx` en `resources/js/pages/`
- Componentes reutilizables en `resources/js/components/`
- Tipos TypeScript en `resources/js/types/`
- Rutas Wayfinder en `resources/js/routes/`

**Tip**: Empieza siempre por los tipos TypeScript. Tenerlos definidos hace que el agente genere código más correcto para las páginas.

---

### Paso 5 — Escribir tests con `testing-agent`

**Cuándo**: Cuando la feature está implementada y funcionando manualmente.

**Cómo invocarlo**:
```
@testing-agent escribe tests Feature para el CRUD de productos
```
```
@testing-agent agrega tests de validación: campos requeridos, precio negativo, nombre duplicado
```
```
@testing-agent crea la factory ProductFactory para usar en tests
```

**Qué produce**:
- Tests en `tests/Feature/` para endpoints HTTP
- Tests en `tests/Unit/` para servicios y modelos
- Factories con estados útiles (`inactive()`, `outOfStock()`)

**Ejecutar los tests**:
```bash
./vendor/bin/sail php vendor/bin/pest
./vendor/bin/sail php vendor/bin/pest --filter=Product
```

---

## Cómo combinar agentes con skills

Los skills son **contexto adicional** que puedes inyectar cuando necesitas precisión en un tema específico.

| Situación | Agente | Skill extra |
|-----------|--------|-------------|
| Crear validaciones complejas | `backend-agent` | `@laravel-validation` |
| Configurar permisos por rol | `backend-agent` | `@laravel-authorization` |
| Implementar un endpoint completo | `backend-agent` | `@add-laravel-endpoint` |
| Crear formularios con Inertia | `frontend-agent` | `@inertia-react-patterns` |
| Diseñar un módulo desde cero | `planning-agent` | `@laravel-patterns` |

**Ejemplo combinado**:
```
@backend-agent @laravel-authorization implementa la ProductPolicy
para que solo el dueño del producto pueda editarlo o eliminarlo
```

---

## Patrones de conversación eficientes

### Agregar una feature nueva (flujo completo)

```
1. @planning-agent quiero agregar un sistema de órdenes de compra

2. @database-agent [pegar el plan del paso 1] — crea las migraciones

3. [ejecutar: ./vendor/bin/sail artisan migrate]

4. @backend-agent implementa OrderController con index, store, update y destroy

5. @frontend-agent crea la página orders/index con tabla y botón de nueva orden

6. @frontend-agent crea el formulario orders/create

7. @testing-agent escribe tests Feature para OrderController
```

### Arreglar un bug de validación

```
@backend-agent @laravel-validation el campo "email" en UpdateUserRequest
debería ser único excepto para el usuario actual — corrígelo
```

### Agregar un campo a una feature existente

```
1. @database-agent agrega la columna "notes" (text, nullable) a la tabla orders

2. [ejecutar: ./vendor/bin/sail artisan migrate]

3. @backend-agent agrega "notes" al $fillable de Order y a las reglas de StoreOrderRequest

4. @frontend-agent agrega el campo "notes" al formulario orders/create
```

### Revisar y mejorar una página existente

```
@frontend-agent @inertia-react-patterns revisa resources/js/pages/products/index.tsx
y agrega paginación usando los links que ya devuelve el backend
```

---

## Referencia rápida

| Quiero... | Usar |
|-----------|------|
| Planificar una feature nueva | `planning-agent` |
| Crear/modificar tablas o relaciones | `database-agent` |
| Crear modelo, controller, request, ruta | `backend-agent` |
| Crear página, componente, tipo, ruta Wayfinder | `frontend-agent` |
| Escribir tests o factories | `testing-agent` |
| Saber cómo validar en Laravel | skill `laravel-validation` |
| Saber cómo manejar permisos | skill `laravel-authorization` |
| Ver el patrón Controller→Service→Model | skill `laravel-patterns` |
| Ver cómo usar useForm o Form component | skill `inertia-react-patterns` |
| Guía completa de endpoint nuevo | skill `add-laravel-endpoint` |

---

## Errores comunes a evitar

**No saltar el planning-agent** — Implementar sin plan lleva a diseños inconsistentes con el resto del proyecto.

**No crear migraciones sin el database-agent** — Escribirlas a mano sin considerar índices y relaciones genera problemas de performance más adelante.

**No dar contexto suficiente** — En vez de `@backend-agent crea el controller`, decir `@backend-agent crea ProductController con index (paginado, 15 por página), store, update y destroy, usando Inertia::render para index y to_route para redirects`.

**No verificar las rutas antes del frontend** — Siempre correr `./vendor/bin/sail artisan route:list | grep product` antes de pedirle al frontend-agent que construya los links.
