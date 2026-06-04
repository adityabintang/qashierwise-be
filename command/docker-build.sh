#!/bin/bash

# Docker build script with retry logic
# Usage: ./docker-build.sh [image-name] [max-retries]

IMAGE_NAME=${1:-qashierwise}
MAX_RETRIES=${2:-3}
RETRY_COUNT=0

echo "Building Docker image: $IMAGE_NAME"
echo "Max retries: $MAX_RETRIES"

cd ../

while [ $RETRY_COUNT -lt $MAX_RETRIES ]; do
    echo ""
    echo "Build attempt $((RETRY_COUNT + 1)) of $MAX_RETRIES..."
    
    # Try to build with BuildKit for better caching
    DOCKER_BUILDKIT=1 docker build \
        --progress=plain \
        --network=host \
        -t $IMAGE_NAME:latest \
        .
    
    # Check if build was successful
    if [ $? -eq 0 ]; then
        echo ""
        echo "✓ Build successful!"
        exit 0
    fi
    
    RETRY_COUNT=$((RETRY_COUNT + 1))
    
    if [ $RETRY_COUNT -lt $MAX_RETRIES ]; then
        echo ""
        echo "✗ Build failed. Retrying in 10 seconds..."
        sleep 10
    fi
done

echo ""
echo "✗ Build failed after $MAX_RETRIES attempts"
exit 1
