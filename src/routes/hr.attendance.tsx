import { createFileRoute } from "@tanstack/react-router";
import { AppLayout } from "@/components/AppLayout";
import { PageHeader } from "@/components/PageHeader";
import { Card } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Button } from "@/components/ui/button";
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table";
import { Badge } from "@/components/ui/badge";
import { Clock, RefreshCw, LogOut as LogOutIcon, LogIn as LogInIcon, X } from "lucide-react";
import { useEffect, useMemo, useState } from "react";
import { api } from "@/lib/api";
import { useAuth } from "@/lib/auth";
import type { AttendanceEntry } from "@/lib/types";
import { toast } from "sonner";

export const Route = createFileRoute("/hr/attendance")({
  head: () => ({ meta: [{ title: "Pointage — CRM" }] }),
  component: AttendancePage,
});

function fmtMin(m: number) {
  const h = Math.floor(m / 60);
  const mm = m % 60;
  return `${h}h${mm.toString().padStart(2, "0")}`;
}

function splitDateTime(s: string | null | undefined): { date: string; time: string } {
  if (!s) return { date: "—", time: "" };
  // Backend returns "YYYY-MM-DD HH:MM:SS" (server local time).
  const [d, t] = s.split(" ");
  return { date: d ?? s, time: (t ?? "").slice(0, 5) };
}

/** Build the inclusive list of "YYYY-MM" months between two ISO dates. */
function monthsBetween(from: string, to: string): string[] {
  const a = new Date(from + "T00:00:00");
  const b = new Date(to + "T00:00:00");
  if (Number.isNaN(a.getTime()) || Number.isNaN(b.getTime()) || a > b) return [];
  const out: string[] = [];
  const cur = new Date(a.getFullYear(), a.getMonth(), 1);
  const end = new Date(b.getFullYear(), b.getMonth(), 1);
  while (cur <= end) {
    out.push(`${cur.getFullYear()}-${String(cur.getMonth() + 1).padStart(2, "0")}`);
    cur.setMonth(cur.getMonth() + 1);
  }
  return out;
}

function AttendancePage() {
  const { user, hasPermission } = useAuth();
  const isPriv = hasPermission("hr.attendance.export");
  const canClock = hasPermission("hr.attendance.clock");
  const [month, setMonth] = useState(new Date().toISOString().slice(0, 7));
  const [from, setFrom] = useState<string>("");
  const [to, setTo] = useState<string>("");
  const [username, setUsername] = useState("");
  const [rows, setRows] = useState<AttendanceEntry[]>([]);
  const [loading, setLoading] = useState(false);

  const rangeActive = Boolean(from && to);

  const load = async () => {
    setLoading(true);
    try {
      // Backend only supports ?month=YYYY-MM. For a date range we fetch each
      // month it spans then concat client-side. Otherwise we just hit `month`.
      if (rangeActive) {
        const months = monthsBetween(from, to);
        if (months.length === 0) {
          toast.error("Plage de dates invalide");
          setLoading(false);
          return;
        }
        const results = await Promise.all(
          months.map((m) =>
            api<{ attendance: AttendanceEntry[] }>("/attendance.php", {
              query: { month: m, ...(username ? { username } : {}) },
            }),
          ),
        );
        const all = results.flatMap((r) => r.attendance);
        setRows(all);
      } else {
        const r = await api<{ attendance: AttendanceEntry[] }>("/attendance.php", {
          query: { month, ...(username ? { username } : {}) },
        });
        setRows(r.attendance);
      }
    } catch (e: any) {
      toast.error(e?.message ?? "Erreur de chargement");
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    void load();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [month, from, to]);

  // Backend (attendance.php) only accepts the action via query string.
  // JSON body actions like {"action":"clock_in"} are rejected with
  // "Méthode non supportée". Keep one helper so UI + API stay aligned.
  const punch = async (action: "clock_in" | "clock_out") => {
    try {
      await api("/attendance.php", { method: "POST", query: { action }, body: {} });
      toast.success(action === "clock_in" ? "Pointage ouvert" : "Pointage fermé");
      void load();
    } catch (e: any) { toast.error(e?.message ?? "Erreur"); }
  };
  const clockIn = () => punch("clock_in");
  const clockOut = () => punch("clock_out");

  // Apply client-side date-range filter (rows are already month-bounded server-side)
  // and optional username filter.
  const visibleRows = useMemo(() => {
    let r = rows;
    if (rangeActive) {
      r = r.filter((row) => {
        const d = (row.loginAt ?? "").slice(0, 10);
        return d >= from && d <= to;
      });
    }
    if (username && isPriv) {
      const u = username.toLowerCase();
      r = r.filter((row) => row.username.toLowerCase().includes(u));
    }
    return r;
  }, [rows, rangeActive, from, to, username, isPriv]);

  const periodLabel = rangeActive ? `${from} → ${to}` : month;

  const clearRange = () => { setFrom(""); setTo(""); };

  // Advanced aggregates moved to dedicated dashboard

  return (
    <AppLayout skeleton="table">
      <PageHeader
        title="Pointage / Présence"
        description="Heures travaillées par utilisateur, calculées au login/logout."
        icon={<Clock className="h-5 w-5" />}
        actions={
          <>
            <Button variant="outline" size="sm" onClick={() => void load()}>
              <RefreshCw className="h-4 w-4 mr-1.5" /> Actualiser
            </Button>
            {canClock && (
              <Button variant="outline" size="sm" onClick={clockIn}>
                <LogInIcon className="h-4 w-4 mr-1.5" /> Pointer (entrée)
              </Button>
            )}
            {canClock && (
              <Button variant="outline" size="sm" onClick={clockOut}>
                <LogOutIcon className="h-4 w-4 mr-1.5" /> Clore ma session
              </Button>
            )}
          </>
        }
      />

      <div className="mt-6 flex flex-wrap gap-3 items-end">
        <div>
          <Label htmlFor="m">Mois</Label>
          <Input
            id="m"
            type="month"
            value={month}
            onChange={(e) => setMonth(e.target.value)}
            disabled={rangeActive}
            className="w-44"
          />
        </div>
        <div>
          <Label htmlFor="from">Du</Label>
          <Input id="from" type="date" value={from} onChange={(e) => setFrom(e.target.value)} className="w-40" />
        </div>
        <div>
          <Label htmlFor="to">Au</Label>
          <Input id="to" type="date" value={to} onChange={(e) => setTo(e.target.value)} className="w-40" />
        </div>
        {rangeActive && (
          <Button variant="ghost" size="sm" onClick={clearRange}>
            <X className="h-4 w-4 mr-1.5" /> Effacer la plage
          </Button>
        )}
        {isPriv && (
          <div>
            <Label htmlFor="u">Utilisateur (filtre)</Label>
            <Input id="u" value={username} onChange={(e) => setUsername(e.target.value)} placeholder="username" className="w-56" />
          </div>
        )}
        {isPriv && (
          <Button size="sm" onClick={() => void load()}>Filtrer</Button>
        )}
        {isPriv && (
          <Button size="sm" variant="outline" asChild>
            <a href="/hr/attendance/dashboard">Ouvrir le tableau Pointage</a>
          </Button>
        )}
      </div>

      <div className="mt-6">
        {isPriv ? (
          <Card className="p-4">
            <h3 className="font-semibold text-sm">Pointage</h3>
            <p className="text-sm text-muted-foreground">
              Cette page affiche toutes les sessions de pointage pour les utilisateurs. Pour les KPI et graphiques, utilisez le Tableau Pointage.
            </p>
          </Card>
        ) : null}
      </div>

      <Card className="p-4 mt-6">
        <div className="flex items-center justify-between mb-3 gap-2 flex-wrap">
          <div>
            <h3 className="font-semibold text-sm">Historique des pointages</h3>
            <p className="text-xs text-muted-foreground">
              Liste des entrées / sorties pour tous les utilisateurs sur la période sélectionnée.
            </p>
          </div>
          {(() => {
            const open = visibleRows.find((r) => !r.logoutAt);
            return open ? (
              <Badge className="bg-emerald-500/15 text-emerald-600 border-emerald-500/30">
                Session ouverte
              </Badge>
            ) : (
              <Badge variant="outline">Aucune session ouverte</Badge>
            );
          })()}
        </div>
        <div className="overflow-x-auto rounded-md border border-border/60">
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead>Utilisateur</TableHead>
                <TableHead className="w-12">#</TableHead>
                <TableHead>Entrée</TableHead>
                <TableHead>Sortie</TableHead>
                <TableHead>Durée</TableHead>
                <TableHead>IP</TableHead>
                <TableHead>Statut</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {visibleRows.length === 0 && (
                <TableRow><TableCell colSpan={7} className="text-center py-6 text-muted-foreground text-sm">Aucun pointage sur cette période</TableCell></TableRow>
              )}
              {visibleRows.map((r, i) => {
                const inDt = splitDateTime(r.loginAt);
                const outDt = r.logoutAt ? splitDateTime(r.logoutAt) : null;
                return (
                  <TableRow key={r.id}>
                    <TableCell className="font-medium">{r.username}</TableCell>
                    <TableCell className="text-xs text-muted-foreground">{i + 1}</TableCell>
                    <TableCell>
                      <div className="text-sm font-medium">{inDt.time}</div>
                      <div className="text-xs text-muted-foreground">{inDt.date}</div>
                    </TableCell>
                    <TableCell>
                      {outDt ? (
                        <>
                          <div className="text-sm font-medium">{outDt.time}</div>
                          <div className="text-xs text-muted-foreground">{outDt.date}</div>
                        </>
                      ) : (
                        <Badge variant="outline">en cours</Badge>
                      )}
                    </TableCell>
                    <TableCell className="text-sm">{r.totalMinutes ? fmtMin(r.totalMinutes) : "—"}</TableCell>
                    <TableCell className="text-xs font-mono text-muted-foreground max-w-[180px] truncate" title={r.ip ?? ""}>
                      {r.ip ?? "—"}
                    </TableCell>
                    <TableCell>
                      {r.logoutAt ? (
                        <Badge variant="secondary">Fermée</Badge>
                      ) : (
                        <Badge className="bg-emerald-500/15 text-emerald-600 border-emerald-500/30">Ouverte</Badge>
                      )}
                    </TableCell>
                  </TableRow>
                );
              })}
            </TableBody>
          </Table>
        </div>
      </Card>

      <div className="mt-6">
        {isPriv ? (
          <Card className="p-4">
            <h3 className="font-semibold mb-2">Vue complète</h3>
            <p className="text-sm text-muted-foreground mb-3">Les synthèses et rapports avancés ont été déplacés vers le Tableau Pointage.</p>
            <Button asChild>
              <a href="/hr/attendance/dashboard">Ouvrir le tableau Pointage</a>
            </Button>
          </Card>
        ) : null}
      </div>

      <div className="mt-6">
        {isPriv ? (
          <Card className="p-4">
            <h3 className="font-semibold mb-2">Vue complète</h3>
            <p className="text-sm text-muted-foreground mb-3">Les synthèses et rapports avancés ont été déplacés vers le Tableau Pointage.</p>
            <Button asChild>
              <a href="/hr/attendance/dashboard">Ouvrir le tableau Pointage</a>
            </Button>
          </Card>
        ) : null}
      </div>
    </AppLayout>
  );
}
