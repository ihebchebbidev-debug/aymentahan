<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/attachment_helpers.php';
$me = require_auth();
$db = (new Database())->getConnection();

// Repair legacy FK: older installs created
//   FOREIGN KEY (uploaded_by) REFERENCES crminternet_users(id)
// but this code stores the username (not the id) in uploaded_by, which makes
// every INSERT fail with SQLSTATE[23000] 1452. Drop the constraint once.
try {
    $fk = $db->query("SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'crminternet_attachments'
          AND COLUMN_NAME = 'uploaded_by'
          AND REFERENCED_TABLE_NAME IS NOT NULL")->fetchAll(PDO::FETCH_COLUMN);
    foreach ($fk as $name) {
        try { $db->exec("ALTER TABLE crminternet_attachments DROP FOREIGN KEY `" . $name . "`"); } catch (\Throwable $e) {}
    }
} catch (\Throwable $e) { /* best effort */ }

$method = $_SERVER['REQUEST_METHOD'];
$ENTITIES = ['prospect', 'opportunity', 'contract', 'migration'];
// Limite max par fichier (100 Ko). Aligné avec le frontend (les images sont
// compressées côté navigateur avant envoi). PDF, images et audios sont acceptés.
const ATTACHMENT_MAX_BYTES = 100 * 1024;
const ATTACHMENT_AUDIO_MAX_BYTES = 5 * 1024 * 1024;
const ATTACHMENT_ALLOWED_MIMES = [
  'application/pdf',
  'image/png','image/jpeg','image/jpg','image/webp','image/gif','image/bmp',
  'audio/mpeg','audio/mp3','audio/aac','audio/x-aac','audio/wav','audio/x-wav',
  'audio/ogg','audio/oga','audio/mp4','audio/x-m4a','audio/m4a','audio/flac','audio/webm',
];

$UPLOAD_DIR = __DIR__ . '/uploads';
if (!is_dir($UPLOAD_DIR)) @mkdir($UPLOAD_DIR, 0775, true);

function att_to_arr(array $r): array {
    return [
        'id'         => $r['id'],
        'entity'     => $r['entity'],
        'entityId'   => $r['entity_id'],
        'filename'   => $r['filename'],
        'mimeType'   => $r['mime_type'],
        'sizeBytes'  => (int)$r['size_bytes'],
        'url'        => 'attachments.php?download=' . urlencode($r['id']),
        'uploadedBy' => $r['uploaded_by'],
        'createdAt'  => $r['created_at'],
    ];
}

if ($method === 'GET') {
    if (isset($_GET['download'])) {
        $s = $db->prepare('SELECT * FROM crminternet_attachments WHERE id = :id');
        $s->execute([':id' => $_GET['download']]);
        $r = $s->fetch();
        if (!$r || !is_file($r['storage_path'])) fail('Fichier introuvable', 404);
        audit_log($db, $me, 'attachment.download', $r['entity'], $r['entity_id'], ['attachmentId' => $r['id'], 'filename' => $r['filename']]);
        $mime = (string)$r['mime_type'];
        $inline = !empty($_GET['inline']) && (strpos($mime, 'image/') === 0 || $mime === 'application/pdf' || strpos($mime, 'audio/') === 0);
        header('Content-Type: ' . $mime);
        header('Content-Length: ' . filesize($r['storage_path']));
        header('Content-Disposition: ' . ($inline ? 'inline' : 'attachment') . '; filename="' . addslashes($r['filename']) . '"');
        readfile($r['storage_path']);
        exit;
    }
    $entity = $_GET['entity'] ?? '';
    $eid    = $_GET['entity_id'] ?? '';
    if (!in_array($entity, $ENTITIES, true) || !$eid) fail('entity & entity_id requis', 422);
    $s = $db->prepare('SELECT * FROM crminternet_attachments WHERE entity=:e AND entity_id=:id ORDER BY created_at DESC');
    $s->execute([':e'=>$entity, ':id'=>$eid]);
    $attachments = array_map('att_to_arr', $s->fetchAll());
    ok(['attachments' => $attachments, 'crminternet_attachments' => $attachments]);
}

if ($method === 'POST') {
    $entity = $_POST['entity'] ?? '';
    $eid    = $_POST['entity_id'] ?? '';
    if (!in_array($entity, $ENTITIES, true) || !$eid) fail('entity & entity_id requis', 422);
    if (empty($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) fail('Fichier requis', 422);
    $f = $_FILES['file'];
    $detected = @mime_content_type($f['tmp_name']) ?: ($f['type'] ?? '');
    $detectedMime = strtolower((string)$detected);
    $ext = strtolower(pathinfo($f['name'] ?? '', PATHINFO_EXTENSION));
    $extensionMimeMap = [
        'aac' => 'audio/aac',
        'mp3' => 'audio/mpeg',
        'wav' => 'audio/wav',
        'ogg' => 'audio/ogg',
        'oga' => 'audio/ogg',
        'm4a' => 'audio/mp4',
        'mp4' => 'audio/mp4',
        'm4b' => 'audio/mp4',
        'flac' => 'audio/flac',
        'webm' => 'audio/webm',
        'pdf' => 'application/pdf',
        'png' => 'image/png',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'webp' => 'image/webp',
        'gif' => 'image/gif',
        'bmp' => 'image/bmp',
    ];
    if (isset($extensionMimeMap[$ext])) {
        $fallbackMime = $extensionMimeMap[$ext];
        if ($detectedMime === '' || $detectedMime === 'application/octet-stream' || $detectedMime === 'binary/octet-stream' || !in_array($detectedMime, ATTACHMENT_ALLOWED_MIMES, true)) {
            $detectedMime = $fallbackMime;
        }
    }
    $isAudio = strpos($detectedMime, 'audio/') === 0 || isset($extensionMimeMap[$ext]) && strpos($extensionMimeMap[$ext], 'audio/') === 0;
    $maxBytes = $isAudio ? ATTACHMENT_AUDIO_MAX_BYTES : ATTACHMENT_MAX_BYTES;
    if ($f['size'] > $maxBytes) {
        $limitLabel = $isAudio ? '5 Mo' : '100 Ko';
        fail('Fichier trop volumineux (max ' . $limitLabel . ')', 413);
    }
    if (!in_array($detectedMime, ATTACHMENT_ALLOWED_MIMES, true)) {
        fail('Type de fichier non autorisé (PDF, image ou audio uniquement)', 415);
    }

    $safeName = attachment_sanitize_filename((string) $f['name']);
    $id = 'AT-' . substr(bin2hex(random_bytes(6)), 0, 10);
    $sub = $GLOBALS['UPLOAD_DIR'] . '/' . $entity;
    if (!is_dir($sub)) @mkdir($sub, 0775, true);
    $dest = $sub . '/' . $id . '_' . $safeName;
    if (!move_uploaded_file($f['tmp_name'], $dest)) fail('Échec écriture fichier', 500);

    $mime = $detectedMime ?: (mime_content_type($dest) ?: ($f['type'] ?? 'application/octet-stream'));
    $s = $db->prepare('INSERT INTO crminternet_attachments (id,entity,entity_id,filename,mime_type,size_bytes,storage_path,uploaded_by)
                       VALUES (:id,:e,:ei,:fn,:mt,:sz,:sp,:u)');
    $s->execute([
        ':id'=>$id, ':e'=>$entity, ':ei'=>$eid, ':fn'=>$safeName, ':mt'=>$mime,
        ':sz'=>$f['size'], ':sp'=>$dest, ':u'=>$me['username'],
    ]);
    audit_log($db, $me, 'attachment.upload', $entity, $eid, ['attachmentId' => $id, 'filename' => $safeName, 'sizeBytes' => (int)$f['size'], 'mimeType' => $mime]);
    if ($entity === 'prospect') {
        try {
            $ins = $db->prepare("INSERT INTO crminternet_lead_actions (id, prospect_id, agent_username, type, comment, created_at)
                                 VALUES (:id, :pid, :u, 'note', :c, NOW())");
            $ins->execute([
                ':id' => 'LA-' . substr(bin2hex(random_bytes(6)), 0, 10),
                ':pid' => $eid,
                ':u' => $me['username'],
                ':c' => 'Pièce jointe ajoutée: ' . $safeName,
            ]);
        } catch (\Throwable $e) { /* best effort */ }
    }
    ok(['id'=>$id,'filename'=>$safeName,'sizeBytes'=>$f['size'],'mimeType'=>$mime,
        'url'=>'attachments.php?download='.urlencode($id)], 201);
}

if ($method === 'DELETE') {
    $id = $_GET['id'] ?? '';
    if (!$id) fail('id requis', 422);
    $s = $db->prepare('SELECT storage_path FROM crminternet_attachments WHERE id = :id');
    $s->execute([':id'=>$id]);
    $row = $s->fetch();
    if (!$row) fail('Introuvable', 404);
    $meta = $db->prepare('SELECT entity, entity_id, filename FROM crminternet_attachments WHERE id = :id');
    $meta->execute([':id'=>$id]);
    $info = $meta->fetch() ?: [];
    // N'efface le fichier physique que si plus aucune autre row ne le référence
    // (les conversions clonent les rows en partageant le même storage_path).
    if (!attachment_storage_path_in_use($db, $row['storage_path'], $id)) {
        @unlink($row['storage_path']);
    }
    $d = $db->prepare('DELETE FROM crminternet_attachments WHERE id = :id');
    $d->execute([':id'=>$id]);
    audit_log($db, $me, 'attachment.delete', $info['entity'] ?? null, $info['entity_id'] ?? null, ['attachmentId' => $id, 'filename' => $info['filename'] ?? null]);
    if (($info['entity'] ?? '') === 'prospect') {
        try {
            $ins = $db->prepare("INSERT INTO crminternet_lead_actions (id, prospect_id, agent_username, type, comment, created_at)
                                 VALUES (:id, :pid, :u, 'note', :c, NOW())");
            $ins->execute([
                ':id' => 'LA-' . substr(bin2hex(random_bytes(6)), 0, 10),
                ':pid' => $info['entity_id'],
                ':u' => $me['username'],
                ':c' => 'Pièce jointe supprimée: ' . ($info['filename'] ?? $id),
            ]);
        } catch (\Throwable $e) { /* best effort */ }
    }
    ok(['deleted' => $d->rowCount()]);
}

fail('Method not allowed', 405);
