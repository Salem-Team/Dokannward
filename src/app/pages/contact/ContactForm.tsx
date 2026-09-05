"use client";

import { useRef, useState } from "react";
import type { SiteContent, StoreSettings } from "@/lib/api";
import { mapsHref, telHref, whatsappHref } from "@/lib/contact";
import { Reveal } from "@/components/Reveal";
import { StoreVisit } from "@/components/StoreVisit";
import {
  IconMail,
  IconPhone,
  IconSpinner,
  IconWhatsApp,
} from "@/components/Icons";
import { ContactSuccessModal } from "@/components/ContactSuccessModal";
import { ApiError, submitContactMessage } from "@/lib/api";
import { useLocale } from "@/context/locale";
import { localizeCmsText } from "@/lib/i18n";

export function ContactForm({
  store,
  copy,
}: {
  store: StoreSettings;
  copy: SiteContent["contact"];
}) {
  const { locale, t } = useLocale();
  const [submitting, setSubmitting] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [sentName, setSentName] = useState<string | null>(null);
  const formRef = useRef<HTMLFormElement>(null);

  const email = store.email;
  const phone = store.phone;
  const wa = whatsappHref(store.whatsapp || phone);
  const tel = telHref(phone);
  const maps = mapsHref(store.maps_url, store.address);
  const hasVisit = Boolean(
    store.address ||
      store.address_ar ||
      store.address_label ||
      store.address_label_ar ||
      maps,
  );
  const eyebrow = localizeCmsText(locale, copy.eyebrow, "contact.eyebrow");
  const title = localizeCmsText(locale, copy.title, "contact.title");
  const lede = localizeCmsText(locale, copy.lede, "contact.lede");
  const formTitle = localizeCmsText(locale, copy.form_title, "contact.formTitle");
  const formButton = localizeCmsText(
    locale,
    copy.form_button,
    "contact.formButton",
  );

  async function handleSubmit(e: React.FormEvent<HTMLFormElement>) {
    e.preventDefault();
    setError(null);

    const form = new FormData(e.currentTarget);
    const name = String(form.get("name") || "").trim();
    const emailValue = String(form.get("email") || "").trim();
    const comment = String(form.get("comment") || "").trim();

    if (!emailValue) {
      setError("Please enter your email address.");
      return;
    }
    if (!comment) {
      setError("Please enter a message.");
      return;
    }

    setSubmitting(true);
    try {
      await submitContactMessage({
        name: name || "Guest",
        email: emailValue,
        phone: String(form.get("phone") || "").trim() || undefined,
        message: comment,
      });
      formRef.current?.reset();
      setSentName(name);
    } catch (err) {
      setError(
        err instanceof ApiError
          ? err.message
          : "Something went wrong — please try again.",
      );
    } finally {
      setSubmitting(false);
    }
  }

  return (
    <div className="container py-12 md:py-16">
      <div className="contact-layout">
        <div className="contact-layout__intro">
          <Reveal>
            <p className="contact-layout__eyebrow">{eyebrow}</p>
            <h1 className="heading contact-layout__title">{title}</h1>
            <p className="contact-layout__lede">{lede}</p>
          </Reveal>

          <Reveal delay={80}>
            <div className="contact-ways">
              {tel ? (
                <a href={tel} className="contact-way">
                  <span className="contact-way__icon" aria-hidden="true">
                    <IconPhone size={16} />
                  </span>
                  <span className="contact-way__body">
                    <span className="contact-way__label">{t("contact.call")}</span>
                    <span className="contact-way__value">{phone}</span>
                  </span>
                </a>
              ) : null}
              {wa ? (
                <a
                  href={wa}
                  target="_blank"
                  rel="noopener noreferrer"
                  className="contact-way"
                >
                  <span className="contact-way__icon" aria-hidden="true">
                    <IconWhatsApp size={16} />
                  </span>
                  <span className="contact-way__body">
                    <span className="contact-way__label">
                      {t("footer.whatsapp")}
                    </span>
                    <span className="contact-way__value">
                      {store.whatsapp || phone}
                    </span>
                  </span>
                </a>
              ) : null}
              {email ? (
                <a href={`mailto:${email}`} className="contact-way">
                  <span className="contact-way__icon" aria-hidden="true">
                    <IconMail size={16} />
                  </span>
                  <span className="contact-way__body">
                    <span className="contact-way__label">
                      {t("contact.email")}
                    </span>
                    <span className="contact-way__value">{email}</span>
                  </span>
                </a>
              ) : null}
            </div>
            {hasVisit ? (
              <StoreVisit store={store} maps={maps} tone="onLight" />
            ) : null}
          </Reveal>
        </div>

        <Reveal delay={100} className="contact-layout__form">
          <form
            ref={formRef}
            className="contact-form space-y-5"
            onSubmit={handleSubmit}
          >
            {formTitle ? (
              <h2 className="heading text-xl md:text-2xl">{formTitle}</h2>
            ) : null}
            <Field label={t("contact.name")} name="name" />
            <Field label={t("contact.email")} name="email" type="email" required />
            <Field label={t("contact.phone")} name="phone" type="tel" />
            <label className="block">
              <span className="block text-sm mb-2">{t("contact.message")}</span>
              <textarea
                name="comment"
                rows={5}
                required
                className="w-full bg-transparent border border-black/20 px-3 py-2.5 outline-none transition-colors duration-200 focus:border-[var(--color-black)]"
              />
            </label>

            {error && <p className="text-sm text-red-600">{error}</p>}

            <button
              type="submit"
              className="btn btn-primary inline-flex items-center gap-2"
              disabled={submitting}
            >
              {submitting && <IconSpinner size={16} className="icon-spin" />}
              {submitting ? t("contact.sending") : formButton}
            </button>
          </form>
        </Reveal>
      </div>

      <ContactSuccessModal
        open={sentName !== null}
        name={sentName ?? undefined}
        onClose={() => setSentName(null)}
      />
    </div>
  );
}

function Field({
  label,
  name,
  type = "text",
  required = false,
}: {
  label: string;
  name: string;
  type?: string;
  required?: boolean;
}) {
  return (
    <label className="block">
      <span className="block text-sm mb-2">
        {label}
        {required ? " *" : ""}
      </span>
      <input
        name={name}
        type={type}
        required={required}
        className="w-full bg-transparent border border-black/20 px-3 py-2.5 outline-none transition-colors duration-200 focus:border-[var(--color-black)]"
      />
    </label>
  );
}
