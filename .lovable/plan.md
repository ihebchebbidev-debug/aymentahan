# Admin edit/delete everywhere — with cascade revert

The audit shows most pieces already exist. This plan fills the gaps only.

## What's already in place (no work)
- Row delete + bulk delete + selection: Prospects, Opportunities, Contracts lists.
- Edit button in the header of every detail page.
- Delete permissions declared (`prospect.delete`, `opportunity.delete`, `contract.delete`, `migration.delete`).
- Confirm dialog, PageHeader, revert endpoints (`revert_to_opportunity`, `revert_to_prospect`).

## Gaps to fix

### 1. Cascade-revert on delete (your rule "delete contract → back to opportunity")
Introduce a shared helper `deleteWithCascade(entity, row)`:
- **Contract**: if `opportunityId` → `POST /contracts.php { action:"revert_to_opportunity" }` (contract removed, opportunity restored). Else `DELETE /contracts.php?id=`.
- **Migration**: if `opportunityId` → `POST /migrations.php { action:"revert_to_opportunity" }`. Else `DELETE /migrations.php?id=`.
- **Opportunity**: if `prospectId` → `POST /opportunities.php { action:"revert_to_prospect" }` (opp removed, prospect restored). Else `DELETE /opportunities.php?id=`.
- **Prospect**: always `DELETE /prospects.php?id=`.

Confirm dialog wording adapts: "Supprimer ce contrat et restaurer son opportunité d'origine ?" vs "Supprimer définitivement ?".

Existing row/bulk delete handlers in the 3 lists get rewired to this helper. Bulk still batches. Migrations gets a brand-new implementation using the same helper.

### 2. Migrations list — bring to parity
Add: `canDelete` flag, selection state + checkbox column, Edit + Delete row actions, bulk-delete toolbar. Mirrors `contracts.index.tsx` exactly.

### 3. Add "Modifier" (pencil) row action in all 4 lists
Route to `/{entity}/$id/edit`. Gated on `entity.edit`. Placed just after "Ouvrir la fiche".

### 4. Add "Supprimer" button in the header of all 4 detail pages
Gated on `entity.delete`. Uses the same cascade-revert helper. On success navigates back to the list (or to the restored parent for contract/opp/migration when reverted).

### 5. Full-info edit pages (100% coverage)
Audit found only one true gap:
- `contracts.$contractId_.edit.tsx` keeps `partner` and `cabinet` in state but doesn't render inputs. Re-expose both as text inputs in the "Détails du contrat" section.

All other edit forms already cover every user-editable field on their type; the remaining unexposed properties are system/audit (`id`, `createdAt`, `convertedToContract`, `revertedAt`, …) which must stay read-only.

## Files touched
- `src/lib/entityDelete.ts` *(new)* — `deleteWithCascade` helper + confirm wording.
- `src/routes/migrations.index.tsx` — selection, bulk toolbar, row Edit + Delete.
- `src/routes/prospects.index.tsx`, `opportunities.index.tsx`, `contracts.index.tsx` — add Edit row action; swap delete handlers to helper.
- `src/routes/prospects.$prospectId.tsx`, `opportunities.$opportunityId.tsx`, `contracts.$contractId.tsx`, `migrations.$migrationId.tsx` — add Delete button in `PageHeader.actions`.
- `src/routes/contracts.$contractId_.edit.tsx` — render `partner` + `cabinet` inputs.

## Technical notes
- Permissions: `Can` component / `hasPermission` — admins pass automatically (per `Can.tsx`).
- Toast + list refresh reuses existing `useCrmListSync` / `refresh*` from `erpStore`.
- No backend or schema changes; all endpoints already exist.
- No changes to types, RLS, or business logic outside the delete/edit surface.
