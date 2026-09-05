import { fetchTestimonials, type Testimonial } from "@/lib/api";
import { Reveal } from "@/components/Reveal";
import { TestimonialCard } from "@/components/TestimonialCard";
import { TestimonialsExpandable } from "@/components/TestimonialsExpandable";

/** How many voices show before “Show more”. */
export const TESTIMONIALS_PREVIEW = 3;

export function sortTestimonials(testimonials: Testimonial[]): Testimonial[] {
  return [...testimonials].sort(
    (a, b) => Number(Boolean(b.is_featured)) - Number(Boolean(a.is_featured)),
  );
}

export function TestimonialsGrid({
  testimonials,
  eyebrow = "Client voices",
  title = "What our clients say",
}: {
  testimonials: Testimonial[];
  eyebrow?: string;
  title?: string;
}) {
  if (testimonials.length === 0) return null;

  const items = sortTestimonials(testimonials);
  const layout =
    items.length === 1 ? "solo" : items.length === 2 ? "duo" : "trio";

  return (
    <section
      className="testimonials content-auto"
      aria-labelledby="testimonials-heading"
    >
      <div className="testimonials__atmosphere" aria-hidden="true" />
      <div className="container">
        <Reveal variant="mask">
          <header className="testimonials__header">
            <p className="testimonials__eyebrow">{eyebrow}</p>
            <h2
              id="testimonials-heading"
              className="heading testimonials__title"
            >
              {title}
            </h2>
            <span className="testimonials__rule" aria-hidden="true" />
          </header>
        </Reveal>

        {items.length <= TESTIMONIALS_PREVIEW ? (
          <div className={`testimonials__grid testimonials__grid--${layout}`}>
            {items.map((t, i) => {
              const featured =
                layout === "solo" || (Boolean(t.is_featured) && i === 0);
              return (
                <Reveal key={t.id} delay={i * 100}>
                  <TestimonialCard
                    testimonial={t}
                    index={i}
                    featured={featured}
                  />
                </Reveal>
              );
            })}
          </div>
        ) : (
          <TestimonialsExpandable
            testimonials={items}
            preview={TESTIMONIALS_PREVIEW}
          />
        )}
      </div>
    </section>
  );
}

export async function Testimonials() {
  const testimonials = await fetchTestimonials();
  return <TestimonialsGrid testimonials={testimonials} />;
}
