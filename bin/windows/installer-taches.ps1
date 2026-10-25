# Installe (ou met à jour) les tâches planifiées SESAM dans le Planificateur de tâches Windows.
# Usage : powershell -ExecutionPolicy Bypass -File bin\windows\installer-taches.ps1
# Les tâches tournent quand la session Windows est ouverte, sans fenêtre, et écrivent dans storage\logs\taches.log.
# Pour les retirer : Unregister-ScheduledTask -TaskPath "\SESAM\" -TaskName * -Confirm:$false

$dir = Split-Path -Parent $MyInvocation.MyCommand.Path
$launcher = Join-Path $dir 'lancer-masque.vbs'

function Install-SesamTask([string]$name, [string]$script, $trigger, [string]$description) {
    $cmd = Join-Path $dir $script
    $action = New-ScheduledTaskAction -Execute 'wscript.exe' -Argument ('"' + $launcher + '" "' + $cmd + '"') -WorkingDirectory $dir
    $settings = New-ScheduledTaskSettingsSet -StartWhenAvailable -DontStopIfGoingOnBatteries -AllowStartIfOnBatteries -ExecutionTimeLimit (New-TimeSpan -Minutes 30) -MultipleInstances IgnoreNew
    Register-ScheduledTask -TaskPath '\SESAM\' -TaskName $name -Action $action -Trigger $trigger -Settings $settings -Description $description -Force | Out-Null
    Write-Output ("Tâche installée : \SESAM\" + $name)
}

# Rappels du matin (programme du jour, échéances, impayés, essais, suivi agence…) : chaque jour à 7 h.
Install-SesamTask 'Rappels du matin' 'notifications-quotidiennes.cmd' (New-ScheduledTaskTrigger -Daily -At '07:00') 'SESAM : notifications quotidiennes (bin/notifications-quotidiennes.php)'

# Rappels aux clients (rendez-vous la veille / 3 h avant) : toutes les 10 minutes.
$every10 = New-ScheduledTaskTrigger -Once -At (Get-Date).Date -RepetitionInterval (New-TimeSpan -Minutes 10) -RepetitionDuration (New-TimeSpan -Days 3650)
Install-SesamTask 'Rappels clients' 'rappels-clients.cmd' $every10 'SESAM : envoi des rappels clients dus (bin/rappels-clients.php)'

# Relances de loyer Immo : chaque jour à 8 h.
Install-SesamTask 'Relances loyers' 'immo-relances.cmd' (New-ScheduledTaskTrigger -Daily -At '08:00') 'SESAM : relances automatiques des loyers (bin/immo-relances.php)'
