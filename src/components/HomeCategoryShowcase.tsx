import Link from "next/link";
import type { FeaturedStorefrontCategory } from "@/lib/catalog";
import { IconArrowRight } from "@/components/Icons";
import { Reveal } from "@/components/Reveal";
import { t } from "@/lib/i18n";
import { getServerLocale } from "@/lib/i18n/server";
import {
  categoryPublicImageFallback,
  storefrontImageSrc,
} from "@/lib/media";

const MAX_PLATES = 9;

/**
 * Homepage category grid — compact cover tiles with Zibra-style entrance motion.
 */
export async function HomeCategoryShowcase({
  categories,
}: {
  categories: FeaturedStorefrontCategory[];
}) {
  if (categories.length === 0) return null;

  const locale = await getServerLocale();
  const plates = dedupeCategories(categories)
    .filter((c) => Boolean(c.image?.trim()))
    .slice(0, MAX_PLATES);

  if (plates.length === 0) return null;

  return (
    <section
      className="home-category"
      aria-labelledby="home-category-heading"
    >
      <div className="container home-category__inner">
        <Reveal variant="mask">
          <header className="home-category__head">
            <p className="home-category__eyebrow">
              {t(locale, "home.category.eyebrow")}
            </p>
            <div className="home-category__head-row">
              <h2 id="home-category-heading" className="heading home-category__title">
                {t(locale, "home.category.title")}
              </h2>
              <Link href="/collections" className="home-category__all">
                {t(locale, "home.category.viewAll")}
                <IconArrowRight size={12} />
              </Link>
            </div>
            <p className="home-category__lede">
              {t(locale, "home.category.lede")}
            </p>
          </header>
        </Reveal>

        <ul className="home-category__grid">
          {plates.map((category, i) => (
            <li key={category.handle} className="home-category__tile">
              <Reveal
                delay={Math.min(i * 70, 420)}
                className="home-category__tile-reveal"
              >
                <Link
                  href={category.href}
                  prefetch
                  className="home-category__tile-link"
                >
                  <span className="home-category__tile-media" aria-hidden="true">
                    {/* eslint-disable-next-line @next/next/no-img-element -- Laravel /storage skips the optimizer */}
                    <img
                      className="home-category__tile-image"
                      src={
                        categoryPublicImageFallback(category.image) ||
                        storefrontImageSrc(category.image) ||
                        category.image
                      }
                      alt=""
                      loading="lazy"
                      decoding="async"
                      draggable={false}
                    />
                    <span className="home-category__tile-shine" />
                    <span className="home-category__tile-stripe" />
                  </span>
                  <span className="home-category__tile-veil" aria-hidden="true" />
                  <span className="home-category__tile-meta">
                    <span className="home-category__tile-title heading" dir="auto">
                      {category.title}
                    </span>
                    {category.products_count > 0 ? (
                      <span className="home-category__tile-count">
                        {t(locale, "home.category.pieces", {
                          count: category.products_count,
                        })}
                      </span>
                    ) : null}
                  </span>
                </Link>
              </Reveal>
            </li>
          ))}
        </ul>
      </div>
    </section>
  );
}

function dedupeCategories(
  categories: FeaturedStorefrontCategory[],
): FeaturedStorefrontCategory[] {
  const seen = new Set<string>();
  const out: FeaturedStorefrontCategory[] = [];
  for (const category of categories) {
    const key = category.handle || category.href;
    if (seen.has(key)) continue;
    seen.add(key);
    out.push(category);
  }
  return out;
}
