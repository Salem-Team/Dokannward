# ROOTK branding audit (Dokannward template)

## Fixed (user-facing)

| Area | Was | Now |
|------|-----|-----|
| Order numbers | `ZBR-######` | `Branding::exportPrefix()` → e.g. `DW-######` |
| Return numbers | `ZBR-R-######` | `{prefix}-R-######` |
| Product SKU auto | `ZBR-XXXXXX` | `{prefix}-XXXXXX` |
| Orders CSV | `dokan-ward-orders-…` | `{slug(exportPrefix)}-orders-…` |
| OrderSuccess / CheckoutEmpty | Invisible cream wordmark / Zibra risk | Tenant seal + `brand.name` |
| Admin login / sidebar / layout / invoice / QR | Hardcoded Dokan Ward / paths | `$brandDisplayName` / `$brandLogoUrl` |
| Storefront layout | Static `BRAND` only | `getTenantBranding()` + ROOTK markers |

## Stack added

- `.rootk/manifest.json`
- `src/lib/tenant-branding.ts` + tests
- `App\Services\Branding\*` + `App\Support\Branding`
- `platform_brands` migration + seeder
- `php artisan rootk:install` / `npm run rootk:install`
- `docs/ROOTK_WHITE_LABEL.md`

## Remaining (non-UI / fixtures)

- Test SKUs still use `ZBR-*` as opaque fixture IDs (not rendered as product name).
- `admin/package.json` name `korfdya` (internal package id).
- CSS class names `zibra-*` / comments — visual tokens, not displayed copy.
