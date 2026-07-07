/** Les 24 gouvernorats tunisiens (ordre alphabétique français). */
export type TunisiaGovernorate = {
  /** Valeur stockée en base (MAJUSCULES, sans accents). */
  value: string;
  /** Libellé affiché dans l'interface. */
  label: string;
};

export const TUNISIA_GOVERNORATES: readonly TunisiaGovernorate[] = [
  { value: "ARIANA", label: "Ariana" },
  { value: "BEJA", label: "Béja" },
  { value: "BEN AROUS", label: "Ben Arous" },
  { value: "BIZERTE", label: "Bizerte" },
  { value: "GABES", label: "Gabès" },
  { value: "GAFSA", label: "Gafsa" },
  { value: "JENDOUBA", label: "Jendouba" },
  { value: "KAIROUAN", label: "Kairouan" },
  { value: "KASSERINE", label: "Kasserine" },
  { value: "KEBILI", label: "Kébili" },
  { value: "LE KEF", label: "Le Kef" },
  { value: "MAHDIA", label: "Mahdia" },
  { value: "MANOUBA", label: "Manouba" },
  { value: "MEDENINE", label: "Médenine" },
  { value: "MONASTIR", label: "Monastir" },
  { value: "NABEUL", label: "Nabeul" },
  { value: "SFAX", label: "Sfax" },
  { value: "SIDI BOUZID", label: "Sidi Bouzid" },
  { value: "SILIANA", label: "Siliana" },
  { value: "SOUSSE", label: "Sousse" },
  { value: "TATAOUINE", label: "Tataouine" },
  { value: "TOZEUR", label: "Tozeur" },
  { value: "TUNIS", label: "Tunis" },
  { value: "ZAGHOUAN", label: "Zaghouan" },
] as const;

export const TUNISIA_GOVERNORATE_VALUES: string[] = TUNISIA_GOVERNORATES.map((g) => g.value);

const VALUE_SET = new Set(TUNISIA_GOVERNORATE_VALUES);

/** Retire accents et normalise les espaces pour la recherche d'alias. */
function foldKey(v: string): string {
  return v
    .normalize("NFD")
    .replace(/[\u0300-\u036f]/g, "")
    .toUpperCase()
    .trim()
    .replace(/[-_]+/g, " ")
    .replace(/\s+/g, " ");
}

const ALIASES: Record<string, string> = {
  ARIANA: "ARIANA",
  BEJA: "BEJA",
  "BEN AROUS": "BEN AROUS",
  BENAROUS: "BEN AROUS",
  BIZERTE: "BIZERTE",
  GABES: "GABES",
  GAFSA: "GAFSA",
  JENDOUBA: "JENDOUBA",
  KAIROUAN: "KAIROUAN",
  KASSERINE: "KASSERINE",
  KEBILI: "KEBILI",
  KEF: "LE KEF",
  "LE KEF": "LE KEF",
  MAHDIA: "MAHDIA",
  MANOUBA: "MANOUBA",
  MEDENINE: "MEDENINE",
  MONASTIR: "MONASTIR",
  NABEUL: "NABEUL",
  SFAX: "SFAX",
  "SIDI BOUZID": "SIDI BOUZID",
  SIDIBOUZID: "SIDI BOUZID",
  SILIANA: "SILIANA",
  SOUSSE: "SOUSSE",
  TATAOUINE: "TATAOUINE",
  TOZEUR: "TOZEUR",
  TUNIS: "TUNIS",
  ZAGHOUAN: "ZAGHOUAN",
};

/** Normalise un gouvernorat saisi (alias, accents, casse). Retourne "" si vide. */
export function normalizeGouvernorat(v: unknown): string {
  if (v == null) return "";
  const raw = String(v).trim();
  if (raw === "") return "";
  const key = foldKey(raw);
  const compact = key.replace(/ /g, "");
  return ALIASES[key] ?? ALIASES[compact] ?? key;
}

export function isKnownGouvernorat(v: unknown): boolean {
  const n = normalizeGouvernorat(v);
  return n !== "" && VALUE_SET.has(n);
}

export function gouvernoratLabel(value: string): string {
  const n = normalizeGouvernorat(value);
  return TUNISIA_GOVERNORATES.find((g) => g.value === n)?.label ?? value;
}

export function gouvernoratSelectOptions(): { value: string; label: string }[] {
  return TUNISIA_GOVERNORATES.map((g) => ({ value: g.value, label: g.label }));
}
