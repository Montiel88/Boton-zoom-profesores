<?php
$hash = '$2y$10$5UFevQrYGVWjjwXfU66ABugFpoO4BMfHkx9yFR3V5GHCzxLHUvAEe';
$password = 'admin123';

if (password_verify($password, $hash)) {
    echo "✅ El hash verifica correctamente con 'admin123'.";
} else {
    echo "❌ El hash NO verifica. Posible error en el hash o en la función password_verify.";
}
?>