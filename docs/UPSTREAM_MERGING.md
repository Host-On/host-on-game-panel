# Upstream Merging

This fork is based on Pterodactyl Panel and is intended to keep pulling changes
from upstream. To make that practical, Host-On-specific code is isolated into
new modules/services rather than scattered through the upstream core.

## Strategy

- Prefer **additive** changes: new tables, models, services, controllers and
  routes.
- Reuse existing service patterns (`handle()`, repositories, transformers).
- Use events/listeners and jobs where possible.
- Isolate branding (config + a handful of view/composer files).
- Avoid mass refactors, formatting changes and unrelated edits.

## Modified upstream files (complete list)

Minimal, surgical edits to upstream files:

| File | Change |
| --- | --- |
| `app/Models/Node.php` | Added `type` column support (`static`/`managed`), `TYPE_*` constants, `type` in `$fillable`/`$casts`/rules. |
| `config/app.php` | Default application name → `Host-On.Games`. |
| `app/Http/ViewComposers/AssetComposer.php` | Serve Host-On branding from `config/hoston.php`. |
| `routes/api-application.php` | Added `/hoston/services` routes. |
| `routes/api-client.php` | Added `/hoston/catalog` + `/hoston/order` routes. |
| `app/Providers/RouteServiceProvider.php` | Load `routes/hoston.php`. |
| `database/Seeders/DatabaseSeeder.php` | Call `HostOnDemoSeeder`. |
| `resources/views/layouts/admin.blade.php` | Branding + Infrastructure sidebar entry. |
| `resources/views/templates/wrapper.blade.php` | Branding default. |
| `resources/scripts/...` (several) | Rebranding, Games catalog route/UI. |
| `.env.example` | `MAIL_FROM_NAME` branding. |

## New files (all additive)

- `app/Models/Infrastructure*`, `ComputeInstance`, `GameService`,
  `GameCatalogEntry`, `ResourceProfile`, `ProvisioningJob`, `ProvisioningStep`,
  `BootstrapToken`, `InfrastructureAuditLog`.
- `app/Services/Infrastructure/**` (provider abstraction, Proxmox + Fake
  providers, placement engine, provisioning orchestrator, lifecycle).
- `app/Contracts/Infrastructure/InfrastructureProviderInterface.php`.
- `app/Jobs/ProcessProvisioningJob.php`.
- `app/Http/Controllers/Api/Application/HostOn/ServiceController.php`.
- `app/Http/Controllers/Api/Client/HostOnOrderController.php`.
- `app/Http/Controllers/Api/HostOn/BootstrapController.php`.
- `app/Http/Controllers/Admin/HostOnController.php`.
- `app/Exceptions/Infrastructure/InfrastructureException.php`.
- `config/hoston.php`.
- `routes/hoston.php`.
- `database/migrations/2025_08_20_*`.
- `database/Seeders/HostOnDemoSeeder.php`.
- `resources/views/admin/hoston/*`.
- `tests/Unit/Services/Infrastructure/*`,
  `tests/Integration/Services/Infrastructure/*`,
  `tests/Integration/Api/Application/HostOn/*`.

## Merging upstream

1. `git fetch upstream && git merge upstream/1.0-develop`.
2. Resolve conflicts in the modified-upstream files listed above (they are
   small, additive edits).
3. Run `composer install`, `php artisan migrate`, `yarn install`, `yarn build`.
4. Run the full test suite.

Because the Host-On code is isolated in its own namespaces/directories, most
upstream changes will merge cleanly.
