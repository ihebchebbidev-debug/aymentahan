import { useEffect, useState } from "react";
import { Dialog, DialogContent, DialogHeader, DialogTitle } from "@/components/ui/dialog";
import { Button } from "@/components/ui/button";
import { chatApi } from "@/lib/chat";

export default function ShareAttachmentDialog({
  open,
  onOpenChange,
  attachmentId,
  onShared,
}: {
  open: boolean;
  onOpenChange: (v: boolean) => void;
  attachmentId: string | null;
  onShared?: (msg: any) => void;
}) {
  const [convs, setConvs] = useState<any[] | null>(null);
  const [users, setUsers] = useState<any[] | null>(null);
  const [loading, setLoading] = useState(false);
  const [mode, setMode] = useState<'conversations'|'users'>('conversations');

  useEffect(() => {
    if (!open) return;
    let mounted = true;
    chatApi.conversations().then((r) => { if (mounted) setConvs(r.conversations); }).catch(() => { if (mounted) setConvs([]); });
    chatApi.users().then((r) => { if (mounted) setUsers(r.users); }).catch(() => { if (mounted) setUsers([]); });
    return () => { mounted = false; };
  }, [open]);

  const shareTo = async (convId: string) => {
    if (!attachmentId) return;
    setLoading(true);
    try {
      const res = await chatApi.forwardAttachment(attachmentId, convId);
      onShared?.(res.message);
      onOpenChange(false);
    } catch (e: any) {
      // eslint-disable-next-line no-console
      console.error(e);
    } finally { setLoading(false); }
  };

  const shareToUser = async (username: string) => {
    if (!attachmentId) return;
    setLoading(true);
    try {
      const res = await chatApi.forwardToUser(attachmentId, username);
      onShared?.(res.message);
      onOpenChange(false);
    } catch (e: any) {
      // eslint-disable-next-line no-console
      console.error(e);
    } finally { setLoading(false); }
  };

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="max-w-md w-[95vw]">
        <DialogHeader>
          <DialogTitle>Partager la pièce jointe</DialogTitle>
        </DialogHeader>
        <div className="p-4">
          <div className="flex gap-2 mb-3">
            <Button size="sm" variant={mode === 'conversations' ? undefined : 'ghost'} onClick={() => setMode('conversations')}>Conversations</Button>
            <Button size="sm" variant={mode === 'users' ? undefined : 'ghost'} onClick={() => setMode('users')}>Personnes</Button>
          </div>
          {mode === 'conversations' && (
            <>
              <div className="text-sm text-muted-foreground mb-3">Choisir une conversation où partager cette pièce jointe.</div>
              <div className="max-h-72 overflow-auto space-y-2">
                {convs === null && <div className="text-sm text-muted-foreground">Chargement…</div>}
                {convs !== null && convs.length === 0 && <div className="text-sm text-muted-foreground">Aucune conversation disponible</div>}
                {convs?.map((c) => (
                  <div key={c.id} className="flex items-center justify-between p-2 rounded hover:bg-muted/40">
                    <div className="min-w-0">
                      <div className="text-sm truncate">{c.name ?? (c.type === 'dm' ? 'Message direct' : 'Conversation')}</div>
                      <div className="text-xs text-muted-foreground">{c.members?.length ?? 0} membre(s)</div>
                    </div>
                    <div>
                      <Button size="sm" onClick={() => shareTo(c.id)} disabled={loading}>Partager</Button>
                    </div>
                  </div>
                ))}
              </div>
            </>
          )}
          {mode === 'users' && (
            <>
              <div className="text-sm text-muted-foreground mb-3">Choisir une personne pour démarrer (ou retrouver) une conversation privée.</div>
              <div className="max-h-72 overflow-auto space-y-2">
                {users === null && <div className="text-sm text-muted-foreground">Chargement…</div>}
                {users !== null && users.length === 0 && <div className="text-sm text-muted-foreground">Aucun utilisateur disponible</div>}
                {users?.map((u) => (
                  <div key={u.username} className="flex items-center justify-between p-2 rounded hover:bg-muted/40">
                    <div className="min-w-0">
                      <div className="text-sm truncate">{u.fullName ?? u.username}</div>
                      <div className="text-xs text-muted-foreground">{u.username} • {u.role}</div>
                    </div>
                    <div>
                      <Button size="sm" onClick={() => shareToUser(u.username)} disabled={loading}>Partager</Button>
                    </div>
                  </div>
                ))}
              </div>
            </>
          )}
          <div className="mt-4 text-right">
            <Button variant="ghost" size="sm" onClick={() => onOpenChange(false)}>Annuler</Button>
          </div>
        </div>
      </DialogContent>
    </Dialog>
  );
}
