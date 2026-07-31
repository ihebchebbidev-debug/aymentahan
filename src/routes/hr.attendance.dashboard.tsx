import { createFileRoute } from "@tanstack/react-router";
import { AppLayout } from "@/components/AppLayout";
import { PageHeader } from "@/components/PageHeader";
import { Card } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";
import { Clock, RefreshCw, BarChart2 } from "lucide-react";
import { useEffect, useMemo, useState } from "react";
import { api } from "@/lib/api";
import { useAuth } from "@/lib/auth";
import type { AttendanceAggregate, AttendanceGroupSummary, AttendanceUserSummary } from "@/lib/types";
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
  const { hasPermission, permissionsHydrated } = useAuth();
  const canView = permissionsHydrated && (hasPermission("page.hr.attendance_dashboard") || hasPermission("page.hr.attendance"));
  const [start, setStart] = useState<string>(new Date(new Date().getFullYear(), new Date().getMonth(), 1).toISOString().slice(0, 10));
  const [end, setEnd] = useState<string>(new Date().toISOString().slice(0, 10));
  const [aggregates, setAggregates] = useState<AttendanceAggregate[]>([]);
  const [userSummaries, setUserSummaries] = useState<AttendanceUserSummary[]>([]);
  const [roleSummaries, setRoleSummaries] = useState<AttendanceGroupSummary[]>([]);
  const [teamSummaries, setTeamSummaries] = useState<AttendanceGroupSummary[]>([]);
  const [selectedUser, setSelectedUser] = useState<string | null>(null);
  const [selectedUserDetail, setSelectedUserDetail] = useState<AttendanceAggregate[]>([]);
  const [loading, setLoading] = useState(false);
  const [detailLoading, setDetailLoading] = useState(false);
  const [activeTab, setActiveTab] = useState("days");

  useEffect(() => {
    if (!canView) return;
    void load();
  }, [start, end, canView]);

  const load = async () => {
    if (!canView) return;
    setLoading(true);
    try {
      const [aggResp, summaryResp] = await Promise.all([
        api<{ aggregates: AttendanceAggregate[] }>("/attendance.php", { query: { action: "aggregates", start, end } }),
        api<{ users: AttendanceUserSummary[]; roles: AttendanceGroupSummary[]; teams: AttendanceGroupSummary[] }>("/attendance.php", {
          query: { action: "summary", start, end },
        }),
      ]);
      setAggregates(aggResp.aggregates ?? []);
      setUserSummaries(summaryResp.users ?? []);
      setRoleSummaries(summaryResp.roles ?? []);
      setTeamSummaries(summaryResp.teams ?? []);
    } catch (e: any) {
      toast.error(e?.message ?? "Erreur");
    } finally {
      setLoading(false);
    }
  };

  const loadUserDetails = async (username: string) => {
    setSelectedUser(username);
    setDetailLoading(true);
    try {
      const r = await api<{ aggregates: AttendanceAggregate[] }>("/attendance.php", {
        query: { action: "aggregates", start, end, username },
      });
      setSelectedUserDetail(r.aggregates ?? []);
    } catch (e: any) {
      toast.error(e?.message ?? "Erreur");
    } finally {
      setDetailLoading(false);
    }
  };

  const totalMinutes = useMemo(() => aggregates.reduce((s, a) => s + (a.minutes || 0), 0), [aggregates]);
  const totalSessions = useMemo(() => aggregates.reduce((s, a) => s + (a.sessions || 0), 0), [aggregates]);
  const daysCount = aggregates.length;
  const avgPerDay = daysCount ? Math.round((totalMinutes / daysCount) * 10) / 10 : 0;
  const avgSessionMinutes = totalSessions ? Math.round((totalMinutes / totalSessions) * 10) / 10 : 0;
  const sessionsPerDay = daysCount ? Math.round((totalSessions / daysCount) * 10) / 10 : 0;
  const maxDaily = daysCount ? Math.max(...aggregates.map((a) => a.minutes || 0)) : 0;
  const minDaily = daysCount ? Math.min(...aggregates.map((a) => a.minutes || 0)) : 0;
  const busiest = daysCount ? aggregates.reduce((best, a) => (a.minutes || 0) > (best.minutes || 0) ? a : best, aggregates[0]) : null;

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

      <div className="grid gap-4 mt-6 sm:grid-cols-2 xl:grid-cols-4">
        <Card className="p-4">
          <div className="text-sm text-muted-foreground">Heures totales</div>
          <div className="text-3xl font-semibold mt-3">{fmtMin(totalMinutes)}</div>
        </Card>
        <Card className="p-4">
          <div className="text-sm text-muted-foreground">Sessions</div>
          <div className="text-3xl font-semibold mt-3">{totalSessions}</div>
        </Card>
        <Card className="p-4">
          <div className="text-sm text-muted-foreground">Durée moyenne / session</div>
          <div className="text-3xl font-semibold mt-3">{avgSessionMinutes} min</div>
        </Card>
        <Card className="p-4">
          <div className="text-sm text-muted-foreground">Jours couverts</div>
          <div className="text-3xl font-semibold mt-3">{daysCount}</div>
        </Card>
      </div>

      <div className="grid gap-4 mt-4 sm:grid-cols-3">
        <Card className="p-4">
          <div className="text-sm text-muted-foreground">Sessions / jour</div>
          <div className="text-xl font-semibold mt-2">{sessionsPerDay}</div>
        </Card>
        <Card className="p-4">
          <div className="text-sm text-muted-foreground">Max / jour</div>
          <div className="text-xl font-semibold mt-2">{fmtMin(maxDaily)}</div>
        </Card>
        <Card className="p-4">
          <div className="text-sm text-muted-foreground">Min / jour</div>
          <div className="text-xl font-semibold mt-2">{fmtMin(minDaily)}</div>
        </Card>
      </div>

      <div className="mt-6">
        <Tabs value={activeTab} onValueChange={setActiveTab}>
          <TabsList className="grid grid-cols-2 gap-2 md:grid-cols-4">
            <TabsTrigger value="days">Par jour</TabsTrigger>
            <TabsTrigger value="users">Par utilisateur</TabsTrigger>
            <TabsTrigger value="roles">Par rôle</TabsTrigger>
            <TabsTrigger value="teams">Par équipe</TabsTrigger>
          </TabsList>

          <TabsContent value="days">
            <Card className="p-4">
              <h3 className="font-semibold mb-3">Tendance quotidienne</h3>
              {loading ? <div>Chargement…</div> : aggregates.length === 0 ? <div className="text-muted-foreground">Aucune donnée</div> : <BarChart data={aggregates} />}
              {busiest ? <div className="text-xs text-muted-foreground mt-3">Jour le plus chargé : {busiest.period} — {fmtMin(busiest.minutes)}</div> : null}
            </Card>
            <Card className="p-4 mt-6">
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
          </TabsContent>

          <TabsContent value="users">
            <Card className="p-4">
              <h3 className="font-semibold mb-3">Synthèse par utilisateur</h3>
              <div className="overflow-x-auto">
                <Table>
                  <TableHeader>
                    <TableRow>
                      <TableHead>Utilisateur</TableHead>
                      <TableHead>Rôle</TableHead>
                      <TableHead>Équipe</TableHead>
                      <TableHead>Heures</TableHead>
                      <TableHead>Sessions</TableHead>
                      <TableHead>Avg. / session</TableHead>
                      <TableHead>Action</TableHead>
                    </TableRow>
                  </TableHeader>
                  <TableBody>
                    {userSummaries.map((user) => (
                      <TableRow key={user.userId}>
                        <TableCell>{user.username}</TableCell>
                        <TableCell>{user.role || 'N/A'}</TableCell>
                        <TableCell>{user.team || 'N/A'}</TableCell>
                        <TableCell>{fmtMin(user.minutes)}</TableCell>
                        <TableCell>{user.sessions}</TableCell>
                        <TableCell>{user.avgMinutes} min</TableCell>
                        <TableCell>
                          <Button size="xs" variant="outline" onClick={() => void loadUserDetails(user.username)}>
                            Voir
                          </Button>
                        </TableCell>
                      </TableRow>
                    ))}
                  </TableBody>
                </Table>
              </div>
            </Card>
            {selectedUser ? (
              <Card className="p-4 mt-6">
                <div className="flex flex-wrap items-center justify-between gap-3 mb-4">
                  <div>
                    <h3 className="font-semibold">Détail utilisateur : {selectedUser}</h3>
                    <p className="text-sm text-muted-foreground">Période {start} → {end}</p>
                  </div>
                  <Button size="sm" variant="outline" onClick={() => loadUserDetails(selectedUser)}>
                    <RefreshCw className="h-4 w-4 mr-1.5" /> Actualiser
                  </Button>
                </div>
                {detailLoading ? (
                  <div>Chargement…</div>
                ) : selectedUserDetail.length === 0 ? (
                  <div className="text-muted-foreground">Aucune donnée pour cet utilisateur</div>
                ) : (
                  <>
                    <div className="mb-4">
                      <BarChart data={selectedUserDetail} />
                    </div>
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
                          {selectedUserDetail.map((row) => (
                            <TableRow key={row.period}>
                              <TableCell>{row.period}</TableCell>
                              <TableCell>{fmtMin(row.minutes)}</TableCell>
                              <TableCell>{row.sessions}</TableCell>
                            </TableRow>
                          ))}
                        </TableBody>
                      </Table>
                    </div>
                  </>
                )}
              </Card>
            ) : null}
          </TabsContent>

          <TabsContent value="roles">
            <Card className="p-4">
              <h3 className="font-semibold mb-3">Synthèse par rôle</h3>
              <div className="overflow-x-auto">
                <Table>
                  <TableHeader>
                    <TableRow>
                      <TableHead>Rôle</TableHead>
                      <TableHead>Heures</TableHead>
                      <TableHead>Sessions</TableHead>
                      <TableHead>Avg. / session</TableHead>
                    </TableRow>
                  </TableHeader>
                  <TableBody>
                    {roleSummaries.map((row) => (
                      <TableRow key={row.group}>
                        <TableCell>{row.group}</TableCell>
                        <TableCell>{fmtMin(row.minutes)}</TableCell>
                        <TableCell>{row.sessions}</TableCell>
                        <TableCell>{row.avgMinutes} min</TableCell>
                      </TableRow>
                    ))}
                  </TableBody>
                </Table>
              </div>
            </Card>
          </TabsContent>

          <TabsContent value="teams">
            <Card className="p-4">
              <h3 className="font-semibold mb-3">Synthèse par équipe</h3>
              <div className="overflow-x-auto">
                <Table>
                  <TableHeader>
                    <TableRow>
                      <TableHead>Équipe</TableHead>
                      <TableHead>Heures</TableHead>
                      <TableHead>Sessions</TableHead>
                      <TableHead>Avg. / session</TableHead>
                    </TableRow>
                  </TableHeader>
                  <TableBody>
                    {teamSummaries.map((row) => (
                      <TableRow key={row.group}>
                        <TableCell>{row.group}</TableCell>
                        <TableCell>{fmtMin(row.minutes)}</TableCell>
                        <TableCell>{row.sessions}</TableCell>
                        <TableCell>{row.avgMinutes} min</TableCell>
                      </TableRow>
                    ))}
                  </TableBody>
                </Table>
              </div>
            </Card>
          </TabsContent>
        </Tabs>
      </div>
    </AppLayout>
  );
}
