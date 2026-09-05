type JsonLdProps = {
  data: Record<string, unknown> | Array<Record<string, unknown>> | null | undefined;
};

/** Safe JSON-LD script tag for App Router server components. */
export function JsonLd({ data }: JsonLdProps) {
  if (!data || (Array.isArray(data) && data.length === 0)) return null;

  return (
    <script
      type="application/ld+json"
      dangerouslySetInnerHTML={{
        __html: JSON.stringify(data).replace(/</g, "\\u003c"),
      }}
    />
  );
}
