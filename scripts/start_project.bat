@echo off
setlocal

set "ROOT=%~dp0.."
for %%I in ("%ROOT%") do set "ROOT=%%~fI"

set "APP_DIR=%ROOT%\library"
set "PHP_EXE=C:\Users\srava\Downloads\php-8.5.1-nts-Win32-vs17-x64\php.exe"
set "NGROK_EXE=C:\Users\srava\Downloads\ngrok-v3-stable-windows-amd64\ngrok.exe"
set "PORT=8000"

echo ========================================
echo Online Library Demo Launcher
echo ========================================
echo [INFO] Project root: %ROOT%
echo [INFO] App folder  : %APP_DIR%
echo [INFO] Port        : %PORT%
echo.

if not exist "%APP_DIR%\index.php" (
    echo [ERROR] App entry file not found: %APP_DIR%\index.php
    pause
    exit /b 1
)

if not exist "%PHP_EXE%" (
    echo [ERROR] PHP executable not found: %PHP_EXE%
    pause
    exit /b 1
)

if not exist "%NGROK_EXE%" (
    echo [ERROR] ngrok executable not found: %NGROK_EXE%
    pause
    exit /b 1
)

echo [INFO] Starting PHP server...
start "PHP Server" cmd /k "cd /d ""%APP_DIR%"" && ""%PHP_EXE%"" -S localhost:%PORT%"

echo [INFO] Waiting for PHP server to initialize...
timeout /t 3 /nobreak >nul

echo [INFO] Starting ngrok tunnel...
start "ngrok" cmd /k """%NGROK_EXE%"" http %PORT%"

echo.
echo [INFO] Local app : http://localhost:%PORT%
echo [INFO] ngrok will show the public URL in its own window.
echo [INFO] Leave the PHP and ngrok windows open while demoing.
echo.
pause
