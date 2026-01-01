<?php

namespace Tests\Unit;

use App\Services\AiResponseValidator;
use Tests\TestCase;

class AiResponseValidatorTest extends TestCase
{
    protected AiResponseValidator $validator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->validator = new AiResponseValidator;
    }

    public function test_detects_product_id_exposure()
    {
        $response = 'Dimsum Keju [ID:123] - Rp 40.000';

        $isValid = $this->validator->validate($response);

        $this->assertFalse($isValid);
        $this->assertContains('Product ID exposed to user', $this->validator->getErrors());
    }

    public function test_detects_manual_calculation()
    {
        $response = 'Total: 40.000 x 2 = 80.000';

        $isValid = $this->validator->validate($response);

        $this->assertFalse($isValid);
        $this->assertContains('Manual calculation detected', $this->validator->getErrors());
    }

    public function test_detects_off_topic_response()
    {
        $response = 'ChatGPT adalah model AI yang...';

        $isValid = $this->validator->validate($response);

        $this->assertContains('Possible off-topic response detected', $this->validator->getWarnings());
    }

    public function test_sanitize_removes_product_ids()
    {
        $response = "Dimsum Keju [ID:123] - Rp 40.000\nTeh Jumbo [ID:456] - Rp 5.000";

        $sanitized = $this->validator->sanitize($response);

        $this->assertStringNotContainsString('[ID:123]', $sanitized);
        $this->assertStringNotContainsString('[ID:456]', $sanitized);
        $this->assertStringContainsString('Dimsum Keju', $sanitized);
        $this->assertStringContainsString('Teh Jumbo', $sanitized);
    }

    public function test_valid_response_passes()
    {
        $response = "Berikut menu kami:\n\n1. Dimsum Keju - Rp 40.000\n2. Teh Jumbo - Rp 5.000";

        $isValid = $this->validator->validate($response);

        $this->assertTrue($isValid);
        $this->assertEmpty($this->validator->getErrors());
    }
}
