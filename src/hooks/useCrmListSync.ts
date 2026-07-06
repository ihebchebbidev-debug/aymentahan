import { useMemo } from "react";
import { useQueryClient } from "@/lib/queryClient";
import { useErp } from "@/lib/erpStore";
import {
  CRM_SYNC,
  syncAfterBulkOpportunityAuto,
  syncAfterBulkProspectAuto,
  syncAfterOpportunityAuto,
  syncAfterProspectAuto,
  syncCrmLists,
  type CrmEntity,
  type CrmSyncOpts,
} from "@/lib/crmListRefresh";

/** Binds query client + erp refresh for instant list updates after CRM mutations. */
export function useCrmListSync() {
  const qc = useQueryClient();
  const { refresh } = useErp();
  const opts: CrmSyncOpts = useMemo(() => ({ qc, refresh }), [qc, refresh]);

  return {
    opts,
    sync: (entities: CrmEntity[]) => syncCrmLists(entities, opts),
    afterProspectAuto: (auto: Parameters<typeof syncAfterProspectAuto>[0]) => syncAfterProspectAuto(auto, opts),
    afterOpportunityAuto: (auto: Parameters<typeof syncAfterOpportunityAuto>[0]) => syncAfterOpportunityAuto(auto, opts),
    afterBulkProspectAuto: (autos: Parameters<typeof syncAfterBulkProspectAuto>[0]) => syncAfterBulkProspectAuto(autos, opts),
    afterBulkOpportunityAuto: (autos: Parameters<typeof syncAfterBulkOpportunityAuto>[0]) => syncAfterBulkOpportunityAuto(autos, opts),
    ...CRM_SYNC,
  };
}
