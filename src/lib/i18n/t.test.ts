import { describe, it, expect } from "vitest";
import { t } from "./index";

describe("t", () => {
  it("returns a11y.skip for en and ar", () => {
    expect(t("en", "a11y.skip")).toBe("Skip to content");
    expect(t("ar", "a11y.skip")).toBe("تخطّى إلى المحتوى");
  });

  it("does not throw when locale is invalid or undefined", () => {
    expect(t("fr" as "en", "a11y.skip")).toBe("Skip to content");
    expect(t(undefined as unknown as "en", "a11y.skip")).toBe(
      "Skip to content",
    );
    expect(t(null as unknown as "en", "a11y.skip")).toBe("Skip to content");
    expect(t("" as "en", "missing.key")).toBe("missing.key");
  });
});
