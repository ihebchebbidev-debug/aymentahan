import { api } from "./api";

export type BackofficeObjective = {
  id: string;
  scope: "agent" | "entity" | "global" | "role";
  agentId: string | null;
  entityId: string | null;
  roleName: string | null;
  periodMonth: string;
  targetContracts: number;
  targetMigrations: number;
  workingDays: number;
  notes: string;
};

export async function listBackofficeObjectives(query: Record<string, string | undefined> = {}): Promise<BackofficeObjective[]> {
  const r = await api<{ objectives: BackofficeObjective[] }>("/backoffice_objectives.php", { query });
  return r.objectives ?? [];
}

export const upsertBackofficeObjective = (body: Partial<BackofficeObjective>) =>
  api("/backoffice_objectives.php", { method: "POST", body });

export const deleteBackofficeObjective = (id: string) =>
  api("/backoffice_objectives.php", { method: "DELETE", query: { id } });

export const getBackofficeDashboard = (query: { month: string; entityId?: string; agentId?: string; roleName?: string }) =>
  api<{ month: string; targets: any; progress: any; contracts: any; migrations: any }>("/backoffice_dashboard.php", { query });
