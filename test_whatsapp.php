<?php
/**
 * Script de Prueba y Validación de WhatsApp
 * 
 * Ejecutar desde terminal:
 * php test_whatsapp.php
 */

// Color outputs para terminal
class Colors {
    const RESET = "\033[0m";
    const GREEN = "\033[92m";
    const RED = "\033[91m";
    const YELLOW = "\033[93m";
    const BLUE = "\033[94m";
}

echo "\n" . str_repeat("=", 70) . "\n";
echo Colors::BLUE . "TEST Y VALIDACIÓN DE WHATSAPP - SISTEMA DE CITAS" . Colors::RESET . "\n";
echo str_repeat("=", 70) . "\n\n";

// 1. Cargar funciones
echo Colors::YELLOW . "[1/5]" . Colors::RESET . " Cargando funciones de WhatsApp...\n";
try {
    require_once(__DIR__ . '/includes/whatsapp_functions.php');
    require_once(__DIR__ . '/includes/whatsapp_config.php');
    echo Colors::GREEN . "✓ Funciones cargadas correctamente\n\n" . Colors::RESET;
} catch (Exception $e) {
    echo Colors::RED . "✗ Error al cargar funciones: " . $e->getMessage() . "\n\n" . Colors::RESET;
    exit(1);
}

// 2. Validar configuración
echo Colors::YELLOW . "[2/5]" . Colors::RESET . " Validando configuración...\n";
$config_result = obtenerConfiguracionWhatsApp();

if ($config_result['success']) {
    $config = $config_result['config'];
    echo Colors::GREEN . "✓ Configuración cargada\n" . Colors::RESET;
    echo "  - Access Token: " . (substr($config['access_token'], 0, 10) . '...' . substr($config['access_token'], -5)) . "\n";
    echo "  - Phone ID: " . $config['phone_number_id'] . "\n";
    echo "  - Template: " . $config['template_name'] . "\n";
    echo "  - Language: " . $config['language_code'] . "\n";
    echo "  - Enabled: " . ($config['enabled'] ? 'SI' : 'NO') . "\n\n";
} else {
    echo Colors::RED . "✗ Error en configuración: " . $config_result['message'] . "\n\n" . Colors::RESET;
    echo Colors::YELLOW . "SOLUCIÓN:\n" . Colors::RESET;
    echo "  1. Verifica que WHATSAPP_ACCESS_TOKEN esté configurado\n";
    echo "  2. Verifica que WHATSAPP_PHONE_NUMBER_ID esté configurado\n";
    echo "  3. Usa variables de entorno o archivo .env\n\n";
    exit(1);
}

// 3. Validar datos de prueba
echo Colors::YELLOW . "[3/5]" . Colors::RESET . " Validando datos de prueba...\n";

$test_data = [
    'telefono' => '5215551234567',  // Cambiar a número real para test real
    'nombre' => 'Juan García',
    'modalidad' => 'Radiografía',
    'fecha' => '2025-11-20',
    'hora' => '14:30:00',
    'descripcion' => 'Estudios de tórax sin contraste'
];

$validacion = validarDatosWhatsApp(
    $test_data['telefono'],
    $test_data['nombre'],
    $test_data['modalidad'],
    $test_data['fecha'],
    $test_data['hora'],
    $test_data['descripcion']
);

if ($validacion['success']) {
    echo Colors::GREEN . "✓ Datos de prueba válidos\n" . Colors::RESET;
    echo "  - Nombre: " . $test_data['nombre'] . "\n";
    echo "  - Modalidad: " . $test_data['modalidad'] . "\n";
    echo "  - Fecha: " . $test_data['fecha'] . "\n";
    echo "  - Hora: " . $test_data['hora'] . "\n";
    echo "  - Teléfono: " . $validacion['telefono_limpio'] . "\n\n";
} else {
    echo Colors::RED . "✗ Error en validación: " . $validacion['message'] . "\n\n" . Colors::RESET;
    exit(1);
}

// 4. Preparar variables
echo Colors::YELLOW . "[4/5]" . Colors::RESET . " Preparando variables para plantilla...\n";

$variables = prepararVariablesTemplate(
    $test_data['nombre'],
    $test_data['modalidad'],
    $test_data['fecha'],
    $test_data['hora'],
    $test_data['descripcion']
);

echo Colors::GREEN . "✓ Variables preparadas:\n" . Colors::RESET;
echo "  {{1}} (Nombre): " . $variables[0] . "\n";
echo "  {{2}} (Modalidad): " . $variables[1] . "\n";
echo "  {{3}} (Fecha): " . $variables[2] . "\n";
echo "  {{4}} (Hora): " . $variables[3] . "\n";
echo "  {{5}} (Descripción): " . $variables[4] . "\n\n";

// 5. Enviar mensaje de prueba
echo Colors::YELLOW . "[5/5]" . Colors::RESET . " Enviando mensaje de prueba...\n";
echo Colors::YELLOW . "NOTA: Cambia el número de teléfono en test_data para usar un número real\n\n" . Colors::RESET;

// Mensaje de advertencia si es número de prueba
if ($test_data['telefono'] === '5215551234567') {
    echo Colors::YELLOW . "⚠ USANDO NÚMERO DE PRUEBA (5215551234567)\n";
    echo "  Para enviar un mensaje REAL, edita este archivo y cambia el número\n\n" . Colors::RESET;
}

$resultado = enviarMensajeWhatsAppCita(
    $test_data['telefono'],
    $test_data['nombre'],
    $test_data['modalidad'],
    $test_data['fecha'],
    $test_data['hora'],
    $test_data['descripcion']
);

echo "\n" . str_repeat("-", 70) . "\n";

if ($resultado['success']) {
    echo Colors::GREEN . "✓ MENSAJE ENVIADO EXITOSAMENTE\n" . Colors::RESET;
    echo "  Mensaje: " . $resultado['message'] . "\n";
    if (isset($resultado['response']['messages']) && !empty($resultado['response']['messages'])) {
        echo "  ID del mensaje: " . $resultado['response']['messages'][0]['id'] . "\n";
    }
} else {
    echo Colors::RED . "✗ ERROR AL ENVIAR MENSAJE\n" . Colors::RESET;
    echo "  Error: " . $resultado['message'] . "\n";
    if (isset($resultado['response']['error'])) {
        $error = $resultado['response']['error'];
        echo "  Código: " . ($error['code'] ?? 'N/A') . "\n";
        echo "  Detalles: " . ($error['message'] ?? 'N/A') . "\n";
    }
}

echo str_repeat("-", 70) . "\n";
echo "\n" . Colors::BLUE . "RESUMEN DE CONFIGURACIÓN:\n" . Colors::RESET;
echo "- Archivo config: " . (file_exists(__DIR__ . '/includes/whatsapp_config.php') ? Colors::GREEN . "✓ EXISTE" . Colors::RESET : Colors::RED . "✗ NO EXISTE" . Colors::RESET) . "\n";
echo "- Archivo funciones: " . (file_exists(__DIR__ . '/includes/whatsapp_functions.php') ? Colors::GREEN . "✓ EXISTE" . Colors::RESET : Colors::RED . "✗ NO EXISTE" . Colors::RESET) . "\n";
echo "- Guardiar cita modificado: " . (strpos(file_get_contents(__DIR__ . '/guardar_cita.php'), 'whatsapp_functions') !== false ? Colors::GREEN . "✓ INTEGRADO" . Colors::RESET : Colors::RED . "✗ NO INTEGRADO" . Colors::RESET) . "\n";
echo "- Guardar reserva modificado: " . (strpos(file_get_contents(__DIR__ . '/guardar_reserva_cliente.php'), 'whatsapp_functions') !== false ? Colors::GREEN . "✓ INTEGRADO" . Colors::RESET : Colors::RED . "✗ NO INTEGRADO" . Colors::RESET) . "\n";

echo "\n" . str_repeat("=", 70) . "\n";
echo Colors::BLUE . "FIN DEL TEST\n" . Colors::RESET;
echo str_repeat("=", 70) . "\n\n";

?>
