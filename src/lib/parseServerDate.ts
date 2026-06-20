/** Parse PHP/MySQL datetimes or ISO strings reliably in all browsers. */
export function parseServerDate(value: string | null | undefined): number {
  if (!value) return NaN;
  const s = String(value).trim();
  if (!s) return NaN;
  // ISO / ATOM
  let t = Date.parse(s);
  if (Number.isFinite(t)) return t;
  // MySQL "YYYY-MM-DD HH:MM:SS" → local time
  t = Date.parse(s.replace(" ", "T"));
  if (Number.isFinite(t)) return t;
  // UTC fallback
  t = Date.parse(s.replace(" ", "T") + "Z");
  return Number.isFinite(t) ? t : NaN;
}
