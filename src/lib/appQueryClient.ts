import type { QueryClient } from "@tanstack/react-query";

/** Bound once in __root — lets erpStore invalidate React Query lists after mutations. */
let appQueryClient: QueryClient | null = null;

export function bindAppQueryClient(qc: QueryClient): void {
  appQueryClient = qc;
}

export function getAppQueryClient(): QueryClient | null {
  return appQueryClient;
}
