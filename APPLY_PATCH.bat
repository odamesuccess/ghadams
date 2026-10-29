@echo off
setlocal EnableExtensions EnableDelayedExpansion
cd /d "%~dp0"

echo ============================================================
echo  GhanaDAMS - Mandatory 2FA for ALL users
echo  APPLY PATCH + AUTOMATIC NPM BUILD
echo ============================================================
echo.

if not exist "artisan" (
  echo [ERROR] artisan was not found.
  echo Copy APPLY_PATCH.bat and PATCH_FILES into the GhanaDAMS project root first.
  pause
  exit /b 1
)

if not exist "PATCH_FILES\app\Http\Middleware\EnsureTwoFactorAuthenticationEnabled.php" (
  echo [ERROR] PATCH_FILES is missing or incomplete.
  pause
  exit /b 1
)

for /f "tokens=1-4 delims=/ " %%a in ("%date%") do set D=%%d%%b%%c
for /f "tokens=1-3 delims=:., " %%a in ("%time%") do set T=%%a%%b%%c
set T=%T: =0%
set BACKUP=_patch_backups\mandatory_2fa_all_users_%D%_%T%

mkdir "%BACKUP%\bootstrap" >nul 2>&1
mkdir "%BACKUP%\resources\js\layouts\settings" >nul 2>&1
mkdir "%BACKUP%\resources\js\pages\settings" >nul 2>&1

copy /Y "bootstrap\app.php" "%BACKUP%\bootstrap\app.php" >nul
copy /Y "resources\js\layouts\settings\Layout.vue" "%BACKUP%\resources\js\layouts\settings\Layout.vue" >nul
copy /Y "resources\js\pages\settings\TwoFactor.vue" "%BACKUP%\resources\js\pages\settings\TwoFactor.vue" >nul

if errorlevel 1 (
  echo [ERROR] Backup failed. No patch was applied.
  pause
  exit /b 1
)

echo [OK] Backup created: %BACKUP%

xcopy /E /I /Y "PATCH_FILES\*" ".\" >nul
if errorlevel 1 goto :fail

echo [OK] Patch files installed.

echo.
echo Clearing Laravel caches...
php artisan optimize:clear
if errorlevel 1 goto :fail

echo.
echo Building frontend...
npm run build
if errorlevel 1 goto :fail

echo.
echo ============================================================
echo [SUCCESS] Mandatory 2FA patch applied and frontend built.
echo Backup: %BACKUP%
echo ============================================================
pause
exit /b 0

:fail
echo.
echo [ERROR] Patch/build did not complete successfully.
echo Your original key files are preserved in:
echo %BACKUP%
echo Restore from that folder if necessary.
pause
exit /b 1
