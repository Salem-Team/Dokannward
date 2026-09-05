"use client";

import { StorefrontImage } from "@/components/StorefrontImage";
import type { Testimonial } from "@/lib/api";
import { IconStar, IconStarFill } from "@/components/Icons";
import type { CSSProperties, ReactNode } from "react";
import { useLocale } from "@/context/locale";

/** Crisp filled glyphs for social badges (white on brand color). */
function Glyph({ children }: { children: ReactNode }) {
  return (
    <svg
      className="testimonial-card__social-glyph"
      viewBox="0 0 24 24"
      width="20"
      height="20"
      aria-hidden="true"
      focusable="false"
    >
      {children}
    </svg>
  );
}

const SOCIAL: Record<
  string,
  { label: string; color: string; soft: string; glyph: ReactNode }
> = {
  instagram: {
    label: "Instagram",
    color: "#E1306C",
    soft: "#fce7ef",
    glyph: (
      <Glyph>
        <rect
          x="4.5"
          y="4.5"
          width="15"
          height="15"
          rx="4.25"
          fill="none"
          stroke="currentColor"
          strokeWidth="2"
        />
        <circle
          cx="12"
          cy="12"
          r="3.55"
          fill="none"
          stroke="currentColor"
          strokeWidth="2"
        />
        <circle cx="16.55" cy="7.45" r="1.25" fill="currentColor" />
      </Glyph>
    ),
  },
  facebook: {
    label: "Facebook",
    color: "#1877F2",
    soft: "#e8f1fe",
    glyph: (
      <Glyph>
        <path
          fill="currentColor"
          d="M14.5 8.25h2.1V5.4c-.35-.05-1.55-.15-2.95-.15-2.95 0-4.95 1.8-4.95 5.1v2.4H6.25v3.2h2.45V20.6h3.2v-4.65h2.7l.4-3.2h-3.1V10.5c0-.95.25-1.55 1.6-1.55z"
        />
      </Glyph>
    ),
  },
  tiktok: {
    label: "TikTok",
    color: "#111111",
    soft: "#ececec",
    glyph: (
      <Glyph>
        <path
          fill="currentColor"
          d="M15.9 3.2c.35 1.95 1.65 3.35 3.85 3.7v2.55c-1.35.05-2.6-.35-3.85-1.15v5.85c0 3.15-2.5 5.65-5.65 5.65S4.6 17.3 4.6 14.15s2.5-5.65 5.65-5.65c.3 0 .6.03.9.08v2.7a2.95 2.95 0 0 0-.9-.14c-1.7 0-3.05 1.35-3.05 3.01s1.35 3.01 3.05 3.01 3.05-1.35 3.05-3.01V3.2h2.6z"
        />
      </Glyph>
    ),
  },
  whatsapp: {
    label: "WhatsApp",
    color: "#25D366",
    soft: "#e9f9ef",
    glyph: (
      <Glyph>
        <path
          fill="currentColor"
          d="M12 3.4a8.6 8.6 0 0 0-7.35 13.05L3.4 20.6l4.3-1.12A8.6 8.6 0 1 0 12 3.4zm4.72 12.2c-.2.56-1.15 1.03-1.6 1.1-.41.06-.93.09-1.5-.09-.35-.11-.79-.26-1.36-.51-2.4-1.04-3.96-3.45-4.08-3.61-.12-.16-.97-1.29-.97-2.46s.61-1.75.83-1.99c.2-.22.45-.28.6-.28h.43c.14 0 .32-.05.5.38.2.46.67 1.63.73 1.75.06.12.1.26.02.42-.08.16-.12.26-.24.4-.12.14-.25.31-.36.42-.12.12-.24.25-.1.49.14.24.62 1.02 1.33 1.65.91.81 1.68 1.06 1.92 1.18.24.12.38.1.52-.06.14-.16.6-.7.76-.94.16-.24.32-.2.54-.12.22.08 1.4.66 1.64.78.24.12.4.18.46.28.06.1.06.58-.14 1.14z"
        />
      </Glyph>
    ),
  },
  google: {
    label: "Google",
    color: "#EA4335",
    soft: "#fce8e6",
    glyph: (
      <Glyph>
        <path
          fill="currentColor"
          d="M21.6 12.23c0-.72-.06-1.41-.18-2.07H12v3.92h5.38a4.6 4.6 0 0 1-2 3.02v2.5h3.24c1.9-1.75 2.98-4.33 2.98-7.37z"
        />
        <path
          fill="currentColor"
          d="M12 22c2.7 0 4.96-.9 6.61-2.4l-3.24-2.5c-.9.6-2.05.96-3.37.96-2.59 0-4.78-1.75-5.56-4.1H3.1v2.58A10 10 0 0 0 12 22z"
        />
        <path
          fill="currentColor"
          d="M6.44 13.96A6 6 0 0 1 6.12 12c0-.68.12-1.34.32-1.96V7.46H3.1A10 10 0 0 0 2 12c0 1.61.38 3.13 1.1 4.54l3.34-2.58z"
        />
        <path
          fill="currentColor"
          d="M12 5.94c1.47 0 2.79.5 3.83 1.5l2.87-2.87C16.95 2.94 14.7 2 12 2A10 10 0 0 0 3.1 7.46l3.34 2.58C7.22 7.69 9.41 5.94 12 5.94z"
        />
      </Glyph>
    ),
  },
  x: {
    label: "X",
    color: "#0a0a0a",
    soft: "#ececec",
    glyph: (
      <Glyph>
        <path
          fill="currentColor"
          d="M17.7 3.5h2.55l-5.57 6.37L21.5 20.5h-5.4l-4.23-5.53L7.05 20.5H4.5l5.96-6.81L2.75 3.5h5.54l3.82 5.06L17.7 3.5zm-.9 15.3h1.41L7.45 5.1H5.93l10.87 13.7z"
        />
      </Glyph>
    ),
  },
  youtube: {
    label: "YouTube",
    color: "#FF0000",
    soft: "#ffe5e5",
    glyph: (
      <Glyph>
        <path
          fill="currentColor"
          d="M21.6 8.1a2.7 2.7 0 0 0-1.9-1.9C18.1 5.75 12 5.75 12 5.75s-6.1 0-7.7.45A2.7 2.7 0 0 0 2.4 8.1 28 28 0 0 0 1.95 12a28 28 0 0 0 .45 3.9 2.7 2.7 0 0 0 1.9 1.9c1.6.45 7.7.45 7.7.45s6.1 0 7.7-.45a2.7 2.7 0 0 0 1.9-1.9A28 28 0 0 0 22.05 12a28 28 0 0 0-.45-3.9zM10.1 15.15V8.85L15.55 12l-5.45 3.15z"
        />
      </Glyph>
    ),
  },
};

function initials(name: string) {
  return name
    .split(/\s+/)
    .filter(Boolean)
    .slice(0, 2)
    .map((part) => part[0]?.toUpperCase() ?? "")
    .join("");
}

function hasCustomAvatar(url?: string | null) {
  if (!url?.trim()) return false;
  return !url.includes("ui-avatars.com");
}

function Stars({ rating }: { rating: number }) {
  if (rating <= 0) return null;
  return (
    <div className="testimonial-card__stars" aria-label={`${rating} out of 5`}>
      {Array.from({ length: 5 }).map((_, s) =>
        s < rating ? (
          <IconStarFill key={s} size={11} />
        ) : (
          <IconStar key={s} size={11} />
        ),
      )}
    </div>
  );
}

function SocialBadge({ source }: { source?: string | null }) {
  if (!source) return null;
  const meta = SOCIAL[source];
  if (!meta) return null;
  const { label, color, soft, glyph } = meta;
  const isInstagram = source === "instagram";

  return (
    <span
      className={`testimonial-card__social${isInstagram ? " testimonial-card__social--instagram" : ""}`}
      style={
        {
          ["--social-color"]: color,
          ["--social-soft"]: soft,
        } as CSSProperties
      }
      title={`Via ${label}`}
      aria-label={`Via ${label}`}
    >
      {glyph}
    </span>
  );
}

function Person({
  name,
  role,
  photo,
  source,
}: {
  name: string;
  role: string;
  photo: string | null;
  source?: string | null;
}) {
  return (
    <figcaption className="testimonial-card__meta">
      {photo ? (
        <StorefrontImage
          src={photo}
          alt=""
          width={96}
          height={96}
          className="testimonial-card__photo"
          loading="lazy"
        />
      ) : (
        <span className="testimonial-card__avatar" aria-hidden="true">
          {initials(name)}
        </span>
      )}
      <span className="testimonial-card__who">
        <span className="testimonial-card__name-row">
          <span className="testimonial-card__name">{name}</span>
          <SocialBadge source={source} />
        </span>
        {role ? <span className="testimonial-card__role">{role}</span> : null}
      </span>
    </figcaption>
  );
}

export function TestimonialCard({
  testimonial,
  index,
  featured = false,
}: {
  testimonial: Testimonial;
  index: number;
  featured?: boolean;
}) {
  const { t } = useLocale();
  const rating = Math.min(5, Math.max(0, Math.round(testimonial.rating || 0)));
  const photo = hasCustomAvatar(testimonial.avatar_url)
    ? testimonial.avatar_url!
    : null;
  const role = [testimonial.title, testimonial.company].filter(Boolean).join(" · ");

  return (
    <figure
      className={`testimonial-card${featured ? " testimonial-card--featured" : ""}`}
    >
      <span className="testimonial-card__mark" aria-hidden="true">
        ”
      </span>

      <div className="testimonial-card__top">
        <Stars rating={rating} />
        {testimonial.is_featured ? (
          <span className="testimonial-card__pill">{t("testimonial.featured")}</span>
        ) : (
          <span className="testimonial-card__index" aria-hidden="true">
            {String(index + 1).padStart(2, "0")}
          </span>
        )}
      </div>

      <blockquote className="testimonial-card__content">
        {testimonial.content}
      </blockquote>

      <Person
        name={testimonial.name}
        role={role}
        photo={photo}
        source={testimonial.source}
      />
    </figure>
  );
}
