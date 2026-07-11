## Approach

Add a **column visibility system** applied uniformly to the five list pages (Prospects, Contracts, Opportunities, Migrations, Guichet). Preferences persist to `localStorage`; the Excel/CSV/JSON export projects onto exactly the visible columns (base + custom fields).

## Deliverables

### 1. Shared infra

- **`src/lib/useColumnPrefs.ts`** — small hook
  ```
  useColumnPrefs(scope: string, allKeys: string[])
    → { visible: Set<string>, isVisible, toggle, reset, showAll, hideAll }
  ```
  Storage key: `crm.columns.<scope>` (localStorage). Default = all keys visible. Unknown legacy keys pruned on read.

- **`src/lib/exportUtils.ts`** — add `pickColumns(rows, labels)`: keeps only listed labeled columns, preserving order.

### 2. Enhanced picker

Rewrite `src/components/CustomColumnsPicker.tsx` to accept both **base columns** (`{key,label}[]`) and **custom fields**, with two clearly-labeled sections, a "Tout / Aucun / Réinitialiser" toolbar, and a counter chip. Same import path → no churn on the pages that already import it.

### 3. Per-page wiring (identical shape × 5 pages)

For `prospects.index.tsx`, `contracts.index.tsx`, `opportunities.index.tsx`, `migrations.index.tsx`, `guichet.tsx`:

1. Declare `BASE_COLS: {key, label}[]` matching the DataGrid columns already there (label = header + a stable export label when they differ).
2. `const cols = useColumnPrefs("<scope>", [...BASE_COLS.map(c=>c.key), ...customDefs.map(d=>d.key)])`
3. Replace `<CustomColumnsPicker>` call with the new signature (`baseCols` + `defs`).
4. DataGrid `columns={[...baseColumns, ...customColumns].filter(c => cols.isVisible(c.key))}`.
5. Export builders: apply `pickColumns(relabelledRows, visibleLabels)` before `exportXLSX/CSV/JSON`.

### 4. Verification

`bun run build`; then in Playwright: open `/contracts`, hide "Partenaire", export XLSX, confirm "Partenaire" absent; refresh page and confirm the hidden state persists.

## Non-goals

- Column reorder (drag) — out of scope; existing order preserved.
- Server-side persistence — client localStorage only.
