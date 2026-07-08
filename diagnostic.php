<?php
echo "===== DIAGNÓSTICO DE CONFIGURACIÓN =====\n\n";

// Test 1: Check if we can include db.php
echo "1. Included db.php: ";
try {
    require_once __DIR__ . '/includes/db.php';
    echo "✓ Success\n";
} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
}

// Test 2: Check database connection
echo "2. Database connection: ";
if ($conn && $conn->connect_error === null) {
    echo "✓ Connected\n";
} else {
    echo "✗ Error: " . ($conn->connect_error ?? "Unknown error") . "\n";
}

// Test 3: Check if we can query pacientes
echo "3. Query portal_pacientes table: ";
if ($conn) {
    $result = $conn->query("SELECT COUNT(*) as total FROM portal_pacientes");
    if ($result) {
        $row = $result->fetch_assoc();
        echo "✓ Success (Total: " . $row['total'] . " pacientes)\n";
    } else {
        echo "✗ Error: " . $conn->error . "\n";
    }
}

// Test 4: Check URL detection
echo "4. Running context:\n";
echo "   - __DIR__: " . __DIR__ . "\n";
echo "   - $_SERVER['SERVER_SOFTWARE']: " . ($_SERVER['SERVER_SOFTWARE'] ?? 'NOT SET') . "\n";
echo "   - MAMP detected: " . (isLocalMAMP() ? 'YES' : 'NO') . "\n";

// Test 5: Check files exist
echo "5. Critical files:\n";
echo "   - citas/pacientes_json.php: " . (file_exists(__DIR__ . '/citas/pacientes_json.php') ? '✓' : '✗') . "\n";
echo "   - includes/db.php: " . (file_exists(__DIR__ . '/includes/db.php') ? '✓' : '✗') . "\n";

function isLocalMAMP() {
    if (strpos($_SERVER['SERVER_SOFTWARE'] ?? '', 'MAMP') !== false) return true;
    if (file_exists('/Applications/MAMP/bin/mysql')) return true;
    if (defined('MAMP_PHP')) return true;
    if (strpos(__DIR__, '/Applications/MAMP/htdocs') !== false) return true;
    return false;
}
?>
