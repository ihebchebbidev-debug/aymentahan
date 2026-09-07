<?php
// =====================================================================
// Public lead intake — receives leads pushed by external sites (e.g. the
// ttshop.pro landing page) and creates them as prospects of type "Site"
// (PT-a252bd6def).
//
// Unlike prospects.php this endpoint is NOT behind require_auth()/JWT: the
// caller is a server-to-server script with no CRM user session. It is
// instead protected by a shared secret sent in the X-Intake-Key header.
//
// POST https://erp.ttshop.pro/code_source/backend/php/lead_intake.php
// Header: X-Intake-Key: <LEAD_INTAKE_SECRET>
// Body (JSON): { name, firstName, phone, cin, governorate, need, email,
//                source, formId, page, referrer, lang,
//                utm_source, utm_medium, utm_campaign, utm_content, utm_term,
//                gclid, fbclid, leadUuid, ... }
// Only `name` (-> lastName) is required, mirroring prospects.php's own rule.
// =====================================================================
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/list_query_helpers.php'; // schema_ensure_once()

require_method('POST');

$key = $_SERVER['HTTP_X_INTAKE_KEY'] ?? ($_GET['key'] ?? '');
if (!is_string($key) || $key === '' || !hash_equals(LEAD_INTAKE_SECRET, $key)) {
    fail('Unauthorized', 401);
}

$db = (new Database())->getConnection();

// created_by/updated_by are added at runtime by prospects.php on older
// installs (not in the base CREATE TABLE) — ensure they exist here too so
// this endpoint doesn't depend on prospects.php having run first.
schema_ensure_once('lead_intake_prospects', '20260817', function () use ($db) {
    try { $db->exec("ALTER TABLE crminternet_prospects ADD COLUMN created_by VARCHAR(80) NULL"); } catch (Throwable $e) {}
});

$in = json_input();

$name = trim((string)($in['name'] ?? $in['nom'] ?? ''));
if ($name === '') fail('name requis', 422, ['field' => 'name']);

/** Finds (or creates) the "Site" prospect type used for automatic website leads. Idempotent. */
function lead_intake_site_web_type_id(PDO $db): string {
    // Production already has this type as PT-a252bd6def / name "Site" — prefer it by id,
    // fall back to matching by name, and only create it if neither exists (fresh install).
    $s = $db->prepare('SELECT id FROM crminternet_prospect_types WHERE id = :id OR name = :n LIMIT 1');
    $s->execute([':id' => 'PT-a252bd6def', ':n' => 'Site']);
    $id = $s->fetchColumn();
    if ($id) return (string)$id;
    $id = 'PT-' . substr(bin2hex(random_bytes(6)), 0, 10);
    try {
        $db->prepare('INSERT INTO crminternet_prospect_types
            (id, name, description, color, position, active, created_at)
            VALUES (:id, :n, :d, :c, :p, 1, NOW())')
           ->execute([
               ':id' => $id, ':n' => 'Site',
               ':d'  => 'Leads collectés automatiquement depuis le formulaire du site web (landing page)',
               ':c'  => 'primary', ':p' => 5,
           ]);
    } catch (Throwable $e) {
        // Race with a concurrent request creating the same row — re-select by name.
        $s2 = $db->prepare('SELECT id FROM crminternet_prospect_types WHERE name = :n LIMIT 1');
        $s2->execute([':n' => 'Site']);
        $id = $s2->fetchColumn() ?: $id;
    }
    return (string)$id;
}
$typeId = lead_intake_site_web_type_id($db);

$firstStatus = 'Nouveau';
try {
    $st = $db->query('SELECT name FROM crminternet_lead_stages ORDER BY position LIMIT 1')->fetchColumn();
    if ($st) $firstStatus = (string)$st;
} catch (Throwable $e) {}

// The landing page's "need" (besoin) field and campaign-attribution metadata
// have no dedicated columns on crminternet_prospects, so they're folded into
// the free-text comment — same place a human agent would jot call notes.
$commentParts = [];
$need = trim((string)($in['need'] ?? ''));
if ($need !== '') $commentParts[] = 'Besoin: ' . $need;
$attribution = [];
foreach (['utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'utm_term', 'gclid', 'fbclid'] as $k) {
    if (!empty($in[$k])) $attribution[] = $k . '=' . $in[$k];
}
if ($attribution) $commentParts[] = 'Attribution: ' . implode('; ', $attribution);
if (!empty($in['formId']))   $commentParts[] = 'Formulaire: ' . $in['formId'];
if (!empty($in['page']))     $commentParts[] = 'Page: ' . $in['page'];
if (!empty($in['referrer'])) $commentParts[] = 'Référent: ' . $in['referrer'];
if (!empty($in['leadUuid'])) $commentParts[] = 'Lead landing UUID: ' . $in['leadUuid'];
$comment = $commentParts ? implode("\n", $commentParts) : null;

$governorate = strtoupper(trim((string)($in['governorate'] ?? $in['gouvernorat'] ?? '')));
$phone = preg_replace('/\D/', '', (string)($in['phone'] ?? ''));
$cin   = trim((string)($in['cin'] ?? ''));

$id = 'P-' . substr(bin2hex(random_bytes(6)), 0, 8);

$ins = $db->prepare('INSERT INTO crminternet_prospects
    (id, civility, last_name, first_name, phone, cin, email, source, status, created_at, created_by, city, gouvernorat, comment, check_valeur, outcome, type_id)
    VALUES (:id, :civ, :ln, :fn, :ph, :cin, :em, :src, :st, :ca, :cb, :city, :gov, :cm, :cv, :oc, :tid)');
$ins->execute([
    ':id'   => $id,
    ':civ'  => 'M',
    ':ln'   => mb_substr($name, 0, 120),
    ':fn'   => mb_substr(trim((string)($in['firstName'] ?? $in['prenom'] ?? '')), 0, 120),
    ':ph'   => substr($phone, 0, 40),
    ':cin'  => $cin !== '' ? mb_substr($cin, 0, 40) : null,
    ':em'   => mb_substr(trim((string)($in['email'] ?? '')), 0, 160),
    ':src'  => 'siteweb',
    ':st'   => $firstStatus,
    ':ca'   => date('Y-m-d'),
    ':cb'   => 'siteweb-bot',
    ':city' => mb_substr($governorate, 0, 120),
    ':gov'  => mb_substr($governorate, 0, 120),
    ':cm'   => $comment,
    ':cv'   => 'pending',
    ':oc'   => 'pending',
    ':tid'  => $typeId,
]);

audit_log($db, ['username' => 'siteweb-bot', 'role' => 'system'], 'prospect.create', 'prospect', $id, [
    'via'    => 'lead_intake',
    'formId' => $in['formId'] ?? null,
]);

ok(['id' => $id, 'typeId' => $typeId, 'message' => 'Prospect créé']);
