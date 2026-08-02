type CommentThreadEntry = {
  id: string;
  author: string | null;
  date: string | null;
  body: string;
};

export function CommentThread({
  entries,
  emptyLabel = "Aucun commentaire.",
}: {
  entries: CommentThreadEntry[];
  emptyLabel?: string;
}) {
  return (
    <div className="space-y-3">
      {entries.length === 0 ? (
        <div className="rounded-3xl border border-border bg-muted/40 p-5 text-sm text-muted-foreground">
          {emptyLabel}
        </div>
      ) : (
        entries.map((entry) => (
          <div key={entry.id} className="rounded-3xl border border-border bg-background shadow-sm p-5">
            <div className="flex flex-wrap items-center justify-between gap-2 text-xs text-muted-foreground">
              <span className="font-semibold text-foreground">{entry.author ?? "Note"}</span>
              {entry.date ? <span>{entry.date}</span> : null}
            </div>
            <div className="whitespace-pre-wrap text-sm text-foreground leading-7 mt-3">{entry.body}</div>
          </div>
        ))
      )}
    </div>
  );
}
