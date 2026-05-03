# Bagisto 2.4.x Knowledge Base for Claude Code

This research synthesizes Bagisto's official developer documentation (https://devdocs.bagisto.com/), the Bagisto GitHub repository, the Webkul blog, and the Konekt Concord upstream documentation into a structured reference designed to be split into 8–12 markdown files for a Claude Code knowledge base. Bagisto v2.4.x is built on **Laravel 12**, requires **PHP 8.3+**, **Node 23.10+ LTS**, **Composer 2.5+**, and **MySQL 8.0.32+**, and ships with a default storefront theme (`packages/Webkul/Shop`) plus a default admin theme (`packages/Webkul/Admin`) styled with Tailwind CSS, built with Vite, and sprinkled with Vue 3 components.

The intended file split is shown below. Each section is written so it can be lifted whole into the corresponding `.md` file.

---

## 01-bagisto-architecture.md — Concord, Repositories, Packages

**Stack and high-level shape.** Bagisto is a modular Laravel 12 application. Each functional area lives in a Laravel package under `packages/Webkul/<Name>` (Admin, Shop, Core, Customer, Checkout, Sales, Product, Category, Attribute, CartRule, CatalogRule, CMS, BookingProduct, DataGrid, DataTransfer, FPC, GDPR, Inventory, MagicAI, Marketing, Notification, Payment, Paypal, Shipping, Sitemap, SocialLogin, SocialShare, Tax, Theme, User, Installer, DebugBar). Reference: `https://devdocs.bagisto.com/architecture/backend.html`.

**Standard package layout (every package follows this):**
```
packages/Webkul/<Name>/src/
├── Config/                # admin-menu.php, acl.php, system.php, payment_methods.php, carriers.php, product_types.php
├── Console/Commands/
├── Contracts/             # interfaces (e.g. Product.php)
├── Database/{Migrations,Seeders,Factories}/
├── DataGrids/{Admin,Shop}/
├── Events/  Listeners/  Mail/  Notifications/
├── Http/{Controllers/{Admin,Shop},Middleware,Requests}/
├── Models/                # Eloquent models + ModelProxy classes
├── Providers/             # <Name>ServiceProvider.php and ModuleServiceProvider.php
├── Repositories/
├── Resources/{views,lang,assets}/
└── Routes/{admin-routes.php,shop-routes.php}
```

**Concord pattern (Contract + Model + Proxy).** Bagisto uses `konekt/concord` (a 3rd-party modular Laravel package) so any package can declare models that the application or another package can override at runtime without touching core. The triad is:

1. **Contract** — `packages/Webkul/<Name>/src/Contracts/<Entity>.php` is an empty (or method-defining) interface. All type-hints, repository `model()` returns, and relationship definitions reference this interface, never the concrete class.
2. **Model** — `packages/Webkul/<Name>/src/Models/<Entity>.php` extends `Illuminate\Database\Eloquent\Model` and `implements <Entity>Contract`.
3. **Proxy** — `packages/Webkul/<Name>/src/Models/<Entity>Proxy.php` extends `Konekt\Concord\Proxies\ModelProxy`. The proxy provides static access (`Entity Proxy::find()`, `::where()`, `::create()`) which is resolved at runtime to whatever class is currently bound to the contract. This lets you do `BelongsTo` definitions like `return $this->belongsTo(ProductProxy::modelClass());` so the relationship still points at an overridden class.

**Registering models with Concord** — every package has a `ModuleServiceProvider` that extends `Konekt\Concord\BaseModuleServiceProvider` and lists its models:
```php
namespace Webkul\RMA\Providers;
use Konekt\Concord\BaseModuleServiceProvider;
class ModuleServiceProvider extends BaseModuleServiceProvider {
    protected $models = [ \Webkul\RMA\Models\ReturnRequest::class ];
}
```
The provider is then registered in `config/concord.php` under `'modules' => [...]`. `registerModel()` also binds the contract to the implementation in Laravel's container, so `app(ProductContract::class)` gives back an instance of whatever class is currently registered.

**Overriding a core model** (the canonical "do not patch core" pattern):
```php
// In your package's AppServiceProvider::boot()
$this->app->concord->registerModel(
    \Webkul\Product\Contracts\Product::class,
    \YourVendor\YourPkg\Models\Product::class // must extend Webkul\Product\Models\Product
);
```
After this, every `ProductProxy::find()`, every type-hinted contract injection, and every repository that `model()`s the contract receives your subclass.

**Repository pattern (Prettus L5 Repository).** Every package has `Repositories/<Entity>Repository.php` extending `Webkul\Core\Eloquent\Repository` (a thin Bagisto subclass over `Prettus\Repository\Eloquent\BaseRepository`). The only required method is `model()` returning the **contract** path:
```php
class ReturnRequestRepository extends Repository {
    public function model(): string {
        return \Webkul\RMA\Contracts\ReturnRequest::class;
    }
}
```
Available out of the box: `all/find/findWhere/findWhereIn/findWhereBetween/create/update/delete/paginate/with/orderBy/count`, criteria, and Prettus's request-driven filtering. Bagisto code consistently injects repositories into controllers via constructor (`__construct(protected ReturnRequestRepository $r) {}`) and never calls Eloquent directly from controllers. Reference: `https://devdocs.bagisto.com/package-development/repositories.html`.

**Service Providers.** Each package usually has TWO providers:
- `ModuleServiceProvider` (Concord) — registers models with Concord.
- `<Name>ServiceProvider` (Laravel) — `mergeConfigFrom()` for `admin-menu`, `acl`, `core` (system.php), `menu.admin`, `payment_methods`, `carriers`, `product_types`; `loadRoutesFrom`, `loadMigrationsFrom`, `loadViewsFrom($path,'<ns>')`, `loadTranslationsFrom($path,'<ns>')`, `publishes()`.

Register the Laravel provider in `bootstrap/providers.php` (Laravel 11/12 style, no longer in `config/app.php`). Add the namespace to root `composer.json` `psr-4`, then `composer dump-autoload`.

**Doc references:** architecture overview `https://devdocs.bagisto.com/architecture/overview.html`; backend `https://devdocs.bagisto.com/architecture/backend.html`; frontend `https://devdocs.bagisto.com/architecture/frontend.html`; package getting started `https://devdocs.bagisto.com/package-development/getting-started.html`; models `https://devdocs.bagisto.com/package-development/models.html`; repositories `https://devdocs.bagisto.com/package-development/repositories.html`; Konekt Concord upstream `https://konekt.dev/concord/1.3/models`.

---

## 02-extending-bagisto.md — Proper Extension Patterns

**Golden rule:** never modify files inside `packages/Webkul/*` directly — those are vendor-managed and will be overwritten on `composer update`. All customisation lives in your own package(s) under `packages/<YourVendor>/<YourPkg>/` or in your custom theme directory. The Bagisto team explicitly warns about this on the forums and docs ("All your custom code must live inside a package or your changes will be lost on update").

**Use `bagisto/bagisto-package-generator`** to scaffold quickly:
```
composer require bagisto/bagisto-package-generator
php artisan package:make-package YourVendor/YourPkg
php artisan package:make-admin-controller FooController YourVendor/YourPkg
php artisan package:make-shop-controller FooController YourVendor/YourPkg
php artisan package:make-admin-route YourVendor/YourPkg
php artisan package:make-shop-route YourVendor/YourPkg
php artisan package:make-model Foo YourVendor/YourPkg          # creates Model + Proxy + Contract
php artisan package:make-model-proxy FooProxy YourVendor/YourPkg
php artisan package:make-model-contract Foo YourVendor/YourPkg
php artisan package:make-migration create_foo_table YourVendor/YourPkg
php artisan package:make-seeder FooSeeder YourVendor/YourPkg
php artisan package:make-request FooRequest YourVendor/YourPkg
php artisan package:make-middleware FooMiddleware YourVendor/YourPkg
php artisan package:make-datagrid FooDataGrid YourVendor/YourPkg
php artisan package:make-repository FooRepository YourVendor/YourPkg
php artisan package:make-event FooEvent YourVendor/YourPkg
php artisan package:make-listener FooListener YourVendor/YourPkg
php artisan package:make-notification FooNotification YourVendor/YourPkg
php artisan package:make-mail FooMail YourVendor/YourPkg
php artisan package:make-payment-method YourVendor/Stripe
php artisan package:make-shipping-method YourVendor/FedEx
php artisan package:make-vite-config vite.config YourVendor/YourPkg
php artisan package:make-tailwind-config tailwind.config YourVendor/YourPkg
php artisan package:make-shop-theme themeName YourVendor/Theme
php artisan package:make-admin-theme themeName YourVendor/Theme
```
Add `--force` to overwrite. Reference: `https://github.com/bagisto/bagisto-package-generator`.

**Three legitimate extension techniques (in order of preference):**

1. **Event listeners** — hook into Bagisto's string-based events (see `05-events-and-listeners.md`). This is the safest mechanism: you never touch core code; you simply react.
2. **Concord model override** — when you must add columns or methods to a core entity (`Product`, `Customer`, `Order`, `OrderItem`, `Cart`, `CartItem`, `Category`, etc.), subclass the core model and `app->concord->registerModel(Contract::class, MyModel::class)` in your `AppServiceProvider::boot()`. Don't forget Concord also rebinds the interface in the container so type-hints resolve to your class.
3. **View override** — to change a Blade in `packages/Webkul/Shop/src/Resources/views/...` or `packages/Webkul/Admin/...`, place an identically-named Blade at the same relative path under your theme's `views_path` (configured in `config/themes.php`). Laravel resolves theme views first, then falls back to the package view. Same idea applies to email templates: `resources/themes/<theme>/views/emails/orders/created.blade.php` overrides `packages/Webkul/Shop/src/Resources/views/emails/orders/created.blade.php`.

**View Render Events** — Bagisto sprinkles `{!! view_render_event('bagisto.shop.<area>.<position>') !!}` markers throughout core Blades. Listen with:
```php
Event::listen('bagisto.shop.products.view.actions.after', function ($manager) {
    $manager->addTemplate('mypkg::shop.products.return-button', ['extra' => 'data']);
});
```
This injects your Blade fragment into the core template at that exact spot — perfect for adding "Request Return" buttons, banners, badges, etc., without touching core. Common hooks include `bagisto.shop.layout.head.before/after`, `bagisto.shop.products.view.gallery.after`, `bagisto.shop.products.view.info.before/after`, `bagisto.shop.products.view.actions.before/after`, `bagisto.shop.checkout.billing.before/after`, `bagisto.shop.checkout.payment.before/after`, `bagisto.shop.customers.account.orders.view.after`. Reference: `https://devdocs.bagisto.com/advanced/view-render-events.html`.

**Repository override** — extend the core repository, then bind it in the service container: `$this->app->bind(\Webkul\Product\Repositories\ProductRepository::class, \YourVendor\Pkg\Repositories\ProductRepository::class);`.

**Controller override** — define a new route in your package pointing at your controller and (if you must shadow a core URL) `Route::redirect()` the old path or rely on Laravel's last-registered-route-wins resolution. Forums confirm this is the recommended approach (`https://laravel.io/forum/how-to-customize-bagisto-package`).

---

## 03-theme-customization.md — Custom Theme on Top of the Default Shop Theme

**Theme system overview.** All themes (shop and admin) are declared in `config/themes.php`:
```php
return [
    'shop-default' => 'default',                       // active shop theme key
    'shop' => [
        'default' => [
            'name'        => 'Default',
            'assets_path' => 'public/themes/shop/default',
            'views_path'  => 'resources/themes/default/views',
            'vite' => [
                'hot_file'                => 'shop-default-vite.hot',
                'build_directory'         => 'themes/shop/default/build',
                'package_assets_directory'=> 'src/Resources/assets',
            ],
        ],
    ],
    'admin-default' => 'default',
    'admin' => [ /* same shape as 'shop' */ ],
];
```
Reference: `https://devdocs.bagisto.com/theme-development/creating-store-theme.html` and `https://devdocs.bagisto.com/theme-development/creating-admin-theme.html`.

**Recommended path for a B2C single-vendor build: package-based theme that inherits the default Shop layout/components**, since this is what Webkul officially recommends to keep changes upgrade-safe (forum thread `https://forums.bagisto.com/topic/3488/`). Two-stage workflow from the docs:

**Stage A — minimal "resources/themes" theme (fast iteration).**
1. Add a theme entry to `config/themes.php` (e.g. `'custom-theme' => [...]`) and flip `'shop-default' => 'custom-theme'`.
2. Create `resources/themes/custom-theme/views/home/index.blade.php` (and any other Blade you wish to override). The Blade can wrap `<x-shop::layouts>` from the default Shop package to inherit header/footer/menu — this is the easiest way to override only the parts you care about while keeping everything else.
3. The directory tree under `resources/themes/custom-theme/views/` MUST mirror `packages/Webkul/Shop/src/Resources/views/` — same relative paths so Laravel's view resolver picks yours first.
4. `php artisan optimize:clear` and reload.

**Stage B — promote to a real package** (`packages/YourVendor/CustomTheme`):
```
packages/YourVendor/CustomTheme/
├── package.json   tailwind.config.js   vite.config.js   postcss.config.js   composer.json
└── src/
    ├── Providers/CustomThemeServiceProvider.php
    └── Resources/{views,assets/{css/app.css,js/app.js,images}}/
```
Service provider's `boot()` publishes views and language files:
```php
public function boot() {
    $this->publishes([
        __DIR__.'/../Resources/views' => resource_path('themes/custom-theme/views'),
    ], 'views');
}
```
Then:
```
composer dump-autoload
php artisan vendor:publish --provider="YourVendor\CustomTheme\Providers\CustomThemeServiceProvider" --force
php artisan optimize:clear
```
Optionally use a symlink during development so editing `packages/.../views` is reflected immediately: `ln -s $(pwd)/packages/YourVendor/CustomTheme/src/Resources/views resources/themes/custom-theme/views`.

**Vite asset compilation.** Bagisto wraps Laravel's Vite plugin via the `@bagistoVite([...])` Blade directive, which knows about per-theme `hot_file` and `build_directory`. Each theme has its own `vite.config.js` that emits to `public/themes/shop/<theme>/build`. Reference `https://devdocs.bagisto.com/theme-development/vite-powered-theme-assets.html`. Required `package.json` (excerpt): laravel-vite-plugin ^1.0, vite ^5.4, tailwindcss ^3.3, postcss ^8.4, autoprefixer ^10.4, @vitejs/plugin-vue ^4.2, vue ^3.5, vee-validate ^4.9, axios ^1.7, mitt ^3.0. Run `cd packages/YourVendor/CustomTheme && npm install && npm run dev` (or `npm run build` for production); compiled assets land under `public/themes/shop/custom-theme/build`.

**Layouts.** The default Shop layout component is `<x-shop::layouts>` (file: `packages/Webkul/Shop/src/Resources/views/components/layouts/index.blade.php`). It has slots `<x-slot:title>`, exposes `$bodyClass`, `$hasHeader`, `$hasFooter` style flags, and emits the view-render-event hooks listed in section 02. For custom layouts of your own, define `packages/YourVendor/CustomTheme/src/Resources/views/layouts/master.blade.php` and consume it via `<x-custom-theme::layouts.master>`. When you build a fully custom layout you must include `@bagistoVite([...])` yourself for asset loading. Reference: `https://devdocs.bagisto.com/theme-development/understanding-layouts.html`.

**Blade Components.** Bagisto exposes a rich set: `<x-shop::layouts>`, `<x-shop::accordion>`, `<x-shop::breadcrumbs>`, `<x-shop::tabs>`, `<x-shop::flat-picker.date>`, `<x-shop::quantity-changer>`, `<x-shop::shimmer.datagrid>`, `<x-shop::form.control-group...>`, `<x-shop::datagrid :src="route(...)">`, plus admin equivalents `<x-admin::layouts>`, `<x-admin::table>`, `<x-admin::drawer>`, `<x-admin::dropdown>`, `<x-admin::tinymce>`, `<x-admin::seo>`, `<x-admin::datagrid>`. Prefer composing these over writing custom HTML.

**Active theme is per-channel** — Settings → Channels in admin lets you assign a different theme per channel. So a multi-channel install can run several themes simultaneously.

---

## 04-storefront-anatomy.md — Catalog, Cart, Checkout, Customer Account

**Shop package structure** (`packages/Webkul/Shop/src/`):
- `Routes/{shop-routes.php,checkout-routes.php,customer-routes.php}` — middlewares `['web','locale','theme','currency']`.
- `Http/Controllers/{ProductController,CategoryController,Onepage(=Checkout)Controller,CartController,Customer\\*}`.
- `Resources/views/`:
  - `home/index.blade.php`
  - `categories/{index,view}.blade.php`
  - `products/{index,view,...}.blade.php`
  - `checkout/cart/{index,mini-cart}.blade.php`
  - `checkout/onepage.blade.php` and partials (`address`, `shipping`, `payment`, `summary`, `success`)
  - `customers/account/{profile,orders,addresses,wishlist,reviews,downloadable-products}/...`
  - `emails/{layouts.blade.php,orders/{created,canceled,invoiced,shipped,refunded},customers/...}`
  - `components/layouts/index.blade.php` — the `<x-shop::layouts>` master.

**Catalog.** Categories are nested-set (`kalnoy/nestedset`) trees managed in admin; product listing/filter pages live under `/categories/...`. Search uses Elasticsearch when configured (see section below). Product types supported by core: `simple`, `configurable` (variants by attribute combinations), `virtual` (no shipping), `downloadable` (digital files), `grouped`, `bundle`, `booking` (appointments/events/rentals/tables). All extend `Webkul\Product\Type\AbstractType`. To add a custom type, drop a `Config/product_types.php` entry and a class extending `AbstractType` in your package; merge config via `mergeConfigFrom(.../product_types.php, 'product_types')`. Reference: `https://devdocs.bagisto.com/product-type-development/getting-started.html`.

**Cart.** Implementation lives in `packages/Webkul/Checkout/src/Cart.php` (facade `Webkul\Checkout\Facades\Cart`). It dispatches the events listed in section 05 around add/update/remove/collect-totals. The cart and its items are full Concord-overridable models (`Cart`, `CartItem`, `CartAddress`, `CartShippingRate`, `CartPayment`).

**Checkout (one-page).** Default flow (controller: `Webkul\Shop\Http\Controllers\API\OnepageController` + Vue components in `packages/Webkul/Shop/src/Resources/assets/js/.../checkout/`). Sequence of POST endpoints (under `shop.checkout.*` route names): `save-address` → `save-shipping` → `save-payment` → `save-order`. The view that orchestrates these steps is `checkout/onepage.blade.php`. To customise the cart page, override `packages/Webkul/Shop/src/Resources/views/checkout/cart/index.blade.php` (and `mini-cart.blade.php`).

**Customer account.** Routes prefixed `customer/account` rendering `customers/account/...` Blades; each is wrapped in a sidebar layout. Common override targets: `customers/account/orders/index.blade.php` (uses `OrderDataGrid`), `customers/account/addresses/...`, `customers/account/profile/edit.blade.php`, `customers/account/wishlist/index.blade.php`.

**SEO.** Per channel and per category/product: meta title, meta description, meta keywords, URL key (slug). Sitemap package autogenerates XML sitemaps; URL rewrites and search synonyms are managed under Marketing → Search SEO. Recommend enabling pretty URLs, Apache `mod_rewrite`/Nginx try_files. Use `<x-admin::seo>` component in admin to surface SEO fields on custom entities.

**Performance.**
- **Full Page Cache (FPC)** built on `spatie/laravel-responsecache`. Enable with `RESPONSE_CACHE_ENABLED=true`, `RESPONSE_CACHE_LIFETIME=10080`, `RESPONSE_CACHE_DRIVER=file|redis`. Clear with `php artisan responsecache:clear [--url=https://...]`. Bagisto's `Webkul\FPC\Listeners\*` already invalidate per entity event (`catalog.product.update.after`, `customer.review.update.after`, `cms.page.update.after`, etc.). Reference: `https://devdocs.bagisto.com/performance/configure-fpc.html`.
- **Varnish** integration ships as a separate package (`varnish:purge`).
- **Laravel Octane** supported (`/performance/configure-laravel-octane.html`).
- **Elasticsearch** via `elasticsearch/elasticsearch` 8.x; configure `ELASTICSEARCH_HOST=http://localhost:9200`. Indexer reindexes from `product_flat` → ES indices.
- **Image optimization** via `intervention/image` and `bagisto/image-cache`; `php artisan storage:link` is mandatory or product images return 404.

**CMS pages.** Managed via the CMS package; admin → CMS → Pages. URL slug, content (TinyMCE), per-channel/per-locale, optional layout. Static blocks/sliders complement them.

---

## 05-events-and-listeners.md — Event Reference

**How Bagisto dispatches events.** String-identifier dispatch (`Event::dispatch('checkout.cart.add.after', $cart)`) — *not* event-class objects. Listeners are registered either in a package `EventServiceProvider` (`protected $listen = [ 'event.name' => [[Listener::class,'method']] ];` and the EventServiceProvider must be `register()`-ed by your main service provider), or directly via `Event::listen()` in the boot method. Listener methods receive whatever payload the dispatch passed.

**Naming convention** (follow it for your own custom events):
- Core: `{module}.{entity}.{action}.{timing}` — e.g. `catalog.product.create.after`.
- Package: `{package}.{feature}.{action}.{timing}` — e.g. `rma.return.request.created`.

**Catalog & content:** `catalog.attribute.{create,update,delete}.{before,after}`, `catalog.attribute_family.*`, `catalog.category.{create,update,delete}.*`, `catalog.categories.mass-update.*`, `catalog.product.{create,update,delete}.*`, `products.datagrid.sync`, `cms.page.{create,update,delete}.*`.

**Customer:** `customer.registration.{before,after}`, `customer.update.*`, `customer.password.update.after`, `customer.note.create.*`, `customer.subscription.*`, `customer.after.login` (payload: `auth()->guard()->user()`), `customer.after.logout`, `customer.delete.*`, `customer.create.*` (admin-side), `customer.addresses.{create,update,delete}.*`, `customer.customer_group.*`, `customer.review.*`, `customer.compare.*` (`create`, `delete`, `delete-all`), `customer.wishlist.*` (`create`, `delete`, `delete-all`, `move-to-cart`), `customer.rma.request.{create,update}.*`, `customer.gdpr-request.*`.

**Cart & checkout:** `checkout.cart.delete.{before,after}` (item id), `checkout.cart.add.{before,after}` (NB — `before` gets product id, `after` gets the entire cart, not the item; community fix discussed at `https://github.com/bagisto/bagisto/issues/3971`), `checkout.cart.update.*` (item), `checkout.cart.collect.totals.{before,after}` (cart), `checkout.cart.calculate.items.tax.*`, `checkout.cart.calculate.shipping.tax.*`, `checkout.load.index`, `checkout.order.save.{before,after}` (after gets `$order`), `checkout.order.orderitem.save.*`.

**Sales lifecycle:** `sales.order.cancel.*`, `sales.order.update-status.{before,after}` (the order), `sales.invoice.save.*`, `sales.invoice.send_duplicate_email`, `sales.shipment.save.*`, `sales.refund.save.*`, `sales.order.comment.create.*`, `sales.rma.{rma-status,reason,request,rules,custom-field}.*`.

**Promotions:** `promotions.cart_rule.*`, `promotions.catalog_rule.*`, `cart_rules.coupons.delete.*`.

**Marketing/SEO:** `marketing.search_seo.{sitemap,search_synonyms,search_terms,url_rewrites}.*`, `marketing.{campaigns,events,templates}.*`.

**Core:** `core.channel.*`, `core.currency.*`, `core.locale.*`, `core.exchange_rate.*`, `core.configuration.save.{before,after}`. **Inventory:** `inventory.inventory_source.*`. **Tax:** `tax.{category,rate}.*`. **User/Roles:** `user.role.*`, `user.admin.*`, `admin.password.update.after`. **Theme:** `theme_customization.*`. **DataGrid saved filters:** `datagrid.saved_filter.*`. **DataTransfer (imports):** `data_transfer.imports.{create,update,validate,started,linking,indexing,completed}` and `data_transfer.imports.batch.{import,linking,indexing}.{before,after}`. **Booking products:** `booking_product.booking.save.*`, `booking_product.booking.event-ticket.save.*`. **Lifecycle:** `bagisto.installed`.

Full table: `https://devdocs.bagisto.com/advanced/event-listeners.html`.

**Pattern: listener that ships with your package.**
```php
// packages/YourVendor/Pkg/src/Providers/EventServiceProvider.php
class EventServiceProvider extends \Illuminate\Foundation\Support\Providers\EventServiceProvider {
    protected $listen = [
        'checkout.order.save.after' => [[OrderListener::class,'handleOrderCreated']],
        'sales.order.update-status.after' => [[OrderListener::class,'handleStatusChange']],
    ];
}
// In your main PkgServiceProvider::register():
$this->app->register(EventServiceProvider::class);
```
Best practices: keep listeners thin (queue heavy work — set `ShouldQueue`); wrap in try/catch and log structured errors so a listener exception never aborts the order; register critical-path listeners first in the `$listen` array (Laravel runs them in declaration order); chain dependent listeners with `Bus::chain([...])->dispatch()` for asynchronous workflows.

**View Render Events** (separate from data events) — see section 02. They use `Event::listen('bagisto.shop.<area>.<position>', fn($mgr) => $mgr->addTemplate('pkg::view', $data))` and ship in the same hook list as data events but with prefix `bagisto.shop.*` / `bagisto.admin.*`.

---

## 06-admin-customization.md — Menus, ACL, System Configuration

**Admin Menu** — three levels: sidebar item → dropdown sub-items → in-page tabs. Created in `packages/<Pkg>/src/Config/admin-menu.php`:
```php
return [
    ['key'=>'rma',                 'name'=>'rma::app.admin.menu.rma',
     'route'=>'admin.rma.return-requests.index', 'sort'=>100, 'icon'=>'icon-rma'],
    ['key'=>'rma.return-requests', 'name'=>'rma::app.admin.menu.return-requests',
     'route'=>'admin.rma.return-requests.index', 'sort'=>1,  'icon'=>''],
    ['key'=>'rma.settings',        'name'=>'rma::app.admin.menu.settings',
     'route'=>'admin.rma.settings.index',         'sort'=>2,  'icon'=>''],
];
```
Hierarchy comes from dot-notation in `key` (NOT nested arrays). Then `mergeConfigFrom(__DIR__.'/../Config/admin-menu.php','menu.admin')` in your service provider's `register()`. Reference: `https://devdocs.bagisto.com/package-development/menu.html`. Frontend ("shop") nav is generated from product Categories in admin and does not need code.

**ACL (Access Control List)** — Bagisto uses `bouncer()` helper for permission checks. ACL definitions in `packages/<Pkg>/src/Config/acl.php`:
```php
return [
    ['key'=>'rma',                              'name'=>'rma::app.admin.acl.rma',                  'route'=>'admin.rma.return-requests.index', 'sort'=>1],
    ['key'=>'rma.return-requests',              'name'=>'rma::app.admin.acl.return-requests',      'route'=>'admin.rma.return-requests.index', 'sort'=>1],
    ['key'=>'rma.return-requests.view',         'name'=>'rma::app.admin.acl.view',                 'route'=>'admin.rma.return-requests.index', 'sort'=>1],
];
```
`mergeConfigFrom(.../acl.php,'acl')`. In controllers: `if (! bouncer()->hasPermission('rma')) abort(401);`. In Blade: `@if (bouncer()->hasPermission('rma.return-requests.view')) ... @endif`. Roles and Users are managed under admin → Settings → Roles / Users; assigning a role binds it to a tree of these permission keys. Reference: `https://devdocs.bagisto.com/package-development/access-control-list.html`.

**System Configuration** — admin → Configuration; hierarchical 3-level structure (section → group → group → fields). File `packages/<Pkg>/src/Config/system.php`:
```php
return [
    ['key'=>'rma','name'=>'rma::...rma','info'=>'rma::...rma-info','sort'=>1],
    ['key'=>'rma.settings','name'=>'rma::...settings','icon'=>'settings/settings.svg','sort'=>1],
    ['key'=>'rma.settings.general','name'=>'rma::...general','sort'=>1,'fields'=>[
        ['name'=>'enable','title'=>'rma::...enable','type'=>'boolean'],
        ['name'=>'max_return_days','title'=>'rma::...max-days','type'=>'integer',
         'validation'=>'numeric|min:1','depends'=>'enable:1'],
        ['name'=>'default_status','type'=>'select','options'=>[
            ['title'=>'Pending','value'=>'pending'],
            ['title'=>'Approved','value'=>'approved']],
         'channel_based'=>true,'locale_based'=>true],
    ]],
];
```
Field types: `text`, `password`, `integer`, `boolean`, `textarea`, `select`, `multiselect`, `image`/`file`, `editor`, `radio`, `checkbox`. Per-field flags: `validation` (Laravel rules), `default_value`, `channel_based` (true → value stored per channel), `locale_based` (true → per locale), `depends:'otherField:value'` for conditional visibility. `mergeConfigFrom(.../system.php,'core')`.

**Reading config values:**
```php
core()->getConfigData('rma.settings.general.enable');
core()->getConfigData('rma.settings.general.max_return_days', $channelCode, $localeCode);
```
Or in payment/shipping classes: `$this->getConfigData('title')` (with `protected $code='rma_method'` matching the section key prefix, otherwise override `getConfigData()` to compose the full path). Reference: `https://devdocs.bagisto.com/package-development/system-configuration.html`.

**Channels & multi-store.** `Settings → Channels` create distinct storefronts that can vary by domain hostname, theme, locales, currencies, base currency, inventory sources, branding (logo/favicon), maintenance mode, SEO defaults. The `Webkul\Core\Core` class (helper `core()`) caches the current channel/currency/locale per request and exposes `getCurrentChannel()`, `getCurrentChannelCode()`, `getDefaultChannel()`, `getCurrentCurrency()`, `getBaseCurrency()`, `getCurrentLocale()`, `getAllCurrencies()`, `getAllLocales()`, `formatPrice()`, `convertPrice()`, `formatDate()`, `channelTimeStamp()`, `countries()`, `states()`, `getConfigData()`, `getMaxUploadSize()`, `getSenderEmailDetails()`, `getAdminEmailDetails()`. Reference: `https://devdocs.bagisto.com/advanced/understanding-core-class.html`.

**DataGrid** — server-driven tables in admin (`packages/Webkul/DataGrid` package), invoked from Blade via `<x-admin::datagrid :src="route('admin.rma.return-requests.index')" />`. Implement a class extending `Webkul\DataGrid\DataGrid` with `prepareQueryBuilder()` returning a `DB::table()` query and `prepareColumns()` calling `addColumn(['index'=>...,'label'=>trans(...),'type'=>'string|date|integer|boolean|price','searchable'=>true,'filterable'=>true,'sortable'=>true,'closure'=>fn($row)=>...])`. Add row actions with `prepareActions()` and bulk actions with `prepareMassActions()`. Controller returns `$dataGrid->toJson()` for AJAX or a Blade with `<x-admin::datagrid>` for the page shell. Real example: `packages/Webkul/Shop/src/DataGrids/OrderDataGrid.php`. Reference: `https://devdocs.bagisto.com/package-development/datagrid.html`.

---

## 07-payment-shipping-patterns.md — Integration Patterns

**Payment methods.** Built-in: PayPal Smart Buttons (v2 SDK in 2.4), Stripe (added in 2.4 with Checkout Sessions), Razorpay (added in 2.4, drop-in UI), PayU (added in 2.4, redirect flow), and offline (Cash on Delivery, Money Transfer). Each is a tiny package.

**Anatomy of a custom payment package** (`packages/YourVendor/Stripe`):
```
src/
├── Config/{payment_methods.php, system.php}
├── Payment/Stripe.php                 # extends Webkul\Payment\Payment\Payment
├── Routes/web.php                     # optional, for redirect/callback URLs
├── Http/Controllers/StripeController.php (optional)
└── Providers/StripeServiceProvider.php
```
`Config/payment_methods.php`:
```php
return ['stripe' => [
    'code'        => 'stripe',
    'title'       => 'Credit Card (Stripe)',
    'description' => 'Secure card payments via Stripe',
    'class'       => YourVendor\Stripe\Payment\Stripe::class,
    'active'      => true,
    'sort'        => 1,
]];
```
`Config/system.php` exposes admin fields under section key `sales.payment_methods.stripe` (Status boolean, Title text channel/locale-based, Description textarea, API keys password, sort_order, etc.). The Payment class:
```php
class Stripe extends \Webkul\Payment\Payment\Payment {
    protected $code = 'stripe';
    public function getRedirectUrl()      { return route('stripe.process'); /* or null */ }
    public function getAdditionalDetails(){ return ['title'=>$this->getConfigData('title'),
                                                    'description'=>$this->getConfigData('description')]; }
}
```
Service provider `register()`:
```php
$this->mergeConfigFrom(dirname(__DIR__).'/Config/payment_methods.php','payment_methods');
$this->mergeConfigFrom(dirname(__DIR__).'/Config/system.php','core');
```
Then add to `bootstrap/providers.php` and PSR-4 in root `composer.json`. After `composer dump-autoload && php artisan config:cache`, the method appears under admin → Configuration → Sales → Payment Methods. References: `https://devdocs.bagisto.com/payment-method-development/getting-started.html`, `.../create-your-first-payment-method.html`, `.../understanding-payment-class.html`, `.../understanding-payment-configuration.html`. The package generator can scaffold all of this with `php artisan package:make-payment-method YourVendor/Stripe`.

**Shipping methods.** Same shape, different base class — `Webkul\Shipping\Carriers\AbstractShipping`. Files live under `Carriers/` instead of `Payment/`, and the config file is `carriers.php` with key `class => YourVendor\FedEx\Carriers\FedEx::class`. The only required method is `calculate()` which returns one or more `Webkul\Checkout\Models\CartShippingRate` objects (or `false` to disable):
```php
public function calculate() {
    if (! $this->getConfigData('active')) return false;
    $cart = $this->getCart();
    $rate = new CartShippingRate;
    $rate->carrier = $this->getCode();
    $rate->carrier_title = $this->getConfigData('title');
    $rate->method = $this->getCode();
    $rate->method_title = $this->getConfigData('title');
    $rate->price = $cart->sub_total >= 100 ? 0 : 9.99; // free over $100
    $rate->base_price = $rate->price;
    return $rate;
}
```
Common pricing patterns: flat rate (`type=>'per_order'`), per-unit (`type=>'per_unit'`), weight-based (`$cart->weight * $perKg`), threshold free shipping, table-rate keyed by destination postcode/zone, or external API call (DHL/FedEx/UPS) with a fallback rate on failure. Admin field config in `Config/system.php` with section key `sales.carriers.<code>` is identical in shape to payment system.php. Provider: `mergeConfigFrom(.../carriers.php,'carriers')` and `mergeConfigFrom(.../system.php,'core')`. Generator: `php artisan package:make-shipping-method YourVendor/FedEx`. References: `https://devdocs.bagisto.com/shipping-method-development/getting-started.html`, `.../create-your-first-shipping-method.html`, `.../understanding-carrier-class.html`, `.../understanding-carrier-configuration.html`, `.../understanding-system-configuration.html`.

**Things to remember.** The `code` property of the Payment/Carrier class MUST equal the array key in `payment_methods.php`/`carriers.php` — getConfigData() composes paths from it. After creating, run `composer dump-autoload && php artisan config:cache && php artisan optimize:clear` or the new method will not show up (this is the #1 forum complaint).

---

## 08-translations-and-locales.md — Multilingual & Multi-currency

**Locales shipped.** Bagisto core ships with translations across the Admin and Shop packages for ~21 languages including `en`, `ar` (RTL), `de`, `es`, `fa` (RTL), `fr`, `he` (RTL), `hi_IN`, `it`, `ja`, `nl`, `pl`, `pt_BR`, `ru`, `sin`, `tr`, `uk`, `zh_CN`. Admin docs reference the canonical `en` set. Each package keeps its own translations under `src/Resources/lang/<locale>/app.php` returning a deeply-nested array (e.g. `admin.menu.products`, `shop.checkout.cart.title`).

**Adding a locale at runtime.** Admin → Settings → Locales → Create Locale (code, name, direction LTR/RTL, optional locale-specific logo). Then assign it under Settings → Channels → "Locales" / "Default Locale". Customers can switch via the storefront language selector; the value persists in the URL/session and is read by the `locale` middleware on every shop route.

**Adding translations in your package.**
```
packages/YourVendor/Pkg/src/Resources/lang/{en,ar,de,fr,...}/app.php
```
Register in service provider's `boot()`:
```php
$this->loadTranslationsFrom(__DIR__.'/../Resources/lang','pkg');
```
Use in code:
```php
trans('pkg::app.admin.menu.title')
@lang('pkg::app.admin.menu.title')
__('pkg::app.errors.return-window-closed', ['days'=>30])
```
Validation messages: place in `Resources/lang/<locale>/validation.php` and Laravel resolves them by default; for package-specific validation, prefix keys.

**Validation translation in JS** — see `packages/Webkul/Shop/src/Resources/assets/js/lang/locales.js` for the VeeValidate dictionary; add an entry for your locale code and import it in `app.js`.

**Verifying translations** — `php artisan bagisto:translations:check [--locale=fr] [--package=Admin] [--details]` validates translation files across packages against the canonical `en` keys and reports missing/extra keys.

**Multi-currency.** Settings → Currencies (3-letter ISO 4217), then Settings → Exchange Rates (manual or via an automated rate provider you implement). Each Channel has a base currency and a list of allowed currencies; the customer picks via the storefront switcher; checkout converts via `core()->convertPrice()` and stores both `base_*` and presentation currency on order/line items. Helpers: `core()->getBaseCurrency()`, `core()->getBaseCurrencyCode()`, `core()->getCurrentCurrency()`, `core()->getChannelBaseCurrency()`, `core()->formatPrice($price, $currencyCode)`, `core()->convertPrice($amount, $targetCurrency)`. Catalog rules and cart rules support per-currency conditions.

**Translatable Eloquent attributes.** Bagisto uses `astrotomic/laravel-translatable` for entity-level translations: a "main" table (e.g. `products`) plus a "_translations" table (`product_translations` keyed by `locale`) — used for product name/description, category name/description, attribute labels, CMS pages, etc. Override patterns: when adding a translatable field, add migrations to both tables and list the field in `$translatedAttributes` on your model.

Doc references: package localization `https://devdocs.bagisto.com/package-development/localization.html`; user-facing locales `https://docs.bagisto.com/settings/locales.html`; multilingual implementation `https://deepwiki.com/bagisto/bagisto/3.3-implementing-multi-language-support`.

---

## 09-common-pitfalls.md — Things that Break

1. **Don't edit `vendor/` or `packages/Webkul/*` files.** Any composer update will silently overwrite them. Always create your own package or use the theme override path. Several forum threads (1907, 1378, 3488) hammer this home.
2. **Forgetting `composer dump-autoload` after PSR-4 changes** is the #1 reason a new package, payment method, or shipping method "doesn't show up". Always run: `composer dump-autoload && php artisan config:cache && php artisan optimize:clear`.
3. **Not registering the provider** in `bootstrap/providers.php` (Laravel 12 path) — old docs still mention `config/app.php`'s `providers` array, which **does not exist** in Laravel 11/12. Use `bootstrap/providers.php`.
4. **Forgetting the `ModuleServiceProvider`** when registering a model with Concord — your package will load but the model's contract → class binding won't exist and `app(ProductContract::class)` will fail. The ModuleServiceProvider must be listed in `config/concord.php` `'modules'` array.
5. **Proxy vs Model confusion** — register *the actual Model class*, not the Proxy, in `protected $models = [...]`. Use the Proxy from your code (`ReturnRequestProxy::find(1)`) and contract-type-hint in repositories/relationships, not the concrete class.
6. **`checkout.cart.add.after` returns the WHOLE cart, not the added item** — counter to expectations. If you need just the item, listen to `checkout.cart.collect.totals.after` and inspect the latest line, or override `Cart::addProduct()`. See `https://github.com/bagisto/bagisto/issues/3971`.
7. **`php artisan db:seed` on a live install resets settings, channels, and categories.** Never run it after the initial install. Add new default data via your own seeder/migration. The official upgrade guide explicitly warns about this.
8. **Missing `php artisan storage:link`** = product images return 404. Always after install AND after deploying.
9. **Forgetting the `theme` middleware on custom shop routes.** Shop routes require middleware group `['web','locale','theme','currency']` for views to resolve the active theme correctly. Admin routes need `['web','admin']` and the `prefix => config('app.admin_url')`.
10. **Vite hot file path mismatch** — when you add a new theme, the `vite.hot_file` and `build_directory` in `config/themes.php` must be unique per theme; reusing the default theme's paths makes both themes share assets and silently breaks hot reloading.
11. **CSS not updating** — clear `bootstrap/cache/*.php` (or `php artisan optimize:clear`), then `npm run build` in the theme package. During development, run `npm run dev` from inside the theme package directory (not project root).
12. **Admin route prefix** uses `config('app.admin_url')`, which defaults to `admin` but can be changed via `.env` `APP_ADMIN_URL=...` for security through obscurity. Hardcoding `/admin/...` in your code breaks if the customer changes that.
13. **Index permissions on storage** — `storage/framework/cache` can balloon to multi-GB if file driver is used at scale; use `redis` for cache and queues in production. Forum thread 3829 confirms.
14. **Elasticsearch must run a queue worker** — if `QUEUE_CONNECTION` is not `sync`, you need `php artisan queue:work` (or `queue:listen`) running in a supervisor/systemd unit. Without it, products created in admin never make it to ES indices and disappear from search.
15. **`webpack.mix.js` is gone.** Bagisto 2.x switched to Vite. Old tutorials referencing Mix/Webpack are obsolete.
16. **Velocity theme is gone.** It was Bagisto's old default; v2.x ships only one default theme (`packages/Webkul/Shop`). Tutorials still reference Velocity paths — ignore.
17. **Concord cache** — after registering a new model with Concord, `php artisan optimize:clear` is required or the runtime binding may not refresh.
18. **Reaching for `$model::find()` directly** — use the proxy `ModelProxy::find()` so overrides keep working.
19. **CSRF on AJAX** — admin routes are CSRF-protected; include the `_token` from the `<meta name="csrf-token">` tag on all POST/PUT/DELETE.
20. **Multi-channel pitfalls** — `getConfigData('key', $channel, $locale)` falls back to channel-default and then app-default; if you don't pass a channel and you're inside a job/queue context with no request, you may read the wrong value. Always pass the order's channel explicitly when processing orders.

---

## 10-claude-code-workflow.md — How the AI Agent Should Work in This Codebase

**File boundaries — never modify** (unless explicitly told to upgrade Bagisto):
- `vendor/**` — composer-managed.
- `packages/Webkul/**` — Webkul's source; treat as read-only for reference.
- `node_modules/**`.
- `public/themes/**/build/**`, `public/storage/**`, `bootstrap/cache/**` — build/cache outputs.
- `storage/framework/**`, `storage/logs/**`.
- `composer.lock`, `package-lock.json`/`pnpm-lock.yaml` — only modify via `composer require`/`npm install`.
- `.env` — only via documented keys; never commit.

**File boundaries — Claude SHOULD modify:**
- `packages/<YourVendor>/<YourPkg>/**` — the customer's own packages.
- `resources/themes/<custom-theme>/**` — when iterating quickly without packaging.
- `config/themes.php`, `config/concord.php`, `bootstrap/providers.php` — for registering the customer's own theme/package.
- Root `composer.json` (PSR-4 only) and `package.json`.
- `app/Providers/AppServiceProvider.php` for Concord overrides only.

**Pre-flight commands before code changes:**
```
git status            # ensure clean working tree
php artisan optimize:clear
composer dump-autoload
```

**Standard "make a change" loop:**
1. Identify the core path(s) being customised under `packages/Webkul/...`.
2. Decide override mechanism in this priority order: event listener → view override → Concord model override → repository binding → custom controller route.
3. Generate scaffolding with `bagisto/bagisto-package-generator` (`php artisan package:make-...`) under `packages/<YourVendor>/<YourPkg>`.
4. Add namespace to root `composer.json` PSR-4 and the provider to `bootstrap/providers.php`.
5. Run `composer dump-autoload && php artisan config:cache && php artisan optimize:clear`.
6. If touching Vite assets, `cd packages/<YourVendor>/<YourPkg> && npm install && npm run build` (production) or `npm run dev` (watch).
7. Browse to verify (admin = `http://<host>/admin`, shop = `http://<host>/`).
8. For database changes, write a migration in your own package (`Database/Migrations/` autoloaded via `loadMigrationsFrom`). Run `php artisan migrate`. Never edit core migrations.
9. For event listeners, register in your package's `EventServiceProvider::$listen` and `$this->app->register(EventServiceProvider::class)` from the main service provider.

**Useful Bagisto-specific Artisan commands** (`https://github.com/bagisto/bagisto-docs/blob/master/src/advanced/artisan-commands.md`):
- `php artisan bagisto:install` — interactive installer (DB, admin user, seed). On a fresh project only.
- `php artisan bagisto:version` — print installed version.
- `php artisan indexer:index [--type=price|inventory|flat] [--mode=full|selective]` — reindex catalog data (after bulk changes).
- `php artisan product:price-rule:index` — apply catalog rules to the price index.
- `php artisan bagisto:translations:check [--locale=fr] [--package=Admin] [--details]` — verify translations.
- `php artisan responsecache:clear [--url=...]` — purge FPC.
- `php artisan varnish:purge [--url=...]` — purge Varnish.
- `php artisan up`/`down` — Bagisto extends Laravel's maintenance mode to also flag every channel's `is_maintenance_on`.
- `php artisan storage:link` — mandatory after install.
- `php artisan optimize:clear` — clear config/route/view caches together.
- `php artisan queue:work --queue=default,broadcastable` — required for ES indexing, real-time admin notifications, mail jobs.

**Scheduled jobs** — Bagisto registers cron entries for `indexer:index --type=price` and `product:price-rule:index` daily at `00:01`. Production deployment must add the standard Laravel scheduler crontab line: `* * * * * cd /path/to/app && php artisan schedule:run >> /dev/null 2>&1`.

**Use `core()` helpers** instead of writing custom logic for channels/currencies/dates/configs. Use `bouncer()` for permission checks.

**Style/rules to encode in CLAUDE.md:**
- Always inject repositories via constructor; never query Eloquent directly in controllers.
- Always reference contracts in `model()` methods and type hints.
- Always use `<EntityProxy>::method()` for static model calls.
- Always wrap event listeners in try/catch + structured `Log::error()`.
- Always use `trans('<pkg>::app...')` keys, never hardcoded strings.
- Always use Tailwind utility classes consistent with the default theme's `tailwind.config.js`.
- Always use `<x-shop::...>` and `<x-admin::...>` Blade components when one exists.
- Never run `php artisan db:seed` on a populated database.
- Never disable CSRF.
- Never put credentials in code; only in `.env` referenced via `config(...)`.

**Recommended Claude Code knowledge files** (`.claude/skills/` or `CLAUDE.md` import set):
1. This knowledge base split into the 10 files listed at the top of this report.
2. `llms.txt` and `llms-full.txt` from `https://devdocs.bagisto.com/llms.txt` — official AI-optimized index.
3. A small `project.md` with: Bagisto version, custom-theme name, custom-package list, payment/shipping methods enabled, Channels and Locales configured, queue driver, cache driver.

**Verification checklist after every task** (have Claude run and report):
- `php -l <changed files>` — syntax check.
- `composer dump-autoload` — exit code 0.
- `php artisan optimize:clear` — exit code 0.
- `php artisan route:list | grep <new-route>` — confirm route registered.
- For migrations: `php artisan migrate --pretend` first.
- For Vite: confirm `public/themes/shop/<theme>/build/manifest.json` updated.
- Browser smoke test of the affected admin page or shop URL.

**Testing.** Bagisto 2.4 ships Pest and Playwright test cases. Run with `./vendor/bin/pest` (PHP) and `npx playwright test` (E2E).

**Security must-haves before going live** (`https://devdocs.bagisto.com/getting-started/best-security-practice.html`): force HTTPS; restrict admin URL via `APP_ADMIN_URL`; whitelist admin IPs at the WAF; deny PHP execution under `public/storage`; enable 2FA for admin users (new in 2.4); use Google reCAPTCHA Enterprise (replaces v2 in 2.4); rotate keys; keep PHP, MySQL, Redis up to date.

---

## What's New in Bagisto v2.4 (vs 2.3) — quick reference

Per Bagisto's release notes (`https://github.com/bagisto/bagisto/releases`, `https://bagisto.com/en/bagisto-v2-4-0-beta1-laravel-12-new-features/`):
- Upgraded to **Laravel 12**, including new PDF response header format and modernized Carbon-based date helpers.
- **Two-Factor Authentication** for admin users.
- Migration from **Google reCAPTCHA v2 → reCAPTCHA Enterprise**.
- New built-in payment integrations: **Stripe** (Checkout Sessions), **Razorpay** (drop-in UI), **PayU** (redirect flow), and PayPal upgraded from v1 SDK to **v2** with controller-based transaction handling and Laravel HTTP client–based IPN.
- Comprehensive **Return Merchandise Authorization (RMA)** module: customer-initiated returns, admin approval, status tracking, refund/replacement workflow, RMA reasons, RMA rules, RMA custom fields. New events under `customer.rma.request.*` and `sales.rma.*`.
- **Magic AI** refactored to per-provider enums via the Laravel AI SDK (`AiProvider`).
- **SMTP configuration from admin panel** (no longer .env-only).
- Demo products auto-seeded on install.
- Pest & Playwright test suites added.

Note these are statements from the Bagisto release notes — verify version-specific behavior against your installed version with `php artisan bagisto:version` before relying on it.

---

## Source Index (for the Claude agent to look things up)

- Developer docs root: `https://devdocs.bagisto.com/`
- LLMs.txt: `https://devdocs.bagisto.com/llms.txt` and `https://devdocs.bagisto.com/llms-full.txt`
- User docs: `https://docs.bagisto.com/`
- API docs: `https://api-docs.bagisto.com/` (REST + GraphQL)
- Headless docs: `https://headless-doc.bagisto.com/`
- GitHub: `https://github.com/bagisto/bagisto` and docs source `https://github.com/bagisto/bagisto-docs`
- Package generator: `https://github.com/bagisto/bagisto-package-generator`
- Concord upstream: `https://konekt.dev/concord/1.3/models`
- Webkul blog: `https://bagisto.com/en/`
- Forums: `https://forums.bagisto.com/`
- Releases: `https://github.com/bagisto/bagisto/releases`
- Roadmap: `https://bagisto.com/en/roadmap/`