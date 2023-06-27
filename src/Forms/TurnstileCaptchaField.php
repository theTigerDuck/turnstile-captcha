<?php

namespace Terraformers\TurnstileCaptcha\Forms;

use Psr\Log\LoggerInterface;
use SilverStripe\Control\Controller;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Forms\FormField;
use SilverStripe\i18n\i18n;
use SilverStripe\View\Requirements;
use Locale;

class TurnstileCaptchaField extends FormField
{
    /**
     * Recaptcha Site Key
     * @config TurnstileCaptchaField.site_key
     */
    private static ?string $site_key = null;

    /**
     * Recaptcha Secret Key
     * @config TurnstileCaptchaField.secret_key
     */
    private static ?string $secret_key = null;


    /**
     * CURL Proxy Server location
     * @config TurnstileCaptchaField.proxy_server
     */
    private static ?string $proxy_server = null;

    /**
     * CURL Proxy authentication
     * @config TurnstileCaptchaField.proxy_auth
     */
    private static ?string $proxy_auth = null;

    /**
     * CURL Proxy port
     * @config TurnstileCaptchaField.proxy_port
     */
    private static $proxy_port;

    /**
     * Verify SSL Certificates
     * @config TurnstileCaptchaField.verify_ssl
     * @default true
     */
    private static bool $verify_ssl = true;

    /**
     * Captcha theme, currently options are light and dark
     * @default light
     */
    private static string $default_theme = 'light';


    /**
     * Whether form submit events are handled directly by this module.
     * If false, a function is provided that can be called by user code submit handlers.
     * @default true
     */
    private static bool $default_handle_submit = true;

    /**
     * TurnstileCaptcha Site Key
     * Configurable via Injector config
     */
    protected ?string $_siteKey = null;

    /**
     * TurnstileCaptcha Site Key
     * Configurable via Injector config
     */
    protected ?string $_secretKey = null;

    /**
     * CURL Proxy Server location
     * Configurable via Injector config
     */
    protected ?string $_proxyServer = null;

    /**
     * CURL Proxy authentication
     * Configurable via Injector config
     */
    protected ?string $_proxyAuth = null;

    /**
     * CURL Proxy port
     * Configurable via Injector config
     */
    protected $_proxyPort;

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
        $secretKey = $this->_secretKey ? $this->_secretKey : self::config()->secret_key;

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
     * @return bool Returns boolean true if valid false if not
     */
    public function validate($validator)
    {

        $request = Controller::curr()->getRequest();
        $recaptchaResponse = $request->requestVar('cf-turnstile-response');

        if (!isset($recaptchaResponse)) {
            $validator->validationError($this->name, _t(
                'Terraformers\\TurnstileCaptcha\\Forms\\TurnstileCaptchaField.NOSCRIPT',
                'if you do not see the captcha you must enable JavaScript'),
                'validation');
            return false;
        }

        $curlOptions = [];
        $secret_key = $this->_secretKey ?: self::config()->secret_key;
        $proxy_server = $this->_proxyServer ?: self::config()->proxy_server;
        if (!empty($proxy_server)) {
            $curlOptions[CURLOPT_PROXY] = $proxy_server;

            $proxy_auth = $this->_proxyAuth ?: self::config()->proxy_auth;
            if (!empty($proxy_auth)) {
                $curlOptions[CURLOPT_PROXYUSERPWD] = $proxy_auth;
            }

            $proxy_port = $this->_proxyPort ?: self::config()->proxy_port;
            if (!empty($proxy_port)) {
                $curlOptions[CURLOPT_PROXYPORT] = $proxy_port;
            }
        }
        $curlOptions[CURLOPT_RETURNTRANSFER] = true;
        $curlOptions[CURLOPT_SSL_VERIFYPEER] = self::config()->verify_ssl;
        $curlOptions[CURLOPT_USERAGENT] = str_replace(',', '/', 'SilverStripe');
        $client = HttpClient::singleton();

        $response = $client->request('POST', 'https://challenges.cloudflare.com/turnstile/v0/siteverify', [
            'json' => [
                'secret' => $secret_key,
                'response' => $recaptchaResponse,
                'remoteip' => $request->getIP(),
            ],
            'curl' => $curlOptions
        ]);

        if ($response->getStatusCode() !== 200) {
            $validator->validationError($this->name, _t(
                'Terraformers\\TurnstileCaptcha\\Forms\\TurnstileCaptchaField.VALIDATE_ERROR',
                '_Captcha could not be validated'),
                'validation');
            $logger = Injector::inst()->get(LoggerInterface::class);
            $logger->error(
                'Turnstile Captch Field validation failed as request was not successful.'
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
     * Gets the site key configured via TurnstileCaptchaField.site_key this is used in the template
     * @return string
     */
    public function getSiteKey(): string
    {
        return $this->_sitekey ? $this->_sitekey : self::config()->site_key;
    }

    /**
     * Setter for _siteKey, this will override the injector or environment variable configuration
     */
    public function setSiteKey($key)
    {
        $this->_sitekey = $key;
    }

    /**
     * Setter for _secretKey, this will override the injector or environment variable configuration
     */
    public function setSecretKey($key)
    {
        $this->_secretKey = $key;
    }

    /**
     * Setter for _proxyServer, this will override the injector or environment variable configuration
     */
    public function setProxyServer($server)
    {
        $this->_proxyServer = $server;
    }

    /**
     * Setter for _proxyAuth, this will override the injector or environment variable configuration
     */
    public function setProxyAuth($auth)
    {
        $this->_proxyAuth = $auth;
    }

    /**
     * Setter for _proxyPort, this will override the injector or environment variable configuration
     */
    public function setProxyPort($port)
    {
        $this->_proxyPort = $port;
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
