"use client";

import { useId, useRef, useState } from "react";
import type { Testimonial } from "@/lib/api";
import { Reveal } from "@/components/Reveal";
import { TestimonialCard } from "@/components/TestimonialCard";

export function TestimonialsExpandable({
  testimonials,
  preview = 3,
}: {
  testimonials: Testimonial[];
  preview?: number;
}) {
  const [expanded, setExpanded] = useState(false);
  const extraId = useId();
  const extraRef = useRef<HTMLDivElement>(null);
  const sectionTopRef = useRef<HTMLDivElement>(null);

  const visible = testimonials.slice(0, preview);
  const extra = testimonials.slice(preview);
  const remaining = extra.length;

  if (remaining <= 0) {
    return (
      <div className="testimonials__grid testimonials__grid--trio">
        {visible.map((t, i) => (
          <Reveal key={t.id} delay={i * 100}>
            <TestimonialCard testimonial={t} index={i} />
          </Reveal>
        ))}
      </div>
    );
  }

  const toggle = () => {
    const next = !expanded;
    setExpanded(next);

    if (next) {
      requestAnimationFrame(() => {
        extraRef.current?.scrollIntoView({
          behavior: "smooth",
          block: "nearest",
        });
      });
      return;
    }

    requestAnimationFrame(() => {
      sectionTopRef.current?.scrollIntoView({
        behavior: "smooth",
        block: "nearest",
      });
    });
  };

  return (
    <div className="testimonials__expandable" ref={sectionTopRef}>
      <div className="testimonials__grid testimonials__grid--trio">
        {visible.map((t, i) => (
          <Reveal key={t.id} delay={i * 100}>
            <TestimonialCard testimonial={t} index={i} />
          </Reveal>
        ))}
      </div>

      <div
        id={extraId}
        ref={extraRef}
        className={`testimonials__extra${expanded ? " is-open" : ""}`}
        hidden={!expanded}
        aria-hidden={!expanded}
      >
        {expanded ? (
          <>
            <div className="testimonials__extra-rule" aria-hidden="true">
              <span className="testimonials__extra-label">
                More client voices
              </span>
            </div>
            <div className="testimonials__grid testimonials__grid--trio testimonials__grid--extra">
              {extra.map((t, i) => (
                <Reveal key={t.id} delay={Math.min(i, 5) * 80}>
                  <TestimonialCard testimonial={t} index={preview + i} />
                </Reveal>
              ))}
            </div>
          </>
        ) : null}
      </div>

      <div className="testimonials__actions">
        <button
          type="button"
          className="testimonials__toggle"
          onClick={toggle}
          aria-expanded={expanded}
          aria-controls={extraId}
        >
          <span className="testimonials__toggle-label">
            {expanded
              ? "Show less"
              : remaining === 1
                ? "Show 1 more review"
                : `Show ${remaining} more reviews`}
          </span>
          <span className="testimonials__toggle-meta" aria-hidden="true">
            {expanded ? "−" : `+${remaining}`}
          </span>
        </button>
      </div>
    </div>
  );
}
