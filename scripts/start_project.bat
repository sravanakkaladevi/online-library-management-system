@echo off
setlocal

set "ROOT=%~dp0.."
for %%I in ("%ROOT%") do set "ROOT=%%~fI"

set "APP_DIR=%ROOT%\library"
set "PHP_EXE=C:\Users\srava\Downloads\php-8.5.1-nts-Win32-vs17-x64\php.exe"
set "NGROK_EXE="
for /f "delims=" %%I in ('where ngrok 2^>nul') do (
    if not defined NGROK_EXE set "NGROK_EXE=%%I"
)
if not defined NGROK_EXE set "NGROK_EXE=C:\Users\srava\Downloads\ngrok-v3-stable-windows-amd64\ngrok.exe"
set "HOST=127.0.0.1"
set "PORT=8000"
set "LOCAL_URL=http://%HOST%:%PORT%"

echo ========================================
echo Online Library Demo Launcher
echo ========================================
echo [INFO] Project root: %ROOT%
echo [INFO] App folder  : %APP_DIR%
echo [INFO] Port        : %PORT%
echo.
echo Step 1: Change directory to library
echo         cd /d "%APP_DIR%"
echo Step 2: Run PHP server and ngrok on the same port %PORT%
echo Step 3: Open the local app in your browser
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

cd /d "%APP_DIR%"
if errorlevel 1 (
    echo [ERROR] Failed to change directory to %APP_DIR%
    pause
    exit /b 1
)

echo [STEP 1] Current folder: %CD%
echo [STEP 2] Starting PHP server on %LOCAL_URL%...
echo [CMD] "%PHP_EXE%" -S %HOST%:%PORT%
start "PHP Server" /D "%APP_DIR%" "%PHP_EXE%" -S %HOST%:%PORT%

echo [INFO] Waiting for PHP server to initialize...
timeout /t 3 /nobreak >nul

echo [STEP 2] Starting ngrok tunnel for %LOCAL_URL%...
echo [CMD] "%NGROK_EXE%" http %LOCAL_URL%
start "ngrok" /D "%APP_DIR%" "%NGROK_EXE%" http %LOCAL_URL%

echo [INFO] Waiting before opening browser...
timeout /t 2 /nobreak >nul

echo [STEP 3] Opening %LOCAL_URL% in your browser...
start "" "%LOCAL_URL%"

echo.
echo [INFO] Local app : %LOCAL_URL%
echo [INFO] ngrok will show the public URL in its own window.
echo [INFO] The local app should open automatically in your browser.
echo [INFO] Leave the PHP and ngrok windows open while demoing.
echo.
pause
