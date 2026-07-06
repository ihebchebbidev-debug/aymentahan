import { RouterProvider } from "@tanstack/react-router";
import { StrictMode } from "react";
import { createRoot } from "react-dom/client";
import { getRouter } from "./router";
import "./styles.css";
import { hasUnsavedForms } from "./lib/unsavedForm";

// Handle "Failed to fetch dynamically imported module" — happens when a new
// build is deployed and the old index.html references chunk hashes that no
// longer exist on the server. Force a one-time hard reload so the browser
// fetches the fresh index.html and the new chunk hashes.
if (typeof window !== "undefined") {
  const RELOAD_KEY = "chunk_reload_at";
  const COOLDOWN_MS = 30_000;
  const isChunkError = (msg: unknown): boolean => {
    if (!msg) return false;
    const s = typeof msg === "string" ? msg : (msg as any)?.message ?? String(msg);
    return /Failed to fetch dynamically imported module|Importing a module script failed|ChunkLoadError|Loading chunk [\w-]+ failed|error loading dynamically imported module|reading 'component'/i.test(s);
  };
  const tryReload = (reason: string) => {
    if (hasUnsavedForms()) {
      console.warn("[chunk-reload] deferred (unsaved form):", reason);
      return;
    }
    const last = Number(sessionStorage.getItem(RELOAD_KEY) ?? 0);
    if (Date.now() - last < COOLDOWN_MS) {
      console.warn("[chunk-reload] suppressed (cooldown):", reason);
      return;
    }
    sessionStorage.setItem(RELOAD_KEY, String(Date.now()));
    console.warn("[chunk-reload] forcing reload:", reason);
    window.location.reload();
  };
  // Vite emits this CustomEvent when a preload <link rel=modulepreload> fails.
  window.addEventListener("vite:preloadError", (e: Event) => {
    e.preventDefault();
    tryReload("vite:preloadError");
  });
  window.addEventListener("error", (e) => {
    if (isChunkError(e.message) || isChunkError((e as any).error)) tryReload("error event");
  });
  window.addEventListener("unhandledrejection", (e) => {
    if (isChunkError(e.reason)) tryReload("unhandledrejection");
  });
}

const router = getRouter();

createRoot(document.getElementById("root")!).render(
  <StrictMode>
    <RouterProvider router={router} />
  </StrictMode>,
);
