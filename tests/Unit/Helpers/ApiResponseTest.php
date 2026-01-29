<?php

namespace Tests\Unit\Helpers;

use App\Helpers\ApiResponse;
use Illuminate\Http\JsonResponse;
use Tests\TestCase;

class ApiResponseTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Set default locale
        app()->setLocale('en');
    }

    public function test_success_response_structure()
    {
        $response = ApiResponse::success(['key' => 'value'], 'messages.success.created', 201);

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(201, $response->getStatusCode());

        $data = $response->getData(true);
        $this->assertTrue($data['success']);
        $this->assertEquals('Created successfully', $data['message']);
        $this->assertEquals(['key' => 'value'], $data['data']);
        $this->assertEquals('en', $data['locale']);
    }

    public function test_success_response_without_message()
    {
        $response = ApiResponse::success(['key' => 'value']);

        $data = $response->getData(true);
        $this->assertTrue($data['success']);
        $this->assertArrayNotHasKey('message', $data);
        $this->assertEquals(['key' => 'value'], $data['data']);
        $this->assertEquals('en', $data['locale']);
    }

    public function test_success_response_without_data()
    {
        $response = ApiResponse::success(null, 'messages.success.saved');

        $data = $response->getData(true);
        $this->assertTrue($data['success']);
        $this->assertEquals('Data saved successfully', $data['message']);
        $this->assertArrayNotHasKey('data', $data);
        $this->assertEquals('en', $data['locale']);
    }

    public function test_error_response_structure()
    {
        $response = ApiResponse::error('messages.error.general', 400);

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(400, $response->getStatusCode());

        $data = $response->getData(true);
        $this->assertFalse($data['success']);
        $this->assertEquals('An error occurred. Please try again.', $data['message']);
        $this->assertEquals('en', $data['locale']);
    }

    public function test_error_response_with_errors()
    {
        $errors = ['field' => 'error message'];
        $response = ApiResponse::error('messages.error.validation', 422, $errors);

        $data = $response->getData(true);
        $this->assertFalse($data['success']);
        $this->assertEquals($errors, $data['errors']);
    }

    public function test_validation_error_response()
    {
        $errors = ['email' => ['The email field is required.']];
        $response = ApiResponse::validationError($errors);

        $this->assertEquals(422, $response->getStatusCode());

        $data = $response->getData(true);
        $this->assertFalse($data['success']);
        $this->assertEquals('Please fix the validation errors', $data['message']);
        $this->assertEquals($errors, $data['errors']);
    }

    public function test_unauthorized_response()
    {
        $response = ApiResponse::unauthorized();

        $this->assertEquals(401, $response->getStatusCode());

        $data = $response->getData(true);
        $this->assertFalse($data['success']);
        $this->assertEquals('You are not authorized to perform this action', $data['message']);
    }

    public function test_forbidden_response()
    {
        $response = ApiResponse::forbidden();

        $this->assertEquals(403, $response->getStatusCode());

        $data = $response->getData(true);
        $this->assertFalse($data['success']);
        $this->assertEquals('Access denied', $data['message']);
    }

    public function test_not_found_response()
    {
        $response = ApiResponse::notFound();

        $this->assertEquals(404, $response->getStatusCode());

        $data = $response->getData(true);
        $this->assertFalse($data['success']);
        $this->assertEquals('Resource not found', $data['message']);
    }

    public function test_server_error_response()
    {
        $response = ApiResponse::serverError();

        $this->assertEquals(500, $response->getStatusCode());

        $data = $response->getData(true);
        $this->assertFalse($data['success']);
        $this->assertEquals('Server error. Please try again later.', $data['message']);
    }

    public function test_response_uses_indonesian_locale()
    {
        app()->setLocale('id');

        $response = ApiResponse::success(null, 'messages.success.created');

        $data = $response->getData(true);
        $this->assertEquals('Berhasil dibuat', $data['message']);
        $this->assertEquals('id', $data['locale']);
    }

    public function test_detect_locale_from_request_parameter()
    {
        $request = request();
        $request->merge(['locale' => 'id']);

        $locale = ApiResponse::detectLocale($request);

        $this->assertEquals('id', $locale);
    }

    public function test_detect_locale_from_accept_language_header()
    {
        $request = request();
        $request->headers->set('Accept-Language', 'id,en;q=0.9');

        $locale = ApiResponse::detectLocale($request);

        $this->assertEquals('id', $locale);
    }

    public function test_detect_locale_falls_back_to_default()
    {
        $request = request();

        $locale = ApiResponse::detectLocale($request);

        $this->assertEquals('en', $locale);
    }

    public function test_detect_locale_rejects_unsupported_locale()
    {
        $request = request();
        $request->merge(['locale' => 'fr']);

        $locale = ApiResponse::detectLocale($request);

        // Should fall back to default since 'fr' is not supported
        $this->assertEquals('en', $locale);
    }

    public function test_set_locale_from_request()
    {
        $request = request();
        $request->merge(['locale' => 'id']);

        ApiResponse::setLocaleFromRequest($request);

        $this->assertEquals('id', app()->getLocale());
    }
}
