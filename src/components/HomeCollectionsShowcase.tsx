import { StorefrontImage } from "@/components/StorefrontImage";
import Link from "next/link";
import {
  getCollectionCategories,
  getCollectionProductList,
  hrefForStorefrontCollection,
  type StorefrontCollection,
} from "@/lib/catalog";
import { IMAGE_QUALITY } from "@/lib/media";
import { Reveal } from "@/components/Reveal";
import { IconArrowRight } from "@/components/Icons";
import { CollectionsAnimatedStories } from "@/components/CollectionsAnimatedStories";
import { getServerLocale } from "@/lib/i18n/server";
import { t } from "@/lib/i18n";

export type EditorialCollection = {
  handle: string;
  title: string;
  href: string;
  image: string;
  description: string | null;
  products_count: number;
  categories: string[];
  previews: { src: string; alt: string }[];
};

const PREVIEW_LIMIT = 3;
const HOME_COLLECTION_LIMIT = 6;

/**
 * Enrich root collections with category names + product previews
 * so each story can speak for the edit — not just a thumbnail grid.
 * `lite` skips product-list scans and uses the collection cover only.
 */
export async function buildEditorialCollections(
  collections: StorefrontCollection[],
  limit = HOME_COLLECTION_LIMIT,
  options: { lite?: boolean } = {},
): Promise<EditorialCollection[]> {
  const selected = collections.slice(0, limit);
  const lite = options.lite === true;

  return Promise.all(
    selected.map(async (c) => {
      if (lite) {
        const image = c.image?.trim() || "";
        if (!image) return null;
        return {
          handle: c.handle,
          title: c.display_title || c.title,
          href: hrefForStorefrontCollection(c),
          image,
          description: c.description?.trim() || null,
          products_count: c.products_count,
          categories: [],
          previews: [],
        };
      }

      const [children, products] = await Promise.all([
        getCollectionCategories(c.handle),
        getCollectionProductList(c.handle),
      ]);

      const previews = products
        .filter((p) => Boolean(p.image))
        .slice(0, PREVIEW_LIMIT)
        .map((p) => ({
          src: p.image as string,
          alt: p.title,
        }));

      const childCover =
        children.find((ch) => ch.image?.trim())?.image?.trim() || "";
      const image = c.image?.trim() || previews[0]?.src || childCover || "";
      if (!image) return null;

      return {
        handle: c.handle,
        title: c.display_title || c.title,
        href: hrefForStorefrontCollection(c),
        image,
        description: c.description?.trim() || null,
        products_count: c.products_count,
        categories: children
          .map((ch) => ch.display_title || ch.title)
          .slice(0, 5),
        previews,
      };
    }),
  ).then((rows) => rows.filter((row): row is EditorialCollection => row != null));
}

/**
 * Homepage + index editorial — each collection as a full story plate:
 * cover, narrative, categories inside the edit, and quiet product previews.
 */
export async function HomeCollectionsShowcase({
  collections,
  eyebrow,
  title,
  lede,
  showViewAll = true,
  headingLevel = 2,
  variant = "home",
}: {
  collections: EditorialCollection[];
  eyebrow?: string;
  title?: string;
  lede?: string;
  showViewAll?: boolean;
  headingLevel?: 1 | 2;
  variant?: "home" | "index";
}) {
  if (collections.length === 0) return null;

  const locale = await getServerLocale();
  const kindLabel = t(locale, "home.collection.kind");
  const viewAll = t(locale, "collections.viewAll");
  const resolvedEyebrow = eyebrow ?? t(locale, "collections.eyebrow");
  const resolvedTitle = title ?? t(locale, "collections.title");
  const resolvedLede = lede ?? t(locale, "collections.lede");
  const HeadingTag = headingLevel === 1 ? "h1" : "h2";

  return (
    <section
      className={`home-collections content-auto${variant === "index" ? " home-collections--index" : ""}`}
      aria-labelledby="home-collections-heading"
    >
      <div className="home-collections__inner">
        <Reveal>
          <header className="home-collections__head container">
            <p className="home-collections__eyebrow">{resolvedEyebrow}</p>
            <div className="home-collections__head-row">
              <HeadingTag
                id="home-collections-heading"
                className="heading home-collections__title"
              >
                {resolvedTitle}
              </HeadingTag>
              {showViewAll ? (
                <Link href="/collections" className="home-collections__all">
                  {viewAll}
                  <IconArrowRight size={12} />
                </Link>
              ) : null}
            </div>
            <p className="home-collections__lede">{resolvedLede}</p>
          </header>
        </Reveal>

        {variant === "index" ? (
          <CollectionsAnimatedStories collections={collections} />
        ) : (
          <div className="home-collections__stories">
            {collections.map((collection, i) => (
              <CollectionStory
                key={collection.handle}
                collection={collection}
                index={i}
                priority={i === 0}
                kindLabel={kindLabel}
                insideLabel={t(locale, "a11y.insideCollection")}
                shopLabel={t(locale, "home.collection.shop")}
                pieceLabel={t(locale, "home.collection.piece")}
                piecesLabel={(count) =>
                  t(locale, "home.collection.pieces", { count })
                }
                descCategories={(categories) =>
                  t(locale, "home.collection.descCategories", { categories })
                }
                descDefault={t(locale, "home.collection.descDefault")}
              />
            ))}
          </div>
        )}
      </div>
    </section>
  );
}

function CollectionStory({
  collection,
  index,
  priority = false,
  kindLabel,
  insideLabel,
  shopLabel,
  pieceLabel,
  piecesLabel,
  descCategories,
  descDefault,
}: {
  collection: EditorialCollection;
  index: number;
  priority?: boolean;
  kindLabel: string;
  insideLabel: string;
  shopLabel: string;
  pieceLabel: string;
  piecesLabel: (count: number) => string;
  descCategories: (categories: string) => string;
  descDefault: string;
}) {
  const flipped = index % 2 === 1;
  const number = String(index + 1).padStart(2, "0");
  const countLabel =
    collection.products_count === 1
      ? pieceLabel
      : piecesLabel(collection.products_count);

  const description =
    collection.description ||
    (collection.categories.length > 0
      ? descCategories(collection.categories.join(", "))
      : descDefault);

  return (
    <article
      className={`home-collections__story${flipped ? " home-collections__story--flip" : ""}`}
    >
      <Reveal
        variant="mask"
        delay={Math.min(index * 40, 160)}
        className="home-collections__media-reveal"
      >
        <Link
          href={collection.href}
          prefetch
          className="home-collections__cover"
          aria-label={`${collection.title} — ${countLabel}`}
        >
          <StorefrontImage
            src={collection.image}
            alt=""
            fill
            sizes="(max-width: 900px) 100vw, 58vw"
            quality={IMAGE_QUALITY.hero}
            priority={priority}
            loading={priority ? "eager" : "lazy"}
            className="home-collections__cover-image media-mono"
          />
          <div className="home-collections__cover-veil" aria-hidden="true" />
          <span className="home-collections__cover-index" aria-hidden="true">
            {number}
          </span>
        </Link>
      </Reveal>

      <Reveal delay={Math.min(80 + index * 40, 200)} className="home-collections__panel-reveal">
        <div className="home-collections__panel">
          <p className="home-collections__kind">{kindLabel}</p>
          <h3 className="heading home-collections__name">
            <Link href={collection.href} prefetch>
              {collection.title}
            </Link>
          </h3>

          <p className="home-collections__desc">{description}</p>

          {collection.categories.length > 0 ? (
            <ul className="home-collections__facets" aria-label={insideLabel}>
              {collection.categories.map((name) => (
                <li key={name} className="home-collections__facet">
                  {name}
                </li>
              ))}
            </ul>
          ) : null}

          <div className="home-collections__meta">
            <span className="home-collections__count">{countLabel}</span>
            <Link href={collection.href} prefetch className="home-collections__cta">
              {shopLabel}
              <IconArrowRight size={12} />
            </Link>
          </div>

          {collection.previews.length > 0 ? (
            <div className="home-collections__previews" aria-hidden="true">
              {collection.previews.map((preview, j) => (
                <div key={`${collection.handle}-p-${j}`} className="home-collections__preview">
                  <StorefrontImage
                    src={preview.src}
                    alt=""
                    fill
                    sizes="120px"
                    quality={IMAGE_QUALITY.card}
                    className="home-collections__preview-image media-mono"
                  />
                </div>
              ))}
            </div>
          ) : null}
        </div>
      </Reveal>
    </article>
  );
}
