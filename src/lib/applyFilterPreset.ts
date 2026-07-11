/** Shared helpers for applying admin filter presets on list pages. */

export const FILTER_ALL = "__all__";

export function presetText(value: unknown, fallback = ""): string {
  return typeof value === "string" ? value : fallback;
}

/** Select / enum preset value — empty or __all__ → fallback (usually "show all"). */
export function presetSelect(value: unknown, fallback = FILTER_ALL): string {
  if (typeof value === "string" && value !== "" && value !== FILTER_ALL) return value;
  return fallback;
}

export type SplitPresetResult = {
  custom: Record<string, string>;
  extra: Record<string, unknown>;
};

/** Split preset payload into custom-field filters vs extra client-side filters. */
export function splitPresetByFields(
  filters: Record<string, unknown>,
  viewKeys: readonly string[],
  customFieldKeys: ReadonlySet<string>,
): SplitPresetResult {
  const custom: Record<string, string> = {};
  const extra: Record<string, unknown> = {};
  const viewSet = new Set(viewKeys);
  for (const [k, v] of Object.entries(filters)) {
    if (v == null || v === "" || v === FILTER_ALL) continue;
    if (viewSet.has(k)) continue;
    if (customFieldKeys.has(k)) custom[k] = String(v);
    else extra[k] = v;
  }
  return { custom, extra };
}
