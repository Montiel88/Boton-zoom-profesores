@echo off
cd C:\xampp\htdocs\zoom-monitor
C:\xampp\php\php.exe scripts/sync_zoom_history.php
echo Sincronizaci?n completada: %date% %time% >> sync_log.txt
