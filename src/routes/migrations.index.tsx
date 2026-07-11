import { createFileRoute, useNavigate } from "@tanstack/react-router";
import { zodValidator, fallback } from "@tanstack/zod-adapter";
import { z } from "zod";
import { useEffect } from "react";
import { AppLayout } from "@/components/AppLayout";
import { PageHeader } from "@/components/PageHeader";
import { ArrowRightLeft, Search, X, Eye, Pencil, Trash2, Download, FileSpreadsheet, FileJson } from "lucide-react";
import { Card } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Badge } from "@/components/ui/badge";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { DataGrid, type DataGridColumn } from "@/components/DataGrid";
import { useAuth } from "@/lib/auth";
import { canViewMigrationsData } from "@/lib/permissions";
import { API_ENABLED } from "@/lib/api";
import { useQuery } from "@/lib/queryClient";
import { fetchMigrations } from "@/lib/migrationsApi";
import { useErp } from "@/lib/erpStore";
import type { Migration } from "@/lib/types";
import { useMemo, useState } from "react";
import { usePersistedState } from "@/hooks/use-persisted-state";
import { useDebouncedValue } from "@/hooks/use-debounced-value";
import { DynamicFilterBar } from "@/components/DynamicFilterBar";
import { autoFilterSchema, schemaKeys } from "@/lib/autoFilterSchemas";
import { exportCSV, exportJSON, exportXLSX, withCustomFields } from "@/lib/exportUtils";
import { toast } from "sonner";
import { DatePicker } from "@/components/ui/date-picker";
import { useMigrationStages } from "@/hooks/use-migration-stages";
import { useCustomFieldsTable, formatCustomValue } from "@/lib/useCustomFields";
import { useColumnPrefs } from "@/lib/useColumnPrefs";
import { pickColumns } from "@/lib/exportUtils";
import { CustomColumnsPicker } from "@/components/CustomColumnsPicker";
import { confirmDialog } from "@/components/ConfirmDialogProvider";
import { deleteWithCascade, confirmCascadeDelete } from "@/lib/entityDelete";
import { useCrmListSync } from "@/hooks/useCrmListSync";

export const Route = createFileRoute("/migrations/")({
  validateSearch: zodValidator(z.object({
    statut: fallback(z.string().optional(), undefined),
  })),
  head: () => ({
    meta: [
      { title: "Migrations — CRM" },
      { name: "description", content: "Dossiers de migration opérateur (terminal du pipeline commercial)." },
    ],
  }),
  component: MigrationsPage,
});

const ALL = "__all__";
const PAGE_SIZE = 50;

const workflowColor: Record<string, string> = {
  "Créer": "bg-info/15 text-info border-info/20",
  "Retour": "bg-destructive/15 text-destructive border-destructive/20",
  "Mes non connecté": "bg-warning/15 text-warning-foreground border-warning/20",
  "Validé": "bg-success/15 text-success border-success/20",
};

function MigrationsPage() {
  const navigate = useNavigate();
  const { statut: urlStatut } = Route.useSearch();
  const migrationStages = useMigrationStages();
  const STATUTS = migrationStages.map((s) => s.name);
  const { user, hasPermission } = useAuth();
  const { users: erpUsers } = useErp();
  const agentOptions = useMemo(
    () =>
      erpUsers
        .filter((u) =>
          ["Agent", "Manager", "AgentSuivi", "AgentActivation", "AgentVente", "Backoffice"].includes(u.role),
        )
        .map((u) => u.username),
    [erpUsers],
  );

  const canLoad = canViewMigrationsData(hasPermission);

  const migrationsQ = useQuery<Migration[]>({
    queryKey: ["migrations"],
    queryFn: fetchMigrations,
    enabled: API_ENABLED && !!user && canLoad,
  });
  const allMigrations = migrationsQ.data ?? [];
  const canExport = hasPermission("migration.export");
  const canEdit = hasPermission("migration.edit");
  const canDelete = hasPermission("migration.delete");
  const { revertMigration } = useCrmListSync();
  const [selected, setSelected] = useState<Set<string>>(new Set());
  const [bulkBusy, setBulkBusy] = useState(false);
  const refresh = async () => { await migrationsQ.refetch(); };

  const userScope = user?.username ?? "anon";
  const pk = (k: string) => `migrations:list:${userScope}:${k}`;
  const [search, setSearch] = usePersistedState(pk("search"), "");
  const [statut, setStatut] = usePersistedState(pk("statut"), ALL);
  const [technical, setTechnical] = usePersistedState(pk("technical"), ALL);
  const [oldOp, setOldOp] = usePersistedState(pk("oldOp"), ALL);
  const [newOp, setNewOp] = usePersistedState(pk("newOp"), ALL);
  const [assigne, setAssigne] = usePersistedState(pk("assigne"), ALL);
  const [dateFrom, setDateFrom] = usePersistedState(pk("dateFrom"), "");
  const [dateTo, setDateTo] = usePersistedState(pk("dateTo"), "");
  const [presetExtra, setPresetExtra] = usePersistedState<Record<string, unknown>>(pk("presetExtra"), {});
  const [customFilters, setCustomFilters] = usePersistedState<Record<string, string>>(pk("customFilters"), {});
  const setCustomFilter = (k: string, v: string) =>
    setCustomFilters((prev) => {
      const n = { ...prev };
      if (v === "") delete n[k]; else n[k] = v;
      return n;
    });

  const { defs: customDefs, valuesById: customValuesById } = useCustomFieldsTable("migration");
  const colPrefs = useColumnPrefs("migrations");
  const BASE_COLS_META: { key: string; label: string }[] = [
    { key: "id",              label: "Réf." },
    { key: "name",            label: "Client" },
    { key: "oldOperator",     label: "Ancien op." },
    { key: "newOperator",     label: "Nouvel op." },
    { key: "portingNumber",   label: "N° portabilité" },
    { key: "workflowStatus",  label: "Statut" },
    { key: "technicalStatus", label: "Technique" },
    { key: "assignedTo",      label: "Assigné" },
    { key: "requestedDate",   label: "Demandée" },
    { key: "createdAt",       label: "Créée" },
  ];
  // Export label mapping — matches the header names used in `baseExportRows`.
  const BASE_EXPORT_LABELS: Record<string, string> = {
    id: "ID", name: "Nom", oldOperator: "Ancien opérateur",
    newOperator: "Nouvel opérateur", portingNumber: "Portabilité",
    workflowStatus: "Statut", technicalStatus: "Technique",
    assignedTo: "Assigné à", requestedDate: "Date demande", createdAt: "Créée le",
  };

  useEffect(() => {
    if (urlStatut && urlStatut !== statut) {
      setStatut(urlStatut);
    }
  }, [urlStatut, statut, setStatut]);

  const debouncedSearch = useDebouncedValue(search, 250);

  const workflowOptions = useMemo(() => {
    const s = new Set<string>(STATUTS);
    for (const m of allMigrations) if (m.workflowStatus) s.add(m.workflowStatus);
    return [...s].sort((a, b) => a.localeCompare(b, "fr"));
  }, [allMigrations, STATUTS]);
  const technicalOptions = useMemo(() => {
    const s = new Set<string>();
    for (const m of allMigrations) if (m.technicalStatus) s.add(m.technicalStatus);
    return [...s].sort((a, b) => a.localeCompare(b, "fr"));
  }, [allMigrations]);
  const oldOpOptions = useMemo(() => {
    const s = new Set<string>();
    for (const m of allMigrations) if (m.oldOperator) s.add(m.oldOperator);
    return [...s].sort((a, b) => a.localeCompare(b, "fr"));
  }, [allMigrations]);
  const newOpOptions = useMemo(() => {
    const s = new Set<string>();
    for (const m of allMigrations) if (m.newOperator) s.add(m.newOperator);
    return [...s].sort((a, b) => a.localeCompare(b, "fr"));
  }, [allMigrations]);

  const filtered = useMemo(() => {
    const q = debouncedSearch.trim().toLowerCase();
    return allMigrations.filter((m) => {
      if (q) {
        const hay = `${m.lastName} ${m.firstName} ${m.phone ?? ""} ${m.cin ?? ""} ${m.portingNumber ?? ""} ${m.oldOperator ?? ""} ${m.newOperator ?? ""}`.toLowerCase();
        if (!hay.includes(q)) return false;
      }
      if (statut !== ALL && m.workflowStatus !== statut) return false;
      if (technical !== ALL && m.technicalStatus !== technical) return false;
      if (oldOp !== ALL && m.oldOperator !== oldOp) return false;
      if (newOp !== ALL && m.newOperator !== newOp) return false;
      if (assigne !== ALL && m.assignedTo !== assigne) return false;
      const created = (m.createdAt ?? "").slice(0, 10);
      if (dateFrom && created < dateFrom) return false;
      if (dateTo && created > dateTo) return false;
      const cfEntries = Object.entries(customFilters);
      if (cfEntries.length > 0) {
        const vals = customValuesById[m.id] ?? {};
        for (const [k, raw] of cfEntries) {
          const q2 = String(raw).trim().toLowerCase();
          if (!q2) continue;
          const v = String(vals[k] ?? "").toLowerCase();
          if (!v.includes(q2)) return false;
        }
      }
      for (const [k, raw] of Object.entries(presetExtra)) {
        if (raw == null || raw === "") continue;
        const val = (m as Record<string, unknown>)[k];
        if (val == null || !String(val).toLowerCase().includes(String(raw).toLowerCase())) return false;
      }
      return true;
    });
  }, [allMigrations, debouncedSearch, statut, technical, oldOp, newOp, assigne, dateFrom, dateTo, presetExtra, customFilters, customValuesById]);

  const filterSchema = useMemo(
    () => autoFilterSchema("migrations", { agents: agentOptions, rows: allMigrations as unknown as ReadonlyArray<Record<string, unknown>>, customFields: customDefs }),
    [agentOptions, allMigrations],
  );

  const allColumns: DataGridColumn<Migration>[] = [
    { key: "id", header: "Réf.", width: "110px", cell: (m) => <span className="font-mono text-xs">{m.id}</span> },
    {
      key: "name",
      header: "Client",
      cell: (m) => (
        <div>
          <div className="font-medium">{m.lastName} {m.firstName}</div>
          <div className="text-xs text-muted-foreground">{m.phone}</div>
        </div>
      ),
    },
    { key: "oldOperator", header: "Ancien op.", cell: (m) => m.oldOperator || "—" },
    { key: "newOperator", header: "Nouvel op.", cell: (m) => m.newOperator || "—" },
    { key: "portingNumber", header: "N° portabilité", cell: (m) => m.portingNumber || "—" },
    {
      key: "workflowStatus",
      header: "Statut",
      cell: (m) => (
        <Badge variant="outline" className={workflowColor[m.workflowStatus] ?? ""}>
          {m.workflowStatus}
        </Badge>
      ),
    },
    { key: "technicalStatus", header: "Technique", cell: (m) => m.technicalStatus || "—" },
    { key: "assignedTo", header: "Assigné", cell: (m) => m.assignedTo || "—" },
    { key: "requestedDate", header: "Demandée", cell: (m) => m.requestedDate?.slice(0, 10) ?? "—" },
    { key: "createdAt", header: "Créée", cell: (m) => m.createdAt?.slice(0, 10) ?? "—" },
    ...customDefs.map<DataGridColumn<Migration>>((d) => ({
      key: `cf_${d.key}`,
      header: d.label,
      cell: (m) => (
        <span className="text-muted-foreground text-sm">
          {formatCustomValue(d, customValuesById[m.id]?.[d.key])}
        </span>
      ),
    })),
  ];
  // Custom-field visibility uses the raw `d.key`, base columns their own key.
  // The DataGrid columns for custom fields are keyed `cf_<key>` — remap here.
  const columns = allColumns.filter((c) => {
    if (c.key.startsWith("cf_")) return colPrefs.isVisible(c.key.slice(3));
    return colPrefs.isVisible(c.key);
  });

  const reset = async () => {
    if (!(await confirmDialog({ title: "Réinitialiser les filtres", description: "Effacer tous les filtres actifs (préréglages, recherche, dates, colonnes personnalisées) et rétablir les filtres rapides ?", tone: "warning", confirmText: "Réinitialiser" }))) return;
    setSearch("");
    setStatut(ALL);
    setTechnical(ALL);
    setOldOp(ALL);
    setNewOp(ALL);
    setAssigne(ALL);
    setDateFrom("");
    setDateTo("");
    setPresetExtra({});
    setCustomFilters({});
    toast.success("Filtres réinitialisés");
  };

  const baseExportRows: Record<string, unknown>[] = filtered.map((m) => ({
    ID: m.id,
    Nom: m.lastName,
    Prénom: m.firstName,
    Téléphone: m.phone ?? "",
    "Ancien opérateur": m.oldOperator ?? "",
    "Nouvel opérateur": m.newOperator ?? "",
    Portabilité: m.portingNumber ?? "",
    Statut: m.workflowStatus,
    Technique: m.technicalStatus ?? "",
    "Assigné à": m.assignedTo ?? "",
    "Date demande": m.requestedDate ?? "",
    "Créée le": m.createdAt ?? "",
    id: m.id,
  }));
  const exportRowsFull = withCustomFields(baseExportRows, customDefs, customValuesById);
  const exportLabels = [
    ...BASE_COLS_META.filter((c) => colPrefs.isVisible(c.key)).map((c) => BASE_EXPORT_LABELS[c.key] ?? c.label),
    ...customDefs.filter((d) => colPrefs.isVisible(d.key)).map((d) => d.label),
  ];
  const exportRows = pickColumns(exportRowsFull, exportLabels);

  return (
    <AppLayout skeleton="table">
      {migrationsQ.error && (
        <Card className="mb-4 border-destructive/30 bg-destructive/5 p-4 text-sm text-destructive">
          {migrationsQ.error.message}
        </Card>
      )}
      <PageHeader
        title="Migrations"
        description={`${allMigrations.length.toLocaleString("fr-FR")} dossiers — étape terminale (alternative au contrat)`}
        icon={<ArrowRightLeft className="h-5 w-5" />}
        actions={
          canExport ? (
            <>
              <CustomColumnsPicker
                baseCols={BASE_COLS_META}
                defs={customDefs}
                isVisible={colPrefs.isVisible}
                onToggle={colPrefs.setVisible}
                onShowAll={colPrefs.showAll}
                onReset={colPrefs.reset}
              />
              <Button size="sm" variant="outline" onClick={() => exportCSV("migrations", exportRows)}>
                <Download className="h-4 w-4 mr-1.5" />CSV
              </Button>
              <Button size="sm" variant="outline" onClick={() => { void exportXLSX("migrations", exportRows); }}>
                <FileSpreadsheet className="h-4 w-4 mr-1.5" />Excel
              </Button>
              <Button size="sm" variant="outline" onClick={() => exportJSON("migrations", exportRows)}>
                <FileJson className="h-4 w-4 mr-1.5" />JSON
              </Button>
            </>
          ) : (
            <CustomColumnsPicker
              baseCols={BASE_COLS_META}
              defs={customDefs}
              isVisible={colPrefs.isVisible}
              onToggle={colPrefs.setVisible}
              onShowAll={colPrefs.showAll}
              onReset={colPrefs.reset}
            />
          )
        }
      />

      <Card className="p-4 mb-4 flex flex-col gap-2">
        <div className="flex items-center gap-2 w-full">
          <div className="relative flex-1 max-w-sm">
            <Search className="absolute left-2.5 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground" />
            <Input
              value={search}
              onChange={(e) => setSearch(e.target.value)}
              placeholder="Rechercher nom, téléphone, CIN, opérateurs…"
              className="pl-9 h-9"
            />
          </div>
          <div
            key={`count-${search}|${statut}|${technical}|${oldOp}|${newOp}|${assigne}|${dateFrom}|${dateTo}|${JSON.stringify(presetExtra)}|${JSON.stringify(customFilters)}`}
            className="ml-auto text-xs text-muted-foreground tabular-nums animate-in fade-in slide-in-from-right-2 duration-300"
          >
            <span className="font-semibold text-foreground">{filtered.length.toLocaleString("fr-FR")}</span> résultat(s) sur {allMigrations.length.toLocaleString("fr-FR")}
          </div>
        </div>

        <DynamicFilterBar
          scope="migrations"
          schema={filterSchema}
          values={{ statut, workflow: statut, technical, oldOp, newOp, assigne, dateFrom, dateTo, ...presetExtra, ...customFilters }}
          onChange={(k, v) => {
            if (k === "statut" || k === "workflow") setStatut(v || ALL);
            else if (k === "technical") setTechnical(v || ALL);
            else if (k === "oldOp") setOldOp(v || ALL);
            else if (k === "newOp") setNewOp(v || ALL);
            else if (k === "assigne") setAssigne(v || ALL);
            else if (k === "dateFrom") setDateFrom(v || "");
            else if (k === "dateTo") setDateTo(v || "");
            else if (customDefs.some(d => d.key === k)) setCustomFilter(k, v);
            else setPresetExtra(prev => {
              const n = { ...prev };
              if (v === "") delete n[k]; else n[k] = v;
              return n;
            });
          }}
          onReset={reset}
        />

      </Card>

      {selected.size > 0 && canDelete && (
        <Card className="p-3 mb-4 shadow-elegant bg-primary/5 border-primary/20 flex items-center justify-between gap-2 flex-wrap">
          <div className="text-sm font-medium">{selected.size} migration(s) sélectionnée(s)</div>
          <div className="flex gap-2 items-center">
            <Button
              variant="outline"
              size="sm"
              disabled={bulkBusy || !API_ENABLED}
              onClick={async () => {
                const ids = Array.from(selected);
                if (!(await confirmDialog({ title: "Suppression", description: `Supprimer ${ids.length} migration(s) ? Celles liées à une opportunité seront renvoyées vers leur opportunité d'origine.`, tone: "destructive", confirmText: "Supprimer" }))) return;
                setBulkBusy(true);
                try {
                  const rows = filtered.filter((m) => selected.has(m.id));
                  const CHUNK = 25;
                  let ok = 0;
                  for (let i = 0; i < rows.length; i += CHUNK) {
                    const slice = rows.slice(i, i + CHUNK);
                    const res = await Promise.allSettled(slice.map((r) => deleteWithCascade("migration", r)));
                    ok += res.filter((r) => r.status === "fulfilled").length;
                    toast.message(`Suppression… ${Math.min(i + CHUNK, rows.length)}/${rows.length}`);
                  }
                  toast.success(`${ok}/${rows.length} migration(s) traitée(s)`);
                  setSelected(new Set());
                  await revertMigration();
                  await refresh();
                } finally { setBulkBusy(false); }
              }}
            >Supprimer</Button>
            <Button variant="ghost" size="sm" onClick={() => setSelected(new Set())}>Désélectionner</Button>
          </div>
        </Card>
      )}

      <DataGrid
        storageKey="migrations:list"
        rows={filtered}
        columns={columns}
        rowKey={(m) => m.id}
        selected={selected}
        onSelectedChange={setSelected}
        pageSize={PAGE_SIZE}
        emptyState="Aucune migration ne correspond aux filtres."
        onRowClick={(m) => navigate({ to: "/migrations/$migrationId", params: { migrationId: m.id } })}
        onDeleteRow={canDelete ? async (row) => {
          if (!(await confirmCascadeDelete("migration", row))) return;
          try {
            const r = await deleteWithCascade("migration", row);
            await revertMigration();
            await refresh();
            toast.success(r.reverted ? "Migration retournée en opportunité" : "Supprimée");
          } catch (e: any) { toast.error(e?.message ?? "Échec"); }
        } : undefined}
        rowActions={[
          {
            label: "Ouvrir la fiche",
            icon: <Eye className="h-4 w-4" />,
            onClick: (m) => navigate({ to: "/migrations/$migrationId", params: { migrationId: m.id } }),
          },
          ...(canEdit ? [{
            label: "Modifier",
            icon: <Pencil className="h-4 w-4" />,
            onClick: (m: Migration) => navigate({ to: "/migrations/$migrationId/edit", params: { migrationId: m.id } }),
          }] : []),
          ...(canDelete ? [{
            label: "Supprimer",
            icon: <Trash2 className="h-4 w-4" />,
            destructive: true,
            onClick: async (m: Migration) => {
              if (!(await confirmCascadeDelete("migration", m))) return;
              try {
                const r = await deleteWithCascade("migration", m);
                await revertMigration();
                await refresh();
                toast.success(r.reverted ? "Migration retournée en opportunité" : "Supprimée");
              } catch (e: any) { toast.error(e?.message ?? "Échec"); }
            },
          }] : []),
        ]}
      />
    </AppLayout>
  );
}
