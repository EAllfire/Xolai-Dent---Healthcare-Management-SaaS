<?php
/**
 * Script de Prueba REAL de WhatsApp
 * Utiliza número real para enviar mensaje de prueba
 * 
 * Ejecutar desde terminal:
 * php test_whatsapp_real.php
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
echo Colors::BLUE . "TEST REAL DE WHATSAPP - SISTEMA DE CITAS" . Colors::RESET . "\n";
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
    exit(1);
}

// 3. Datos REALES - ⭐ CAMBIAR NÚMERO AQUÍ PARA TEST REAL
echo Colors::YELLOW . "[3/5]" . Colors::RESET . " Configurando datos de prueba REAL...\n\n";

echo Colors::YELLOW . "⚠️  IMPORTANTE:\n" . Colors::RESET;
echo "   Tu número registrado: +526251281200\n";
echo "   Para enviar a otro número, primero debe ser agregado como ADMIN\n";
echo "   en tu cuenta de WhatsApp Business en Meta\n\n";

$test_data = [
    'telefono' => '526251281200',  // ⭐ Tu número real (sin el +)
    'nombre' => 'Hospital Angeles',
    'modalidad' => 'Radiografía',
    'fecha' => date('Y-m-d', strtotime('+2 days')),  // Dos días desde hoy
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
    echo Colors::GREEN . "✓ Datos válidos\n" . Colors::RESET;
    echo "  - Nombre: " . $test_data['nombre'] . "\n";
    echo "  - Modalidad: " . $test_data['modalidad'] . "\n";
    echo "  - Fecha: " . $test_data['fecha'] . " (en 2 días)\n";
    echo "  - Hora: " . $test_data['hora'] . "\n";
    echo "  - Teléfono: +" . $validacion['telefono_limpio'] . "\n\n";
} else {
    echo Colors::RED . "✗ Error en validación: " . $validacion['message'] . "\n\n" . Colors::RESET;
    exit(1);
}

// 4. Preparar variables
echo Colors::YELLOW . "[4/5]" . Colors::RESET . " Preparando mensaje...\n";

$variables = prepararVariablesTemplate(
    $test_data['nombre'],
    $test_data['modalidad'],
    $test_data['fecha'],
    $test_data['hora'],
    $test_data['descripcion']
);

echo Colors::GREEN . "✓ Variables de plantilla:\n" . Colors::RESET;
echo "  {{1}} Nombre: " . $variables[0] . "\n";
echo "  {{2}} Modalidad: " . $variables[1] . "\n";
echo "  {{3}} Fecha: " . $variables[2] . "\n";
echo "  {{4}} Hora: " . $variables[3] . "\n";
echo "  {{5}} Descripción: " . $variables[4] . "\n\n";

// 5. Enviar mensaje REAL
echo Colors::YELLOW . "[5/5]" . Colors::RESET . " Enviando mensaje WhatsApp REAL...\n\n";

echo Colors::YELLOW . "🚀 ENVIANDO A: +" . $validacion['telefono_limpio'] . "\n";
echo "   Plantilla: citaagendada\n";
echo "   Status: Enviando...\n\n" . Colors::RESET;

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
    echo Colors::GREEN . "✅ MENSAJE ENVIADO EXITOSAMENTE\n" . Colors::RESET;
    echo "  Mensaje: " . $resultado['message'] . "\n";
    if (isset($resultado['response']['messages']) && !empty($resultado['response']['messages'])) {
        echo "  ID del mensaje: " . $resultado['response']['messages'][0]['id'] . "\n";
        echo "\n" . Colors::GREEN . "✓ El mensaje ha sido entregado a WhatsApp\n" . Colors::RESET;
        echo "  Verifica tu teléfono para confirmar recepción\n";
    }
} else {
    echo Colors::RED . "❌ ERROR AL ENVIAR MENSAJE\n" . Colors::RESET;
    echo "  Error: " . $resultado['message'] . "\n";
    if (isset($resultado['response']['error'])) {
        $error = $resultado['response']['error'];
        echo "  Código: " . ($error['code'] ?? 'N/A') . "\n";
        echo "  Detalles: " . ($error['message'] ?? 'N/A') . "\n";
        
        // Sugerencias basadas en el error
        if (isset($error['code'])) {
            echo "\n" . Colors::YELLOW . "SUGERENCIAS:\n" . Colors::RESET;
            switch ($error['code']) {
                case 190:
                    echo "  ⚠️  Token de acceso inválido o expirado\n";
                    echo "     Genera uno nuevo en: https://developers.facebook.com/apps/\n";
                    break;
                case 191:
                    echo "  ⚠️  Permiso requerido\n";
                    echo "     Verifica que tu token tenga permisos: whatsapp_business_messaging\n";
                    break;
                case 1104:
                    echo "  ⚠️  Número de teléfono no es un número de WhatsApp Business válido\n";
                    echo "     O el número no está agregado como admin\n";
                    break;
                default:
                    echo "  Consulta la documentación de Meta Cloud API para el código: " . $error['code'] . "\n";
            }
        }
    }
}

echo str_repeat("-", 70) . "\n";

echo "\n" . Colors::BLUE . "INFORMACIÓN DEL SISTEMA:\n" . Colors::RESET;
echo "- Archivo config: " . (file_exists(__DIR__ . '/includes/whatsapp_config.php') ? Colors::GREEN . "✓ OK" . Colors::RESET : Colors::RED . "✗ FALTA" . Colors::RESET) . "\n";
echo "- Archivo funciones: " . (file_exists(__DIR__ . '/includes/whatsapp_functions.php') ? Colors::GREEN . "✓ OK" . Colors::RESET : Colors::RED . "✗ FALTA" . Colors::RESET) . "\n";
echo "- Integración guardar_cita.php: " . (strpos(file_get_contents(__DIR__ . '/guardar_cita.php'), 'whatsapp_functions') !== false ? Colors::GREEN . "✓ OK" . Colors::RESET : Colors::RED . "✗ FALTA" . Colors::RESET) . "\n";
echo "- Integración guardar_reserva_cliente.php: " . (strpos(file_get_contents(__DIR__ . '/guardar_reserva_cliente.php'), 'whatsapp_functions') !== false ? Colors::GREEN . "✓ OK" . Colors::RESET : Colors::RED . "✗ FALTA" . Colors::RESET) . "\n";

echo "\n" . str_repeat("=", 70) . "\n";
echo Colors::BLUE . "✅ TEST COMPLETADO\n" . Colors::RESET;
echo str_repeat("=", 70) . "\n\n";

?>
