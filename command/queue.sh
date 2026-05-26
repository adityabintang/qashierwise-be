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

php artisan queue:work --queue=ai-agent --tries=3 --timeout=60 --sleep=3
