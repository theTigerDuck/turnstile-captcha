<?php

namespace Terraformers\TurnstileCaptcha\Forms;

use GuzzleHttp\Exception\GuzzleException;
use Locale;
use Psr\Container\NotFoundExceptionInterface;
use Psr\Log\LoggerInterface;
use SilverStripe\Control\Controller;
use SilverStripe\Core\Environment;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Forms\FormField;
use SilverStripe\i18n\i18n;
use SilverStripe\View\Requirements;
use Terraformers\TurnstileCaptcha\Http\HttpClient;

/**
 * @property HttpClient $httpClient
 */
class TurnstileCaptchaField extends FormField
{
    /**
     * TurnstileCaptchaField Site Key
     * @config TurnstileCaptchaField.site_key
     */
    private static ?string $site_key = null;

    /**
     * TurnstileCaptchaField Secret Key
     * @config TurnstileCaptchaField.secret_key
     */
    private static ?string $secret_key = null;


    /**
     * Captcha theme, currently options are light and dark
     * @default light
     */
    private static string $default_theme = 'auto';


    /**
     * Whether form submit events are handled directly by this module.
     * If false, a function is provided that can be called by user code submit handlers.
     * @default true
     */
    private static bool $default_handle_submit = true;


    /**
     * Onload callback to be called for Turnstile is loaded
     *
     */
    private static $js_onload_callback = null;

    /**
     * Captcha theme, currently options are light and dark
     */
    private ?string $_captchaTheme = null;

    /**
     * The verification response
     */
    protected array $verifyResponse;


    /**
     * Whether form submit events are handled directly by this module.
     * If false, a function is provided that can be called by user code submit handlers.
     */
    private bool $handleSubmitEvents;

    private static array $dependencies = [
        'httpClient' => '%$' . HttpClient::class
    ];

    /**
     * Creates a new TurnstileCaptcha 2 field.
     * @param string $name The internal field name, passed to forms.
     * @param string $title The human-readable field label.
     * @param mixed $value The value of the field (unused)
     */
    public function __construct($name, $title = null, $value = null)
    {
        parent::__construct($name, $title, $value);

        $this->title = $title;

        $this->_captchaTheme = self::config()->default_theme;
        $this->handleSubmitEvents = self::config()->default_handle_submit;
    }

    /**
     * Adds in the requirements for the field
     * @param array $properties Array of properties for the form element (not used)
     * @return string Rendered field template
     */
    public function Field($properties = array())
    {
        $siteKey = $this->getSiteKey();
        $secretKey = $this->getSecretKey();

        if (empty($siteKey) || empty($secretKey)) {
            user_error('You must configure site_key and secret_key, you can retrieve these at https://developers.cloudflare.com/turnstile/', E_USER_ERROR);
        }


        Requirements::javascript(
            'https://challenges.cloudflare.com/turnstile/v0/api.js?hl=' . Locale::getPrimaryLanguage(i18n::get_locale()) . ($this->config()->js_onload_callback ? '&onload=' . $this->config()->js_onload_callback : ''),
            [
                'async' => true,
                'defer' => true,
            ]
        );
        return parent::Field($properties);
    }


    /**
     * Validates the captcha against the TurnstileCaptcha API
     *
     * @param Validator $validator Validator to send errors to
     * @return bool Returns boolean
     * @throws NotFoundExceptionInterface
     */
    public function validate($validator): bool
    {

        $request = Controller::curr()->getRequest();
        $captchaResponse = $request->requestVar('cf-turnstile-response');

        if (!isset($captchaResponse)) {
            $validator->validationError($this->name, _t(
                'Terraformers\\TurnstileCaptcha\\Forms\\TurnstileCaptchaField.NOSCRIPT',
                'if you do not see the captcha you must enable JavaScript'),
                'validation');
            return false;
        }


        $client = $this->httpClient->getClient();

        try {
            $response = $client->request('POST', 'https://challenges.cloudflare.com/turnstile/v0/siteverify', [
                'json' => [
                    'secret' => $this->getSecretKey(),
                    'response' => $captchaResponse,
                    'remoteip' => $request->getIP(),
                ],
                'timeout' => 10
            ]);
        } catch (GuzzleException $e) {
            $logger = Injector::inst()->get(LoggerInterface::class);
            $logger->error($e->getMessage());
            return false;
        }
        $responseBody = json_decode($response->getBody(), true);

        if (is_array($responseBody)) {
            $this->verifyResponse = $responseBody;
        }
        if ($response->getStatusCode() !== 200 || !$this->verifyResponse['success']) {
            $validator->validationError($this->name, _t(
                'Terraformers\\TurnstileCaptcha\\Forms\\TurnstileCaptchaField.VALIDATE_ERROR',
                'Turnstile Captcha Field could not be validated'),
                'validation');
            $logger = Injector::inst()->get(LoggerInterface::class);
            $logger->error(
                'Turnstile Captcha Field validation failed as request was not successful.'
            );
            return false;
        }

        return true;
    }

    /**
     * Sets whether form submit events are handled directly by this module.
     *
     * @param boolean $value
     * @return TurnstileCaptchaField
     */
    public function setHandleSubmitEvents(bool $value): TurnstileCaptchaField
    {
        $this->handleSubmitEvents = $value;
        return $this;
    }

    /**
     * Get whether form submit events are handled directly by this module.
     *
     * @return boolean
     */
    public function getHandleSubmitEvents(): bool
    {
        return $this->handleSubmitEvents;
    }

    /**
     * Sets the theme for this captcha
     * @param string $value Theme to set it to, currently the api supports light, dark & auto
     * @return TurnstileCaptchaField
     */
    public function setTheme(string $value): TurnstileCaptchaField
    {
        $this->_captchaTheme = $value;

        return $this;
    }

    /**
     * Gets the theme for this captcha
     * @return string
     */
    public function getCaptchaTheme(): string
    {
        return $this->_captchaTheme;
    }

    /**
     * Gets the site key configured via .env variable this is used in the template
     * @return string
     */
    public function getSiteKey(): string
    {

        return Environment::getEnv('SS_TURNSTILE_SITE_KEY');
    }

    /**
     * Gets the site key configured via .env variable this is used in the template
     * @return string
     */
    public function getSecretKey(): string
    {
        return Environment::getEnv('SS_TURNSTILE_SECRET_KEY');
    }


    /**
     * Gets the form's id
     * @return ?string
     */
    public function getFormID(): ?string
    {
        return ($this->form ? $this->getTemplateHelper()->generateFormID($this->form) : null);
    }

    /**
     * @return array
     */
    public function getVerifyResponse(): array
    {
        return $this->verifyResponse;
    }

}
