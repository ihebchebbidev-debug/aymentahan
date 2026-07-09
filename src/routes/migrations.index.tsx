import { createFileRoute, useNavigate } from "@tanstack/react-router";
import { zodValidator, fallback } from "@tanstack/zod-adapter";
import { z } from "zod";
import { useEffect } from "react";
import { AppLayout } from "@/components/AppLayout";
import { PageHeader } from "@/components/PageHeader";
import { ArrowRightLeft, Search, X, Eye, Download, FileSpreadsheet, FileJson } from "lucide-react";
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
import { FilterPresetPicker } from "@/components/FilterPresetPicker";
import { autoFilterSchema, schemaKeys } from "@/lib/autoFilterSchemas";
import { exportCSV, exportJSON, exportXLSX, withCustomFields, relabelRows } from "@/lib/exportUtils";
import { toast } from "sonner";
import { DatePicker } from "@/components/ui/date-picker";
import { useMigrationStages } from "@/hooks/use-migration-stages";
import { useCustomFieldsTable, formatCustomValue } from "@/lib/useCustomFields";
import { CustomColumnsPicker } from "@/components/CustomColumnsPicker";

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

const MIGRATION_LABELS: Record<string, string> = {
  id: "ID",
  lastName: "Nom",
  firstName: "Prénom",
  phone: "Téléphone",
  oldOperator: "Ancien opérateur",
  newOperator: "Nouvel opérateur",
  portingNumber: "Portabilité",
  workflowStatus: "Statut",
  technicalStatus: "Technique",
  assignedTo: "Assigné à",
  requestedDate: "Date demande",
  createdAt: "Créée le",
};

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
  const [presetExtra, setPresetExtra] = useState<Record<string, unknown>>({});

  useEffect(() => {
    if (urlStatut && urlStatut !== statut) {
      setStatut(urlStatut);
    }
  }, [urlStatut, statut, setStatut]);

  const { defs: customDefs, valuesById: customValuesById } = useCustomFieldsTable("migration");
  const [visibleCols, setVisibleCols] = useState<Set<string>>(new Set());
  const [customFilters, setCustomFilters] = useState<Record<string, string>>({});
  const setCustomFilter = (k: string, v: string) =>
    setCustomFilters((prev) => {
      const next = { ...prev };
      if (!v) delete next[k]; else next[k] = v;
      return next;
    });

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
    const cfEntries = Object.entries(customFilters);
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
      if (cfEntries.length > 0) {
        const vals = customValuesById[m.id] ?? {};
        for (const [k, want] of cfEntries) {
          const v = String(vals[k] ?? "").toLowerCase();
          if (!v.includes(want.toLowerCase())) return false;
        }
      }
      for (const [k, raw] of Object.entries(presetExtra)) {
        if (raw == null || raw === "") continue;
        const val = (m as Record<string, unknown>)[k];
        const target = String(raw).toLowerCase();
        
        if (val == null) {
          if (target === "false") continue;
          return false;
        }
        
        if (typeof val === "boolean") { if (String(val) !== target) return false; continue; }
        if (!String(val).toLowerCase().includes(target)) return false;
      }
      return true;
    });
  }, [allMigrations, debouncedSearch, statut, technical, oldOp, newOp, assigne, dateFrom, dateTo, presetExtra, customFilters, customValuesById]);

  const filterSchema = useMemo(
    () => autoFilterSchema("migrations", { agents: agentOptions, rows: allMigrations as unknown as ReadonlyArray<Record<string, unknown>> }),
    [agentOptions, allMigrations],
  );

  const columns: DataGridColumn<Migration>[] = [
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
  ];

  const customColumns: DataGridColumn<Migration>[] = customDefs
    .filter((d) => visibleCols.has(d.key))
    .map((d) => ({
      key: `cf-${d.key}`,
      header: d.label,
      accessor: (m) => customValuesById[m.id]?.[d.key] ?? "",
      cell: (m) => <span className="text-muted-foreground text-sm">{formatCustomValue(d, customValuesById[m.id]?.[d.key])}</span>,
      hideBelow: "lg",
    }));

  const allColumns = [...columns, ...customColumns];

  const reset = () => {
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

  const exportRows = useMemo(
    () => relabelRows(withCustomFields(filtered, customDefs, customValuesById), MIGRATION_LABELS),
    [filtered, customDefs, customValuesById],
  );

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
                defs={customDefs}
                visible={visibleCols}
                onToggle={(k, v) => setVisibleCols((prev) => {
                  const n = new Set(prev);
                  if (v) n.add(k); else n.delete(k);
                  return n;
                })}
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
              defs={customDefs}
              visible={visibleCols}
              onToggle={(k, v) => setVisibleCols((prev) => {
                const n = new Set(prev);
                if (v) n.add(k); else n.delete(k);
                return n;
              })}
            />
          )
        }
      />

      <Card className="p-4 mb-4 space-y-4">
        <div className="flex flex-wrap gap-3 items-end">
          <div className="flex-1 min-w-[200px]">
            <Label className="text-xs">Recherche</Label>
            <div className="relative">
              <Search className="absolute left-2.5 top-2.5 h-4 w-4 text-muted-foreground" />
              <Input className="pl-9" placeholder="Nom, téléphone, CIN, opérateurs…" value={search} onChange={(e) => setSearch(e.target.value)} />
            </div>
          </div>
          <div className="w-40">
            <Label className="text-xs">Statut</Label>
            <Select value={statut} onValueChange={setStatut}>
              <SelectTrigger><SelectValue /></SelectTrigger>
              <SelectContent>
                <SelectItem value={ALL}>Tous statuts</SelectItem>
                {workflowOptions.map((w) => <SelectItem key={w} value={w}>{w}</SelectItem>)}
              </SelectContent>
            </Select>
          </div>
          <div className="w-40">
            <Label className="text-xs">Statut technique</Label>
            <Select value={technical} onValueChange={setTechnical}>
              <SelectTrigger><SelectValue /></SelectTrigger>
              <SelectContent>
                <SelectItem value={ALL}>Tous</SelectItem>
                {technicalOptions.map((t) => <SelectItem key={t} value={t}>{t}</SelectItem>)}
              </SelectContent>
            </Select>
          </div>
          <div className="w-36">
            <Label className="text-xs">Ancien op.</Label>
            <Select value={oldOp} onValueChange={setOldOp}>
              <SelectTrigger><SelectValue /></SelectTrigger>
              <SelectContent>
                <SelectItem value={ALL}>Tous</SelectItem>
                {oldOpOptions.map((o) => <SelectItem key={o} value={o}>{o}</SelectItem>)}
              </SelectContent>
            </Select>
          </div>
          <div className="w-36">
            <Label className="text-xs">Nouvel op.</Label>
            <Select value={newOp} onValueChange={setNewOp}>
              <SelectTrigger><SelectValue /></SelectTrigger>
              <SelectContent>
                <SelectItem value={ALL}>Tous</SelectItem>
                {newOpOptions.map((o) => <SelectItem key={o} value={o}>{o}</SelectItem>)}
              </SelectContent>
            </Select>
          </div>
          <div className="w-40">
            <Label className="text-xs">Assigné à</Label>
            <Select value={assigne} onValueChange={setAssigne}>
              <SelectTrigger><SelectValue /></SelectTrigger>
              <SelectContent>
                <SelectItem value={ALL}>Tous</SelectItem>
                {[...new Set([...agentOptions, ...allMigrations.map((m) => m.assignedTo).filter(Boolean)])].sort().map((a) => (
                  <SelectItem key={a} value={a}>{a}</SelectItem>
                ))}
              </SelectContent>
            </Select>
          </div>
          <FilterPresetPicker
            scope="migrations"
            current={{ search, statut, technical, oldOp, newOp, assigne, dateFrom, dateTo }}
            filterKeys={schemaKeys(filterSchema)}
            filterSchema={filterSchema}
            onApply={(f) => {
              setSearch(typeof f.search === "string" ? f.search : "");
              setStatut(typeof f.statut === "string" && f.statut ? f.statut : ALL);
              setTechnical(typeof f.technical === "string" && f.technical ? f.technical : ALL);
              setOldOp(typeof f.oldOp === "string" && f.oldOp ? f.oldOp : ALL);
              setNewOp(typeof f.newOp === "string" && f.newOp ? f.newOp : ALL);
              setAssigne(typeof f.assigne === "string" && f.assigne ? f.assigne : ALL);
              setDateFrom(typeof f.dateFrom === "string" ? f.dateFrom : "");
              setDateTo(typeof f.dateTo === "string" ? f.dateTo : "");
              const extra: Record<string, unknown> = {};
              for (const [k, v] of Object.entries(f)) {
                if (!["search", "statut", "technical", "oldOp", "newOp", "assigne", "dateFrom", "dateTo"].includes(k)) {
                  if (v != null && v !== "") extra[k] = v;
                }
              }
              setPresetExtra(extra);
            }}
            onReset={reset}
          />
          <div
            key={`count-${search}|${statut}|${technical}|${oldOp}|${newOp}|${assigne}|${dateFrom}|${dateTo}|${JSON.stringify(presetExtra)}|${JSON.stringify(customFilters)}`}
            className="ml-auto text-xs text-muted-foreground tabular-nums animate-in fade-in slide-in-from-right-2 duration-300"
          >
            <span className="font-semibold text-foreground">{filtered.length.toLocaleString("fr-FR")}</span> résultat(s)
          </div>
        </div>

        {customDefs.length > 0 && (
          <div className="mt-3 pt-3 border-t border-border flex flex-wrap items-center gap-2">
            <Label className="text-[11px] uppercase tracking-wider text-muted-foreground ml-2 mr-1">Champs perso</Label>
            {customDefs.map((def) => (
              <Input
                key={def.id}
                type={def.type === "number" ? "number" : def.type === "date" ? "date" : "text"}
                value={customFilters[def.key] ?? ""}
                onChange={(e) => setCustomFilter(def.key, e.target.value)}
                placeholder={def.label}
                className="h-9 w-[160px]"
              />
            ))}
          </div>
        )}

        <div className="flex flex-wrap gap-3">
          <div>
            <Label className="text-xs">Créée du</Label>
            <DatePicker value={dateFrom} onChange={setDateFrom} />
          </div>
          <div>
            <Label className="text-xs">au</Label>
            <DatePicker value={dateTo} onChange={setDateTo} />
          </div>
        </div>
      </Card>

      <DataGrid
        storageKey="migrations:list"
        rows={filtered}
        columns={allColumns}
        rowKey={(m) => m.id}
        pageSize={PAGE_SIZE}
        emptyState="Aucune migration ne correspond aux filtres."
        onRowClick={(m) => navigate({ to: "/migrations/$migrationId", params: { migrationId: m.id } })}
        rowActions={[
          {
            label: "Ouvrir la fiche",
            icon: <Eye className="h-4 w-4" />,
            onClick: (m) => navigate({ to: "/migrations/$migrationId", params: { migrationId: m.id } }),
          },
        ]}
      />
    </AppLayout>
  );
}
