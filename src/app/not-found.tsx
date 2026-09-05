import Link from "next/link";
import { IconArrowRight } from "@/components/Icons";
import { getServerLocale } from "@/lib/i18n/server";
import { t } from "@/lib/i18n";

export default async function NotFound() {
  const locale = await getServerLocale();
  return (
    <div className="container py-24 text-center animate-fade-up">
      <p className="heading text-6xl md:text-8xl mb-4 tracking-tight">404</p>
      <p className="opacity-70 mb-8">{t(locale, "notFound.copy")}</p>
      <Link href="/" className="btn btn-primary gap-2">
        {t(locale, "notFound.home")}
        <IconArrowRight size={14} />
      </Link>
    </div>
  );
}
