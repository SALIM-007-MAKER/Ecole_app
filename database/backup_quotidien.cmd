@echo off
rem Sauvegarde quotidienne verifiee par restauration (tache planifiee Windows "Scolaris sauvegarde").
rem Journal : storage\logs\backup-auto.log ; code de sortie different de 0 = sauvegarde non validee.
cd /d "%~dp0\.."
"C:\wamp64\bin\php\php8.2.29\php.exe" database\backup.php --keep=14 --verify-restore >> storage\logs\backup-auto.log 2>&1
exit /b %ERRORLEVEL%
