"use client";

import { useEffect, useMemo, useRef, useState, type FormEvent } from "react";
import { StorefrontImage } from "@/components/StorefrontImage";
import Link from "next/link";
import { useBrand } from "@/context/brand";
import { useCart, lineKey } from "@/context/cart";
import { useCheckoutSettings } from "@/context/checkout-settings";
import { useCurrency } from "@/context/currency";
import {
  submitOrder,
  ApiError,
  type GeoPlace,
  type OrderConfirmation,
  computeCheckoutTotals,
  formatCheckoutMessage,
  type CheckoutSettings,
} from "@/lib/api";
import { EGYPT_CITIES, formFromPlace, type CheckoutLocation } from "@/lib/geo";
import { trackMetaPurchase } from "@/lib/meta-pixel";
import { CheckoutEmpty } from "@/components/CheckoutEmpty";
import { OrderSuccess } from "@/components/OrderSuccess";
import { Reveal } from "@/components/Reveal";
import { LocationPicker } from "@/components/LocationPicker";
import {
  IconAddress,
  IconArrowRight,
  IconLock,
  IconLocate,
  IconSpinner,
} from "@/components/Icons";
import { useLocale } from "@/context/locale";
import { localizeLabel, t as translate, type Locale } from "@/lib/i18n";

type DeliveryMode = "pin" | "address";

const CHECKOUT_POLICIES = [
  { label: "Privacy Policy", href: "/policies/privacy-policy" },
  { label: "Terms & Conditions", href: "/policies/terms-of-service" },
  { label: "Refund & Returns", href: "/policies/refund-policy" },
  { label: "Shipping Policy", href: "/policies/shipping-policy" },
] as const;

type FieldName =
  | "name"
  | "phone"
  | "email"
  | "address"
  | "address2"
  | "city"
  | "postal_code"
  | "notes"
  | "location";

type FormValues = Record<Exclude<FieldName, "location">, string>;
type FormErrors = Partial<Record<FieldName, string>>;

const EMPTY: FormValues = {
  name: "",
  phone: "",
  email: "",
  address: "",
  address2: "",
  city: "",
  postal_code: "",
  notes: "",
};

function validateField(
  name: FieldName,
  value: string,
  locale: Locale,
): string | undefined {
  const v = value.trim();

  switch (name) {
    case "name":
      if (!v) return translate(locale, "checkout.err.nameRequired");
      if (v.length < 3) return translate(locale, "checkout.err.nameShort");
      if (!/^[a-zA-Z\u0600-\u06FF\s'.-]+$/.test(v))
        return translate(locale, "checkout.err.nameChars");
      return;
    case "phone": {
      if (!v) return translate(locale, "checkout.err.phoneRequired");
      const digits = v.replace(/[\s()-]/g, "");
      if (!/^(\+?20|0)?1[0125]\d{8}$/.test(digits))
        return translate(locale, "checkout.err.phoneInvalid");
      return;
    }
    case "email":
      if (!v) return;
      if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v))
        return translate(locale, "checkout.err.emailInvalid");
      return;
    case "address":
      if (!v) return translate(locale, "checkout.err.addressRequired");
      if (v.length < 5) return translate(locale, "checkout.err.addressShort");
      return;
    case "city":
      if (!v) return translate(locale, "checkout.err.cityRequired");
      if (v.length < 2) return translate(locale, "checkout.err.cityInvalid");
      return;
    case "postal_code":
      if (!v) return;
      if (!/^\d{4,7}$/.test(v)) return translate(locale, "checkout.err.postal");
      return;
    default:
      return;
  }
}

function validateAll(
  values: FormValues,
  location: CheckoutLocation | null,
  mode: DeliveryMode,
  locale: Locale,
): FormErrors {
  const errors: FormErrors = {};
  (["name", "phone", "email", "postal_code", "notes"] as const).forEach((key) => {
    const err = validateField(key, values[key], locale);
    if (err) errors[key] = err;
  });

  if (mode === "pin") {
    if (!location) {
      errors.location = translate(locale, "checkout.err.location");
    }
    const line = values.address.trim() || location?.place_name || "";
    if (location && !line) {
      errors.address = translate(locale, "checkout.err.landmark");
    }
    if (location && !values.city.trim()) {
      errors.city = translate(locale, "checkout.err.cityRequired");
    }
  } else {
    const addressErr = validateField("address", values.address, locale);
    const cityErr = validateField("city", values.city, locale);
    if (addressErr) errors.address = addressErr;
    if (cityErr) errors.city = cityErr;
  }

  return errors;
}

export default function CheckoutClient({
  initialSettings,
}: {
  initialSettings: CheckoutSettings;
}) {
  const { items, subtotal, clearCart } = useCart();
  const { format, currency } = useCurrency();
  const brand = useBrand();
  const { locale, t } = useLocale();
  const [values, setValues] = useState<FormValues>(EMPTY);
  const [deliveryMode, setDeliveryMode] = useState<DeliveryMode>("pin");
  const [location, setLocation] = useState<CheckoutLocation | null>(null);
  const [autofilled, setAutofilled] = useState(false);
  const autofillTimer = useRef<ReturnType<typeof setTimeout> | null>(null);
  const [errors, setErrors] = useState<FormErrors>({});
  const [touched, setTouched] = useState<Partial<Record<FieldName, boolean>>>({});
  const [submitting, setSubmitting] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [confirmation, setConfirmation] = useState<OrderConfirmation | null>(null);
  const [paymentMethod, setPaymentMethod] = useState<string | null>(null);
  const { settings: liveSettings, isLive } = useCheckoutSettings();
  // This page renders dynamically, so `initialSettings` is fresh on a real
  // request — but a client-side navigation can replay a cached RSC payload.
  // Prefer the browser-confirmed copy as soon as it lands.
  const checkoutSettings = isLive ? liveSettings : initialSettings;

  const { shipping, tax, total } = useMemo(
    () => computeCheckoutTotals(subtotal, checkoutSettings),
    [subtotal, checkoutSettings],
  );
  const taxMessage = checkoutSettings.tax_enabled
    ? formatCheckoutMessage(
        checkoutSettings.tax_enabled_message,
        t("cart.taxOn"),
        { rate: checkoutSettings.tax_rate },
      )
    : formatCheckoutMessage(
        checkoutSettings.tax_disabled_message,
        t("cart.taxOff"),
        {},
      );
  const methods = checkoutSettings.payment_methods;
  // The admin can retire a method while this page is open, so always resolve
  // against the current list rather than trusting the earlier selection.
  const activePayment =
    methods.find((m) => m.key === paymentMethod) ??
    methods.find((m) => m.key === checkoutSettings.default_payment_method) ??
    methods[0];

  useEffect(() => {
    if (paymentMethod && !methods.some((m) => m.key === paymentMethod)) {
      setPaymentMethod(null);
    }
  }, [methods, paymentMethod]);

  useEffect(() => {
    return () => {
      if (autofillTimer.current) clearTimeout(autofillTimer.current);
    };
  }, []);

  const setField = (name: keyof FormValues, value: string) => {
    setValues((prev) => ({ ...prev, [name]: value }));
    if (touched[name] || errors[name]) {
      const err = validateField(name, value, locale);
      setErrors((prev) => {
        const next = { ...prev };
        if (err) next[name] = err;
        else delete next[name];
        return next;
      });
    }
  };

  const markTouched = (name: keyof FormValues) => {
    setTouched((prev) => ({ ...prev, [name]: true }));
    const err = validateField(name, values[name], locale);
    setErrors((prev) => {
      const next = { ...prev };
      if (err) next[name] = err;
      else delete next[name];
      return next;
    });
  };

  async function handleSubmit(e: FormEvent<HTMLFormElement>) {
    e.preventDefault();
    setError(null);

    const nextErrors = validateAll(values, location, deliveryMode, locale);
    setErrors(nextErrors);
    setTouched({
      name: true,
      phone: true,
      email: true,
      address: true,
      address2: true,
      city: true,
      postal_code: true,
      notes: true,
      location: true,
    });

    if (Object.keys(nextErrors).length > 0) {
      const first = Object.keys(nextErrors)[0];
      if (first === "location") {
        document.getElementById("checkout-delivery-title")?.scrollIntoView({
          behavior: "smooth",
          block: "center",
        });
      } else {
        document.getElementById(`field-${first}`)?.focus();
      }
      return;
    }

    if (deliveryMode === "pin" && !location) return;

    const pinLocation = deliveryMode === "pin" ? location : null;
    const line1 =
      values.address.trim() || pinLocation?.place_name || "Pinned delivery location";
    const city = values.city.trim() || "Egypt";

    setSubmitting(true);

    try {
      const order = await submitOrder({
        recipient_name: values.name.trim(),
        phone: values.phone.trim(),
        email: values.email.trim() || undefined,
        line_1: line1,
        line_2: values.address2.trim() || undefined,
        city,
        postal_code: values.postal_code.trim() || undefined,
        country: "Egypt",
        latitude: pinLocation?.latitude,
        longitude: pinLocation?.longitude,
        place_name: pinLocation?.place_name || undefined,
        location_source:
          deliveryMode === "address" ? "typed" : (pinLocation?.source ?? "typed"),
        notes: values.notes.trim() || undefined,
        payment_method: activePayment.key,
        items: items.map(({ product, quantity, color }) => ({
          product_id: String(product.id),
          variant_id: color?.variantId ?? null,
          name: color
            ? `${product.title} — ${color.name}${color.size ? ` / ${color.size}` : ""}`
            : product.title,
          price: parseFloat(color?.price ?? product.price),
          qty: quantity,
          sku: color?.sku ?? product.handle,
        })),
      });

      trackMetaPurchase({
        value: total,
        currency: currency.code,
        contentIds: items.map(
          ({ product, color }) => color?.variantId ?? product.id ?? product.handle,
        ),
        numItems: items.reduce((sum, { quantity }) => sum + quantity, 0),
        orderId: order.order_number,
      });

      window.scrollTo({ top: 0, left: 0, behavior: "instant" });
      setConfirmation(order);
      clearCart();
    } catch (err) {
      setError(
        err instanceof ApiError
          ? err.message
          : "Something went wrong placing your order. Please try again.",
      );
    } finally {
      setSubmitting(false);
    }
  }

  if (confirmation) {
    return <OrderSuccess order={confirmation} />;
  }

  if (items.length === 0) {
    return <CheckoutEmpty />;
  }

  return (
    <div className="container-narrow py-12 md:py-16">
      <Reveal>
        <h1 className="heading text-3xl md:text-5xl mb-8">{t("checkout.title")}</h1>
      </Reveal>

      <div className="grid md:grid-cols-[1.3fr_1fr] gap-10 md:gap-16">
        <Reveal className="checkout-form-column" delay={80}>
        <form className="checkout-form space-y-5" onSubmit={handleSubmit} noValidate>
            <Field
              label={t("checkout.fullName")}
              name="name"
              value={values.name}
              error={touched.name ? errors.name : undefined}
              required
              disabled={submitting}
              onChange={setField}
              onBlur={markTouched}
              autoComplete="name"
            />
            <div className="grid grid-cols-2 gap-4">
              <Field
                label={t("checkout.phone")}
                name="phone"
                type="tel"
                value={values.phone}
                error={touched.phone ? errors.phone : undefined}
                required
                disabled={submitting}
                onChange={setField}
                onBlur={markTouched}
                autoComplete="tel"
              />
              <Field
                label={t("checkout.email")}
                name="email"
                type="email"
                value={values.email}
                error={touched.email ? errors.email : undefined}
                disabled={submitting}
                onChange={setField}
                onBlur={markTouched}
                autoComplete="email"
              />
            </div>
            <section className="checkout-delivery" aria-labelledby="checkout-delivery-title">
              <header className="checkout-delivery__head">
                <div>
                  <p id="checkout-delivery-title" className="checkout-location__eyebrow">
                    {t("checkout.delivery")}
                  </p>
                  <p className="checkout-location__lede">
                    {t("checkout.deliveryLede")}
                  </p>
                </div>
              </header>

              <div className="checkout-delivery__switch" role="tablist" aria-label={t("checkout.delivery")}>
                <button
                  type="button"
                  role="tab"
                  aria-selected={deliveryMode === "pin"}
                  className={`checkout-delivery__tab${deliveryMode === "pin" ? " is-active" : ""}`}
                  disabled={submitting}
                  onClick={() => {
                    setDeliveryMode("pin");
                    setErrors((prev) => {
                      const next = { ...prev };
                      delete next.address;
                      delete next.city;
                      return next;
                    });
                  }}
                >
                  <IconLocate size={15} />
                  {t("checkout.useLocation")}
                </button>
                <button
                  type="button"
                  role="tab"
                  aria-selected={deliveryMode === "address"}
                  className={`checkout-delivery__tab${deliveryMode === "address" ? " is-active" : ""}`}
                  disabled={submitting}
                  onClick={() => {
                    setDeliveryMode("address");
                    setLocation(null);
                    setErrors((prev) => {
                      const next = { ...prev };
                      delete next.location;
                      return next;
                    });
                  }}
                >
                  <IconAddress size={15} />
                  {t("checkout.typeAddress")}
                </button>
              </div>

              {deliveryMode === "pin" ? (
                <>
                  <LocationPicker
                    value={location}
                    disabled={submitting}
                    error={touched.location ? errors.location : undefined}
                    onChange={(next) => {
                      setLocation(next);
                      if (next) {
                        setErrors((prev) => {
                          const copy = { ...prev };
                          delete copy.location;
                          return copy;
                        });
                      }
                    }}
                    onSuggestAddress={(place: GeoPlace) => {
                      const filled = formFromPlace(place);
                      setValues((prev) => {
                        const next = {
                          ...prev,
                          address: filled.address || prev.address,
                          city: filled.city || prev.city,
                          postal_code: filled.postal_code || prev.postal_code,
                        };
                        setErrors((errs) => {
                          const copy = { ...errs };
                          (["address", "city", "postal_code"] as const).forEach((key) => {
                            const err = validateField(key, next[key], locale);
                            if (err) copy[key] = err;
                            else delete copy[key];
                          });
                          return copy;
                        });
                        return next;
                      });
                      if (filled.address || filled.city) {
                        setAutofilled(true);
                        if (autofillTimer.current) clearTimeout(autofillTimer.current);
                        autofillTimer.current = setTimeout(() => setAutofilled(false), 1100);
                      }
                    }}
                  />
                  {location ? (
                    <div className="checkout-delivery__extras">
                      <p className="checkout-delivery__extras-label">
                        {t("checkout.writtenAddress")}
                      </p>
                      <p className="checkout-delivery__extras-copy">
                        Street and city are filled from your pin. Edit anything the courier should know.
                      </p>
                      <Field
                        label={t("checkout.street")}
                        name="address"
                        value={values.address}
                        error={touched.address ? errors.address : undefined}
                        autofilled={autofilled}
                        disabled={submitting}
                        onChange={setField}
                        onBlur={markTouched}
                        autoComplete="street-address"
                      />
                      <Field
                        label={t("checkout.apartment")}
                        name="address2"
                        value={values.address2}
                        error={touched.address2 ? errors.address2 : undefined}
                        disabled={submitting}
                        onChange={setField}
                        onBlur={markTouched}
                      />
                      <div className="grid grid-cols-2 gap-4">
                        <CityField
                          value={values.city}
                          error={touched.city ? errors.city : undefined}
                          required
                          autofilled={autofilled}
                          disabled={submitting}
                          onChange={setField}
                          onBlur={markTouched}
                        />
                        <Field
                          label={t("checkout.postal")}
                          name="postal_code"
                          value={values.postal_code}
                          error={touched.postal_code ? errors.postal_code : undefined}
                          autofilled={autofilled}
                          disabled={submitting}
                          onChange={setField}
                          onBlur={markTouched}
                          autoComplete="postal-code"
                        />
                      </div>
                    </div>
                  ) : null}
                </>
              ) : (
                <div className="checkout-delivery__typed">
                  <Field
                    label={t("checkout.address")}
                    name="address"
                    value={values.address}
                    error={touched.address ? errors.address : undefined}
                    required
                    disabled={submitting}
                    onChange={setField}
                    onBlur={markTouched}
                    autoComplete="street-address"
                  />
                  <Field
                    label={t("checkout.apartmentAlt")}
                    name="address2"
                    value={values.address2}
                    error={touched.address2 ? errors.address2 : undefined}
                    disabled={submitting}
                    onChange={setField}
                    onBlur={markTouched}
                  />
                  <div className="grid grid-cols-2 gap-4">
                    <CityField
                      value={values.city}
                      error={touched.city ? errors.city : undefined}
                      required
                      disabled={submitting}
                      onChange={setField}
                      onBlur={markTouched}
                    />
                    <Field
                      label={t("checkout.postal")}
                      name="postal_code"
                      value={values.postal_code}
                      error={touched.postal_code ? errors.postal_code : undefined}
                      disabled={submitting}
                      onChange={setField}
                      onBlur={markTouched}
                      autoComplete="postal-code"
                    />
                  </div>
                </div>
              )}
            </section>
            <label className="checkout-field">
              <span className="checkout-field__label">{t("checkout.notes")}</span>
              <textarea
                id="field-notes"
                name="notes"
                rows={3}
                value={values.notes}
                disabled={submitting}
                onChange={(e) => setField("notes", e.target.value)}
                className="checkout-field__input checkout-field__input--area"
              />
            </label>

            <fieldset className="checkout-payment" disabled={submitting}>
              <legend className="checkout-field__label">{t("checkout.payment")}</legend>
              <div className="checkout-payment__options">
                {methods.map((method) => {
                  const selected = method.key === activePayment.key;
                  return (
                    <label
                      key={method.key}
                      className={`checkout-payment__option${selected ? " is-selected" : ""}`}
                    >
                      <input
                        type="radio"
                        name="payment_method"
                        value={method.key}
                        checked={selected}
                        onChange={() => setPaymentMethod(method.key)}
                        className="checkout-payment__radio"
                      />
                      <span className="checkout-payment__body">
                        <span className="checkout-payment__label">
                          {localizeLabel(locale, method.label)}
                        </span>
                        {method.instructions ? (
                          <span className="checkout-payment__hint">
                            {localizeLabel(locale, method.instructions)}
                          </span>
                        ) : null}
                      </span>
                    </label>
                  );
                })}
              </div>
            </fieldset>

            {error && (
              <p className="checkout-form__banner" role="alert">
                {error}
              </p>
            )}

            <button
              type="submit"
              className={`btn btn-primary w-full gap-2 checkout-submit${submitting ? " is-loading" : ""}`}
              disabled={submitting}
              aria-busy={submitting}
            >
              {submitting ? (
                <>
                  <IconSpinner size={16} />
                  {t("checkout.placing")}
                </>
              ) : (
                <>
                  <IconLock size={14} />
                  {t("checkout.placeOrder")} · {format(total)}
                </>
              )}
            </button>
          </form>

          <nav className="checkout-policies" aria-label={t("checkout.policiesAria")}>
            <p className="checkout-policies__eyebrow">
              {t("checkout.policiesEyebrow")}
            </p>
            <p className="checkout-policies__copy">
              {t("checkout.policiesCopy")}
            </p>
            <div className="checkout-policies__links">
              {CHECKOUT_POLICIES.map((policy) => (
                <Link
                  key={policy.href}
                  href={policy.href}
                  className="checkout-policies__link"
                >
                  <span>
                    {localizeLabel(locale, policy.label, policy.href)}
                  </span>
                  <IconArrowRight size={13} aria-hidden="true" />
                </Link>
              ))}
            </div>
          </nav>
        </Reveal>

        <Reveal
          className="bg-[var(--color-paper)] border border-black/10 p-6"
          delay={160}
        >
            <h2 className="heading text-sm uppercase tracking-[0.15em] opacity-60 mb-5">
              Order summary
            </h2>
            <ul className="space-y-4">
              {items.map(({ product, quantity, color }) => (
                <li key={lineKey(product.handle, color)} className="flex gap-3">
                  <StorefrontImage
                    src={color?.image || product.image}
                    fallbacks={[...(product.images ?? []), brand.logo]}
                    alt={product.title}
                    width={64}
                    height={64}
                    className="w-16 h-16 object-cover bg-white media-mono"
                  />
                  <div className="flex-1 min-w-0">
                    <p className="text-sm font-medium truncate">{product.title}</p>
                    {color && (
                      <p className="flex items-center gap-1.5 text-xs opacity-60 mt-0.5">
                        <span
                          className="w-2.5 h-2.5 rounded-full border border-black/10 shrink-0"
                          style={{ backgroundColor: color.hex }}
                        />
                        {color.name}
                        {color.size ? ` · ${color.size}` : ""}
                      </p>
                    )}
                    <p className="text-xs opacity-60">
                      {t("checkout.qty", { count: quantity })} ·{" "}
                      {format(color?.price ?? product.price)}
                    </p>
                  </div>
                </li>
              ))}
            </ul>
            <div className="mt-5 pt-4 border-t border-black/10 space-y-2 text-sm">
              <div className="flex justify-between">
                <span className="opacity-70">{t("checkout.subtotal")}</span>
                <span>{format(subtotal)}</span>
              </div>
              <div className="flex justify-between">
                <span className="opacity-70">
                  {t("checkout.shipping")}
                  {checkoutSettings.shipping_company
                    ? ` · ${checkoutSettings.shipping_company}`
                    : ""}
                </span>
                <span>{format(shipping)}</span>
              </div>
              {checkoutSettings.tax_enabled && (
                <div className="flex justify-between">
                  <span className="opacity-70">
                    Tax ({checkoutSettings.tax_rate}%)
                  </span>
                  <span>{format(tax)}</span>
                </div>
              )}
              <div className="flex justify-between pt-2 border-t border-black/10 font-medium">
                <span>{t("checkout.total")}</span>
                <span>{format(total)}</span>
              </div>
            </div>

            <div
              className="commerce-notes mt-5"
              aria-label={t("checkout.shipping")}
            >
              <ul className="commerce-notes__list">
                <li>
                  {checkoutSettings.shipping_company
                    ? `${t("checkout.shipping")} · ${checkoutSettings.shipping_company}`
                    : t("checkout.shipping")}{" "}
                  · {format(shipping)}
                </li>
                <li>
                  {t("checkout.payingWith", {
                    method: localizeLabel(locale, activePayment.label),
                  })}
                </li>
                <li>{taxMessage}</li>
              </ul>
            </div>
        </Reveal>
      </div>
    </div>
  );
}

function Field({
  label,
  name,
  type = "text",
  value,
  error,
  required = false,
  disabled = false,
  autofilled = false,
  autoComplete,
  onChange,
  onBlur,
}: {
  label: string;
  name: keyof FormValues;
  type?: string;
  value: string;
  error?: string;
  required?: boolean;
  disabled?: boolean;
  autofilled?: boolean;
  autoComplete?: string;
  onChange: (name: keyof FormValues, value: string) => void;
  onBlur: (name: keyof FormValues) => void;
}) {
  const invalid = Boolean(error);

  return (
    <label className={`checkout-field${invalid ? " is-invalid" : ""}${value ? " is-filled" : ""}${autofilled ? " is-autofilled" : ""}`}>
      <span className="checkout-field__label">
        {label}
        {required ? <span aria-hidden="true"> *</span> : null}
      </span>
      <input
        id={`field-${name}`}
        name={name}
        type={type}
        value={value}
        required={required}
        disabled={disabled}
        autoComplete={autoComplete}
        aria-invalid={invalid}
        aria-describedby={invalid ? `error-${name}` : undefined}
        onChange={(e) => onChange(name, e.target.value)}
        onBlur={() => onBlur(name)}
        className="checkout-field__input"
      />
      <span className="checkout-field__line" aria-hidden="true" />
      {error ? (
        <span id={`error-${name}`} className="checkout-field__error" role="alert">
          {error}
        </span>
      ) : null}
    </label>
  );
}

function CityField({
  value,
  error,
  required = false,
  disabled = false,
  autofilled = false,
  onChange,
  onBlur,
}: {
  value: string;
  error?: string;
  required?: boolean;
  disabled?: boolean;
  autofilled?: boolean;
  onChange: (name: keyof FormValues, value: string) => void;
  onBlur: (name: keyof FormValues) => void;
}) {
  const { t } = useLocale();
  const invalid = Boolean(error);
  const options = (EGYPT_CITIES as readonly string[]).includes(value)
    ? EGYPT_CITIES
    : value.trim()
      ? [value, ...EGYPT_CITIES]
      : EGYPT_CITIES;

  return (
    <label className={`checkout-field${invalid ? " is-invalid" : ""}${value ? " is-filled" : ""}${autofilled ? " is-autofilled" : ""}`}>
      <span className="checkout-field__label">
        {t("checkout.city")}
        {required ? <span aria-hidden="true"> *</span> : null}
      </span>
      <select
        id="field-city"
        name="city"
        value={value}
        required={required}
        disabled={disabled}
        autoComplete="address-level2"
        aria-invalid={invalid}
        aria-describedby={invalid ? "error-city" : undefined}
        onChange={(e) => onChange("city", e.target.value)}
        onBlur={() => onBlur("city")}
        className="checkout-field__input checkout-field__input--select"
      >
        <option value="">{t("checkout.selectCity")}</option>
        {options.map((city) => (
          <option key={city} value={city}>
            {city}
          </option>
        ))}
      </select>
      <span className="checkout-field__line" aria-hidden="true" />
      {error ? (
        <span id="error-city" className="checkout-field__error" role="alert">
          {error}
        </span>
      ) : null}
    </label>
  );
}
