import { useEffect, useState } from "react";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import {
  Select, SelectContent, SelectItem, SelectTrigger, SelectValue,
} from "@/components/ui/select";

export const DEBIT_PRESETS = [10, 20, 30, 50, 100] as const;
const AUTRE = "__autre__";
const NONE = "__none__";

type Props = {
  value: number | null | undefined;
  onChange: (v: number | null) => void;
  label?: string;
  allowNone?: boolean;
};

/** Select "Débit" avec valeurs 10/20/30/50/100 Mbps + Autre (saisie libre). */
export function DebitSelect({ value, onChange, label = "Débit", allowNone = true }: Props) {
  const isPreset = value != null && (DEBIT_PRESETS as readonly number[]).includes(value);
  const [mode, setMode] = useState<string>(
    value == null ? (allowNone ? NONE : String(DEBIT_PRESETS[0]))
      : isPreset ? String(value) : AUTRE,
  );
  const [autre, setAutre] = useState<string>(value != null && !isPreset ? String(value) : "");

  useEffect(() => {
    if (value == null) { setMode(allowNone ? NONE : String(DEBIT_PRESETS[0])); setAutre(""); return; }
    if ((DEBIT_PRESETS as readonly number[]).includes(value)) {
      setMode(String(value));
    } else {
      setMode(AUTRE);
      setAutre(String(value));
    }
  }, [value, allowNone]);

  const handleMode = (v: string) => {
    setMode(v);
    if (v === NONE) { onChange(null); return; }
    if (v === AUTRE) {
      const n = Number(autre);
      onChange(Number.isFinite(n) && n > 0 ? Math.round(n) : null);
      return;
    }
    onChange(Number(v));
  };

  const handleAutre = (raw: string) => {
    setAutre(raw);
    const n = Number(raw);
    onChange(Number.isFinite(n) && n > 0 ? Math.round(n) : null);
  };

  return (
    <div className="space-y-1.5">
      <Label>{label}</Label>
      <div className="flex gap-2">
        <Select value={mode} onValueChange={handleMode}>
          <SelectTrigger className="flex-1"><SelectValue placeholder="Débit…" /></SelectTrigger>
          <SelectContent>
            {allowNone && <SelectItem value={NONE}>— Non renseigné —</SelectItem>}
            {DEBIT_PRESETS.map((n) => (
              <SelectItem key={n} value={String(n)}>{n} Mbps</SelectItem>
            ))}
            <SelectItem value={AUTRE}>Autre…</SelectItem>
          </SelectContent>
        </Select>
        {mode === AUTRE && (
          <Input
            type="number"
            min={1}
            className="w-28"
            placeholder="Mbps"
            value={autre}
            onChange={(e) => handleAutre(e.target.value)}
          />
        )}
      </div>
    </div>
  );
}