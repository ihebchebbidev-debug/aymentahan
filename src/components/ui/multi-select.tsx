import { Check, ChevronDown } from "lucide-react";
import { Button } from "@/components/ui/button";
import { Popover, PopoverContent, PopoverTrigger } from "@/components/ui/popover";
import { Badge } from "@/components/ui/badge";
import { cn } from "@/lib/utils";

type MultiSelectOption = string | { value: string; label: string };

export function MultiSelect({
  options,
  values,
  onChange,
  placeholder = "Choisir…",
  className,
}: {
  options: MultiSelectOption[];
  values: string[];
  onChange: (next: string[]) => void;
  placeholder?: string;
  className?: string;
}) {
  const normalize = (option: MultiSelectOption) =>
    typeof option === "string" ? option : option.value;
  const labelOf = (option: MultiSelectOption) =>
    typeof option === "string" ? option : option.label;
  const toggle = (o: MultiSelectOption) => {
    const value = normalize(o);
    onChange(values.includes(value) ? values.filter((v) => v !== value) : [...values, value]);
  };

  return (
    <Popover>
      <PopoverTrigger asChild>
        <Button
          type="button"
          variant="outline"
          className={cn("w-full justify-between font-normal h-10", className)}
        >
          <span className="flex flex-wrap gap-1 items-center text-left min-w-0">
            {values.length === 0 ? (
              <span className="text-muted-foreground">{placeholder}</span>
            ) : (
              values.slice(0, 3).map((v) => (
                <Badge key={v} variant="secondary" className="font-normal">{v}</Badge>
              ))
            )}
            {values.length > 3 && (
              <Badge variant="secondary" className="font-normal">+{values.length - 3}</Badge>
            )}
          </span>
          <ChevronDown className="h-4 w-4 opacity-50 shrink-0" />
        </Button>
      </PopoverTrigger>
      <PopoverContent className="w-[--radix-popover-trigger-width] p-1" align="start">
        <div className="max-h-64 overflow-auto">
          {options.length === 0 ? (
            <div className="px-2 py-1.5 text-xs text-muted-foreground">Aucune option</div>
          ) : options.map((o) => {
            const value = normalize(o);
            const checked = values.includes(value);
            return (
              <button
                type="button"
                key={value}
                onClick={() => toggle(o)}
                className="flex items-center w-full gap-2 px-2 py-1.5 text-sm rounded hover:bg-accent"
              >
                <span className={cn(
                  "h-4 w-4 rounded border flex items-center justify-center",
                  checked ? "bg-primary border-primary text-primary-foreground" : "border-input"
                )}>
                  {checked && <Check className="h-3 w-3" />}
                </span>
                <span className="truncate">{labelOf(o)}</span>
              </button>
            );
          })}
        </div>
      </PopoverContent>
    </Popover>
  );
}

/** Parse a stored value (JSON array, comma list, or empty) into string[]. */
export function parseMulti(raw: string | undefined): string[] {
  if (!raw) return [];
  const s = String(raw).trim();
  if (!s) return [];
  if (s.startsWith("[")) {
    try {
      const v = JSON.parse(s);
      return Array.isArray(v) ? v.map(String) : [];
    } catch { /* fallthrough */ }
  }
  return s.split(",").map((x) => x.trim()).filter(Boolean);
}

/** Serialize string[] as JSON for storage. */
export function serializeMulti(values: string[]): string {
  return JSON.stringify(values);
}
