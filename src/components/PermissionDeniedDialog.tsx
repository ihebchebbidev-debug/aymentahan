import { createContext, useCallback, useContext, useEffect, useMemo, useState, type ReactNode } from "react";
import { ShieldAlert, Mail, Copy, Check } from "lucide-react";
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog";
import { Button } from "@/components/ui/button";
import { Badge } from "@/components/ui/badge";
import { PERMISSION_SECTIONS } from "@/lib/permissions";

// ---------------------------------------------------------------------------
// Lookup: permission key -> { label, section }
// ---------------------------------------------------------------------------
const PERM_INDEX: Record<string, { label: string; section: string }> = (() => {
  const out: Record<string, { label: string; section: string }> = {};
  for (const s of PERMISSION_SECTIONS) {
    for (const p of s.perms) out[p.key] = { label: p.label, section: s.title };
  }
  return out;
})();

export type PermissionDeniedPayload = {
  perm?: string;
  /** When multiple permissions could unlock the action (any-of). */
  perms?: string[];
  /** Optional action description, e.g. "Supprimer ce prospect". */
  action?: string;
  /** Optional extra context line shown to the user (in French, plain text). */
  details?: string;
};

type Ctx = {
  show: (p: PermissionDeniedPayload) => void;
};
const PermDeniedCtx = createContext<Ctx | null>(null);

export function usePermissionDenied(): Ctx {
  const ctx = useContext(PermDeniedCtx);
  if (!ctx) return { show: () => {} };
  return ctx;
}

// Module-level emitter so non-React code (api.ts global 403 handler,
// permissionGuard helpers) can open the dialog without using a hook.
let _externalShow: ((p: PermissionDeniedPayload) => void) | null = null;
export function showPermissionDenied(p: PermissionDeniedPayload) {
  if (_externalShow) _externalShow(p);
  else if (typeof window !== "undefined") {
    // Defer until provider mounts (e.g. very early 403)
    setTimeout(() => _externalShow?.(p), 50);
  }
}

export function PermissionDeniedDialogProvider({ children }: { children: ReactNode }) {
  const [open, setOpen] = useState(false);
  const [payload, setPayload] = useState<PermissionDeniedPayload | null>(null);
  const [copied, setCopied] = useState(false);

  const show = useCallback((p: PermissionDeniedPayload) => {
    setPayload(p);
    setCopied(false);
    setOpen(true);
  }, []);

  useEffect(() => {
    _externalShow = show;
    return () => {
      if (_externalShow === show) _externalShow = null;
    };
  }, [show]);

  // Normalise to a list: prefer explicit perms[], else fall back to single perm.
  const permKeys = useMemo(() => {
    const list = payload?.perms?.length ? payload.perms : payload?.perm ? [payload.perm] : [];
    // Dedupe while preserving order.
    return Array.from(new Set(list.map((k) => k.trim()).filter(Boolean)));
  }, [payload]);

  const permItems = permKeys.map((key) => {
    const info = PERM_INDEX[key];
    return { key, label: info?.label ?? key, section: info?.section };
  });

  const anyOf = permItems.length > 1;
  const primaryLabel = permItems[0]?.label ?? "Permission requise";

  const copyMessage = useMemo(() => {
    const permLines = permItems.length
      ? permItems.map((p) =>
          `  • « ${p.label} » (clé technique : ${p.key}${p.section ? ` — ${p.section}` : ""})`,
        )
      : ["  • (permission inconnue)"];
    const intro = anyOf
      ? "Permissions à m'accorder (au moins une suffit) :"
      : "Permission à m'accorder :";
    const lines = [
      "Bonjour,",
      "",
      "Je ne parviens pas à effectuer une action dans le CRM car il me manque une permission.",
      payload?.action ? `Action souhaitée : ${payload.action}` : "",
      intro,
      ...permLines,
      "",
      "Merci de me l'attribuer dans Rôles & Permissions, ou via mes accès personnels.",
    ].filter(Boolean);
    return lines.join("\n");
  }, [payload, permItems, anyOf]);

  const onCopy = async () => {
    try {
      await navigator.clipboard.writeText(copyMessage);
      setCopied(true);
      setTimeout(() => setCopied(false), 2000);
    } catch { /* ignore */ }
  };

  const ctx = useMemo<Ctx>(() => ({ show }), [show]);

  return (
    <PermDeniedCtx.Provider value={ctx}>
      {children}
      <Dialog open={open} onOpenChange={setOpen}>
        <DialogContent className="max-w-lg">
          <DialogHeader>
            <div className="mx-auto h-14 w-14 rounded-full bg-destructive/10 text-destructive flex items-center justify-center mb-2">
              <ShieldAlert className="h-7 w-7" />
            </div>
            <DialogTitle className="text-center text-xl">
              Vous n'avez pas la permission nécessaire
            </DialogTitle>
            <DialogDescription className="text-center">
              Cette action a été bloquée par le système de sécurité du CRM.
            </DialogDescription>
          </DialogHeader>

          <div className="space-y-4 py-2">
            {payload?.action && (
              <div className="rounded-lg border border-border bg-muted/30 px-3 py-2 text-sm">
                <div className="text-[11px] uppercase tracking-wide text-muted-foreground mb-0.5">
                  Action demandée
                </div>
                <div className="font-medium">{payload.action}</div>
              </div>
            )}

            <div className="rounded-lg border border-destructive/30 bg-destructive/5 px-3 py-3 space-y-2">
              <div className="text-[11px] uppercase tracking-wide text-destructive/80">
                {anyOf
                  ? `Permissions manquantes (au moins une requise)`
                  : "Permission manquante"}
              </div>
              {permItems.length === 0 ? (
                <div className="text-sm text-muted-foreground italic">
                  Permission non identifiée par le serveur.
                </div>
              ) : (
                <ul className="space-y-2">
                  {permItems.map((p) => (
                    <li
                      key={p.key}
                      className="rounded-md bg-background/60 border border-destructive/20 px-2.5 py-2"
                    >
                      <div className="font-semibold text-sm">{p.label}</div>
                      <div className="mt-1 flex flex-wrap items-center gap-1.5">
                        <code className="px-1.5 py-0.5 rounded bg-muted text-foreground text-[11px] font-mono">
                          {p.key}
                        </code>
                        {p.section && (
                          <Badge variant="outline" className="text-[10px]">
                            {p.section}
                          </Badge>
                        )}
                      </div>
                    </li>
                  ))}
                </ul>
              )}
            </div>

            <div className="text-sm text-muted-foreground leading-relaxed">
              Pour débloquer cette action, veuillez contacter votre{" "}
              <span className="font-medium text-foreground">administrateur</span>{" "}
              {anyOf ? (
                <>et lui demander de vous attribuer <span className="font-medium text-foreground">l'une</span> des permissions listées ci-dessus.</>
              ) : (
                <>et lui demander de vous attribuer la permission{" "}
                <span className="font-medium text-foreground">« {primaryLabel} »</span>.</>
              )}{" "}
              Il pourra le faire depuis la page{" "}
              <span className="font-medium text-foreground">Rôles &amp; Permissions</span>{" "}
              ou directement dans vos accès personnels.
            </div>

            {payload?.details && (
              <div className="text-xs text-muted-foreground italic">{payload.details}</div>
            )}
          </div>


          <DialogFooter className="gap-2 sm:gap-2">
            <Button variant="outline" onClick={onCopy} className="gap-2">
              {copied ? <Check className="h-4 w-4" /> : <Copy className="h-4 w-4" />}
              {copied ? "Message copié" : "Copier le message pour l'admin"}
            </Button>
            <Button onClick={() => setOpen(false)} className="gap-2">
              <Mail className="h-4 w-4" />
              J'ai compris
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </PermDeniedCtx.Provider>
  );
}
