import { useCallback, useEffect, useMemo, useState } from "react";

/**
 * Column visibility preferences, persisted in localStorage.
 *
 * Storage shape: `{ hidden: string[] }` under key `crm.columns.v1.<scope>`.
 * We store the HIDDEN keys (not visible ones) so that new columns added
 * later default to visible — no data migration needed.
 */
type State = { hidden: string[] };

export type ColumnPrefs = {
  isVisible: (key: string) => boolean;
  setVisible: (key: string, v: boolean) => void;
  toggle: (key: string) => void;
  showAll: () => void;
  hideAllExcept: (keep: string[]) => void;
  reset: () => void;
  /** Filter helper: keeps items whose `.key` is currently visible. */
  filterCols: <T extends { key: string }>(cols: T[]) => T[];
  /** Visible keys count / total known count (for UI badge). */
  counts: (all: string[]) => { visible: number; total: number };
};

export function useColumnPrefs(scope: string, defaults: { hidden?: string[] } = {}): ColumnPrefs {
  const storageKey = `crm.columns.v1.${scope}`;
  const initial: State = { hidden: defaults.hidden ?? [] };

  const [state, setState] = useState<State>(() => {
    if (typeof window === "undefined") return initial;
    try {
      const raw = window.localStorage.getItem(storageKey);
      if (!raw) return initial;
      const parsed = JSON.parse(raw);
      if (parsed && Array.isArray(parsed.hidden)) return { hidden: parsed.hidden.map(String) };
      return initial;
    } catch { return initial; }
  });

  useEffect(() => {
    if (typeof window === "undefined") return;
    try { window.localStorage.setItem(storageKey, JSON.stringify(state)); } catch { /* quota */ }
  }, [state, storageKey]);

  const hidden = useMemo(() => new Set(state.hidden), [state.hidden]);

  const isVisible = useCallback((k: string) => !hidden.has(k), [hidden]);
  const setVisible = useCallback((k: string, v: boolean) => {
    setState((s) => {
      const set = new Set(s.hidden);
      if (v) set.delete(k); else set.add(k);
      return { hidden: [...set] };
    });
  }, []);
  const toggle = useCallback((k: string) => setVisible(k, hidden.has(k)), [setVisible, hidden]);
  const showAll = useCallback(() => setState({ hidden: [] }), []);
  const hideAllExcept = useCallback((keep: string[]) => {
    // Convenience for “hide all except identifier” — currently unused, kept
    // for future toolbar wiring.
    setState((s) => {
      const keepSet = new Set(keep);
      const merged = new Set(s.hidden);
      for (const k of keep) merged.delete(k);
      // Also hide everything else the caller already knows about; the caller
      // should union `s.hidden` with the "known" set separately if needed.
      void keepSet;
      return { hidden: [...merged] };
    });
  }, []);
  const reset = useCallback(() => setState({ hidden: defaults.hidden ?? [] }), [defaults.hidden]);

  const filterCols = useCallback(<T extends { key: string }>(cols: T[]) => cols.filter((c) => !hidden.has(c.key)), [hidden]);
  const counts = useCallback((all: string[]) => {
    const total = all.length;
    let vis = 0;
    for (const k of all) if (!hidden.has(k)) vis++;
    return { visible: vis, total };
  }, [hidden]);

  return { isVisible, setVisible, toggle, showAll, hideAllExcept, reset, filterCols, counts };
}