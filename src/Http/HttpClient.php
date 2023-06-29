<?php

namespace Terraformers\TurnstileCaptcha\Http;

use GuzzleHttp\Client;
use SilverStripe\Core\Injector\Injectable;

class HttpClient
{
    use Injectable;

    protected ?Client $client;

    public function __construct(?Client $client = null)
    {
        if ($client === null) {
            $client = new Client();
        }

        $this->client = $client;
    }

    public function getClient(): Client
    {
        return $this->client;
    }
}
