' Startet Praxis-Ruf ohne sichtbares Konsolenfenster.
' Verknuepfung auf diese Datei in den Autostart-Ordner legen (Win + R -> shell:startup).
' Beenden ueber den Task-Manager, Prozess "Node.js".

Option Explicit

Dim shell, dateien, ordner
Set shell = CreateObject("WScript.Shell")
Set dateien = CreateObject("Scripting.FileSystemObject")

ordner = dateien.GetParentFolderName(WScript.ScriptFullName)
shell.CurrentDirectory = ordner

' 0 = kein Fenster, False = nicht auf das Ende warten
shell.Run "node.exe """ & ordner & "\server.js""", 0, False
