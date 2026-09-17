# توافق قالب المتجر مع ROOTK (White-Label / Multi-Tenant)

> **المنتج:** Next.js 15 storefront + Laravel 12 admin/API  
> **النشر:** نفس الكود لكل عميل عبر ROOTK — **بدون fork** لكل tenant

---

## الهدف

عندما يغيّر المشغّل في ROOTK Dashboard الاسم / اللوجو / الألوان / الدومين، تنعكس تلقائياً على:

- Storefront (هيدر، عنوان التبويب، 404، PWA manifest، SEO/editorial، `llms.txt`)
- Admin (login، sidebar، dashboard، PDF invoices، CSV export، QR labels)
- إيميلات `mail.from.name` / إعدادات المتجر الافتراضية

---

## البنية

```
defaults محايدة (Default Company)          ← كود فقط، بدون اسم منتج
   ↓
admin/branding/*.json                      ← قيم tenant المعبّأة / مثال محلي
   ↓
platform_brands (is_default=true)
   ↓
.rootk/branding.json + .rootk/tenant.json  ← حقن ROOTK عند Deploy/Sync
   ↓
ROOTK_TENANT_* / NEXT_PUBLIC_ROOTK_TENANT_*  (قراءة حية — لا config:cache)
```

| الطبقة | المسار | الدور |
|--------|--------|-------|
| Manifest | `.rootk/manifest.json` | الوصف الوحيد الذي يقرأه ROOTK |
| Next resolver | `src/lib/tenant-branding.ts` | `getTenantBranding()` |
| Client chrome | `src/lib/brand.ts` | `NEXT_PUBLIC_*` + fallback محايد |
| Laravel | `App\Services\Branding\BrandingService` | أدمن/PDF/mail/export |
| Facade | `App\Support\Branding` | `companyName()`, `exportPrefix()`, `tenantBranding()` |
| Install | `cd admin && php artisan rootk:install` | migrate + seed + sync + clears (**بدون** `config:cache`) |
| Frontend hook | `npm run rootk:install` | يتحقق من الـ manifest + branding markers |

---

## إضافة حقل branding جديد

1. أضفه إلى `.rootk/manifest.json` → `branding.variables` إن كان لوناً.
2. أضفه إلى `admin/branding/*.json` (قيمة tenant أو default).
3. مرّره عبر `RootkBrandingLoader` (`mapBrandingJson` / `mapEnvOverrides`).
4. ادمجه في `BrandingService::buildResolved` و `tenantBranding()`.
5. على الفرونت: أضفه في `TenantBranding` داخل `src/lib/tenant-branding.ts` (+ `BRAND` إن لزم للكلاينت).
6. استهلكه عبر `getTenantBranding()` / `Branding::get()` — **لا hardcode**.
7. أضف اختباراً في `src/lib/tenant-branding.test.ts` و/أو `admin/tests/Feature/RootkBrandingTest.php`.

---

## Markers (Next.js SSR)

في `src/app/layout.tsx` (تظهر في HTML المُصيَّر):

```html
<!-- ROOTK_TENANT_BRANDING_START -->
<style id="rootk-tenant-branding">…</style>
<!-- ROOTK_TENANT_BRANDING_END -->
```

هذا القالب ليس static `dist/` — الـ markers في layout SSR. `npm run rootk:install` يفشل إن اختفت.

---

## أوامر

```bash
cd admin && php artisan rootk:install
npm run rootk:install

# تطوير محلي
npm run dev
cd admin && php artisan serve --port=8001
```

**مهم:** على managed tenants ممنوع الاعتماد على `php artisan config:cache`.

---

## قبول سريع

```bash
test -f .rootk/manifest.json
npm run rootk:install
npm test -- src/lib/tenant-branding.test.ts src/lib/seo.test.ts src/lib/checkout-parity.test.ts

# لا اسم منتج hardcoded في أسطح العرض (الاستثناء: docs / tests / scrape / branding pack):
rg -n 'Zibra|زيبرا' --glob '*.tsx' --glob '*.blade.php' src admin/resources/views
```

`admin/branding/*.json` قد يحتوي اسم tenant الحالي (مثل Zibra) — ROOTK يستبدله عند Deploy لعميل آخر. **الكود** وdefaults الـ NEUTRAL يجب أن تبقى `Default Company` مع لوجو `/images/brand-logo.png`.

عند إضافة حقل branding جديد اتبع القسم أعلاه، ثم حدّث [ROOTK_BRANDING_AUDIT.md](./ROOTK_BRANDING_AUDIT.md).
