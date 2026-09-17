"use client";

import { useEffect, useRef, useState } from "react";
import Link from "next/link";
import { useBrand } from "@/context/brand";
import { useCurrency } from "@/context/currency";
import { useLocale } from "@/context/locale";
import type { OrderConfirmation } from "@/lib/api";
import { IconCheck, IconCopy } from "@/components/Icons";

export function OrderSuccess({ order }: { order: OrderConfirmation }) {
  const { format } = useCurrency();
  const brand = useBrand();
  const { t, localizeLabel } = useLocale();
  const [copied, setCopied] = useState(false);
  const resetTimer = useRef<ReturnType<typeof setTimeout> | null>(null);

  useEffect(() => {
    const prev = history.scrollRestoration;
    if ("scrollRestoration" in history) {
      history.scrollRestoration = "manual";
    }
    window.scrollTo({ top: 0, left: 0, behavior: "instant" });

    return () => {
      if ("scrollRestoration" in history) {
        history.scrollRestoration = prev;
      }
      if (resetTimer.current) clearTimeout(resetTimer.current);
    };
  }, []);

  async function copyOrderNumber() {
    const value = order.order_number;
    try {
      if (navigator.clipboard?.writeText) {
        await navigator.clipboard.writeText(value);
      } else {
        const el = document.createElement("textarea");
        el.value = value;
        el.setAttribute("readonly", "");
        el.style.position = "fixed";
        el.style.opacity = "0";
        document.body.appendChild(el);
        el.select();
        document.execCommand("copy");
        document.body.removeChild(el);
      }
      setCopied(true);
      if (resetTimer.current) clearTimeout(resetTimer.current);
      resetTimer.current = setTimeout(() => setCopied(false), 1800);
    } catch {
      /* clipboard may be blocked — keep UI quiet */
    }
  }

  const thankYou = t("order.thankYou");

  return (
    <section className="order-success" aria-live="polite">
      <div className="order-success__veil" aria-hidden="true" />

      <div className="order-success__panel">
        <div className="order-success__logo-wrap">
          <img
            src={brand.logo}
            alt={brand.name}
            width={96}
            height={96}
            className="order-success__logo"
          />
          <span className="order-success__logo-shine" aria-hidden="true" />
          <p className="order-success__brand-name">{brand.name}</p>
        </div>

        <div className="order-success__check" aria-hidden="true">
          <svg viewBox="0 0 64 64" className="order-success__check-svg">
            <circle className="order-success__check-ring" cx="32" cy="32" r="28" />
            <path
              className="order-success__check-mark"
              d="M18.5 33.5 27.2 42 45.5 22.5"
            />
          </svg>
        </div>

        <p className="order-success__eyebrow">{t("order.confirmed")}</p>

        <h1 className="order-success__title heading">
          {thankYou.split("").map((ch, i) => (
            <span
              key={`${ch}-${i}`}
              style={{ animationDelay: `${520 + i * 45}ms` }}
            >
              {ch === " " ? "\u00A0" : ch}
            </span>
          ))}
        </h1>

        <div className="order-success__rule" aria-hidden="true" />

        <p className="order-success__copy">{t("order.placed")}</p>

        <div className="order-success__id">
          <span className="order-success__id-label">{t("order.number")}</span>
          <div className="order-success__id-row">
            <code className="order-success__order-no">{order.order_number}</code>
            <button
              type="button"
              className={`order-success__copy-btn${copied ? " is-copied" : ""}`}
              onClick={copyOrderNumber}
              aria-label={copied ? t("order.copiedAria") : t("order.copyAria")}
            >
              <span className="order-success__copy-icons" aria-hidden="true">
                <IconCopy
                  size={14}
                  className="order-success__copy-icon order-success__copy-icon--idle"
                />
                <IconCheck
                  size={14}
                  className="order-success__copy-icon order-success__copy-icon--done"
                />
              </span>
              <span className="order-success__copy-label">
                {copied ? t("order.copied") : t("order.copy")}
              </span>
            </button>
          </div>
        </div>

        <p className="order-success__total">
          {t("order.total", { amount: format(order.total_amount) })}
        </p>

        {order.payment_label ? (
          <div className="order-success__payment">
            <p className="order-success__payment-label">
              {localizeLabel(order.payment_label)}
            </p>
            {order.payment_instructions ? (
              <p className="order-success__payment-hint">
                {localizeLabel(order.payment_instructions)}
              </p>
            ) : null}
          </div>
        ) : null}

        <div className="order-success__stripes" aria-hidden="true">
          <span />
          <span />
          <span />
          <span />
          <span />
          <span />
          <span />
        </div>

        <Link href="/collections/all" className="btn btn-primary order-success__cta">
          {t("order.continue")}
        </Link>

        <p className="order-success__note">
          {t("order.note", { brand: brand.name })}
        </p>
      </div>
    </section>
  );
}
