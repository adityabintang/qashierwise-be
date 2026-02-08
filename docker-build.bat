@echo off
REM Docker build script with retry logic for Windows
REM Usage: docker-build.bat [image-name] [max-retries]

setlocal enabledelayedexpansion

set IMAGE_NAME=%1
if "%IMAGE_NAME%"=="" set IMAGE_NAME=qashierwise

set MAX_RETRIES=%2
if "%MAX_RETRIES%"=="" set MAX_RETRIES=3

set RETRY_COUNT=0

echo Building Docker image: %IMAGE_NAME%
echo Max retries: %MAX_RETRIES%

:retry
echo.
echo Build attempt %RETRY_COUNT% of %MAX_RETRIES%...

REM Try to build with BuildKit for better caching
set DOCKER_BUILDKIT=1
docker build --progress=plain --network=host -t %IMAGE_NAME%:latest .

if %ERRORLEVEL% EQU 0 (
    echo.
    echo Build successful!
    exit /b 0
)

set /a RETRY_COUNT+=1

if %RETRY_COUNT% LSS %MAX_RETRIES% (
    echo.
    echo Build failed. Retrying in 10 seconds...
    timeout /t 10 /nobreak >nul
    goto retry
)

echo.
echo Build failed after %MAX_RETRIES% attempts
exit /b 1
