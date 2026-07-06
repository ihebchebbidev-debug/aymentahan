import type { AttachmentSource } from "@/components/AttachmentsCard";

/** Merge attachment sources for pipeline entities (prospect → opportunity → contract). */
export function buildAttachmentExtraSources(opts: {
  prospectId?: string | null;
  opportunityId?: string | null;
  /** Current primary entity — excluded from extras. */
  primaryEntity: AttachmentSource["entity"];
  primaryId: string;
}): AttachmentSource[] {
  const out: AttachmentSource[] = [];
  const { primaryEntity, primaryId, prospectId, opportunityId } = opts;

  if (prospectId && !(primaryEntity === "prospect" && String(prospectId) === String(primaryId))) {
    out.push({ entity: "prospect", entityId: String(prospectId), label: "Prospect" });
  }
  if (
    opportunityId &&
    !(primaryEntity === "opportunity" && String(opportunityId) === String(primaryId))
  ) {
    out.push({ entity: "opportunity", entityId: String(opportunityId), label: "Opportunité" });
  }
  return out;
}
