<?php
/**
 * Helpers de clonage des pièces jointes lors des conversions
 * lead → opportunité → contrat (manuelles ET auto-actions de pipeline).
 *
 * Stratégie : on n'écrit PAS de nouveau fichier sur disque. On insère une
 * nouvelle ligne dans crminternet_attachments avec un nouvel id, le couple
 * (entity, entity_id) cible, mais on garde storage_path / filename / mime /
 * size identiques. N rows logiques peuvent référencer le même fichier
 * physique. Le DELETE de attachments.php doit donc protéger l'unlink via
 * attachment_storage_path_in_use().
 *
 * Idempotent : si une row avec le même storage_path existe déjà sur la
 * cible, on n'insère pas (utile en cas de re-conversion après revert).
 */

if (!function_exists('attachment_clone_entity')) {
    function attachment_clone_entity(PDO $db, string $fromEntity, string $fromId, string $toEntity, string $toId): int {
        if ($fromEntity === '' || $fromId === '' || $toEntity === '' || $toId === '') return 0;
        if ($fromEntity === $toEntity && $fromId === $toId) return 0;

        try {
            $src = $db->prepare('SELECT * FROM crminternet_attachments WHERE entity = :e AND entity_id = :id');
            $src->execute([':e' => $fromEntity, ':id' => $fromId]);
            $rows = $src->fetchAll();
        } catch (Throwable $e) { return 0; }
        if (!$rows) return 0;

        $exists = $db->prepare('SELECT 1 FROM crminternet_attachments
                                 WHERE entity = :e AND entity_id = :id AND storage_path = :sp LIMIT 1');
        $ins = $db->prepare('INSERT INTO crminternet_attachments
            (id, entity, entity_id, filename, mime_type, size_bytes, storage_path, uploaded_by, created_at)
            VALUES (:id, :e, :ei, :fn, :mt, :sz, :sp, :ub, NOW())');

        $copied = 0;
        foreach ($rows as $r) {
            $exists->execute([':e' => $toEntity, ':id' => $toId, ':sp' => $r['storage_path']]);
            if ($exists->fetchColumn()) continue;

            $newId = 'AT-' . substr(bin2hex(random_bytes(6)), 0, 10);
            try {
                $ins->execute([
                    ':id' => $newId,
                    ':e'  => $toEntity,
                    ':ei' => $toId,
                    ':fn' => $r['filename'],
                    ':mt' => $r['mime_type'],
                    ':sz' => (int)$r['size_bytes'],
                    ':sp' => $r['storage_path'],
                    ':ub' => $r['uploaded_by'],
                ]);
                $copied++;
            } catch (Throwable $e) { /* best effort */ }
        }
        return $copied;
    }
}

/**
 * Clone attachments from all upstream entities (prospect, then opportunity)
 * onto a target record (contract, migration, etc.).
 */
if (!function_exists('attachment_clone_lineage')) {
    function attachment_clone_lineage(
        PDO $db,
        string $toEntity,
        string $toId,
        ?string $prospectId,
        ?string $opportunityId = null
    ): int {
        $copied = 0;
        if ($prospectId !== null && $prospectId !== '') {
            $copied += attachment_clone_entity($db, 'prospect', $prospectId, $toEntity, $toId);
        }
        if ($opportunityId !== null && $opportunityId !== '' && $opportunityId !== $toId) {
            $copied += attachment_clone_entity($db, 'opportunity', $opportunityId, $toEntity, $toId);
        }
        return $copied;
    }
}

/**
 * Sanitize uploaded filename while preserving "[CIN Recto] …" category prefixes.
 */
if (!function_exists('attachment_sanitize_filename')) {
    function attachment_sanitize_filename(string $name): string
    {
        $name = basename(str_replace("\0", '', $name));
        if ($name === '') {
            return 'file';
        }
        if (preg_match('/^(\[[^\]]+\]\s*)(.+)$/', $name, $m)) {
            $prefix = preg_replace('/[^\[\]A-Za-z0-9 _-]/', '_', $m[1]);
            $rest = preg_replace('/[^A-Za-z0-9._-]/', '_', $m[2]);
            $out = $prefix . ($rest !== '' ? $rest : 'file');
            return $out !== '' ? $out : 'file';
        }
        $safe = preg_replace('/[^A-Za-z0-9._-]/', '_', $name);
        return $safe !== '' ? $safe : 'file';
    }
}

/**
 * Fix filenames mangled by the old sanitizer (_CIN_Recto__… → [CIN Recto] …).
 */
if (!function_exists('attachment_repair_legacy_category_filenames')) {
    function attachment_repair_legacy_category_filenames(PDO $db): int
    {
        $map = [
            '_CIN_Recto__' => '[CIN Recto] ',
            '_CIN_Verso__' => '[CIN Verso] ',
            '_Contrat_TT__' => '[Contrat TT] ',
            '_Contrat_TOPNET__' => '[Contrat TOPNET] ',
            '_CGV__' => '[CGV] ',
        ];
        $fixed = 0;
        $upd = $db->prepare('UPDATE crminternet_attachments SET filename = :fn WHERE id = :id');
        foreach ($db->query('SELECT id, filename FROM crminternet_attachments') as $row) {
            $fn = (string) ($row['filename'] ?? '');
            foreach ($map as $bad => $good) {
                if (strncmp($fn, $bad, strlen($bad)) !== 0) {
                    continue;
                }
                $upd->execute([
                    ':fn' => $good . substr($fn, strlen($bad)),
                    ':id' => (string) $row['id'],
                ]);
                $fixed++;
                break;
            }
        }
        return $fixed;
    }
}

if (!function_exists('attachment_storage_path_in_use')) {
    /**
     * Renvoie true si une autre row (id != $excludeId) référence le même
     * storage_path. Utilisé par DELETE pour décider si on peut unlink le
     * fichier disque.
     */
    function attachment_storage_path_in_use(PDO $db, string $path, string $excludeId): bool {
        if ($path === '') return false;
        $s = $db->prepare('SELECT 1 FROM crminternet_attachments
                            WHERE storage_path = :sp AND id <> :id LIMIT 1');
        $s->execute([':sp' => $path, ':id' => $excludeId]);
        return (bool)$s->fetchColumn();
    }
}
