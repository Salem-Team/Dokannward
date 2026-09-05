import type { Locale } from "@/lib/i18n";
import { t } from "@/lib/i18n";
import type { RelatedProductGroup } from "@/lib/catalog";

/** Localized heading for PDP related rails. */
export function relatedGroupTitle(
  locale: Locale,
  group: Pick<RelatedProductGroup, "kind" | "name">,
): string {
  if (group.kind === "collection") {
    return t(locale, "related.moreFrom", { name: group.name });
  }
  const name = group.name.trim() || t(locale, "related.thisCategory");
  return t(locale, "related.moreIn", { name });
}
