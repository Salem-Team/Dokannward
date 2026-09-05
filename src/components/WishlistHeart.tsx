"use client";

import { useState } from "react";
import type { Product } from "@/lib/catalog";
import { useWishlist } from "@/context/wishlist";
import { IconHeart } from "@/components/Icons";

type WishlistHeartProps = {
  product: Product;
  className?: string;
  size?: "sm" | "md";
};

export function WishlistHeart({
  product,
  className = "",
  size = "sm",
}: WishlistHeartProps) {
  const { has, toggleItem } = useWishlist();
  const saved = has(product.handle);
  const [pulse, setPulse] = useState(false);

  return (
    <button
      type="button"
      className={`wish-heart wish-heart--${size}${saved ? " is-saved" : ""}${pulse ? " is-pulse" : ""}${className ? ` ${className}` : ""}`}
      aria-label={
        saved
          ? `Remove ${product.title} from wishlist`
          : `Save ${product.title}`
      }
      aria-pressed={saved}
      onClick={(e) => {
        e.preventDefault();
        e.stopPropagation();
        setPulse(true);
        toggleItem(product);
        window.setTimeout(() => setPulse(false), 700);
      }}
    >
      <span className="wish-heart__ring wish-heart__ring--a" aria-hidden="true" />
      <span className="wish-heart__ring wish-heart__ring--b" aria-hidden="true" />
      <span className="wish-heart__glow" aria-hidden="true" />
      <IconHeart
        size={size === "md" ? 18 : 15}
        filled={saved}
        className="wish-heart__icon"
      />
    </button>
  );
}
