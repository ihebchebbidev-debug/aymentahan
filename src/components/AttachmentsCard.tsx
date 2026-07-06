import { useCallback, useEffect, useMemo, useRef, useState } from "react";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { Badge } from "@/components/ui/badge";
import { Input } from "@/components/ui/input";
import { DatePicker } from "@/components/ui/date-picker";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Paperclip, Upload, Download, Trash2, FileText, Image as ImageIcon, FileArchive, File as FileIcon, Loader2, Search, X, Eye } from "lucide-react";
import { api, apiUpload, authenticatedApiUrl, API_ENABLED } from "@/lib/api";
import { useAuth } from "@/lib/auth";
import { entityEditPerm } from "@/lib/entityPerms";
import { toast } from "sonner";
import { compressImageToBudget, isCompressibleImage, MAX_ATTACHMENT_BYTES } from "@/lib/compressImage";
import {
  AttachmentPreviewDialog,
  isAttachmentPreviewable,
  previewItemFromFile,
  revokePreviewItem,
  type AttachmentPreviewItem,
} from "@/components/AttachmentPreviewDialog";
import {
  CategorizedAttachmentSlots,
  ATTACHMENT_CATEGORIES,
  type AttachmentCategoryKey,
  type CategorizedSlotState,
  type CategoryLinkedAttachment,
  withCategoryPrefix,
  categoryLabelOf,
  detectCategoryFromFilename,
} from "./CategorizedAttachmentSlots";

type TypeFilter = "all" | "image" | "pdf" | "doc" | "sheet" | "archive" | "other";
type SizeFilter = "all" | "small" | "medium" | "large";

function categoryOf(mime: string): TypeFilter {
  const m = (mime || "").toLowerCase();
  if (m.startsWith("image/")) return "image";
  if (m === "application/pdf") return "pdf";
  if (m.includes("word") || m.includes("msword") || m.includes("officedocument.wordprocessing") || m === "text/plain") return "doc";
  if (m.includes("sheet") || m.includes("excel") || m === "text/csv") return "sheet";
  if (m.includes("zip") || m.includes("compressed") || m.includes("rar") || m.includes("tar")) return "archive";
  return "other";
}

export type Attachment = {
  id: string;
  entity: "prospect" | "opportunity" | "contract" | "migration";
  entityId: string;
  filename: string;
  mimeType: string;
  sizeBytes: number;
  url: string;
  uploadedBy: string;
  createdAt: string;
  _readOnly?: boolean;
  _originLabel?: string;
};

function fmtSize(b: number) {
  if (b < 1024) return `${b} o`;
  if (b < 1024 * 1024) return `${(b / 1024).toFixed(1)} Ko`;
  return `${(b / 1024 / 1024).toFixed(2)} Mo`;
}

function FileTypeIcon({ mime }: { mime: string }) {
  if (mime?.startsWith("image/")) return <ImageIcon className="h-4 w-4" />;
  if (mime === "application/pdf") return <FileText className="h-4 w-4" />;
  if (mime?.includes("zip") || mime?.includes("compressed")) return <FileArchive className="h-4 w-4" />;
  return <FileIcon className="h-4 w-4" />;
}

export type AttachmentSource = {
  entity: "prospect" | "opportunity" | "contract" | "migration" | "reclamation";
  entityId: string;
  label?: string; // optional badge label (e.g. "Prospect", "Opportunité")
};

export function AttachmentsCard({
  entity,
  entityId,
  extraSources,
  onAdded,
  onRemoved,
}: {
  entity: "prospect" | "opportunity" | "contract" | "migration" | "reclamation";
  entityId: string;
  /** Additional read-only sources whose attachments are merged into the list (e.g. parent prospect on a contract page). */
  extraSources?: AttachmentSource[];
  onAdded?: (a: { filename: string; sizeBytes: number }) => void;
  onRemoved?: (a: { filename: string; sizeBytes: number }) => void;
}) {
  const { user, hasPermission } = useAuth();
  const isAdmin = user?.role === "Administrateur";
  const canEdit = hasPermission(entityEditPerm(entity));
  const [items, setItems] = useState<Attachment[]>([]);
  const [loading, setLoading] = useState(false);
  const [uploading, setUploading] = useState(false);
  const [deletingId, setDeletingId] = useState<string | null>(null);
  const [replacing, setReplacing] = useState(false);
  const [preview, setPreview] = useState<AttachmentPreviewItem | null>(null);
  const [previewCategory, setPreviewCategory] = useState<AttachmentCategoryKey | null>(null);
  const inputRef = useRef<HTMLInputElement | null>(null);
  const [dragOver, setDragOver] = useState(false);


  // Filters
  const [search, setSearch] = useState("");
  const [typeFilter, setTypeFilter] = useState<TypeFilter>("all");
  const [sizeFilter, setSizeFilter] = useState<SizeFilter>("all");
  const [dateFrom, setDateFrom] = useState("");
  const [dateTo, setDateTo] = useState("");

  // Categorized slots (CIN Recto, CIN Verso, Contrat TT, Contrat TOPNET, CGV)
  const [slots, setSlots] = useState<Record<string, CategorizedSlotState>>({});

  const hasTarget = Boolean(entity && entityId && String(entityId).trim() !== "" && String(entityId) !== "0" && String(entityId) !== "undefined" && String(entityId) !== "null");

  const canModifyAttachment = useCallback((a: Attachment) => {
    return canEdit && !a._readOnly && a.entity === entity && String(a.entityId) === String(entityId);
  }, [canEdit, entity, entityId]);

  const attachmentToPreview = useCallback((a: Attachment): AttachmentPreviewItem => ({
    id: a.id,
    filename: a.filename,
    mimeType: a.mimeType,
    sizeBytes: a.sizeBytes,
    previewUrl: authenticatedApiUrl(a.url, isAttachmentPreviewable(a.mimeType) ? { inline: 1 } : undefined),
    downloadUrl: authenticatedApiUrl(a.url),
    readOnly: !canModifyAttachment(a),
  }), [canModifyAttachment]);

  const attachmentByCategory = useMemo(() => {
    const map: Partial<Record<AttachmentCategoryKey, Attachment>> = {};
    for (const a of items) {
      const key = detectCategoryFromFilename(a.filename);
      if (key && !map[key]) map[key] = a;
    }
    return map;
  }, [items]);

  const linkedAttachmentsForSlots = useMemo(() => {
    const out: Partial<Record<AttachmentCategoryKey, CategoryLinkedAttachment>> = {};
    for (const [key, a] of Object.entries(attachmentByCategory) as [AttachmentCategoryKey, Attachment][]) {
      out[key] = {
        id: a.id,
        filename: a.filename,
        mimeType: a.mimeType,
        previewUrl: authenticatedApiUrl(a.url, { inline: 1 }),
      };
    }
    return out;
  }, [attachmentByCategory]);

  const load = useCallback(async () => {
    if (!API_ENABLED) return;
    if (!hasTarget) return; // avoid "entity & entity_id requis" before parent has loaded the row
    setLoading(true);
    try {
      const sources: Array<{ entity: string; entityId: string; label?: string; readOnly: boolean }> = [
        { entity, entityId, label: undefined, readOnly: false },
      ];
      for (const s of extraSources ?? []) {
        if (!s.entityId || String(s.entityId).trim() === "" || String(s.entityId) === "0") continue;
        if (s.entity === entity && String(s.entityId) === String(entityId)) continue;
        sources.push({ entity: s.entity, entityId: String(s.entityId), label: s.label, readOnly: true });
      }
      const results = await Promise.all(
        sources.map(async (s) => {
          try {
            const r = await api<{ attachments: Attachment[] }>("/attachments.php", {
              query: { entity: s.entity, entity_id: s.entityId },
            });
            return (r.attachments ?? []).map((a) => ({ ...a, _originLabel: s.label, _readOnly: s.readOnly } as Attachment & { _originLabel?: string; _readOnly?: boolean }));
          } catch (e: any) {
            const msg = String(e?.message ?? "");
            if (/entity.*requis|entity_id.*requis|introuvable|not\s*found/i.test(msg)) return [];
            if (s.readOnly) return []; // silently skip extras
            throw e;
          }
        })
      );
      // Dedupe by id AND by underlying file (rows cloned during conversion
      // share the same backend storage but have different ids — the native
      // row from the current entity is listed first and wins, hiding the
      // read-only twin from the parent).
      const seenId = new Set<string>();
      const seenFile = new Set<string>();
      const merged: Attachment[] = [];
      for (const arr of results) for (const a of arr) {
        if (seenId.has(a.id)) continue;
        // Use download URL as the file fingerprint proxy (server returns
        // attachments.php?download=<id> — different per row, so we fall back
        // to filename+size+mime which are preserved by the clone).
        const fp = `${a.filename}|${a.sizeBytes}|${a.mimeType}`;
        if (seenFile.has(fp)) continue;
        seenId.add(a.id);
        seenFile.add(fp);
        merged.push(a as Attachment);
      }
      setItems(merged);
      // Reflect categorized files (CIN Recto/Verso, etc.) from this entity or lineage extras.
      setSlots((prev) => {
        const next = { ...prev };
        for (const a of merged) {
          const key = detectCategoryFromFilename(a.filename);
          if (!key || next[key]?.status === "uploading") continue;
          if (!next[key] || next[key]?.status !== "done") {
            next[key] = {
              file: null,
              status: "done",
              message: a.filename.replace(/^\[[^\]]+\]\s*/, "") || a.filename,
            };
          }
        }
        return next;
      });
    } catch (e: any) {
      const msg = String(e?.message ?? "");
      const status = Number(e?.status ?? 0);
      if (status === 401 || /unauthorized|non autoris/i.test(msg)) {
        // Session expired — the api layer redirects to /login. No toast.
        setItems([]);
      } else if (/entity.*requis|entity_id.*requis|introuvable|not\s*found/i.test(msg)) {
        setItems([]);
      } else {
        toast.error("Chargement des pièces jointes impossible", { description: msg });
      }
    } finally {
      setLoading(false);
    }
  }, [entity, entityId, hasTarget, extraSources]);


  useEffect(() => { void load(); }, [load]);

  const handleFiles = async (files: FileList | File[] | null) => {
    if (!files || !API_ENABLED) return;
    if (!hasTarget) { toast.error("Enregistrez d'abord la fiche avant d'ajouter des pièces jointes"); return; }
    const list = Array.from(files);
    if (!list.length) return;
    setUploading(true);
    try {
      for (const f of list) {
        const mime = (f.type || "").toLowerCase();
        const isPdf = mime === "application/pdf";
        const isImg = mime.startsWith("image/");
        if (!isPdf && !isImg) {
          toast.error(`${f.name}: format refusé (PDF ou image uniquement)`);
          continue;
        }
        let toUpload = f;
        if (isImg && f.size > MAX_ATTACHMENT_BYTES && isCompressibleImage(f)) {
          try {
            toUpload = await compressImageToBudget(f);
            if (toUpload.size < f.size) {
              toast.message(`${f.name} compressé`, {
                description: `${(f.size / 1024).toFixed(0)} Ko → ${(toUpload.size / 1024).toFixed(0)} Ko`,
              });
            }
          } catch {/* fall back to original; backend will reject if too big */}
        }
        if (toUpload.size > MAX_ATTACHMENT_BYTES) {
          toast.error(`${f.name}: fichier trop volumineux (max 100 Ko${isImg ? " après compression" : ""})`);
          continue;
        }
        await apiUpload("/attachments.php", {
          entity,
          entity_id: entityId,
          file: toUpload,
        });
        onAdded?.({ filename: toUpload.name, sizeBytes: toUpload.size });
      }
      toast.success(`${list.length} fichier(s) téléversé(s)`);
      await load();
    } catch (e: any) {
      toast.error("Échec de l'envoi", { description: e?.message });
    } finally {
      setUploading(false);
      if (inputRef.current) inputRef.current.value = "";
    }
  };

  const uploadCategorized = async (categoryKey: AttachmentCategoryKey, file: File) => {
    if (!API_ENABLED) { toast.error("API non configurée"); return; }
    if (!hasTarget) { toast.error("Enregistrez d'abord la fiche avant d'ajouter des pièces jointes"); return; }
    const label = categoryLabelOf(categoryKey);
    const mime = (file.type || "").toLowerCase();
    const isPdf = mime === "application/pdf";
    const isImg = mime.startsWith("image/");
    if (!isPdf && !isImg) {
      setSlots((s) => ({ ...s, [categoryKey]: { file, status: "error", message: "Format refusé (PDF ou image)" } }));
      return;
    }
    setSlots((s) => ({ ...s, [categoryKey]: { file, status: "uploading" } }));
    try {
      let toUpload = file;
      if (isImg && file.size > MAX_ATTACHMENT_BYTES && isCompressibleImage(file)) {
        try { toUpload = await compressImageToBudget(file); } catch {/* keep original */}
      }
      if (toUpload.size > MAX_ATTACHMENT_BYTES) {
        setSlots((s) => ({ ...s, [categoryKey]: { file, status: "error", message: "Fichier trop volumineux (max 100 Ko)" } }));
        return;
      }
      const prefixed = withCategoryPrefix(toUpload, label);
      await apiUpload("/attachments.php", { entity, entity_id: entityId, file: prefixed });
      onAdded?.({ filename: prefixed.name, sizeBytes: prefixed.size });
      setSlots((s) => ({ ...s, [categoryKey]: { file, status: "done", message: "Téléversé" } }));
      await load();
    } catch (e: any) {
      setSlots((s) => ({ ...s, [categoryKey]: { file, status: "error", message: e?.message || "Échec" } }));
    }
  };

  const remove = async (a: Attachment, opts?: { closePreview?: boolean }) => {
    setDeletingId(a.id);
    try {
      await api(`/attachments.php?id=${encodeURIComponent(a.id)}`, { method: "DELETE" });
      setItems((prev) => prev.filter((x) => x.id !== a.id));
      const catKey = detectCategoryFromFilename(a.filename);
      if (catKey) {
        setSlots((s) => { const c = { ...s }; delete c[catKey]; return c; });
      }
      onRemoved?.({ filename: a.filename, sizeBytes: a.sizeBytes });
      toast.success("Pièce jointe supprimée", { description: a.filename });
      if (opts?.closePreview) setPreview(null);
    } catch (e: any) {
      toast.error("Suppression impossible", { description: e?.message });
    } finally {
      setDeletingId(null);
    }
  };

  const prepareUploadFile = async (f: File): Promise<File | null> => {
    const mime = (f.type || "").toLowerCase();
    const isPdf = mime === "application/pdf";
    const isImg = mime.startsWith("image/");
    if (!isPdf && !isImg) {
      toast.error(`${f.name}: format refusé (PDF ou image uniquement)`);
      return null;
    }
    let toUpload = f;
    if (isImg && f.size > MAX_ATTACHMENT_BYTES && isCompressibleImage(f)) {
      try {
        toUpload = await compressImageToBudget(f);
      } catch { /* keep original */ }
    }
    if (toUpload.size > MAX_ATTACHMENT_BYTES) {
      toast.error(`${f.name}: fichier trop volumineux (max 100 Ko${isImg ? " après compression" : ""})`);
      return null;
    }
    return toUpload;
  };

  const replaceAttachment = async (previewItem: AttachmentPreviewItem, file: File, categoryKey?: AttachmentCategoryKey | null) => {
    const existing = items.find((x) => x.id === previewItem.id);
    setReplacing(true);
    try {
      const toUpload = await prepareUploadFile(file);
      if (!toUpload) return;

      const cat = categoryKey ?? (existing ? detectCategoryFromFilename(existing.filename) : null);
      const labelled = cat ? withCategoryPrefix(toUpload, categoryLabelOf(cat)) : toUpload;

      if (existing && canModifyAttachment(existing)) {
        await api(`/attachments.php?id=${encodeURIComponent(existing.id)}`, { method: "DELETE" });
      }

      await apiUpload("/attachments.php", { entity, entity_id: entityId, file: labelled });
      onAdded?.({ filename: labelled.name, sizeBytes: labelled.size });
      toast.success("Fichier remplacé");
      await load();
      if (cat) {
        setSlots((s) => ({ ...s, [cat]: { file: null, status: "done", message: labelled.name.replace(/^\[[^\]]+\]\s*/, "") } }));
      }
      setPreview(null);
    } catch (e: any) {
      toast.error("Remplacement impossible", { description: e?.message });
    } finally {
      setReplacing(false);
    }
  };

  const openPreviewForAttachment = (a: Attachment) => {
    setPreviewCategory(detectCategoryFromFilename(a.filename));
    setPreview(attachmentToPreview(a));
  };

  const openPreviewForCategory = (key: AttachmentCategoryKey) => {
    const linked = attachmentByCategory[key];
    if (linked) {
      setPreviewCategory(key);
      setPreview(attachmentToPreview(linked));
      return;
    }
    const slot = slots[key];
    if (slot?.file) {
      setPreviewCategory(key);
      setPreview(previewItemFromFile(slot.file, `slot-${key}`));
    }
  };

  const handlePreviewRemove = async (item: AttachmentPreviewItem) => {
    const existing = items.find((x) => x.id === item.id);
    if (existing && canModifyAttachment(existing)) {
      await remove(existing, { closePreview: true });
      return;
    }
    if (previewCategory && slots[previewCategory]?.file) {
      setSlots((s) => { const c = { ...s }; delete c[previewCategory!]; return c; });
      revokePreviewItem(item);
      setPreview(null);
    }
  };

  const handlePreviewReplace = async (item: AttachmentPreviewItem, file: File) => {
    await replaceAttachment(item, file, previewCategory);
  };

  // Apply filters
  const filtered = useMemo(() => {
    const q = search.trim().toLowerCase();
    const fromTs = dateFrom ? new Date(dateFrom + "T00:00:00").getTime() : null;
    const toTs = dateTo ? new Date(dateTo + "T23:59:59").getTime() : null;
    return items.filter((a) => {
      if (q && !a.filename.toLowerCase().includes(q) && !a.uploadedBy?.toLowerCase().includes(q)) return false;
      if (typeFilter !== "all" && categoryOf(a.mimeType) !== typeFilter) return false;
      if (sizeFilter !== "all") {
        const kb = a.sizeBytes / 1024;
        if (sizeFilter === "small" && kb >= 100) return false;
        if (sizeFilter === "medium" && (kb < 100 || kb > 500)) return false;
        if (sizeFilter === "large" && kb <= 500) return false;
      }
      const ts = new Date(a.createdAt).getTime();
      if (fromTs !== null && ts < fromTs) return false;
      if (toTs !== null && ts > toTs) return false;
      return true;
    });
  }, [items, search, typeFilter, sizeFilter, dateFrom, dateTo]);

  const hasActiveFilter =
    search !== "" || typeFilter !== "all" || sizeFilter !== "all" || dateFrom !== "" || dateTo !== "";

  const resetFilters = () => {
    setSearch(""); setTypeFilter("all"); setSizeFilter("all"); setDateFrom(""); setDateTo("");
  };

  // Display order — same grouping used in the JSX list.
  const orderedFiltered = useMemo(() => {
    const buckets = new Map<string, Attachment[]>();
    ATTACHMENT_CATEGORIES.forEach((c) => buckets.set(c.key, []));
    buckets.set("__other__", []);
    for (const a of filtered) {
      const key = detectCategoryFromFilename(a.filename) ?? "__other__";
      buckets.get(key)!.push(a);
    }
    const out: Attachment[] = [];
    for (const arr of buckets.values()) out.push(...arr);
    return out;
  }, [filtered]);

  const previewItems = useMemo(
    () => orderedFiltered.map(attachmentToPreview),
    [orderedFiltered, attachmentToPreview]
  );


  return (
    <Card className="shadow-elegant">
      <CardHeader className="pb-3">
        <div className="flex items-start justify-between gap-3">
          <div>
            <CardTitle className="text-base flex items-center gap-2">
              <Paperclip className="h-4 w-4" /> Pièces jointes
            </CardTitle>
            <CardDescription>PDF et images — max 100 Ko / fichier (les images sont compressées automatiquement)</CardDescription>
          </div>
          <Badge variant="outline" className="bg-primary/5">
            {hasActiveFilter ? `${filtered.length}/${items.length}` : items.length}
          </Badge>
        </div>
      </CardHeader>
      <CardContent className="space-y-3">
        {/* Categorized slots — CIN Recto, CIN Verso, Contrat TT, TOPNET, CGV */}
        <CategorizedAttachmentSlots
          slots={slots}
          linkedAttachments={linkedAttachmentsForSlots}
          disabled={!canEdit || !API_ENABLED || !hasTarget}
          hint={!hasTarget ? "Enregistrez d'abord la fiche pour activer l'envoi par catégorie." : !canEdit ? "Lecture seule — cliquez sur un document pour l'aperçu." : undefined}
          onPick={(key, file) => void uploadCategorized(key, file)}
          onClear={canEdit ? (key) => setSlots((s) => { const c = { ...s }; delete c[key]; return c; }) : undefined}
          onView={(key) => openPreviewForCategory(key)}
        />

        {/* Dropzone / picker (autres documents libres) */}
        {canEdit ? (
        <div
          onDragOver={(e) => { e.preventDefault(); setDragOver(true); }}
          onDragLeave={() => setDragOver(false)}
          onDrop={(e) => {
            e.preventDefault(); setDragOver(false);
            void handleFiles(e.dataTransfer.files);
          }}
          className={`rounded-lg border-2 border-dashed p-4 text-center transition-base cursor-pointer ${
            dragOver ? "border-primary bg-primary/5" : "border-border hover:bg-muted/30"
          }`}
          onClick={() => inputRef.current?.click()}
        >
          <input
            ref={inputRef}
            type="file"
            multiple
            accept="application/pdf,image/*"
            className="hidden"
            onChange={(e) => void handleFiles(e.target.files)}
            disabled={!API_ENABLED || uploading}
          />
          <div className="flex flex-col items-center gap-1.5 text-sm">
            {uploading ? (
              <Loader2 className="h-5 w-5 animate-spin text-primary" />
            ) : (
              <Upload className="h-5 w-5 text-muted-foreground" />
            )}
            <span className="font-medium">
              {uploading ? "Envoi en cours…" : "Glissez vos fichiers ici ou cliquez"}
            </span>
            <span className="text-xs text-muted-foreground">
              PDF ou images — max 100 Ko (compression auto)
            </span>
          </div>
        </div>
        ) : (
          <div className="rounded-lg border border-dashed p-3 text-center text-xs text-muted-foreground">
            Lecture seule — vous n'avez pas la permission d'ajouter des pièces jointes.
          </div>
        )}

        {!API_ENABLED && (
          <div className="text-xs text-muted-foreground italic text-center">
            API non configurée — les pièces jointes nécessitent le backend.

          </div>
        )}

        {/* Filters */}
        {items.length > 0 && (
          <div className="space-y-2 rounded-lg border border-border bg-muted/20 p-2.5">
            <div className="relative">
              <Search className="absolute left-2.5 top-1/2 -translate-y-1/2 h-3.5 w-3.5 text-muted-foreground" />
              <Input
                value={search}
                onChange={(e) => setSearch(e.target.value)}
                placeholder="Rechercher par nom ou auteur…"
                className="pl-8 h-9"
              />
            </div>
            <div className="grid grid-cols-2 sm:grid-cols-4 gap-2">
              <Select value={typeFilter} onValueChange={(v) => setTypeFilter(v as TypeFilter)}>
                <SelectTrigger className="h-9"><SelectValue placeholder="Type" /></SelectTrigger>
                <SelectContent>
                  <SelectItem value="all">Tous types</SelectItem>
                  <SelectItem value="image">Images</SelectItem>
                  <SelectItem value="pdf">PDF</SelectItem>
                  <SelectItem value="doc">Documents</SelectItem>
                  <SelectItem value="sheet">Tableurs</SelectItem>
                  <SelectItem value="archive">Archives</SelectItem>
                  <SelectItem value="other">Autres</SelectItem>
                </SelectContent>
              </Select>
              <Select value={sizeFilter} onValueChange={(v) => setSizeFilter(v as SizeFilter)}>
                <SelectTrigger className="h-9"><SelectValue placeholder="Taille" /></SelectTrigger>
                <SelectContent>
                  <SelectItem value="all">Toutes tailles</SelectItem>
                  <SelectItem value="small">&lt; 100 Ko</SelectItem>
                  <SelectItem value="medium">100 – 500 Ko</SelectItem>
                  <SelectItem value="large">&gt; 500 Ko</SelectItem>
                </SelectContent>
              </Select>
              <DatePicker value={dateFrom} onChange={setDateFrom} placeholder="Date début" />
              <DatePicker value={dateTo} onChange={setDateTo} placeholder="Date fin" />
            </div>
            {hasActiveFilter && (
              <div className="flex justify-end">
                <Button variant="ghost" size="sm" className="h-7 text-xs" onClick={resetFilters}>
                  <X className="h-3 w-3 mr-1" /> Réinitialiser
                </Button>
              </div>
            )}
          </div>
        )}

        {/* List */}
        {loading ? (
          <div className="text-sm text-muted-foreground text-center py-6 flex items-center justify-center gap-2">
            <Loader2 className="h-4 w-4 animate-spin" />Chargement…
          </div>
        ) : items.length === 0 ? (
          <div className="text-sm text-muted-foreground text-center py-6">
            Aucun document pour le moment.
          </div>
        ) : filtered.length === 0 ? (
          <div className="text-sm text-muted-foreground text-center py-6">
            Aucun document ne correspond aux filtres.
          </div>
        ) : (
          (() => {
            // Group by category in the canonical order, with "Autres" at the end.
            const groups = new Map<string, { label: string; items: Attachment[] }>();
            ATTACHMENT_CATEGORIES.forEach((c) => groups.set(c.key, { label: c.label, items: [] }));
            groups.set("__other__", { label: "Autres documents", items: [] });
            for (const a of filtered) {
              const key = detectCategoryFromFilename(a.filename) ?? "__other__";
              groups.get(key)!.items.push(a);
            }
            const cleanName = (n: string) => n.replace(/^\[[^\]]+\]\s*/, "");
            return (
              <div className="space-y-3">
                {Array.from(groups.entries())
                  .filter(([, g]) => g.items.length > 0)
                  .map(([key, g]) => (
                    <div key={key} className="rounded-lg border border-border overflow-hidden">
                      <div className="flex items-center justify-between bg-muted/40 px-3 py-1.5 border-b border-border">
                        <div className="text-xs font-semibold text-foreground">{g.label}</div>
                        <Badge variant="outline" className="h-5 text-[10px]">{g.items.length}</Badge>
                      </div>
                      <div className="divide-y divide-border">
                        {g.items.map((a) => {
                          const isImg = a.mimeType?.startsWith("image/");
                          const previewUrl = authenticatedApiUrl(a.url, isImg ? { inline: 1 } : undefined);
                          const modifiable = canModifyAttachment(a);
                          return (
                            <div
                              key={a.id}
                              role="button"
                              tabIndex={0}
                              onClick={() => openPreviewForAttachment(a)}
                              onKeyDown={(e) => { if (e.key === "Enter" || e.key === " ") { e.preventDefault(); openPreviewForAttachment(a); } }}
                              className="flex items-center gap-3 px-3 py-2.5 hover:bg-muted/20 cursor-pointer"
                            >
                              <div className="h-9 w-9 rounded-md bg-accent/40 flex items-center justify-center shrink-0 overflow-hidden">
                                {isImg ? (
                                  <img src={previewUrl} alt="" className="h-9 w-9 object-cover rounded-md" />
                                ) : (
                                  <FileTypeIcon mime={a.mimeType} />
                                )}
                              </div>
                              <div className="flex-1 min-w-0">
                                <div className="text-sm font-medium truncate" title={a.filename}>{cleanName(a.filename)}</div>
                                <div className="text-[11px] text-muted-foreground truncate">
                                  {fmtSize(a.sizeBytes)} · @{a.uploadedBy} · {new Date(a.createdAt).toLocaleString("fr-FR")}
                                  {a._originLabel ? ` · ${a._originLabel}` : ""}
                                </div>
                              </div>
                              <Button
                                variant="ghost"
                                size="icon"
                                className="h-8 w-8"
                                onClick={(e) => { e.stopPropagation(); openPreviewForAttachment(a); }}
                                aria-label={`Aperçu ${a.filename}`}
                              >
                                <Eye className="h-4 w-4" />
                              </Button>
                              <a
                                href={authenticatedApiUrl(a.url)}
                                target="_blank"
                                rel="noreferrer"
                                className="inline-flex h-8 w-8 items-center justify-center rounded-md hover:bg-muted text-muted-foreground hover:text-foreground"
                                aria-label={`Télécharger ${a.filename}`}
                                onClick={(e) => e.stopPropagation()}
                              >
                                <Download className="h-4 w-4" />
                              </a>
                              {modifiable && (
                              <Button
                                variant="ghost"
                                size="icon"
                                className="h-8 w-8 text-destructive hover:bg-destructive/10"
                                onClick={(e) => { e.stopPropagation(); void remove(a); }}
                                disabled={deletingId === a.id}
                                aria-label={`Supprimer ${a.filename}`}
                              >
                                {deletingId === a.id ? <Loader2 className="h-4 w-4 animate-spin" /> : <Trash2 className="h-4 w-4" />}
                              </Button>
                              )}
                            </div>
                          );
                        })}
                      </div>
                    </div>
                  ))}
              </div>
            );
          })()
        )}
      </CardContent>

      <AttachmentPreviewDialog
        item={preview}
        open={!!preview}
        onOpenChange={(o) => {
          if (!o && preview?.previewUrl.startsWith("blob:")) revokePreviewItem(preview);
          if (!o) { setPreview(null); setPreviewCategory(null); }
        }}
        items={previewItems}
        onNavigate={(item) => {
          setPreviewCategory(detectCategoryFromFilename(item.filename));
          setPreview(item);
        }}
        canEdit={canEdit}
        removing={!!deletingId}
        replacing={replacing}
        onRemove={handlePreviewRemove}
        onReplaceFile={canEdit ? handlePreviewReplace : undefined}
      />
    </Card>
  );
}
