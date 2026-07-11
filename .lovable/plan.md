
## Analysis

Contracts are stored in MySQL table `crminternet_contracts` (backend PHP) and exposed via `backend/php/contracts.php` (GET list, POST create/import, PATCH update). The frontend model `Contract` lives in `src/lib/types.ts`, list + filters in `src/routes/contracts.index.tsx`, creation dialog in `src/components/NewContractDialog.tsx`, edit form in `src/routes/contracts.$contractId_.edit.tsx`, detail view in `src/routes/contracts.$contractId.tsx`, mapper in `src/lib/erpStore.tsx` (for `importContracts`).

The customer wants a **Débit (bandwidth)** dropdown with fixed options `10, 20, 30, 50, 100, Autre`. When `Autre` is chosen, a free numeric input appears. Stored as an integer Mbps (nullable).

## Deliverables

### 1. SQL migration (`backend/php/migration_contract_debit.sql`)

```sql
-- Add "débit" (Mbps) column to contracts
ALTER TABLE `crminternet_contracts`
  ADD COLUMN `debit` INT UNSIGNED NULL COMMENT 'Débit internet en Mbps' AFTER `premium`;

CREATE INDEX `idx_contract_debit` ON `crminternet_contracts` (`debit`);
```

Also add it to the schema repair spec in `backend/php/repair_schema.php` (`crminternet_contracts` block) so a fresh install auto-heals:
`'debit' => "INT UNSIGNED NULL"` and index `idx_contract_debit`.

### 2. Backend (`backend/php/contracts.php`)

- Extend `$listCols` to include `debit`.
- Map row → response: `'debit' => is_null($r['debit']) ? null : (int)$r['debit']`.
- PATCH: allow updating `debit` (integer or null), audit-log like `premium`.
- POST/import upsert: add `debit=VALUES(debit)` and bind `:debit`.
- Field-map for filters: `'debit' => 'debit'`.

### 3. Frontend types & store

- `src/lib/types.ts` → add `debit?: number | null` on `Contract`.
- `src/lib/erpStore.tsx` → thread `debit` through the contracts mapper and `importContracts` payload; add `updateContractDebit` (optional, mirrors `updateContractPremium`).

### 4. Reusable UI helper

New `src/components/DebitSelect.tsx`:

```text
DEBIT_PRESETS = [10, 20, 30, 50, 100]
Value: number | null
UI: <Select> with "10 Mbps" … "100 Mbps" + "Autre"
When Autre → show <Input type=number> next to it.
Emits number | null on change.
```

### 5. Forms

- `NewContractDialog.tsx` — add `<DebitSelect>` next to Cotisation, send `debit` in `importContracts([...])`.
- `contracts.$contractId_.edit.tsx` — load `contract.debit`, edit via `<DebitSelect>`, include in PATCH.
- `contracts.$contractId.tsx` — show "Débit : 50 Mbps" in the summary card.

### 6. Contracts list — column + dynamic filter

In `src/routes/contracts.index.tsx`:

- Add column `{ key: "debit", header: "Débit", accessor: (c) => c.debit ?? "", cell: (c) => c.debit ? `${c.debit} Mbps` : "—" }`.
- Add to `columnsMeta`/import mapping: `{ key: "debit", label: "Débit (Mbps)", sample: "50" }`.
- Dynamic filter: add `debit` to the filter schema with operator = select-multi over presets + "Autre", plus range (`debitMin` / `debitMax`) filtered locally like `premiumMin`.

### 7. Verification

- `bun run build`
- Load `/contracts`, create a contract with débit=50, filter list by débit, edit to `Autre = 75`, confirm persisted after reload.

## Notes

- `Autre` is a UI-only sentinel — persisted value is always numeric (or null).
- Existing contracts remain valid (nullable column, no backfill needed).
- No breaking API change: unknown field is ignored by older backends until the migration + PHP redeploy.
