import { useEffect, useRef } from "react";
import { toast } from "sonner";
import { hasUnsavedForms } from "@/lib/unsavedForm";

const STORAGE_KEY = "app_version_seen";
const RELOAD_GUARD_KEY = "app_version_last_reload";
const RELOAD_COOLDOWN_MS = 5 * 60_000;
const VERSION_URL = "/version.json";

/**
 * Detects new deployments via /version.json.
 * Never forces a reload while the user has an unsaved form open — shows a
 * toast with a manual "Recharger" action instead.
 */
export function VersionWatcher() {
  const currentVersion = useRef<string | null>(null);
  const reloading = useRef(false);
  const pendingVersion = useRef<string | null>(null);
  const toastShown = useRef(false);

  useEffect(() => {
    if (typeof window === "undefined") return;

    async function fetchVersion(): Promise<string | null> {
      try {
        const res = await fetch(`${VERSION_URL}?t=${Date.now()}`, {
          cache: "no-store",
          headers: { "cache-control": "no-cache" },
        });
        if (!res.ok) return null;
        const data = (await res.json()) as { version?: string };
        return data?.version ?? null;
      } catch {
        return null;
      }
    }

    function scheduleReload(v: string) {
      reloading.current = true;
      localStorage.setItem(STORAGE_KEY, v);
      localStorage.setItem(RELOAD_GUARD_KEY, String(Date.now()));
      toast.info("Nouvelle version disponible — rechargement…", { duration: 2000 });
      window.setTimeout(() => {
        window.location.reload();
      }, 1500);
    }

    function promptManualReload(v: string) {
      pendingVersion.current = v;
      if (toastShown.current) return;
      toastShown.current = true;
      toast.info("Nouvelle version disponible", {
        description: "Terminez votre saisie en cours, puis rechargez la page.",
        duration: Infinity,
        action: {
          label: "Recharger maintenant",
          onClick: () => scheduleReload(v),
        },
      });
    }

    async function check() {
      if (reloading.current) return;
      const v = await fetchVersion();
      if (!v) return;

      if (v === "dev") {
        currentVersion.current = v;
        return;
      }

      if (currentVersion.current === null) {
        const stored = localStorage.getItem(STORAGE_KEY);
        currentVersion.current = stored ?? v;
        if (!stored) localStorage.setItem(STORAGE_KEY, v);
        if (currentVersion.current === v) return;
      }

      if (v === currentVersion.current) {
        pendingVersion.current = null;
        toastShown.current = false;
        return;
      }

      const lastReload = Number(localStorage.getItem(RELOAD_GUARD_KEY) ?? 0);
      if (Date.now() - lastReload < RELOAD_COOLDOWN_MS) {
        currentVersion.current = v;
        return;
      }

      if (hasUnsavedForms()) {
        promptManualReload(v);
        return;
      }

      scheduleReload(v);
    }

    void check();
    const interval = window.setInterval(check, 60_000);
    const onFocus = () => void check();
    const onVisibility = () => {
      if (document.visibilityState === "visible") void check();
    };
    window.addEventListener("focus", onFocus);
    document.addEventListener("visibilitychange", onVisibility);

    return () => {
      window.clearInterval(interval);
      window.removeEventListener("focus", onFocus);
      document.removeEventListener("visibilitychange", onVisibility);
    };
  }, []);

  return null;
}
