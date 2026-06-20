import { createFileRoute, Link, useNavigate } from "@tanstack/react-router";
import { AppLayout } from "@/components/AppLayout";
import { PageHeader } from "@/components/PageHeader";
import {
  ArrowRightLeft, ArrowLeft, Download, Printer, FileJson, FileSpreadsheet,
  Phone, Mail, MapPin, User, Pencil, History, Activity, CheckCircle2, X,
  LayoutGrid, Paperclip, Sparkles, Network, RotateCcw, Clock, ArrowRight,
} from "lucide-react";
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { Badge } from "@/components/ui/badge";
import { Label } from "@/components/ui/label";
import {
  DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger, DropdownMenuSeparator, DropdownMenuLabel,
} from "@/components/ui/dropdown-menu";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Tabs, TabsList, TabsTrigger, TabsContent } from "@/components/ui/tabs";
import { useAuth } from "@/lib/auth";
import { canViewMigrationsData } from "@/lib/permissions";
import { api, API_ENABLED } from "@/lib/api";
import { fetchMigrationById } from "@/lib/migrationsApi";
import type { Migration, PipelineStage } from "@/lib/types";
import { useEffect, useMemo, useState } from "react";
import { toast } from "sonner";
import { AttachmentsCard } from "@/components/AttachmentsCard";
import { CustomFieldsCard } from "@/components/CustomFieldsCard";
import { ContractInfoCard } from "@/components/ContractInfoCard";
import { ClientIdentityCard } from "@/components/ClientIdentityCard";
import { JourneyTimeline } from "@/components/JourneyTimeline";
import { OriginOpportunityCard } from "@/components/OriginOpportunityCard";
import { LastModifiedInfo } from "@/components/LastModifiedInfo";
import { EntityActivityLogCard } from "@/components/EntityActivityLogCard";
import { useMigrationStages } from "@/hooks/use-migration-stages";
import { useErp } from "@/lib/erpStore";
import { exportCSV, exportJSON, printPage } from "@/lib/exportUtils";
import { confirmDialog } from "@/components/ConfirmDialogProvider";

export const Route = createFileRoute("/migrations/$migrationId")({
  head: ({ params }) => ({
    meta: [
      { title: `Migration ${params.migrationId} — CRM` },
      { name: "description", content: "Détail du dossier migration opérateur." },
    ],
  }),
  component: MigrationDetailPage,
  notFoundComponent: () => (
    <AppLayout skeleton="detail">
      <div className="p-10 text-center">
        <h2 className="text-xl font-semibold">Migration introuvable</h2>
        <Link to="/migrations" className="text-primary text-sm mt-2 inline-block">← Retour aux migrations</Link>
      </div>
    </AppLayout>
  ),
});

const workflowColor: Record<string, string> = {
  "Créer": "bg-info/15 text-info border-info/20",
  "Retour": "bg-destructive/15 text-destructive border-destructive/20",
  "Mes non connecté": "bg-warning/15 text-warning-foreground border-warning/20",
  "Validé": "bg-success/15 text-success border-success/20",
};
const colorClass = (c?: string) =>
  c ? `bg-${c}/15 text-${c} border-${c}/20` : "bg-muted text-muted-foreground border-border";

function InfoLine({ icon, value }: { icon: React.ReactNode; value?: string | null }) {
  if (!value) return null;
  return (
    <div className="flex items-center gap-2 text-muted-foreground">
      {icon}<span className="text-foreground">{value}</span>
    </div>
  );
}

function MigrationDetailPage() {
  const { migrationId } = Route.useParams();
  const navigate = useNavigate();
  const { user, hasPermission } = useAuth();
  const { users, prospects, refresh } = useErp();
  const stages = useMigrationStages();

  const [m, setM] = useState<Migration | null>(null);
  const [lookupState, setLookupState] = useState<"idle" | "loading" | "missing">("loading");
  const [reverting, setReverting] = useState(false);
  const [busy, setBusy] = useState(false);

  const canLoad = canViewMigrationsData(hasPermission);
  const isAgent = ["Agent", "AgentSuivi", "AgentActivation", "AgentVente"].includes(user?.role ?? "");
  const canEdit = hasPermission("migration.edit");
  const canRevert = hasPermission("migration.revert");
  const canExport = hasPermission("migration.export");
  const canViewJourney = hasPermission("lead.history");

  const reload = async () => {
    if (!API_ENABLED || !canLoad) {
      setLookupState("missing");
      return null;
    }
    try {
      const row = await fetchMigrationById(migrationId);
      setM(row);
      setLookupState(row ? "idle" : "missing");
      return row;
    } catch {
      setM(null);
      setLookupState("missing");
      return null;
    }
  };

  useEffect(() => {
    setLookupState("loading");
    void reload();
  // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [migrationId]);

  if (lookupState === "loading") {
    return <AppLayout skeleton="detail"><div /></AppLayout>;
  }

  if (!m) {
    return (
      <AppLayout skeleton="detail">
        <div className="p-10 text-center">
          <h2 className="text-xl font-semibold">Migration introuvable</h2>
          <p className="text-sm text-muted-foreground mt-2">L&apos;identifiant {migrationId} n&apos;existe pas.</p>
          <Button className="mt-4" onClick={() => navigate({ to: "/migrations" })}>
            <ArrowLeft className="h-4 w-4 mr-1.5" />Retour
          </Button>
        </div>
      </AppLayout>
    );
  }

  if (isAgent && m.assignedTo !== user?.username) {
    return (
      <AppLayout skeleton="detail">
        <div className="p-10 text-center">
          <h2 className="text-xl font-semibold">Accès restreint</h2>
          <p className="text-sm text-muted-foreground mt-2">Ce dossier n&apos;est pas dans votre portefeuille.</p>
          <Button className="mt-4" onClick={() => navigate({ to: "/migrations" })}>
            <ArrowLeft className="h-4 w-4 mr-1.5" />Retour
          </Button>
        </div>
      </AppLayout>
    );
  }

  return <MigrationDetailsView migration={m} onReload={reload} busy={busy} setBusy={setBusy}
    stages={stages} users={users} prospects={prospects}
    canEdit={canEdit} canRevert={canRevert} canExport={canExport} canViewJourney={canViewJourney}
    reverting={reverting} setReverting={setReverting} refresh={refresh} navigate={navigate} />;
}

function MigrationDetailsView({
  migration: m,
  onReload,
  busy,
  setBusy,
  stages,
  users,
  prospects,
  canEdit,
  canRevert,
  canExport,
  canViewJourney,
  reverting,
  setReverting,
  refresh,
  navigate,
}: {
  migration: Migration;
  onReload: () => Promise<Migration | null>;
  busy: boolean;
  setBusy: (v: boolean) => void;
  stages: PipelineStage[];
  users: { username: string; fullName: string; role: string }[];
  prospects: { id: string; lastName: string; firstName: string; phone?: string; email?: string; city?: string; createdAt?: string }[];
  canEdit: boolean;
  canRevert: boolean;
  canExport: boolean;
  canViewJourney: boolean;
  reverting: boolean;
  setReverting: (v: boolean) => void;
  refresh?: () => Promise<void>;
  navigate: ReturnType<typeof useNavigate>;
}) {
  const stageByName = useMemo(() => Object.fromEntries(stages.map((s) => [s.name, s])), [stages]);
  const agent = useMemo(() => users.find((u) => u.username === m.assignedTo), [users, m.assignedTo]);
  const linkedProspect = useMemo(
    () => (m.prospectId ? prospects.find((p) => p.id === m.prospectId) : prospects.find(
      (p) => p.lastName === m.lastName && p.firstName === m.firstName,
    )),
    [prospects, m],
  );

  const patch = async (body: Record<string, unknown>) => {
    try {
      setBusy(true);
      await api("/migrations.php", { method: "PATCH", body: { id: m.id, ...body } });
      await onReload();
      toast.success("Enregistré");
    } catch (e: unknown) {
      toast.error(e instanceof Error ? e.message : "Échec");
    } finally {
      setBusy(false);
    }
  };

  const handleWorkflowChange = async (status: string) => {
    await patch({ workflowStatus: status });
    toast.success("Statut mis à jour", { description: status });
  };

  const handleExportCSV = () => {
    exportCSV(`migration-${m.id}.csv`, [{
      id: m.id,
      nom: m.lastName,
      prenom: m.firstName,
      ancien_operateur: m.oldOperator,
      nouvel_operateur: m.newOperator,
      portabilite: m.portingNumber,
      statut_workflow: m.workflowStatus,
      statut_technique: m.technicalStatus,
      date_demande: m.requestedDate ?? "",
      date_fin: m.completedDate ?? "",
      agent: m.assignedTo,
    }]);
    toast.success("Export Excel généré");
  };

  const handleExportJSON = () => {
    exportJSON(`migration-${m.id}.json`, { migration: m, agent: agent?.fullName, prospect: linkedProspect });
    toast.success("Export JSON généré");
  };

  const timeline = useMemo(() => {
    const items: { id: string; date: string; title: string; description?: string; done: boolean }[] = [];
    if (linkedProspect?.createdAt) {
      items.push({ id: "lead", date: linkedProspect.createdAt, title: "Lead créé", done: true });
    }
    if (m.requestedDate) {
      items.push({ id: "req", date: m.requestedDate, title: "Demande de migration", description: m.migrationType, done: true });
    }
    if (m.completedDate) {
      items.push({ id: "done", date: m.completedDate, title: "Migration terminée", description: m.technicalStatus, done: true });
    } else {
      items.push({ id: "done-pending", date: "—", title: "Migration terminée", description: "En attente", done: false });
    }
    if (m.validatedAt) {
      items.push({ id: "val", date: m.validatedAt.slice(0, 10), title: "Validation", description: m.workflowStatus, done: true });
    }
    return items.sort((a, b) => (a.date === "—" ? 1 : b.date === "—" ? -1 : a.date.localeCompare(b.date)));
  }, [m, linkedProspect]);

  const badgeClass = workflowColor[m.workflowStatus] ?? colorClass(stageByName[m.workflowStatus]?.color);

  return (
    <AppLayout skeleton="detail">
      <PageHeader
        title={`${m.firstName} ${m.lastName}`}
        description={`Migration ${m.id}${m.oldOperator ? ` · ${m.oldOperator} → ${m.newOperator ?? "?"}` : ""}`}
        icon={<ArrowRightLeft className="h-5 w-5" />}
        actions={
          <>
            <Button variant="outline" size="sm" onClick={() => navigate({ to: "/migrations" })}>
              <ArrowLeft className="h-4 w-4 mr-1.5" />Retour
            </Button>
            {canEdit && (
              <Button variant="outline" size="sm" asChild>
                <Link to="/migrations/$migrationId/edit" params={{ migrationId: m.id }}>
                  <Pencil className="h-4 w-4 mr-1.5" />Modifier
                </Link>
              </Button>
            )}
            {canRevert && m.opportunityId && (
              <Button
                size="sm"
                variant="outline"
                disabled={reverting}
                className="border-warning/30 text-warning-foreground hover:bg-warning/10"
                onClick={async () => {
                  if (!(await confirmDialog({
                    title: "Retour opportunité",
                    description: "Renvoyer ce dossier dans la liste des opportunités ? La migration sera supprimée.",
                    tone: "destructive",
                    confirmText: "Confirmer",
                  }))) return;
                  setReverting(true);
                  try {
                    const r = await api<{ opportunityId: string }>("/migrations.php", {
                      method: "POST",
                      body: { action: "revert_to_opportunity", id: m.id },
                    });
                    toast.success("Migration retournée en opportunité");
                    await refresh?.();
                    navigate({ to: "/opportunities/$opportunityId", params: { opportunityId: r.opportunityId } });
                  } catch (e: unknown) {
                    toast.error(e instanceof Error ? e.message : "Échec");
                    setReverting(false);
                  }
                }}
              >
                <RotateCcw className={`h-4 w-4 mr-1.5 ${reverting ? "animate-spin" : ""}`} />
                Retour opportunité
              </Button>
            )}
            {canExport && (
              <DropdownMenu>
                <DropdownMenuTrigger asChild>
                  <Button variant="outline" size="sm"><Download className="h-4 w-4 mr-1.5" />Exporter</Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end" className="w-44">
                  <DropdownMenuLabel className="text-xs">Format</DropdownMenuLabel>
                  <DropdownMenuItem onClick={handleExportCSV}>
                    <FileSpreadsheet className="h-4 w-4 mr-2" />Excel
                  </DropdownMenuItem>
                  <DropdownMenuItem onClick={handleExportJSON}>
                    <FileJson className="h-4 w-4 mr-2" />JSON
                  </DropdownMenuItem>
                  <DropdownMenuSeparator />
                  <DropdownMenuItem onClick={printPage}>
                    <Printer className="h-4 w-4 mr-2" />Imprimer / PDF
                  </DropdownMenuItem>
                </DropdownMenuContent>
              </DropdownMenu>
            )}
          </>
        }
      />

      <div className="grid grid-cols-1 lg:grid-cols-3 gap-4 mt-6">
        <div className="lg:col-span-2">
          <Tabs defaultValue="overview" className="space-y-4">
            <TabsList className="h-auto flex-wrap justify-start gap-1 bg-muted/60 p-1">
              <TabsTrigger value="overview" className="gap-1.5"><LayoutGrid className="h-3.5 w-3.5" />Vue d&apos;ensemble</TabsTrigger>
              {m.opportunityId && (
                <TabsTrigger value="sources" className="gap-1.5"><User className="h-3.5 w-3.5" />Opportunité source</TabsTrigger>
              )}
              <TabsTrigger value="activity" className="gap-1.5"><Activity className="h-3.5 w-3.5" />Activité</TabsTrigger>
              {canViewJourney && (
                <TabsTrigger value="journey" className="gap-1.5"><History className="h-3.5 w-3.5" />Parcours complet</TabsTrigger>
              )}
              <TabsTrigger value="attachments" className="gap-1.5"><Paperclip className="h-3.5 w-3.5" />Pièces jointes</TabsTrigger>
              <TabsTrigger value="custom" className="gap-1.5"><Sparkles className="h-3.5 w-3.5" />Champs perso</TabsTrigger>
              <TabsTrigger value="contract-info" className="gap-1.5"><Network className="h-3.5 w-3.5" />Info technique</TabsTrigger>
            </TabsList>

            <TabsContent value="overview" className="space-y-4 mt-0">
              <ClientIdentityCard
                data={m as any}
                title="Identité client"
                description="Snapshot issu de l'opportunité convertie"
                enrichFromProspectId={m.prospectId ?? null}
              />

              <Card className="shadow-elegant">
                <CardHeader className="pb-3">
                  <div className="flex items-start justify-between gap-3 flex-wrap">
                    <div>
                      <CardTitle className="text-base">Résumé migration</CardTitle>
                      <CardDescription>Opérateurs, portabilité et statuts</CardDescription>
                    </div>
                    <Badge variant="outline" className={badgeClass}>{m.workflowStatus || "—"}</Badge>
                  </div>
                </CardHeader>
                <CardContent className="space-y-4">
                  <div className="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                    <div><span className="text-muted-foreground text-xs">Ancien opérateur</span><p className="font-medium">{m.oldOperator || "—"}</p></div>
                    <div><span className="text-muted-foreground text-xs">Nouvel opérateur</span><p className="font-medium">{m.newOperator || "—"}</p></div>
                    <div><span className="text-muted-foreground text-xs">N° portabilité</span><p className="font-medium">{m.portingNumber || "—"}</p></div>
                    <div><span className="text-muted-foreground text-xs">Type</span><p className="font-medium">{m.migrationType || "—"}</p></div>
                    <div><span className="text-muted-foreground text-xs">Statut technique</span><p className="font-medium">{m.technicalStatus || "—"}</p></div>
                    <div><span className="text-muted-foreground text-xs">Réf. externe</span><p className="font-medium">{m.externalRef || "—"}</p></div>
                  </div>
                  <LastModifiedInfo
                    kind="migration"
                    id={m.id}
                    createdAt={m.createdAt ?? null}
                    createdBy={m.createdBy ?? null}
                  />
                </CardContent>
              </Card>

              {canEdit && (
                <Card className="shadow-elegant">
                  <CardHeader className="pb-3">
                    <CardTitle className="text-base">Actions workflow</CardTitle>
                    <CardDescription>Changer l&apos;étape du dossier migration</CardDescription>
                  </CardHeader>
                  <CardContent className="grid grid-cols-1 gap-4">
                    <div className="space-y-1.5">
                      <Label className="text-xs">Statut workflow</Label>
                      <Select value={m.workflowStatus} onValueChange={handleWorkflowChange} disabled={busy}>
                        <SelectTrigger><SelectValue /></SelectTrigger>
                        <SelectContent>
                          {(stages.length ? stages.map((s) => s.name) : [m.workflowStatus]).map((s) => (
                            <SelectItem key={s} value={s}>{s}</SelectItem>
                          ))}
                        </SelectContent>
                      </Select>
                    </div>
                    <div className="flex flex-wrap gap-2 pt-2 border-t border-border">
                      {stages.filter((s) => s.isWon).slice(0, 1).map((s) => (
                        <Button key={s.id} size="sm" variant="outline" disabled={busy}
                          onClick={() => handleWorkflowChange(s.name)}
                          className="border-success/30 text-success hover:bg-success/10">
                          <CheckCircle2 className="h-4 w-4 mr-1.5" />{s.name}
                        </Button>
                      ))}
                      {stages.filter((s) => s.isLost).slice(0, 1).map((s) => (
                        <Button key={s.id} size="sm" variant="outline" disabled={busy}
                          onClick={() => handleWorkflowChange(s.name)}
                          className="border-destructive/30 text-destructive hover:bg-destructive/10">
                          <X className="h-4 w-4 mr-1.5" />{s.name}
                        </Button>
                      ))}
                    </div>
                  </CardContent>
                </Card>
              )}

              <Card className="shadow-elegant">
                <CardHeader className="pb-3">
                  <CardTitle className="text-base">Parcours migration</CardTitle>
                  <CardDescription>Étapes clés du dossier</CardDescription>
                </CardHeader>
                <CardContent>
                  <ol className="relative border-l border-border ml-3 space-y-5">
                    {timeline.map((t) => (
                      <li key={t.id} className="ml-6">
                        <span className={`absolute -left-3 flex h-6 w-6 items-center justify-center rounded-full ring-4 ring-background ${
                          t.done ? "bg-primary text-primary-foreground" : "bg-muted text-muted-foreground"
                        }`}>
                          <Clock className="h-3 w-3" />
                        </span>
                        <div className="flex flex-wrap items-baseline gap-x-2">
                          <h4 className="text-sm font-semibold">{t.title}</h4>
                          <span className="text-xs text-muted-foreground">{t.date}</span>
                          {!t.done && <Badge variant="outline" className="text-[10px] py-0">à venir</Badge>}
                        </div>
                        {t.description && <p className="text-xs text-muted-foreground mt-0.5">{t.description}</p>}
                      </li>
                    ))}
                  </ol>
                </CardContent>
              </Card>
            </TabsContent>

            <TabsContent value="activity" className="mt-0">
              <EntityActivityLogCard entityType="migration" entityId={m.id} />
            </TabsContent>

            {canViewJourney && (
              <TabsContent value="journey" className="mt-0">
                <JourneyTimeline
                  prospectId={m.prospectId ?? m.opportunityId ?? m.id}
                  opportunityId={m.opportunityId ?? null}
                  contractId={null}
                  migrationId={m.id}
                />
              </TabsContent>
            )}

            <TabsContent value="attachments" className="mt-0">
              <AttachmentsCard
                entity="migration"
                entityId={m.id}
                extraSources={[
                  ...(m.prospectId ? [{ entity: "prospect" as const, entityId: m.prospectId, label: "Prospect" }] : []),
                  ...(m.opportunityId ? [{ entity: "opportunity" as const, entityId: m.opportunityId, label: "Opportunité" }] : []),
                ]}
              />
            </TabsContent>

            <TabsContent value="custom" className="mt-0">
              <CustomFieldsCard entity="migration" entityId={m.id} typeId={m.typeId ?? null} />
            </TabsContent>

            <TabsContent value="contract-info" className="mt-0">
              <ContractInfoCard entity="migration" entityId={m.id} />
            </TabsContent>

            {m.opportunityId && (
              <TabsContent value="sources" className="mt-0 space-y-4">
                <OriginOpportunityCard opportunityId={m.opportunityId} />
                <CustomFieldsCard entity="opportunity" entityId={m.opportunityId} />
              </TabsContent>
            )}
          </Tabs>
        </div>

        <div className="space-y-4">
          <Card className="shadow-elegant">
            <CardHeader className="pb-3"><CardTitle className="text-base">Synthèse</CardTitle></CardHeader>
            <CardContent className="space-y-2 text-sm">
              <div className="flex items-center justify-between">
                <span className="text-muted-foreground">Workflow</span>
                <Badge variant="outline" className={badgeClass}>{m.workflowStatus || "—"}</Badge>
              </div>
              <div className="flex items-center justify-between">
                <span className="text-muted-foreground">Technique</span>
                <span className="font-medium truncate">{m.technicalStatus || "—"}</span>
              </div>
              <div className="flex items-center justify-between">
                <span className="text-muted-foreground">Demandée</span>
                <span>{m.requestedDate ? new Date(m.requestedDate).toLocaleDateString("fr-FR") : "—"}</span>
              </div>
              <div className="flex items-center justify-between">
                <span className="text-muted-foreground">Agent</span>
                <span>{agent?.fullName ?? <span className="italic text-muted-foreground">Non assigné</span>}</span>
              </div>
            </CardContent>
          </Card>

          <Card className="shadow-elegant">
            <CardHeader className="pb-3"><CardTitle className="text-base">Adhérent</CardTitle></CardHeader>
            <CardContent className="space-y-3">
              <div className="flex items-center gap-3">
                <div className="h-12 w-12 rounded-full bg-primary text-primary-foreground flex items-center justify-center font-semibold">
                  {(m.firstName[0] ?? "")}{(m.lastName[0] ?? "")}
                </div>
                <div>
                  <div className="font-semibold">{m.firstName} {m.lastName}</div>
                  <div className="text-xs text-muted-foreground">Migration #{m.id}</div>
                </div>
              </div>
              <InfoLine icon={<Phone className="h-3.5 w-3.5" />} value={m.phone} />
              <InfoLine icon={<Mail className="h-3.5 w-3.5" />} value={m.email} />
              <InfoLine icon={<MapPin className="h-3.5 w-3.5" />} value={m.city} />
              {linkedProspect && (
                <Button variant="link" size="sm" className="px-0 h-auto" asChild>
                  <Link to="/prospects/$prospectId" params={{ prospectId: linkedProspect.id }}>
                    Voir le lead <ArrowRight className="h-3 w-3 ml-1" />
                  </Link>
                </Button>
              )}
            </CardContent>
          </Card>

          {agent && (
            <Card className="shadow-elegant">
              <CardHeader className="pb-3"><CardTitle className="text-base">Agent assigné</CardTitle></CardHeader>
              <CardContent className="text-sm">
                <div className="font-medium">{agent.fullName}</div>
                <div className="text-xs text-muted-foreground">@{agent.username}</div>
              </CardContent>
            </Card>
          )}
        </div>
      </div>
    </AppLayout>
  );
}
