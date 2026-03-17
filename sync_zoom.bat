@echo off
REM sync_zoom.bat - Script para Windows Task Scheduler
REM Ejecuta la sincronización de Zoom datos

cd /d "%~dp0"

echo ===========================================
echo ZOOM SYNC - %DATE% %TIME%
echo ===========================================

REM Ejecutar sincronización
php scripts\sync_zoom_data.php >> logs\sync_log.txt 2>&1

echo Sync completado: %DATE% %TIME%
echo.
