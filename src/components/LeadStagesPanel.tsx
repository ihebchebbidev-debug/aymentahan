import { useState } from "react";
import { Plus, Pencil, Trash2, ArrowUp, ArrowDown, ListChecks, Check, AlertTriangle, RefreshCw } from "lucide-react";
import { Card } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Badge } from "@/components/ui/badge";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Switch } from "@/components/ui/switch";
import { useCan } from "@/components/Can";
import {
  Dialog, DialogContent, DialogDescription, DialogFooter,
  DialogHeader, DialogTitle,
} from "@/components/ui/dialog";
import {
  AlertDialog, AlertDialogAction, AlertDialogCancel, AlertDialogContent,
  AlertDialogDescription, AlertDialogFooter, AlertDialogHeader, AlertDialogTitle,
} from "@/components/ui/alert-dialog";
import { api, API_ENABLED } from "@/lib/api";
import { useLeadStages, refetchLeadStages, STAGE_COLOR_CLASSES, type LeadStageWithUsage } from "@/hooks/use-lead-stages";
import { toast } from "sonner";

const COLOR_OPTIONS = [
  { value: "success", label: "Vert (Gagné / Succès)", bg: "bg-success/15 text-success border-success/20" },
  { value: "warning", label: "Orange / Jaune (En attente)", bg: "bg-warning/15 text-warning-foreground border-warning/20" },
  { value: "info", label: "Bleu (Information / Rappel)", bg: "bg-info/15 text-info border-info/20" },
  { value: "destructive", label: "Rouge (Refusé / Perdu)", bg: "bg-destructive/10 text-destructive border-destructive/20" },
  { value: "primary", label: "Violet (Processus / Migration)", bg: "bg-primary/10 text-primary border-primary/20" },
  { value: "muted", label: "Gris (Neutre)", bg: "bg-muted text-muted-foreground border-border" },
];

const AUTO_ACTION_LABELS: Record<string, string> = {
  none: "Aucune",
  convert_opportunity: "Convertir en Opportunité",
  convert_contract: "Convertir en Contrat",
};

export function LeadStagesPanel() {
  const canManageStages = useCan()("stage.manage");
  const stages = useLeadStages();
  const [loading, setLoading] = useState(false);
  const [modalOpen, setModalOpen] = useState(false);
  const [editingStage, setEditingStage] = useState<LeadStageWithUsage | null>(null);

  // Form State
  const [name, setName] = useState("");
  const [color, setColor] = useState("muted");
  const [position, setPosition] = useState(1);
  const [isInitial, setIsInitial] = useState(false);
  const [isWon, setIsWon] = useState(false);
  const [isLost, setIsLost] = useState(false);
  const [autoAction, setAutoAction] = useState("none");
  const [submitting, setSubmitting] = useState(false);

  // Delete State
  const [deleteStage, setDeleteStage] = useState<LeadStageWithUsage | null>(null);
  const [deleting, setDeleting] = useState(false);

  const openAddModal = () => {
    setEditingStage(null);
    setName("");
    setColor("muted");
    setPosition(stages.length + 1);
    setIsInitial(false);
    setIsWon(false);
    setIsLost(false);
    setAutoAction("none");
    setModalOpen(true);
  };

  const openEditModal = (s: LeadStageWithUsage) => {
    setEditingStage(s);
    setName(s.name);
    setColor(s.color || "muted");
    setPosition(s.position);
    setIsInitial(!!s.isInitial);
    setIsWon(!!s.isWon);
    setIsLost(!!s.isLost);
    setAutoAction(s.autoAction || "none");
    setModalOpen(true);
  };

  const handleSave = async () => {
    const trimmed = name.trim();
    if (!trimmed) {
      toast.error("Le nom du statut d'appel est obligatoire");
      return;
    }

    setSubmitting(true);
    try {
      if (editingStage) {
        // Update existing stage
        await api("/stages.php", {
          method: "PUT",
          body: {
            id: editingStage.id,
            name: trimmed,
            color,
            position,
            isInitial,
            isWon,
            isLost,
            autoAction,
          },
        });
        toast.success("Statut d'appel mis à jour");
      } else {
        // Create new stage
        await api("/stages.php", {
          method: "POST",
          body: {
            name: trimmed,
            color,
            position,
            isInitial,
            isWon,
            isLost,
            autoAction,
          },
        });
        toast.success("Statut d'appel créé avec succès");
      }

      await refetchLeadStages();
      setModalOpen(false);
    } catch (err: any) {
      toast.error(err.message || "Erreur lors de l'enregistrement du statut");
    } finally {
      setSubmitting(false);
    }
  };

  const handleDelete = async (force = false) => {
    if (!deleteStage) return;
    setDeleting(true);
    try {
      await api("/stages.php", {
        method: "DELETE",
        query: { id: deleteStage.id, ...(force ? { force: "1" } : {}) },
      });
      toast.success("Statut d'appel supprimé");
      await refetchLeadStages();
      setDeleteStage(null);
    } catch (err: any) {
      if (err.data?.inUse && !force) {
        toast.error(`Ce statut est utilisé par ${err.data.prospectCount} prospect(s).`);
      } else {
        toast.error(err.message || "Erreur lors de la suppression");
      }
    } finally {
      setDeleting(false);
    }
  };

  const movePosition = async (index: number, direction: "up" | "down") => {
    if (direction === "up" && index === 0) return;
    if (direction === "down" && index === stages.length - 1) return;

    const newStages = [...stages];
    const targetIndex = direction === "up" ? index - 1 : index + 1;
    const temp = newStages[index];
    newStages[index] = newStages[targetIndex];
    newStages[targetIndex] = temp;

    // Re-assign positions
    const items = newStages.map((s, idx) => ({ id: s.id, position: idx + 1 }));

    setLoading(true);
    try {
      await api("/stages.php?action=reorder", {
        method: "PUT",
        body: { items },
      });
      await refetchLeadStages();
      toast.success("Ordre des statuts mis à jour");
    } catch (err: any) {
      toast.error("Erreur lors du réordonnancement");
    } finally {
      setLoading(false);
    }
  };

  return (
    <Card className="p-5 shadow-elegant">
      <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
        <div className="flex items-center gap-3">
          <div className="h-10 w-10 rounded-xl bg-primary/10 text-primary flex items-center justify-center">
            <ListChecks className="h-5 w-5" />
          </div>
          <div>
            <h3 className="font-semibold text-base">Configuration des Statuts d'Appel</h3>
            <p className="text-xs text-muted-foreground">
              Gérez les résultats et qualifications d'appels utilisés dans les prospects, filtres et tableaux de bord.
            </p>
          </div>
        </div>

        <div className="flex items-center gap-2">
          <Button
            variant="outline"
            size="sm"
            onClick={() => void refetchLeadStages()}
            title="Rafraîchir"
            disabled={loading}
          >
            <RefreshCw className={`h-4 w-4 ${loading ? "animate-spin" : ""}`} />
          </Button>
          {canManageStages && (
            <Button size="sm" onClick={openAddModal} className="bg-primary text-primary-foreground">
              <Plus className="h-4 w-4 mr-1.5" /> Ajouter un statut
            </Button>
          )}
        </div>
      </div>

      {stages.length === 0 ? (
        <div className="text-center p-8 border border-dashed rounded-lg text-muted-foreground text-sm">
          Aucun statut d'appel configuré. Cliquez sur "Ajouter un statut" pour commencer.
        </div>
      ) : (
        <div className="overflow-x-auto border rounded-lg">
          <table className="w-full text-left text-sm">
            <thead className="bg-muted/50 border-b text-xs font-semibold text-muted-foreground uppercase tracking-wider">
              <tr>
                <th className="p-3 w-12 text-center">Ordre</th>
                <th className="p-3">Statut (Nom & Badge)</th>
                <th className="p-3">Action automatique</th>
                <th className="p-3 text-center">Prospects associés</th>
                <th className="p-3 text-right">Actions</th>
              </tr>
            </thead>
            <tbody className="divide-y">
              {stages.map((stg, idx) => {
                const colorClass = STAGE_COLOR_CLASSES[stg.color] || STAGE_COLOR_CLASSES.muted;
                return (
                  <tr key={stg.id} className="hover:bg-muted/30 transition-colors">
                    <td className="p-3 text-center font-medium text-muted-foreground">
                      <div className="flex items-center justify-center gap-1">
                        <button
                          disabled={idx === 0 || loading || !canManageStages}
                          onClick={() => movePosition(idx, "up")}
                          className="p-1 hover:bg-muted rounded disabled:opacity-30"
                          title="Monter"
                        >
                          <ArrowUp className="h-3.5 w-3.5" />
                        </button>
                        <span className="w-5 text-center">{stg.position}</span>
                        <button
                          disabled={idx === stages.length - 1 || loading || !canManageStages}
                          onClick={() => movePosition(idx, "down")}
                          className="p-1 hover:bg-muted rounded disabled:opacity-30"
                          title="Descendre"
                        >
                          <ArrowDown className="h-3.5 w-3.5" />
                        </button>
                      </div>
                    </td>

                    <td className="p-3">
                      <div className="flex items-center gap-2">
                        <Badge className={`${colorClass} px-2.5 py-1 text-xs font-medium border`}>
                          {stg.name}
                        </Badge>
                        {stg.isInitial && (
                          <Badge variant="outline" className="text-[10px] text-muted-foreground">
                            Initial
                          </Badge>
                        )}
                        {stg.isWon && (
                          <Badge variant="outline" className="text-[10px] border-success text-success">
                            Gagné
                          </Badge>
                        )}
                        {stg.isLost && (
                          <Badge variant="outline" className="text-[10px] border-destructive text-destructive">
                            Perdu
                          </Badge>
                        )}
                      </div>
                    </td>

                    <td className="p-3 text-xs text-muted-foreground">
                      {AUTO_ACTION_LABELS[stg.autoAction || "none"]}
                    </td>

                    <td className="p-3 text-center font-medium text-xs">
                      {stg.usageCount !== undefined ? (
                        <span className={`px-2 py-0.5 rounded-full ${stg.usageCount > 0 ? "bg-primary/10 text-primary" : "text-muted-foreground bg-muted"}`}>
                          {stg.usageCount} prospect{stg.usageCount > 1 ? "s" : ""}
                        </span>
                      ) : (
                        "—"
                      )}
                    </td>

                    <td className="p-3 text-right">
                      <div className="flex items-center justify-end gap-1">
                        {canManageStages && (
                        <>
                        <Button
                          variant="ghost"
                          size="icon"
                          className="h-8 w-8 text-muted-foreground hover:text-foreground"
                          onClick={() => openEditModal(stg)}
                        >
                          <Pencil className="h-4 w-4" />
                        </Button>
                        <Button
                          variant="ghost"
                          size="icon"
                          className="h-8 w-8 text-destructive hover:bg-destructive/10"
                          onClick={() => setDeleteStage(stg)}
                        >
                          <Trash2 className="h-4 w-4" />
                        </Button>
                        </>
                        )}
                      </div>
                    </td>
                  </tr>
                );
              })}
            </tbody>
          </table>
        </div>
      )}

      {/* Modal Add / Edit */}
      <Dialog open={modalOpen} onOpenChange={setModalOpen}>
        <DialogContent className="sm:max-w-md">
          <DialogHeader>
            <DialogTitle>
              {editingStage ? "Modifier le statut d'appel" : "Nouveau statut d'appel"}
            </DialogTitle>
            <DialogDescription>
              Définissez les propriétés et l'apparence visuelle du statut d'appel.
            </DialogDescription>
          </DialogHeader>

          <div className="space-y-4 py-2">
            <div className="space-y-1.5">
              <Label className="text-xs font-semibold">Nom du statut *</Label>
              <Input
                value={name}
                onChange={(e) => setName(e.target.value)}
                placeholder="Ex: Qualifié, Rappel, Injoignable..."
              />
            </div>

            <div className="space-y-1.5">
              <Label className="text-xs font-semibold">Couleur du badge</Label>
              <Select value={color} onValueChange={setColor}>
                <SelectTrigger>
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  {COLOR_OPTIONS.map((opt) => (
                    <SelectItem key={opt.value} value={opt.value}>
                      <div className="flex items-center gap-2">
                        <span className={`inline-block w-3 h-3 rounded-full border ${opt.bg}`} />
                        <span>{opt.label}</span>
                      </div>
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </div>

            <div className="space-y-1.5">
              <Label className="text-xs font-semibold">Ordre / Position</Label>
              <Input
                type="number"
                min={1}
                value={position}
                onChange={(e) => setPosition(Number(e.target.value))}
              />
            </div>

            <div className="space-y-1.5">
              <Label className="text-xs font-semibold">Action automatique à l'attribution</Label>
              <Select value={autoAction} onValueChange={setAutoAction}>
                <SelectTrigger>
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="none">Aucune</SelectItem>
                  <SelectItem value="convert_opportunity">Convertir automatiquement en Opportunité</SelectItem>
                  <SelectItem value="convert_contract">Convertir automatiquement en Contrat</SelectItem>
                </SelectContent>
              </Select>
            </div>

            <div className="border-t pt-3 space-y-3">
              <div className="flex items-center justify-between">
                <Label className="text-xs font-medium">Marquer comme statut initial (par défaut)</Label>
                <Switch checked={isInitial} onCheckedChange={setIsInitial} />
              </div>
              <div className="flex items-center justify-between">
                <Label className="text-xs font-medium text-success">Marquer comme statut Gagné</Label>
                <Switch checked={isWon} onCheckedChange={setIsWon} />
              </div>
              <div className="flex items-center justify-between">
                <Label className="text-xs font-medium text-destructive">Marquer comme statut Perdu</Label>
                <Switch checked={isLost} onCheckedChange={setIsLost} />
              </div>
            </div>

            {/* Badge Preview */}
            <div className="p-3 bg-muted/40 rounded-lg border flex items-center justify-between">
              <span className="text-xs text-muted-foreground">Aperçu du badge :</span>
              <Badge className={`${STAGE_COLOR_CLASSES[color] || STAGE_COLOR_CLASSES.muted} px-3 py-1 text-xs border font-medium`}>
                {name || "Nom du statut"}
              </Badge>
            </div>
          </div>

          <DialogFooter>
            <Button variant="outline" onClick={() => setModalOpen(false)} disabled={submitting}>
              Annuler
            </Button>
            <Button onClick={handleSave} disabled={submitting} className="bg-primary text-primary-foreground">
              {submitting ? "Enregistrement..." : editingStage ? "Mettre à jour" : "Créer"}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      {/* Delete Alert Dialog */}
      <AlertDialog open={!!deleteStage} onOpenChange={(open) => !open && setDeleteStage(null)}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle className="flex items-center gap-2">
              <AlertTriangle className="h-5 w-5 text-destructive" />
              Supprimer le statut d'appel ?
            </AlertDialogTitle>
            <AlertDialogDescription>
              Êtes-vous sûr de vouloir supprimer le statut <strong>"{deleteStage?.name}"</strong> ?
              {deleteStage?.usageCount ? (
                <div className="mt-2 p-3 bg-destructive/10 text-destructive rounded-lg border border-destructive/20 text-xs">
                  Attention : Ce statut est actuellement attribué à <strong>{deleteStage.usageCount} prospect(s)</strong>.
                  Sa suppression peut affecter le filtrage de ces prospects.
                </div>
              ) : (
                " Cette action est irréversible."
              )}
            </AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel disabled={deleting}>Annuler</AlertDialogCancel>
            <AlertDialogAction
              onClick={(e) => {
                e.preventDefault();
                void handleDelete(true);
              }}
              disabled={deleting}
              className="bg-destructive text-destructive-foreground hover:bg-destructive/90"
            >
              {deleting ? "Suppression..." : "Confirmer la suppression"}
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </Card>
  );
}
