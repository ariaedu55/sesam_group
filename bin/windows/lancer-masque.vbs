' Lance un script .cmd sans ouvrir de fenetre (taches planifiees SESAM).
If WScript.Arguments.Count > 0 Then
    CreateObject("WScript.Shell").Run """" & WScript.Arguments(0) & """", 0, True
End If
