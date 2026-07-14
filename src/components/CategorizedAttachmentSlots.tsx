import { useRef } from "react";
import { Label } from "@/components/ui/label";
import { Button } from "@/components/ui/button";
import { Upload, X, FileText, Image as ImageIcon, Loader2, Check, Eye, AudioLines } from "lucide-react";
import { attachmentAcceptAttribute, isAudioAttachmentFile } from "@/lib/attachmentRules";

export const ATTACHMENT_CATEGORIES = [
  { key: "cin_recto", label: "CIN Recto" },
  { key: "cin_verso", label: "CIN Verso" },
  { key: "contrat_tt", label: "Contrat TT" },
  { key: "contrat_topnet", label: "Contrat TOPNET" },
  { key: "cgv", label: "CGV" },
  { key: "enregistrement_vocal", label: "Enregistrement vocal" },
] as const;

export type AttachmentCategoryKey = typeof ATTACHMENT_CATEGORIES[number]["key"];

export type CategorizedSlotState = {
  file: File | null;
  status: "idle" | "uploading" | "done" | "error";
  message?: string;
};

export type CategoryLinkedAttachment = {
  id: string;
  filename: string;
  mimeType: string;
  previewUrl: string;
};

export function CategorizedAttachmentSlots({
  slots,
  onPick,
  onClear,
  onView,
  linkedAttachments,
  disabled,
  hint,
}: {
  slots: Record<string, CategorizedSlotState | undefined>;
  onPick: (categoryKey: AttachmentCategoryKey, file: File) => void | Promise<void>;
  onClear?: (categoryKey: AttachmentCategoryKey) => void;
  /** Open preview modal for a category (uploaded server file or local staged file). */
  onView?: (categoryKey: AttachmentCategoryKey) => void;
  /** Server-side attachment already stored for each category. */
  linkedAttachments?: Partial<Record<AttachmentCategoryKey, CategoryLinkedAttachment>>;
  disabled?: boolean;
  hint?: string;
}) {
  return (
    <div className="space-y-2">
      <div className="flex items-center justify-between">
        <Label className="text-sm font-medium">Pièces jointes — par catégorie</Label>
        <span className="text-[10px] text-muted-foreground">Cliquez pour prévisualiser</span>
      </div>
      {hint && <p className="text-[11px] text-muted-foreground">{hint}</p>}
      <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
        {ATTACHMENT_CATEGORIES.map((cat) => (
          <SlotCard
            key={cat.key}
            label={cat.label}
            state={slots[cat.key]}
            linked={linkedAttachments?.[cat.key]}
            disabled={disabled}
            onPick={(f) => onPick(cat.key, f)}
            onClear={onClear ? () => onClear(cat.key) : undefined}
            onView={onView ? () => onView(cat.key) : undefined}
          />
        ))}
      </div>
    </div>
  );
}

function SlotCard({
  label,
  state,
  linked,
  disabled,
  onPick,
  onClear,
  onView,
}: {
  label: string;
  state?: CategorizedSlotState;
  linked?: CategoryLinkedAttachment;
  disabled?: boolean;
  onPick: (file: File) => void;
  onClear?: () => void;
  onView?: () => void;
}) {
  const ref = useRef<HTMLInputElement>(null);
  const file = state?.file;
  const status = state?.status ?? "idle";
  const isImg = file?.type.startsWith("image/") || linked?.mimeType?.startsWith("image/");
  const isAudio = isAudioAttachmentFile(file as File | undefined) || linked?.mimeType?.startsWith("audio/");
  const hasContent = status === "done" || !!file;
  const displayName = file?.name ?? linked?.filename?.replace(/^\[[^\]]+\]\s*/, "") ?? state?.message ?? label;

  const openPreview = () => {
    if (hasContent && onView) onView();
  };

  return (
    <div className="rounded-lg border border-border bg-card p-3 space-y-1.5">
      <div className="flex items-center justify-between">
        <Label className="text-xs font-medium">{label}</Label>
        {status === "done" && <Check className="h-3.5 w-3.5 text-success" />}
        {status === "uploading" && <Loader2 className="h-3.5 w-3.5 animate-spin text-primary" />}
      </div>
      <input
        ref={ref}
        type="file"
        accept={attachmentAcceptAttribute()}
        className="hidden"
        disabled={disabled || status === "uploading"}
        onChange={(e) => {
          const f = e.target.files?.[0];
          if (f) onPick(f);
          if (ref.current) ref.current.value = "";
        }}
      />
      {!file && status === "done" ? (
        <button
          type="button"
          disabled={!onView}
          onClick={openPreview}
          className={`w-full flex items-center gap-1.5 text-[11px] rounded-md border px-2 py-1.5 text-left transition-colors ${
            onView
              ? "border-success/30 bg-success/5 text-success hover:bg-success/10 cursor-pointer"
              : "border-success/30 bg-success/5 text-success"
          }`}
        >
          {linked && isImg ? (
            <img src={linked.previewUrl} alt="" className="h-8 w-8 rounded object-cover shrink-0" />
          ) : (
            <Check className="h-3.5 w-3.5 shrink-0" />
          )}
          <span className="truncate flex-1" title={displayName}>
            {displayName}
          </span>
          {onView && <Eye className="h-3.5 w-3.5 shrink-0 opacity-70" />}
        </button>
      ) : !file ? (
        <Button
          type="button"
          variant="outline"
          size="sm"
          className="w-full justify-start text-xs h-8"
          disabled={disabled}
          onClick={() => ref.current?.click()}
        >
          <Upload className="h-3.5 w-3.5 mr-1.5" />
          Choisir un fichier
        </Button>
      ) : (
        <button
          type="button"
          disabled={!onView}
          onClick={openPreview}
          className={`w-full flex items-center gap-1.5 text-[11px] rounded-md border px-2 py-1.5 text-left ${
            status === "error"
              ? "bg-destructive/5 border-destructive/30 text-destructive"
              : onView
                ? "bg-muted/30 hover:bg-muted/50 cursor-pointer"
                : "bg-muted/30"
          }`}
        >
          {isImg ? <ImageIcon className="h-3 w-3 shrink-0" /> : isAudio ? <AudioLines className="h-3 w-3 shrink-0" /> : <FileText className="h-3 w-3 shrink-0" />}
          <span className="truncate flex-1" title={file.name}>{file.name}</span>
          <span className="text-[10px] opacity-70">{Math.round(file.size / 1024)} Ko</span>
          {onView && <Eye className="h-3.5 w-3.5 shrink-0 opacity-70" />}
          {onClear && status !== "uploading" && (
            <Button
              type="button"
              variant="ghost"
              size="icon"
              className="h-5 w-5 shrink-0"
              onClick={(e) => { e.stopPropagation(); onClear(); }}
            >
              <X className="h-3 w-3" />
            </Button>
          )}
        </button>
      )}
      {state?.message && status === "error" && (
        <p className="text-[10px] text-destructive">{state.message}</p>
      )}
    </div>
  );
}

/**
 * Helper: prefix a file's name with its category label so the backend stores
 * the category without requiring schema changes.
 */
export function withCategoryPrefix(file: File, categoryLabel: string): File {
  const prefixed = `[${categoryLabel}] ${file.name}`;
  try {
    return new File([file], prefixed, { type: file.type, lastModified: file.lastModified });
  } catch {
    return file;
  }
}

export function categoryLabelOf(key: AttachmentCategoryKey): string {
  return ATTACHMENT_CATEGORIES.find((c) => c.key === key)?.label ?? key;
}

const LEGACY_CATEGORY_PREFIXES: Array<{ prefix: string; key: AttachmentCategoryKey }> = [
  { prefix: "_cin_recto__", key: "cin_recto" },
  { prefix: "_cin_verso__", key: "cin_verso" },
  { prefix: "_contrat_tt__", key: "contrat_tt" },
  { prefix: "_contrat_topnet__", key: "contrat_topnet" },
  { prefix: "_cgv__", key: "cgv" },
  { prefix: "_enregistrement_vocal__", key: "enregistrement_vocal" },
];

export function detectCategoryFromFilename(filename: string): AttachmentCategoryKey | null {
  const m = filename.match(/^\[([^\]]+)\]\s*/);
  if (m) {
    const label = m[1].trim().toLowerCase();
    const found = ATTACHMENT_CATEGORIES.find((c) => c.label.toLowerCase() === label);
    return found?.key ?? null;
  }
  const lower = filename.toLowerCase();
  for (const legacy of LEGACY_CATEGORY_PREFIXES) {
    if (lower.startsWith(legacy.prefix)) return legacy.key;
  }
  return null;
}
