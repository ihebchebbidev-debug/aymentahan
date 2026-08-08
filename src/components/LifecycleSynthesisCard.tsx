import { useEffect, useMemo, useState } from "react";
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from "@/components/ui/card";
import { api, API_ENABLED } from "@/lib/api";
import type { Prospect, Opportunity, Contract, Migration, ProspectType } from "@/lib/types";

/**
 * Carte "Synthèse" unifiée : affiche, depuis n'importe quelle fiche
 * (prospect / opportunité / contrat / migration), le cycle de vie complet
 * du client — Prospect, Opportunité, Contrat, Migration — avec pour chaque
 * étape : statut, créé le / par, modifié le / par.
 * Les étapes inexistantes affichent "—".
 */

export type LifecycleEntity = "prospect" | "opportunity" | "contract" | "migration";

type Props = {
  entity: LifecycleEntity;
  id: string;
  /** Identifiants déjà connus par la page appelante (accélère la résolution). */
  prospectId?: string | null;
  opportunityId?: string | null;
  contractId?: string | null;
  migrationId?: string | null;
};

type Stamp = { at: string | null; by: string | null };

const DASH = "—";

function fmt(d?: string | null) {
  if (!d) return DASH;
  const t = new Date(d);
  if (Number.isNaN(t.getTime())) return String(d);
  return t.toLocaleString("fr-FR", {
    day: "2-digit", month: "2-digit", year: "numeric",
    hour: "2-digit", minute: "2-digit",
  });
}

async function get<T>(url: string): Promise<T | null> {
  if (!API_ENABLED) return null;
  try { return await api<T>(url); } catch { return null; }
}

/** Dernière modification : croise activity_log et audit_log (le plus récent gagne). */
async function fetchLastModified(entity: LifecycleEntity, id: string): Promise<Stamp> {
  const actQs = entity === "contract"
    ? `?contractId=${encodeURIComponent(id)}&limit=1`
    : `?entity=${entity}&entity_id=${encodeURIComponent(id)}&limit=1`;
  const [a, b] = await Promise.all([
    get<{ activity: Array<{ user?: string | null; timestamp?: string | null }> }>(`/activity.php${actQs}`),
    get<{ logs: Array<{ user?: string | null; createdAt?: string | null }> }>(
      `/audit_log.php?entity=${entity}&entity_id=${encodeURIComponent(id)}&limit=1&sort=desc`,
    ),
  ]);
  const act = a?.activity?.[0] ?? null;
  const aud = b?.logs?.[0] ?? null;
  const actTs = act?.timestamp ? new Date(act.timestamp).getTime() : 0;
  const audTs = aud?.createdAt ? new Date(aud.createdAt).getTime() : 0;
  if (!actTs && !audTs) return { at: null, by: null };
  return actTs >= audTs
    ? { at: act?.timestamp ?? null, by: act?.user ?? null }
    : { at: aud?.createdAt ?? null, by: aud?.user ?? null };
}

type Block = {
  key: LifecycleEntity;
  label: string;
  id: string | null;
  typeLabel?: string | null;   // prospect uniquement
  status: string | null;
  createdAt: string | null;
  createdBy: string | null;
  updatedAt: string | null;
  updatedBy: string | null;
};

export function LifecycleSynthesisCard(props: Props) {
  const [blocks, setBlocks] = useState<Block[] | null>(null);

  const seed = useMemo(
    () => ({
      prospectId: props.prospectId ?? (props.entity === "prospect" ? props.id : null),
      opportunityId: props.opportunityId ?? (props.entity === "opportunity" ? props.id : null),
      contractId: props.contractId ?? (props.entity === "contract" ? props.id : null),
      migrationId: props.migrationId ?? (props.entity === "migration" ? props.id : null),
    }),
    [props.entity, props.id, props.prospectId, props.opportunityId, props.contractId, props.migrationId],
  );

  useEffect(() => {
    let cancel = false;
    (async () => {
      let prospectId = seed.prospectId;
      let opportunityId = seed.opportunityId;
      let contractId = seed.contractId;
      let migrationId = seed.migrationId;

      // Étape 1 : charger ce qu'on connaît déjà pour remonter/descendre la chaîne.
      const [c0, m0] = await Promise.all([
        contractId ? get<{ contract: Contract }>(`/contracts.php?id=${encodeURIComponent(contractId)}`) : null,
        migrationId ? get<{ migration: Migration }>(`/migrations.php?id=${encodeURIComponent(migrationId)}`) : null,
      ]);
      opportunityId = opportunityId ?? c0?.contract?.opportunityId ?? m0?.migration?.opportunityId ?? null;
      prospectId = prospectId ?? (c0?.contract as any)?.prospectId ?? m0?.migration?.prospectId ?? null;

      let opp: Opportunity | null = null;
      if (opportunityId) {
        opp = (await get<{ opportunity: Opportunity }>(`/opportunities.php?id=${encodeURIComponent(opportunityId)}`))?.opportunity ?? null;
        prospectId = prospectId ?? opp?.prospectId ?? null;
        contractId = contractId ?? opp?.contractId ?? null;
        migrationId = migrationId ?? opp?.migrationId ?? null;
      }

      let prospect: Prospect | null = null;
      if (prospectId) {
        prospect = (await get<{ prospect: Prospect }>(`/prospects.php?id=${encodeURIComponent(prospectId)}`))?.prospect ?? null;
        if (!opportunityId) {
          opportunityId = prospect?.opportunityId ?? prospect?.lastOpportunityId ?? null;
          if (opportunityId) {
            opp = (await get<{ opportunity: Opportunity }>(`/opportunities.php?id=${encodeURIComponent(opportunityId)}`))?.opportunity ?? null;
            contractId = contractId ?? opp?.contractId ?? null;
            migrationId = migrationId ?? opp?.migrationId ?? null;
          }
        }
      }

      const [cRes, mRes] = await Promise.all([
        contractId && !c0 ? get<{ contract: Contract }>(`/contracts.php?id=${encodeURIComponent(contractId)}`) : null,
        migrationId && !m0 ? get<{ migration: Migration }>(`/migrations.php?id=${encodeURIComponent(migrationId)}`) : null,
      ]);
      const contract = c0?.contract ?? cRes?.contract ?? null;
      const migration = m0?.migration ?? mRes?.migration ?? null;

      // Type prospect (libellé).
      let typeLabel: string | null = null;
      if (prospect?.typeId) {
        const t = await get<{ types: ProspectType[] }>("/prospect_types.php");
        typeLabel = t?.types?.find((x) => String(x.id) === String(prospect!.typeId))?.name ?? null;
      }

      // Dernières modifications.
      const [pm, om, cm, mm] = await Promise.all([
        prospect ? fetchLastModified("prospect", prospect.id) : Promise.resolve({ at: null, by: null }),
        opp ? fetchLastModified("opportunity", opp.id) : Promise.resolve({ at: null, by: null }),
        contract ? fetchLastModified("contract", contract.id) : Promise.resolve({ at: null, by: null }),
        migration ? fetchLastModified("migration", migration.id) : Promise.resolve({ at: null, by: null }),
      ]);

      const next: Block[] = [
        {
          key: "prospect", label: "Prospect", id: prospect?.id ?? null,
          typeLabel: typeLabel ?? null,
          status: prospect?.status ?? null,
          createdAt: prospect?.createdAt ?? null,
          createdBy: prospect?.createdBy ?? null,
          updatedAt: pm.at ?? prospect?.updatedAt ?? null,
          updatedBy: pm.by ?? prospect?.updatedBy ?? null,
        },
        {
          key: "opportunity", label: "Opportunité", id: opp?.id ?? null,
          status: opp?.stage ?? null,
          createdAt: opp?.createdAt ?? null,
          createdBy: opp?.createdBy ?? null,
          updatedAt: om.at ?? opp?.updatedAt ?? null,
          updatedBy: om.by ?? opp?.updatedBy ?? null,
        },
        {
          key: "contract", label: "Contrat", id: contract?.id ?? null,
          status: contract?.billingStatus ?? null,
          createdAt: contract?.createdAt ?? contract?.signatureDate ?? null,
          createdBy: contract?.createdBy ?? null,
          updatedAt: cm.at ?? contract?.updatedAt ?? null,
          updatedBy: cm.by ?? contract?.updatedBy ?? null,
        },
        {
          key: "migration", label: "Migration", id: migration?.id ?? null,
          status: migration?.workflowStatus ?? null,
          createdAt: migration?.createdAt ?? null,
          createdBy: migration?.createdBy ?? null,
          updatedAt: mm.at ?? migration?.updatedAt ?? null,
          updatedBy: mm.by ?? migration?.updatedBy ?? null,
        },
      ];
      if (!cancel) setBlocks(next);
    })();
    return () => { cancel = true; };
  }, [seed]);

  return (
    <Card className="shadow-elegant">
      <CardHeader className="pb-3">
        <CardTitle className="text-base">Synthèse</CardTitle>
        <CardDescription>Traçabilité complète du cycle client</CardDescription>
      </CardHeader>
      <CardContent className="space-y-4 text-sm">
        {(blocks ?? [
          { key: "prospect", label: "Prospect" },
          { key: "opportunity", label: "Opportunité" },
          { key: "contract", label: "Contrat" },
          { key: "migration", label: "Migration" },
        ] as Block[]).map((b) => (
          <div key={b.key} className="space-y-1.5">
            <div className="flex items-center justify-between gap-2">
              <span className="font-semibold">{b.label}</span>
              {b.id ? (
                <span className="text-[11px] text-muted-foreground truncate max-w-[45%]" title={b.id}>{b.id}</span>
              ) : null}
            </div>
            {b.key === "prospect" && (
              <Row label="Type prospect" value={b.typeLabel || DASH} />
            )}
            <Row label="Statut" value={b.status || DASH} />
            <Row label="Créé le" value={fmt(b.createdAt)} />
            <Row label="Créé par" value={b.createdBy || DASH} />
            <Row label="Modifié le" value={fmt(b.updatedAt)} />
            <Row label="Modifié par" value={b.updatedBy || DASH} />
          </div>
        ))}
      </CardContent>
    </Card>
  );
}

function Row({ label, value }: { label: string; value: React.ReactNode }) {
  return (
    <div className="flex items-center justify-between gap-2">
      <span className="text-muted-foreground">{label}</span>
      <span className="font-medium truncate max-w-[60%] text-right" title={typeof value === "string" ? value : undefined}>{value}</span>
    </div>
  );
}
