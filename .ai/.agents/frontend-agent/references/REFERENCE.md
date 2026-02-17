# Frontend Reference

Referencia técnica del frontend React 19 + TypeScript + Inertia.js 2 para este proyecto.

## Página Inertia.js completa (patrón del proyecto)

```tsx
// resources/js/pages/products/index.tsx
import AppLayout from '@/layouts/app-layout';
import { Head, Link, router } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import {
    Table, TableBody, TableCell, TableHead,
    TableHeader, TableRow
} from '@/components/ui/table';
import Heading from '@/components/heading';
import type { BreadcrumbItem, SharedData } from '@/types';
import type { PaginatedProducts, Product } from '@/types/product';
import { create, edit } from '@/routes/products';
import { usePage } from '@inertiajs/react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Productos', href: '/products' },
];

interface Props {
    products: PaginatedProducts;
    filters: { search?: string };
}

export default function ProductsIndex({ products, filters }: Props) {
    const { flash } = usePage<SharedData>().props;

    function handleDelete(product: Product) {
        if (confirm(`¿Eliminar "${product.name}"?`)) {
            router.delete(`/products/${product.id}`);
        }
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Productos" />

            <div className="space-y-6">
                <div className="flex items-center justify-between">
                    <Heading
                        title="Productos"
                        description="Gestiona el catálogo de productos"
                    />
                    <Button asChild>
                        <Link href={create().url}>Nuevo Producto</Link>
                    </Button>
                </div>

                {flash.status === 'product-created' && (
                    <p className="text-sm text-green-600">Producto creado exitosamente.</p>
                )}

                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>Nombre</TableHead>
                            <TableHead>Precio</TableHead>
                            <TableHead>Stock</TableHead>
                            <TableHead>Estado</TableHead>
                            <TableHead>Acciones</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {products.data.map(product => (
                            <TableRow key={product.id}>
                                <TableCell>{product.name}</TableCell>
                                <TableCell>${product.price}</TableCell>
                                <TableCell>{product.stock}</TableCell>
                                <TableCell>
                                    <Badge variant={product.is_active ? 'default' : 'secondary'}>
                                        {product.is_active ? 'Activo' : 'Inactivo'}
                                    </Badge>
                                </TableCell>
                                <TableCell className="space-x-2">
                                    <Button variant="outline" size="sm" asChild>
                                        <Link href={edit(product.id).url}>Editar</Link>
                                    </Button>
                                    <Button
                                        variant="destructive"
                                        size="sm"
                                        onClick={() => handleDelete(product)}
                                    >
                                        Eliminar
                                    </Button>
                                </TableCell>
                            </TableRow>
                        ))}
                    </TableBody>
                </Table>
            </div>
        </AppLayout>
    );
}
```

## Formulario con `useForm` de Inertia.js

```tsx
// resources/js/pages/products/create.tsx
import AppLayout from '@/layouts/app-layout';
import { Head, useForm } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import InputError from '@/components/input-error';
import Heading from '@/components/heading';
import type { BreadcrumbItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Productos', href: '/products' },
    { title: 'Nuevo Producto', href: '/products/create' },
];

export default function CreateProduct() {
    const { data, setData, post, processing, errors, reset } = useForm({
        name: '',
        description: '',
        price: '',
        stock: 0,
        is_active: true,
    });

    function handleSubmit(e: React.FormEvent) {
        e.preventDefault();
        post('/products', {
            onSuccess: () => reset(),
        });
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Nuevo Producto" />

            <div className="space-y-6 max-w-2xl">
                <Heading
                    title="Nuevo Producto"
                    description="Completa los datos del producto"
                />

                <form onSubmit={handleSubmit} className="space-y-4">
                    <div className="grid gap-2">
                        <Label htmlFor="name">Nombre</Label>
                        <Input
                            id="name"
                            value={data.name}
                            onChange={e => setData('name', e.target.value)}
                            placeholder="Nombre del producto"
                            required
                        />
                        <InputError message={errors.name} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="description">Descripción</Label>
                        <Textarea
                            id="description"
                            value={data.description}
                            onChange={e => setData('description', e.target.value)}
                            placeholder="Descripción opcional"
                            rows={3}
                        />
                        <InputError message={errors.description} />
                    </div>

                    <div className="grid grid-cols-2 gap-4">
                        <div className="grid gap-2">
                            <Label htmlFor="price">Precio</Label>
                            <Input
                                id="price"
                                type="number"
                                step="0.01"
                                min="0"
                                value={data.price}
                                onChange={e => setData('price', e.target.value)}
                            />
                            <InputError message={errors.price} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="stock">Stock</Label>
                            <Input
                                id="stock"
                                type="number"
                                min="0"
                                value={data.stock}
                                onChange={e => setData('stock', parseInt(e.target.value))}
                            />
                            <InputError message={errors.stock} />
                        </div>
                    </div>

                    <Button type="submit" disabled={processing}>
                        {processing ? 'Guardando...' : 'Crear Producto'}
                    </Button>
                </form>
            </div>
        </AppLayout>
    );
}
```

## Tipos TypeScript para una entidad

```typescript
// resources/js/types/product.ts

export interface Product {
    id: number;
    user_id: number;
    category_id: number | null;
    name: string;
    description: string | null;
    price: number;
    stock: number;
    is_active: boolean;
    created_at: string;
    updated_at: string;
    deleted_at: string | null;
}

// Para respuestas paginadas de Laravel
export interface PaginatedProducts {
    data: Product[];
    links: {
        first: string | null;
        last: string | null;
        prev: string | null;
        next: string | null;
    };
    meta: {
        current_page: number;
        from: number | null;
        last_page: number;
        per_page: number;
        to: number | null;
        total: number;
    };
}
```

## Rutas Wayfinder

```typescript
// resources/js/routes/products/index.ts
import { makeUrl } from '@/wayfinder';

// Sin parámetros
export const index = () => makeUrl('/products');
export const create = () => makeUrl('/products/create');

// Con parámetros
export const show = (id: number) => makeUrl(`/products/${id}`);
export const edit = (id: number) => makeUrl(`/products/${id}/edit`);
```

## SharedData (tipos globales)

```typescript
// resources/js/types/index.ts - ya existe en el proyecto
export interface User {
    id: number;
    name: string;
    email: string;
    email_verified_at: string | null;
}

export interface SharedData {
    auth: {
        user: User;
    };
    flash: {
        status?: string;
    };
    [key: string]: unknown;
}

export interface BreadcrumbItem {
    title: string;
    href: string;
}
```

## Patrón de formulario con `Form` component (Inertia v2)

El proyecto usa el nuevo componente `Form` de Inertia.js con acciones tipadas:

```tsx
// Ver resources/js/pages/settings/profile.tsx como referencia
import ProfileController from '@/actions/App/Http/Controllers/Settings/ProfileController';
import { Form } from '@inertiajs/react';

<Form
    {...ProfileController.update.form()}
    options={{ preserveScroll: true }}
>
    {({ processing, recentlySuccessful, errors }) => (
        <>
            <Input name="name" defaultValue={auth.user.name} />
            <InputError message={errors.name} />

            <Button disabled={processing}>Guardar</Button>

            {recentlySuccessful && (
                <p className="text-sm text-neutral-600">Guardado.</p>
            )}
        </>
    )}
</Form>
```

## Agregar item al Sidebar

```tsx
// resources/js/components/app-sidebar.tsx
// Buscar el array de navItems y agregar:
{
    title: 'Productos',
    href: '/products',
    icon: Package,  // de lucide-react
}
```

## Convenciones de archivos

| Tipo | Ubicación | Ejemplo |
|------|-----------|---------|
| Página | `resources/js/pages/` | `products/index.tsx` |
| Componente | `resources/js/components/` | `product-card.tsx` |
| Hook | `resources/js/hooks/` | `use-products.ts` |
| Tipo | `resources/js/types/` | `product.ts` |
| Ruta | `resources/js/routes/` | `products/index.ts` |
