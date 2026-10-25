@echo off
rem Tache planifiee SESAM : lance bin\immo-relances.php et ajoute la sortie a storage\logs\taches.log
cd /d "%~dp0..\.."
set PHP=C:\wamp64\bin\php\php8.3.28\php.exe
echo [%date% %time%] immo-relances >> storage\logs\taches.log
"%PHP%" bin\immo-relances.php >> storage\logs\taches.log 2>&1
