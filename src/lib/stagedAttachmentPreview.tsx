import { useMemo, useState } from "react";
import {
  AttachmentPreviewDialog,
  previewItemFromFile,
  revokePreviewItem,
  type AttachmentPreviewItem,
} from "@/components/AttachmentPreviewDialog";
import type { AttachmentCategoryKey, CategorizedSlotState } from "@/components/CategorizedAttachmentSlots";

export type StagedPreviewContext =
  | { kind: "slot"; key: AttachmentCategoryKey }
  | { kind: "file"; index: number };

export function useStagedAttachmentPreview() {
  const [preview, setPreview] = useState<AttachmentPreviewItem | null>(null);
  const [context, setContext] = useState<StagedPreviewContext | null>(null);

  const openFromFile = (file: File, ctx: StagedPreviewContext, id?: string) => {
    if (preview?.previewUrl.startsWith("blob:")) revokePreviewItem(preview);
    setContext(ctx);
    setPreview(previewItemFromFile(file, id));
  };

  const close = () => {
    if (preview?.previewUrl.startsWith("blob:")) revokePreviewItem(preview);
    setPreview(null);
    setContext(null);
  };

  return { preview, context, setPreview, openFromFile, close };
}

export function StagedAttachmentPreviewDialog({
  preview,
  context,
  items,
  onNavigate,
  onClose,
  onRemove,
  onReplaceFile,
  replacing,
}: {
  preview: AttachmentPreviewItem | null;
  context: StagedPreviewContext | null;
  items: AttachmentPreviewItem[];
  onNavigate: (item: AttachmentPreviewItem) => void;
  onClose: () => void;
  onRemove: (ctx: StagedPreviewContext) => void;
  onReplaceFile: (ctx: StagedPreviewContext, file: File) => void | Promise<void>;
  replacing?: boolean;
}) {
  return (
    <AttachmentPreviewDialog
      item={preview}
      open={!!preview}
      onOpenChange={(o) => { if (!o) onClose(); }}
      items={items}
      onNavigate={onNavigate}
      canEdit
      replacing={replacing}
      onRemove={() => { if (context) onRemove(context); }}
      onReplaceFile={(_, file) => { if (context) void onReplaceFile(context, file); }}
    />
  );
}

/** Build navigable preview list from categorized slots + free staged files. */
export function buildStagedPreviewItems(
  slots: Record<string, CategorizedSlotState | undefined>,
  files: Array<{ toUpload: File; status: string }>
): AttachmentPreviewItem[] {
  const out: AttachmentPreviewItem[] = [];
  for (const [key, st] of Object.entries(slots)) {
    if (st?.file && st.status === "done") {
      out.push(previewItemFromFile(st.file, `slot-${key}`));
    }
  }
  files.forEach((sf, i) => {
    if (sf.status === "ready") out.push(previewItemFromFile(sf.toUpload, `file-${i}`));
  });
  return out;
}

export function findStagedContextByPreviewId(
  id: string,
  slots: Record<string, CategorizedSlotState | undefined>,
): StagedPreviewContext | null {
  if (id.startsWith("slot-")) {
    const key = id.slice(5) as AttachmentCategoryKey;
    if (slots[key]?.file) return { kind: "slot", key };
  }
  if (id.startsWith("file-")) {
    const index = parseInt(id.slice(5), 10);
    if (!Number.isNaN(index)) return { kind: "file", index };
  }
  return null;
}

export function useStagedPreviewItems(
  slots: Record<string, CategorizedSlotState | undefined>,
  files: Array<{ toUpload: File; status: string }>
) {
  return useMemo(() => buildStagedPreviewItems(slots, files), [slots, files]);
}
