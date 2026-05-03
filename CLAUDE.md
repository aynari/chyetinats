# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

Bagisto 2.4.x — open-source Laravel 12 e-commerce platform. PHP 8.3+, Vue.js 3, Tailwind CSS 3, Vite 5.

## Reference Knowledge Base

- `compass_bagisto.md` — deep reference (architecture, extension patterns, theme system, events catalog, payment/shipping integration, admin customization, locales, common pitfalls). Read the relevant section before non-trivial work.
- `AGENTS.md` — repository map, package list, validation checklist.

## Common Commands

### Development
```bash
composer install                # Install PHP dependencies
php artisan bagisto:install     # Full installation (migrations, seeders, assets) — fresh project only
php artisan serve               # Start PHP dev server
php artisan optimize:clear      # Clear all caches (run after config/code changes)
composer dump-autoload          # Required after adding new PSR-4 namespaces / new packages
```

### Bagisto-specific Artisan
```bash
php artisan bagisto:version                        # Print installed Bagisto version
php artisan bagisto:translations:check             # Validate translation keys across 21 locales
php artisan indexer:index --type=price             # Reindex price data after bulk catalog changes
php artisan indexer:index --type=inventory         # Reindex inventory
php artisan indexer:index --type=flat              # Reindex product_flat
php artisan product:price-rule:index               # Apply catalog rules to price index
php artisan responsecache:clear [--url=...]        # Purge Full Page Cache
php artisan varnish:purge [--url=...]              # Purge Varnish (if enabled)
php artisan storage:link                           # Mandatory after install — product images 404 without it
php artisan queue:work --queue=default,broadcastable  # Required for ES indexing, admin notifications, mail
```

### Testing
```bash
vendor/bin/pest                                         # Run all tests
vendor/bin/pest --testsuite="Admin Feature Test"        # Run a specific test suite
vendor/bin/pest packages/Webkul/Admin/tests/Feature     # Run tests in a directory
vendor/bin/pest --filter="test name"                    # Run a single test by name
php artisan test --compact                              # Alternate runner with condensed output
```

Test suites defined in `phpunit.xml`: Admin Feature, Core Unit, Customer Unit, DataGrid Unit, Installer Feature, PayU Unit/Feature, Razorpay Unit/Feature, Shop Feature, Stripe Unit/Feature.

Tests use **Pest 3** with package-specific TestCase classes bound in `tests/Pest.php`. Each package's tests live in `packages/Webkul/<Package>/tests/`.

### E2E Tests (Playwright)
E2E tests are run from within each package directory. Each package has its own Playwright config and tests:

**Admin**:
```bash
cd packages/Webkul/Admin
npm install
npx playwright install --with-deps chromium
npx playwright test --config=tests/e2e-pw/playwright.config.ts
```

**Shop**:
```bash
cd packages/Webkul/Shop
npm install
npx playwright install --with-deps chromium
npx playwright test --config=tests/e2e-pw/playwright.config.ts
```

Tests require a running Laravel server (`php artisan serve`) and seeded database. Set `BASE_URL` env var if not using default.

### Code Style
```bash
vendor/bin/pint --dirty     # Fix only changed files (preferred during dev)
vendor/bin/pint             # Fix PHP code style on all files
vendor/bin/pint --test      # Check style without fixing (CI uses this)
```

### Translations
When adding new translation keys, provide translations for **all 21 locales** in the package's `Resources/lang/<locale>/` directory: `ar, bn, ca, de, en, es, fa, fr, he, hi_IN, id, it, ja, nl, pl, pt_BR, ru, sin, tr, uk, zh_CN`. A missing locale fails CI. Verify with:
```bash
php artisan bagisto:translations:check
```

## Architecture

### Modular Package System

All core functionality lives in **`packages/Webkul/`** (~42 packages). Each package is a self-contained Laravel package with its own models, controllers, routes, views, migrations, and service providers.

**Dual registration**: Each package registers in two places:
1. **`bootstrap/providers.php`** — Main ServiceProvider (routes, views, events, config)
2. **`config/concord.php`** — ModuleServiceProvider (Konekt Concord model/enum registration)

**Path repositories**: `composer.json` declares `packages/*/*` as `"type": "path"` — packages are symlinked, so edits to package code take effect with no `composer update`. Run `composer dump-autoload` only when adding new packages or namespaces.

### Key Design Patterns

**Concord three-component pattern**: Every entity has a **Contract** (interface in `Contracts/`), a **Model** (Eloquent class in `Models/`), and a **Proxy** (in `Models/`, extends `Konekt\Concord\Proxies\ModelProxy`). The Model's binding to its Contract is registered in the package's `ModuleServiceProvider` and wired through `config/concord.php`. This is what enables runtime model substitution without modifying core.

**Override a core model** (preferred over editing `packages/Webkul/*`):
```php
// In your package's AppServiceProvider::boot()
$this->app->concord->registerModel(
    \Webkul\Product\Contracts\Product::class,
    \YourVendor\YourPkg\Models\Product::class // must extend Webkul\Product\Models\Product
);
```
After this, every `ProductProxy::find()`, contract type-hint, and repository `model()` resolves to your subclass.

**Repository Pattern**: All database access goes through repositories (`Prettus L5 Repository`, base class `Webkul\Core\Eloquent\Repository`). The repository's `model()` method returns the **Contract class, not the Model** — this preserves the Concord substitution chain. Inject repositories into controllers via constructor; never query Eloquent directly from controllers.

**Proxy usage**: For static model calls and cross-package relationships, use the Proxy (`ProductProxy::find(1)`, `$this->belongsTo(ProductProxy::modelClass())`), never the concrete Model class.

**Event-driven extensibility**: String-identifier events fire at every lifecycle point (`catalog.product.create.after`, `checkout.order.save.after`, `customer.after.login`, `sales.order.update-status.after`, etc.). Listen via `Event::listen('event.name', $cb)` or a package `EventServiceProvider`. Naming convention: `{module}.{entity}.{action}.{timing}`. Full catalog: `compass_bagisto.md` §05.

**View Render Events** — `{!! view_render_event('bagisto.shop.<area>.<position>') !!}` markers embedded in core Blades let you inject Blade fragments without overriding the whole view:
```php
Event::listen('bagisto.shop.products.view.actions.after', function ($manager) {
    $manager->addTemplate('mypkg::shop.products.return-button', ['extra' => 'data']);
});
```
Common hooks: `bagisto.shop.layout.head.{before,after}`, `bagisto.shop.products.view.{gallery,info,actions}.{before,after}`, `bagisto.shop.checkout.{billing,payment}.{before,after}`, `bagisto.shop.customers.account.orders.view.after`, plus `bagisto.admin.*` equivalents.

### Extension Priority Order

When customizing core behavior, prefer (in order):
1. **Event listener** — react to lifecycle events; never touches core code.
2. **View Render Event listener** — inject Blade fragments into core templates.
3. **View override** — drop a same-named Blade under `resources/themes/<theme>/views/...` mirroring the path under `packages/Webkul/Shop/src/Resources/views/...`. Theme views resolve before package views.
4. **Concord model override** — when you must add columns/methods to a core entity.
5. **Repository binding** — `$this->app->bind(CoreRepo::class, MyRepo::class)` in your service provider.
6. **Custom controller + route** — last resort.

**Never edit `packages/Webkul/*` or `vendor/*` directly.** Composer updates will overwrite changes silently.

### Core Helpers

- **`core()`** (`Webkul\Core\Core` facade): channel/currency/locale/config helpers. Common methods:
  - `core()->getCurrentChannel()`, `getCurrentChannelCode()`, `getDefaultChannel()`
  - `core()->getCurrentCurrency()`, `getBaseCurrency()`, `getChannelBaseCurrency()`
  - `core()->getCurrentLocale()`, `getAllLocales()`
  - `core()->formatPrice($price, $code)`, `convertPrice($amount, $target)`, `formatDate(...)`
  - `core()->getConfigData('rma.settings.general.enable', $channel?, $locale?)` — read system config
  - `core()->countries()`, `states()`, `getMaxUploadSize()`, `getSenderEmailDetails()`
- **`bouncer()`**: ACL permission checks. `bouncer()->hasPermission('rma.return-requests.view')` in controllers/Blades.

When processing orders inside jobs/queues, **pass channel/locale explicitly** to `getConfigData()` — there's no request context to infer from.

### Routes

- **Admin routes** (`admin-routes.php`): middleware `['web', 'admin']`, prefixed with `config('app.admin_url')` (configurable via `APP_ADMIN_URL`; do not hardcode `/admin`).
- **Shop routes** (`shop-routes.php`): middleware `['web', 'locale', 'theme', 'currency']`. Custom shop routes MUST include all four — without `theme`, view resolution silently uses the wrong theme.
- Root `routes/web.php` is intentionally minimal — packages own their routing.

### Package Anatomy

```
packages/Webkul/<Package>/src/
├── Config/           # system.php (admin settings), admin-menu.php, acl.php, payment_methods.php, carriers.php
├── Database/         # Migrations/, Seeders/, Factories/
├── DataGrids/        # Admin/ Shop/ DataGrid classes
├── Http/             # Controllers/{Admin,Shop}/, Middleware/, Requests/
├── Models/           # Eloquent models + Proxy classes
├── Repositories/     # Data access layer
├── Contracts/        # Interfaces for models and repositories
├── Resources/
│   ├── views/        # Blade templates (admin/, shop/)
│   ├── lang/         # Localization (translatable strings, 21 locales)
│   └── assets/       # CSS/JS source files
├── Routes/           # admin-routes.php, shop-routes.php, api.php
├── Providers/        # ServiceProvider + ModuleServiceProvider
├── Listeners/
└── Events/
```

**Admin config files** (merged into Laravel config via `mergeConfigFrom` in the service provider's `register()`):
- `admin-menu.php` → `menu.admin` — sidebar/dropdown/tab items, hierarchy via dot-notation in `key`.
- `acl.php` → `acl` — permission keys checked by `bouncer()`.
- `system.php` → `core` — admin Configuration screen fields. Field types: `text, password, integer, boolean, textarea, select, multiselect, image, file, editor, radio, checkbox`. Per-field flags: `validation`, `default_value`, `channel_based`, `locale_based`, `depends:'otherField:value'`.
- `payment_methods.php` → `payment_methods`, `carriers.php` → `carriers` — payment/shipping integrations. The class's `$code` property MUST equal the array key.

### Theme System

Themes declared in `config/themes.php`. Active shop theme: `'shop-default' => '<key>'` (same shape for `'admin-default'`). Each theme entry defines `views_path`, `assets_path`, and Vite `hot_file` + `build_directory` (must be **unique per theme** or assets collide).

Two override paths:
- **Quick iteration**: `resources/themes/<theme>/views/<mirror-of-package-path>.blade.php` overrides the package Blade.
- **Packaged custom theme**: `packages/<YourVendor>/<Theme>/` with its own service provider, Vite config, and `publishes()`. Generator: `php artisan package:make-shop-theme <name> <YourVendor>/Theme`.

Use `<x-shop::layouts>` / `<x-admin::layouts>` and the rich Blade component library (`<x-admin::datagrid>`, `<x-admin::drawer>`, `<x-admin::tinymce>`, `<x-admin::seo>`, `<x-shop::breadcrumbs>`, `<x-shop::tabs>`, `<x-shop::quantity-changer>`, `<x-shop::form.control-group>`, etc.) instead of writing custom HTML.

### Frontend Assets

Admin, Shop, and Installer each have independent Vite builds. Run `npm install` and `npm run dev`/`npm run build` from within the respective package directory:
- **Admin**: `packages/Webkul/Admin/` → `public/themes/admin/default/build/`
- **Shop**: `packages/Webkul/Shop/` → `public/themes/shop/default/build/`
- **Installer**: `packages/Webkul/Installer/`

Vue 3 components are mounted within Blade templates via `@pushOnce('scripts')` / Blade component slots. Bagisto wraps the Vite plugin via the `@bagistoVite([...])` directive (per-theme `hot_file` and `build_directory`).

### Translatable Models

Bagisto uses `astrotomic/laravel-translatable`: a "main" table plus a `_translations` table keyed by `locale` (e.g. `products` + `product_translations`). When adding a translatable field: migrate both tables and list the field in `$translatedAttributes` on the model.

### DataGrid

Admin tables are server-driven via `Webkul\DataGrid\DataGrid`. Implement `prepareQueryBuilder()` (returns a `DB::table()` query) and `prepareColumns()` (`addColumn(['index'=>..., 'label'=>trans(...), 'type'=>..., 'searchable'=>true, ...])`). Optional: `prepareActions()`, `prepareMassActions()`. Render via `<x-admin::datagrid :src="route('admin.your.index')" />`. Reference: `packages/Webkul/Shop/src/DataGrids/OrderDataGrid.php`.

### Naming Conventions

- **Namespace**: `Webkul\<PackageName>` (e.g., `Webkul\Product`)
- **Routes**: Separate `admin-routes.php` and `shop-routes.php` per package
- **Models**: Singular (`Product`, `Category`)
- **Repositories**: `<Model>Repository` (e.g., `ProductRepository`)
- **Controllers**: `<Model>Controller` in `Http/Controllers/Admin/` or `Shop/`

### Adding a New Package

Use the generator (`bagisto/bagisto-package-generator`) — it scaffolds Contract+Model+Proxy+Repository+Migration+Provider in one shot:
```bash
php artisan package:make-package <YourVendor>/<Pkg>
php artisan package:make-model Foo <YourVendor>/<Pkg>          # creates Model + Proxy + Contract
php artisan package:make-admin-controller FooController <YourVendor>/<Pkg>
php artisan package:make-payment-method <YourVendor>/Stripe
php artisan package:make-shipping-method <YourVendor>/FedEx
php artisan package:make-shop-theme <name> <YourVendor>/Theme
```
After scaffolding:
1. Add PSR-4 namespace to root `composer.json` autoload
2. Register ServiceProvider in `bootstrap/providers.php`
3. Register ModuleServiceProvider in `config/concord.php`
4. Run `composer dump-autoload && php artisan optimize:clear`

## Safety Rails

- **Never call `env()` outside `config/` files.** Cached configs (`config:cache`) leave runtime `env()` calls returning `null` in production.
- **Don't casually edit `bootstrap/providers.php` or `config/concord.php`.** Removing a provider de-registers an entire module — controllers, routes, models all silently break.
- **Do not edit**: `vendor/`, `node_modules/`, `composer.lock`, `package-lock.json`, `public/themes/*/build/` (Vite output), `storage/` (runtime caches/logs/compiled views), `*.hot` files (Vite HMR markers), or anything under `packages/Webkul/*` (vendor-managed).
- **Frontend asset edits** under `packages/Webkul/*/src/Resources/assets/` require running `npm run build` from the respective package directory afterward.
- **Don't add or remove composer/npm dependencies without explicit approval.**
- **New entities need all four pieces**: Contract + Model + Proxy + Repository, with the Model registered in the package's `ModuleServiceProvider`.
- **Never run `php artisan db:seed` on a populated install** — it resets settings, channels, categories. Add new default data via your own seeder.

## Non-obvious Pitfalls

- **`checkout.cart.add.after` payload is the whole cart, not the added item.** For just the item, listen to `checkout.cart.collect.totals.after` and inspect the latest line. (See bagisto/bagisto#3971.)
- **ES-indexed products require a running queue worker.** With `QUEUE_CONNECTION != sync`, run `php artisan queue:work` under supervisor or new products won't appear in search.
- **Theme `vite.hot_file` and `build_directory` must be unique per theme.** Reusing the default theme's paths makes both themes share assets and breaks HMR.
- **`composer dump-autoload && php artisan config:cache && php artisan optimize:clear`** after adding a payment method, shipping method, or new package — otherwise it doesn't show up. (#1 forum complaint.)
- **CSRF on admin AJAX**: include `_token` from `<meta name="csrf-token">` on POST/PUT/DELETE.
- **`webpack.mix.js` and the Velocity theme are gone.** Old tutorials referencing Mix/Webpack or Velocity paths are obsolete.
- **`bootstrap/providers.php` is the Laravel 12 location.** Old docs reference `config/app.php`'s `providers` array — it doesn't exist in L11/12.

## CI Pipeline

- **pest_tests.yml**: Pest tests on PHP 8.3 + MySQL 8.0
- **pint_tests.yml**: Code style checks with Laravel Pint
- **admin_playwright_tests.yml / shop_playwright_tests.yml**: E2E tests (6 parallel shards)
- **translation_tests.yml**: Translation file validation
