<?php

namespace Tests\Unit\Services;

use App\Services\TemplateService;
use Eris\Generators;
use Eris\TestTrait;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Property-based tests for TemplateService
 * 
 * Feature: template-management
 */
class TemplateServicePropertyTest extends TestCase
{
    use TestTrait;

    private TemplateService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new TemplateService();
    }

    /**
     * Feature: template-management, Property 1: Template Name Validation
     * Validates: Requirements 1.3, 5.2
     * 
     * For any string input as template name, the validation function SHALL accept 
     * the string if and only if it contains only lowercase letters (a-z), digits (0-9), 
     * and underscores (_), and reject all other strings with an appropriate error message.
     */
    #[Test]
    public function template_name_validation_accepts_only_valid_characters(): void
    {
        $this
            ->limitTo(100)
            ->forAll(
                Generators::string()
            )
            ->then(function (string $name) {
                $result = $this->service->validateTemplateName($name);
                
                // Determine expected validity based on the property definition
                // Valid names: non-empty strings containing only [a-z0-9_]
                // Note: Use strict empty check ($name !== '') instead of empty() because
                // PHP's empty('0') returns true, but '0' is a valid template name
                $shouldBeValid = $name !== '' && preg_match('/^[a-z0-9_]+$/', $name) === 1;
                
                $this->assertEquals(
                    $shouldBeValid,
                    $result['valid'],
                    sprintf(
                        "Template name '%s' validation mismatch. Expected %s but got %s. Error: %s",
                        $name,
                        $shouldBeValid ? 'valid' : 'invalid',
                        $result['valid'] ? 'valid' : 'invalid',
                        $result['error'] ?? 'none'
                    )
                );
                
                // If invalid, should have an error message
                if (!$result['valid']) {
                    $this->assertNotNull(
                        $result['error'],
                        "Invalid template name should have an error message"
                    );
                }
                
                // If valid, should not have an error message
                if ($result['valid']) {
                    $this->assertNull(
                        $result['error'],
                        "Valid template name should not have an error message"
                    );
                }
            });
    }

    /**
     * Feature: template-management, Property 1: Template Name Validation (Valid Names)
     * Validates: Requirements 1.3, 5.2
     * 
     * For any string composed only of valid characters (lowercase letters, digits, underscores),
     * the validation function SHALL accept it.
     */
    #[Test]
    public function template_name_validation_accepts_all_valid_names(): void
    {
        // Valid character set for template names
        $validChars = 'abcdefghijklmnopqrstuvwxyz0123456789_';
        
        $this
            ->limitTo(100)
            ->forAll(
                // Generate random lengths between 1 and 50
                Generators::choose(1, 50)
            )
            ->then(function (int $length) use ($validChars) {
                // Generate a valid name from valid characters only
                $name = '';
                for ($i = 0; $i < $length; $i++) {
                    $name .= $validChars[random_int(0, strlen($validChars) - 1)];
                }
                
                $result = $this->service->validateTemplateName($name);
                
                $this->assertTrue(
                    $result['valid'],
                    sprintf(
                        "Valid template name '%s' should be accepted. Error: %s",
                        $name,
                        $result['error'] ?? 'none'
                    )
                );
                
                $this->assertNull(
                    $result['error'],
                    "Valid template name should not have an error message"
                );
            });
    }

    /**
     * Feature: template-management, Property 2: Footer Length Validation
     * Validates: Requirements 1.7
     * 
     * For any string input as footer text, the validation function SHALL accept strings 
     * with 60 or fewer characters and reject strings exceeding 60 characters with an 
     * appropriate error message.
     */
    #[Test]
    public function footer_length_validation_accepts_strings_within_limit(): void
    {
        $this
            ->limitTo(100)
            ->forAll(
                Generators::string()
            )
            ->then(function (string $footer) {
                $result = $this->service->validateFooter($footer);
                
                // Determine expected validity based on the property definition
                // Valid footers: strings with 60 or fewer characters (empty is also valid)
                $length = mb_strlen($footer);
                $shouldBeValid = $length <= 60;
                
                $this->assertEquals(
                    $shouldBeValid,
                    $result['valid'],
                    sprintf(
                        "Footer '%s' (length: %d) validation mismatch. Expected %s but got %s. Error: %s",
                        substr($footer, 0, 100) . (strlen($footer) > 100 ? '...' : ''),
                        $length,
                        $shouldBeValid ? 'valid' : 'invalid',
                        $result['valid'] ? 'valid' : 'invalid',
                        $result['error'] ?? 'none'
                    )
                );
                
                // If invalid (exceeds 60 chars), should have an error message
                if (!$result['valid']) {
                    $this->assertNotNull(
                        $result['error'],
                        "Invalid footer should have an error message"
                    );
                }
                
                // If valid, should not have an error message
                if ($result['valid']) {
                    $this->assertNull(
                        $result['error'],
                        "Valid footer should not have an error message"
                    );
                }
            });
    }

    /**
     * Feature: template-management, Property 2: Footer Length Validation (Boundary)
     * Validates: Requirements 1.7
     * 
     * For any string of exactly 60 characters, the validation SHALL accept it.
     * For any string of exactly 61 characters, the validation SHALL reject it.
     */
    #[Test]
    public function footer_length_validation_boundary_cases(): void
    {
        $this
            ->limitTo(100)
            ->forAll(
                // Generate random printable characters
                Generators::choose(32, 126)
            )
            ->then(function (int $charCode) {
                $char = chr($charCode);
                
                // Test exactly 60 characters (should be valid)
                $footer60 = str_repeat($char, 60);
                $result60 = $this->service->validateFooter($footer60);
                
                $this->assertTrue(
                    $result60['valid'],
                    sprintf(
                        "Footer with exactly 60 characters should be valid. Got error: %s",
                        $result60['error'] ?? 'none'
                    )
                );
                
                // Test exactly 61 characters (should be invalid)
                $footer61 = str_repeat($char, 61);
                $result61 = $this->service->validateFooter($footer61);
                
                $this->assertFalse(
                    $result61['valid'],
                    "Footer with exactly 61 characters should be invalid"
                );
                
                $this->assertNotNull(
                    $result61['error'],
                    "Invalid footer should have an error message"
                );
            });
    }

    /**
     * Feature: template-management, Property 4: Body Length Validation
     * Validates: Requirements 5.3
     * 
     * For any string input as body text, the validation function SHALL accept strings 
     * with 1024 or fewer characters and reject strings exceeding 1024 characters with 
     * an appropriate error message.
     */
    #[Test]
    public function body_length_validation_accepts_strings_within_limit(): void
    {
        $this
            ->limitTo(100)
            ->forAll(
                Generators::string()
            )
            ->then(function (string $body) {
                // Skip empty strings as body is required (tested separately)
                if (empty($body)) {
                    return;
                }
                
                $result = $this->service->validateBody($body);
                
                // Determine expected validity based on the property definition
                // Valid body: non-empty strings with 1024 or fewer characters
                $length = mb_strlen($body);
                $shouldBeValid = $length <= 1024;
                
                $this->assertEquals(
                    $shouldBeValid,
                    $result['valid'],
                    sprintf(
                        "Body (length: %d) validation mismatch. Expected %s but got %s. Error: %s",
                        $length,
                        $shouldBeValid ? 'valid' : 'invalid',
                        $result['valid'] ? 'valid' : 'invalid',
                        $result['error'] ?? 'none'
                    )
                );
                
                // If invalid (exceeds 1024 chars), should have an error message
                if (!$result['valid']) {
                    $this->assertNotNull(
                        $result['error'],
                        "Invalid body should have an error message"
                    );
                }
                
                // If valid, should not have an error message
                if ($result['valid']) {
                    $this->assertNull(
                        $result['error'],
                        "Valid body should not have an error message"
                    );
                }
            });
    }

    /**
     * Feature: template-management, Property 4: Body Length Validation (Boundary)
     * Validates: Requirements 5.3
     * 
     * For any string of exactly 1024 characters, the validation SHALL accept it.
     * For any string of exactly 1025 characters, the validation SHALL reject it.
     */
    #[Test]
    public function body_length_validation_boundary_cases(): void
    {
        $this
            ->limitTo(100)
            ->forAll(
                // Generate random printable characters
                Generators::choose(32, 126)
            )
            ->then(function (int $charCode) {
                $char = chr($charCode);
                
                // Test exactly 1024 characters (should be valid)
                $body1024 = str_repeat($char, 1024);
                $result1024 = $this->service->validateBody($body1024);
                
                $this->assertTrue(
                    $result1024['valid'],
                    sprintf(
                        "Body with exactly 1024 characters should be valid. Got error: %s",
                        $result1024['error'] ?? 'none'
                    )
                );
                
                $this->assertNull(
                    $result1024['error'],
                    "Valid body should not have an error message"
                );
                
                // Test exactly 1025 characters (should be invalid)
                $body1025 = str_repeat($char, 1025);
                $result1025 = $this->service->validateBody($body1025);
                
                $this->assertFalse(
                    $result1025['valid'],
                    "Body with exactly 1025 characters should be invalid"
                );
                
                $this->assertNotNull(
                    $result1025['error'],
                    "Invalid body should have an error message"
                );
            });
    }

    /**
     * Feature: template-management, Property 4: Body Length Validation (Empty Body)
     * Validates: Requirements 5.3
     * 
     * Empty body text should be rejected as body is a required field.
     */
    #[Test]
    public function body_length_validation_rejects_empty_body(): void
    {
        $result = $this->service->validateBody('');
        
        $this->assertFalse(
            $result['valid'],
            "Empty body should be invalid"
        );
        
        $this->assertNotNull(
            $result['error'],
            "Empty body should have an error message"
        );
    }

    /**
     * Feature: template-management, Property 3: Button Limit Validation
     * Validates: Requirements 1.8
     * 
     * For any array of buttons, the validation function SHALL accept configurations 
     * with up to 10 quick reply buttons OR up to 2 call-to-action buttons, 
     * and reject configurations exceeding these limits.
     */
    #[Test]
    public function button_limit_validation_quick_reply_buttons(): void
    {
        $this
            ->limitTo(100)
            ->forAll(
                // Generate random number of quick reply buttons (0-15 to test both valid and invalid)
                Generators::choose(0, 15)
            )
            ->then(function (int $buttonCount) {
                // Generate array of quick reply buttons
                $buttons = [];
                for ($i = 0; $i < $buttonCount; $i++) {
                    $buttons[] = [
                        'type' => 'QUICK_REPLY',
                        'text' => 'Button ' . ($i + 1)
                    ];
                }
                
                $result = $this->service->validateButtons($buttons);
                
                // Valid if 10 or fewer quick reply buttons
                $shouldBeValid = $buttonCount <= 10;
                
                $this->assertEquals(
                    $shouldBeValid,
                    $result['valid'],
                    sprintf(
                        "Quick reply buttons count %d validation mismatch. Expected %s but got %s. Error: %s",
                        $buttonCount,
                        $shouldBeValid ? 'valid' : 'invalid',
                        $result['valid'] ? 'valid' : 'invalid',
                        $result['error'] ?? 'none'
                    )
                );
                
                // If invalid, should have an error message
                if (!$result['valid']) {
                    $this->assertNotNull(
                        $result['error'],
                        "Invalid button configuration should have an error message"
                    );
                }
            });
    }

    /**
     * Feature: template-management, Property 3: Button Limit Validation (CTA Buttons)
     * Validates: Requirements 1.8
     * 
     * For any array of CTA buttons (URL or PHONE_NUMBER), the validation function 
     * SHALL accept configurations with up to 2 buttons and reject configurations 
     * exceeding this limit.
     */
    #[Test]
    public function button_limit_validation_cta_buttons(): void
    {
        $ctaTypes = ['URL', 'PHONE_NUMBER'];
        
        $this
            ->limitTo(100)
            ->forAll(
                // Generate random number of CTA buttons (0-5 to test both valid and invalid)
                Generators::choose(0, 5)
            )
            ->then(function (int $buttonCount) use ($ctaTypes) {
                // Generate array of CTA buttons
                $buttons = [];
                for ($i = 0; $i < $buttonCount; $i++) {
                    $type = $ctaTypes[array_rand($ctaTypes)];
                    $button = [
                        'type' => $type,
                        'text' => 'Button ' . ($i + 1)
                    ];
                    
                    if ($type === 'URL') {
                        $button['url'] = 'https://example.com/' . ($i + 1);
                    } else {
                        $button['phone_number'] = '+1234567890' . $i;
                    }
                    
                    $buttons[] = $button;
                }
                
                $result = $this->service->validateButtons($buttons);
                
                // Valid if 2 or fewer CTA buttons
                $shouldBeValid = $buttonCount <= 2;
                
                $this->assertEquals(
                    $shouldBeValid,
                    $result['valid'],
                    sprintf(
                        "CTA buttons count %d validation mismatch. Expected %s but got %s. Error: %s",
                        $buttonCount,
                        $shouldBeValid ? 'valid' : 'invalid',
                        $result['valid'] ? 'valid' : 'invalid',
                        $result['error'] ?? 'none'
                    )
                );
                
                // If invalid, should have an error message
                if (!$result['valid']) {
                    $this->assertNotNull(
                        $result['error'],
                        "Invalid button configuration should have an error message"
                    );
                }
            });
    }

    /**
     * Feature: template-management, Property 3: Button Limit Validation (Mixed Buttons)
     * Validates: Requirements 1.8
     * 
     * For any configuration mixing quick reply and CTA buttons, the validation 
     * function SHALL reject the configuration.
     */
    #[Test]
    public function button_limit_validation_rejects_mixed_buttons(): void
    {
        $this
            ->limitTo(100)
            ->forAll(
                // Generate random number of quick reply buttons (1-5)
                Generators::choose(1, 5),
                // Generate random number of CTA buttons (1-3)
                Generators::choose(1, 3)
            )
            ->then(function (int $quickReplyCount, int $ctaCount) {
                $buttons = [];
                
                // Add quick reply buttons
                for ($i = 0; $i < $quickReplyCount; $i++) {
                    $buttons[] = [
                        'type' => 'QUICK_REPLY',
                        'text' => 'Quick Reply ' . ($i + 1)
                    ];
                }
                
                // Add CTA buttons
                for ($i = 0; $i < $ctaCount; $i++) {
                    $buttons[] = [
                        'type' => 'URL',
                        'text' => 'CTA ' . ($i + 1),
                        'url' => 'https://example.com/' . ($i + 1)
                    ];
                }
                
                $result = $this->service->validateButtons($buttons);
                
                // Mixed buttons should always be invalid
                $this->assertFalse(
                    $result['valid'],
                    sprintf(
                        "Mixed buttons (%d quick reply + %d CTA) should be invalid",
                        $quickReplyCount,
                        $ctaCount
                    )
                );
                
                $this->assertNotNull(
                    $result['error'],
                    "Mixed button configuration should have an error message"
                );
            });
    }

    /**
     * Feature: template-management, Property 3: Button Limit Validation (Empty/Null)
     * Validates: Requirements 1.8
     * 
     * Empty or null button arrays should be valid (buttons are optional).
     */
    #[Test]
    public function button_limit_validation_accepts_empty_buttons(): void
    {
        // Test null
        $resultNull = $this->service->validateButtons(null);
        $this->assertTrue(
            $resultNull['valid'],
            "Null buttons should be valid"
        );
        
        // Test empty array
        $resultEmpty = $this->service->validateButtons([]);
        $this->assertTrue(
            $resultEmpty['valid'],
            "Empty buttons array should be valid"
        );
    }

    /**
     * Feature: template-management, Property 3: Button Limit Validation (Boundary)
     * Validates: Requirements 1.8
     * 
     * Boundary test: exactly 10 quick reply buttons should be valid,
     * exactly 11 should be invalid. Exactly 2 CTA buttons should be valid,
     * exactly 3 should be invalid.
     */
    #[Test]
    public function button_limit_validation_boundary_cases(): void
    {
        // Test exactly 10 quick reply buttons (should be valid)
        $buttons10 = [];
        for ($i = 0; $i < 10; $i++) {
            $buttons10[] = ['type' => 'QUICK_REPLY', 'text' => 'Button ' . ($i + 1)];
        }
        $result10 = $this->service->validateButtons($buttons10);
        $this->assertTrue(
            $result10['valid'],
            "Exactly 10 quick reply buttons should be valid"
        );
        
        // Test exactly 11 quick reply buttons (should be invalid)
        $buttons11 = $buttons10;
        $buttons11[] = ['type' => 'QUICK_REPLY', 'text' => 'Button 11'];
        $result11 = $this->service->validateButtons($buttons11);
        $this->assertFalse(
            $result11['valid'],
            "Exactly 11 quick reply buttons should be invalid"
        );
        
        // Test exactly 2 CTA buttons (should be valid)
        $cta2 = [
            ['type' => 'URL', 'text' => 'URL 1', 'url' => 'https://example.com/1'],
            ['type' => 'PHONE_NUMBER', 'text' => 'Call', 'phone_number' => '+1234567890']
        ];
        $resultCta2 = $this->service->validateButtons($cta2);
        $this->assertTrue(
            $resultCta2['valid'],
            "Exactly 2 CTA buttons should be valid"
        );
        
        // Test exactly 3 CTA buttons (should be invalid)
        $cta3 = $cta2;
        $cta3[] = ['type' => 'URL', 'text' => 'URL 2', 'url' => 'https://example.com/2'];
        $resultCta3 = $this->service->validateButtons($cta3);
        $this->assertFalse(
            $resultCta3['valid'],
            "Exactly 3 CTA buttons should be invalid"
        );
    }

    /**
     * Feature: template-management, Property 5: Required Field Validation
     * Validates: Requirements 5.4
     * 
     * For any template form submission, the validation function SHALL reject submissions 
     * where required fields (name, category, language, body) are empty and return specific 
     * error messages for each missing field.
     */
    #[Test]
    public function required_field_validation_rejects_missing_fields(): void
    {
        $requiredFields = ['name', 'category', 'language', 'body'];
        
        $this
            ->limitTo(100)
            ->forAll(
                // Generate a random subset of fields to include (as bitmask 0-15)
                Generators::choose(0, 15)
            )
            ->then(function (int $fieldMask) use ($requiredFields) {
                $data = [];
                $missingFields = [];
                
                // Build data array based on bitmask
                foreach ($requiredFields as $index => $field) {
                    if ($fieldMask & (1 << $index)) {
                        // Include this field with a valid value
                        $data[$field] = match($field) {
                            'name' => 'valid_template_name',
                            'category' => 'MARKETING',
                            'language' => 'en',
                            'body' => 'This is a valid body text',
                        };
                    } else {
                        // Field is missing
                        $missingFields[] = $field;
                    }
                }
                
                $result = $this->service->validateRequiredFields($data);
                
                // Should be valid only if all fields are present (bitmask = 15)
                $shouldBeValid = $fieldMask === 15;
                
                $this->assertEquals(
                    $shouldBeValid,
                    $result['valid'],
                    sprintf(
                        "Required fields validation mismatch. Missing fields: [%s]. Expected %s but got %s",
                        implode(', ', $missingFields),
                        $shouldBeValid ? 'valid' : 'invalid',
                        $result['valid'] ? 'valid' : 'invalid'
                    )
                );
                
                // If invalid, should have error messages for each missing field
                if (!$result['valid']) {
                    foreach ($missingFields as $field) {
                        $this->assertArrayHasKey(
                            $field,
                            $result['errors'],
                            sprintf("Missing field '%s' should have an error message", $field)
                        );
                        $this->assertNotEmpty(
                            $result['errors'][$field],
                            sprintf("Error message for '%s' should not be empty", $field)
                        );
                    }
                }
                
                // If valid, should have no errors
                if ($result['valid']) {
                    $this->assertEmpty(
                        $result['errors'],
                        "Valid submission should have no errors"
                    );
                }
            });
    }

    /**
     * Feature: template-management, Property 5: Required Field Validation (Empty Values)
     * Validates: Requirements 5.4
     * 
     * For any template form submission with empty string values for required fields,
     * the validation function SHALL treat them as missing and return appropriate errors.
     */
    #[Test]
    public function required_field_validation_rejects_empty_string_values(): void
    {
        $requiredFields = ['name', 'category', 'language', 'body'];
        
        $this
            ->limitTo(100)
            ->forAll(
                // Generate a random subset of fields to make empty (as bitmask 0-15)
                Generators::choose(0, 15)
            )
            ->then(function (int $emptyMask) use ($requiredFields) {
                $data = [];
                $emptyFields = [];
                
                // Build data array - all fields present but some are empty strings
                foreach ($requiredFields as $index => $field) {
                    if ($emptyMask & (1 << $index)) {
                        // This field has a valid value
                        $data[$field] = match($field) {
                            'name' => 'valid_template_name',
                            'category' => 'UTILITY',
                            'language' => 'id',
                            'body' => 'Body text content',
                        };
                    } else {
                        // This field is empty string
                        $data[$field] = '';
                        $emptyFields[] = $field;
                    }
                }
                
                $result = $this->service->validateRequiredFields($data);
                
                // Should be valid only if all fields have values (emptyMask = 15)
                $shouldBeValid = $emptyMask === 15;
                
                $this->assertEquals(
                    $shouldBeValid,
                    $result['valid'],
                    sprintf(
                        "Required fields validation mismatch. Empty fields: [%s]. Expected %s but got %s",
                        implode(', ', $emptyFields),
                        $shouldBeValid ? 'valid' : 'invalid',
                        $result['valid'] ? 'valid' : 'invalid'
                    )
                );
                
                // If invalid, should have error messages for each empty field
                if (!$result['valid']) {
                    foreach ($emptyFields as $field) {
                        $this->assertArrayHasKey(
                            $field,
                            $result['errors'],
                            sprintf("Empty field '%s' should have an error message", $field)
                        );
                    }
                }
            });
    }

    /**
     * Feature: template-management, Property 5: Required Field Validation (All Present)
     * Validates: Requirements 5.4
     * 
     * For any template form submission with all required fields present and non-empty,
     * the validation function SHALL accept it.
     */
    #[Test]
    public function required_field_validation_accepts_complete_data(): void
    {
        $categories = ['MARKETING', 'UTILITY', 'AUTHENTICATION'];
        $languages = ['en', 'id', 'en_US', 'id_ID', 'es', 'fr', 'de'];
        $validChars = 'abcdefghijklmnopqrstuvwxyz0123456789_';
        
        $this
            ->limitTo(100)
            ->forAll(
                // Generate random category index
                Generators::choose(0, count($categories) - 1),
                // Generate random language index
                Generators::choose(0, count($languages) - 1),
                // Generate random body length (1-100 to ensure non-empty)
                Generators::choose(1, 100)
            )
            ->then(function (int $categoryIndex, int $languageIndex, int $bodyLength) 
                use ($categories, $languages, $validChars) {
                
                // Generate valid template name (always at least 1 character)
                // Start with a letter to avoid PHP empty('0') edge case
                $letters = 'abcdefghijklmnopqrstuvwxyz';
                $nameLength = random_int(1, 30);
                $name = $letters[random_int(0, strlen($letters) - 1)];
                for ($i = 1; $i < $nameLength; $i++) {
                    $name .= $validChars[random_int(0, strlen($validChars) - 1)];
                }
                
                // Generate body text (always at least 1 character)
                $body = str_repeat('a', $bodyLength);
                
                $data = [
                    'name' => $name,
                    'category' => $categories[$categoryIndex],
                    'language' => $languages[$languageIndex],
                    'body' => $body,
                ];
                
                $result = $this->service->validateRequiredFields($data);
                
                $this->assertTrue(
                    $result['valid'],
                    sprintf(
                        "Complete data should be valid. Got errors: %s",
                        json_encode($result['errors'])
                    )
                );
                
                $this->assertEmpty(
                    $result['errors'],
                    "Valid submission should have no errors"
                );
            });
    }

    /**
     * Feature: template-management, Property 8: Template Data Round-Trip
     * Validates: Requirements 6.1, 6.2, 6.3
     * 
     * For any valid template data object, serializing to JSON for storage and then 
     * deserializing back SHALL produce an object equivalent to the original, 
     * preserving all component data including nested button configurations.
     */
    #[Test]
    public function template_data_round_trip_preserves_all_data(): void
    {
        $this
            ->limitTo(100)
            ->forAll(
                // Generate random template configuration flags
                Generators::choose(0, 15) // Bitmask for optional components
            )
            ->then(function (int $componentMask) {
                $templateData = $this->generateRandomTemplateData($componentMask);
                
                // Serialize the template data
                $serialized = $this->service->serializeTemplate($templateData);
                
                // Deserialize back
                $deserialized = $this->service->deserializeTemplate($serialized);
                
                // Verify round-trip preserves all data
                $this->assertEquals(
                    $templateData,
                    $deserialized,
                    sprintf(
                        "Round-trip should preserve template data.\nOriginal: %s\nAfter round-trip: %s",
                        json_encode($templateData, JSON_PRETTY_PRINT),
                        json_encode($deserialized, JSON_PRETTY_PRINT)
                    )
                );
            });
    }

    /**
     * Feature: template-management, Property 8: Template Data Round-Trip (Components)
     * Validates: Requirements 6.1, 6.2, 6.3
     * 
     * For any valid components array with nested button configurations,
     * serializing and deserializing SHALL preserve the exact structure.
     */
    #[Test]
    public function template_data_round_trip_preserves_nested_buttons(): void
    {
        $buttonTypes = ['QUICK_REPLY', 'URL', 'PHONE_NUMBER'];
        
        $this
            ->limitTo(100)
            ->forAll(
                // Generate random number of buttons (0-5)
                Generators::choose(0, 5),
                // Generate random button type index
                Generators::choose(0, 2)
            )
            ->then(function (int $buttonCount, int $buttonTypeIndex) use ($buttonTypes) {
                // Generate buttons of a single type (no mixing)
                $buttons = [];
                $buttonType = $buttonTypes[$buttonTypeIndex];
                
                // Limit CTA buttons to 2, quick reply to 10
                $maxButtons = $buttonType === 'QUICK_REPLY' ? min($buttonCount, 10) : min($buttonCount, 2);
                
                for ($i = 0; $i < $maxButtons; $i++) {
                    $button = [
                        'type' => $buttonType,
                        'text' => 'Button ' . ($i + 1),
                    ];
                    
                    if ($buttonType === 'URL') {
                        $button['url'] = 'https://example.com/path/' . ($i + 1);
                    } elseif ($buttonType === 'PHONE_NUMBER') {
                        $button['phone_number'] = '+1234567890' . $i;
                    }
                    
                    $buttons[] = $button;
                }
                
                $templateData = [
                    'name' => 'test_template',
                    'category' => 'MARKETING',
                    'language' => 'en',
                    'components' => [
                        [
                            'type' => 'BODY',
                            'text' => 'Test body text',
                        ],
                    ],
                    'buttons' => $buttons,
                ];
                
                // Serialize the template data
                $serialized = $this->service->serializeTemplate($templateData);
                
                // Verify components and buttons are JSON strings after serialization
                if (!empty($templateData['components'])) {
                    $this->assertIsString(
                        $serialized['components'],
                        "Components should be serialized to JSON string"
                    );
                }
                
                if (!empty($templateData['buttons'])) {
                    $this->assertIsString(
                        $serialized['buttons'],
                        "Buttons should be serialized to JSON string"
                    );
                }
                
                // Deserialize back
                $deserialized = $this->service->deserializeTemplate($serialized);
                
                // Verify round-trip preserves all data including nested buttons
                $this->assertEquals(
                    $templateData,
                    $deserialized,
                    sprintf(
                        "Round-trip should preserve nested button configurations.\nOriginal buttons: %s\nAfter round-trip: %s",
                        json_encode($templateData['buttons'], JSON_PRETTY_PRINT),
                        json_encode($deserialized['buttons'] ?? [], JSON_PRETTY_PRINT)
                    )
                );
            });
    }

    /**
     * Feature: template-management, Property 8: Template Data Round-Trip (Complex Components)
     * Validates: Requirements 6.1, 6.2, 6.3
     * 
     * For any valid template with all component types (HEADER, BODY, FOOTER, BUTTONS),
     * serializing and deserializing SHALL preserve the complete structure.
     */
    #[Test]
    public function template_data_round_trip_preserves_complex_components(): void
    {
        $headerTypes = ['TEXT', 'IMAGE', 'VIDEO', 'DOCUMENT'];
        $categories = ['MARKETING', 'UTILITY', 'AUTHENTICATION'];
        
        $this
            ->limitTo(100)
            ->forAll(
                // Generate random header type index
                Generators::choose(0, 3),
                // Generate random category index
                Generators::choose(0, 2),
                // Generate random number of variables (0-5)
                Generators::choose(0, 5)
            )
            ->then(function (int $headerTypeIndex, int $categoryIndex, int $variableCount) 
                use ($headerTypes, $categories) {
                
                $headerType = $headerTypes[$headerTypeIndex];
                $category = $categories[$categoryIndex];
                
                // Build body text with variables
                $bodyText = 'Hello';
                for ($i = 1; $i <= $variableCount; $i++) {
                    $bodyText .= " {{" . $i . "}}";
                }
                
                // Build complex components array
                $components = [];
                
                // Add HEADER component
                $headerComponent = [
                    'type' => 'HEADER',
                    'format' => $headerType,
                ];
                if ($headerType === 'TEXT') {
                    $headerComponent['text'] = 'Header text';
                } else {
                    $headerComponent['example'] = [
                        'header_handle' => ['https://example.com/media.jpg']
                    ];
                }
                $components[] = $headerComponent;
                
                // Add BODY component
                $bodyComponent = [
                    'type' => 'BODY',
                    'text' => $bodyText,
                ];
                if ($variableCount > 0) {
                    $examples = [];
                    for ($i = 1; $i <= $variableCount; $i++) {
                        $examples[] = 'Example ' . $i;
                    }
                    $bodyComponent['example'] = [
                        'body_text' => [$examples]
                    ];
                }
                $components[] = $bodyComponent;
                
                // Add FOOTER component
                $components[] = [
                    'type' => 'FOOTER',
                    'text' => 'Footer text',
                ];
                
                // Add BUTTONS component with quick reply buttons
                $components[] = [
                    'type' => 'BUTTONS',
                    'buttons' => [
                        ['type' => 'QUICK_REPLY', 'text' => 'Yes'],
                        ['type' => 'QUICK_REPLY', 'text' => 'No'],
                    ],
                ];
                
                $templateData = [
                    'name' => 'complex_template',
                    'category' => $category,
                    'language' => 'en',
                    'components' => $components,
                ];
                
                // Serialize the template data
                $serialized = $this->service->serializeTemplate($templateData);
                
                // Deserialize back
                $deserialized = $this->service->deserializeTemplate($serialized);
                
                // Verify round-trip preserves all complex component data
                $this->assertEquals(
                    $templateData,
                    $deserialized,
                    sprintf(
                        "Round-trip should preserve complex component structure.\nOriginal: %s\nAfter round-trip: %s",
                        json_encode($templateData, JSON_PRETTY_PRINT),
                        json_encode($deserialized, JSON_PRETTY_PRINT)
                    )
                );
            });
    }

    /**
     * Feature: template-management, Property 7: Preview Variable Rendering
     * Validates: Requirements 4.3
     * 
     * For any template body containing N variable placeholders ({{1}} through {{N}}), 
     * the preview function SHALL render exactly N placeholder indicators in the output string.
     */
    #[Test]
    public function preview_variable_rendering_produces_correct_placeholder_count(): void
    {
        $this
            ->limitTo(100)
            ->forAll(
                // Generate random number of variables (0-10)
                Generators::choose(0, 10)
            )
            ->then(function (int $variableCount) {
                // Generate body text with sequential variables
                $bodyText = 'Hello';
                for ($i = 1; $i <= $variableCount; $i++) {
                    $bodyText .= " {{" . $i . "}} world";
                }
                
                // Render the preview
                $preview = $this->service->renderPreviewBody($bodyText);
                
                // Count placeholder indicators in the output
                preg_match_all('/\[Variable \d+\]/', $preview, $matches);
                $placeholderCount = count($matches[0]);
                
                $this->assertEquals(
                    $variableCount,
                    $placeholderCount,
                    sprintf(
                        "Body with %d variables should produce %d placeholder indicators. Got %d. Body: '%s', Preview: '%s'",
                        $variableCount,
                        $variableCount,
                        $placeholderCount,
                        $bodyText,
                        $preview
                    )
                );
                
                // Verify each variable is replaced correctly
                for ($i = 1; $i <= $variableCount; $i++) {
                    $this->assertStringContainsString(
                        "[Variable $i]",
                        $preview,
                        sprintf("Preview should contain [Variable %d]", $i)
                    );
                    
                    // Original placeholder should not be present
                    $this->assertStringNotContainsString(
                        "{{" . $i . "}}",
                        $preview,
                        sprintf("Original placeholder {{%d}} should be replaced", $i)
                    );
                }
            });
    }

    /**
     * Feature: template-management, Property 7: Preview Variable Rendering (Non-Sequential)
     * Validates: Requirements 4.3
     * 
     * For any template body containing non-sequential variable placeholders,
     * the preview function SHALL still render the correct number of placeholder indicators.
     */
    #[Test]
    public function preview_variable_rendering_handles_non_sequential_variables(): void
    {
        $this
            ->limitTo(100)
            ->forAll(
                // Generate random number of variables (1-5)
                Generators::choose(1, 5),
                // Generate random starting number (1-10)
                Generators::choose(1, 10)
            )
            ->then(function (int $variableCount, int $startNum) {
                // Generate body text with non-sequential variables
                $bodyText = 'Hello';
                $variableNumbers = [];
                for ($i = 0; $i < $variableCount; $i++) {
                    $varNum = $startNum + ($i * 2); // Skip numbers to make non-sequential
                    $variableNumbers[] = $varNum;
                    $bodyText .= " {{" . $varNum . "}}";
                }
                
                // Render the preview
                $preview = $this->service->renderPreviewBody($bodyText);
                
                // Count placeholder indicators in the output
                preg_match_all('/\[Variable \d+\]/', $preview, $matches);
                $placeholderCount = count($matches[0]);
                
                $this->assertEquals(
                    $variableCount,
                    $placeholderCount,
                    sprintf(
                        "Body with %d non-sequential variables should produce %d placeholder indicators. Got %d.",
                        $variableCount,
                        $variableCount,
                        $placeholderCount
                    )
                );
                
                // Verify each variable is replaced correctly
                foreach ($variableNumbers as $varNum) {
                    $this->assertStringContainsString(
                        "[Variable $varNum]",
                        $preview,
                        sprintf("Preview should contain [Variable %d]", $varNum)
                    );
                }
            });
    }

    /**
     * Feature: template-management, Property 7: Preview Variable Rendering (Duplicate Variables)
     * Validates: Requirements 4.3
     * 
     * For any template body containing duplicate variable placeholders,
     * the preview function SHALL render each occurrence as a placeholder indicator.
     */
    #[Test]
    public function preview_variable_rendering_handles_duplicate_variables(): void
    {
        $this
            ->limitTo(100)
            ->forAll(
                // Generate random number of unique variables (1-5)
                Generators::choose(1, 5),
                // Generate random number of duplicates per variable (1-3)
                Generators::choose(1, 3)
            )
            ->then(function (int $uniqueVarCount, int $duplicatesPerVar) {
                // Generate body text with duplicate variables
                $bodyText = 'Hello';
                $totalOccurrences = 0;
                
                for ($i = 1; $i <= $uniqueVarCount; $i++) {
                    for ($j = 0; $j < $duplicatesPerVar; $j++) {
                        $bodyText .= " {{" . $i . "}}";
                        $totalOccurrences++;
                    }
                }
                
                // Render the preview
                $preview = $this->service->renderPreviewBody($bodyText);
                
                // Count placeholder indicators in the output
                preg_match_all('/\[Variable \d+\]/', $preview, $matches);
                $placeholderCount = count($matches[0]);
                
                // Each occurrence should be replaced
                $this->assertEquals(
                    $totalOccurrences,
                    $placeholderCount,
                    sprintf(
                        "Body with %d total variable occurrences should produce %d placeholder indicators. Got %d.",
                        $totalOccurrences,
                        $totalOccurrences,
                        $placeholderCount
                    )
                );
            });
    }

    /**
     * Feature: template-management, Property 7: Preview Variable Rendering (Empty Body)
     * Validates: Requirements 4.3
     * 
     * Empty body text should produce empty preview with zero placeholders.
     */
    #[Test]
    public function preview_variable_rendering_handles_empty_body(): void
    {
        $preview = $this->service->renderPreviewBody('');
        
        $this->assertEquals(
            '',
            $preview,
            "Empty body should produce empty preview"
        );
        
        preg_match_all('/\[Variable \d+\]/', $preview, $matches);
        $this->assertCount(
            0,
            $matches[0],
            "Empty body should produce zero placeholder indicators"
        );
    }

    /**
     * Feature: template-management, Property 7: Preview Variable Rendering (No Variables)
     * Validates: Requirements 4.3
     * 
     * Body text without variables should be returned unchanged with zero placeholders.
     */
    #[Test]
    public function preview_variable_rendering_preserves_text_without_variables(): void
    {
        $this
            ->limitTo(100)
            ->forAll(
                Generators::string()
            )
            ->then(function (string $bodyText) {
                // Skip if the random string happens to contain variable patterns
                if (preg_match('/\{\{\d+\}\}/', $bodyText)) {
                    return;
                }
                
                // Skip empty strings (tested separately)
                if (empty($bodyText)) {
                    return;
                }
                
                // Render the preview
                $preview = $this->service->renderPreviewBody($bodyText);
                
                // Body without variables should be unchanged
                $this->assertEquals(
                    $bodyText,
                    $preview,
                    "Body without variables should be unchanged in preview"
                );
                
                // Should have zero placeholder indicators
                preg_match_all('/\[Variable \d+\]/', $preview, $matches);
                $this->assertCount(
                    0,
                    $matches[0],
                    "Body without variables should produce zero placeholder indicators"
                );
            });
    }

    /**
     * Generate random template data for property testing
     *
     * @param int $componentMask Bitmask for optional components (1=header, 2=footer, 4=buttons, 8=examples)
     * @return array Random template data
     */
    private function generateRandomTemplateData(int $componentMask): array
    {
        $validChars = 'abcdefghijklmnopqrstuvwxyz0123456789_';
        $categories = ['MARKETING', 'UTILITY', 'AUTHENTICATION'];
        $languages = ['en', 'id', 'en_US', 'id_ID'];
        
        // Generate valid template name
        $nameLength = random_int(3, 20);
        $name = '';
        for ($i = 0; $i < $nameLength; $i++) {
            $name .= $validChars[random_int(0, strlen($validChars) - 1)];
        }
        
        $templateData = [
            'name' => $name,
            'category' => $categories[array_rand($categories)],
            'language' => $languages[array_rand($languages)],
        ];
        
        // Build components array
        $components = [];
        
        // Add HEADER if bit 0 is set
        if ($componentMask & 1) {
            $headerTypes = ['TEXT', 'IMAGE', 'VIDEO', 'DOCUMENT'];
            $headerType = $headerTypes[array_rand($headerTypes)];
            $header = [
                'type' => 'HEADER',
                'format' => $headerType,
            ];
            if ($headerType === 'TEXT') {
                $header['text'] = 'Header text ' . random_int(1, 100);
            }
            $components[] = $header;
        }
        
        // Always add BODY (required)
        $bodyText = 'Body text ' . random_int(1, 1000);
        $components[] = [
            'type' => 'BODY',
            'text' => $bodyText,
        ];
        
        // Add FOOTER if bit 1 is set
        if ($componentMask & 2) {
            $components[] = [
                'type' => 'FOOTER',
                'text' => 'Footer ' . random_int(1, 100),
            ];
        }
        
        // Add BUTTONS if bit 2 is set
        if ($componentMask & 4) {
            $buttonCount = random_int(1, 3);
            $buttons = [];
            for ($i = 0; $i < $buttonCount; $i++) {
                $buttons[] = [
                    'type' => 'QUICK_REPLY',
                    'text' => 'Button ' . ($i + 1),
                ];
            }
            $components[] = [
                'type' => 'BUTTONS',
                'buttons' => $buttons,
            ];
            
            // Also store buttons at top level for separate serialization
            $templateData['buttons'] = $buttons;
        }
        
        $templateData['components'] = $components;
        
        return $templateData;
    }
}
