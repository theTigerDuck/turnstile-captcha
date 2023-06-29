<?php

namespace tests;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use SilverStripe\Control\Controller;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\Form;
use Terraformers\TurnstileCaptcha\Forms\TurnstileCaptchaField;

class TestTurnstileCaptchaField extends SapphireTest
{

    /**
     * Testing the TurnstileCaptchaField Spam Protection Field
     * @return void
     */
    public function testSpamProtectionField(): void
    {
        $form = Form::create(Controller::create(), 'Form', new FieldList(), new FieldList());

        $turnstileCaptchField = new TurnstileCaptchaField('turnstileField');
        $turnstileCaptchField->setSiteKey('1x00000000000000000000AA');
        $turnstileCaptchField->setSecretKey('1x0000000000000000000000000000000AA');
        $turnstileCaptchField->setForm($form);
        $this->assertNotNull($turnstileCaptchField->getSiteKey());
        $this->assertStringContainsString("Form_Form", $turnstileCaptchField->getFormID());
    }

    /**
     * Testing the local Mock API data response for the TurnstileCaptchaField
     * @return void
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function testTurnstileMockApi(): void
    {

        // Create a Mock Handler with success & fail response data
        $mock = new MockHandler([
            new Response(200, [], '{
                "success": true,
                "challenge_ts": "2022-02-28T15:14:30.096Z",
                "hostname": "example.com",
                "error-codes": [],
                "action": "login",
                "cdata": "sessionid-123456789"
                }'),
            new Response(201, [], '{
                "success": false,
                 "error-codes": ["invalid-input-response"]
                }'),
        ]);
        $handleStack = HandlerStack::create($mock);
        $client = new Client(['handler' => $handleStack]);

        // mock the success response data
        $successResponse = $client->request('GET', '/');
        $successResponseBody = json_decode($successResponse->getBody(), true);
        $this->assertTrue($successResponseBody['success']);
        // expecting a empty error-code array
        $this->assertEmpty($successResponseBody['error-codes']);

        // mock the failed response data
        $failedResponse = $client->request('GET', '/');
        $failedResponseBody = json_decode($failedResponse->getBody(), true);
        $this->assertFalse($failedResponseBody['success']);
        // expecting a error message for a failed reponse
        $this->assertNotEmpty($failedResponseBody['error-codes']);
    }

}
