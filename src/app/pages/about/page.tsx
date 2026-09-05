import { StorefrontImage } from "@/components/StorefrontImage";
import Link from "next/link";
import { getSiteContent, getStoreSettings } from "@/lib/catalog";
import { fetchPage } from "@/lib/api";
import { BRAND } from "@/lib/brand";
import { mapsHref } from "@/lib/contact";
import { Reveal } from "@/components/Reveal";
import { StoreVisit } from "@/components/StoreVisit";
import { Multiline } from "@/lib/multiline";
import {
  IconCraft,
  IconDiscover,
  IconOrigin,
  IconShipping,
  IconSparkle,
} from "@/components/Icons";
import { getServerLocale } from "@/lib/i18n/server";
import { localizeCmsText } from "@/lib/i18n";

const PILLAR_ICONS = [IconSparkle, IconCraft, IconOrigin] as const;
const JOURNEY_ICONS = [IconDiscover, IconSparkle, IconShipping] as const;
const JOURNEY_KEYS = [
  { title: "about.journey.select", text: "about.journey.selectText" },
  { title: "about.journey.style", text: "about.journey.styleText" },
  { title: "about.journey.deliver", text: "about.journey.deliverText" },
] as const;

/** Keep "Story" capitalized in About headlines. */
function withCapitalStory(text: string) {
  return text.replace(/\bstory\b/gi, "Story");
}

function resolveAboutHeroImage(src?: string | null) {
  const image = src?.trim() || "";
  if (!image) return BRAND.heroImage;
  // Remap known legacy scrape assets only — never override a real admin upload.
  if (
    image.includes("handbags-and-accessories-natural") ||
    image.includes("hero-editorial")
  ) {
    return BRAND.heroImage;
  }
  return image;
}

export default async function AboutPage() {
  const locale = await getServerLocale();
  const [store, content, aboutPage] = await Promise.all([
    getStoreSettings(),
    getSiteContent(),
    fetchPage("about"),
  ]);
  const about = content.about;
  const heroTitle = withCapitalStory(
    localizeCmsText(locale, about.hero_title, "about.hero.title"),
  );
  const storyTitle = withCapitalStory(
    localizeCmsText(
      locale,
      aboutPage?.title || about.story_title,
      "about.story.title",
    ),
  );
  const heroImage = resolveAboutHeroImage(about.hero_image);
  const maps = mapsHref(store.maps_url, store.address);

  return (
    <>
      <section className="about-hero relative bg-[var(--color-black)] text-[var(--color-white)]">
        <div className="about-hero__media relative w-full overflow-hidden">
          <StorefrontImage
            src={heroImage}
            alt=""
            fill
            priority
            className="object-cover object-center media-mono opacity-55"
            sizes="100vw"
          />
          <div className="absolute inset-0 bg-gradient-to-t from-black via-black/40 to-black/20" />
          <div className="absolute inset-0 flex items-end">
            <div className="relative container about-hero__copy text-center">
              <p className="text-[11px] uppercase tracking-[0.22em] opacity-70 mb-3 animate-fade-up">
                {localizeCmsText(locale, about.hero_eyebrow, "about.hero.eyebrow")}
              </p>
              <h1
                className="heading about-hero__title mb-3 animate-fade-up"
                style={{ animationDelay: "80ms" }}
              >
                <Multiline text={heroTitle} />
              </h1>
              <p
                className="about-hero__subtitle opacity-85 max-w-xl mx-auto text-left leading-relaxed animate-fade-up"
                style={{ animationDelay: "160ms" }}
              >
                {localizeCmsText(
                  locale,
                  about.hero_subtitle,
                  "about.hero.subtitle",
                )}
              </p>
            </div>
          </div>
        </div>
      </section>

      <section className="container about-story">
        <div className="about-story__grid grid md:grid-cols-2 gap-12 md:gap-16 items-center">
          <Reveal variant="zebra">
            <div className="about-story__mark">
              <StorefrontImage
                src={about.story_image || store.logo || BRAND.logo}
                alt={store.name || BRAND.name}
                width={1400}
                height={380}
                className="about-story__logo"
                sizes="(max-width: 768px) 96vw, 52vw"
                priority={false}
              />
            </div>
          </Reveal>
          <div>
            <Reveal>
              <p className="about-story__eyebrow">
                {localizeCmsText(
                  locale,
                  about.story_eyebrow,
                  "about.story.eyebrow",
                )}
              </p>
              <h2 className="heading about-story__title mb-7">{storyTitle}</h2>
            </Reveal>
            <Reveal delay={100}>
              {aboutPage?.content ? (
                <div
                  className="about-story__body rte"
                  dangerouslySetInnerHTML={{ __html: aboutPage.content }}
                />
              ) : (
                <div className="about-story__body">
                  {about.story_paragraphs.map((p, i) => (
                    <p key={p.slice(0, 32)}>
                      {localizeCmsText(
                        locale,
                        p,
                        `about.story.p${i + 1}`,
                      )}
                    </p>
                  ))}
                </div>
              )}
            </Reveal>
          </div>
        </div>
      </section>

      <section className="bg-[var(--color-black)] text-[var(--color-white)]">
        <div className="container py-16 md:py-20 text-center max-w-3xl mx-auto">
          <Reveal>
            <div className="zebra-strip mb-10 opacity-90" aria-hidden />
            <blockquote className="heading text-2xl md:text-4xl leading-snug tracking-tight">
              {localizeCmsText(locale, about.quote, "about.quote")}
            </blockquote>
            <p className="mt-6 text-xs uppercase tracking-[0.2em] opacity-50">
              {about.quote_attribution}
            </p>
          </Reveal>
        </div>
      </section>

      <section className="container py-16 md:py-24">
        <Reveal>
          <p className="text-[11px] uppercase tracking-[0.2em] opacity-50 mb-3 text-center">
            {localizeCmsText(
              locale,
              about.pillars_eyebrow,
              "about.pillars.eyebrow",
            )}
          </p>
          <h2 className="heading text-3xl md:text-4xl text-center mb-12 md:mb-16">
            {localizeCmsText(locale, about.pillars_title, "about.pillars.title")}
          </h2>
        </Reveal>
        <div className="grid md:grid-cols-3 gap-10 md:gap-12">
          {about.pillars.map((item, i) => {
            const Icon = PILLAR_ICONS[i % PILLAR_ICONS.length];
            return (
              <Reveal key={`${item.title}-${i}`} delay={i * 90}>
                <div className="about-pillar">
                  <span className="about-pillar__icon" aria-hidden="true">
                    <Icon size={18} />
                  </span>
                  <span className="about-pillar__index">0{i + 1}</span>
                  <h3 className="heading about-pillar__title">
                    {localizeCmsText(
                      locale,
                      item.title,
                      `about.pillars.${i}.title`,
                    )}
                  </h3>
                  <p className="about-pillar__text">
                    {localizeCmsText(
                      locale,
                      item.text,
                      `about.pillars.${i}.text`,
                    )}
                  </p>
                </div>
              </Reveal>
            );
          })}
        </div>
      </section>

      <section className="bg-[var(--color-paper)] border-y border-black/5">
        <div className="container py-16 md:py-24">
          <Reveal>
            <p className="text-[11px] uppercase tracking-[0.2em] opacity-50 mb-3 text-center">
              {localizeCmsText(
                locale,
                about.journey_eyebrow,
                "about.journey.eyebrow",
              )}
            </p>
            <h2 className="heading text-3xl md:text-4xl mb-12 md:mb-16 text-center">
              {localizeCmsText(
                locale,
                about.journey_title,
                "about.journey.title",
              )}
            </h2>
          </Reveal>
          <div className="grid md:grid-cols-3 gap-8 md:gap-10">
            {about.journey.map((item, i) => {
              const Icon = JOURNEY_ICONS[i % JOURNEY_ICONS.length];
              const keys = JOURNEY_KEYS[i] ?? JOURNEY_KEYS[0];
              const step = String(i + 1).padStart(2, "0");
              return (
                <Reveal key={`${item.title}-${i}`} delay={i * 100}>
                  <div className="about-step">
                    <div className="about-step__head">
                      <span className="about-step__icon" aria-hidden="true">
                        <Icon size={17} />
                      </span>
                      <span className="about-step__num">{step}</span>
                    </div>
                    <h3 className="heading about-step__title">
                      {localizeCmsText(locale, item.title, keys.title)}
                    </h3>
                    <p className="about-step__text">
                      {localizeCmsText(locale, item.text, keys.text)}
                    </p>
                  </div>
                </Reveal>
              );
            })}
          </div>
        </div>
      </section>

      <section className="relative overflow-hidden">
        <div className="absolute inset-0 bg-[var(--color-black)]" />
        <div className="relative container py-16 md:py-20 text-[var(--color-white)] text-center">
          <Reveal>
            <p className="text-[11px] uppercase tracking-[0.2em] opacity-50 mb-4">
              {localizeCmsText(locale, about.cta_eyebrow, "about.cta.eyebrow")}
            </p>
            <h2 className="heading text-3xl md:text-5xl mb-3">
              {localizeCmsText(
                locale,
                store.address_label,
                "about.cta.location",
              )}
            </h2>
            {store.address_label_ar ? (
              <p
                className="text-lg md:text-xl opacity-70 mb-5"
                dir="rtl"
                lang="ar"
              >
                {store.address_label_ar}
              </p>
            ) : null}
            {store.address || store.address_ar || maps ? (
              <div className="about-visit">
                <StoreVisit
                  store={store}
                  maps={maps}
                  tone="onDark"
                  heading={null}
                  showHeadline={false}
                />
              </div>
            ) : null}
            <p className="opacity-60 text-sm mb-8">
              {[store.email, store.phone].filter(Boolean).join(" · ")}
            </p>
            <div className="flex flex-wrap gap-3 justify-center">
              <Link href="/pages/contact" className="btn-on-dark">
                <span>
                  {localizeCmsText(
                    locale,
                    about.cta_primary_label,
                    "about.cta.primary",
                  )}
                </span>
              </Link>
              <Link
                href="/collections/all"
                className="btn-on-dark btn-on-dark--ghost"
              >
                <span>
                  {localizeCmsText(
                    locale,
                    about.cta_secondary_label,
                    "about.cta.secondary",
                  )}
                </span>
              </Link>
            </div>
          </Reveal>
        </div>
      </section>
    </>
  );
}
