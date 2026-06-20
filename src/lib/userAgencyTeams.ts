/** Canonical user.agency / user.team values (dashboard « Agence » filter). */
export const DEFAULT_USER_AGENCY_TEAMS = [
  "Agence Sousse",
  "Agence Tunis",
  "Backoffice",
  "Direction",
  "Lead-Actifs",
  "Lead-Premium",
  "Pôle 1",
  "Pôle 2",
] as const;

export const DEFAULT_USER_AGENCY_TEAM = "Lead-Actifs";

/** Merge catalog with teams already assigned to users (dashboard-style). */
export function mergeUserAgencyTeams(existing: Iterable<string | null | undefined>): string[] {
  const set = new Set<string>(DEFAULT_USER_AGENCY_TEAMS);
  for (const t of existing) {
    const v = (t ?? "").trim();
    if (v) set.add(v);
  }
  return Array.from(set).sort((a, b) => a.localeCompare(b, "fr"));
}
