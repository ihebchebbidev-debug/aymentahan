import { createFileRoute } from "@tanstack/react-router";
import { AppLayout } from "@/components/AppLayout";
import { PageHeader } from "@/components/PageHeader";
import { CheckSquare, Plus, Trash2, RefreshCw, Eye } from "lucide-react";
import { Card } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { DatePicker } from "@/components/ui/date-picker";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Badge } from "@/components/ui/badge";
import { Textarea } from "@/components/ui/textarea";
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from "@/components/ui/dialog";
import { useEffect, useMemo, useState } from "react";
import { api, API_ENABLED } from "@/lib/api";
import { useAuth } from "@/lib/auth";
import { Can } from "@/components/Can";
import { useErp } from "@/lib/erpStore";
import { toast } from "sonner";
import { confirmDialog } from "@/components/ConfirmDialogProvider";

export const Route = createFileRoute("/tasks")({
  head: () => ({
    meta: [
      { title: "Tâches & tâches — CRM" },
      { name: "description", content: "Liste des relances et tâches du jour, par échéance et priorité." },
    ],
  }),
  component: TasksPage,
});

type Task = {
  id: string; title: string; description: string | null; assignedTo: string;
  relatedEntity: string | null; relatedId: string | null; dueDate: string | null;
  priority: "low" | "normal" | "high"; status: "todo" | "in_progress" | "done" | "cancelled";
  createdBy: string; createdAt: string; completedAt: string | null;
};

const PRIO_BADGE: Record<string, string> = {
  low: "bg-muted text-muted-foreground",
  normal: "bg-info/15 text-info",
  high: "bg-destructive/15 text-destructive",
};

function TasksPage() {
  const auth = useAuth();
  const canEditTask = auth.hasPermission("task.edit");
  const canCompleteTask = auth.hasPermission("task.complete");
  const canDeleteTask = auth.hasPermission("task.delete");
  const { users } = useErp();
  const [tasks, setTasks] = useState<Task[]>([]);
  const [loading, setLoading] = useState(false);
  const [title, setTitle] = useState("");
  const [desc, setDesc] = useState("");
  const [assignee, setAssignee] = useState(auth.user?.username ?? "");
  const [due, setDue] = useState("");
  const [priority, setPriority] = useState<Task["priority"]>("normal");
  const [selectedTask, setSelectedTask] = useState<Task | null>(null);
  const [descriptionNote, setDescriptionNote] = useState("");
  const [taskSaving, setTaskSaving] = useState(false);
  const [search, setSearch] = useState("");
  const [statusFilter, setStatusFilter] = useState<Task["status"] | "all">("all");
  const [priorityFilter, setPriorityFilter] = useState<Task["priority"] | "all">("all");
  const [sortBy, setSortBy] = useState<"newest" | "due" | "priority">("newest");
  const currentUsername = auth.user?.username ?? "";

  const load = async () => {
    if (!API_ENABLED) return;
    setLoading(true);
    try {
      const r = await api<{ tasks: Task[] }>("/tasks.php");
      setTasks(r.tasks);
    } catch (e: any) { toast.error("Erreur", { description: e?.message }); }
    finally { setLoading(false); }
  };
  useEffect(() => { void load(); }, []);

  const visibleTasks = useMemo(() => {
    return tasks
      .filter((t) => statusFilter === "all" || t.status === statusFilter)
      .filter((t) => priorityFilter === "all" || t.priority === priorityFilter)
      .filter((t) => {
        const term = search.trim().toLowerCase();
        if (!term) return true;
        return [t.title, t.description ?? "", t.assignedTo, t.createdBy, t.relatedEntity, t.relatedId]
          .some((value) => String(value ?? "").toLowerCase().includes(term));
      })
      .sort((a, b) => {
        const aRelevant = a.assignedTo === currentUsername || a.createdBy === currentUsername ? 1 : 0;
        const bRelevant = b.assignedTo === currentUsername || b.createdBy === currentUsername ? 1 : 0;
        if (aRelevant !== bRelevant) return bRelevant - aRelevant;
        if (sortBy === "newest") {
          return new Date(b.createdAt).getTime() - new Date(a.createdAt).getTime();
        }
        if (sortBy === "due") {
          if (!a.dueDate && b.dueDate) return 1;
          if (a.dueDate && !b.dueDate) return -1;
          if (!a.dueDate && !b.dueDate) return 0;
          return new Date(a.dueDate).getTime() - new Date(b.dueDate).getTime();
        }
        if (sortBy === "priority") {
          const order = { high: 3, normal: 2, low: 1 } as Record<Task["priority"], number>;
          return order[b.priority] - order[a.priority];
        }
        return 0;
      });
  }, [tasks, statusFilter, priorityFilter, search, sortBy, currentUsername]);

  const saveTask = async () => {
    if (!selectedTask) return;
    if (!selectedTask.title.trim()) { toast.error("Titre requis"); return; }
    setTaskSaving(true);
    try {
      const payload: Record<string, unknown> = {
        id: selectedTask.id,
        title: selectedTask.title.trim(),
        assignedTo: selectedTask.assignedTo,
        dueDate: selectedTask.dueDate || null,
        priority: selectedTask.priority,
        status: selectedTask.status,
      };
      if (descriptionNote.trim()) {
        payload.description = descriptionNote.trim();
      }
      await api("/tasks.php", { method: "PATCH", body: payload });
      toast.success("Tâche mise à jour");
      setDescriptionNote("");
      setSelectedTask(null);
      await load();
    } catch (e: any) {
      toast.error(e?.message ?? "Échec de la mise à jour");
    } finally {
      setTaskSaving(false);
    }
  };

  const create = async () => {
    if (!title.trim()) { toast.error("Titre requis"); return; }
    try {
      await api("/tasks.php", { method: "POST", body: {
        title: title.trim(), description: desc.trim() || null,
        assignedTo: assignee || auth.user?.username, dueDate: due || null, priority,
      }});
      toast.success("Tâche créée");
      setTitle(""); setDesc(""); setDue("");
      await load();
    } catch (e: any) { toast.error(e?.message); }
  };

  const setStatus = async (id: string, status: Task["status"]) => {
    try { await api("/tasks.php", { method: "PATCH", body: { id, status } }); await load(); }
    catch (e: any) { toast.error(e?.message); }
  };
  const remove = async (id: string) => {
    if (!(await confirmDialog({ title: "Suppression", description: "Supprimer cette tâche ?", tone: "destructive", confirmText: "Supprimer" }))) return;
    try { await api(`/tasks.php?id=${id}`, { method: "DELETE" }); await load(); }
    catch (e: any) { toast.error(e?.message); }
  };

  return (
    <AppLayout skeleton="table">
      <PageHeader
        title="Tâches"
        description="Suivi des tâches: échéances, priorités et assignations."
        icon={<CheckSquare className="h-5 w-5" />}
      />

      <Can perm="task.add">
        <Card className="p-4 mt-6 shadow-elegant">
          <div className="font-semibold text-sm mb-3">Nouvelle tâche</div>
          <div className="grid grid-cols-1 md:grid-cols-2 gap-3">
            <div className="space-y-1">
              <Label>Titre</Label>
              <Input value={title} onChange={(e) => setTitle(e.target.value)} placeholder="ex: Rappeler M. Dupont" />
            </div>
            <div className="space-y-1">
              <Label>Assigné à</Label>
              <Select value={assignee} onValueChange={setAssignee} disabled={!auth.hasPermission("task.edit")}>
                <SelectTrigger><SelectValue placeholder="Choisir" /></SelectTrigger>
                <SelectContent>
                  {users.map((u) => <SelectItem key={u.username} value={u.username}>{u.fullName} ({u.username})</SelectItem>)}
                </SelectContent>
              </Select>
            </div>
            <div className="space-y-1">
              <Label>Échéance</Label>
              <DatePicker value={due} onChange={setDue} placeholder="Choisir une échéance" />
            </div>
            <div className="space-y-1">
              <Label>Priorité</Label>
              <Select value={priority} onValueChange={(v: Task["priority"]) => setPriority(v)}>
                <SelectTrigger><SelectValue /></SelectTrigger>
                <SelectContent>
                  <SelectItem value="low">Basse</SelectItem>
                  <SelectItem value="normal">Normale</SelectItem>
                  <SelectItem value="high">Haute</SelectItem>
                </SelectContent>
              </Select>
            </div>
            <div className="md:col-span-2 space-y-1">
              <Label>Description</Label>
              <Textarea value={desc} onChange={(e) => setDesc(e.target.value)} rows={2} />
            </div>
          </div>
          <div className="flex justify-end mt-3">
            <Button onClick={create} className="bg-primary text-primary-foreground hover:bg-primary/90">
              <Plus className="h-4 w-4 mr-1.5" />Créer
            </Button>
          </div>
        </Card>
      </Can>

      <Card className="mt-6 shadow-elegant">
        <div className="px-4 py-3 border-b flex flex-wrap items-center gap-3 justify-between">
          <div className="font-semibold text-sm">{visibleTasks.length} tâche(s)</div>
          <div className="flex flex-wrap gap-2 items-center">
            <Input value={search} onChange={(e) => setSearch(e.target.value)} placeholder="Rechercher" className="w-[220px]" />
            <Select value={statusFilter} onValueChange={(v) => setStatusFilter(v)}>
              <SelectTrigger className="w-[130px] h-8"><SelectValue /></SelectTrigger>
              <SelectContent>
                <SelectItem value="all">Tous statuts</SelectItem>
                <SelectItem value="todo">À faire</SelectItem>
                <SelectItem value="in_progress">En cours</SelectItem>
                <SelectItem value="done">Terminées</SelectItem>
                <SelectItem value="cancelled">Annulées</SelectItem>
              </SelectContent>
            </Select>
            <Select value={priorityFilter} onValueChange={(v) => setPriorityFilter(v)}>
              <SelectTrigger className="w-[130px] h-8"><SelectValue /></SelectTrigger>
              <SelectContent>
                <SelectItem value="all">Toutes priorités</SelectItem>
                <SelectItem value="high">Haute</SelectItem>
                <SelectItem value="normal">Normale</SelectItem>
                <SelectItem value="low">Basse</SelectItem>
              </SelectContent>
            </Select>
            <Select value={sortBy} onValueChange={(v) => setSortBy(v as "newest" | "due" | "priority") }>
              <SelectTrigger className="w-[140px] h-8"><SelectValue /></SelectTrigger>
              <SelectContent>
                <SelectItem value="newest">Nouveaux d'abord</SelectItem>
                <SelectItem value="due">Échéance</SelectItem>
                <SelectItem value="priority">Priorité</SelectItem>
              </SelectContent>
            </Select>
            <Button variant="ghost" size="sm" onClick={() => { setSearch(""); setStatusFilter("all"); setPriorityFilter("all"); setSortBy("newest"); }}>
              Réinitialiser
            </Button>
            <Button variant="ghost" size="sm" onClick={load}><RefreshCw className={`h-4 w-4 ${loading ? "animate-spin" : ""}`} /></Button>
          </div>
        </div>
        <div className="divide-y divide-border">
          {visibleTasks.map((t) => (
            <div key={t.id} className="px-4 py-3 flex items-center gap-3 hover:bg-muted/20">
              <div className="flex-1 min-w-0">
                <div className="flex items-center gap-2 flex-wrap">
                  <div className={`font-medium text-sm ${t.status === "done" ? "line-through text-muted-foreground" : ""}`}>{t.title}</div>
                  <Badge className={PRIO_BADGE[t.priority]} variant="secondary">{t.priority}</Badge>
                  {t.dueDate && <span className="text-xs text-muted-foreground">échéance: {t.dueDate}</span>}
                </div>
                <div className="text-xs text-muted-foreground truncate">
                  {t.assignedTo} · créé par {t.createdBy} {t.description ? `· ${t.description}` : ""}
                </div>
              </div>
              <Button variant="ghost" size="icon" onClick={() => setSelectedTask(t)} className="text-primary hover:bg-primary/10">
                <Eye className="h-4 w-4" />
              </Button>
              {canEditTask ? (
                <Select value={t.status} onValueChange={(v: Task["status"]) => setStatus(t.id, v)}>
                  <SelectTrigger className="w-[140px] h-8"><SelectValue /></SelectTrigger>
                  <SelectContent>
                    <SelectItem value="todo">À faire</SelectItem>
                    <SelectItem value="in_progress">En cours</SelectItem>
                    <SelectItem value="done">Terminée</SelectItem>
                    <SelectItem value="cancelled">Annulée</SelectItem>
                  </SelectContent>
                </Select>
              ) : canCompleteTask ? (
                <Select value={t.status} onValueChange={(v: Task["status"]) => setStatus(t.id, v)}>
                  <SelectTrigger className="w-[140px] h-8"><SelectValue /></SelectTrigger>
                  <SelectContent>
                    <SelectItem value="done">Terminée</SelectItem>
                    <SelectItem value="cancelled">Annulée</SelectItem>
                  </SelectContent>
                </Select>
              ) : (
                <Badge variant="outline" className="capitalize">{t.status}</Badge>
              )}
              {canDeleteTask && (
                <Button variant="ghost" size="icon" onClick={() => remove(t.id)} className="text-destructive hover:bg-destructive/10">
                  <Trash2 className="h-4 w-4" />
                </Button>
              )}
            </div>
          ))}
          {visibleTasks.length === 0 && (
            <div className="px-4 py-10 text-center text-sm text-muted-foreground">Aucune tâche pour le moment</div>
          )}
        </div>
      </Card>

      <Dialog open={!!selectedTask} onOpenChange={(o) => { if (!o) { setSelectedTask(null); setDescriptionNote(""); } }}>
        <DialogContent className="max-w-2xl">
          <DialogHeader>
            <DialogTitle>Tâche {selectedTask?.id}</DialogTitle>
            <DialogDescription>Voir tous les détails de la tâche et ajouter un historique de description.</DialogDescription>
          </DialogHeader>
          <div className="space-y-4 py-3">
            {selectedTask ? (
              <>
                <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                  <div className="space-y-1"><Label>Titre</Label><Input value={selectedTask.title} onChange={(e) => setSelectedTask({ ...selectedTask, title: e.target.value })} /></div>
                  <div className="space-y-1"><Label>Assigné à</Label><Input value={selectedTask.assignedTo} onChange={(e) => setSelectedTask({ ...selectedTask, assignedTo: e.target.value })} /></div>
                  <div className="space-y-1"><Label>Status</Label><Select value={selectedTask.status} onValueChange={(v) => setSelectedTask({ ...selectedTask, status: v as Task["status"] })}><SelectTrigger><SelectValue /></SelectTrigger><SelectContent><SelectItem value="todo">À faire</SelectItem><SelectItem value="in_progress">En cours</SelectItem><SelectItem value="done">Terminée</SelectItem><SelectItem value="cancelled">Annulée</SelectItem></SelectContent></Select></div>
                  <div className="space-y-1"><Label>Priorité</Label><Select value={selectedTask.priority} onValueChange={(v) => setSelectedTask({ ...selectedTask, priority: v as Task["priority"] })}><SelectTrigger><SelectValue /></SelectTrigger><SelectContent><SelectItem value="high">Haute</SelectItem><SelectItem value="normal">Normale</SelectItem><SelectItem value="low">Basse</SelectItem></SelectContent></Select></div>
                </div>
                <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                  <div className="space-y-1"><Label>Échéance</Label><DatePicker value={selectedTask.dueDate ?? ""} onChange={(value) => setSelectedTask({ ...selectedTask, dueDate: value || null })} /></div>
                  <div className="space-y-1"><Label>Créée par</Label><Input value={selectedTask.createdBy} disabled /></div>
                </div>
                <div className="space-y-1">
                  <Label>Description actuelle</Label>
                  <div className="rounded-md border border-border bg-background p-3 whitespace-pre-wrap text-sm text-foreground min-h-[120px]">{selectedTask.description || "Aucune description"}</div>
                </div>
                <div className="space-y-1">
                  <Label>Ajouter une note à l'historique</Label>
                  <Textarea rows={3} value={descriptionNote} onChange={(e) => setDescriptionNote(e.target.value)} placeholder="Ajouter une note sans écraser l'historique" />
                </div>
              </>
            ) : null}
          </div>
          <DialogFooter className="flex justify-end gap-2">
            <Button variant="outline" onClick={() => { setSelectedTask(null); setDescriptionNote(""); }}>Fermer</Button>
            <Button onClick={saveTask} disabled={!selectedTask || taskSaving}>
              {taskSaving ? "Enregistrement…" : "Enregistrer"}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </AppLayout>
  );
}
