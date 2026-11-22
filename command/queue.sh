#!/bin/bash

# Start Laravel Queue Worker for AI Agent
# This script starts the queue worker to process AI Agent messages

echo "========================================"
echo "   Starting Laravel Queue Worker"
echo "========================================"
echo ""

echo "Queue: ai-agent"
echo "Connection: database"
echo ""

echo "Press Ctrl+C to stop the worker"
echo ""

cd ../

php artisan queue:work --queue=default,ai-agent --sleep=3 --tries=3 --max-time=3600
