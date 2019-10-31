@ECHO OFF
setlocal DISABLEDELAYEDEXPANSION
SET BIN_TARGET=%~dp0/../crysalead/kahlan/bin/kahlan
php "%BIN_TARGET%" %*
