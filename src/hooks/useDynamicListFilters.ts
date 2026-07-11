import { useCallback, useMemo } from "react";
import type { FilterFieldSchema } from "@/components/FilterPresetPicker";
import type { FilterPresetScope } from "@/lib/filterPresets";
import { FILTER_ALL, presetSelect, presetText } from "@/lib/applyFilterPreset";
import { DEFAULT_ENABLED_FILTER_KEYS } from "@/lib/listFilterEngine";
import { usePersistedState } from "@/hooks/use-persisted-state";

function normalizeValue(key: string, value: unknown): string {
  if (value == null) return "";
  const s = String(value);
  if (s === FILTER_ALL) return "";
  return s;
}

/** Merge preset payload into filter values and auto-enable fields that have values. */
export function presetToFilterValues(f: Record<string, unknown>): Record<string, string> {
  const out: Record<string, string> = {};
  for (const [k, v] of Object.entries(f)) {
    const norm = normalizeValue(k, v);
    if (norm !== "") out[k] = norm;
  }
  return out;
}

export function useDynamicListFilters(
  scope: FilterPresetScope,
  storageKey: string,
  schema: FilterFieldSchema[],
) {
  const schemaKeys = useMemo(() => new Set(schema.map((s) => s.key)), [schema]);

  const defaultEnabled = useMemo(() => {
    const wanted = DEFAULT_ENABLED_FILTER_KEYS[scope] ?? ["search"];
    return wanted.filter((k) => schemaKeys.has(k));
  }, [scope, schemaKeys]);

  const [enabledKeys, setEnabledKeys] = usePersistedState<string[]>(
    `${storageKey}:enabledFilters`,
    defaultEnabled,
  );
  const [values, setValues] = usePersistedState<Record<string, string>>(
    `${storageKey}:filterValues`,
    {},
  );

  const enabledSet = useMemo(() => new Set(enabledKeys), [enabledKeys]);

  const activeFields = useMemo(
    () => schema.filter((s) => enabledSet.has(s.key)),
    [schema, enabledSet],
  );

  const setValue = useCallback((key: string, val: string) => {
    setValues((prev) => {
      const next = { ...prev };
      const trimmed = val.trim();
      if (!trimmed || trimmed === FILTER_ALL) delete next[key];
      else next[key] = trimmed;
      return next;
    });
    if (val.trim() && val.trim() !== FILTER_ALL) {
      setEnabledKeys((prev) => (prev.includes(key) ? prev : [...prev, key]));
    }
  }, [setValues, setEnabledKeys]);

  const toggleField = useCallback((key: string, on: boolean) => {
    setEnabledKeys((prev) => {
      if (on) return prev.includes(key) ? prev : [...prev, key];
      return prev.filter((k) => k !== key);
    });
    if (!on) {
      setValues((prev) => {
        if (!(key in prev)) return prev;
        const next = { ...prev };
        delete next[key];
        return next;
      });
    }
  }, [setEnabledKeys, setValues]);

  const clearAll = useCallback(() => {
    setValues({});
  }, [setValues]);

  const applyPreset = useCallback((f: Record<string, unknown>) => {
    const next = presetToFilterValues(f);
    setValues(next);
    setEnabledKeys((prev) => {
      const set = new Set(prev);
      for (const k of Object.keys(next)) if (schemaKeys.has(k)) set.add(k);
      return [...set];
    });
  }, [setValues, setEnabledKeys, schemaKeys]);

  const hasActive = useMemo(
    () => Object.values(values).some((v) => v && v !== FILTER_ALL),
    [values],
  );

  const searchValue = values.search ?? "";

  return {
    values,
    setValues,
    setValue,
    enabledKeys,
    setEnabledKeys,
    toggleField,
    clearAll,
    applyPreset,
    activeFields,
    hasActive,
    searchValue,
    schemaKeys,
  };
}

/** Helpers for legacy preset apply paths that map view-state keys. */
export { presetText, presetSelect, FILTER_ALL };
