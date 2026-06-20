@echo off
REM ===========================================================================
REM CRM Internet - Quick Start Script for Windows
REM ===========================================================================

setlocal enabledelayedexpansion

echo.
echo ===============================================
echo   CRM Internet - Setup & Build Script
echo ===============================================
echo.

REM Check if Node.js is installed
node --version >nul 2>&1
if errorlevel 1 (
    echo ERROR: Node.js is not installed!
    echo Please install Node.js from: https://nodejs.org/
    pause
    exit /b 1
)

echo [✓] Node.js detected: 
node --version
echo.

REM Display menu
:menu
echo.
echo Select an option:
echo.
echo   1 - Install dependencies (npm install)
echo   2 - Start development server (npm run dev)
echo   3 - Build for production (npm run build)
echo   4 - Format code (npm run format)
echo   5 - Lint code (npm run lint)
echo   6 - Clean build files (remove dist/)
echo   7 - Exit
echo.

set /p choice="Enter your choice (1-7): "

if "%choice%"=="1" goto install
if "%choice%"=="2" goto dev
if "%choice%"=="3" goto build
if "%choice%"=="4" goto format
if "%choice%"=="5" goto lint
if "%choice%"=="6" goto clean
if "%choice%"=="7" goto exit
echo Invalid choice, try again.
goto menu

:install
echo.
echo [*] Installing dependencies...
call npm install
if errorlevel 1 (
    echo ERROR: npm install failed!
    pause
    exit /b 1
)
echo [✓] Dependencies installed successfully!
echo.
pause
goto menu

:dev
echo.
echo [*] Starting development server...
echo.
echo    Open your browser and navigate to: http://localhost:5173/
echo    Press Ctrl+C to stop the server.
echo.
call npm run dev
goto menu

:build
echo.
echo [*] Building for production...
call npm run build
if errorlevel 1 (
    echo ERROR: Build failed!
    pause
    exit /b 1
)
echo [✓] Build completed successfully!
echo.
echo Build output folder: dist/
echo Ready to upload to server!
echo.
pause
goto menu

:format
echo.
echo [*] Formatting code...
call npm run format
echo [✓] Code formatted!
echo.
pause
goto menu

:lint
echo.
echo [*] Linting code...
call npm run lint
echo.
pause
goto menu

:clean
echo.
echo [*] Removing build files...
if exist dist\ (
    rmdir /s /q dist
    echo [✓] dist/ folder removed!
) else (
    echo    dist/ folder not found
)
echo.
pause
goto menu

:exit
echo.
echo Goodbye!
exit /b 0
