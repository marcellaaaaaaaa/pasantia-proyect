---
name: inertia-react-patterns
description: Patrones de Inertia.js v2 + React 19 + TypeScript para este proyecto. Usa este skill cuando crees páginas, formularios, o componentes que interactúan con el backend Laravel. Cubre estructura de páginas, useForm, router.visit, usePage, flash messages, y layouts.
---

# Inertia.js v2 + React 19 Patterns

Patrones del frontend para este proyecto Laravel + Inertia.js + React.

## Estructura de una página Inertia.js

```tsx
// resources/js/pages/products/index.tsx
import AppLayout from '@/layouts/app-layout';
import { Head, Link } from '@inertiajs/react';
import type { BreadcrumbItem } from '@/types';

// Props vienen del Controller de Laravel
interface Product {
    id: number;
    name: string;
    price: number;
    stock: number;
}

interface Props {
    products: {
        data: Product[];
        links: any[];
        meta: any;
    };
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Productos', href: '/products' },
];

export default function ProductsIndex({ products }: Props) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Productos" />
            {/* contenido */}
        </AppLayout>
    );
}
```

## Formularios con Inertia.js `Form` component (patrón del proyecto)

Este proyecto usa el componente `Form` de `@inertiajs/react` con acciones tipadas (wayfinder/actions):

```tsx
// Patrón existente en el proyecto (ver profile.tsx)
import ProfileController from '@/actions/App/Http/Controllers/Settings/ProfileController';
import { Form } from '@inertiajs/react';

<Form
    {...ProfileController.update.form()}
    options={{ preserveScroll: true }}
    className="space-y-6"
>
    {({ processing, recentlySuccessful, errors }) => (
        <>
            <Input name="name" defaultValue={auth.user.name} />
            <InputError message={errors.name} />
            <Button disabled={processing}>Guardar</Button>
        </>
    )}
</Form>
```

## Formularios con `useForm` (patrón alternativo)

```tsx
import { useForm } from '@inertiajs/react';

export default function CreateProduct() {
    const { data, setData, post, processing, errors, reset } = useForm({
        name: '',
        price: 0,
        stock: 0,
    });

    function handleSubmit(e: React.FormEvent) {
        e.preventDefault();
        post('/products', {
            onSuccess: () => reset(),
        });
    }

    return (
        <form onSubmit={handleSubmit}>
            <input
                value={data.name}
                onChange={e => setData('name', e.target.value)}
            />
            {errors.name && <p>{errors.name}</p>}
            <button disabled={processing}>Crear</button>
        </form>
    );
}
```

## Navegación con `router` e `Link`

```tsx
import { router, Link } from '@inertiajs/react';
import { index } from '@/routes/products'; // wayfinder route

// Link declarativo
<Link href={index().url}>Ver productos</Link>

// Navegación programática
router.visit('/products');
router.visit('/products', { method: 'get', data: { search: 'laptop' } });

// DELETE con confirmación
router.delete(`/products/${id}`, {
    onSuccess: () => console.log('Eliminado'),
});
```

## Acceso a props compartidas (`usePage`)

```tsx
import { usePage } from '@inertiajs/react';
import type { SharedData } from '@/types';

// SharedData está definido en resources/js/types/index.ts
export default function MyComponent() {
    const { auth, flash } = usePage<SharedData>().props;

    return (
        <div>
            <p>Hola, {auth.user.name}</p>
            {flash.status && <p>{flash.status}</p>}
        </div>
    );
}
```

## Flash Messages

En Laravel (Controller):
```php
return to_route('products.index')->with('status', 'product-created');
```

En Inertia (HandleInertiaRequests.php ya lo comparte):
```tsx
const { flash } = usePage<SharedData>().props;
// flash.status === 'product-created'
```

## Layouts

```tsx
// Layout principal de la app (con sidebar)
import AppLayout from '@/layouts/app-layout';

// Layout de autenticación
import AuthLayout from '@/layouts/auth-layout';

// Layout de settings (sub-layout)
import SettingsLayout from '@/layouts/settings/layout';

// Uso típico:
export default function MyPage() {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Mi Página" />
            {/* contenido */}
        </AppLayout>
    );
}
```

## Tipos TypeScript

```tsx
// resources/js/types/index.ts - tipos compartidos
export interface User {
    id: number;
    name: string;
    email: string;
    email_verified_at: string | null;
}

export interface SharedData {
    auth: { user: User };
    flash: { status?: string };
    [key: string]: unknown;
}

// Para tipos específicos de un módulo, crear en resources/js/types/
// Ej: resources/js/types/product.ts
export interface Product {
    id: number;
    name: string;
    price: number;
    stock: number;
    created_at: string;
}
```

## Rutas con Wayfinder

El proyecto usa Wayfinder para rutas tipadas:

```tsx
// resources/js/routes/products/index.ts (crear si no existe)
import { makeUrl } from '@/wayfinder';

export const index = () => makeUrl('/products');
export const create = () => makeUrl('/products/create');
export const show = (id: number) => makeUrl(`/products/${id}`);
export const edit = (id: number) => makeUrl(`/products/${id}/edit`);

// Uso en componentes:
import { index, edit } from '@/routes/products';
<Link href={index().url}>Lista</Link>
<Link href={edit(product.id).url}>Editar</Link>
```

## Componentes shadcn/ui disponibles

```tsx
// Importar desde @/components/ui/
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select } from '@/components/ui/select';
import { Separator } from '@/components/ui/separator';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Alert, AlertDescription } from '@/components/ui/alert';
```

## Componentes de proyecto reutilizables

```tsx
import Heading from '@/components/heading';          // Títulos de sección
import InputError from '@/components/input-error';   // Errores de formulario
import { Spinner } from '@/components/ui/spinner';   // Loading states
```

## Diferencia: Página vs Componente

- **Página** (`resources/js/pages/`): Renderizada por Inertia desde el backend. Recibe props del Controller. Un archivo por vista.
- **Componente** (`resources/js/components/`): Componente React reutilizable. No se monta directamente por Inertia. Importado por páginas.

## Manejo de errores de formulario

Los errores los maneja Inertia automáticamente via `errors` del Form o `useForm`:
```tsx
// En Form component (patrón del proyecto)
{({ errors }) => (
    <>
        <Input name="email" />
        <InputError message={errors.email} />
    </>
)}

// En useForm
const { errors } = useForm({ email: '' });
{errors.email && <InputError message={errors.email} />}
```
