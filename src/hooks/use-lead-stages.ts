import { useEffect, useState } from "react";
import { api, API_ENABLED } from "@/lib/api";
import { LEAD_STATUSES, type PipelineStage } from "@/lib/types";

export type LeadStageWithUsage = PipelineStage & {
  usageCount?: number;
};

let cache: LeadStageWithUsage[] | null = null;
let inflight: Promise<LeadStageWithUsage[]> | null = null;
const listeners = new Set<(s: LeadStageWithUsage[]) => void>();

export async function fetchLeadStages(): Promise<LeadStageWithUsage[]> {
  if (!API_ENABLED) return [];
  if (inflight) return inflight;
  inflight = api<{ stages: LeadStageWithUsage[] }>("/stages.php")
    .then((r) => {
      cache = (r.stages ?? []).slice().sort((a, b) => a.position - b.position);
      listeners.forEach((cb) => cb(cache!));
      return cache;
    })
    .catch(() => {
      cache = cache ?? [];
      return cache;
    })
    .finally(() => { inflight = null; });
  return inflight;
}

export async function refetchLeadStages(): Promise<LeadStageWithUsage[]> {
  cache = null;
  return fetchLeadStages();
}

/** Lead/prospect status stages, cached across components. */
export function useLeadStages(): LeadStageWithUsage[] {
  const [stages, setStages] = useState<LeadStageWithUsage[]>(cache ?? []);
  useEffect(() => {
    listeners.add(setStages);
    if (!cache) void fetchLeadStages();
    else setStages(cache);
    return () => { listeners.delete(setStages); };
  }, []);
  return stages;
}

/** Ordered status names from API, or LEAD_STATUSES fallback when offline. */
export function useLeadStatusNames(): string[] {
  const stages = useLeadStages();
  const names = stages.map((s) => s.name);
  for (const status of LEAD_STATUSES) {
    if (!names.includes(status)) names.push(status);
  }
  return names;
}

/** Predefined badge style variants matching color strings. */
export const STAGE_COLOR_CLASSES: Record<string, string> = {
  success: "bg-success/15 text-success border-success/20",
  warning: "bg-warning/15 text-warning-foreground border-warning/20",
  info: "bg-info/15 text-info border-info/20",
  destructive: "bg-destructive/10 text-destructive border-destructive/20",
  primary: "bg-primary/10 text-primary border-primary/20",
  muted: "bg-muted text-muted-foreground border-border",
  accent: "bg-accent text-accent-foreground border-accent",
};

/** Get badge class for a given stage name or color string */
export function getStageBadgeClass(colorOrName?: string): string {
  if (!colorOrName) return STAGE_COLOR_CLASSES.muted;
  if (STAGE_COLOR_CLASSES[colorOrName]) return STAGE_COLOR_CLASSES[colorOrName];
  return STAGE_COLOR_CLASSES.muted;
}
