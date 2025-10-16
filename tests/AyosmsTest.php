<?php

namespace Tests;

use PHPUnit\Framework\TestCase;
use ImamRasyid\Ayosms\Ayosms;

/**
 * @covers \ImamRasyid\Ayosms\Ayosms
 */
class AyosmsTest extends TestCase
{
    private Ayosms $client;

    protected function setUp(): void
    {
        $this->client = new Ayosms(['api_key' => 'DUMMY_API_KEY']);
    }

    /**
     * Create a mock client overriding httpPost()
     */
    private function createMockClient(array $mockData): Ayosms
    {
        $mockResponse = json_encode($mockData);
        $client = $this->getMockBuilder(Ayosms::class)
            ->onlyMethods(['httpPost'])
            ->setConstructorArgs([['api_key' => 'DUMMY_API_KEY']])
            ->getMock();
        $client->method('httpPost')->willReturn($mockResponse);
        return $client;
    }

    // ---------------------------------------------------------
    // sendSMS()
    // ---------------------------------------------------------

    public function testSendSMS_ReturnsSuccess_WhenValidRequest()
    {
        $client = $this->createMockClient([
            'status' => 1,
            'msg_id' => '123456',
            'to' => '6281234567890'
        ]);

        $result = $client->sendSMS('AYOSMS', '6281234567890', 'Hello world');
        $data = json_decode($result, true);

        $this->assertSame(1, $data['status']);
        $this->assertArrayHasKey('segment', $data);
        $this->assertEquals(1, $data['segment']);
    }

    public function testSendSMS_ReturnsError_WhenApiKeyMissing()
    {
        $client = new Ayosms(['api_key' => '']);
        $res = json_decode($client->sendSMS('AYOSMS', '6281234567890', 'Test'), true);
        $this->assertSame(0, $res['status']);
        $this->assertStringContainsString('api_key is empty', $res['error-text']);
    }

    public function testSendSMS_ReturnsError_WhenNonGSMCharacters()
    {
        $res = json_decode($this->client->sendSMS('AYOSMS', '6281234567890', 'Hello 😀'), true);
        $this->assertSame(0, $res['status']);
        $this->assertStringContainsString('non-GSM 7-bit characters', $res['error-text']);
    }

    public function testSendSMS_CalculatesSegments_ForLongMessage()
    {
        $msg = str_repeat('a', 161);
        $mock = $this->createMockClient(['status' => 1, 'msg_id' => 'ABC']);
        $res = json_decode($mock->sendSMS('AYOSMS', '6281234567890', $msg), true);
        $this->assertEquals(2, $res['segment']);
    }

    // ---------------------------------------------------------
    // checkBalance()
    // ---------------------------------------------------------

    public function testCheckBalance_ReturnsSuccess()
    {
        $client = $this->createMockClient([
            'status' => 1,
            'balance' => '5000',
            'currency' => 'IDR'
        ]);
        $res = json_decode($client->checkBalance(), true);
        $this->assertSame(1, $res['status']);
        $this->assertSame('5000', $res['balance']);
    }

    public function testCheckBalance_ReturnsError_WhenNoApiKey()
    {
        $client = new Ayosms(['api_key' => '']);
        $res = json_decode($client->checkBalance(), true);
        $this->assertSame(0, $res['status']);
        $this->assertStringContainsString('api_key is empty', $res['error-text']);
    }

    // ---------------------------------------------------------
    // sendHLR()
    // ---------------------------------------------------------

    public function testSendHLR_ReturnsSuccess()
    {
        $client = $this->createMockClient([
            'status' => 1,
            'msg_id' => 'HLR123'
        ]);
        $res = json_decode($client->sendHLR('6281234567890'), true);
        $this->assertSame(1, $res['status']);
        $this->assertSame('HLR123', $res['msg_id']);
    }

    // ---------------------------------------------------------
    // otpRequest() / otpCheck()
    // ---------------------------------------------------------

    public function testOtpRequest_ReturnsError_WhenSecretMissing()
    {
        $res = json_decode($this->client->otpRequest([
            'from' => 'AYOSMS',
            'to'   => '6281234567890'
        ]), true);
        $this->assertSame(0, $res['status']);
        $this->assertStringContainsString('secret error or empty', $res['error-text']);
    }

    public function testOtpCheck_ReturnsError_WhenPinMissing()
    {
        $res = json_decode($this->client->otpCheck([
            'from' => 'AYOSMS',
            'secret' => 'MYSECRET'
        ]), true);
        $this->assertSame(0, $res['status']);
        $this->assertStringContainsString('pin error or empty', $res['error-text']);
    }

    // ---------------------------------------------------------
    // validateDlrPayload()
    // ---------------------------------------------------------

    public function testValidateDlrPayload_ReturnsValid_WhenAllFieldsPresent()
    {
        $res = $this->client->validateDlrPayload([
            'msg_id' => '1',
            'to' => '6281234567890',
            'status' => 1
        ]);
        $this->assertTrue($res['valid']);
    }

    public function testValidateDlrPayload_ReturnsInvalid_WhenMissingFields()
    {
        $res = $this->client->validateDlrPayload(['msg_id' => '']);
        $this->assertFalse($res['valid']);
        $this->assertContains('to is missing', $res['errors'] ?? []);
    }

    public function testIsGSM7Bit_AllowsSpace()
    {
        $ref = new \ReflectionMethod(Ayosms::class, 'isGSM7Bit');
        $ref->setAccessible(true);
        $this->assertTrue($ref->invoke($this->client, 'Hello world'));
    }
}
