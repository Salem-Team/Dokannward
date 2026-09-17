import type { NextConfig } from "next";
import { existsSync, readFileSync } from "node:fs";
import { join } from "node:path";

/** Load ROOTK-injected tenant env before Next evaluates NEXT_PUBLIC_* / branding. */
function loadRootkEnvFiles() {
  for (const rel of [".rootk/tenant.env", ".rootk/deployment.env"]) {
    const path = join(process.cwd(), rel);
    if (!existsSync(path)) continue;
    for (const line of readFileSync(path, "utf8").split("\n")) {
      const trimmed = line.trim();
      if (!trimmed || trimmed.startsWith("#")) continue;
      const match =
        trimmed.match(/^([A-Z0-9_]+)='([^']*)'/) ||
        trimmed.match(/^([A-Z0-9_]+)="([^"]*)"/) ||
        trimmed.match(/^([A-Z0-9_]+)=(.*)$/);
      if (!match) continue;
      const key = match[1];
      const value = match[2] ?? "";
      if (
        (key.startsWith("ROOTK_") || key.startsWith("NEXT_PUBLIC_ROOTK_")) &&
        !process.env[key]
      ) {
        process.env[key] = value;
      }
    }
  }
}

loadRootkEnvFiles();

const apiUrl = process.env.NEXT_PUBLIC_API_URL || "http://localhost:8001/api";
let apiHostname = "localhost";
let apiPort: string | undefined = "8001";
let apiProtocol: "http" | "https" = "http";

try {
  const parsed = new URL(apiUrl);
  apiHostname = parsed.hostname;
  apiPort = parsed.port || undefined;
  apiProtocol = parsed.protocol.replace(":", "") as "http" | "https";
} catch {
  // keep defaults
}

const nextConfig: NextConfig = {
  distDir: process.env.NEXT_DIST_DIR || ".next",
  poweredByHeader: false,
  compress: true,
  reactStrictMode: true,

  // Node 22 + Next's WasmHash can crash mid-build on a stale/.corrupt cache
  // (`Cannot read properties of undefined (reading 'length')`). Prefer a
  // pure-JS hasher so `npm run build` / `test:all` stay reliable.
  webpack: (config) => {
    config.output = config.output ?? {};
    // sha256 avoids the intermittent WasmHash crash seen with xxhash64/wasm.
    config.output.hashFunction = "sha256";
    return config;
  },

  // Lint runs via `npm run lint` (flat ESLint 9). Skipping the in-build lint
  // step avoids FlatCompat/circular-config noise during `next build`.
  eslint: {
    ignoreDuringBuilds: true,
  },

  // Hide the on-screen Next.js route/dev indicator in `next dev`.
  // (The empty <nextjs-portal> is Next’s own tooling — not storefront UI.)
  // Build/runtime errors still surface; production builds never include this.
  devIndicators: false,

  // Prefer modern JS output — smaller client bundles on evergreen browsers.
  compiler: {
    removeConsole: process.env.NODE_ENV === "production" ? { exclude: ["error", "warn"] } : false,
  },

  images: {
    formats: ["image/avif", "image/webp"],
    deviceSizes: [640, 750, 828, 1080, 1200, 1920],
    imageSizes: [64, 96, 128, 256, 384],
    /**
     * Next.js 15+ rejects /_next/image with HTTP 400 when `quality` is not listed.
     * Keep a wide allow-list so editorial/card qualities never brick the storefront.
     * Canonical app values live in `src/lib/media.ts` (75 card / 80 pdp / 85 hero).
     */
    qualities: [50, 60, 70, 75, 80, 85, 90, 92, 95, 100],
    minimumCacheTTL: 60 * 60 * 24 * 30,
    // Allow cache-busting query strings on local public assets (e.g. hero ?v5).
    localPatterns: [{ pathname: "/images/**" }, { pathname: "/**" }],
    remotePatterns: [
      {
        protocol: apiProtocol,
        hostname: apiHostname,
        ...(apiPort ? { port: apiPort } : {}),
        pathname: "/**",
      },
      // Local Laravel asset hosts (APP_URL may be 127.0.0.1 while API_URL is localhost).
      {
        protocol: "http",
        hostname: "127.0.0.1",
        port: "8000",
        pathname: "/**",
      },
      {
        protocol: "http",
        hostname: "localhost",
        port: "8000",
        pathname: "/**",
      },
      {
        protocol: "http",
        hostname: "127.0.0.1",
        port: "8001",
        pathname: "/**",
      },
      {
        protocol: "http",
        hostname: "localhost",
        port: "8001",
        pathname: "/**",
      },
      {
        protocol: "https",
        hostname: "ui-avatars.com",
        pathname: "/api/**",
      },
      {
        protocol: "https",
        hostname: "logo.clearbit.com",
        pathname: "/**",
      },
      {
        protocol: "https",
        hostname: "dokannward.com",
        pathname: "/**",
      },
      {
        protocol: "https",
        hostname: "cdn.shopify.com",
        pathname: "/**",
      },
    ],
  },

  // Ensure admin branding JSON + ROOTK overlays are available when
  // getTenantBranding() reads files at build/SSG time.
  outputFileTracingIncludes: {
    "/*": ["./admin/branding/**/*", "./.rootk/**/*"],
  },

  experimental: {
    staleTimes: {
      dynamic: 300,
      static: 900,
    },
  },

  headers: async () => [
    {
      source: "/fonts/:path*",
      headers: [
        {
          key: "Cache-Control",
          value: "public, max-age=31536000, immutable",
        },
      ],
    },
    {
      source: "/images/:path*",
      headers: [
        {
          key: "Cache-Control",
          value: "public, max-age=31536000, immutable",
        },
      ],
    },
    {
      source: "/_next/static/:path*",
      headers: [
        {
          key: "Cache-Control",
          value: "public, max-age=31536000, immutable",
        },
      ],
    },
    {
      source: "/_next/image",
      headers: [
        {
          key: "Cache-Control",
          value: "public, max-age=31536000, immutable",
        },
      ],
    },
    {
      source: "/:path*",
      headers: [
        { key: "X-DNS-Prefetch-Control", value: "on" },
        { key: "X-Content-Type-Options", value: "nosniff" },
        { key: "Referrer-Policy", value: "strict-origin-when-cross-origin" },
        { key: "X-Frame-Options", value: "SAMEORIGIN" },
        {
          key: "Permissions-Policy",
          value: "camera=(), microphone=(), geolocation=(self), payment=()",
        },
        {
          key: "Strict-Transport-Security",
          value: "max-age=63072000; includeSubDomains; preload",
        },
      ],
    },
  ],

  // Proxy Laravel public disk so relative `/storage/…` works from the Next origin
  // even when APP_URL omits the artisan port (common local misconfig).
  rewrites: async () => {
    const origin =
      localApiOrigin() ||
      (() => {
        try {
          return new URL(apiUrl).origin;
        } catch {
          return "http://127.0.0.1:8001";
        }
      })();
    return [
      {
        source: "/storage/:path*",
        destination: `${origin}/storage/:path*`,
      },
    ];
  },

  // Build-time redirects for brand slugs that used to live under /collections/*
  redirects: async () => {
    const base = (process.env.NEXT_PUBLIC_API_URL || "http://127.0.0.1:8000/api").replace(
      /\/$/,
      "",
    );
    try {
      const res = await fetch(`${base}/brands?per_page=200`, {
        headers: { Accept: "application/json" },
      });
      if (!res.ok) return [];
      const json = (await res.json()) as { data?: Array<{ slug?: string }> };
      const items = Array.isArray(json?.data) ? json.data : [];
      return items
        .map((b) => b.slug)
        .filter((slug): slug is string => Boolean(slug))
        .map((slug) => ({
          source: `/collections/${slug}`,
          destination: `/brands/${slug}`,
          permanent: true,
        }));
    } catch {
      return [];
    }
  },
};

function localApiOrigin(): string | null {
  try {
    const parsed = new URL(apiUrl);
    if (!/^(localhost|127\.0\.0\.1|0\.0\.0\.0|\[::1\])$/i.test(parsed.hostname)) {
      return null;
    }
    return parsed.origin;
  } catch {
    return null;
  }
}

export default nextConfig;
