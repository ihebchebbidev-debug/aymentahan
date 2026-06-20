import { api } from "./api";
import { fetchAllPaginated } from "./paginatedFetch";
import type { Opportunity } from "./types";

/** Load all opportunities — paginated first, legacy full-list fallback. */
export async function fetchAllOpportunities(signal?: AbortSignal): Promise<Opportunity[]> {
  try {
    const rows = await fetchAllPaginated<Opportunity>("/opportunities.php", "opportunities", {
      baseQuery: { _t: Date.now() },
      signal,
    });
    return Array.isArray(rows) ? rows : [];
  } catch {
    const r = await api<{ opportunities?: Opportunity[] | null }>("/opportunities.php", {
      signal,
      query: { _t: Date.now() },
    });
    const list = r?.opportunities;
    return Array.isArray(list) ? list : [];
  }
}
