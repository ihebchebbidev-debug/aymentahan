import { createFileRoute } from "@tanstack/react-router";
import { AppLayout } from "@/components/AppLayout";
import { PageHeader } from "@/components/PageHeader";
import { Card } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table";
import { Clock, RefreshCw, BarChart2 } from "lucide-react";
import { useEffect, useMemo, useState } from "react";
import { api } from "@/lib/api";
import { useAuth } from "@/lib/auth";
import type { AttendanceAggregate } from "@/lib/types";
import { toast } from "sonner";

export const Route = createFileRoute("/hr/attendance/dashboard")({
  head: () => ({ meta: [{ title: "Pointage — Dashboard" }] }),
  component: AttendanceDashboard,
});

function fmtMin(m: number) {
  const h = Math.floor(m / 60);
  const mm = m % 60;
  return `${h}h${mm.toString().padStart(2, "0")}`;
}

function BarChart({ data }: { data: AttendanceAggregate[] }) {
  const max = Math.max(1, ...data.map((d) => d.minutes));
  const width = 600;
  const height = 160;
  const barW = Math.max(4, Math.floor(width / data.length) - 2);
  return (
    <svg viewBox={`0 0 ${width} ${height}`} width="100%" height={height} className="rounded-md bg-card">
      {data.map((d, i) => {
        const h = Math.round((d.minutes / max) * (height - 20));
        const x = i * (barW + 2) + 2;
        const y = height - h - 10;
        return (
          <g key={d.period}>
            <rect x={x} y={y} width={barW} height={h} rx={2} fill="#60a5fa" />
          </g>
        );
      })}
    </svg>
  );
}

function AttendanceDashboard() {
  const { user, hasPermission } = useAuth();
  const canView = hasPermission("page.hr.attendance_dashboard") || hasPermission("page.hr.attendance");
  const [start, setStart] = useState<string>(new Date(new Date().getFullYear(), new Date().getMonth(), 1).toISOString().slice(0, 10));
  const [end, setEnd] = useState<string>(new Date().toISOString().slice(0, 10));
  const [aggregates, setAggregates] = useState<AttendanceAggregate[]>([]);
  const [loading, setLoading] = useState(false);

  useEffect(() => { void load(); }, [start, end]);

  const load = async () => {
    if (!canView) return;
    setLoading(true);
    try {
      const r = await api<{ aggregates: AttendanceAggregate[] }>("/attendance.php", { query: { action: "aggregates", start, end } });
      setAggregates(r.aggregates ?? []);
    } catch (e: any) { toast.error(e?.message ?? "Erreur"); }
    finally { setLoading(false); }
  };

  const totalMinutes = useMemo(() => aggregates.reduce((s, a) => s + (a.minutes || 0), 0), [aggregates]);
  const totalSessions = useMemo(() => aggregates.reduce((s, a) => s + (a.sessions || 0), 0), [aggregates]);
  const avgPerDay = aggregates.length ? Math.round((totalMinutes / aggregates.length) * 10) / 10 : 0;

  if (!canView) return (
    <AppLayout>
      <Card className="p-6">Accès refusé</Card>
    </AppLayout>
  );

  return (
    <AppLayout>
      <PageHeader title="Pointage — Dashboard" description="Synthèse avancée du pointage: heures actives, sessions et tendances" icon={<BarChart2 className="h-5 w-5" />} actions={(
        <>
          <Button variant="outline" size="sm" onClick={() => void load()}><RefreshCw className="h-4 w-4 mr-1.5" /> Actualiser</Button>
        </>
      )} />

      <div className="grid lg:grid-cols-4 gap-4 mt-6">
        <Card className="p-4">
          <div className="text-sm text-muted-foreground">Période</div>
          <div className="mt-2 flex gap-2 items-center">
            <input type="date" value={start} onChange={(e) => setStart(e.target.value)} className="border rounded px-2 py-1" />
            <input type="date" value={end} onChange={(e) => setEnd(e.target.value)} className="border rounded px-2 py-1" />
          </div>
        </Card>
        <Card className="p-4">
          <div className="text-sm text-muted-foreground">Heures totales</div>
          <div className="text-2xl font-semibold mt-2">{fmtMin(totalMinutes)}</div>
        </Card>
        <Card className="p-4">
          <div className="text-sm text-muted-foreground">Sessions</div>
          <div className="text-2xl font-semibold mt-2">{totalSessions}</div>
        </Card>
        <Card className="p-4">
          <div className="text-sm text-muted-foreground">Moyenne / jour</div>
          <div className="text-2xl font-semibold mt-2">{fmtMin(Math.round(avgPerDay))}</div>
        </Card>
      </div>

      <div className="mt-6">
        <Card className="p-4">
          <h3 className="font-semibold mb-3">Tendance quotidienne</h3>
          {loading ? <div>Chargement…</div> : aggregates.length === 0 ? <div className="text-muted-foreground">Aucune donnée</div> : <BarChart data={aggregates} />}
        </Card>
      </div>

      <div className="mt-6">
        <Card className="p-4">
          <h3 className="font-semibold mb-3">Détail par jour</h3>
          <div className="overflow-x-auto">
            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead>Période</TableHead>
                  <TableHead>Durée</TableHead>
                  <TableHead>Sessions</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                {aggregates.map((a) => (
                  <TableRow key={a.period}>
                    <TableCell>{a.period}</TableCell>
                    <TableCell>{fmtMin(a.minutes)}</TableCell>
                    <TableCell>{a.sessions}</TableCell>
                  </TableRow>
                ))}
              </TableBody>
            </Table>
          </div>
        </Card>
      </div>
    </AppLayout>
  );
}
