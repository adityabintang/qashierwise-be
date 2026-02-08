@echo off
REM Start Laravel Queue Worker (All Queues)
REM This script starts queue worker to process all jobs including OTP emails

echo ========================================
echo   Starting Laravel Queue Worker
echo ========================================
echo.

echo Processing all queues...
echo Connection: database
echo.

echo Press Ctrl+C to stop the worker
echo.

php artisan queue:work --tries=3 --timeout=90 --sleep=3

pause
