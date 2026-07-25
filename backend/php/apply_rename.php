<?php
require_once __DIR__ . '/config.php';
$db = (new Database())->getConnection();

try {
    // Check if 'refuse' exists
    $stmt = $db->query("SELECT id FROM crminternet_lead_stages WHERE name = 'refuse'");
    if ($stmt->fetch()) {
        // Update the stage name
        $db->exec("UPDATE crminternet_lead_stages SET name = 'deja migré' WHERE name = 'refuse'");
        // Cascade to prospects
        $db->exec("UPDATE crminternet_prospects SET status = 'deja migré' WHERE status = 'refuse'");
        echo "Successfully renamed 'refuse' to 'deja migré'.\n";
    } else {
        echo "'refuse' not found, might have been already renamed.\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
