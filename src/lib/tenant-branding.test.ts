import { afterEach, describe, expect, it } from "vitest";
import {
  clearTenantBrandingCache,
  getTenantBranding,
  brandingCssVariables,
} from "@/lib/tenant-branding";

describe("getTenantBranding", () => {
  afterEach(() => {
    clearTenantBrandingCache();
    delete process.env.NEXT_PUBLIC_ROOTK_TENANT_DISPLAY_NAME;
    delete process.env.ROOTK_TENANT_DISPLAY_NAME;
    delete process.env.NEXT_PUBLIC_ROOTK_TENANT_LOGO_URL;
    delete process.env.NEXT_PUBLIC_ROOTK_TENANT_PRIMARY_COLOR;
    delete process.env.NEXT_PUBLIC_ROOTK_TENANT_BRAND_COLORS;
  });

  it("uses packaged tenant branding from admin/branding files", () => {
    const b = getTenantBranding();
    expect(b.displayName).toBe("Dokan Ward");
    expect(b.displayNameAr).toBe("دكان ورد");
    expect(b.websiteTitle).toContain("Dokan Ward");
    expect(b.websiteTitleAr).toContain("دكان ورد");
    expect(b.colors.primary_color).toMatch(/^#/);
  });

  it("overrides display name from ROOTK env without clobbering SEO title", () => {
    process.env.NEXT_PUBLIC_ROOTK_TENANT_DISPLAY_NAME = "Acme Atelier";
    clearTenantBrandingCache();
    const b = getTenantBranding();
    expect(b.displayName).toBe("Acme Atelier");
    expect(b.websiteTitle).toContain("Dokan Ward");
  });

  it("overrides logo and primary color from ROOTK env", () => {
    process.env.NEXT_PUBLIC_ROOTK_TENANT_LOGO_URL = "https://cdn.example/logo.png";
    process.env.NEXT_PUBLIC_ROOTK_TENANT_PRIMARY_COLOR = "#1180ee";
    clearTenantBrandingCache();
    const b = getTenantBranding();
    expect(b.logoLightUrl).toBe("https://cdn.example/logo.png");
    expect(b.colors.primary_color).toBe("#1180ee");
  });

  it("maps ROOTK hex-array brand colors onto primary/secondary/accent", () => {
    process.env.NEXT_PUBLIC_ROOTK_TENANT_BRAND_COLORS = JSON.stringify([
      "#532904",
      "#debcad",
      "#3D2E26",
    ]);
    clearTenantBrandingCache();
    const b = getTenantBranding();
    expect(b.colors.primary_color).toBe("#532904");
    expect(b.colors.secondary_color).toBe("#debcad");
    expect(b.colors.accent_color).toBe("#3D2E26");
    expect(brandingCssVariables(b)).toContain("--brand-primary: #532904");
  });
});
