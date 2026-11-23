@echo off
rem Builds a cPanel upload zip into the dist folder.
powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0deploy\cpanel-build.ps1"
if errorlevel 1 (echo. & echo BUILD FAILED) else (explorer "%~dp0dist")
pause
