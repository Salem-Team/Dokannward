export const META_PIXEL_ID = "2551828858665412";

type PurchasePayload = {
  value: number;
  currency: string;
  contentIds: string[];
  numItems: number;
  orderId: string;
};

declare global {
  interface Window {
    fbq?: (...args: unknown[]) => void;
  }
}

export function trackMetaPurchase(payload: PurchasePayload) {
  if (typeof window === "undefined" || typeof window.fbq !== "function") return;

  window.fbq("track", "Purchase", {
    value: payload.value,
    currency: payload.currency,
    content_ids: payload.contentIds,
    content_type: "product",
    num_items: payload.numItems,
    order_id: payload.orderId,
  });
}
