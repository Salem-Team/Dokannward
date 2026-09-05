"use client";

import { useState } from "react";
import type { ProductReview } from "@/lib/catalog";
import { ApiError, submitProductReview } from "@/lib/api";
import { useLocale } from "@/context/locale";
import { IconStar } from "@/components/Icons";
import { ReviewSuccessModal } from "@/components/ReviewSuccessModal";

function Stars({ value, size = 13 }: { value: number; size?: number }) {
  return (
    <span
      className="flex items-center gap-0.5 text-[var(--color-accent,#b08d57)]"
      aria-hidden="true"
    >
      {Array.from({ length: 5 }).map((_, i) => (
        <IconStar
          key={i}
          size={size}
          className={i < Math.round(value) ? "opacity-100" : "opacity-25"}
          fill={i < Math.round(value) ? "currentColor" : "none"}
        />
      ))}
    </span>
  );
}

function StarPicker({
  value,
  onChange,
  ratingLabel,
}: {
  value: number;
  onChange: (v: number) => void;
  ratingLabel: string;
}) {
  return (
    <div className="flex items-center gap-1" role="radiogroup" aria-label={ratingLabel}>
      {Array.from({ length: 5 }).map((_, i) => {
        const n = i + 1;
        return (
          <button
            key={n}
            type="button"
            role="radio"
            aria-checked={value === n}
            aria-label={`${n}`}
            onClick={() => onChange(n)}
            className="p-0.5 text-[var(--color-accent,#b08d57)] transition-transform hover:scale-110"
          >
            <IconStar
              size={22}
              className={n <= value ? "opacity-100" : "opacity-25"}
              fill={n <= value ? "currentColor" : "none"}
            />
          </button>
        );
      })}
    </div>
  );
}

type Props = {
  handle: string;
  title: string;
  reviews: ProductReview[];
  ratingAverage: number | null;
  reviewsCount: number;
};

export function ProductReviews({
  handle,
  title,
  reviews,
  ratingAverage,
  reviewsCount,
}: Props) {
  const { t } = useLocale();
  const [showForm, setShowForm] = useState(false);
  const [submitting, setSubmitting] = useState(false);
  const [submitted, setSubmitted] = useState(false);
  const [successOpen, setSuccessOpen] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [rating, setRating] = useState(5);

  const handleSubmit = async (e: React.FormEvent<HTMLFormElement>) => {
    e.preventDefault();
    setError(null);

    const form = new FormData(e.currentTarget);
    const name = String(form.get("reviewer_name") || "").trim();
    if (!name) {
      setError(t("checkout.err.nameRequired"));
      return;
    }

    setSubmitting(true);
    try {
      await submitProductReview(handle, {
        reviewer_name: name,
        reviewer_email:
          String(form.get("reviewer_email") || "").trim() || undefined,
        rating,
        title: String(form.get("title") || "").trim() || undefined,
        body: String(form.get("body") || "").trim() || undefined,
      });
      setSubmitted(true);
      setShowForm(false);
      setSuccessOpen(true);
    } catch (err) {
      setError(
        err instanceof ApiError
          ? err.message
          : t("reviews.error"),
      );
    } finally {
      setSubmitting(false);
    }
  };

  const summary =
    reviewsCount === 1
      ? t("reviews.summary", {
          rating: ratingAverage?.toFixed(1) ?? "0",
          count: reviewsCount,
        })
      : t("reviews.summaryPlural", {
          rating: ratingAverage?.toFixed(1) ?? "0",
          count: reviewsCount,
        });

  return (
    <div>
      <div className="flex items-center justify-between flex-wrap gap-4 mb-8">
        <div>
          <h2 className="heading text-2xl md:text-3xl mb-2">{t("reviews.title")}</h2>
          {reviewsCount > 0 ? (
            <p className="flex items-center gap-2 text-sm opacity-70">
              <Stars value={ratingAverage ?? 0} />
              <span>{summary}</span>
            </p>
          ) : (
            <p className="text-sm opacity-60">{t("reviews.empty")}</p>
          )}
        </div>
        {!showForm && !submitted && (
          <button
            type="button"
            className="btn btn-secondary"
            onClick={() => setShowForm(true)}
          >
            {t("reviews.write")}
          </button>
        )}
      </div>

      {submitted && !successOpen && (
        <div className="review-success-inline" role="status">
          <span className="review-success-inline__check" aria-hidden="true">
            <svg viewBox="0 0 24 24" className="review-success-inline__svg">
              <circle
                className="review-success-inline__ring"
                cx="12"
                cy="12"
                r="10"
              />
              <path
                className="review-success-inline__mark"
                d="M7 12.5 10.2 15.5 17 8.5"
              />
            </svg>
          </span>
          <div>
            <p className="review-success-inline__title">{t("reviews.submitted")}</p>
            <p className="review-success-inline__copy">
              {t("reviews.submittedCopy")}
            </p>
          </div>
        </div>
      )}

      {showForm && (
        <form
          onSubmit={handleSubmit}
          className="mb-10 border border-black/10 p-5 md:p-6 space-y-4 max-w-lg"
        >
          <div>
            <p className="text-xs uppercase tracking-[0.12em] opacity-50 mb-2">
              {t("reviews.rating")}
            </p>
            <StarPicker
              value={rating}
              onChange={setRating}
              ratingLabel={t("reviews.rating")}
            />
          </div>
          <div className="grid grid-cols-2 gap-4">
            <label className="block">
              <span className="text-xs uppercase tracking-[0.1em] opacity-50 block mb-1.5">
                {t("reviews.name")}
              </span>
              <input
                name="reviewer_name"
                required
                className="w-full border border-black/20 px-3 py-2 text-sm"
              />
            </label>
            <label className="block">
              <span className="text-xs uppercase tracking-[0.1em] opacity-50 block mb-1.5">
                {t("reviews.email")}
              </span>
              <input
                name="reviewer_email"
                type="email"
                className="w-full border border-black/20 px-3 py-2 text-sm"
              />
            </label>
          </div>
          <label className="block">
            <span className="text-xs uppercase tracking-[0.1em] opacity-50 block mb-1.5">
              {t("reviews.reviewTitle")}
            </span>
            <input
              name="title"
              className="w-full border border-black/20 px-3 py-2 text-sm"
            />
          </label>
          <label className="block">
            <span className="text-xs uppercase tracking-[0.1em] opacity-50 block mb-1.5">
              {t("reviews.body")}
            </span>
            <textarea
              name="body"
              rows={4}
              className="w-full border border-black/20 px-3 py-2 text-sm"
            />
          </label>

          {error && <p className="text-sm text-red-600">{error}</p>}

          <div className="flex items-center gap-3">
            <button
              type="submit"
              className="btn btn-primary"
              disabled={submitting}
            >
              {submitting ? t("reviews.submitting") : t("reviews.submit")}
            </button>
            <button
              type="button"
              className="text-sm opacity-60 hover:opacity-100"
              onClick={() => setShowForm(false)}
            >
              {t("reviews.cancel")}
            </button>
          </div>
        </form>
      )}

      {reviews.length > 0 && (
        <ul className="space-y-6">
          {reviews.map((r) => (
            <li key={r.id} className="border-t border-black/10 pt-6">
              <div className="flex items-center justify-between mb-1.5">
                <div className="flex items-center gap-2">
                  <Stars value={r.rating} />
                  <span className="text-sm font-medium">{r.author}</span>
                </div>
                <span className="text-xs opacity-50">
                  {new Date(r.createdAt).toLocaleDateString(undefined, {
                    year: "numeric",
                    month: "short",
                    day: "numeric",
                  })}
                </span>
              </div>
              {r.title && <p className="text-sm font-medium mb-1">{r.title}</p>}
              {r.body && (
                <p className="text-sm opacity-70 leading-relaxed">{r.body}</p>
              )}
            </li>
          ))}
        </ul>
      )}

      <ReviewSuccessModal
        open={successOpen}
        onClose={() => setSuccessOpen(false)}
        productTitle={title}
      />
    </div>
  );
}
