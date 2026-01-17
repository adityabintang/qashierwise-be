# Docker Build Troubleshooting Guide

## Network Timeout Issues

Jika Anda mengalami error `ETIMEDOUT` saat build Docker, berikut beberapa solusi:

### Solusi 1: Gunakan Script Build dengan Retry

**Windows:**
```bash
docker-build.bat qashierwise 5
```

**Linux/Mac:**
```bash
chmod +x docker-build.sh
./docker-build.sh qashierwise 5
```

### Solusi 2: Build dengan Pre-built Assets

Jika network terus bermasalah, build assets di local terlebih dahulu:

```bash
# Install dependencies dan build di local
npm install
npm run build

# Build Docker tanpa npm install
docker build -f Dockerfile.prebuilt -t qashierwise:latest .
```

### Solusi 3: Gunakan NPM Mirror

Jika di Indonesia, gunakan npm mirror yang lebih cepat:

```bash
# Set npm registry ke mirror Indonesia
npm config set registry https://registry.npmmirror.com

# Atau gunakan Taobao mirror
npm config set registry https://registry.npm.taobao.org

# Build ulang
docker build -t qashierwise:latest .
```

### Solusi 4: Increase Docker Resources

Pastikan Docker memiliki resource yang cukup:

1. Buka Docker Desktop Settings
2. Resources → Advanced
3. Tingkatkan Memory ke minimal 4GB
4. Tingkatkan CPU ke minimal 2 cores

### Solusi 5: Build dengan Network Host Mode

```bash
docker build --network=host -t qashierwise:latest .
```

### Solusi 6: Use Docker BuildKit Cache

```bash
# Enable BuildKit
set DOCKER_BUILDKIT=1

# Build dengan cache mount
docker build --progress=plain -t qashierwise:latest .
```

### Solusi 7: Manual Build Steps

Jika semua gagal, build secara manual:

```bash
# 1. Build assets di local
npm install
npm run build

# 2. Buat Dockerfile.simple tanpa npm install
# 3. Build dengan Dockerfile.simple
docker build -f Dockerfile.simple -t qashierwise:latest .
```

## Optimisasi Dockerfile

Dockerfile sudah dioptimasi dengan:

1. **Layer Caching**: Package files di-copy terpisah untuk caching yang lebih baik
2. **Retry Logic**: NPM dikonfigurasi dengan retry otomatis
3. **Timeout Settings**: Timeout diperpanjang untuk koneksi lambat
4. **Offline Mode**: `--prefer-offline` untuk menggunakan cache jika tersedia

## Konfigurasi NPM yang Diterapkan

```bash
npm config set fetch-retry-mintimeout 20000
npm config set fetch-retry-maxtimeout 120000
npm config set fetch-retries 5
npm config set fetch-timeout 300000
```

## Troubleshooting Lainnya

### Error: "no space left on device"

```bash
# Clean Docker system
docker system prune -a --volumes

# Remove unused images
docker image prune -a
```

### Error: "composer install failed"

```bash
# Clear composer cache
composer clear-cache

# Rebuild
docker build --no-cache -t qashierwise:latest .
```

### Build Terlalu Lama

```bash
# Build dengan progress output
docker build --progress=plain -t qashierwise:latest .

# Atau gunakan BuildKit
DOCKER_BUILDKIT=1 docker build -t qashierwise:latest .
```

## Tips

1. **Gunakan WiFi yang stabil** saat build pertama kali
2. **Hindari VPN** yang bisa memperlambat koneksi
3. **Build di waktu off-peak** untuk koneksi lebih cepat
4. **Gunakan Docker BuildKit** untuk caching yang lebih baik
5. **Pre-build assets** jika network tidak stabil

## Kontak Support

Jika masih mengalami masalah, hubungi tim development dengan informasi:
- Error message lengkap
- Output dari `docker version`
- Output dari `docker info`
- Network speed test result
