export type GeolocationFailureCode = 0 | 1 | 2 | 3;

export class GeolocationReadError extends Error {
  code: GeolocationFailureCode;

  constructor(code: GeolocationFailureCode, message: string) {
    super(message);
    this.name = "GeolocationReadError";
    this.code = code;
  }
}

function getCurrentPosition(options: PositionOptions): Promise<GeolocationPosition> {
  return new Promise((resolve, reject) => {
    navigator.geolocation.getCurrentPosition(resolve, reject, options);
  });
}

function watchForPosition(
  options: PositionOptions,
  timeoutMs: number,
): Promise<GeolocationPosition> {
  return new Promise((resolve, reject) => {
    let settled = false;
    const finish = (fn: () => void) => {
      if (settled) return;
      settled = true;
      fn();
    };

    const id = navigator.geolocation.watchPosition(
      (position) => {
        navigator.geolocation.clearWatch(id);
        clearTimeout(timer);
        finish(() => resolve(position));
      },
      (error) => {
        navigator.geolocation.clearWatch(id);
        clearTimeout(timer);
        finish(() => reject(error));
      },
      options,
    );

    const timer = setTimeout(() => {
      navigator.geolocation.clearWatch(id);
      finish(() => reject(new GeolocationReadError(3, "Location timed out.")));
    }, timeoutMs);
  });
}

/**
 * Best-effort browser location for checkout.
 * Tries network/Wi‑Fi fixes before GPS hardware (more reliable on laptops),
 * then falls back to a short watchPosition session.
 */
export async function readCheckoutGeolocation(): Promise<GeolocationPosition> {
  if (typeof navigator === "undefined" || !navigator.geolocation) {
    throw new GeolocationReadError(0, "Geolocation is not supported in this browser.");
  }

  const attempts: PositionOptions[] = [
    { enableHighAccuracy: false, timeout: 22_000, maximumAge: 300_000 },
    { enableHighAccuracy: true, timeout: 18_000, maximumAge: 0 },
    { enableHighAccuracy: false, timeout: 28_000, maximumAge: 900_000 },
  ];

  let lastError: GeolocationPositionError | GeolocationReadError | null = null;

  for (const options of attempts) {
    try {
      return await getCurrentPosition(options);
    } catch (error) {
      const geoError = error as GeolocationPositionError;
      lastError = geoError;
      if (geoError.code === geoError.PERMISSION_DENIED) {
        throw new GeolocationReadError(1, "Location permission denied.");
      }
    }
  }

  try {
    return await watchForPosition(
      { enableHighAccuracy: false, maximumAge: 300_000 },
      20_000,
    );
  } catch (error) {
    const geoError = error as GeolocationPositionError;
    if (geoError.code === geoError.PERMISSION_DENIED) {
      throw new GeolocationReadError(1, "Location permission denied.");
    }
    if (geoError.code === geoError.TIMEOUT) {
      throw new GeolocationReadError(3, "Location timed out.");
    }
    throw new GeolocationReadError(
      2,
      lastError?.message || "Could not determine your position.",
    );
  }
}

/** i18n message keys for geolocation failure hints — translate with `t(locale, key)`. */
export function geolocationHintKey(code: GeolocationFailureCode): string {
  switch (code) {
    case 0:
      return "geo.unsupported";
    case 1:
      return "geo.denied";
    case 3:
      return "geo.timeout";
    default:
      return "geo.unavailable";
  }
}

/** Alias: returns an i18n key (not English copy). Use with `t()`. */
export function geolocationHint(code: GeolocationFailureCode): string {
  return geolocationHintKey(code);
}
