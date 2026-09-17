"use client";

import { useEffect, useState } from "react";
import { Reveal } from "@/components/Reveal";
import { IconPlus } from "@/components/Icons";
import { useLocale } from "@/context/locale";
import { localizeFaqItem } from "@/lib/i18n";

const HASH_ALIASES: Record<string, number> = {
  faq: 0,
  "faq-returns": 0,
  "faq-delivery": 1,
  "faq-shipping": 1,
  "faq-origin": 2,
};

function slugifyTag(tag: string, index: number) {
  const base = tag
    .toLowerCase()
    .replace(/[^a-z0-9]+/g, "-")
    .replace(/^-|-$/g, "");
  return base ? `faq-${base}` : `faq-item-${index + 1}`;
}

function indexFromHash(itemIds: string[]) {
  if (typeof window === "undefined") return null;
  const id = window.location.hash.replace(/^#/, "");
  if (!id) return null;
  if (HASH_ALIASES[id] != null) return HASH_ALIASES[id];
  const byId = itemIds.indexOf(id);
  return byId >= 0 ? byId : null;
}

type FaqItem = { q: string; a: string; tag?: string };

export function PoliciesFaq({
  items,
  eyebrow = "Support",
  title = "Our Policies",
}: {
  items?: FaqItem[];
  eyebrow?: string;
  title?: string;
}) {
  const { locale } = useLocale();
  const faqItems = items?.length ? items : [];
  const itemIdsKey = faqItems
    .map((item, i) => `${i}:${item.tag ?? ""}:${item.q}`)
    .join("|");
  const itemIds = faqItems.map((item, i) =>
    slugifyTag(item.tag?.trim() || "", i),
  );
  const [open, setOpen] = useState<number | null>(0);

  useEffect(() => {
    if (faqItems.length === 0) return;

    const applyHash = () => {
      const index = indexFromHash(itemIds);
      if (index == null) return;
      setOpen(index);
      const raw = window.location.hash.replace(/^#/, "");
      const targetId = raw === "faq" ? "faq" : raw;
      window.requestAnimationFrame(() => {
        document
          .getElementById(targetId)
          ?.scrollIntoView({ behavior: "smooth", block: "start" });
      });
    };

    applyHash();
    window.addEventListener("hashchange", applyHash);
    window.addEventListener("dokannward:faq-hash", applyHash);
    return () => {
      window.removeEventListener("hashchange", applyHash);
      window.removeEventListener("dokannward:faq-hash", applyHash);
    };
    // itemIdsKey tracks FAQ content; itemIds is rebuilt in sync with it.
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [faqItems.length, itemIdsKey]);

  if (faqItems.length === 0) return null;

  return (
    <section className="policies-faq" id="faq" aria-labelledby="faq-heading">
      <div className="container">
        <Reveal variant="mask">
          <header className="policies-faq__header">
            <p className="policies-faq__eyebrow">{eyebrow}</p>
            <h2 id="faq-heading" className="heading policies-faq__title">
              {title}
            </h2>
            <span className="policies-faq__rule" aria-hidden="true" />
          </header>
        </Reveal>

        <div className="policies-faq__shell">
          <div className="policies-faq__list" role="list">
            {faqItems.map((item, i) => {
              const isOpen = open === i;
              const id = itemIds[i];
              const indexLabel = String(i + 1).padStart(2, "0");
              const localized = localizeFaqItem(locale, item);
              const question = localized.q;
              const answer = localized.a;
              const tagLabel = localized.tag?.trim() || null;

              return (
                <Reveal key={`${item.q}-${i}`} delay={i * 55}>
                  <div
                    id={id}
                    className={`policies-faq__item${isOpen ? " is-open" : ""}`}
                    role="listitem"
                  >
                    <button
                      type="button"
                      className="policies-faq__trigger"
                      onClick={() => setOpen(isOpen ? null : i)}
                      aria-expanded={isOpen}
                      aria-controls={`${id}-panel`}
                    >
                      <span className="policies-faq__index" aria-hidden="true">
                        {indexLabel}
                      </span>

                      <span className="policies-faq__copy">
                        {tagLabel ? (
                          <span className="policies-faq__tag">{tagLabel}</span>
                        ) : null}
                        <span className="policies-faq__question">{question}</span>
                      </span>

                      <span
                        className={`policies-faq__toggle${isOpen ? " is-open" : ""}`}
                        aria-hidden="true"
                      >
                        <IconPlus size={13} />
                      </span>
                    </button>

                    <div
                      id={`${id}-panel`}
                      className="policies-faq__panel"
                      role="region"
                      style={{ gridTemplateRows: isOpen ? "1fr" : "0fr" }}
                    >
                      <div className="policies-faq__panel-inner">
                        <p className="policies-faq__answer">{answer}</p>
                      </div>
                    </div>
                  </div>
                </Reveal>
              );
            })}
          </div>
        </div>
      </div>
    </section>
  );
}
