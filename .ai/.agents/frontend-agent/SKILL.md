---
name: frontend-agent
description: Implementa el frontend en React 19 + TypeScript + Inertia.js 2 + shadcn/ui + Tailwind CSS 4. Úsalo cuando necesites crear páginas, componentes, hooks, tipos TypeScript, o cualquier código del lado del cliente.
compatibility: Designed for Claude Code with React 19 + TypeScript + Inertia.js 2 + shadcn/ui + Tailwind CSS 4
allowed-tools: Read Write Edit Glob Grep
---

# Frontend Agent

Agente especializado en el frontend React 19 + TypeScript + Inertia.js 2 + shadcn/ui.

## Cuándo activar

- Crear nuevas páginas Inertia.js
- Implementar componentes React reutilizables
- Escribir tipos TypeScript para entidades
- Crear rutas Wayfinder para nuevos endpoints
- Implementar formularios con validación del servidor
- Agregar hooks personalizados
- Modificar layouts o navegación

## Estructura del proyecto (frontend)

```
resources/js/
├── actions/                      # Acciones tipadas (auto-generadas por wayfinder)
│   └── App/Http/Controllers/     # Espejo de controladores PHP
├── components/
│   ├── ui/                       # Componentes shadcn/ui (NO modificar directamente)
│   │   ├── button.tsx
│   │   ├── input.tsx
│   │   ├── label.tsx
│   │   ├── card.tsx
│   │   ├── dialog.tsx
│   │   ├── table.tsx
│   │   └── ... (más componentes)
│   ├── app-sidebar.tsx           # Sidebar de navegación
│   ├── heading.tsx               # Títulos de sección
│   ├── input-error.tsx           # Errores de formulario
│   ├── delete-user.tsx           # Ejemplo de componente con confirmación
│   └── ...
├── hooks/
│   ├── use-appearance.tsx
│   ├── use-clipboard.ts
│   ├── use-initials.tsx
│   └── use-mobile.tsx
├── layouts/
│   ├── app-layout.tsx            # Layout principal (con sidebar)
│   ├── auth-layout.tsx           # Layout de autenticación
│   └── settings/
│       └── layout.tsx            # Sub-layout de settings
├── pages/
│   ├── auth/                     # Páginas de autenticación (NO modificar)
│   ├── settings/                 # Ejemplo de páginas con formularios
│   │   ├── profile.tsx
│   │   └── password.tsx
│   └── dashboard.tsx             # Página de referencia
├── routes/                       # Rutas Wayfinder tipadas
│   ├── index.ts
│   ├── profile/index.ts
│   └── login/index.ts
├── types/
│   ├── index.ts                  # SharedData, User, BreadcrumbItem
│   ├── auth.ts
│   └── navigation.ts
├── app.tsx                       # Entry point
└── wayfinder/index.ts            # Utilidad de rutas
```

## Skills a consultar

- `inertia-react-patterns` — Páginas, formularios, layouts, routing
- `add-laravel-endpoint` — Pasos 6-9 para frontend de un endpoint nuevo

## Referencia detallada

Ver `references/REFERENCE.md` para:
- Ejemplos de páginas completas
- Cómo usar shadcn/ui components
- Patrón de tipos TypeScript
- Cómo crear rutas Wayfinder

## Convenciones importantes

- **Archivos**: kebab-case (`product-form.tsx`, `use-products.ts`)
- **Componentes**: PascalCase (`ProductForm`, `useProducts`)
- **Props types**: inline o interface nombrado con sufijo `Props`
- **Páginas**: default export siempre
- **Tipos de entidades**: en `resources/js/types/nombre.ts`
- **Rutas Wayfinder**: en `resources/js/routes/nombre/index.ts`
- **Imports**: usar `@/` como alias para `resources/js/`

## Componentes shadcn/ui disponibles

```tsx
// Formularios
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Checkbox } from '@/components/ui/checkbox';

// Layout
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Separator } from '@/components/ui/separator';
import { Badge } from '@/components/ui/badge';

// Feedback
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Spinner } from '@/components/ui/spinner';
import { Skeleton } from '@/components/ui/skeleton';

// Overlay
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Sheet, SheetContent, SheetHeader, SheetTitle, SheetTrigger } from '@/components/ui/sheet';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';

// Data
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
```

## Hooks de Inertia.js disponibles

```tsx
import { useForm, usePage, router } from '@inertiajs/react';
import { Form, Head, Link } from '@inertiajs/react';
```
