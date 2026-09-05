"use client";

import {
  useCallback,
  useEffect,
  useId,
  useMemo,
  useRef,
  useState,
  type PointerEvent as ReactPointerEvent,
} from "react";
import { reverseGeocode, searchPlaces, fetchRoughLocation, ApiError, type GeoPlace } from "@/lib/api";
import {
  EGYPT_CENTER,
  GEO_ZOOM,
  clamp,
  fallbackGeoPlace,
  formatCoords,
  project,
  tileUrl,
  unproject,
  type CheckoutLocation,
  type LatLng,
} from "@/lib/geo";
import {
  GeolocationReadError,
  geolocationHint,
  readCheckoutGeolocation,
} from "@/lib/geolocation";
import { useLocale } from "@/context/locale";
import { IconLocate, IconPin, IconSearch, IconSpinner } from "@/components/Icons";

const TILE = 256;

type Props = {
  value: CheckoutLocation | null;
  onChange: (value: CheckoutLocation | null) => void;
  onSuggestAddress?: (place: GeoPlace) => void;
  disabled?: boolean;
  error?: string;
};

export function LocationPicker({
  value,
  onChange,
  onSuggestAddress,
  disabled = false,
  error,
}: Props) {
  const { t } = useLocale();
  const searchId = useId();
  const searchRef = useRef<HTMLInputElement>(null);
  const frameRef = useRef<HTMLDivElement>(null);
  const [size, setSize] = useState({ width: 640, height: 320 });
  const [center, setCenter] = useState<LatLng>(
    value ? { lat: value.latitude, lng: value.longitude } : EGYPT_CENTER,
  );
  const [zoom, setZoom] = useState<number>(value ? GEO_ZOOM.street : GEO_ZOOM.city);
  const [query, setQuery] = useState("");
  const [hits, setHits] = useState<GeoPlace[]>([]);
  const [searching, setSearching] = useState(false);
  const [locating, setLocating] = useState(false);
  const [resolving, setResolving] = useState(false);
  const [placeLabel, setPlaceLabel] = useState(value?.place_name ?? "");
  const [gpsHint, setGpsHint] = useState<string | null>(null);
  const [searchOpen, setSearchOpen] = useState(false);
  const [dragging, setDragging] = useState(false);
  const drag = useRef<{
    pointerId: number;
    x: number;
    y: number;
    moved: boolean;
  } | null>(null);
  const idleTimer = useRef<ReturnType<typeof setTimeout> | null>(null);
  const searchTimer = useRef<ReturnType<typeof setTimeout> | null>(null);
  const sourceRef = useRef<CheckoutLocation["source"]>(value?.source ?? "map");
  const centerRef = useRef(center);
  const zoomRef = useRef(zoom);
  const publishSeq = useRef(0);
  const reverseAbort = useRef<AbortController | null>(null);
  const lastResolvedKey = useRef<string | null>(null);
  const confirmed = Boolean(value);

  centerRef.current = center;
  zoomRef.current = zoom;

  useEffect(() => {
    if (!value) return;
    setCenter({ lat: value.latitude, lng: value.longitude });
    setPlaceLabel(value.place_name ?? "");
    sourceRef.current = value.source;
    setZoom((prev) => Math.max(prev, GEO_ZOOM.street));
  }, [value?.latitude, value?.longitude, value?.place_name, value?.source]);

  useEffect(() => {
    const node = frameRef.current;
    if (!node) return;

    const apply = () => {
      const rect = node.getBoundingClientRect();
      setSize({
        width: Math.max(280, Math.round(rect.width)),
        height: Math.max(240, Math.round(rect.height)),
      });
    };

    apply();
    const observer = new ResizeObserver(apply);
    observer.observe(node);
    return () => observer.disconnect();
  }, []);

  const tiles = useMemo(() => {
    const z = Math.round(zoom);
    const origin = project(center, z);
    const left = origin.x - size.width / 2;
    const top = origin.y - size.height / 2;
    const minX = Math.floor(left / TILE) - 1;
    const maxX = Math.floor((left + size.width) / TILE) + 1;
    const minY = Math.floor(top / TILE) - 1;
    const maxY = Math.floor((top + size.height) / TILE) + 1;
    const list: Array<{ key: string; src: string; left: number; top: number }> = [];

    for (let x = minX; x <= maxX; x += 1) {
      for (let y = minY; y <= maxY; y += 1) {
        if (y < 0 || y >= 2 ** z) continue;
        list.push({
          key: `${z}:${x}:${y}`,
          src: tileUrl(z, x, y),
          left: x * TILE - left,
          top: y * TILE - top,
        });
      }
    }

    return list;
  }, [center, zoom, size.height, size.width]);

  const publish = useCallback(
    async (next: LatLng, source: CheckoutLocation["source"], options?: { zoom?: number }) => {
      const seq = ++publishSeq.current;
      sourceRef.current = source;
      setCenter(next);
      centerRef.current = next;
      if (options?.zoom) {
        setZoom(options.zoom);
        zoomRef.current = options.zoom;
      }
      setGpsHint(null);

      const cacheKey = `${next.lat.toFixed(5)},${next.lng.toFixed(5)}`;
      if (cacheKey === lastResolvedKey.current && source === "map") {
        onChange({
          latitude: Number(next.lat.toFixed(7)),
          longitude: Number(next.lng.toFixed(7)),
          place_name: placeLabel || formatCoords(next),
          source,
        });
        return;
      }

      reverseAbort.current?.abort();
      const controller = new AbortController();
      reverseAbort.current = controller;

      setResolving(true);

      let place: GeoPlace | null = null;
      try {
        place = await reverseGeocode(next.lat, next.lng, { signal: controller.signal });
      } catch (err) {
        if (controller.signal.aborted || seq !== publishSeq.current) return;
        if (err instanceof ApiError && err.status === 429) {
          setGpsHint(t("location.rateLimit"));
        } else {
          setGpsHint(t("location.resolveFail"));
        }
        place = fallbackGeoPlace(next);
      }

      if (seq !== publishSeq.current) return;

      if (!place) {
        place = fallbackGeoPlace(next);
      }

      lastResolvedKey.current = cacheKey;
      const label = place.place_name ?? formatCoords(next);
      setPlaceLabel(label);
      setResolving(false);

      onChange({
        latitude: Number(next.lat.toFixed(7)),
        longitude: Number(next.lng.toFixed(7)),
        place_name: label,
        source,
      });

      onSuggestAddress?.(place);
    },
    [onChange, onSuggestAddress, placeLabel, t],
  );

  const scheduleResolve = useCallback(
    (next: LatLng, source: CheckoutLocation["source"]) => {
      if (idleTimer.current) clearTimeout(idleTimer.current);
      idleTimer.current = setTimeout(() => {
        void publish(next, source);
      }, 650);
    },
    [publish],
  );

  useEffect(() => {
    return () => {
      if (idleTimer.current) clearTimeout(idleTimer.current);
      if (searchTimer.current) clearTimeout(searchTimer.current);
      reverseAbort.current?.abort();
    };
  }, []);

  useEffect(() => {
    const q = query.trim();
    if (searchTimer.current) clearTimeout(searchTimer.current);
    if (q.length < 2) {
      setHits([]);
      setSearching(false);
      return;
    }

    setSearching(true);
    searchTimer.current = setTimeout(() => {
      void searchPlaces(q)
        .then((rows) => {
          setHits(rows);
          setSearching(false);
          setSearchOpen(true);
          setGpsHint(null);
        })
        .catch((err) => {
          setHits([]);
          setSearching(false);
          if (err instanceof ApiError && err.status === 429) {
            setGpsHint(t("location.busy"));
          }
        });
    }, 450);

    return () => {
      if (searchTimer.current) clearTimeout(searchTimer.current);
    };
  }, [query, t]);

  const panBy = (dx: number, dy: number) => {
    const z = Math.round(zoomRef.current);
    const origin = project(centerRef.current, z);
    const next = unproject(origin.x - dx, origin.y - dy, z);
    centerRef.current = next;
    setCenter(next);
    return next;
  };

  const isMapSurface = useCallback((target: EventTarget | null) => {
    if (!(target instanceof Node)) return false;
    const frame = frameRef.current;
    if (!frame) return false;
    if (target instanceof HTMLElement && target.closest(".checkout-location__controls")) {
      return false;
    }
    return frame.contains(target);
  }, []);

  const finishPointer = useCallback(
    (pointerId: number, target: EventTarget | null, allowTap: boolean) => {
      const state = drag.current;
      if (!state || state.pointerId !== pointerId) return;
      const moved = state.moved;
      drag.current = null;
      setDragging(false);

      sourceRef.current = "map";
      if (moved) {
        scheduleResolve(centerRef.current, "map");
        return;
      }

      if (allowTap && isMapSurface(target)) {
        void publish(centerRef.current, "map", {
          zoom: Math.max(Math.round(zoomRef.current), GEO_ZOOM.street),
        });
      }
    },
    [isMapSurface, publish, scheduleResolve],
  );

  useEffect(() => {
    if (!dragging) return;

    const onMove = (event: PointerEvent) => {
      const state = drag.current;
      if (!state || state.pointerId !== event.pointerId) return;
      const dx = event.clientX - state.x;
      const dy = event.clientY - state.y;
      if (!state.moved && dx * dx + dy * dy < 9) return;
      state.moved = true;
      state.x = event.clientX;
      state.y = event.clientY;
      panBy(dx, dy);
    };

    const onUp = (event: PointerEvent) => {
      finishPointer(event.pointerId, event.target, true);
    };

    window.addEventListener("pointermove", onMove);
    window.addEventListener("pointerup", onUp);
    window.addEventListener("pointercancel", onUp);

    return () => {
      window.removeEventListener("pointermove", onMove);
      window.removeEventListener("pointerup", onUp);
      window.removeEventListener("pointercancel", onUp);
    };
  }, [dragging, finishPointer]);

  const onPointerDown = (event: ReactPointerEvent<HTMLDivElement>) => {
    if (disabled || event.button !== 0) return;
    event.preventDefault();
    event.currentTarget.setPointerCapture(event.pointerId);
    drag.current = {
      pointerId: event.pointerId,
      x: event.clientX,
      y: event.clientY,
      moved: false,
    };
    setDragging(true);
  };

  const endDrag = (event: ReactPointerEvent<HTMLDivElement>) => {
    finishPointer(event.pointerId, event.target, true);
  };

  useEffect(() => {
    const node = frameRef.current;
    if (!node) return;

    const onWheel = (event: WheelEvent) => {
      if (disabled) return;
      event.preventDefault();
      const direction = event.deltaY > 0 ? -1 : 1;
      const currentZoom = Math.round(zoomRef.current);
      const nextZoom = clamp(currentZoom + direction, GEO_ZOOM.min, GEO_ZOOM.max);
      if (nextZoom === currentZoom) return;

      const rect = node.getBoundingClientRect();
      const offsetX = event.clientX - rect.left;
      const offsetY = event.clientY - rect.top;
      const current = project(centerRef.current, currentZoom);
      const world = {
        x: current.x - size.width / 2 + offsetX,
        y: current.y - size.height / 2 + offsetY,
      };
      const focus = unproject(world.x, world.y, currentZoom);
      const focused = project(focus, nextZoom);
      const nextCenter = unproject(
        focused.x - offsetX + size.width / 2,
        focused.y - offsetY + size.height / 2,
        nextZoom,
      );
      zoomRef.current = nextZoom;
      centerRef.current = nextCenter;
      setZoom(nextZoom);
      setCenter(nextCenter);
    };

    node.addEventListener("wheel", onWheel, { passive: false });
    return () => node.removeEventListener("wheel", onWheel);
  }, [disabled, size.height, size.width]);

  const zoomBy = (delta: number) => {
    if (disabled) return;
    setZoom((prev) => clamp(Math.round(prev) + delta, GEO_ZOOM.min, GEO_ZOOM.max));
  };

  const useGps = () => {
    if (disabled) return;

    setLocating(true);
    setGpsHint(null);

    void readCheckoutGeolocation()
      .then((position) => {
        setLocating(false);
        void publish(
          { lat: position.coords.latitude, lng: position.coords.longitude },
          "gps",
          { zoom: GEO_ZOOM.building },
        );
      })
      .catch(async (err: unknown) => {
        const rough = await fetchRoughLocation().catch(() => null);
        if (rough) {
          setLocating(false);
          setGpsHint(t("location.networkHint"));
          void publish(
            { lat: rough.latitude, lng: rough.longitude },
            "gps",
            { zoom: GEO_ZOOM.street },
          );
          onSuggestAddress?.(rough);
          return;
        }

        setLocating(false);
        const code =
          err instanceof GeolocationReadError
            ? err.code
            : (err as GeolocationPositionError)?.code ===
                (err as GeolocationPositionError)?.PERMISSION_DENIED
              ? 1
              : (err as GeolocationPositionError)?.code ===
                  (err as GeolocationPositionError)?.TIMEOUT
                ? 3
                : 2;
        setGpsHint(t(geolocationHint(code)));
        searchRef.current?.focus();
      });
  };

  const chooseHit = (hit: GeoPlace) => {
    setQuery(hit.place_name ?? query);
    setHits([]);
    setSearchOpen(false);
    void publish(
      { lat: hit.latitude, lng: hit.longitude },
      "search",
      { zoom: GEO_ZOOM.street },
    );
  };

  return (
    <section
      className={`checkout-location${error ? " is-invalid" : ""}${confirmed ? " is-pinned" : ""}`}
      aria-label={t("location.pinAria")}
    >
      <button
        type="button"
        className={`checkout-location__hero${locating ? " is-busy" : ""}${confirmed && sourceRef.current === "gps" ? " is-done" : ""}`}
        disabled={disabled || locating}
        onClick={useGps}
      >
        {locating ? <IconSpinner size={16} /> : <IconLocate size={16} />}
        <span>
          <strong>
            {confirmed && sourceRef.current === "gps"
              ? t("location.shared")
              : t("location.useCurrent")}
          </strong>
          <em>
            {locating
              ? t("location.finding")
              : confirmed && sourceRef.current === "gps"
                ? t("location.fineTune")
                : t("location.fastest")}
          </em>
        </span>
      </button>

      <div className="checkout-location__search">
        <label className="checkout-location__search-field" htmlFor={searchId}>
          <IconSearch size={15} />
          <input
            ref={searchRef}
            id={searchId}
            type="search"
            value={query}
            disabled={disabled}
            placeholder={t("location.searchPlaceholder")}
            autoComplete="off"
            onChange={(e) => {
              setQuery(e.target.value);
              setSearchOpen(true);
            }}
            onFocus={() => hits.length > 0 && setSearchOpen(true)}
            onBlur={() => {
              window.setTimeout(() => setSearchOpen(false), 160);
            }}
          />
          {searching ? <IconSpinner size={14} /> : null}
        </label>
        {searchOpen && hits.length > 0 ? (
          <ul
            className="checkout-location__hits"
            role="listbox"
            aria-label={t("location.searchResults")}
          >
            {hits.map((hit) => (
              <li key={`${hit.latitude}-${hit.longitude}-${hit.place_name}`}>
                <button
                  type="button"
                  role="option"
                  disabled={disabled}
                  onMouseDown={(e) => e.preventDefault()}
                  onClick={() => chooseHit(hit)}
                >
                  <IconPin size={14} />
                  <span>
                    <strong>{hit.city || hit.line_1 || "Egypt"}</strong>
                    <em>{hit.place_name}</em>
                  </span>
                </button>
              </li>
            ))}
          </ul>
        ) : null}
      </div>

      <div
        ref={frameRef}
        className={`checkout-location__map${disabled ? " is-disabled" : ""}`}
        role="application"
        aria-label={t("location.mapAria")}
        tabIndex={disabled ? -1 : 0}
        onPointerDown={onPointerDown}
        onPointerUp={endDrag}
        onPointerCancel={endDrag}
        onKeyDown={(event) => {
          if (disabled) return;
          const step = event.shiftKey ? 80 : 36;
          if (event.key === "ArrowLeft") {
            event.preventDefault();
            scheduleResolve(panBy(step, 0), "map");
          } else if (event.key === "ArrowRight") {
            event.preventDefault();
            scheduleResolve(panBy(-step, 0), "map");
          } else if (event.key === "ArrowUp") {
            event.preventDefault();
            scheduleResolve(panBy(0, step), "map");
          } else if (event.key === "ArrowDown") {
            event.preventDefault();
            scheduleResolve(panBy(0, -step), "map");
          } else if (event.key === "+" || event.key === "=") {
            event.preventDefault();
            zoomBy(1);
          } else if (event.key === "-" || event.key === "_") {
            event.preventDefault();
            zoomBy(-1);
          }
        }}
      >
        <div className="checkout-location__tiles" aria-hidden="true">
          {tiles.map((tile) => (
            <img
              key={tile.key}
              src={tile.src}
              alt=""
              draggable={false}
              className="checkout-location__tile"
              style={{ transform: `translate(${tile.left}px, ${tile.top}px)` }}
            />
          ))}
        </div>

        <div className="checkout-location__pin" aria-hidden="true">
          <span className="checkout-location__pin-shadow" />
          <span className="checkout-location__pin-mark">
            <IconPin size={22} />
          </span>
        </div>

        <div className="checkout-location__controls">
          <button
            type="button"
            className="checkout-location__zoom"
            disabled={disabled || zoom >= GEO_ZOOM.max}
            onClick={() => zoomBy(1)}
            aria-label={t("location.zoomIn")}
          >
            +
          </button>
          <button
            type="button"
            className="checkout-location__zoom"
            disabled={disabled || zoom <= GEO_ZOOM.min}
            onClick={() => zoomBy(-1)}
            aria-label={t("location.zoomOut")}
          >
            −
          </button>
        </div>

        <div className="checkout-location__actions">
          {!confirmed ? (
            <button
              type="button"
              className="checkout-location__confirm"
              disabled={disabled || resolving}
              onClick={() => {
                void publish(centerRef.current, "map", {
                  zoom: Math.max(Math.round(zoomRef.current), GEO_ZOOM.street),
                });
              }}
            >
              {t("location.usePin")}
            </button>
          ) : null}
        </div>

        <p className="checkout-location__attrib">
          ©{" "}
          <a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noreferrer">
            OpenStreetMap
          </a>
        </p>
      </div>

      <div className="checkout-location__status" aria-live="polite">
        {resolving ? (
          <p className="checkout-location__status-row">
            <IconSpinner size={13} />
            {t("location.findingAddress")}
          </p>
        ) : confirmed ? (
          <p className="checkout-location__status-row">
            <IconPin size={13} />
            <span>
              <strong>{placeLabel || t("location.pinDropped")}</strong>
              <em>{formatCoords({ lat: value!.latitude, lng: value!.longitude })}</em>
            </span>
          </p>
        ) : (
          <p className="checkout-location__status-row checkout-location__status-row--hint">
            {t("location.manualHint")}
          </p>
        )}
        {gpsHint ? (
          <p className="checkout-location__hint" role="status">
            {gpsHint}
          </p>
        ) : null}
        {error ? (
          <p className="checkout-location__error" role="alert">
            {error}
          </p>
        ) : null}
      </div>
    </section>
  );
}
