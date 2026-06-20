import { useEffect, useState } from "react";
import { api, API_ENABLED } from "@/lib/api";
import type { PipelineStage } from "@/lib/types";

/** Fallback when API not ready — matches contract terminal stages. */
export const DEFAULT_MIGRATION_STAGES: PipelineStage[] = [
  { id: "MS-1", name: "Créer", color: "info", position: 1, isInitial: true, isWon: false, isLost: false, autoAction: "none" },
  { id: "MS-2", name: "Retour", color: "destructive", position: 2, isInitial: false, isWon: false, isLost: true, autoAction: "none" },
  { id: "MS-3", name: "Mes non connecté", color: "warning", position: 3, isInitial: false, isWon: false, isLost: false, autoAction: "none" },
  { id: "MS-4", name: "Validé", color: "success", position: 4, isInitial: false, isWon: true, isLost: false, autoAction: "none" },
];

let cache: PipelineStage[] | null = null;
let inflight: Promise<PipelineStage[]> | null = null;
const listeners = new Set<(s: PipelineStage[]) => void>();

async function fetchStages(): Promise<PipelineStage[]> {
  if (!API_ENABLED) return DEFAULT_MIGRATION_STAGES;
  if (inflight) return inflight;
  inflight = api<{ stages: PipelineStage[] }>("/crm_migration_stages.php")
    .then((r) => {
      const rows = (r.stages ?? []).slice().sort((a, b) => a.position - b.position);
      cache = rows.length ? rows : DEFAULT_MIGRATION_STAGES;
      listeners.forEach((cb) => cb(cache!));
      return cache;
    })
    .catch(() => {
      cache = cache?.length ? cache : DEFAULT_MIGRATION_STAGES;
      listeners.forEach((cb) => cb(cache!));
      return cache;
    })
    .finally(() => { inflight = null; });
  return inflight;
}

/** Migration pipeline stages, cached across components. */
export function useMigrationStages(): PipelineStage[] {
  const [stages, setStages] = useState<PipelineStage[]>(cache ?? DEFAULT_MIGRATION_STAGES);
  useEffect(() => {
    listeners.add(setStages);
    if (!cache) void fetchStages();
    else setStages(cache);
    return () => { listeners.delete(setStages); };
  }, []);
  return stages.length ? stages : DEFAULT_MIGRATION_STAGES;
}
