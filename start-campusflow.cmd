@echo off
cd /d "%~dp0"
start "" node proxy-server.js
timeout /t 1 /nobreak >nul
start "" "http://localhost:3000"
