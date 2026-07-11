import type { FilterPresetScope } from "@/lib/filterPresets";
import { FILTER_ALL } from "@/lib/applyFilterPreset";

/** Schema filter key → row property name (camelCase entity fields). */
export const LIST_FILTER_FIELD_ALIASES: Partial<Record<FilterPresetScope, Record<string, string>>> = {
  prospects: {
    statut: "status",
    assigne: "assignedTo",
    typeF: "typeId",
    typeId: "typeId",
    dateCree: "createdAt",
    createdAt: "createdAt",
    checkValeur: "checkValeur",
    codePostal: "codePostal",
  },
  opportunities: {
    stage: "stage",
    assigne: "assignedTo",
    dateCree: "createdAt",
    createdAt: "createdAt",
  },
  contracts: {
    statut: "billingStatus",
    partenaire: "partner",
    assigne: "assignedTo",
    dateSig: "signatureDate",
    dateEffet: "effectiveDate",
    dateVal: "validationDate",
  },
  migrations: {
    workflow: "workflowStatus",
    technical: "technicalStatus",
    oldOp: "oldOperator",
    newOp: "newOperator",
    assigne: "assignedTo",
  },
};

export type ListFilterMatchContext = {
  scope: FilterPresetScope;
  /** Lowercased haystack for free-text search. */
  haystack?: string;
  /** Custom-field values for the row (entity-specific). */
  customValues?: Record<string, string>;
  customFieldKeys?: Set<string>;
};

/** Which row date field `dateFrom` / `dateTo` compare against. */
const DATE_RANGE_FIELD: Partial<Record<FilterPresetScope, string>> = {
  prospects: "createdAt",
  opportunities: "createdAt",
  contracts: "signatureDate",
  migrations: "createdAt",
};

function isEmptyFilterValue(v: unknown): boolean {
  return v == null || v === "" || v === FILTER_ALL;
}

function rowField(row: Record<string, unknown>, key: string, scope: FilterPresetScope): unknown {
  const aliases = LIST_FILTER_FIELD_ALIASES[scope] ?? {};
  const field = aliases[key] ?? key;
  return row[field];
}

function matchScalarField(rowVal: unknown, target: string): boolean {
  if (rowVal == null) return false;
  if (typeof rowVal === "boolean") return String(rowVal) === target;
  return String(rowVal).toLowerCase().includes(target.toLowerCase());
}

/**
 * Returns true when a row satisfies all active filter values.
 * Only keys present in `values` with non-empty values are evaluated.
 */
export function rowMatchesListFilters(
  row: Record<string, unknown>,
  values: Record<string, string>,
  ctx: ListFilterMatchContext,
): boolean {
  for (const [key, raw] of Object.entries(values)) {
    if (isEmptyFilterValue(raw)) continue;

    if (key === "search") {
      const q = raw.trim().toLowerCase();
      if (q && !(ctx.haystack ?? "").includes(q)) return false;
      continue;
    }

    if (key === "dateFrom") {
      const dateField = DATE_RANGE_FIELD[ctx.scope] ?? "createdAt";
      const d = String(row[dateField] ?? row.created_at ?? row.date_creation ?? "").slice(0, 10);
      if (d && d < raw) return false;
      continue;
    }
    if (key === "dateTo") {
      const dateField = DATE_RANGE_FIELD[ctx.scope] ?? "createdAt";
      const d = String(row[dateField] ?? row.created_at ?? row.date_creation ?? "").slice(0, 10);
      if (d && d > raw) return false;
      continue;
    }
    if (key === "dateCree" || key === "createdAt") {
      const created = String(row.createdAt ?? row.created_at ?? "").slice(0, 10);
      if (created !== raw) return false;
      continue;
    }

    if (key === "amountMin") {
      if (Number(row.amount ?? 0) < Number(raw)) return false;
      continue;
    }
    if (key === "amountMax") {
      if (Number(row.amount ?? 0) > Number(raw)) return false;
      continue;
    }
    if (key === "probabilityMin") {
      if (Number(row.probability ?? 0) < Number(raw)) return false;
      continue;
    }
    if (key === "premiumMin") {
      if (Number(row.premium ?? 0) < Number(raw)) return false;
      continue;
    }
    if (key === "premiumMax") {
      if (Number(row.premium ?? 0) > Number(raw)) return false;
      continue;
    }

    if (key === "revertedFrom") {
      if (!row.revertedAt) return false;
      if (raw !== "any" && row.revertedFrom !== raw) return false;
      continue;
    }

    if (key === "converted" || key === "convertedToContract") {
      const want = raw === "true" || raw === "1";
      const val = Boolean(row[key === "converted" ? "converted" : "convertedToContract"] ?? row.converted_to_contract);
      if (val !== want) return false;
      continue;
    }

    if (ctx.customFieldKeys?.has(key)) {
      const v = String(ctx.customValues?.[key] ?? "").toLowerCase();
      if (!v.includes(raw.toLowerCase())) return false;
      continue;
    }

    const val = rowField(row, key, ctx.scope);
    if (!matchScalarField(val, raw)) return false;
  }
  return true;
}

/** Default visible filter fields per list (user can change via checkboxes — persisted). */
export const DEFAULT_ENABLED_FILTER_KEYS: Partial<Record<FilterPresetScope, string[]>> = {
  prospects: ["search", "statut", "source", "assigne"],
  opportunities: ["search", "stage", "source", "assigne"],
  contracts: ["search", "statut", "partenaire", "assigne"],
  migrations: ["search", "workflow", "assigne"],
};
