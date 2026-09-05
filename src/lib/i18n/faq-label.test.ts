import { describe, it, expect } from "vitest";
import { localizeFaqItem } from "./index";

describe("localizeFaqItem", () => {
  it("translates default FAQ rows by tag in Arabic", () => {
    const row = localizeFaqItem("ar", {
      q: "What is the return policy?",
      a: "Our goal is for every customer to be totally satisfied with their purchase. If this isn't the case, let us know and we'll do our best to work with you to make it right.",
      tag: "Returns",
    });
    expect(row.tag).toBe("الإرجاع");
    expect(row.q).toBe("ما هي سياسة الإرجاع؟");
    expect(row.a).toContain("هدفنا");
  });

  it("keeps English when locale is en", () => {
    const row = localizeFaqItem("en", {
      q: "What is the return policy?",
      a: "Answer",
      tag: "Returns",
    });
    expect(row.q).toBe("What is the return policy?");
    expect(row.tag).toBe("Returns");
  });
});
