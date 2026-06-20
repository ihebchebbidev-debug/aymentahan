import { api, ApiError } from "./api";
import { fetchAllPaginated } from "./paginatedFetch";
import type { Migration } from "./types";

export async function fetchMigrations(): Promise<Migration[]> {
  const cacheBuster = { _t: Date.now() };
  try {
    return await fetchAllPaginated<Migration>("/migrations.php", "migrations", {
      baseQuery: cacheBuster,
    });
  } catch (e) {
    const data = await api<unknown>(`/migrations.php?_t=${Date.now()}`).catch(() => null);
    if (data && typeof data === "object" && !Array.isArray((data as { migrations?: unknown }).migrations)) {
      throw new ApiError(
        "Le serveur ne répond pas correctement sur migrations.php. Déployez le module migration (repair_terminal_migrations.php).",
        502,
      );
    }
    throw e;
  }
}

export async function fetchMigrationById(id: string): Promise<Migration | null> {
  const r = await api<{ migration: Migration }>(`/migrations.php?id=${encodeURIComponent(id)}`);
  return r.migration ?? null;
}

export async function updateMigration(
  id: string,
  patch: Record<string, unknown>,
): Promise<Migration | null> {
  const r = await api<{ migration?: Migration | null }>("/migrations.php", {
    method: "PATCH",
    body: { id, ...patch },
  });
  return r.migration ?? null;
}
