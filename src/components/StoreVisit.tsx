"use client";

import type { StoreSettings } from "@/lib/api";
import {
  IconAtelier,
  IconExternal,
  IconMapFold,
  IconPin,
} from "@/components/Icons";
import { useLocale } from "@/context/locale";

type StoreVisitTone = "onDark" | "onLight";

export function StoreVisit({
  store,
  maps,
  tone = "onDark",
  heading,
  showHeadline = true,
}: {
  store: StoreSettings;
  maps: string;
  tone?: StoreVisitTone;
  heading?: string | null;
  showHeadline?: boolean;
}) {
  const { locale, t } = useLocale();
  const visitHeading =
    heading === null ? null : heading ?? t("footer.visit");
  const headline = store.address_label.trim();
  const headlineAr = store.address_label_ar.trim();
  const address = store.address.trim();
  const addressAr = store.address_ar.trim();
  const hasHeadline = showHeadline && Boolean(headline || headlineAr);
  const hasAddress = Boolean(address || addressAr);
  const hasMaps = Boolean(maps);

  if (!hasHeadline && !hasAddress && !hasMaps) return null;

  return (
    <div className={`store-visit store-visit--${tone}`}>
      {visitHeading ? (
        <h3 className="store-visit__heading">{visitHeading}</h3>
      ) : null}

      <ul className="store-visit__list">
        {hasHeadline ? (
          <li className="store-visit__row">
            <div className="store-visit__item">
              <span className="store-visit__icon" aria-hidden>
                <IconPin size={15} />
              </span>
              <div className="store-visit__body">
                <p className="store-visit__label">{t("footer.atelier")}</p>
                {locale === "ar" ? (
                  <>
                    {headlineAr ? (
                      <p className="store-visit__ar" dir="rtl" lang="ar">
                        {headlineAr}
                      </p>
                    ) : null}
                    {headline && !headlineAr ? (
                      <p className="store-visit__en">{headline}</p>
                    ) : null}
                  </>
                ) : (
                  <>
                    {headline ? <p className="store-visit__en">{headline}</p> : null}
                    {headlineAr ? (
                      <p className="store-visit__ar" dir="rtl" lang="ar">
                        {headlineAr}
                      </p>
                    ) : null}
                  </>
                )}
              </div>
            </div>
          </li>
        ) : null}

        {hasAddress ? (
          <li className="store-visit__row">
            <div className="store-visit__item">
              <span className="store-visit__icon" aria-hidden>
                <IconAtelier size={15} />
              </span>
              <div className="store-visit__body">
                <p className="store-visit__label">{t("footer.address")}</p>
                {locale === "ar" ? (
                  <>
                    {addressAr ? (
                      <p className="store-visit__ar" dir="rtl" lang="ar">
                        {addressAr}
                      </p>
                    ) : null}
                    {address && !addressAr ? (
                      <p className="store-visit__en">{address}</p>
                    ) : null}
                  </>
                ) : (
                  <>
                    {address ? <p className="store-visit__en">{address}</p> : null}
                    {addressAr ? (
                      <p className="store-visit__ar" dir="rtl" lang="ar">
                        {addressAr}
                      </p>
                    ) : null}
                  </>
                )}
              </div>
            </div>
          </li>
        ) : null}

        {hasMaps ? (
          <li className="store-visit__row">
            <a
              href={maps}
              target="_blank"
              rel="noopener noreferrer"
              className="store-visit__item store-visit__maps"
            >
              <span className="store-visit__icon" aria-hidden>
                <IconMapFold size={15} />
              </span>
              <span className="store-visit__body">
                <span className="store-visit__label">
                  {t("footer.directions")}
                </span>
                <span className="store-visit__cta">
                  {t("store.maps")}
                  <IconExternal size={11} aria-hidden />
                </span>
              </span>
            </a>
          </li>
        ) : null}
      </ul>
    </div>
  );
}
