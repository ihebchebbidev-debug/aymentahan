import { Settings2, RotateCcw } from "lucide-react";
import { Button } from "@/components/ui/button";
import { Checkbox } from "@/components/ui/checkbox";
import { Label } from "@/components/ui/label";
import { Popover, PopoverContent, PopoverTrigger } from "@/components/ui/popover";
import type { FieldDef } from "@/components/CustomFieldsInline";

export type ColItem = { key: string; label: string };

type Props = {
  /** Base columns of the list (default visible). */
  baseCols?: ColItem[];
  /** Custom field definitions (default visible unless in initial hidden set). */
  defs?: FieldDef[];
  isVisible: (key: string) => boolean;
  onToggle: (key: string, next: boolean) => void;
  onShowAll?: () => void;
  onReset?: () => void;
};

/**
 * Unified column picker: toggles both the built-in columns of the table AND
 * any custom-field columns. Preferences are managed by the caller (see
 * `useColumnPrefs`) and persisted to localStorage.
 *
 * The exported header set follows the same visibility flags, so users get
 * "export exactly what they see".
 */
export function CustomColumnsPicker({ baseCols = [], defs = [], isVisible, onToggle, onShowAll, onReset }: Props) {
  if (baseCols.length === 0 && defs.length === 0) return null;

  const totalCount = baseCols.length + defs.length;
  const visibleCount = baseCols.filter((c) => isVisible(c.key)).length +
    defs.filter((d) => isVisible(d.key)).length;

  return (
    <Popover>
      <PopoverTrigger asChild>
        <Button variant="outline" size="sm">
          <Settings2 className="h-4 w-4 mr-1.5" /> Colonnes
          <span className="ml-1.5 text-[10px] text-muted-foreground">({visibleCount}/{totalCount})</span>
        </Button>
      </PopoverTrigger>
      <PopoverContent align="end" className="w-72 p-3 space-y-3">
        <div className="flex items-center justify-between gap-2 pb-2 border-b border-border">
          <span className="text-[10px] uppercase tracking-wide text-muted-foreground">Affichage</span>
          <div className="flex gap-1">
            {onShowAll && (
              <Button variant="ghost" size="sm" className="h-6 px-2 text-xs" onClick={onShowAll}>Tout</Button>
            )}
            {onReset && (
              <Button variant="ghost" size="sm" className="h-6 px-2 text-xs" onClick={onReset} title="Réinitialiser">
                <RotateCcw className="h-3 w-3" />
              </Button>
            )}
          </div>
        </div>

        {baseCols.length > 0 && (
          <div className="space-y-2">
            <div className="text-[10px] font-medium uppercase tracking-wide text-muted-foreground">
              Colonnes principales
            </div>
            <div className="space-y-1 max-h-56 overflow-auto">
              {baseCols.map((c) => (
                <Label
                  key={c.key}
                  htmlFor={`col-base-${c.key}`}
                  className="flex items-center gap-2 cursor-pointer text-sm font-normal py-0.5"
                >
                  <Checkbox
                    id={`col-base-${c.key}`}
                    checked={isVisible(c.key)}
                    onCheckedChange={(v) => onToggle(c.key, !!v)}
                  />
                  <span className="truncate">{c.label}</span>
                </Label>
              ))}
            </div>
          </div>
        )}

        {defs.length > 0 && (
          <div className="space-y-2">
            <div className="text-[10px] font-medium uppercase tracking-wide text-muted-foreground">
              Champs personnalisés
            </div>
            <div className="space-y-1 max-h-56 overflow-auto">
              {defs.map((f) => (
                <Label
                  key={f.id}
                  htmlFor={`col-cf-${f.id}`}
                  className="flex items-center gap-2 cursor-pointer text-sm font-normal py-0.5"
                >
                  <Checkbox
                    id={`col-cf-${f.id}`}
                    checked={isVisible(f.key)}
                    onCheckedChange={(v) => onToggle(f.key, !!v)}
                  />
                  <span className="truncate">{f.label}</span>
                </Label>
              ))}
            </div>
          </div>
        )}
      </PopoverContent>
    </Popover>
  );
}
