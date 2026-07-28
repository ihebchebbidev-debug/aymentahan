import { useEffect, useState, useMemo } from "react";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Save } from "lucide-react";
import { toast } from "sonner";
import { useErp } from "@/lib/erpStore";
import { listEntities } from "@/lib/guichetApi";
import { listBackofficeObjectives, upsertBackofficeObjective, type BackofficeObjective } from "@/lib/backofficeApi";

export function BackofficeObjectivesPanel() {
  const { users } = useErp();
  const [month, setMonth] = useState(new Date().toISOString().slice(0,7));
  const [entities, setEntities] = useState([] as { id: string; name: string }[]);

  useEffect(() => { listEntities(true).then(setEntities).catch(() => {}); }, []);

  return (
    <div className="space-y-4">
      <ObjectiveEditor month={month} entities={entities} />
    </div>
  );
}

function ObjectiveEditor({ month, entities }: { month: string; entities: { id: string; name: string }[] }) {
  const { users } = useErp();
  const [scope, setScope] = useState<"agent"|"entity"|"global">("global");
  const [agentId, setAgentId] = useState("");
  const [entityId, setEntityId] = useState("");
  const [contracts, setContracts] = useState(0);
  const [migrations, setMigrations] = useState(0);
  const [workingDays, setWorkingDays] = useState(26);

  const agents = useMemo(() => users.filter((u) => u.active !== false), [users]);

  const load = async () => {
    const q: Record<string,string|undefined> = { scope, month };
    if (scope === 'agent') q.agentId = agentId || undefined;
    if (scope === 'entity') q.entityId = entityId || undefined;
    if ((scope === 'agent' && !agentId) || (scope === 'entity' && !entityId)) {
      setContracts(0); setMigrations(0); setWorkingDays(26); return;
    }
    try {
      const list = await listBackofficeObjectives(q);
      const o = list[0];
      if (!o) { setContracts(0); setMigrations(0); setWorkingDays(26); return; }
      setContracts(o.targetContracts);
      setMigrations(o.targetMigrations);
      setWorkingDays(o.workingDays);
    } catch {
      setContracts(0); setMigrations(0); setWorkingDays(26);
    }
  };

  useEffect(() => { void load(); }, [scope, agentId, entityId, month]);

  const save = async () => {
    if (scope === 'agent' && !agentId) { toast.error('Sélectionner un agent'); return; }
    if (scope === 'entity' && !entityId) { toast.error('Sélectionner une entité'); return; }
    try {
      await upsertBackofficeObjective({ scope, agentId: scope==='agent' ? agentId : undefined, entityId: scope==='entity' ? entityId : undefined, periodMonth: month, targetContracts: contracts, targetMigrations: migrations, workingDays });
      toast.success('Objectif backoffice enregistré');
    } catch (e: any) { toast.error(e?.message ?? 'Erreur'); }
  };

  return (
    <Card>
      <CardHeader><CardTitle className="text-sm">Backoffice · Objectif ({month})</CardTitle></CardHeader>
      <CardContent className="grid grid-cols-1 sm:grid-cols-6 gap-2 items-end">
        <div className="space-y-1.5"><Label>Portée</Label>
          <Select value={scope} onValueChange={(v) => setScope(v as any)}><SelectTrigger><SelectValue /></SelectTrigger>
            <SelectContent><SelectItem value="global">Global</SelectItem><SelectItem value="entity">Par entité</SelectItem><SelectItem value="agent">Par agent</SelectItem></SelectContent>
          </Select>
        </div>
        {scope === 'entity' && <div className="space-y-1.5"><Label>Entité</Label>
          <Select value={entityId} onValueChange={setEntityId}><SelectTrigger><SelectValue placeholder="Choisir" /></SelectTrigger>
            <SelectContent>{entities.map((e) => <SelectItem key={e.id} value={e.id}>{e.name}</SelectItem>)}</SelectContent>
          </Select></div>}
        {scope === 'agent' && <div className="space-y-1.5"><Label>Agent</Label>
          <Select value={agentId} onValueChange={setAgentId}><SelectTrigger><SelectValue placeholder="Choisir un agent" /></SelectTrigger>
            <SelectContent>{agents.map((a) => <SelectItem key={a.id} value={a.id}>{a.fullName ?? a.username}</SelectItem>)}</SelectContent>
          </Select></div>}
        <div className="space-y-1.5"><Label>Contrats (mois)</Label><Input type="number" value={contracts} onChange={(e) => setContracts(+e.target.value)} /></div>
        <div className="space-y-1.5"><Label>Migrations (mois)</Label><Input type="number" value={migrations} onChange={(e) => setMigrations(+e.target.value)} /></div>
        <div className="space-y-1.5"><Label>Jours ouvrés</Label><Input type="number" value={workingDays} onChange={(e) => setWorkingDays(+e.target.value)} /></div>
        <Button className="sm:col-span-6 w-fit" onClick={save}><Save className="h-4 w-4 mr-1" /> Enregistrer</Button>
      </CardContent>
    </Card>
  );
}

export default BackofficeObjectivesPanel;
