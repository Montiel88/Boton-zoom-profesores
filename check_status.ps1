Write-Host "=========================================" -ForegroundColor Cyan
Write-Host "    ESTADO DEL PROYECTO ZOOM MONITOR    " -ForegroundColor Cyan
Write-Host "=========================================" -ForegroundColor Cyan
Write-Host ""

# 1. Verificar archivos
Write-Host "📁 ARCHIVOS:" -ForegroundColor Yellow
$archivos = @("scripts/sync_zoom_history.php", "api/webhook.php", ".env")
foreach ($archivo in $archivos) {
    if (Test-Path $archivo) { Write-Host "✅ $archivo" -ForegroundColor Green }
    else { Write-Host "❌ $archivo" -ForegroundColor Red }
}

# 2. Verificar BD
Write-Host "`n🗄️  BASE DE DATOS:" -ForegroundColor Yellow
php -r "
require_once 'config/config.php';
require_once 'includes/functions.php';
\$db = getDB();
\$count = \$db->query('SELECT COUNT(*) FROM reuniones_historicas')->fetchColumn();
echo \"Reuniones: \$count\n\";
\$users = \$db->query('SELECT COUNT(DISTINCT usuario_id) FROM reuniones_historicas')->fetchColumn();
echo \"Profesores con datos: \$users\n\";
"

# 3. Verificar tarea programada
Write-Host "`n⏰ TAREA PROGRAMADA:" -ForegroundColor Yellow
$tarea = Get-ScheduledTask -TaskName "ZoomHistorySync" -ErrorAction SilentlyContinue
if ($tarea) { 
    Write-Host "✅ Tarea existe - Próxima ejecución: $($tarea.NextRunTime)" -ForegroundColor Green
} else { 
    Write-Host "❌ Tarea no creada" -ForegroundColor Red
}

Write-Host "`n=========================================" -ForegroundColor Cyan
