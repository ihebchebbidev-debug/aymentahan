import { useEffect, useMemo, useState } from "react";
import {
  Activity, ArrowRight, Download, FileSpreadsheet, History, X,
} from "lucide-react";
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from "@/components/ui/card";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { DatePicker } from "@/components/ui/date-picker";
import {
  Select, SelectContent, SelectItem, SelectTrigger, SelectValue,
} from "@/components/ui/select";
import {
  DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuLabel, DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";
import { api, API_ENABLED } from "@/lib/api";
import { exportCSV, exportXLSX } from "@/lib/exportUtils";
import { toast } from "sonner";

export type ActivityEntry = {
  id: string;
  field: string;
  previousValue: string;
  newValue: string;
  user: string;
  timestamp: string;
};

const DEFAULT_FIELD_FR: Record<string, string> = {
  workflowStatus: "Statut workflow",
  technicalStatus: "Statut technique",
  billingStatus: "Statut facturation",
  premium: "Cotisation",
  oldOperator: "Ancien opérateur",
  newOperator: "Nouvel opérateur",
  portingNumber: "N° portabilité",
  attachment_added: "Pièce jointe ajoutée",
  attachment_removed: "Pièce jointe supprimée",
};

function formatTs(ts: string) {
  try {
    return new Date(ts).toLocaleString("fr-FR", {
      day: "2-digit", month: "2-digit", year: "numeric",
      hour: "2-digit", minute: "2-digit",
    });
  } catch {
    return ts;
  }
}

type Props = {
  entityType: string;
  entityId: string;
  fieldLabels?: Record<string, string>;
  filterFields?: { value: string; label: string }[];
  description?: string;
};

export function EntityActivityLogCard({
  entityType,
  entityId,
  fieldLabels = {},
  filterFields,
  description = "Historique des modifications enregistrées sur cette fiche",
}: Props) {
  const [entries, setEntries] = useState<ActivityEntry[]>([]);
  const [loading, setLoading] = useState(true);
  const [fieldFilter, setFieldFilter] = useState("all");
  const [search, setSearch] = useState("");
  const [from, setFrom] = useState("");
  const [to, setTo] = useState("");

  const labels = { ...DEFAULT_FIELD_FR, ...fieldLabels };

  useEffect(() => {
    if (!API_ENABLED || !entityId) {
      setLoading(false);
      return;
    }
    let cancel = false;
    setLoading(true);
    api<{ activity: ActivityEntry[] }>(
      `/activity.php?entity=${encodeURIComponent(entityType)}&entity_id=${encodeURIComponent(entityId)}&limit=200`,
    )
      .then((r) => { if (!cancel) setEntries(r.activity ?? []); })
      .catch(() => { if (!cancel) setEntries([]); })
      .finally(() => { if (!cancel) setLoading(false); });
    return () => { cancel = true; };
  }, [entityType, entityId]);

  const fieldLabel = (f: string) => labels[f] ?? f;

  const filtered = useMemo(() => {
    return entries.filter((e) => {
      if (fieldFilter !== "all" && e.field !== fieldFilter) return false;
      if (from && e.timestamp.slice(0, 10) < from) return false;
      if (to && e.timestamp.slice(0, 10) > to) return false;
      if (search) {
        const q = search.toLowerCase();
        const hay = `${e.previousValue} ${e.newValue} ${e.user} ${e.field}`.toLowerCase();
        if (!hay.includes(q)) return false;
      }
      return true;
    });
  }, [entries, fieldFilter, from, to, search]);

  const exportRows = () =>
    filtered.map((e) => ({
      Date: formatTs(e.timestamp),
      Utilisateur: e.user,
      Champ: fieldLabel(e.field),
      "Ancienne valeur": e.previousValue,
      "Nouvelle valeur": e.newValue,
    }));

  const handleExportCSV = () => {
    if (filtered.length === 0) { toast.error("Aucune entrée à exporter"); return; }
    exportCSV(`activite-${entityType}-${entityId}.csv`, exportRows());
    toast.success("Journal exporté");
  };
  const handleExportXLSX = async () => {
    if (filtered.length === 0) { toast.error("Aucune entrée à exporter"); return; }
    await exportXLSX(`activite-${entityType}-${entityId}.xlsx`, exportRows(), "Activité");
    toast.success("Journal exporté");
  };

  const reset = () => { setFieldFilter("all"); setSearch(""); setFrom(""); setTo(""); };

  const defaultFilters = filterFields ?? [
    { value: "workflowStatus", label: "Statut workflow" },
    { value: "technicalStatus", label: "Statut technique" },
    { value: "attachment_added", label: "Pièces jointes — ajout" },
    { value: "attachment_removed", label: "Pièces jointes — suppression" },
  ];

  return (
    <Card className="shadow-elegant">
      <CardHeader className="pb-3">
        <div className="flex items-center justify-between gap-3 flex-wrap">
          <div>
            <CardTitle className="text-base flex items-center gap-2">
              <History className="h-4 w-4" />Journal d&apos;activité
            </CardTitle>
            <CardDescription>{description}</CardDescription>
          </div>
          <div className="flex items-center gap-2">
            <Badge variant="outline" className="bg-muted text-muted-foreground">
              {loading ? "…" : `${filtered.length} / ${entries.length}`}
            </Badge>
            <DropdownMenu>
              <DropdownMenuTrigger asChild>
                <Button variant="outline" size="sm" disabled={entries.length === 0}>
                  <Download className="h-4 w-4 mr-1.5" />Exporter
                </Button>
              </DropdownMenuTrigger>
              <DropdownMenuContent align="end">
                <DropdownMenuLabel className="text-xs">Format</DropdownMenuLabel>
                <DropdownMenuItem onClick={handleExportCSV}>
                  <FileSpreadsheet className="h-4 w-4 mr-2" />Excel
                </DropdownMenuItem>
                <DropdownMenuItem onClick={handleExportXLSX}>
                  <FileSpreadsheet className="h-4 w-4 mr-2" />Excel (.xlsx)
                </DropdownMenuItem>
              </DropdownMenuContent>
            </DropdownMenu>
          </div>
        </div>

        <div className="grid grid-cols-2 md:grid-cols-5 gap-2 pt-3">
          <div className="md:col-span-2">
            <Input
              placeholder="Rechercher (valeur, utilisateur)…"
              value={search}
              onChange={(e) => setSearch(e.target.value)}
              className="h-8 text-xs"
            />
          </div>
          <Select value={fieldFilter} onValueChange={setFieldFilter}>
            <SelectTrigger className="h-8 text-xs"><SelectValue /></SelectTrigger>
            <SelectContent>
              <SelectItem value="all">Tous les champs</SelectItem>
              {defaultFilters.map((f) => (
                <SelectItem key={f.value} value={f.value}>{f.label}</SelectItem>
              ))}
            </SelectContent>
          </Select>
          <DatePicker value={from} onChange={setFrom} placeholder="Du" size="sm" />
          <div className="flex gap-1">
            <DatePicker value={to} onChange={setTo} placeholder="Au" size="sm" />
            {(fieldFilter !== "all" || search || from || to) && (
              <Button variant="ghost" size="icon" className="h-8 w-8 shrink-0" onClick={reset} aria-label="Réinitialiser">
                <X className="h-3.5 w-3.5" />
              </Button>
            )}
          </div>
        </div>
      </CardHeader>
      <CardContent>
        {loading ? (
          <p className="text-sm text-muted-foreground text-center py-8">Chargement…</p>
        ) : entries.length === 0 ? (
          <div className="flex flex-col items-center justify-center py-8 text-center text-muted-foreground">
            <Activity className="h-8 w-8 mb-2 opacity-50" />
            <p className="text-sm">Aucune activité enregistrée pour le moment.</p>
          </div>
        ) : filtered.length === 0 ? (
          <div className="text-center py-8 text-sm text-muted-foreground">
            Aucune entrée ne correspond aux filtres.
          </div>
        ) : (
          <ul className="divide-y divide-border">
            {filtered.map((e) => {
              const isAttach = e.field === "attachment_added" || e.field === "attachment_removed";
              const isRemove = e.field === "attachment_removed";
              return (
                <li key={e.id} className="py-3 flex items-start gap-3">
                  <div className="h-8 w-8 rounded-full bg-accent text-accent-foreground flex items-center justify-center text-[11px] font-semibold shrink-0">
                    {e.user.split(".").map((p) => p[0]).join("").slice(0, 2)}
                  </div>
                  <div className="min-w-0 flex-1">
                    <div className="flex flex-wrap items-baseline gap-x-2 gap-y-0.5">
                      <span className="text-sm font-medium">{fieldLabel(e.field)}</span>
                      <span className="text-xs text-muted-foreground ml-auto">{formatTs(e.timestamp)}</span>
                    </div>
                    {isAttach ? (
                      <div className="mt-1 flex items-center gap-2 text-xs flex-wrap">
                        <span className={`px-2 py-0.5 rounded-md font-medium ${isRemove ? "bg-destructive/10 text-destructive line-through" : "bg-success/10 text-success"}`}>
                          {e.newValue || e.previousValue}
                        </span>
                      </div>
                    ) : (
                      <div className="mt-1 flex items-center gap-2 text-xs flex-wrap">
                        <span className="px-2 py-0.5 rounded-md bg-muted text-muted-foreground line-through">{e.previousValue || "vide"}</span>
                        <ArrowRight className="h-3 w-3 text-muted-foreground" />
                        <span className="px-2 py-0.5 rounded-md bg-success/10 text-success font-medium">{e.newValue || "vide"}</span>
                      </div>
                    )}
                    <div className="text-[11px] text-muted-foreground mt-1">par @{e.user}</div>
                  </div>
                </li>
              );
            })}
          </ul>
        )}
      </CardContent>
    </Card>
  );
}
