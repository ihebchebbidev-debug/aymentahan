// Shared delete-with-cascade helper.
//
// Rule (per admin request):
//   - Contract   → if opportunityId  : revert_to_opportunity (contract removed, opportunity restored)
//                  else               : hard DELETE /contracts.php
//   - Migration  → if opportunityId  : revert_to_opportunity (migration removed, opportunity restored)
//                  else               : hard DELETE /migrations.php
//   - Opportunity→ if prospectId     : revert_to_prospect (opp removed, lead restored)
//                  else               : hard DELETE /opportunities.php
//   - Prospect   → always hard DELETE /prospects.php
//
// Returns { reverted: boolean, parentId?: string } so callers can navigate to the restored parent.

import { api } from "@/lib/api";
import { confirmDialog } from "@/components/ConfirmDialogProvider";

export type CascadeEntity = "prospect" | "opportunity" | "contract" | "migration";

type Row = {
  id: string;
  prospectId?: string | null;
  opportunityId?: string | null;
  firstName?: string;
  lastName?: string;
};

const LABEL: Record<CascadeEntity, string> = {
  prospect: "prospect",
  opportunity: "opportunité",
  contract: "contrat",
  migration: "migration",
};

export function cascadeDescription(entity: CascadeEntity, row: Row): string {
  const who = row.firstName || row.lastName ? `${row.firstName ?? ""} ${row.lastName ?? ""}`.trim() : row.id;
  if (entity === "contract" && row.opportunityId) {
    return `Supprimer le contrat ${who} et restaurer son opportunité d'origine ?`;
  }
  if (entity === "migration" && row.opportunityId) {
    return `Supprimer la migration ${who} et restaurer son opportunité d'origine ?`;
  }
  if (entity === "opportunity" && row.prospectId) {
    return `Supprimer l'opportunité ${who} et restaurer le prospect d'origine ?`;
  }
  return `Supprimer définitivement ${LABEL[entity]} ${who} ?`;
}

export async function confirmCascadeDelete(entity: CascadeEntity, row: Row): Promise<boolean> {
  return confirmDialog({
    title: "Suppression",
    description: cascadeDescription(entity, row),
    tone: "destructive",
    confirmText: (entity === "contract" && row.opportunityId) ||
                 (entity === "migration" && row.opportunityId) ||
                 (entity === "opportunity" && row.prospectId)
      ? "Supprimer et restaurer"
      : "Supprimer",
  });
}

export async function deleteWithCascade(
  entity: CascadeEntity,
  row: Row,
): Promise<{ reverted: boolean; parentId?: string | null }> {
  if (entity === "contract" && row.opportunityId) {
    const r = await api<{ opportunityId?: string }>("/contracts.php", {
      method: "POST",
      body: { action: "revert_to_opportunity", id: row.id },
    });
    return { reverted: true, parentId: r.opportunityId ?? row.opportunityId };
  }
  if (entity === "migration" && row.opportunityId) {
    const r = await api<{ opportunityId?: string }>("/migrations.php", {
      method: "POST",
      body: { action: "revert_to_opportunity", id: row.id },
    });
    return { reverted: true, parentId: r.opportunityId ?? row.opportunityId };
  }
  if (entity === "opportunity" && row.prospectId) {
    const r = await api<{ prospectId?: string }>("/opportunities.php", {
      method: "POST",
      body: { action: "revert_to_prospect", id: row.id },
    });
    return { reverted: true, parentId: r.prospectId ?? row.prospectId };
  }

  const endpoint =
    entity === "prospect" ? "/prospects.php" :
    entity === "opportunity" ? "/opportunities.php" :
    entity === "contract" ? "/contracts.php" :
    "/migrations.php";
  await api(`${endpoint}?id=${encodeURIComponent(row.id)}`, { method: "DELETE" });
  return { reverted: false };
}
