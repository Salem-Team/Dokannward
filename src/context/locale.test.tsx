/** @vitest-environment jsdom */
import { describe, it, expect, vi, beforeEach, afterEach } from "vitest";
import { act, cleanup, render, screen, waitFor } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { LocaleProvider, useLocale } from "@/context/locale";

const refresh = vi.fn();

vi.mock("next/navigation", () => ({
  useRouter: () => ({ refresh }),
}));

function Probe() {
  const { locale, t, setLocale } = useLocale();
  return (
    <div>
      <span data-testid="locale">{locale}</span>
      <span data-testid="skip">{t("a11y.skip")}</span>
      <button type="button" onClick={() => setLocale("ar")}>
        to-ar
      </button>
      <button type="button" onClick={() => setLocale("en")}>
        to-en
      </button>
    </div>
  );
}

describe("LocaleProvider", () => {
  beforeEach(() => {
    cleanup();
    refresh.mockClear();
    document.cookie = "dw_locale=;path=/;max-age=0";
    localStorage.clear();
    sessionStorage.clear();
    document.documentElement.lang = "en";
    document.documentElement.dir = "ltr";
  });

  afterEach(() => {
    cleanup();
    document.cookie = "dw_locale=;path=/;max-age=0";
    localStorage.clear();
    sessionStorage.clear();
  });

  it("renders skip link copy for the server locale", () => {
    render(
      <LocaleProvider initialLocale="en">
        <Probe />
      </LocaleProvider>,
    );
    expect(screen.getByTestId("locale").textContent).toBe("en");
    expect(screen.getByTestId("skip").textContent).toBe("Skip to content");
  });

  it("hydrates Arabic from localStorage once without looping refresh", async () => {
    localStorage.setItem("dw_locale", "ar");

    const first = render(
      <LocaleProvider initialLocale="en">
        <Probe />
      </LocaleProvider>,
    );

    await waitFor(() => {
      expect(screen.getByTestId("locale").textContent).toBe("ar");
    });
    expect(screen.getByTestId("skip").textContent).toBe("تخطّى إلى المحتوى");
    expect(refresh).toHaveBeenCalledTimes(1);

    first.unmount();
    refresh.mockClear();

    // Remount simulates Strict Mode / soft navigation — must not refresh again.
    render(
      <LocaleProvider initialLocale="en">
        <Probe />
      </LocaleProvider>,
    );
    await act(async () => {
      await Promise.resolve();
    });
    await waitFor(() => {
      expect(screen.getByTestId("locale").textContent).toBe("ar");
    });
    expect(refresh).not.toHaveBeenCalled();
  });

  it("does not refresh when re-selecting the active locale", async () => {
    const user = userEvent.setup();
    render(
      <LocaleProvider initialLocale="en">
        <Probe />
      </LocaleProvider>,
    );

    await user.click(screen.getByRole("button", { name: "to-en" }));
    expect(refresh).not.toHaveBeenCalled();

    await user.click(screen.getByRole("button", { name: "to-ar" }));
    expect(refresh).toHaveBeenCalledTimes(1);
    expect(screen.getByTestId("locale").textContent).toBe("ar");
  });
});
