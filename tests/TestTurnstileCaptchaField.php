<?php

namespace tests;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\Request;
use SilverStripe\Control\Controller;
use SilverStripe\Core\Environment;
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
        $turnstileCaptchField->setSiteKey(Environment::getEnv('SS_TURNSTILE_SITE_KEY'));
        $turnstileCaptchField->setSecretKey(Environment::getEnv('SS_TURNSTILE_SECRET_KEY'));
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

        // Mock Request
        $request = new Request('POST', 'https://challenges.cloudflare.com/turnstile/v0/siteverify',
            [], '');

        // Create a Mock Handler with success response data
        $mock = new MockHandler([
            new Response(200, [], '{
                "success": true,
                "challenge_ts": "2022-02-28T15:14:30.096Z",
                "hostname": "example.com",
                "error-codes": [],
                "action": "login",
                "cdata": "sessionid-123456789"
                }')
        ]);
        $handleStack = HandlerStack::create($mock);
        $client = new Client(['handler' => $handleStack]);
        // mock response data
        $response = $client->send($request);
        $responseData = json_decode($response->getBody(), true);
        // validating the response data
        $this->assertTrue($responseData['success']);
        $this->assertEmpty($responseData['error-codes']);
    }

}
