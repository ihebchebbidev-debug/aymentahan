import { createFileRoute, Link, useNavigate } from "@tanstack/react-router";
import { useEffect, useMemo, useState } from "react";
import { ArrowLeft, Pencil } from "lucide-react";
import { AppLayout } from "@/components/AppLayout";
import { PageHeader } from "@/components/PageHeader";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Textarea } from "@/components/ui/textarea";
import { Label } from "@/components/ui/label";
import { Card } from "@/components/ui/card";
import { DatePicker } from "@/components/ui/date-picker";
import {
  Select, SelectContent, SelectItem, SelectTrigger, SelectValue,
} from "@/components/ui/select";
import { useErp } from "@/lib/erpStore";
import { useCrmListSync } from "@/hooks/useCrmListSync";
import type { Migration, PipelineStage, ProspectType } from "@/lib/types";
import { useMigrationStages } from "@/hooks/use-migration-stages";
import { ensureDefaultProspectTypes } from "@/lib/prospectTypes";
import { toast } from "sonner";
import {
  normalizeLocalisationXy, normalizeCodePostal, isValidLocalisationXy,
} from "@/lib/geo";
import { RequirePerm } from "@/components/RequirePerm";
import { fetchMigrationById, updateMigration } from "@/lib/migrationsApi";
import { API_ENABLED } from "@/lib/api";

export const Route = createFileRoute("/migrations/$migrationId_/edit")({
  head: ({ params }) => ({
    meta: [
      { title: `Modifier migration ${params.migrationId} — CRM` },
      { name: "description", content: "Modifier toutes les informations du dossier migration." },
    ],
  }),
  component: GuardedEditMigrationPage,
});

function GuardedEditMigrationPage() {
  return (
    <RequirePerm perm="migration.edit" backTo="/migrations" backLabel="Retour aux migrations">
      <EditMigrationPage />
    </RequirePerm>
  );
}

const SOURCES = ["Terrain", "Facebook", "Base de donné", "Technicien", "Autre"];
const MIGRATION_TYPES = ["Portabilité", "Nouvelle ligne", "Changement offre", "Autre"];

function EditMigrationPage() {
  const { migrationId } = Route.useParams();
  const navigate = useNavigate();
  const { users } = useErp();
  const { sync } = useCrmListSync();
  const stages: PipelineStage[] = useMigrationStages();
  const [types, setTypes] = useState<ProspectType[]>([]);
  const [migration, setMigration] = useState<Migration | null>(null);
  const [loading, setLoading] = useState(true);

  const [saving, setSaving] = useState(false);
  const [hydrated, setHydrated] = useState(false);

  const [civility, setCivility] = useState<"M" | "Mme">("M");
  const [lastName, setLastName] = useState("");
  const [firstName, setFirstName] = useState("");
  const [phone, setPhone] = useState("");
  const [phone2, setPhone2] = useState("");
  const [animateur, setAnimateur] = useState("");
  const [ancienLigne, setAncienLigne] = useState("");
  const [cin, setCin] = useState("");
  const [birthDate, setBirthDate] = useState("");
  const [email, setEmail] = useState("");
  const [gouvernorat, setGouvernorat] = useState("");
  const [delegation, setDelegation] = useState("");
  const [city, setCity] = useState("");
  const [zone, setZone] = useState("");
  const [address, setAddress] = useState("");
  const [localisationXy, setLocalisationXy] = useState("");
  const [codePostal, setCodePostal] = useState("");
  const [comment1, setComment1] = useState("");
  const [comment2, setComment2] = useState("");
  const [source, setSource] = useState(SOURCES[0]);
  const [typeId, setTypeId] = useState("");
  const [oldOperator, setOldOperator] = useState("");
  const [newOperator, setNewOperator] = useState("");
  const [portingNumber, setPortingNumber] = useState("");
  const [migrationType, setMigrationType] = useState(MIGRATION_TYPES[0]);
  const [requestedDate, setRequestedDate] = useState("");
  const [completedDate, setCompletedDate] = useState("");
  const [technicalStatus, setTechnicalStatus] = useState("");
  const [externalRef, setExternalRef] = useState("");
  const [workflowStatus, setWorkflowStatus] = useState("");
  const [notes, setNotes] = useState("");
  const [assignedTo, setAssignedTo] = useState("__none__");

  useEffect(() => {
    if (!API_ENABLED) { setLoading(false); return; }
    setLoading(true);
    fetchMigrationById(migrationId)
      .then((row) => setMigration(row))
      .catch(() => setMigration(null))
      .finally(() => setLoading(false));
  }, [migrationId]);

  useEffect(() => {
    if (!migration || hydrated) return;
    setCivility((migration.civility as "M" | "Mme") || "M");
    setLastName(migration.lastName ?? "");
    setFirstName(migration.firstName ?? "");
    setPhone(migration.phone ?? "");
    setPhone2(migration.phone2 ?? "");
    setAnimateur(migration.animateur ?? "");
    setAncienLigne(migration.ancienLigne ?? "");
    setCin(migration.cin ?? "");
    setBirthDate(migration.birthDate ?? "");
    setEmail(migration.email ?? "");
    setGouvernorat(migration.gouvernorat ?? "");
    setDelegation(migration.delegation ?? "");
    setCity(migration.city ?? "");
    setZone(migration.zone ?? "");
    setAddress(migration.address ?? "");
    setLocalisationXy(migration.localisationXy ?? "");
    setCodePostal(migration.codePostal ?? "");
    setComment1(migration.comment1 ?? "");
    setComment2(migration.comment2 ?? "");
    setSource(migration.source || SOURCES[0]);
    setTypeId(migration.typeId ?? "");
    setOldOperator(migration.oldOperator ?? "");
    setNewOperator(migration.newOperator ?? "");
    setPortingNumber(migration.portingNumber ?? "");
    setMigrationType(migration.migrationType || MIGRATION_TYPES[0]);
    setRequestedDate(migration.requestedDate ?? "");
    setCompletedDate(migration.completedDate ?? "");
    setTechnicalStatus(migration.technicalStatus ?? "");
    setExternalRef(migration.externalRef ?? "");
    setWorkflowStatus(migration.workflowStatus ?? "");
    setNotes(migration.notes ?? "");
    setAssignedTo(migration.assignedTo && migration.assignedTo !== "—" ? migration.assignedTo : "__none__");
    setHydrated(true);
  }, [migration, hydrated]);

  useEffect(() => { ensureDefaultProspectTypes().then(setTypes).catch(() => {}); }, []);

  const agents = useMemo(
    () => users.filter((u) => ["Agent", "Manager", "AgentSuivi", "AgentActivation", "AgentVente", "Administrateur"].includes(u.role)),
    [users],
  );

  if (loading) {
    return <AppLayout skeleton="form"><div className="p-10 text-center text-muted-foreground">Chargement…</div></AppLayout>;
  }

  if (!migration) {
    return (
      <AppLayout>
        <PageHeader title="Migration introuvable" icon={<Pencil className="h-5 w-5" />} />
        <div className="mt-6">
          <Button asChild variant="outline"><Link to="/migrations"><ArrowLeft className="h-4 w-4 mr-1.5" />Retour</Link></Button>
        </div>
      </AppLayout>
    );
  }

  const submit = async () => {
    if (!lastName.trim()) { toast.error("Nom obligatoire"); return; }
    if (localisationXy && !isValidLocalisationXy(localisationXy)) {
      toast.error("Localisation XY invalide", { description: "Format attendu : lat,lng" });
      return;
    }
    setSaving(true);
    try {
      await updateMigration(migration.id, {
        civility,
        lastName: lastName.trim(),
        firstName: firstName.trim(),
        phone: phone.trim(),
        phone2: phone2.trim(),
        animateur: animateur.trim() || null,
        ancienLigne: ancienLigne.trim() || null,
        cin: cin.trim(),
        birthDate: birthDate || null,
        email: email.trim(),
        gouvernorat: gouvernorat.trim().toUpperCase(),
        delegation: delegation.trim(),
        city: city.trim(),
        zone: zone.trim() || null,
        address: address.trim(),
        localisationXy: normalizeLocalisationXy(localisationXy) || null,
        codePostal: normalizeCodePostal(codePostal) || null,
        comment1: comment1.trim() || null,
        comment2: comment2.trim() || null,
        source,
        typeId: typeId || null,
        oldOperator: oldOperator.trim(),
        newOperator: newOperator.trim(),
        portingNumber: portingNumber.trim(),
        migrationType,
        requestedDate: requestedDate || null,
        completedDate: completedDate || null,
        technicalStatus: technicalStatus.trim(),
        externalRef: externalRef.trim(),
        workflowStatus: workflowStatus || migration.workflowStatus,
        notes: notes.trim() || null,
        assignedTo: assignedTo === "__none__" ? "" : assignedTo,
      });
      await sync(["migrations"]);
      toast.success("Migration mise à jour");
      navigate({ to: "/migrations/$migrationId", params: { migrationId: migration.id } });
    } catch (e: unknown) {
      toast.error(e instanceof Error ? e.message : "Échec de la mise à jour");
    } finally {
      setSaving(false);
    }
  };

  return (
    <AppLayout skeleton="form">
      <PageHeader
        title={`Modifier ${migration.firstName} ${migration.lastName}`}
        description="Identité, contact, opérateurs et workflow du dossier migration."
        icon={<Pencil className="h-5 w-5" />}
        actions={
          <Button variant="outline" size="sm" asChild>
            <Link to="/migrations/$migrationId" params={{ migrationId: migration.id }}>
              <ArrowLeft className="h-4 w-4 mr-1.5" />Retour à la fiche
            </Link>
          </Button>
        }
      />

      <div className="mt-6">
        <Card className="p-6 shadow-elegant space-y-6">
          <section>
            <h2 className="text-sm font-semibold mb-3 text-foreground">Identité</h2>
            <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
              {types.length > 0 && (
                <div className="space-y-1.5 sm:col-span-2">
                  <Label>Type</Label>
                  <Select value={typeId || "__none__"} onValueChange={(v) => setTypeId(v === "__none__" ? "" : v)}>
                    <SelectTrigger><SelectValue /></SelectTrigger>
                    <SelectContent>
                      <SelectItem value="__none__">— Aucun —</SelectItem>
                      {types.map((t) => <SelectItem key={t.id} value={t.id}>{t.name}</SelectItem>)}
                    </SelectContent>
                  </Select>
                </div>
              )}
              <div className="space-y-1.5">
                <Label>Civilité</Label>
                <Select value={civility} onValueChange={(v) => setCivility(v as "M" | "Mme")}>
                  <SelectTrigger><SelectValue /></SelectTrigger>
                  <SelectContent>
                    <SelectItem value="M">M</SelectItem>
                    <SelectItem value="Mme">Mme</SelectItem>
                  </SelectContent>
                </Select>
              </div>
              <div className="space-y-1.5">
                <Label>Date de naissance</Label>
                <DatePicker value={birthDate} onChange={setBirthDate} max={new Date().toISOString().slice(0, 10)} />
              </div>
              <div className="space-y-1.5"><Label>Nom *</Label><Input value={lastName} onChange={(e) => setLastName(e.target.value)} /></div>
              <div className="space-y-1.5"><Label>Prénom</Label><Input value={firstName} onChange={(e) => setFirstName(e.target.value)} /></div>
              <div className="space-y-1.5"><Label>CIN</Label><Input value={cin} onChange={(e) => setCin(e.target.value)} /></div>
              <div className="space-y-1.5"><Label>Email</Label><Input type="email" value={email} onChange={(e) => setEmail(e.target.value)} /></div>
            </div>
          </section>

          <section className="border-t border-border pt-6">
            <h2 className="text-sm font-semibold mb-3 text-foreground">Contact & adresse</h2>
            <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
              <div className="space-y-1.5"><Label>Gsm 1</Label><Input value={phone} onChange={(e) => setPhone(e.target.value)} /></div>
              <div className="space-y-1.5"><Label>Gsm 2</Label><Input value={phone2} onChange={(e) => setPhone2(e.target.value)} /></div>
              <div className="space-y-1.5"><Label>Animateur</Label><Input value={animateur} onChange={(e) => setAnimateur(e.target.value)} /></div>
              <div className="space-y-1.5"><Label>Ancienne ligne</Label><Input value={ancienLigne} onChange={(e) => setAncienLigne(e.target.value)} /></div>
              <div className="space-y-1.5"><Label>Gouvernorat</Label><Input value={gouvernorat} onChange={(e) => setGouvernorat(e.target.value)} /></div>
              <div className="space-y-1.5"><Label>Délégation</Label><Input value={delegation} onChange={(e) => setDelegation(e.target.value)} /></div>
              <div className="space-y-1.5"><Label>Ville</Label><Input value={city} onChange={(e) => setCity(e.target.value)} /></div>
              <div className="space-y-1.5"><Label>Zone</Label><Input value={zone} onChange={(e) => setZone(e.target.value)} /></div>
              <div className="space-y-1.5"><Label>Code postal</Label>
                <Input value={codePostal} onChange={(e) => setCodePostal(e.target.value)} onBlur={(e) => setCodePostal(normalizeCodePostal(e.target.value))} />
              </div>
              <div className="space-y-1.5 sm:col-span-2"><Label>Adresse</Label><Textarea rows={2} value={address} onChange={(e) => setAddress(e.target.value)} /></div>
              <div className="space-y-1.5 sm:col-span-2">
                <Label>Localisation XY</Label>
                <Input value={localisationXy} onChange={(e) => setLocalisationXy(e.target.value)}
                  onBlur={(e) => setLocalisationXy(normalizeLocalisationXy(e.target.value))} placeholder="36.123456,10.123698" />
              </div>
            </div>
          </section>

          <section className="border-t border-border pt-6">
            <h2 className="text-sm font-semibold mb-3 text-foreground">Migration opérateur</h2>
            <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
              <div className="space-y-1.5"><Label>Ancien opérateur</Label><Input value={oldOperator} onChange={(e) => setOldOperator(e.target.value)} /></div>
              <div className="space-y-1.5"><Label>Nouvel opérateur</Label><Input value={newOperator} onChange={(e) => setNewOperator(e.target.value)} /></div>
              <div className="space-y-1.5"><Label>N° portabilité</Label><Input value={portingNumber} onChange={(e) => setPortingNumber(e.target.value)} /></div>
              <div className="space-y-1.5">
                <Label>Type migration</Label>
                <Select value={migrationType} onValueChange={setMigrationType}>
                  <SelectTrigger><SelectValue /></SelectTrigger>
                  <SelectContent>
                    {MIGRATION_TYPES.map((t) => <SelectItem key={t} value={t}>{t}</SelectItem>)}
                  </SelectContent>
                </Select>
              </div>
              <div className="space-y-1.5"><Label>Date demande</Label><DatePicker value={requestedDate} onChange={setRequestedDate} /></div>
              <div className="space-y-1.5"><Label>Date fin</Label><DatePicker value={completedDate} onChange={setCompletedDate} /></div>
              <div className="space-y-1.5"><Label>Statut technique</Label><Input value={technicalStatus} onChange={(e) => setTechnicalStatus(e.target.value)} /></div>
              <div className="space-y-1.5"><Label>Réf. externe</Label><Input value={externalRef} onChange={(e) => setExternalRef(e.target.value)} /></div>
              <div className="space-y-1.5">
                <Label>Statut workflow</Label>
                <Select value={workflowStatus || "__keep__"} onValueChange={(v) => v !== "__keep__" && setWorkflowStatus(v)}>
                  <SelectTrigger><SelectValue /></SelectTrigger>
                  <SelectContent>
                    {stages.length > 0
                      ? stages.map((s) => <SelectItem key={s.id} value={s.name}>{s.name}</SelectItem>)
                      : <SelectItem value={workflowStatus || "Créer"}>{workflowStatus || "Créer"}</SelectItem>}
                  </SelectContent>
                </Select>
              </div>
              <div className="space-y-1.5">
                <Label>Source</Label>
                <Select value={source} onValueChange={setSource}>
                  <SelectTrigger><SelectValue /></SelectTrigger>
                  <SelectContent>
                    {SOURCES.map((s) => <SelectItem key={s} value={s}>{s}</SelectItem>)}
                  </SelectContent>
                </Select>
              </div>
              <div className="space-y-1.5 sm:col-span-2">
                <Label>Assigné à</Label>
                <Select value={assignedTo} onValueChange={setAssignedTo}>
                  <SelectTrigger><SelectValue /></SelectTrigger>
                  <SelectContent>
                    <SelectItem value="__none__">— Non attribué —</SelectItem>
                    {agents.map((u) => <SelectItem key={u.username} value={u.username}>{u.fullName} (@{u.username})</SelectItem>)}
                  </SelectContent>
                </Select>
              </div>
              <div className="space-y-1.5 sm:col-span-2"><Label>Notes</Label><Textarea rows={2} value={notes} onChange={(e) => setNotes(e.target.value)} /></div>
              <div className="space-y-1.5 sm:col-span-2"><Label>Observation 1</Label><Textarea rows={2} value={comment1} onChange={(e) => setComment1(e.target.value)} /></div>
              <div className="space-y-1.5 sm:col-span-2"><Label>Observation 2</Label><Textarea rows={2} value={comment2} onChange={(e) => setComment2(e.target.value)} /></div>
            </div>
          </section>

          <div className="flex justify-end gap-2 pt-2 border-t">
            <Button variant="outline" asChild disabled={saving}>
              <Link to="/migrations/$migrationId" params={{ migrationId: migration.id }}>Annuler</Link>
            </Button>
            <Button onClick={submit} disabled={saving}>
              {saving ? "Enregistrement…" : "Enregistrer les modifications"}
            </Button>
          </div>
        </Card>
      </div>
    </AppLayout>
  );
}
