import { useMemo } from "react";
import { Label } from "@/components/ui/label";
import {
  Select, SelectContent, SelectItem, SelectTrigger, SelectValue,
} from "@/components/ui/select";
import { useErp } from "@/lib/erpStore";
import { useTeams } from "@/hooks/use-teams";
import { DEFAULT_USER_AGENCY_TEAM, mergeUserAgencyTeams } from "@/lib/userAgencyTeams";

export function UserAgencyTeamSelect({
  value,
  onChange,
  id,
}: {
  value: string;
  onChange: (team: string) => void;
  id?: string;
}) {
  const { users } = useErp();
  const { teams } = useTeams();
  const options = useMemo(() => {
    // Start with backend teams
    const set = new Set<string>(teams.map((t) => t.name));
    // Add static defaults + user-assigned teams as fallback
    for (const t of mergeUserAgencyTeams(users.map((u) => u.team))) {
      set.add(t);
    }
    const current = value.trim();
    if (current) set.add(current);
    return Array.from(set).sort((a, b) => a.localeCompare(b, "fr"));
  }, [users, teams, value]);

  const selected = value.trim() || DEFAULT_USER_AGENCY_TEAM;

  return (
    <div className="space-y-1.5">
      <Label htmlFor={id}>Équipe</Label>
      <Select value={selected} onValueChange={onChange}>
        <SelectTrigger id={id}>
          <SelectValue placeholder="Choisir une équipe" />
        </SelectTrigger>
        <SelectContent>
          {options.map((t) => (
            <SelectItem key={t} value={t}>{t}</SelectItem>
          ))}
        </SelectContent>
      </Select>
    </div>
  );
}
