<?php
session_start();
require_once '../includes/auth.php';
require_once '../includes/db.php';

header('Content-Type: application/json');

if (!puedeRealizar('gestionar_usuarios')) {
    http_response_code(403);
    echo json_encode(['success'=>false,'error'=>'Acceso denegado']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success'=>false,'error'=>'Método no permitido']);
    exit;
}

$modalidad_id = intval($_POST['modalidad_id'] ?? 0);
if ($modalidad_id <= 0) {
    echo json_encode(['success'=>false,'error'=>'modalidad_id inválido']);
    exit;
}

if (!isset($_FILES['imagen']) || $_FILES['imagen']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success'=>false,'error'=>'Archivo no recibido']);
    exit;
}

$uploaddir = __DIR__ . '/../images/modalidades/';
if (!is_dir($uploaddir)) mkdir($uploaddir, 0755, true);

$file = $_FILES['imagen'];
$ext = pathinfo($file['name'], PATHINFO_EXTENSION);
$allowed = ['jpg','jpeg','png','webp'];
if (!in_array(strtolower($ext), $allowed)) {
    echo json_encode(['success'=>false,'error'=>'Tipo de archivo no permitido']);
    exit;
}

$filename = 'modalidad_' . $modalidad_id . '_' . time() . '.' . $ext;
$target = $uploaddir . $filename;
if (!move_uploaded_file($file['tmp_name'], $target)) {
    echo json_encode(['success'=>false,'error'=>'Error al mover el archivo']);
    exit;
}

// ensure readable by web server
@chmod($target, 0644);

// public relative path under project root
$relpath = 'images/modalidades/' . $filename;

// Optionally skip updating DB if caller set store_db=0 (useful if you prefer only filesystem storage)
$store_db = 1;
if (isset($_POST['store_db'])) { $store_db = intval($_POST['store_db']); }
if ($store_db) {
    // Verify 'imagen' column exists before attempting UPDATE
    $colCheck = $conn->query("SHOW COLUMNS FROM agenda_modalidades LIKE 'imagen'");
    if (!$colCheck || $colCheck->num_rows === 0) {
        // Column missing: return a helpful JSON error instead of throwing
        http_response_code(500);
        echo json_encode(['success'=>false,'error' => "Columna 'imagen' no encontrada en la tabla agenda_modalidades. Ejecute la migración para agregarla (ALTER TABLE agenda_modalidades ADD COLUMN imagen VARCHAR(255) DEFAULT NULL)."]);
        exit;
    }

    $stmt = $conn->prepare("UPDATE agenda_modalidades SET imagen = ? WHERE id = ?");
    if (!$stmt) {
        http_response_code(500);
        echo json_encode(['success'=>false,'error' => 'Error al preparar la consulta: ' . $conn->error]);
        exit;
    }
    $stmt->bind_param('si', $relpath, $modalidad_id);
    if (!$stmt->execute()) {
        http_response_code(500);
        echo json_encode(['success'=>false,'error'=>'Error al actualizar DB: '.$stmt->error]);
        exit;
    }
}

// normalize public path with application base prefix (avoid including /citas/ in the URL)
$scriptDir = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');
if (basename($scriptDir) === 'citas') {
    $appBase = rtrim(dirname($scriptDir), '/');
} else {
    $appBase = $scriptDir;
}
if ($appBase === '' || $appBase === '/') {
    $outPath = '/' . ltrim($relpath, '/');
} else {
    $outPath = $appBase . '/' . ltrim($relpath, '/');
}
echo json_encode(['success'=>true,'imagen'=> $outPath, 'stored_in_db' => ($store_db?true:false)]);
exit;

?>