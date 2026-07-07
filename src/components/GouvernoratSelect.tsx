import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import {
  TUNISIA_GOVERNORATES,
  isKnownGouvernorat,
  normalizeGouvernorat,
} from "@/lib/tunisiaGovernorates";

const EMPTY = "__gouvernorat_empty__";

type Props = {
  value: string;
  onChange: (value: string) => void;
  id?: string;
  className?: string;
  placeholder?: string;
  disabled?: boolean;
};

export function GouvernoratSelect({
  value,
  onChange,
  id,
  className,
  placeholder = "Choisir un gouvernorat",
  disabled,
}: Props) {
  const normalized = normalizeGouvernorat(value);
  const selectValue = normalized || EMPTY;
  const legacy = value.trim() && !isKnownGouvernorat(value) ? value.trim() : "";

  return (
    <Select
      value={selectValue}
      onValueChange={(v) => onChange(v === EMPTY ? "" : v)}
      disabled={disabled}
    >
      <SelectTrigger id={id} className={className}>
        <SelectValue placeholder={placeholder} />
      </SelectTrigger>
      <SelectContent>
        <SelectItem value={EMPTY}>— Non renseigné —</SelectItem>
        {TUNISIA_GOVERNORATES.map((g) => (
          <SelectItem key={g.value} value={g.value}>
            {g.label}
          </SelectItem>
        ))}
        {legacy ? (
          <SelectItem value={legacy}>{legacy} (valeur existante)</SelectItem>
        ) : null}
      </SelectContent>
    </Select>
  );
}
