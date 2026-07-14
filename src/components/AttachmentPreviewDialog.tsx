import { useCallback, useEffect, useRef } from "react";
import { Button } from "@/components/ui/button";
import { attachmentAcceptAttribute, isAudioAttachmentFile } from "@/lib/attachmentRules";
import {
  Dialog,
  DialogContent,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog";
import {
  ChevronLeft,
  ChevronRight,
  Download,
  Loader2,
  RefreshCw,
  Trash2,
} from "lucide-react";

export type AttachmentPreviewItem = {
  id: string;
  filename: string;
  mimeType: string;
  sizeBytes: number;
  /** URL used for inline preview (blob: or authenticated API URL). */
  previewUrl: string;
  /** URL used for download; defaults to previewUrl. */
  downloadUrl?: string;
  readOnly?: boolean;
};

function cleanFilename(name: string) {
  return name.replace(/^\[[^\]]+\]\s*/, "");
}

function fmtSize(b: number) {
  if (b < 1024) return `${b} o`;
  if (b < 1024 * 1024) return `${(b / 1024).toFixed(1)} Ko`;
  return `${(b / 1024 / 1024).toFixed(2)} Mo`;
}

export function isAttachmentPreviewable(mime: string) {
  return mime?.startsWith("image/") || mime === "application/pdf" || mime?.startsWith("audio/");
}

export function AttachmentPreviewDialog({
  item,
  open,
  onOpenChange,
  items,
  onNavigate,
  onRemove,
  onReplaceFile,
  canEdit = false,
  removing = false,
  replacing = false,
}: {
  item: AttachmentPreviewItem | null;
  open: boolean;
  onOpenChange: (open: boolean) => void;
  items?: AttachmentPreviewItem[];
  onNavigate?: (item: AttachmentPreviewItem) => void;
  onRemove?: (item: AttachmentPreviewItem) => void | Promise<void>;
  onReplaceFile?: (item: AttachmentPreviewItem, file: File) => void | Promise<void>;
  canEdit?: boolean;
  removing?: boolean;
  replacing?: boolean;
}) {
  const replaceRef = useRef<HTMLInputElement>(null);
  const navItems = items && items.length > 0 ? items : (item ? [item] : []);
  const index = item ? navItems.findIndex((x) => x.id === item.id) : -1;

  const go = useCallback((delta: number) => {
    if (index < 0 || navItems.length < 2 || !onNavigate) return;
    const next = (index + delta + navItems.length) % navItems.length;
    onNavigate(navItems[next]);
  }, [index, navItems, onNavigate]);

  useEffect(() => {
    if (!open) return;
    const onKey = (e: KeyboardEvent) => {
      if (e.key === "ArrowRight") { e.preventDefault(); go(1); }
      else if (e.key === "ArrowLeft") { e.preventDefault(); go(-1); }
    };
    window.addEventListener("keydown", onKey);
    return () => window.removeEventListener("keydown", onKey);
  }, [open, go]);

  const editable = canEdit && !item?.readOnly && !!onRemove;
  const replaceable = canEdit && !!onReplaceFile;

  return (
    <>
      <input
        ref={replaceRef}
        type="file"
        accept={attachmentAcceptAttribute()}
        className="hidden"
        onChange={(e) => {
          const f = e.target.files?.[0];
          if (f && item && onReplaceFile) void onReplaceFile(item, f);
          if (replaceRef.current) replaceRef.current.value = "";
        }}
      />
      <Dialog open={open} onOpenChange={onOpenChange}>
        <DialogContent className="max-w-4xl w-[95vw] p-0 overflow-hidden">
          <DialogHeader className="px-4 py-3 border-b">
            <DialogTitle className="text-sm truncate pr-8">
              {item ? cleanFilename(item.filename) : ""}
              {navItems.length > 1 && index >= 0 && (
                <span className="ml-2 text-xs font-normal text-muted-foreground">
                  ({index + 1} / {navItems.length})
                </span>
              )}
            </DialogTitle>
            {item && (
              <p className="text-[11px] text-muted-foreground font-normal truncate">
                {fmtSize(item.sizeBytes)}
                {item.readOnly ? " · lecture seule (fiche liée)" : ""}
              </p>
            )}
          </DialogHeader>
          {item && (
            <div className="relative bg-muted/30 flex items-center justify-center min-h-[55vh] max-h-[75vh]">
              {item.mimeType?.startsWith("image/") ? (
                <img
                  src={item.previewUrl}
                  alt={item.filename}
                  className="max-h-[75vh] max-w-full object-contain"
                />
              ) : item.mimeType === "application/pdf" ? (
                <iframe
                  src={item.previewUrl}
                  title={item.filename}
                  className="w-full h-[75vh] bg-white"
                />
              ) : isAudioAttachmentFile({ name: item.filename, type: item.mimeType } as File) ? (
                <div className="p-8 text-sm text-muted-foreground text-center">
                  <div className="mb-3 text-base font-medium text-foreground">Enregistrement vocal</div>
                  <p className="mb-4">Cet audio peut être téléchargé et lu depuis votre lecteur local.</p>
                  <div className="mt-3">
                    <Button variant="outline" size="sm" asChild>
                      <a href={item.downloadUrl ?? item.previewUrl} target="_blank" rel="noreferrer">
                        <Download className="h-4 w-4 mr-1.5" />
                        Télécharger
                      </a>
                    </Button>
                  </div>
                </div>
              ) : (
                <div className="p-8 text-sm text-muted-foreground text-center">
                  Aperçu indisponible pour ce format.
                  <div className="mt-3">
                    <Button variant="outline" size="sm" asChild>
                      <a href={item.downloadUrl ?? item.previewUrl} target="_blank" rel="noreferrer">
                        <Download className="h-4 w-4 mr-1.5" />
                        Télécharger
                      </a>
                    </Button>
                  </div>
                </div>
              )}
              {navItems.length > 1 && onNavigate && (
                <>
                  <button
                    type="button"
                    onClick={() => go(-1)}
                    className="absolute left-2 top-1/2 -translate-y-1/2 h-10 w-10 rounded-full bg-background/80 hover:bg-background border border-border shadow-sm flex items-center justify-center"
                    aria-label="Fichier précédent"
                  >
                    <ChevronLeft className="h-5 w-5" />
                  </button>
                  <button
                    type="button"
                    onClick={() => go(1)}
                    className="absolute right-2 top-1/2 -translate-y-1/2 h-10 w-10 rounded-full bg-background/80 hover:bg-background border border-border shadow-sm flex items-center justify-center"
                    aria-label="Fichier suivant"
                  >
                    <ChevronRight className="h-5 w-5" />
                  </button>
                </>
              )}
            </div>
          )}
          {item && (
            <div className="flex flex-wrap items-center justify-between gap-2 px-4 py-3 border-t">
              <div className="flex flex-wrap gap-2">
                {navItems.length > 1 && onNavigate && (
                  <>
                    <Button variant="outline" size="sm" onClick={() => go(-1)}>
                      <ChevronLeft className="h-4 w-4 mr-1" />
                      Précédent
                    </Button>
                    <Button variant="outline" size="sm" onClick={() => go(1)}>
                      Suivant
                      <ChevronRight className="h-4 w-4 ml-1" />
                    </Button>
                  </>
                )}
                {replaceable && (
                  <Button
                    variant="outline"
                    size="sm"
                    disabled={replacing || removing}
                    onClick={() => replaceRef.current?.click()}
                  >
                    {replacing ? (
                      <Loader2 className="h-4 w-4 mr-1.5 animate-spin" />
                    ) : (
                      <RefreshCw className="h-4 w-4 mr-1.5" />
                    )}
                    Remplacer
                  </Button>
                )}
                {editable && (
                  <Button
                    variant="outline"
                    size="sm"
                    className="text-destructive border-destructive/30 hover:bg-destructive/10"
                    disabled={removing || replacing}
                    onClick={() => void onRemove!(item)}
                  >
                    {removing ? (
                      <Loader2 className="h-4 w-4 mr-1.5 animate-spin" />
                    ) : (
                      <Trash2 className="h-4 w-4 mr-1.5" />
                    )}
                    Supprimer
                  </Button>
                )}
              </div>
              <div className="flex flex-wrap gap-2 ml-auto">
                <Button variant="outline" size="sm" asChild>
                  <a
                    href={item.downloadUrl ?? item.previewUrl}
                    target="_blank"
                    rel="noreferrer"
                    download={cleanFilename(item.filename)}
                  >
                    <Download className="h-4 w-4 mr-1.5" />
                    Télécharger
                  </a>
                </Button>
                <Button variant="ghost" size="sm" onClick={() => onOpenChange(false)}>
                  Fermer
                </Button>
              </div>
            </div>
          )}
        </DialogContent>
      </Dialog>
    </>
  );
}

/** Build a preview item from a local File (blob URL — revoke when done). */
export function previewItemFromFile(file: File, id?: string): AttachmentPreviewItem {
  return {
    id: id ?? `local-${file.name}-${file.lastModified}`,
    filename: file.name,
    mimeType: file.type || "application/octet-stream",
    sizeBytes: file.size,
    previewUrl: URL.createObjectURL(file),
  };
}

export function revokePreviewItem(item: AttachmentPreviewItem) {
  if (item.previewUrl.startsWith("blob:")) {
    try { URL.revokeObjectURL(item.previewUrl); } catch { /* ignore */ }
  }
}
