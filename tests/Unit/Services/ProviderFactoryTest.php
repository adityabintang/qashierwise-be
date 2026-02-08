<?php

namespace Tests\Unit\Services;

use App\Contracts\PaymentProviderInterface;
use App\Exceptions\UnsupportedProviderException;
use App\Services\PaymentProviders\DokuProvider;
use App\Services\PaymentProviders\DuitkuProvider;
use App\Services\PaymentProviders\MidtransProvider;
use App\Services\PaymentProviders\ProviderFactory;
use App\Services\PaymentProviders\XenditProvider;
use Tests\TestCase;

class ProviderFactoryTest extends TestCase
{
    private ProviderFactory $factory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->factory = new ProviderFactory;
    }

    public function test_creates_doku_provider(): void
    {
        $provider = $this->factory->make('doku');

        $this->assertInstanceOf(PaymentProviderInterface::class, $provider);
        $this->assertInstanceOf(DokuProvider::class, $provider);
        $this->assertEquals('doku', $provider->getProviderName());
    }

    public function test_creates_xendit_provider(): void
    {
        $provider = $this->factory->make('xendit');

        $this->assertInstanceOf(PaymentProviderInterface::class, $provider);
        $this->assertInstanceOf(XenditProvider::class, $provider);
        $this->assertEquals('xendit', $provider->getProviderName());
    }

    public function test_creates_midtrans_provider(): void
    {
        $provider = $this->factory->make('midtrans');

        $this->assertInstanceOf(PaymentProviderInterface::class, $provider);
        $this->assertInstanceOf(MidtransProvider::class, $provider);
        $this->assertEquals('midtrans', $provider->getProviderName());
    }

    public function test_creates_duitku_provider(): void
    {
        $provider = $this->factory->make('duitku');

        $this->assertInstanceOf(PaymentProviderInterface::class, $provider);
        $this->assertInstanceOf(DuitkuProvider::class, $provider);
        $this->assertEquals('duitku', $provider->getProviderName());
    }

    public function test_throws_exception_for_unsupported_provider(): void
    {
        $this->expectException(UnsupportedProviderException::class);
        $this->expectExceptionMessage('Unsupported payment provider: invalid');

        $this->factory->make('invalid');
    }

    public function test_provider_names_are_case_insensitive(): void
    {
        $provider1 = $this->factory->make('DOKU');
        $provider2 = $this->factory->make('Doku');
        $provider3 = $this->factory->make('doku');

        $this->assertInstanceOf(DokuProvider::class, $provider1);
        $this->assertInstanceOf(DokuProvider::class, $provider2);
        $this->assertInstanceOf(DokuProvider::class, $provider3);
    }

    public function test_get_supported_providers_returns_all_providers(): void
    {
        $providers = $this->factory->getSupportedProviders();

        $this->assertIsArray($providers);
        $this->assertCount(4, $providers);
        $this->assertContains('doku', $providers);
        $this->assertContains('xendit', $providers);
        $this->assertContains('midtrans', $providers);
        $this->assertContains('duitku', $providers);
    }

    public function test_is_supported_returns_true_for_valid_providers(): void
    {
        $this->assertTrue($this->factory->isSupported('doku'));
        $this->assertTrue($this->factory->isSupported('xendit'));
        $this->assertTrue($this->factory->isSupported('midtrans'));
        $this->assertTrue($this->factory->isSupported('duitku'));
        $this->assertTrue($this->factory->isSupported('DOKU')); // Case insensitive
    }

    public function test_is_supported_returns_false_for_invalid_providers(): void
    {
        $this->assertFalse($this->factory->isSupported('invalid'));
        $this->assertFalse($this->factory->isSupported('stripe'));
        $this->assertFalse($this->factory->isSupported(''));
    }

    public function test_all_providers_implement_required_interface(): void
    {
        foreach ($this->factory->getSupportedProviders() as $providerName) {
            $provider = $this->factory->make($providerName);

            $this->assertInstanceOf(PaymentProviderInterface::class, $provider);
            $this->assertEquals($providerName, $provider->getProviderName());
            $this->assertIsArray($provider->getRequiredCredentialFields());
            $this->assertNotEmpty($provider->getRequiredCredentialFields());
        }
    }

    public function test_doku_provider_has_correct_required_fields(): void
    {
        $provider = $this->factory->make('doku');
        $fields = $provider->getRequiredCredentialFields();

        $this->assertCount(2, $fields);
        $this->assertContains('client_id', $fields);
        $this->assertContains('secret_key', $fields);
    }

    public function test_xendit_provider_has_correct_required_fields(): void
    {
        $provider = $this->factory->make('xendit');
        $fields = $provider->getRequiredCredentialFields();

        $this->assertCount(2, $fields);
        $this->assertContains('api_key', $fields);
        $this->assertContains('webhook_token', $fields);
    }

    public function test_midtrans_provider_has_correct_required_fields(): void
    {
        $provider = $this->factory->make('midtrans');
        $fields = $provider->getRequiredCredentialFields();

        $this->assertCount(2, $fields);
        $this->assertContains('server_key', $fields);
        $this->assertContains('client_key', $fields);
    }

    public function test_duitku_provider_has_correct_required_fields(): void
    {
        $provider = $this->factory->make('duitku');
        $fields = $provider->getRequiredCredentialFields();

        $this->assertCount(2, $fields);
        $this->assertContains('merchant_code', $fields);
        $this->assertContains('api_key', $fields);
    }
}
