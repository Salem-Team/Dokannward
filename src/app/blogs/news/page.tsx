import type { Metadata } from "next";
import Link from "next/link";
import { pageMetadata } from "@/lib/seo";
import { getServerLocale } from "@/lib/i18n/server";
import { t } from "@/lib/i18n";

export async function generateMetadata(): Promise<Metadata> {
  const locale = await getServerLocale();
  return pageMetadata({
    path: "/blogs/news",
    title: t(locale, "news.title"),
    description: "Dokan Ward editorial notes and studio news.",
    index: false,
  });
}

export default async function BlogPage() {
  const locale = await getServerLocale();
  return (
    <div className="container py-16 md:py-24 text-center animate-fade-up">
      <h1 className="heading text-3xl md:text-5xl mb-4">{t(locale, "news.title")}</h1>
      <p className="opacity-70 mb-8">{t(locale, "news.empty")}</p>
      <Link href="/" className="btn btn-secondary">
        {t(locale, "notFound.home")}
      </Link>
    </div>
  );
}
