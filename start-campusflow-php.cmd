@echo off
cd /d "%~dp0"
if not exist campusflow.sqlite echo La base SQLite sera creee au premier appel.
start "" http://localhost:8000/index.html
php -S localhost:8000 router.php
