import type { QueryClient } from "@tanstack/react-query";
import { getAppQueryClient } from "./appQueryClient";

export type CrmEntity = "prospects" | "opportunities" | "contracts" | "migrations";

export type CrmSyncOpts = {
  qc?: QueryClient | null;
  /** erpStore refresh — reloads prospects + contracts in global store */
  refresh?: () => Promise<void>;
};

type AutoPayload = {
  executed?: boolean;
  opportunityId?: string;
  contractId?: string;
  migrationId?: string;
  created?: boolean;
} | null | undefined;

function qcOf(opts?: CrmSyncOpts): QueryClient | null {
  return opts?.qc ?? getAppQueryClient();
}

/** Refresh ERP store + invalidate React Query caches for the given entities. */
export async function syncCrmLists(entities: CrmEntity[], opts?: CrmSyncOpts): Promise<void> {
  const qc = qcOf(opts);
  const tasks: Promise<unknown>[] = [];

  if (opts?.refresh && entities.some((e) => e === "prospects" || e === "contracts")) {
    tasks.push(opts.refresh());
  }
  if (entities.includes("opportunities") && qc) {
    tasks.push(qc.invalidateQueries({ queryKey: ["opportunities"] }));
  }
  if (entities.includes("contracts") && qc) {
    tasks.push(qc.invalidateQueries({ queryKey: ["contracts"] }));
  }
  if (entities.includes("migrations") && qc) {
    tasks.push(qc.invalidateQueries({ queryKey: ["migrations"] }));
  }

  await Promise.all(tasks);
}

export async function syncAfterProspectAuto(auto: AutoPayload, opts?: CrmSyncOpts): Promise<void> {
  if (!auto?.executed || auto.created === false) return;
  if (auto.contractId) {
    await syncCrmLists(["prospects", "opportunities", "contracts"], opts);
    return;
  }
  if (auto.opportunityId) {
    await syncCrmLists(["prospects", "opportunities"], opts);
  }
}

export async function syncAfterOpportunityAuto(auto: AutoPayload, opts?: CrmSyncOpts): Promise<void> {
  if (!auto?.executed || auto.created === false) return;
  if (auto.contractId) {
    await syncCrmLists(["prospects", "opportunities", "contracts"], opts);
    return;
  }
  if (auto.migrationId) {
    await syncCrmLists(["opportunities", "migrations"], opts);
  }
}

/** After bulk prospect status change — prospects already refreshed via erpStore.refresh(). */
export async function syncAfterBulkProspectAuto(
  autos: Record<string, AutoPayload> | null | undefined,
  opts?: CrmSyncOpts,
): Promise<void> {
  if (!autos) return;
  const extra = new Set<CrmEntity>();
  for (const auto of Object.values(autos)) {
    if (!auto?.executed || auto.created === false) continue;
    if (auto.contractId) {
      extra.add("opportunities");
      extra.add("contracts");
    } else if (auto.opportunityId) {
      extra.add("opportunities");
    }
  }
  if (!extra.size) return;
  await syncCrmLists([...extra], opts);
}

/** After bulk opportunity stage change — opportunities list already reloaded. */
export async function syncAfterBulkOpportunityAuto(
  autos: AutoPayload[],
  opts?: CrmSyncOpts,
): Promise<void> {
  const extra = new Set<CrmEntity>();
  for (const auto of autos) {
    if (!auto?.executed || auto.created === false) continue;
    if (auto.contractId) {
      extra.add("prospects");
      extra.add("contracts");
    } else if (auto.migrationId) {
      extra.add("migrations");
    }
  }
  if (!extra.size) return;
  await syncCrmLists([...extra], opts);
}

export const CRM_SYNC = {
  newProspect: (opts?: CrmSyncOpts) => syncCrmLists(["prospects"], opts),
  prospectToOpportunity: (opts?: CrmSyncOpts) => syncCrmLists(["prospects", "opportunities"], opts),
  toContract: (opts?: CrmSyncOpts) => syncCrmLists(["prospects", "opportunities", "contracts"], opts),
  toMigration: (opts?: CrmSyncOpts) => syncCrmLists(["opportunities", "migrations"], opts),
  markWon: (opts?: CrmSyncOpts) => syncCrmLists(["prospects", "contracts"], opts),
  revertOpportunity: (opts?: CrmSyncOpts) => syncCrmLists(["prospects", "opportunities"], opts),
  revertContract: (opts?: CrmSyncOpts) => syncCrmLists(["prospects", "opportunities", "contracts"], opts),
  revertMigration: (opts?: CrmSyncOpts) => syncCrmLists(["opportunities", "migrations"], opts),
};
