<?php
// debug_login.php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

echo "<h1>🔍 Depuración de login</h1>";

// 1. Mostrar configuración de la base de datos
echo "<h3>Configuración de BD:</h3>";
echo "<pre>";
echo "DB_HOST: " . DB_HOST . "\n";
echo "DB_NAME: " . DB_NAME . "\n";
echo "DB_USER: " . DB_USER . "\n";
echo "DB_PASS: " . (DB_PASS ? '****' : 'vacío') . "\n";
echo "</pre>";

// 2. Probar conexión a base de datos
try {
    $db = getDB();
    echo "<p style='color:green'>✅ Conexión a BD exitosa.</p>";

    // 3. Verificar la tabla 'usuarios'
    $stmt = $db->query("SHOW TABLES LIKE 'usuarios'");
    if ($stmt->rowCount() == 0) {
        echo "<p style='color:red'>❌ La tabla 'usuarios' no existe en la base de datos.</p>";
        exit;
    } else {
        echo "<p style='color:green'>✅ La tabla 'usuarios' existe.</p>";
    }

    // 4. Verificar columnas de la tabla
    $columns = $db->query("SHOW COLUMNS FROM usuarios")->fetchAll(PDO::FETCH_COLUMN);
    echo "<p>Columnas encontradas: " . implode(', ', $columns) . "</p>";

    // 5. Buscar al usuario admin
    $stmt = $db->prepare("SELECT * FROM usuarios WHERE correo = ?");
    $testEmail = 'admin@tesa.edu.ec';
    $stmt->execute([$testEmail]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        echo "<p style='color:red'>❌ Usuario 'admin@tesa.edu.ec' no encontrado.</p>";
        // Mostrar todos los correos existentes para depurar
        $all = $db->query("SELECT correo FROM usuarios")->fetchAll(PDO::FETCH_COLUMN);
        echo "<p>Correos en la tabla: " . implode(', ', $all) . "</p>";
        exit;
    }

    echo "<p style='color:green'>✅ Usuario encontrado:</p>";
    echo "<pre>";
    print_r($user);
    echo "</pre>";

    // 6. Verificar contraseña
    $password_test = 'admin123';
    if (password_verify($password_test, $user['password'])) {
        echo "<p style='color:green'>✅ La contraseña 'admin123' es CORRECTA.</p>";
    } else {
        echo "<p style='color:red'>❌ La contraseña 'admin123' NO es correcta.</p>";
        echo "<p>Hash almacenado: " . $user['password'] . "</p>";
    }

    // 7. Verificar si el usuario está activo
    if ($user['activo'] == 1) {
        echo "<p style='color:green'>✅ El usuario está activo.</p>";
    } else {
        echo "<p style='color:red'>❌ El usuario NO está activo.</p>";
    }

} catch (Exception $e) {
    echo "<p style='color:red'>❌ Error de BD: " . $e->getMessage() . "</p>";
}
?>