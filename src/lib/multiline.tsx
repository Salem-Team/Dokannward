import type { ReactNode } from "react";

/** Turn `\n` in admin-edited headlines into line breaks. */
export function Multiline({ text }: { text: string }): ReactNode {
  const parts = text.split("\n");
  return parts.map((line, i) => (
    <span key={`${i}-${line.slice(0, 12)}`}>
      {i > 0 ? <br /> : null}
      {line}
    </span>
  ));
}
