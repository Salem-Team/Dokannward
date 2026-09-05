import type { ReactNode, SVGProps } from "react";

type IconProps = SVGProps<SVGSVGElement> & {
  size?: number;
  title?: string;
};

const defaults = {
  fill: "none",
  stroke: "currentColor",
  strokeWidth: 1.25,
  strokeLinecap: "round" as const,
  strokeLinejoin: "round" as const,
};

function IconBase({
  size = 18,
  title,
  children,
  className = "",
  ...props
}: IconProps & { children: ReactNode }) {
  return (
    <svg
      width={size}
      height={size}
      viewBox="0 0 24 24"
      aria-hidden={title ? undefined : true}
      role={title ? "img" : "presentation"}
      className={`icon ${className}`.trim()}
      {...defaults}
      {...props}
    >
      {title ? <title>{title}</title> : null}
      {children}
    </svg>
  );
}

/** Brand sparkle — matches the Dokan Ward star mark */
export function IconSparkle({ size = 14, ...props }: IconProps) {
  return (
    <IconBase size={size} {...props}>
      <path
        d="M12 2.5l1.55 6.2L20 10l-6.45 1.3L12 17.5l-1.55-6.2L4 10l6.45-1.3L12 2.5z"
        fill="currentColor"
        stroke="none"
      />
    </IconBase>
  );
}

export function IconMenu({ size = 18, ...props }: IconProps) {
  return (
    <IconBase size={size} {...props}>
      <path d="M4 7.25h16" />
      <path d="M4 12h16" />
      <path d="M4 16.75h16" />
    </IconBase>
  );
}

export function IconHome({ size = 18, ...props }: IconProps) {
  return (
    <IconBase size={size} {...props}>
      <path d="M4.5 10.75 12 4.75l7.5 6" />
      <path d="M7 10v8.5h10V10" />
      <path d="M10 18.5v-4.25h4V18.5" />
    </IconBase>
  );
}

export function IconGrid({ size = 18, ...props }: IconProps) {
  return (
    <IconBase size={size} {...props}>
      <rect x="4.5" y="4.5" width="6" height="6" rx="1" />
      <rect x="13.5" y="4.5" width="6" height="6" rx="1" />
      <rect x="4.5" y="13.5" width="6" height="6" rx="1" />
      <rect x="13.5" y="13.5" width="6" height="6" rx="1" />
    </IconBase>
  );
}

export function IconInfo({ size = 18, ...props }: IconProps) {
  return (
    <IconBase size={size} {...props}>
      <circle cx="12" cy="12" r="7.5" />
      <path d="M12 10.75v5" />
      <path d="M12 8.1h.01" />
    </IconBase>
  );
}

export function IconSearch({ size = 18, ...props }: IconProps) {
  return (
    <IconBase size={size} {...props}>
      <circle cx="11" cy="11" r="6.25" />
      <path d="M20 20l-3.35-3.35" />
    </IconBase>
  );
}

export function IconAccount({ size = 18, ...props }: IconProps) {
  return (
    <IconBase size={size} {...props}>
      <circle cx="12" cy="8" r="3.25" />
      <path d="M5.5 19.25c1.35-2.9 3.7-4.35 6.5-4.35s5.15 1.45 6.5 4.35" />
    </IconBase>
  );
}

export function IconBag({ size = 18, ...props }: IconProps) {
  return (
    <IconBase size={size} {...props}>
      <path d="M7.5 8.5h9l-.75 10.25a1.25 1.25 0 0 1-1.25 1.15H9.5a1.25 1.25 0 0 1-1.25-1.15L7.5 8.5z" />
      <path d="M9.25 8.5V7.4a2.75 2.75 0 0 1 5.5 0V8.5" />
    </IconBase>
  );
}

export function IconSpinner({ size = 18, className = "", ...props }: IconProps) {
  return (
    <IconBase size={size} className={`icon-spin ${className}`.trim()} {...props}>
      <path d="M12 3.25a8.75 8.75 0 1 1-8.75 8.75" />
    </IconBase>
  );
}

export function IconClose({ size = 18, ...props }: IconProps) {
  return (
    <IconBase size={size} {...props}>
      <path d="M6.5 6.5l11 11" />
      <path d="M17.5 6.5l-11 11" />
    </IconBase>
  );
}

export function IconPlus({ size = 16, ...props }: IconProps) {
  return (
    <IconBase size={size} {...props}>
      <path d="M12 6.5v11" />
      <path d="M6.5 12h11" />
    </IconBase>
  );
}

export function IconMinus({ size = 16, ...props }: IconProps) {
  return (
    <IconBase size={size} {...props}>
      <path d="M6.5 12h11" />
    </IconBase>
  );
}

export function IconCheck({ size = 16, ...props }: IconProps) {
  return (
    <IconBase size={size} {...props}>
      <path d="M5 12.5l4.2 4.2L19 7" />
    </IconBase>
  );
}

export function IconHeart({
  size = 16,
  filled = false,
  ...props
}: IconProps & { filled?: boolean }) {
  return (
    <IconBase
      size={size}
      fill={filled ? "currentColor" : "none"}
      strokeWidth={filled ? 0 : 1.25}
      {...props}
    >
      <path d="M12 20.25S3.75 15.1 3.75 9.6A3.85 3.85 0 0 1 7.7 5.75c1.45 0 2.7.72 3.3 1.82.6-1.1 1.85-1.82 3.3-1.82a3.85 3.85 0 0 1 3.95 3.85c0 5.5-8.25 10.65-8.25 10.65Z" />
    </IconBase>
  );
}

export function IconHeartEmpty({ size = 40, ...props }: IconProps) {
  return (
    <IconBase size={size} strokeWidth={1} {...props}>
      <path d="M12 20.25S3.75 15.1 3.75 9.6A3.85 3.85 0 0 1 7.7 5.75c1.45 0 2.7.72 3.3 1.82.6-1.1 1.85-1.82 3.3-1.82a3.85 3.85 0 0 1 3.95 3.85c0 5.5-8.25 10.65-8.25 10.65Z" />
    </IconBase>
  );
}

export function IconCopy({ size = 16, ...props }: IconProps) {
  return (
    <IconBase size={size} {...props}>
      <rect x="8.5" y="8.5" width="10" height="10" rx="1.5" />
      <path d="M15.5 8.5V6.75A1.25 1.25 0 0 0 14.25 5.5H6.75A1.25 1.25 0 0 0 5.5 6.75v7.5A1.25 1.25 0 0 0 6.75 15.5H8.5" />
    </IconBase>
  );
}

export function IconChevronDown({ size = 16, ...props }: IconProps) {
  return (
    <IconBase size={size} {...props}>
      <path d="M6.5 9.5L12 15l5.5-5.5" />
    </IconBase>
  );
}

export function IconChevronLeft({ size = 16, ...props }: IconProps) {
  return (
    <IconBase size={size} {...props}>
      <path d="M14.5 6.5L9 12l5.5 5.5" />
    </IconBase>
  );
}

export function IconChevronRight({ size = 16, ...props }: IconProps) {
  return (
    <IconBase size={size} {...props}>
      <path d="M9.5 6.5L15 12l-5.5 5.5" />
    </IconBase>
  );
}

export function IconArrowRight({ size = 16, ...props }: IconProps) {
  return (
    <IconBase size={size} {...props}>
      <path d="M5 12h13.5" />
      <path d="M13.5 6.5L19 12l-5.5 5.5" />
    </IconBase>
  );
}

export function IconTrash({ size = 16, ...props }: IconProps) {
  return (
    <IconBase size={size} {...props}>
      <path d="M5 7.5h14" />
      <path d="M9.5 7.5V6.25A1.25 1.25 0 0 1 10.75 5h2.5A1.25 1.25 0 0 1 14.5 6.25V7.5" />
      <path d="M8 7.5l.7 11.1A1.25 1.25 0 0 0 9.95 19.75h4.1a1.25 1.25 0 0 0 1.25-1.15L16 7.5" />
    </IconBase>
  );
}

export function IconPhone({ size = 16, ...props }: IconProps) {
  return (
    <IconBase size={size} {...props}>
      <path d="M8.2 4.75h2.1l1.05 3.15-1.35 1.1a10.5 10.5 0 0 0 4.9 4.9l1.1-1.35 3.15 1.05v2.1a1.4 1.4 0 0 1-1.45 1.45A13.75 13.75 0 0 1 4.75 6.2a1.4 1.4 0 0 1 1.45-1.45z" />
    </IconBase>
  );
}

export function IconMail({ size = 16, ...props }: IconProps) {
  return (
    <IconBase size={size} {...props}>
      <rect x="3.75" y="5.75" width="16.5" height="12.5" rx="1.5" />
      <path d="M4.5 7.5L12 12.75 19.5 7.5" />
    </IconBase>
  );
}

export function IconFacebook({ size = 18, ...props }: IconProps) {
  return (
    <IconBase size={size} {...props}>
      <path d="M14.5 8.25h2.1V5.4c-.35-.05-1.55-.15-2.95-.15-2.95 0-4.95 1.8-4.95 5.1v2.4H6.25v3.2h2.45V20.6h3.2v-4.65h2.7l.4-3.2h-3.1V10.5c0-.95.25-1.55 1.6-1.55z" />
    </IconBase>
  );
}

export function IconInstagram({ size = 18, ...props }: IconProps) {
  return (
    <IconBase size={size} {...props}>
      <rect x="3.75" y="3.75" width="16.5" height="16.5" rx="4.5" />
      <circle cx="12" cy="12" r="3.75" />
      <circle cx="17.1" cy="6.9" r="0.9" fill="currentColor" stroke="none" />
    </IconBase>
  );
}

export function IconTikTok({ size = 18, ...props }: IconProps) {
  return (
    <IconBase size={size} fill="currentColor" stroke="none" {...props}>
      <path d="M15.9 3.2c.35 1.95 1.65 3.35 3.85 3.7v2.55c-1.35.05-2.6-.35-3.85-1.15v5.85c0 3.15-2.5 5.65-5.65 5.65S4.6 17.3 4.6 14.15s2.5-5.65 5.65-5.65c.3 0 .6.03.9.08v2.7a2.95 2.95 0 0 0-.9-.14c-1.7 0-3.05 1.35-3.05 3.01s1.35 3.01 3.05 3.01 3.05-1.35 3.05-3.01V3.2h2.6z" />
    </IconBase>
  );
}

export function IconWhatsApp({ size = 18, ...props }: IconProps) {
  return (
    <IconBase size={size} {...props}>
      <path d="M12 3.75a8.25 8.25 0 0 0-7.05 12.55L3.75 20.25l4.1-1.08A8.25 8.25 0 1 0 12 3.75z" />
      <path d="M9.45 8.9h1.55l.7 2.05-.95.75a6.7 6.7 0 0 0 3.15 3.15l.75-.95 2.05.7v1.45a.95.95 0 0 1-.98.95 9 9 0 0 1-8.2-8.2.95.95 0 0 1 .93-.95z" />
    </IconBase>
  );
}

export function IconGoogle({ size = 18, ...props }: IconProps) {
  return (
    <IconBase size={size} fill="currentColor" stroke="none" {...props}>
      <path d="M21.6 12.23c0-.72-.06-1.41-.18-2.07H12v3.92h5.38a4.6 4.6 0 0 1-2 3.02v2.5h3.24c1.9-1.75 2.98-4.33 2.98-7.37z" />
      <path d="M12 22c2.7 0 4.96-.9 6.61-2.4l-3.24-2.5c-.9.6-2.05.96-3.37.96-2.59 0-4.78-1.75-5.56-4.1H3.1v2.58A10 10 0 0 0 12 22z" />
      <path d="M6.44 13.96A6 6 0 0 1 6.12 12c0-.68.12-1.34.32-1.96V7.46H3.1A10 10 0 0 0 2 12c0 1.61.38 3.13 1.1 4.54l3.34-2.58z" />
      <path d="M12 5.94c1.47 0 2.79.5 3.83 1.5l2.87-2.87C16.95 2.94 14.7 2 12 2A10 10 0 0 0 3.1 7.46l3.34 2.58C7.22 7.69 9.41 5.94 12 5.94z" />
    </IconBase>
  );
}

export function IconX({ size = 18, ...props }: IconProps) {
  return (
    <IconBase size={size} fill="currentColor" stroke="none" {...props}>
      <path d="M17.7 3.5h2.55l-5.57 6.37L21.5 20.5h-5.4l-4.23-5.53L7.05 20.5H4.5l5.96-6.81L2.75 3.5h5.54l3.82 5.06L17.7 3.5zm-.9 15.3h1.41L7.45 5.1H5.93l10.87 13.7z" />
    </IconBase>
  );
}

export function IconYouTube({ size = 18, ...props }: IconProps) {
  return (
    <IconBase size={size} fill="currentColor" stroke="none" {...props}>
      <path d="M21.6 8.1a2.7 2.7 0 0 0-1.9-1.9C18.1 5.75 12 5.75 12 5.75s-6.1 0-7.7.45A2.7 2.7 0 0 0 2.4 8.1 28 28 0 0 0 1.95 12a28 28 0 0 0 .45 3.9 2.7 2.7 0 0 0 1.9 1.9c1.6.45 7.7.45 7.7.45s6.1 0 7.7-.45a2.7 2.7 0 0 0 1.9-1.9A28 28 0 0 0 22.05 12a28 28 0 0 0-.45-3.9zM10.1 15.15V8.85L15.55 12l-5.45 3.15z" />
    </IconBase>
  );
}

/** Authenticity / guarantee seal */
export function IconBadge({ size = 18, ...props }: IconProps) {
  return (
    <IconBase size={size} {...props}>
      <path d="M12 3.5 14.2 5l2.55.35-1.65 2.05.2 2.55L12 9.2l-3.3.75.2-2.55L7.25 5.35 9.8 5 12 3.5z" />
      <path d="M8.25 12.25 10.5 14.5l5.25-5.5" />
      <path d="M6.5 14.75h11" opacity="0.45" />
      <path d="M7.75 17.25h8.5" opacity="0.35" />
    </IconBase>
  );
}

export function IconEye({ size = 16, ...props }: IconProps) {
  return (
    <IconBase size={size} {...props}>
      <path d="M2.75 12s3.4-6 9.25-6 9.25 6 9.25 6-3.4 6-9.25 6-9.25-6-9.25-6z" />
      <circle cx="12" cy="12" r="2.4" />
    </IconBase>
  );
}

export function IconBagEmpty({ size = 40, ...props }: IconProps) {
  return (
    <IconBase size={size} strokeWidth={1.1} {...props}>
      <path d="M7.5 8.5h9l-.75 10.25a1.25 1.25 0 0 1-1.25 1.15H9.5a1.25 1.25 0 0 1-1.25-1.15L7.5 8.5z" />
      <path d="M9.25 8.5V7.4a2.75 2.75 0 0 1 5.5 0V8.5" />
      <path d="M9.5 14.25h5" opacity="0.45" />
    </IconBase>
  );
}

/** Returns / exchanges — refined circular arrow */
export function IconReturn({ size = 18, ...props }: IconProps) {
  return (
    <IconBase size={size} strokeWidth={1.25} {...props}>
      <path d="M5 12a7 7 0 1 0 2-4.95" />
      <path d="M5 5.25V9.5h4.25" />
    </IconBase>
  );
}

/** Shipping / delivery — refined parcel */
export function IconShipping({ size = 18, ...props }: IconProps) {
  return (
    <IconBase size={size} strokeWidth={1.25} {...props}>
      <path d="M4 8.25h16v9.5H4z" />
      <path d="M4 8.25l8 4.25 8-4.25" />
      <path d="M12 12.5v5.25" />
    </IconBase>
  );
}

/** Origin / craftsmanship — refined globe */
export function IconOrigin({ size = 18, ...props }: IconProps) {
  return (
    <IconBase size={size} strokeWidth={1.25} {...props}>
      <circle cx="12" cy="12" r="7.5" />
      <path d="M4.5 12h15" />
      <path d="M12 4.5c2.15 2.35 3.25 4.85 3.25 7.5S14.15 17.15 12 19.5C9.85 17.15 8.75 14.65 8.75 12S9.85 6.85 12 4.5z" />
    </IconBase>
  );
}

export function IconLock({ size = 16, ...props }: IconProps) {
  return (
    <IconBase size={size} {...props}>
      <rect x="5.5" y="10.5" width="13" height="9.25" rx="1.25" />
      <path d="M8.25 10.5V8.4a3.75 3.75 0 0 1 7.5 0v2.1" />
    </IconBase>
  );
}

export function IconStar({ size = 14, ...props }: IconProps) {
  return (
    <IconBase size={size} {...props}>
      <path d="M12 3.6l2.05 4.55 4.95.5-3.75 3.3 1.1 4.85L12 14.55 7.65 16.8l1.1-4.85-3.75-3.3 4.95-.5L12 3.6z" />
    </IconBase>
  );
}

export function IconStarFill({ size = 14, ...props }: IconProps) {
  return (
    <IconBase size={size} fill="currentColor" stroke="none" {...props}>
      <path d="M12 3.6l2.05 4.55 4.95.5-3.75 3.3 1.1 4.85L12 14.55 7.65 16.8l1.1-4.85-3.75-3.3 4.95-.5L12 3.6z" />
    </IconBase>
  );
}

export function IconQuote({ size = 18, ...props }: IconProps) {
  return (
    <IconBase size={size} fill="currentColor" stroke="none" {...props}>
      <path d="M9.8 6.5c-2.7 0-4.8 2-4.8 4.85 0 1.7.85 3.1 2.2 3.9-.2.85-.65 1.55-1.35 2.15 1.85-.15 3.4-1.1 4.25-2.55.55-.9.85-1.95.85-3.05 0-2.85-1.95-5.3-5.15-5.3zm9.4 0c-2.7 0-4.8 2-4.8 4.85 0 1.7.85 3.1 2.2 3.9-.2.85-.65 1.55-1.35 2.15 1.85-.15 3.4-1.1 4.25-2.55.55-.9.85-1.95.85-3.05 0-2.85-1.95-5.3-5.15-5.3z" />
    </IconBase>
  );
}

export function IconPin({ size = 16, ...props }: IconProps) {
  return (
    <IconBase size={size} {...props}>
      <path d="M12 21s5.75-5.15 5.75-10A5.75 5.75 0 0 0 12 5.25 5.75 5.75 0 0 0 6.25 11C6.25 15.85 12 21 12 21z" />
      <circle cx="12" cy="11" r="1.85" />
    </IconBase>
  );
}

export function IconAtelier({ size = 16, ...props }: IconProps) {
  return (
    <IconBase size={size} {...props}>
      <path d="M4.75 19.5h14.5" />
      <path d="M6.25 19.5V10.1L12 5.4l5.75 4.7V19.5" />
      <path d="M10.15 19.5v-4.2h3.7v4.2" />
      <path d="M8.35 12.15h1.9M13.75 12.15h1.9" />
    </IconBase>
  );
}

export function IconAddress({ size = 16, ...props }: IconProps) {
  return (
    <IconBase size={size} {...props}>
      <rect x="4.5" y="5.5" width="15" height="13" rx="1.25" />
      <path d="M7.25 9.1h9.5M7.25 12.15h6.75M7.25 15.2h8.25" />
    </IconBase>
  );
}

export function IconMapFold({ size = 16, ...props }: IconProps) {
  return (
    <IconBase size={size} {...props}>
      <path d="M5 6.6 9.6 5l4.8 1.6L19 5.2v12.7l-4.6 1.5-4.8-1.6L5 19.4V6.6z" />
      <path d="M9.6 5.1v12.6M14.4 6.6v12.6" />
    </IconBase>
  );
}

export function IconExternal({ size = 16, ...props }: IconProps) {
  return (
    <IconBase size={size} {...props}>
      <path d="M9.25 6.5H7A1.5 1.5 0 0 0 5.5 8v9A1.5 1.5 0 0 0 7 18.5h9a1.5 1.5 0 0 0 1.5-1.5v-2.25" />
      <path d="M13 5.5h5.5V11" />
      <path d="M11.5 12.5 18.5 5.5" />
    </IconBase>
  );
}

export function IconLocate({ size = 16, ...props }: IconProps) {
  return (
    <IconBase size={size} {...props}>
      <circle cx="12" cy="12" r="3.15" />
      <path d="M12 3.5v2.4M12 18.1v2.4M3.5 12h2.4M18.1 12h2.4" />
      <circle cx="12" cy="12" r="7.25" />
    </IconBase>
  );
}

/** Craft / quality mark */
export function IconCraft({ size = 18, ...props }: IconProps) {
  return (
    <IconBase size={size} {...props}>
      <path d="M7.5 16.5 16.5 7.5" />
      <path d="M9.25 7.5h7.25V14.75" />
      <path d="M7.5 12.25v4.25H11.75" />
    </IconBase>
  );
}

/** Discover / explore */
export function IconDiscover({ size = 18, ...props }: IconProps) {
  return (
    <IconBase size={size} {...props}>
      <circle cx="11" cy="11" r="6.25" />
      <path d="M20 20l-3.4-3.4" />
      <path d="M11 8.25v5.5M8.25 11h5.5" />
    </IconBase>
  );
}

/** Language / globe — paths use CSS classes for motion */
export function IconLanguage({ size = 18, className = "", ...props }: IconProps) {
  return (
    <IconBase size={size} className={`icon-language ${className}`.trim()} {...props}>
      <circle className="icon-language__rim" cx="12" cy="12" r="8.15" />
      <ellipse className="icon-language__meridian" cx="12" cy="12" rx="3.35" ry="8.15" />
      <path className="icon-language__lat" d="M4.2 9.15h15.6" />
      <path className="icon-language__lat" d="M3.85 12h16.3" />
      <path className="icon-language__lat" d="M4.2 14.85h15.6" />
    </IconBase>
  );
}
