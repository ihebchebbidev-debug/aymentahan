import { createFileRoute, Link, useNavigate, useParams } from "@tanstack/react-router";
import { useEffect, useMemo, useState } from "react";
import { AppLayout } from "@/components/AppLayout";
import { PageHeader } from "@/components/PageHeader";
import { Card } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Badge } from "@/components/ui/badge";
import { Textarea } from "@/components/ui/textarea";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { AttachmentsCard } from "@/components/AttachmentsCard";
import { CommentThread } from "@/components/CommentThread";
import { api, API_ENABLED } from "@/lib/api";
import { useAuth } from "@/lib/auth";
import { toast } from "sonner";
import { ArrowLeft, MessageSquareWarning, Save, Trash2 } from "lucide-react";
import { confirmDialog } from "@/components/ConfirmDialogProvider";
import { useErp } from "@/lib/erpStore";

const SERVICES = ["Technique", "Facturation", "Commercial", "Autre"] as const;
type Service = (typeof SERVICES)[number];

const AUDIT = ["en_cours", "resolu", "annule"] as const;
type Audit = (typeof AUDIT)[number];
const AUDIT_LABEL: Record<Audit, string> = { en_cours: "En cours", resolu: "Résolu", annule: "Annulé" };
const AUDIT_CLASS: Record<Audit, string> = {
  en_cours: "bg-warning/15 text-warning-foreground border-warning/20",
  resolu: "bg-success/15 text-success border-success/20",
  annule: "bg-destructive/15 text-destructive border-destructive/20",
};

type Reclamation = {
  id: number;
  reference: string;
  tel_adsl: string | null;
  ref_demand: string | null;
  cin_client: string | null;
  gsm_client: string | null;
  client_name: string | null;
  service: Service;
  description: string | null;
  statut_crm: string | null;
  statut_tt: string | null;
  audit_status: Audit;
  localisation: string | null;
  etat: string | null;
  remarques: string | null;
  date_creation: string;
  date_resolution: string | null;
  assigned_to: string | null;
};

export const Route = createFileRoute("/reclamations/$id")({
  head: ({ params }) => ({
    meta: [{ title: `Réclamation ${params.id} — CRM` }],
  }),
  component: ReclamationDetailPage,
});

function ReclamationDetailPage() {
  const { id } = useParams({ from: "/reclamations/$id" });
  const navigate = useNavigate();
  const { user, hasPermission } = useAuth();
  const { users } = useErp();
  const canEdit = hasPermission("reclamation.edit");
  const canDelete = hasPermission("reclamation.delete");

  const [row, setRow] = useState<Reclamation | null>(null);
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);

  const numericId = useMemo(() => Number(id), [id]);
  const validId = Number.isFinite(numericId) && numericId > 0;
  const [draftDescriptionComment, setDraftDescriptionComment] = useState("");
  const [addingDescriptionComment, setAddingDescriptionComment] = useState(false);

  const descriptionHistory = useMemo(() => {
    const text = row?.description ?? "";
    const trimmed = text.trim();
    if (!trimmed) return [] as Array<{ id: string; author: string | null; date: string | null; body: string }>;

    return trimmed.split(/\n{2,}/).filter(Boolean).map((entry, index) => {
      const lines = entry.split(/\n/);
      const first = lines[0];
      const rest = lines.slice(1).join("\n");
      const match = first.match(/^\[([^\]]+)\]\s+([^:]+):\s*(.*)$/);
      if (match) {
        const [, date, author, firstBody] = match;
        const body = [firstBody, rest].filter(Boolean).join("\n");
        return { id: `description-${index}-${date}`, author, date, body };
      }
      return { id: `description-${index}`, author: null, date: null, body: entry };
    });
  }, [row?.description]);

  const buildDescriptionText = (existing: string | null, comment: string): string => {
    const base = existing?.trim();
    const now = new Date();
    const timestamp = now.toLocaleString("fr-FR", {
      year: "numeric",
      month: "2-digit",
      day: "2-digit",
      hour: "2-digit",
      minute: "2-digit",
    });
    const author = user?.fullName || user?.username || "Commentaire";
    const entry = `[${timestamp}] ${author}: ${comment}`;
    return base ? `${base}\n\n${entry}` : entry;
  };

  const addDescriptionComment = async () => {
    if (!row) return;
    const text = draftDescriptionComment.trim();
    if (!text) return;

    setAddingDescriptionComment(true);
    try {
      const nextDescription = buildDescriptionText(row.description, text);
      await api(`/reclamations.php?id=${row.id}`, { method: "PATCH", body: { description: nextDescription } });
      update({ description: nextDescription });
      setDraftDescriptionComment("");
      toast.success("Commentaire ajouté");
    } catch (e: any) {
      toast.error("Échec de l'ajout du commentaire", { description: e?.message });
    } finally {
      setAddingDescriptionComment(false);
    }
  };

  const assignedUserOptions = useMemo(
    () => (users ?? [])
      .filter((u: any) => ["Agent","Manager","AgentSuivi","AgentActivation","AgentVente","Administrateur"].includes(u.role))
      .map((u: any) => ({
        username: u.username,
        label: [u.fullName, u.username].filter(Boolean).join(" — "),
      })),
    [users],
  );

  useEffect(() => {
    let cancelled = false;
    async function load() {
      if (!API_ENABLED || !validId) { setLoading(false); return; }
      setLoading(true);
      try {
        // Try direct GET by id, then fallback to list filter
        let found: Reclamation | null = null;
        try {
          const r = await api<{ reclamation?: Reclamation; reclamations?: Reclamation[] }>(
            "/reclamations.php",
            { query: { id: numericId } },
          );
          found = r.reclamation ?? (r.reclamations ?? []).find((x) => x.id === numericId) ?? null;
        } catch {
          const r2 = await api<{ reclamation?: Reclamation }>("/reclamations.php", { query: { id: numericId } });
          found = r2.reclamation ?? null;
        }
        if (!cancelled) setRow(found);
        if (!found && !cancelled) toast.error("Réclamation introuvable");
      } catch (e: any) {
        toast.error("Chargement impossible", { description: e?.message });
      } finally {
        if (!cancelled) setLoading(false);
      }
    }
    load();
    return () => { cancelled = true; };
  }, [numericId, validId]);

  const update = (patch: Partial<Reclamation>) => setRow((r) => (r ? { ...r, ...patch } : r));

  const save = async () => {
    if (!row) return;
    setSaving(true);
    try {
      await api(`/reclamations.php?id=${row.id}`, { method: "PATCH", body: row });
      toast.success("Réclamation mise à jour");
    } catch (e: any) {
      toast.error("Échec de l'enregistrement", { description: e?.message });
    } finally {
      setSaving(false);
    }
  };

  const remove = async () => {
    if (!row) return;
    if (!(await confirmDialog({ title: "Suppression", description: `Supprimer la réclamation ${row.reference} ?`, tone: "destructive", confirmText: "Supprimer" }))) return;
    try {
      await api(`/reclamations.php?id=${row.id}`, { method: "DELETE" });
      toast.success("Réclamation supprimée");
      navigate({ to: "/reclamations" });
    } catch (e: any) {
      toast.error("Suppression impossible", { description: e?.message });
    }
  };

  return (
    <AppLayout>
      <div className="space-y-4">
        <div className="flex items-center justify-between gap-2 flex-wrap">
          <Button asChild variant="outline" size="sm">
            <Link to="/reclamations">
              <ArrowLeft className="h-4 w-4 mr-1" /> Retour
            </Link>
          </Button>
          <div className="flex gap-2">
            {canDelete && row && (
              <Button variant="destructive" size="sm" onClick={remove}>
                <Trash2 className="h-4 w-4 mr-1" /> Supprimer
              </Button>
            )}
            {canEdit && row && (
              <Button size="sm" onClick={save} disabled={saving}>
                <Save className="h-4 w-4 mr-1" /> {saving ? "Enregistrement…" : "Enregistrer"}
              </Button>
            )}
          </div>
        </div>

        <PageHeader
          icon={<MessageSquareWarning className="h-5 w-5" />}
          title={row ? `Réclamation ${row.reference}` : loading ? "Chargement…" : "Réclamation"}
          description={row ? row.client_name ?? "—" : ""}
          actions={row ? <Badge className={AUDIT_CLASS[row.audit_status]}>{AUDIT_LABEL[row.audit_status]}</Badge> : null}
        />

        {!validId ? (
          <Card className="p-6 text-sm text-destructive">Identifiant invalide.</Card>
        ) : loading ? (
          <Card className="p-6 text-sm text-muted-foreground">Chargement…</Card>
        ) : !row ? (
          <Card className="p-6 text-sm text-muted-foreground">Réclamation introuvable.</Card>
        ) : (
          <>
            <Card className="p-4">
              <div className="grid grid-cols-1 md:grid-cols-2 gap-3">
                <Field label="Tél ADSL"><Input value={row.tel_adsl ?? ""} disabled={!canEdit} onChange={(e) => update({ tel_adsl: e.target.value })} /></Field>
                <Field label="Réf demande"><Input value={row.ref_demand ?? ""} disabled={!canEdit} onChange={(e) => update({ ref_demand: e.target.value })} /></Field>
                <Field label="CIN client"><Input value={row.cin_client ?? ""} disabled={!canEdit} onChange={(e) => update({ cin_client: e.target.value })} /></Field>
                <Field label="GSM client"><Input value={row.gsm_client ?? ""} disabled={!canEdit} onChange={(e) => update({ gsm_client: e.target.value })} /></Field>
                <Field label="Client" className="md:col-span-2"><Input value={row.client_name ?? ""} disabled={!canEdit} onChange={(e) => update({ client_name: e.target.value })} /></Field>

                <Field label="Service">
                  <Select value={row.service} onValueChange={(v) => update({ service: v as Service })} disabled={!canEdit}>
                    <SelectTrigger><SelectValue /></SelectTrigger>
                    <SelectContent>
                      {SERVICES.map((s) => <SelectItem key={s} value={s}>{s}</SelectItem>)}
                    </SelectContent>
                  </Select>
                </Field>
                <Field label="Audit">
                  <Select value={row.audit_status} onValueChange={(v) => update({ audit_status: v as Audit })} disabled={!canEdit}>
                    <SelectTrigger><SelectValue /></SelectTrigger>
                    <SelectContent>
                      {AUDIT.map((a) => <SelectItem key={a} value={a}>{AUDIT_LABEL[a]}</SelectItem>)}
                    </SelectContent>
                  </Select>
                </Field>

                <Field label="Statut CRM"><Input value={row.statut_crm ?? ""} disabled={!canEdit} onChange={(e) => update({ statut_crm: e.target.value })} /></Field>
                <Field label="Statut TT"><Input value={row.statut_tt ?? ""} disabled={!canEdit} onChange={(e) => update({ statut_tt: e.target.value })} /></Field>
                <Field label="Localisation"><Input value={row.localisation ?? ""} disabled={!canEdit} onChange={(e) => update({ localisation: e.target.value })} /></Field>
                <Field label="État"><Input value={row.etat ?? ""} disabled={!canEdit} onChange={(e) => update({ etat: e.target.value })} /></Field>

                <Field label="Date création">
                  <Input type="datetime-local" disabled={!canEdit}
                    value={row.date_creation ? String(row.date_creation).slice(0, 16).replace(" ", "T") : ""}
                    onChange={(e) => update({ date_creation: e.target.value })} />
                </Field>
                <Field label="Date résolution">
                  <Input type="datetime-local" disabled={!canEdit}
                    value={row.date_resolution ? String(row.date_resolution).slice(0, 16).replace(" ", "T") : ""}
                    onChange={(e) => update({ date_resolution: e.target.value || null })} />
                </Field>

                <Field label="Assigné à" className="md:col-span-2">
                  <Select value={row.assigned_to ?? "__none__"} onValueChange={(v) => update({ assigned_to: v === "__none__" ? null : v })} disabled={!canEdit}>
                    <SelectTrigger><SelectValue placeholder="Sélectionner un utilisateur" /></SelectTrigger>
                    <SelectContent>
                      <SelectItem value="__none__">Aucun</SelectItem>
                      {assignedUserOptions.map((u) => (
                        <SelectItem key={u.username} value={u.username}>{u.label}</SelectItem>
                      ))}
                    </SelectContent>
                  </Select>
                </Field>
                <Field label="Remarques" className="md:col-span-2"><Textarea rows={2} value={row.remarques ?? ""} disabled={!canEdit} onChange={(e) => update({ remarques: e.target.value })} /></Field>
              </div>
            </Card>

            <Card className="p-4">
              <div className="flex items-center justify-between mb-3">
                <div>
                  <Label className="text-sm">Historique de la description</Label>
                  <p className="text-xs text-muted-foreground">Les commentaires s'ajoutent à l'historique sans écraser le contenu précédent.</p>
                </div>
                <span className="text-xs text-muted-foreground">{descriptionHistory.length} entrée{descriptionHistory.length > 1 ? 's' : ''}</span>
              </div>
              <CommentThread
                entries={descriptionHistory}
                emptyLabel="Aucun commentaire dans la description."
              />
              <div className="space-y-2 mt-4">
                <Textarea
                  rows={3}
                  value={draftDescriptionComment}
                  onChange={(e) => setDraftDescriptionComment(e.target.value)}
                  placeholder={canEdit ? 'Ajouter un commentaire à l’historique…' : 'Vous ne pouvez pas ajouter de commentaire.'}
                  disabled={!canEdit}
                />
                {canEdit && (
                  <div className="flex justify-end gap-2">
                    <Button size="sm" variant="outline" disabled={addingDescriptionComment || !draftDescriptionComment.trim()} onClick={() => setDraftDescriptionComment("")}>Effacer</Button>
                    <Button size="sm" disabled={addingDescriptionComment || !draftDescriptionComment.trim()} onClick={addDescriptionComment}>
                      Ajouter
                    </Button>
                  </div>
                )}
              </div>
            </Card>

            <Card className="p-4">
              <Label className="mb-2 block">Photos & pièces jointes</Label>
              <AttachmentsCard entity="reclamation" entityId={String(row.id)} />
            </Card>
          </>
        )}
      </div>
    </AppLayout>
  );
}

function Field({ label, className = "", children }: { label: string; className?: string; children: React.ReactNode }) {
  return (
    <div className={className}>
      <Label className="text-xs mb-1 block">{label}</Label>
      {children}
    </div>
  );
}
