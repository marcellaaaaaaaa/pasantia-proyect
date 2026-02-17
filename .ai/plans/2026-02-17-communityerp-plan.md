# Plan: CommunityERP — Sistema SaaS de Gestión Territorial y Recaudación

> Generado: 2026-02-17
> Stack confirmado: Laravel 12, PostgreSQL, Inertia.js 2 + React 19 (frontend), FilamentPHP (panel admin)

---

## Contexto

Plataforma Multi-tenant para gestionar el cobro de servicios comunitarios (agua, aseo, vigilancia) en urbanizaciones. Resuelve la fricción entre cobros en efectivo en campo (por "Jefes de Calle") y la conciliación bancaria centralizada. El flujo crítico es: cobro en campo → custodia temporal en Wallet del cobrador → liquidación (Remittance) → ingreso al vault central de la comunidad.

**Nota sobre el stack actual:** El proyecto base tiene Inertia.js + React 19 (sin FilamentPHP). Se propone instalar FilamentPHP como panel admin en la ruta `/admin`, coexistiendo con el frontend React en el resto de rutas. FilamentPHP usa Livewire/Alpine.js internamente, lo que es compatible y no choca con Inertia.

---

## 1. Arquitectura de Datos (ERD Completo)

### Dominio de Tenancy

```
tenants
├── id (ulid/uuid)
├── name          VARCHAR(255)
├── slug          VARCHAR(100) UNIQUE  ← subdominio o identificador
├── plan          ENUM('free','basic','pro')
├── status        ENUM('active','suspended','cancelled')
├── settings      JSONB               ← config por tenant (moneda, timezone)
└── timestamps

sectors (Calles)
├── id
├── tenant_id     FK → tenants
├── name          VARCHAR(150)
├── description   TEXT NULL
└── timestamps

properties (Inmuebles)
├── id
├── tenant_id     FK → tenants
├── sector_id     FK → sectors
├── address       VARCHAR(255)
├── type          ENUM('house','apartment','commercial')
├── unit_number   VARCHAR(20) NULL    ← para aptos en multifamiliares
└── timestamps

families (Unidad lógica de cobro)
├── id
├── tenant_id     FK → tenants
├── property_id   FK → properties
├── name          VARCHAR(150)        ← "Familia González"
├── is_active     BOOLEAN DEFAULT true
└── timestamps

inhabitants
├── id
├── tenant_id     FK → tenants
├── family_id     FK → families
├── full_name     VARCHAR(200)
├── phone         VARCHAR(30) NULL
├── email         VARCHAR(150) NULL
├── is_primary_contact BOOLEAN DEFAULT false
└── timestamps
```

### Dominio de Usuarios

```
users  (tabla existente — EXTENDER con migración)
├── ... (columnas existentes: id, name, email, password, 2FA)
├── tenant_id     FK → tenants NULL    ← NULL solo para super-admin
├── role          ENUM('super_admin','admin','collector')
└── timestamps

sector_user  (pivot — asignación de cobradores a calles)
├── id
├── user_id       FK → users
├── sector_id     FK → sectors
└── assigned_at   TIMESTAMP
```

### Dominio Financiero (Critical Path)

```
services (Catálogo de servicios)
├── id
├── tenant_id     FK → tenants
├── name          VARCHAR(100)        ← "Agua", "Aseo", "Vigilancia"
├── description   TEXT NULL
├── default_price DECIMAL(10,2)
├── is_active     BOOLEAN DEFAULT true
└── timestamps

billings (Deudas generadas — immutable una vez creadas)
├── id
├── tenant_id     FK → tenants
├── family_id     FK → families
├── service_id    FK → services
├── period        CHAR(7)             ← "2026-02" (YYYY-MM)
├── amount        DECIMAL(10,2)
├── status        ENUM('pending','partial','paid','cancelled','void')
├── due_date      DATE
├── notes         TEXT NULL
├── generated_at  TIMESTAMP           ← trazabilidad: cuándo se generó
└── timestamps

payments (Pagos individuales — registro atómico)
├── id
├── tenant_id     FK → tenants
├── billing_id    FK → billings
├── collector_id  FK → users
├── amount        DECIMAL(10,2)       ← puede ser pago parcial
├── payment_method ENUM('cash','bank_transfer','mobile_payment')
├── status        ENUM('paid','pending_remittance','conciliated','reversed')
│                 ← paid: registrado; pending_remittance: en wallet del cobrador
│                 ← conciliated: admin lo confirmó; reversed: anulado
├── reference     VARCHAR(100) NULL   ← ref bancaria si aplica
├── payment_date  DATE
├── notes         TEXT NULL
├── receipt_sent_at TIMESTAMP NULL    ← auditoría de envío de comprobante
└── timestamps

wallets (Billetera de custodia — una por cobrador)
├── id
├── tenant_id     FK → tenants
├── user_id       FK → users UNIQUE   ← un cobrador = una wallet
├── balance       DECIMAL(12,2) NOT NULL DEFAULT 0.00
└── timestamps

wallet_transactions (Ledger inmutable de movimientos)
├── id
├── wallet_id     FK → wallets
├── payment_id    FK → payments NULL
├── remittance_id FK → remittances NULL
├── type          ENUM('credit','debit')
├── amount        DECIMAL(10,2)
├── balance_after DECIMAL(12,2)       ← saldo resultante (snapshot para auditoría)
├── description   VARCHAR(255)
└── created_at    TIMESTAMP           ← sin updated_at: es immutable

remittances (Liquidaciones — agrupación de pagos para entregar al admin)
├── id
├── tenant_id     FK → tenants
├── collector_id  FK → users
├── reviewed_by   FK → users NULL
├── amount_declared  DECIMAL(10,2)    ← suma declarada por el cobrador
├── amount_confirmed DECIMAL(10,2) NULL ← monto que el admin verificó físicamente
├── status        ENUM('draft','submitted','approved','rejected')
├── submitted_at  TIMESTAMP NULL
├── reviewed_at   TIMESTAMP NULL
├── collector_notes TEXT NULL
├── admin_notes   TEXT NULL
└── timestamps

remittance_payments (Pivot inmutable)
├── id
├── remittance_id FK → remittances
├── payment_id    FK → payments UNIQUE ← un pago solo puede estar en UNA liquidación
└── created_at

community_vaults (Caja central de la comunidad)
├── id
├── tenant_id     FK → tenants UNIQUE
├── balance       DECIMAL(12,2) NOT NULL DEFAULT 0.00
└── timestamps

vault_transactions (Ledger del vault central — immutable)
├── id
├── vault_id      FK → community_vaults
├── remittance_id FK → remittances
├── type          ENUM('credit','debit')
├── amount        DECIMAL(10,2)
├── balance_after DECIMAL(12,2)
├── description   VARCHAR(255)
└── created_at
```

### Índices críticos recomendados

```sql
-- Búsquedas de deudas por período y estado (consulta más frecuente)
CREATE INDEX idx_billings_tenant_period_status ON billings(tenant_id, period, status);

-- Pagos pendientes de remesa por cobrador
CREATE INDEX idx_payments_collector_status ON payments(collector_id, status);

-- Ledger ordenado cronológicamente por wallet
CREATE INDEX idx_wallet_tx_wallet_created ON wallet_transactions(wallet_id, created_at DESC);

-- Pivot de liquidaciones (prevenir duplicados)
CREATE UNIQUE INDEX uq_remittance_payment ON remittance_payments(payment_id);

-- Búsqueda de deudas por familia
CREATE INDEX idx_billings_family_period ON billings(family_id, period);
```

---

## 2. Paquetes PHP a instalar

```bash
# Panel administrativo
composer require filament/filament:"^3.3"

# Multi-tenancy (enfoque: single DB con tenant_id scope)
# No se usa paquete externo — se implementa manualmente con middleware
# Ventaja: menor complejidad, control total

# Roles y permisos (integración nativa con Filament)
composer require spatie/laravel-permission

# Auditoría de movimientos
composer require spatie/laravel-activitylog

# Generación de PDFs para comprobantes
composer require barryvdh/laravel-dompdf

# Importación masiva desde Excel/CSV
composer require maatwebsite/excel

# Filament Shield (roles/políticas integradas con Filament)
composer require bezhansalleh/filament-shield
```

---

## 3. Roadmap de Desarrollo por Fases

### FASE 1: Foundation — Censo y Configuración (Sprint 1-2, ~3 semanas)

**Objetivo:** MVP navegable con panel admin funcional y gestión territorial básica.

**Semana 1 — Setup técnico:**
- [ ] Instalar FilamentPHP y crear panel en `/admin`
- [ ] Instalar spatie/laravel-permission, configurar roles: `super_admin`, `admin`, `collector`
- [ ] Crear middleware `SetTenantScope` que inyecta `tenant_id` en queries via Global Scope
- [ ] Migración: `tenants`, `sectors`, `properties`, `families`, `inhabitants`
- [ ] Migración: extender `users` con `tenant_id` y `role`
- [ ] Migración: `sector_user` (pivot)

**Semana 2 — CRUD en Filament:**
- [ ] Filament Resource: `TenantResource` (solo super_admin)
- [ ] Filament Resource: `SectorResource` (listado, crear, editar)
- [ ] Filament Resource: `PropertyResource` (con filtro por sector)
- [ ] Filament Resource: `FamilyResource` (con relación a inmueble)
- [ ] Filament Resource: `InhabitantResource` (nested en Family)
- [ ] Filament Resource: `UserResource` (gestión de cobradores, asignación a sectores)

**Semana 3 — Importación masiva y políticas:**
- [ ] Importar Excel: `FamilyImport` con maatwebsite/excel
- [ ] Artisan Command: `import:census {tenant} {file}` para importación por CLI
- [ ] Policy: `SectorPolicy`, `PropertyPolicy`, `FamilyPolicy` — cobradores solo ven su sector
- [ ] Filament Shield: publicar políticas y configurar permisos

**Entregable Fase 1:** Admin puede crear comunidades, calles, viviendas, familias y asignar cobradores.

---

### FASE 2: Core Financiero — Facturación y Cobro (Sprint 3-4, ~3 semanas)

**Objetivo:** Generación de deudas y registro de pagos funcionando.

**Semana 4 — Modelos financieros:**
- [ ] Migración: `services`, `billings`, `payments`
- [ ] Migración: `wallets`, `wallet_transactions`
- [ ] Modelo `Service` con scope `active()`
- [ ] Modelo `Billing` con scope `byPeriod()`, `pending()`, relaciones
- [ ] Modelo `Payment` con scope `pendingRemittance()`, observers
- [ ] Modelo `Wallet` con método `credit(amount)` y `debit(amount)` + transacción DB
- [ ] Filament Resource: `ServiceResource`

**Semana 5 — Generación de deudas:**
- [ ] Artisan Command: `billing:generate {tenant} {period?}`
  - Itera todas las familias activas del tenant
  - Crea un Billing por cada servicio activo
  - Idempotente: verifica si ya existe billing para ese periodo/familia/servicio
- [ ] Registrar en Schedule: ejecutar el 1ro de cada mes para todos los tenants
- [ ] Filament Resource: `BillingResource` con filtros por sector, familia, periodo, estado
- [ ] Filament Action: "Generar deudas del mes" (manual desde panel)

**Semana 6 — Registro de pagos y lógica de wallet:**
- [ ] `PaymentService::register(billing, collector, amount, method)`:
  1. Crear `Payment` con status `paid`
  2. Actualizar `Billing` status según monto (partial/paid)
  3. Llamar `Wallet::credit(amount)` dentro de DB Transaction
  4. Crear `WalletTransaction` de tipo `credit` con `balance_after` calculado
  5. Registrar en activity log
- [ ] Filament Resource: `PaymentResource` (solo lectura para admin)
- [ ] Filament Action en BillingResource: "Registrar Pago" (modal con form)
- [ ] Observer `PaymentObserver`: on created → trigger wallet credit

**Entregable Fase 2:** Admin y cobradores (desde Filament) pueden generar deudas y registrar pagos. Wallet se actualiza automáticamente.

---

### FASE 3: Auditoría — Flujo de Remesas y Billeteras (Sprint 5-6, ~3 semanas)

**Objetivo:** Flujo completo de rendición de dinero y conciliación bancaria.

**Semana 7 — Entidades de remesas:**
- [ ] Migración: `remittances`, `remittance_payments`, `community_vaults`, `vault_transactions`
- [ ] Modelo `Remittance` con state machine: `draft → submitted → approved/rejected`
- [ ] Modelo `CommunityVault` con método `credit(amount, remittance)`
- [ ] `RemittanceService::create(collector)`:
  - Toma todos los `payments` del cobrador con status `pending_remittance`
  - Crea `Remittance` en `draft`
  - Crea registros en `remittance_payments` (pivot)
- [ ] `RemittanceService::approve(remittance, admin, amountConfirmed)`:
  - Valida que `amount_confirmed` ≤ `amount_declared` (con tolerancia configurable)
  - Cambia status de todos los `payments` incluidos a `conciliated`
  - Hace `Wallet::debit(amount_confirmed)` del cobrador
  - Hace `CommunityVault::credit(amount_confirmed)`
  - Crea `WalletTransaction` de tipo `debit`
  - Crea `VaultTransaction` de tipo `credit`
  - Todo dentro de una única `DB::transaction()`
  - Registra en activity log con detalle completo

**Semana 8 — Panel de remesas en Filament:**
- [ ] Filament Resource: `RemittanceResource`
  - Vista de lista: pendientes de revisión destacadas
  - Página de detalle: lista de pagos incluidos, monto declarado vs confirmado
  - Filament Action: "Aprobar Remesa" (input de monto confirmado)
  - Filament Action: "Rechazar Remesa" (motivo requerido)
- [ ] Filament Resource: `WalletResource` (solo lectura — saldo actual de cada cobrador)
- [ ] Filament Resource: `VaultResource` (saldo del vault y transacciones)
- [ ] Filament Widget: Dashboard con saldos actuales (wallets + vault)

**Semana 9 — Instalación de Spatie Activity Log:**
- [ ] Configurar `spatie/laravel-activitylog` con `log_name` por tipo de evento
- [ ] Loggear: registro de pago, aprobación/rechazo de remesa, cambio de estado de billing
- [ ] Filament Resource: `ActivityLogResource` (solo lectura, filtros por tipo y fecha)
- [ ] Tests Feature: flujo completo de remesa con assertions sobre balances

**Entregable Fase 3:** Flujo completo de dinero auditable: cobro → wallet → remesa → vault. Cada peso está rastreado.

---

### FASE 4: PWA Offline, Reportes y Pulido UX (Sprint 7-8, ~3 semanas)

**Objetivo:** Experiencia móvil para cobradores + reportes ejecutivos.

**Semana 10 — PWA para cobradores:**
- [ ] Configurar Vite PWA plugin (`vite-plugin-pwa`)
- [ ] Service Worker: cachear assets y rutas estáticas
- [ ] IndexedDB (con Dexie.js): almacenar pagos pendientes de sincronización offline
- [ ] Página React: `pages/collector/dashboard.tsx` (lista de deudas del sector)
- [ ] Página React: `pages/collector/payment-form.tsx` (formulario de cobro offline-first)
- [ ] API endpoint: `POST /api/collector/payments/sync` para sincronizar cuando vuelve la conexión
- [ ] Página React: `pages/collector/remittance.tsx` (crear liquidación desde móvil)

**Semana 11 — Comprobantes digitales:**
- [ ] `ReceiptService::generate(payment)` → PDF con barryvdh/laravel-dompdf
  - Datos: comunidad, familia, servicio, período, monto, fecha, cobrador, N° recibo
  - Template Blade: `resources/views/receipts/payment.blade.php`
- [ ] Ruta firmada: `GET /receipts/{payment}` (no requiere login, URL con expiración)
- [ ] Filament Action: "Enviar comprobante" → genera URL firmada para WhatsApp
- [ ] Observer: cuando `Payment` se crea, encolar job `SendReceiptJob`

**Semana 12 — Reportes y Dashboard:**
- [ ] Filament Widget: `RevenueChart` — cobros del mes por servicio (barra)
- [ ] Filament Widget: `CollectorPerformanceTable` — pagos por cobrador en el período
- [ ] Filament Widget: `PendingRemittancesAlert` — cobradores con saldo alto sin liquidar
- [ ] Filament Page: `Reports/MonthlyReport` — exportar a Excel/PDF
- [ ] Reporte: deudas vencidas por sector
- [ ] Reporte: estado de wallets de todos los cobradores

---

## 4. Backlog Técnico Completo (Tickets)

### Infraestructura / Setup
```
INF-001  Instalar FilamentPHP v3 y crear AdminPanelProvider
INF-002  Instalar spatie/laravel-permission y configurar roles/guards
INF-003  Crear middleware TenantScope con Global Scope en modelos
INF-004  Instalar spatie/laravel-activitylog y configurar log_name customizados
INF-005  Configurar Filament Shield para permisos granulares
INF-006  Configurar Queue con Redis para jobs asíncronos
INF-007  Configurar Schedule en console.php (billing:generate mensual)
```

### Migraciones
```
DB-001   create_tenants_table
DB-002   create_sectors_table
DB-003   create_properties_table
DB-004   create_families_table
DB-005   create_inhabitants_table
DB-006   add_tenant_role_to_users_table (extender tabla existente)
DB-007   create_sector_user_table (pivot)
DB-008   create_services_table
DB-009   create_billings_table (con índices compuestos)
DB-010   create_payments_table (con índices)
DB-011   create_wallets_table
DB-012   create_wallet_transactions_table
DB-013   create_remittances_table
DB-014   create_remittance_payments_table (con unique constraint)
DB-015   create_community_vaults_table
DB-016   create_vault_transactions_table
```

### Modelos y Servicios
```
MOD-001  Tenant model con relaciones hasMany sectors, users, services
MOD-002  Sector model con belongsTo tenant, hasMany properties, belongsToMany users
MOD-003  Property model con hasMany families
MOD-004  Family model con belongsTo property, hasMany billings, inhabitants
MOD-005  Service model con scope active()
MOD-006  Billing model + scope byPeriod(), pending(), relación payments
MOD-007  Payment model + observer PaymentObserver → wallet credit
MOD-008  Wallet model + credit() y debit() con DB transaction y lock pesimista
MOD-009  WalletTransaction model (sin updated_at, immutable)
MOD-010  Remittance model con state machine (draft→submitted→approved/rejected)
MOD-011  CommunityVault model + credit() con DB transaction
MOD-012  VaultTransaction model (immutable)
SVC-001  BillingGenerationService::generateForTenant(tenant, period)
SVC-002  PaymentService::register(billing, collector, amount, method)
SVC-003  RemittanceService::create(collector) y approve(remittance, admin, amount)
SVC-004  ReceiptService::generate(payment) → PDF
JOB-001  GenerateMonthlyBillingsJob (queue: billing)
JOB-002  SendReceiptJob (queue: notifications)
CMD-001  billing:generate Artisan command
CMD-002  import:census Artisan command
```

### Filament Resources
```
FIL-001  TenantResource (super_admin)
FIL-002  SectorResource
FIL-003  PropertyResource (con filtros por sector)
FIL-004  FamilyResource (con InhabitantResource como RelationManager)
FIL-005  UserResource (con asignación de sectores)
FIL-006  ServiceResource
FIL-007  BillingResource (con Action: Registrar Pago)
FIL-008  PaymentResource (solo lectura + Action: Enviar comprobante)
FIL-009  RemittanceResource (con Actions: Aprobar / Rechazar)
FIL-010  WalletResource (solo lectura)
FIL-011  VaultResource (solo lectura, historial de transacciones)
FIL-012  ActivityLogResource (solo lectura, filtros)
FIL-013  Dashboard Widgets (saldos, cobros del mes, alertas)
FIL-014  ReportsPage (exportar Excel/PDF)
```

### Frontend React / PWA
```
PWA-001  Instalar y configurar vite-plugin-pwa con service worker
PWA-002  Instalar Dexie.js para IndexedDB (almacenamiento offline)
PWA-003  pages/collector/dashboard.tsx (lista de deudas del sector)
PWA-004  pages/collector/payment-form.tsx (formulario offline-first)
PWA-005  API endpoint POST /api/collector/payments/sync (sincronización)
PWA-006  pages/collector/remittance.tsx (crear liquidación)
PWA-007  Push notifications para recordatorios de liquidación
```

### Tests
```
TST-001  Feature: BillingGenerationTest — genera correctamente por periodo
TST-002  Feature: PaymentRegistrationTest — wallet se incrementa tras pago
TST-003  Feature: RemittanceApprovalTest — balances correctos post-aprobación
TST-004  Feature: WalletConcurrencyTest — lock pesimista previene double-spending
TST-005  Unit: WalletTest — credit/debit con saldo insuficiente lanza excepción
TST-006  Unit: RemittanceStateMachineTest — transiciones de estado válidas/inválidas
TST-007  Feature: TenantScopeTest — cobrador no puede ver datos de otro tenant
TST-008  Feature: CollectorScopeTest — cobrador solo ve su sector asignado
```

---

## 5. Consideraciones de Riesgo

### CRÍTICO — Integridad Financiera

**R-1: Double spending en wallet (RIESGO ALTO)**
- **Problema:** Dos requests simultáneos pueden leer el mismo balance y sumar/restar incorrectamente.
- **Solución:** Usar `SELECT FOR UPDATE` (lock pesimista) al leer el balance antes de cualquier operación:
  ```php
  DB::transaction(function() use ($wallet, $amount) {
      $wallet = Wallet::lockForUpdate()->findOrFail($wallet->id);
      // ahora modificar balance de forma segura
  });
  ```
- **Nunca** usar `$wallet->balance += $amount; $wallet->save()` sin lock.

**R-2: Pago incluido en múltiples remesas (RIESGO ALTO)**
- **Problema:** Un cobrador malicioso podría incluir el mismo pago en dos liquidaciones.
- **Solución:** Constraint `UNIQUE` en `remittance_payments(payment_id)` a nivel base de datos. La aplicación también debe verificar, pero la BD es la última línea de defensa.

**R-3: Balance de wallet negativo (RIESGO MEDIO)**
- **Problema:** Una remesa aprobada por más de lo que tiene la wallet dejaría saldo negativo.
- **Solución:** En `Wallet::debit()`, verificar que `balance >= amount` dentro del transaction con lock. Lanzar `InsufficientBalanceException` si no hay saldo suficiente.

**R-4: Billing generado doble para el mismo período (RIESGO MEDIO)**
- **Problema:** El cron podría dispararse dos veces, generando deudas duplicadas.
- **Solución:** Constraint `UNIQUE` en `billings(family_id, service_id, period)`. El `BillingGenerationService` debe usar `firstOrCreate`.

### SEGURIDAD

**R-5: Aislamiento de tenant (RIESGO ALTO)**
- **Problema:** Un usuario de un tenant podría acceder a datos de otro tenant.
- **Solución:** Global Scope `TenantScope` en todos los modelos que tienen `tenant_id`. El middleware debe setear `app()->instance('current_tenant', ...)`. Tests específicos (`TenantScopeTest`) que validen este aislamiento.

**R-6: Cobrador accediendo a otros sectores (RIESGO MEDIO)**
- **Problema:** Un cobrador podría ver o cobrar familias de calles que no son suyas.
- **Solución:** `CollectorScope` en queries de `Billing` y `Family` que filtra por `sector_user` cuando el usuario tiene rol `collector`. Policies: `BillingPolicy::view()` verifica que `billing->family->property->sector` esté asignada al cobrador.

**R-7: URLs de recibos expuestos (RIESGO BAJO)**
- **Problema:** URLs de comprobantes accesibles sin autenticación (necesario para WhatsApp).
- **Solución:** Usar `URL::temporarySignedRoute()` con expiración de 48 horas. Validar la firma con middleware `ValidateSignature`.

### OPERACIONAL

**R-8: Sincronización offline con conflictos (RIESGO MEDIO)**
- **Problema:** Cobrador registra pago offline → el mismo billing ya fue pagado por otro medio.
- **Solución:** El endpoint de sync valida el status del billing antes de crear el payment. Si ya está `paid`, retorna error 409 con mensaje claro para el cobrador.

**R-9: Importación masiva con datos inconsistentes (RIESGO BAJO)**
- **Problema:** Excel de censo con errores (casas sin sector, familias sin casa).
- **Solución:** `FamilyImport` usa `WithValidation` de maatwebsite/excel. Retornar reporte de filas fallidas en lugar de rollback total.

---

## 6. Orden de Implementación Recomendado

```
1. INF-001 → INF-003 (Filament + permisos + TenantScope middleware)
2. DB-001 → DB-007 (todas las migraciones del dominio territorial)
3. MOD-001 → MOD-004 (modelos territoriales)
4. FIL-001 → FIL-005 (recursos Filament para censo)
5. DB-008 → DB-010 (migraciones financieras básicas)
6. MOD-005 → MOD-008 (modelos financieros) ← con lock pesimista desde el inicio
7. SVC-001 → SVC-002 (servicios de facturación y cobro)
8. CMD-001 (billing:generate command)
9. FIL-006 → FIL-008 (recursos Filament financieros)
10. DB-011 → DB-016 (migraciones de remesas y vault)
11. MOD-009 → MOD-012 (modelos de remesas)
12. SVC-003 (RemittanceService) ← punto más crítico del sistema
13. FIL-009 → FIL-013 (remesas, wallet, vault en Filament)
14. TST-001 → TST-008 (todos los tests antes de la PWA)
15. PWA-001 → PWA-007 (frontend PWA para cobradores)
16. JOB-001 → JOB-002, INF-006, INF-007 (queues y crons)
17. SVC-004 + PWA-006 (recibos PDF y WhatsApp)
18. FIL-014 (reportes y dashboard final)
```

---

## 7. Archivos a Crear (Lista completa)

### Migraciones (16)
- `database/migrations/YYYY_MM_DD_create_tenants_table.php`
- `database/migrations/YYYY_MM_DD_create_sectors_table.php`
- `database/migrations/YYYY_MM_DD_create_properties_table.php`
- `database/migrations/YYYY_MM_DD_create_families_table.php`
- `database/migrations/YYYY_MM_DD_create_inhabitants_table.php`
- `database/migrations/YYYY_MM_DD_add_tenant_role_to_users_table.php`
- `database/migrations/YYYY_MM_DD_create_sector_user_table.php`
- `database/migrations/YYYY_MM_DD_create_services_table.php`
- `database/migrations/YYYY_MM_DD_create_billings_table.php`
- `database/migrations/YYYY_MM_DD_create_payments_table.php`
- `database/migrations/YYYY_MM_DD_create_wallets_table.php`
- `database/migrations/YYYY_MM_DD_create_wallet_transactions_table.php`
- `database/migrations/YYYY_MM_DD_create_remittances_table.php`
- `database/migrations/YYYY_MM_DD_create_remittance_payments_table.php`
- `database/migrations/YYYY_MM_DD_create_community_vaults_table.php`
- `database/migrations/YYYY_MM_DD_create_vault_transactions_table.php`

### Modelos (12)
- `app/Models/Tenant.php`
- `app/Models/Sector.php`
- `app/Models/Property.php`
- `app/Models/Family.php`
- `app/Models/Inhabitant.php`
- `app/Models/Service.php`
- `app/Models/Billing.php`
- `app/Models/Payment.php`
- `app/Models/Wallet.php`
- `app/Models/WalletTransaction.php`
- `app/Models/Remittance.php`
- `app/Models/CommunityVault.php`
- `app/Models/VaultTransaction.php`

### Servicios y Jobs
- `app/Services/BillingGenerationService.php`
- `app/Services/PaymentService.php`
- `app/Services/RemittanceService.php`
- `app/Services/ReceiptService.php`
- `app/Jobs/GenerateMonthlyBillingsJob.php`
- `app/Jobs/SendReceiptJob.php`
- `app/Console/Commands/GenerateBillings.php`
- `app/Console/Commands/ImportCensus.php`

### Middleware y Scopes
- `app/Http/Middleware/SetTenantScope.php`
- `app/Scopes/TenantScope.php`
- `app/Scopes/CollectorScope.php`
- `app/Exceptions/InsufficientBalanceException.php`

### Policies
- `app/Policies/BillingPolicy.php`
- `app/Policies/PaymentPolicy.php`
- `app/Policies/RemittancePolicy.php`
- `app/Policies/SectorPolicy.php`

### Filament (14 recursos + widgets)
- `app/Filament/Resources/TenantResource.php`
- `app/Filament/Resources/SectorResource.php`
- `app/Filament/Resources/PropertyResource.php`
- `app/Filament/Resources/FamilyResource.php`
- `app/Filament/Resources/UserResource.php`
- `app/Filament/Resources/ServiceResource.php`
- `app/Filament/Resources/BillingResource.php`
- `app/Filament/Resources/PaymentResource.php`
- `app/Filament/Resources/RemittanceResource.php`
- `app/Filament/Resources/WalletResource.php`
- `app/Filament/Widgets/RevenueChart.php`
- `app/Filament/Widgets/CollectorPerformanceTable.php`
- `app/Filament/Widgets/PendingRemittancesAlert.php`
- `app/Filament/Pages/Reports/MonthlyReport.php`

### Frontend (PWA)
- `resources/js/pages/collector/dashboard.tsx`
- `resources/js/pages/collector/payment-form.tsx`
- `resources/js/pages/collector/remittance.tsx`
- `resources/js/lib/offline-db.ts` (Dexie.js IndexedDB wrapper)
- `resources/views/receipts/payment.blade.php` (template PDF)

### Tests
- `tests/Feature/BillingGenerationTest.php`
- `tests/Feature/PaymentRegistrationTest.php`
- `tests/Feature/RemittanceApprovalTest.php`
- `tests/Feature/WalletConcurrencyTest.php`
- `tests/Feature/TenantScopeTest.php`
- `tests/Feature/CollectorScopeTest.php`
- `tests/Unit/WalletTest.php`
- `tests/Unit/RemittanceStateMachineTest.php`

### Archivos a Modificar
- `routes/web.php` — agregar rutas API para PWA (`/api/collector/*`)
- `app/Models/User.php` — agregar `tenant_id`, `role`, relaciones y usar TenantScope
- `app/Providers/AppServiceProvider.php` — registrar Global Scopes y middleware
- `bootstrap/app.php` — registrar middleware `SetTenantScope`
- `routes/console.php` — registrar schedule para billing mensual
- `vite.config.ts` — agregar `vite-plugin-pwa`
- `composer.json` — nuevas dependencias
- `package.json` — `vite-plugin-pwa`, `dexie`
