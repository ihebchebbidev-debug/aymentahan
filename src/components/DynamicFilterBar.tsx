/**
 * DynamicFilterBar — Simple, configurable filter bar.
 *
 * Users open the config dialog, check the fields they want, save — those
 * fields instantly appear as live inputs in the bar. Every change fires
 * onChange() immediately so the table updates with no "Apply" button.
 *
 * Configuration is persisted to localStorage per scope so it survives
 * page reloads and navigation.
 */
import { useState } from "react";
import { SlidersHorizontal, X, Search } from "lucide-react";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Badge } from "@/components/ui/badge";
import { Checkbox } from "@/components/ui/checkbox";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import {
  Dialog, DialogContent, DialogHeader, DialogTitle, DialogFooter,
} from "@/components/ui/dialog";
import { usePersistedState } from "@/hooks/use-persisted-state";
import type { FilterFieldSchema } from "@/components/FilterPresetPicker";

// Keys that are always handled separately (search box)
const EXCLUDED_KEYS = new Set(["search"]);

const ALL = "__all__";

export type DynamicFilterBarProps = {
  /** Unique key used to persist the field configuration in localStorage. */
  scope: string;
  /** Full list of filter fields available for this entity. */
  schema: FilterFieldSchema[];
  /** Current filter values, keyed by field key. */
  values: Record<string, string>;
  /** Called whenever a filter value changes. Pass "" to clear a key. */
  onChange: (key: string, value: string) => void;
  /** Called when the "Réinitialiser" button is clicked. */
  onReset: () => void;
};

export function DynamicFilterBar({
  scope,
  schema,
  values,
  onChange,
  onReset,
}: DynamicFilterBarProps) {
  const [configOpen, setConfigOpen] = useState(false);
  // Persisted list of checked field keys for this scope
  const [activeFields, setActiveFields] = usePersistedState<string[]>(
    `dynamicFilters:${scope}:fields`,
    [],
  );
  // Draft state while the config dialog is open
  const [draft, setDraft] = useState<string[]>([]);

  // Fields available to be checked (excludes always-visible ones like search)
  const availableFields = schema.filter((f) => !EXCLUDED_KEYS.has(f.key));
  // Fields currently shown in the bar
  const activeSchemaFields = availableFields.filter((f) =>
    activeFields.includes(f.key),
  );

  // Number of fields that have an active (non-empty) value
  const activeValueCount = activeSchemaFields.filter((f) => {
    const v = values[f.key];
    return v && v !== ALL && v !== "";
  }).length;

  // ---- Config dialog ----
  const openConfig = () => {
    setDraft([...activeFields]);
    setConfigOpen(true);
  };

  const saveConfig = () => {
    // When a field is unchecked, clear its value
    const removed = activeFields.filter((k) => !draft.includes(k));
    for (const k of removed) onChange(k, "");
    setActiveFields(draft);
    setConfigOpen(false);
  };

  const toggleDraft = (key: string) => {
    setDraft((prev) =>
      prev.includes(key) ? prev.filter((k) => k !== key) : [...prev, key],
    );
  };

  // Unpin a single field from the bar and clear its value
  const removeField = (key: string) => {
    setActiveFields((prev) => prev.filter((k) => k !== key));
    onChange(key, "");
  };

  // ---- Render one filter input ----
  const renderField = (field: FilterFieldSchema) => {
    const rawVal = values[field.key] ?? "";
    const selectVal = rawVal || ALL;
    const hasValue = rawVal && rawVal !== ALL && rawVal !== "";

    if (field.type === "select" && field.options && field.options.length > 0) {
      return (
        <div key={field.key} className="flex flex-col gap-1 group/field">
          <span className="text-[10px] font-semibold uppercase tracking-wider text-muted-foreground">
            {field.label}
          </span>
          <div className="flex items-center gap-1">
            <Select
              value={selectVal}
              onValueChange={(v) => onChange(field.key, v === ALL ? "" : v)}
            >
              <SelectTrigger
                className={`h-9 min-w-[140px] max-w-[220px] text-xs transition-colors ${
                  hasValue
                    ? "border-primary/60 bg-primary/5 text-primary font-medium"
                    : ""
                }`}
              >
                <SelectValue placeholder={`— ${field.label} —`} />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value={ALL}>
                  <span className="text-muted-foreground">— Tous —</span>
                </SelectItem>
                {field.options.map((o) => (
                  <SelectItem key={o.value} value={o.value}>
                    {o.label}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
            <button
              type="button"
              title={`Retirer le filtre "${field.label}"`}
              onClick={() => removeField(field.key)}
              className="h-5 w-5 rounded-full opacity-0 group-hover/field:opacity-100 transition-opacity bg-muted hover:bg-destructive/20 hover:text-destructive flex items-center justify-center shrink-0"
            >
              <X className="h-3 w-3" />
            </button>
          </div>
        </div>
      );
    }

    if (field.type === "date") {
      return (
        <div key={field.key} className="flex flex-col gap-1 group/field">
          <span className="text-[10px] font-semibold uppercase tracking-wider text-muted-foreground">
            {field.label}
          </span>
          <div className="flex items-center gap-1">
            <Input
              type="date"
              value={rawVal}
              onChange={(e) => onChange(field.key, e.target.value)}
              className={`h-9 w-[155px] text-xs transition-colors ${
                hasValue ? "border-primary/60 bg-primary/5" : ""
              }`}
            />
            <button
              type="button"
              title={`Retirer le filtre "${field.label}"`}
              onClick={() => removeField(field.key)}
              className="h-5 w-5 rounded-full opacity-0 group-hover/field:opacity-100 transition-opacity bg-muted hover:bg-destructive/20 hover:text-destructive flex items-center justify-center shrink-0"
            >
              <X className="h-3 w-3" />
            </button>
          </div>
        </div>
      );
    }

    // text
    return (
      <div key={field.key} className="flex flex-col gap-1 group/field">
        <span className="text-[10px] font-semibold uppercase tracking-wider text-muted-foreground">
          {field.label}
        </span>
        <div className="flex items-center gap-1">
          <div className="relative">
            {hasValue && (
              <Search className="absolute left-2.5 top-1/2 -translate-y-1/2 h-3.5 w-3.5 text-primary" />
            )}
            <Input
              value={rawVal}
              onChange={(e) => onChange(field.key, e.target.value)}
              placeholder={field.label}
              className={`h-9 w-[160px] text-xs transition-colors ${
                hasValue ? "border-primary/60 bg-primary/5 pl-8" : ""
              }`}
            />
          </div>
          <button
            type="button"
            title={`Retirer le filtre "${field.label}"`}
            onClick={() => removeField(field.key)}
            className="h-5 w-5 rounded-full opacity-0 group-hover/field:opacity-100 transition-opacity bg-muted hover:bg-destructive/20 hover:text-destructive flex items-center justify-center shrink-0"
          >
            <X className="h-3 w-3" />
          </button>
        </div>
      </div>
    );
  };

  return (
    <>
      {/* ---- Active filter inputs ---- */}
      {activeSchemaFields.length > 0 && (
        <div className="flex flex-wrap items-end gap-3 py-2 border-t border-border/50 mt-3 pt-3">
          {activeSchemaFields.map((f) => renderField(f))}
        </div>
      )}

      {/* ---- Control row ---- */}
      <div className="flex items-center gap-2 flex-wrap">
        <Button
          type="button"
          variant="outline"
          size="sm"
          className={`h-9 gap-2 transition-colors ${
            activeFields.length > 0
              ? "border-primary/40 text-primary hover:bg-primary/5"
              : "border-dashed"
          }`}
          onClick={openConfig}
        >
          <SlidersHorizontal className="h-3.5 w-3.5" />
          <span>Configurer les filtres</span>
          {activeFields.length > 0 && (
            <Badge
              variant="secondary"
              className="h-4 min-w-[18px] px-1 text-[10px] font-bold ml-0.5 bg-primary/10 text-primary border-primary/20"
            >
              {activeFields.length}
            </Badge>
          )}
        </Button>

        {activeValueCount > 0 && (
          <Button
            type="button"
            variant="ghost"
            size="sm"
            className="h-9 text-muted-foreground hover:text-destructive"
            onClick={onReset}
          >
            <X className="h-3.5 w-3.5 mr-1" />
            Réinitialiser ({activeValueCount})
          </Button>
        )}
      </div>

      {/* ---- Config Dialog ---- */}
      <Dialog open={configOpen} onOpenChange={setConfigOpen}>
        <DialogContent className="max-w-md max-h-[85vh] flex flex-col gap-0 p-0">
          <DialogHeader className="px-6 pt-6 pb-4 border-b border-border">
            <DialogTitle className="flex items-center gap-2 text-base">
              <SlidersHorizontal className="h-4 w-4 text-primary" />
              Configurer les filtres
            </DialogTitle>
            <p className="text-sm text-muted-foreground mt-1">
              Cochez les critères que vous voulez afficher dans la barre de filtres. Chaque valeur filtre le tableau instantanément.
            </p>
          </DialogHeader>

          <div className="flex-1 overflow-y-auto px-4 py-3 space-y-0.5">
            {availableFields.length === 0 && (
              <p className="text-sm text-muted-foreground text-center py-8">
                Aucun filtre disponible pour cet entité.
              </p>
            )}
            {availableFields.map((field) => {
              const isChecked = draft.includes(field.key);
              const typeLabel =
                field.type === "select"
                  ? "Liste déroulante"
                  : field.type === "date"
                    ? "Date"
                    : "Texte libre";
              return (
                <label
                  key={field.key}
                  className={`flex items-center gap-3 px-3 py-2.5 rounded-lg cursor-pointer select-none transition-colors ${
                    isChecked
                      ? "bg-primary/5 border border-primary/20"
                      : "hover:bg-muted/60 border border-transparent"
                  }`}
                >
                  <Checkbox
                    checked={isChecked}
                    onCheckedChange={() => toggleDraft(field.key)}
                    className="shrink-0"
                  />
                  <div className="flex-1 min-w-0">
                    <p className="text-sm font-medium leading-tight truncate">
                      {field.label}
                    </p>
                    <p className="text-[11px] text-muted-foreground mt-0.5">
                      {typeLabel}
                      {field.options && field.options.length > 0
                        ? ` · ${field.options.length} options`
                        : ""}
                    </p>
                  </div>
                  {isChecked && (
                    <Badge
                      variant="secondary"
                      className="text-[10px] bg-primary/10 text-primary border-primary/20 shrink-0"
                    >
                      Actif
                    </Badge>
                  )}
                </label>
              );
            })}
          </div>

          <DialogFooter className="px-6 py-4 border-t border-border bg-muted/20 flex items-center gap-2">
            <span className="text-xs text-muted-foreground mr-auto">
              {draft.length} filtre{draft.length !== 1 ? "s" : ""} sélectionné{draft.length !== 1 ? "s" : ""}
            </span>
            {draft.length > 0 && (
              <Button
                type="button"
                variant="ghost"
                size="sm"
                className="h-8 text-xs"
                onClick={() => setDraft([])}
              >
                Tout décocher
              </Button>
            )}
            <Button
              type="button"
              variant="outline"
              size="sm"
              className="h-8"
              onClick={() => setConfigOpen(false)}
            >
              Annuler
            </Button>
            <Button type="button" size="sm" className="h-8" onClick={saveConfig}>
              Enregistrer
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </>
  );
}
