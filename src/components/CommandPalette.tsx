import { useEffect, useMemo, useState } from "react";
import { useNavigate } from "@tanstack/react-router";
import {
  CommandDialog,
  CommandEmpty,
  CommandGroup,
  CommandInput,
  CommandItem,
  CommandList,
  CommandSeparator,
} from "@/components/ui/command";
import { useErp } from "@/lib/erpStore";
import { useAuth } from "@/lib/auth";
import { useQuery } from "@/lib/queryClient";
import { fetchMigrations } from "@/lib/migrationsApi";
import { canViewMigrationsData } from "@/lib/permissions";
import { API_ENABLED } from "@/lib/api";
import {
  ClipboardList,
  FileText,
  ArrowRightLeft,
  Users as UsersIcon,
  LayoutDashboard,
  Calendar as CalIcon,
  BarChart3,
  Sparkles,
  Plus,
  CheckSquare,
  Bell,
  GitMerge,
  Settings,
  ShieldCheck,
} from "lucide-react";

/**
 * Global ⌘K palette — search prospects/contracts/users + quick navigation.
 * Mounted once in AppLayout.
 */
export function CommandPalette() {
  const [open, setOpen] = useState(false);
  const [query, setQuery] = useState("");
  const navigate = useNavigate();
  const { prospects, contracts, users, events } = useErp();
  const { user, hasPermission } = useAuth();
  const isAdmin = user?.role === "Administrateur";
  const can = (perm: string) => isAdmin || hasPermission(perm);

  const canViewProspects = can("page.prospects");
  const canViewOpportunities = can("page.opportunities");
  const canViewContracts = can("page.contracts");
  const canViewMigrations = canViewMigrationsData(can);
  const canViewUsers = can("page.users");
  const canViewCalendar = can("page.calendar");
  const canViewTasks = can("page.tasks");
  const canViewReports = can("page.reports");
  const canViewReconciliation = can("page.reconciliation");
  const canViewDispatch = can("page.dispatch");
  const canViewRoles = can("page.roles");
  const canViewConfiguration = can("page.configuration");

  useEffect(() => {
    const onKey = (e: KeyboardEvent) => {
      if ((e.metaKey || e.ctrlKey) && e.key.toLowerCase() === "k") {
        e.preventDefault();
        setOpen((o) => !o);
      }
    };
    window.addEventListener("keydown", onKey);
    return () => window.removeEventListener("keydown", onKey);
  }, []);

  // Expose a global opener so the header search bar can also trigger it.
  useEffect(() => {
    (window as any).__openCommandPalette = () => setOpen(true);
    return () => { delete (window as any).__openCommandPalette; };
  }, []);

  const q = query.trim().toLowerCase();
  const matchedProspects = useMemo(() => {
    if (!canViewProspects) return [];
    if (!q) return prospects.slice(0, 5);
    return prospects
      .filter((p) =>
        `${p.lastName} ${p.firstName} ${p.phone} ${p.email} ${p.city} ${p.id}`
          .toLowerCase()
          .includes(q),
      )
      .slice(0, 8);
  }, [prospects, q, canViewProspects]);

  const migrationsQ = useQuery({
    queryKey: ["migrations"],
    queryFn: fetchMigrations,
    enabled: API_ENABLED && canViewMigrations && open,
  });
  const migrations = migrationsQ.data ?? [];

  const matchedContracts = useMemo(() => {
    if (!canViewContracts) return [];
    if (!q) return contracts.slice(0, 5);
    return contracts
      .filter((c) =>
        `${c.lastName} ${c.firstName} ${c.city} ${c.partner} ${c.id}`
          .toLowerCase()
          .includes(q),
      )
      .slice(0, 8);
  }, [contracts, q, canViewContracts]);

  const matchedMigrations = useMemo(() => {
    if (!canViewMigrations) return [];
    if (!q) return migrations.slice(0, 5);
    return migrations
      .filter((m) =>
        `${m.lastName} ${m.firstName} ${m.phone ?? ""} ${m.oldOperator ?? ""} ${m.newOperator ?? ""} ${m.id}`
          .toLowerCase()
          .includes(q),
      )
      .slice(0, 8);
  }, [migrations, q, canViewMigrations]);

  const matchedUsers = useMemo(() => {
    if (!canViewUsers) return [];
    if (!q) return users.slice(0, 5);
    return users
      .filter((u) =>
        `${u.fullName} ${u.username} ${u.email} ${u.team} ${u.role}`
          .toLowerCase()
          .includes(q),
      )
      .slice(0, 8);
  }, [users, q, canViewUsers]);

  const matchedEvents = useMemo(() => {
    if (!canViewCalendar) return [];
    if (!q) return events.slice(0, 3);
    return events
      .filter((e) =>
        `${e.title} ${e.agent} ${e.type} ${e.date}`.toLowerCase().includes(q),
      )
      .slice(0, 6);
  }, [events, q, canViewCalendar]);

  const go = (to: string, params?: Record<string, string>) => {
    setOpen(false);
    setQuery("");
    navigate({ to, params } as any);
  };

  const hasResults =
    matchedProspects.length > 0 ||
    matchedContracts.length > 0 ||
    matchedMigrations.length > 0 ||
    matchedUsers.length > 0 ||
    matchedEvents.length > 0;

  return (
    <CommandDialog open={open} onOpenChange={setOpen} shouldFilter={false}>
      <CommandInput
        value={query}
        onValueChange={setQuery}
        placeholder="Rechercher prospects, contrats, migrations… ou tapez une commande"
      />
      <CommandList>
        {q && !hasResults && <CommandEmpty>Aucun résultat.</CommandEmpty>}

        {!q && (
          <CommandGroup heading="Navigation">
            <CommandItem onSelect={() => go("/")}>
              <LayoutDashboard className="h-4 w-4 mr-2" /> Tableau de bord
            </CommandItem>
            {canViewProspects && (
              <CommandItem onSelect={() => go("/prospects")}>
                <ClipboardList className="h-4 w-4 mr-2" /> Prospects
              </CommandItem>
            )}
            {canViewOpportunities && (
              <CommandItem onSelect={() => go("/opportunities")}>
                <GitMerge className="h-4 w-4 mr-2" /> Opportunités
              </CommandItem>
            )}
            {canViewContracts && (
              <CommandItem onSelect={() => go("/contracts")}>
                <FileText className="h-4 w-4 mr-2" /> Contrats
              </CommandItem>
            )}
            {canViewMigrations && (
              <CommandItem onSelect={() => go("/migrations")}>
                <ArrowRightLeft className="h-4 w-4 mr-2" /> Migrations
              </CommandItem>
            )}
            {canViewCalendar && (
              <CommandItem onSelect={() => go("/calendar")}>
                <CalIcon className="h-4 w-4 mr-2" /> Calendrier
              </CommandItem>
            )}
            {canViewTasks && (
              <CommandItem onSelect={() => go("/tasks")}>
                <CheckSquare className="h-4 w-4 mr-2" /> Tâches
              </CommandItem>
            )}
            <CommandItem onSelect={() => go("/notifications")}>
              <Bell className="h-4 w-4 mr-2" /> Notifications
            </CommandItem>
            {canViewReports && (
              <CommandItem onSelect={() => go("/reports")}>
                <BarChart3 className="h-4 w-4 mr-2" /> Rapports
              </CommandItem>
            )}
            {canViewReconciliation && (
              <CommandItem onSelect={() => go("/reconciliation")}>
                <GitMerge className="h-4 w-4 mr-2" /> Réconciliation des imports
              </CommandItem>
            )}
            {canViewUsers && (
              <CommandItem onSelect={() => go("/users")}>
                <UsersIcon className="h-4 w-4 mr-2" /> Utilisateurs
              </CommandItem>
            )}
            {canViewRoles && (
              <CommandItem onSelect={() => go("/roles")}>
                <ShieldCheck className="h-4 w-4 mr-2" /> Rôles
              </CommandItem>
            )}
            {canViewConfiguration && (
              <CommandItem onSelect={() => go("/configuration")}>
                <Settings className="h-4 w-4 mr-2" /> Configuration
              </CommandItem>
            )}
          </CommandGroup>
        )}

        {matchedProspects.length > 0 && (
          <>
            <CommandSeparator />
            <CommandGroup heading={`Prospects (${matchedProspects.length})`}>
              {matchedProspects.map((p) => (
                <CommandItem
                  key={p.id}
                  value={`prospect-${p.id}-${p.lastName}-${p.firstName}-${p.phone}`}
                  onSelect={() =>
                    go("/prospects/$prospectId", { prospectId: p.id })
                  }
                >
                  <ClipboardList className="h-4 w-4 mr-2 text-muted-foreground" />
                  <span className="font-medium">
                    {p.civility} {p.lastName} {p.firstName}
                  </span>
                  <span className="ml-2 text-xs text-muted-foreground truncate">
                    {p.phone || p.email} · {p.city} · {p.status}
                  </span>
                </CommandItem>
              ))}
            </CommandGroup>
          </>
        )}

        {matchedMigrations.length > 0 && (
          <>
            <CommandSeparator />
            <CommandGroup heading={`Migrations (${matchedMigrations.length})`}>
              {matchedMigrations.map((m) => (
                <CommandItem
                  key={m.id}
                  value={`migration-${m.id}-${m.lastName}-${m.firstName}`}
                  onSelect={() => go("/migrations/$migrationId", { migrationId: m.id })}
                >
                  <ArrowRightLeft className="h-4 w-4 mr-2 text-muted-foreground" />
                  <span className="font-medium">{m.lastName} {m.firstName}</span>
                  <span className="ml-2 text-xs text-muted-foreground truncate">
                    {m.oldOperator} → {m.newOperator} · {m.workflowStatus}
                  </span>
                </CommandItem>
              ))}
            </CommandGroup>
          </>
        )}

        {matchedContracts.length > 0 && (
          <>
            <CommandSeparator />
            <CommandGroup heading={`Contrats (${matchedContracts.length})`}>
              {matchedContracts.map((c) => (
                <CommandItem
                  key={c.id}
                  value={`contract-${c.id}-${c.lastName}-${c.firstName}`}
                  onSelect={() =>
                    go("/contracts/$contractId", { contractId: c.id })
                  }
                >
                  <FileText className="h-4 w-4 mr-2 text-muted-foreground" />
                  <span className="font-medium">
                    {c.lastName} {c.firstName}
                  </span>
                  <span className="ml-2 text-xs text-muted-foreground truncate">
                    {c.partner} · {c.city} · {c.billingStatus}
                  </span>
                </CommandItem>
              ))}
            </CommandGroup>
          </>
        )}

        {matchedUsers.length > 0 && (
          <>
            <CommandSeparator />
            <CommandGroup heading={`Utilisateurs (${matchedUsers.length})`}>
              {matchedUsers.map((u) => (
                <CommandItem
                  key={u.id}
                  value={`user-${u.username}-${u.fullName}`}
                  onSelect={() => go("/users")}
                >
                  <UsersIcon className="h-4 w-4 mr-2 text-muted-foreground" />
                  <span className="font-medium">{u.fullName}</span>
                  <span className="ml-2 text-xs text-muted-foreground truncate">
                    @{u.username} · {u.role} · {u.team}
                  </span>
                </CommandItem>
              ))}
            </CommandGroup>
          </>
        )}

        {matchedEvents.length > 0 && (
          <>
            <CommandSeparator />
            <CommandGroup heading={`Calendrier (${matchedEvents.length})`}>
              {matchedEvents.map((e) => (
                <CommandItem
                  key={e.id}
                  value={`event-${e.id}-${e.title}-${e.agent}`}
                  onSelect={() => go("/calendar")}
                >
                  <CalIcon className="h-4 w-4 mr-2 text-muted-foreground" />
                  <span className="font-medium">{e.title}</span>
                  <span className="ml-2 text-xs text-muted-foreground truncate">
                    {e.date} {e.time} · {e.type} · {e.agent}
                  </span>
                </CommandItem>
              ))}
            </CommandGroup>
          </>
        )}

        {!q && (canViewProspects || canViewContracts || canViewDispatch) && (
          <>
            <CommandSeparator />
            <CommandGroup heading="Actions rapides">
              {canViewProspects && (
                <CommandItem onSelect={() => go("/prospects")}>
                  <Plus className="h-4 w-4 mr-2" /> Nouveau prospect
                </CommandItem>
              )}
              {canViewContracts && (
                <CommandItem onSelect={() => go("/contracts")}>
                  <Plus className="h-4 w-4 mr-2" /> Nouveau contrat
                </CommandItem>
              )}
              {canViewDispatch && (
                <CommandItem onSelect={() => go("/dispatch")}>
                  <Sparkles className="h-4 w-4 mr-2" /> Dispatch des leads
                </CommandItem>
              )}
            </CommandGroup>
          </>
        )}
      </CommandList>
    </CommandDialog>
  );
}
