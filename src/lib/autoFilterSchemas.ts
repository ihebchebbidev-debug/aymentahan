// Auto-generated filter schemas per scope.
// Single source of truth: pages pass the rows they already loaded plus a few
// dynamic lists (agents, stages, contract billing). Filter SELECT options are
// derived 100% from real data — no hardcoded fallback catalogues. If nothing
// has been configured / saved yet, the field falls back to a free-text input
// so the user can still type a value.
import type { FilterFieldSchema } from "@/components/FilterPresetPicker";
import type { FilterPresetScope } from "@/lib/filterPresets";
import { TUNISIA_GOVERNORATE_VALUES } from "@/lib/tunisiaGovernorates";

// --- helpers ---------------------------------------------------------------
function uniqStr(rows: ReadonlyArray<Record<string, unknown>>, key: string): string[] {
  const set = new Set<string>();
  for (const r of rows) {
    const v = r?.[key];
    if (v == null || v === "") continue;
    set.add(String(v));
  }
  return [...set];
}
const sortFr = (a: string, b: string) => a.localeCompare(b, "fr", { numeric: true });

/** Build a select field from real values only. If no values exist yet,
 *  return a free-text field so the admin can still type a value. */
function realField(
  label: string,
  key: string,
  rows: ReadonlyArray<Record<string, unknown>>,
  rowKey: string,
  extra: Iterable<string> = [],
  forceSelect: boolean = false,
): FilterFieldSchema {
  const set = new Set<string>();
  for (const v of extra) if (v) set.add(String(v));
  for (const v of uniqStr(rows, rowKey)) set.add(v);
  const vals = [...set].sort(sortFr);
  if (vals.length === 0 && !forceSelect) return { key, label, type: "text" };
  return { key, label, type: "select", options: vals.map((v) => ({ value: v, label: v })) };
}

/** Gouvernorat : toujours les 24 valeurs officielles + valeurs déjà en base. */
function gouvernoratField(
  rows: ReadonlyArray<Record<string, unknown>>,
): FilterFieldSchema {
  return realField("Gouvernorat", "gouvernorat", rows, "gouvernorat", TUNISIA_GOVERNORATE_VALUES, true);
}

export type AutoSchemaInput = {
  /** Rows already loaded by the page — used to derive 100% real values. */
  rows?: ReadonlyArray<Record<string, unknown>>;
  /** Agent usernames (assigne / assignedTo). Merged with values found in rows. */
  agents?: string[];
  /** Opportunity stage names. Merged with values found in rows. */
  opportunityStages?: string[];
  /** Contract billing statuses (configured pipeline). Merged with values found in rows. */
  contractBilling?: string[];
  /** Prospect status names (dynamic stages). Merged with values found in rows. */
  prospectStatuses?: string[];
  /** Prospect/Contract types, if available. */
  types?: { id: string; name: string }[];
  /** Custom-field definitions for the entity — appended so presets can filter on them. */
  customFields?: ReadonlyArray<{ key: string; label: string; type: string; options?: string[] }>;
};

/** Map a custom-field type to a preset-builder schema entry. */
function customFieldsToSchema(
  defs: ReadonlyArray<{ key: string; label: string; type: string; options?: string[] }> = [],
): FilterFieldSchema[] {
  return defs.map((d) => {
    const label = `★ ${d.label}`;
    if (d.type === "date") return { key: d.key, label, type: "date" };
    if (d.type === "boolean") return {
      key: d.key, label, type: "select",
      options: [{ value: "1", label: "Oui" }, { value: "0", label: "Non" }],
    };
    if ((d.type === "select" || d.type === "multiselect") && d.options && d.options.length > 0) {
      return { key: d.key, label, type: "select", options: d.options.map((v) => ({ value: v, label: v })) };
    }
    return { key: d.key, label, type: "text" };
  });
}

export function autoFilterSchema(
  scope: FilterPresetScope,
  input: AutoSchemaInput = {},
): FilterFieldSchema[] {
  const rows = input.rows ?? [];
  const types = (input.types ?? []).map((t) => ({ value: t.id, label: t.name }));
  const cfs = customFieldsToSchema(input.customFields);
  const withCf = (base: FilterFieldSchema[]) => cfs.length > 0 ? [...base, ...cfs] : base;

  const standardSources = ["Appel Entrant", "Fiche Contact", "Partenaire", "Facebook", "WhatsApp", "Recommandation", "Prospection Téléphonique", "Site Web", "Autre"];

  switch (scope) {
    case "prospects":
      return withCf([
        { key: "search", label: "Recherche (nom, prénom, tél, email, CIN)", type: "text" },
        realField("Statut d'appel", "statut", rows, "status", input.prospectStatuses ?? [], true),
        realField("Source", "source", rows, "source", standardSources, true),
        realField("Assigné à", "assigne", rows, "assignedTo", input.agents ?? [], true),
        realField("Civilité", "civility", rows, "civility", ["M", "Mme"], true),
        { key: "outcome", label: "Issue", type: "select", options: [
          { value: "pending", label: "En cours" },
          { value: "won", label: "Gagné" },
          { value: "lost", label: "Perdu" }
        ]},
        { key: "checkValeur", label: "Validation", type: "select", options: [
          { value: "pending", label: "En attente" },
          { value: "valid", label: "Validé" },
          { value: "invalid", label: "Rejeté" }
        ]},
        gouvernoratField(rows),
        realField("Délégation", "delegation", rows, "delegation"),
        realField("Ville", "city", rows, "city"),
        realField("Zone", "zone", rows, "zone"),
        realField("Code postal", "codePostal", rows, "codePostal"),
        { key: "address", label: "Adresse", type: "text" },
        { key: "phone", label: "Téléphone (GSM)", type: "text" },
        { key: "phone2", label: "Téléphone 2", type: "text" },
        { key: "email", label: "Email", type: "text" },
        { key: "cin", label: "CIN", type: "text" },
        { key: "typeId", label: "Type de prospect", type: "select",
          options: types.length ? types : uniqStr(rows, "typeId").sort(sortFr).map((v) => ({ value: v, label: v })) },
        { key: "converted", label: "Converti", type: "select",
          options: [{ value: "true", label: "Oui" }, { value: "false", label: "Non" }] },
        { key: "recoveredF", label: "Récupérés", type: "select", options: [
          { value: "any", label: "↩ Récupérés uniquement" },
          { value: "opportunity", label: "↩ Depuis opportunité" },
          { value: "contract", label: "↩ Depuis contrat" },
        ]},
        { key: "dateFrom", label: "Créé du", type: "date" },
        { key: "dateTo", label: "Créé au", type: "date" },
        { key: "dateCree", label: "Créé le (exact)", type: "date" },
        { key: "birthDate", label: "Date de naissance", type: "date" },
      ]);

    case "opportunities":
      return withCf([
        { key: "search", label: "Recherche (nom, ville, titre)", type: "text" },
        realField("Étape", "stage", rows, "stage", input.opportunityStages ?? [], true),
        realField("Assigné à", "assigne", rows, "assignedTo", input.agents ?? [], true),
        realField("Source", "source", rows, "source", standardSources, true),
        realField("Civilité", "civility", rows, "civility", ["M", "Mme"], true),
        gouvernoratField(rows),
        realField("Délégation", "delegation", rows, "delegation"),
        realField("Ville", "city", rows, "city"),
        realField("Code postal", "codePostal", rows, "codePostal"),
        { key: "address", label: "Adresse", type: "text" },
        { key: "phone", label: "Téléphone (GSM)", type: "text" },
        { key: "email", label: "Email", type: "text" },
        { key: "cin", label: "CIN", type: "text" },
        { key: "title", label: "Titre", type: "text" },
        { key: "amountMin", label: "Montant min", type: "text" },
        { key: "amountMax", label: "Montant max", type: "text" },
        { key: "probabilityMin", label: "Probabilité min (%)", type: "text" },
        { key: "typeId", label: "Type", type: "select",
          options: types.length ? types : uniqStr(rows, "typeId").sort(sortFr).map((v) => ({ value: v, label: v })) },
        { key: "convertedToContract", label: "Converti en contrat", type: "select",
          options: [{ value: "true", label: "Oui" }, { value: "false", label: "Non" }] },
        { key: "dateFrom", label: "Créée du", type: "date" },
        { key: "dateTo", label: "Créée au", type: "date" },
        { key: "dateCree", label: "Créée le (exact)", type: "date" },
        { key: "expectedCloseDate", label: "Date de clôture prévue", type: "date" },
      ]);

    case "migrations":
      return withCf([
        { key: "search", label: "Recherche (nom, téléphone, CIN, opérateurs)", type: "text" },
        realField("Workflow", "workflow", rows, "workflowStatus", ["Nouveau", "En attente document", "Soumis", "Approuvé", "Rejeté", "En cours de portage", "Complété", "Annulé"], true),
        realField("Statut technique", "technical", rows, "technicalStatus", ["En attente", "En cours", "OK", "KO", "Annulé"], true),
        realField("Ancien opérateur", "oldOp", rows, "oldOperator", ["Tunisie Telecom", "Ooredoo", "Orange", "Autre"], true),
        realField("Nouvel opérateur", "newOp", rows, "newOperator", ["Tunisie Telecom", "Ooredoo", "Orange", "Autre"], true),
        realField("Assigné à", "assigne", rows, "assignedTo", input.agents ?? [], true),
        { key: "dateFrom", label: "Créée du", type: "date" },
        { key: "dateTo", label: "Créée au", type: "date" },
        { key: "portingNumber", label: "N° portabilité", type: "text" },
        { key: "externalRef", label: "Réf. externe", type: "text" },
      ]);

    case "contracts":
      return withCf([
        { key: "search", label: "Recherche (nom, prénom, ville)", type: "text" },
        realField("Statut Facturation", "statut", rows, "billingStatus", input.contractBilling ?? ["Brouillon", "Actif", "Résilié", "Suspendu"], true),
        realField("Source", "source", rows, "source", standardSources, true),
        realField("Assigné à", "assigne", rows, "assignedTo", input.agents ?? [], true),
        realField("Civilité", "civility", rows, "civility", ["M", "Mme"], true),
        gouvernoratField(rows),
        realField("Délégation", "delegation", rows, "delegation"),
        realField("Ville", "city", rows, "city"),
        realField("Code postal", "codePostal", rows, "codePostal"),
        { key: "address", label: "Adresse", type: "text" },
        { key: "phone", label: "Téléphone (GSM)", type: "text" },
        { key: "email", label: "Email", type: "text" },
        { key: "cin", label: "CIN", type: "text" },
        { key: "premiumMin", label: "Cotisation min", type: "text" },
        { key: "premiumMax", label: "Cotisation max", type: "text" },
        { key: "debit", label: "Débit (Mbps)", type: "select", options: [
          { value: "10", label: "10 Mbps" },
          { value: "20", label: "20 Mbps" },
          { value: "30", label: "30 Mbps" },
          { value: "50", label: "50 Mbps" },
          { value: "100", label: "100 Mbps" },
        ]},
        { key: "debitMin", label: "Débit min (Mbps)", type: "text" },
        { key: "debitMax", label: "Débit max (Mbps)", type: "text" },
        { key: "typeId", label: "Type", type: "select",
          options: types.length ? types : uniqStr(rows, "typeId").sort(sortFr).map((v) => ({ value: v, label: v })) },
        { key: "dateSig", label: "Date Signature", type: "date" },
        { key: "dateEffet", label: "Date Effet", type: "date" },
        { key: "dateVal", label: "Date Validation", type: "date" },
        { key: "dateFrom", label: "Signature du", type: "date" },
        { key: "dateTo", label: "Signature au", type: "date" },
      ]);

    case "guichet":
      return withCf([
        { key: "search", label: "Recherche (réf, client, CIN)", type: "text" },
        realField("Entité", "entityId", rows, "entityId"),
        realField("Type d'opération", "type", rows, "type"),
        { key: "status", label: "Statut", type: "select", options: [
          { value: "draft", label: "Brouillon" }, { value: "valide", label: "Validé" },
        ] },
        realField("Agent", "agentId", rows, "agentId", input.agents ?? []),
        { key: "month", label: "Mois (YYYY-MM)", type: "text" },
        { key: "clientName", label: "Client (nom)", type: "text" },
        { key: "clientCin", label: "CIN", type: "text" },
        { key: "phone", label: "Téléphone / Numéro", type: "text" },
        realField("Offre", "offre", rows, "offre"),
        { key: "dateFrom", label: "Date début", type: "date" },
        { key: "dateTo", label: "Date fin", type: "date" },
      ]);

    case "reclamations":
      return withCf([
        { key: "search", label: "Recherche (client, tél, CIN, GSM, réf)", type: "text" },
        realField("Service", "service", rows, "service"),
        { key: "audit", label: "Audit", type: "select", options: [
          { value: "en_cours", label: "En cours" },
          { value: "resolu", label: "Résolu" },
          { value: "annule", label: "Annulé" },
        ] },
        realField("Statut CRM", "statut_crm", rows, "statut_crm"),
        realField("Statut TT", "statut_tt", rows, "statut_tt"),
        realField("Localisation", "localisation", rows, "localisation"),
        realField("État", "etat", rows, "etat"),
        realField("Assigné à", "assigned_to", rows, "assigned_to", input.agents ?? []),
        { key: "tel", label: "Tél ADSL", type: "text" },
        { key: "cin", label: "CIN client", type: "text" },
        { key: "gsm", label: "GSM client", type: "text" },
        { key: "ref", label: "Réf demande", type: "text" },
        { key: "client_name", label: "Client (nom)", type: "text" },
        { key: "mois", label: "Mois (1-12)", type: "text" },
        { key: "annee", label: "Année", type: "text" },
        { key: "date_creation", label: "Date création", type: "date" },
        { key: "date_resolution", label: "Date résolution", type: "date" },
      ]);
  }
}

/** Convenience: derive `filterKeys` from a generated schema. */
export const schemaKeys = (s: FilterFieldSchema[]) => s.map((f) => f.key);

