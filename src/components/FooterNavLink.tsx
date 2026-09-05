"use client";

import Link from "next/link";
import { usePathname, useRouter } from "next/navigation";
import type { ReactNode, MouseEvent } from "react";

/**
 * Hash links to the homepage FAQ need special handling: App Router soft
 * navigation often skips scroll-into-view, and same-page hash clicks may not
 * remount the FAQ accordion. This keeps footer deep-links reliable.
 */
export function FooterNavLink({
  href,
  children,
  className,
}: {
  href: string;
  children: ReactNode;
  className?: string;
}) {
  const pathname = usePathname();
  const router = useRouter();
  const hashIndex = href.indexOf("#");
  const hasHash = hashIndex >= 0;
  const path = hasHash ? href.slice(0, hashIndex) || "/" : href;
  const hash = hasHash ? href.slice(hashIndex + 1) : "";

  if (!hasHash) {
    return (
      <Link href={href} className={className}>
        {children}
      </Link>
    );
  }

  function applyHash() {
    if (window.location.hash !== `#${hash}`) {
      window.history.replaceState(null, "", `${path}#${hash}`);
    }
    window.dispatchEvent(new Event("dokannward:faq-hash"));
  }

  function onClick(e: MouseEvent<HTMLAnchorElement>) {
    const onTarget = pathname === path || (path === "/" && pathname === "/");

    if (onTarget) {
      e.preventDefault();
      applyHash();
      return;
    }

    e.preventDefault();
    router.push(path);

    let tries = 0;
    const timer = window.setInterval(() => {
      tries += 1;
      const arrived =
        window.location.pathname === path ||
        (path === "/" && window.location.pathname === "/");
      if (arrived) {
        window.clearInterval(timer);
        applyHash();
      } else if (tries > 50) {
        window.clearInterval(timer);
      }
    }, 40);
  }

  return (
    <a href={href} className={className} onClick={onClick}>
      {children}
    </a>
  );
}
