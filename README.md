<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

<p align="center">
<a href="https://github.com/laravel/framework/actions"><img src="https://github.com/laravel/framework/workflows/tests/badge.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/dt/laravel/framework" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/v/laravel/framework" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/l/laravel/framework" alt="License"></a>
</p>

## About Laravel

Laravel is a web application framework with expressive, elegant syntax. We believe development must be an enjoyable and creative experience to be truly fulfilling. Laravel takes the pain out of development by easing common tasks used in many web projects, such as:

- [Simple, fast routing engine](https://laravel.com/docs/routing).
- [Powerful dependency injection container](https://laravel.com/docs/container).
- Multiple back-ends for [session](https://laravel.com/docs/session) and [cache](https://laravel.com/docs/cache) storage.
- Expressive, intuitive [database ORM](https://laravel.com/docs/eloquent).
- Database agnostic [schema migrations](https://laravel.com/docs/migrations).
- [Robust background job processing](https://laravel.com/docs/queues).
- [Real-time event broadcasting](https://laravel.com/docs/broadcasting).

Laravel is accessible, powerful, and provides tools required for large, robust applications.

## Learning Laravel

Laravel has the most extensive and thorough [documentation](https://laravel.com/docs) and video tutorial library of all modern web application frameworks, making it a breeze to get started with the framework. You can also check out [Laravel Learn](https://laravel.com/learn), where you will be guided through building a modern Laravel application.

If you don't feel like reading, [Laracasts](https://laracasts.com) can help. Laracasts contains thousands of video tutorials on a range of topics including Laravel, modern PHP, unit testing, and JavaScript. Boost your skills by digging into our comprehensive video library.

## Laravel Sponsors

We would like to extend our thanks to the following sponsors for funding Laravel development. If you are interested in becoming a sponsor, please visit the [Laravel Partners program](https://partners.laravel.com).

### Premium Partners

- **[Vehikl](https://vehikl.com)**
- **[Tighten Co.](https://tighten.co)**
- **[Kirschbaum Development Group](https://kirschbaumdevelopment.com)**
- **[64 Robots](https://64robots.com)**
- **[Curotec](https://www.curotec.com/services/technologies/laravel)**
- **[DevSquad](https://devsquad.com/hire-laravel-developers)**
- **[Redberry](https://redberry.international/laravel-development)**
- **[Active Logic](https://activelogic.com)**

## Contributing

Thank you for considering contributing to the Laravel framework! The contribution guide can be found in the [Laravel documentation](https://laravel.com/docs/contributions).

## Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

If you discover a security vulnerability within Laravel, please send an e-mail to Taylor Otwell via [taylor@laravel.com](mailto:taylor@laravel.com). All security vulnerabilities will be promptly addressed.

## Multi-Provider QRIS Payment System

This application includes a flexible multi-provider QRIS payment system that supports multiple payment providers:

### Supported Providers

- **Midtrans**: Established payment gateway with comprehensive features
- **Xendit**: Modern payment platform with competitive rates and fast settlement

### Key Features

- 🔄 **Multiple Provider Support**: Configure and switch between Midtrans and Xendit
- 🔒 **Secure Credential Storage**: All API credentials are encrypted at rest
- 🔔 **Webhook Integration**: Automatic payment notifications from providers
- 📊 **Transaction Tracking**: Monitor all QRIS transactions across providers
- 🔁 **Backward Compatible**: Existing Midtrans integrations continue to work seamlessly
- 🧪 **Comprehensive Testing**: Unit tests, property-based tests, and integration tests

### Quick Start

#### 1. Configure a Payment Provider

```bash
# Set up environment variables (optional)
XENDIT_API_KEY=your_xendit_secret_api_key
XENDIT_SECRET_KEY=your_xendit_webhook_verification_token
```

#### 2. Run Migrations

```bash
php artisan migrate
```

#### 3. Configure Provider via API

```bash
curl -X POST https://yourdomain.com/api/provider-credentials \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "provider": "xendit",
    "api_key": "xnd_development_your_api_key",
    "secret_key": "your_webhook_verification_token"
  }'
```

#### 4. Generate QRIS Code

```bash
curl -X POST https://yourdomain.com/api/qris/generate \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "amount": 50000,
    "order_id": "ORDER-2025-001"
  }'
```

### Documentation

Comprehensive documentation is available in the `docs/` directory:

- **[Xendit Integration Guide](docs/XENDIT_INTEGRATION.md)**: Complete guide for integrating Xendit
- **[Merchant Configuration Guide](docs/MERCHANT_CONFIGURATION_GUIDE.md)**: Step-by-step setup for merchants
- **[Webhook Setup and Testing](docs/WEBHOOK_SETUP_AND_TESTING.md)**: Webhook configuration and testing guide
- **[Multi-Provider Architecture](docs/MULTI_PROVIDER_ARCHITECTURE.md)**: Technical architecture documentation
- **[Error Handling](docs/ERROR_HANDLING.md)**: Error handling and troubleshooting guide
- **[Migration Guide](docs/MIGRATION_GUIDE.md)**: Guide for migrating from single to multi-provider

### Testing

Run the test suite:

```bash
# Run all tests
php artisan test

# Run specific test suites
php artisan test --testsuite=Unit
php artisan test --testsuite=Feature

# Run with coverage
php artisan test --coverage
```

### Architecture

The system uses a provider factory pattern with a common interface:

```
QrisService → ProviderFactory → PaymentProviderInterface
                                        ↓
                        ┌───────────────┴───────────────┐
                        ↓                               ↓
                MidtransProvider                XenditProvider
```

All providers implement `PaymentProviderInterface`, ensuring consistent behavior and making it easy to add new providers.

### Security

- **Encrypted Credentials**: All API keys are encrypted using AES-256-CBC
- **Webhook Verification**: All webhooks are verified using HMAC-SHA256 signatures
- **Audit Logging**: All credential access and API calls are logged
- **Error Sanitization**: Sensitive information is never exposed in error messages

### Support

For issues or questions:

- Check the [documentation](docs/)
- Review [error handling guide](docs/ERROR_HANDLING.md)
- Check application logs: `storage/logs/laravel.log`

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
