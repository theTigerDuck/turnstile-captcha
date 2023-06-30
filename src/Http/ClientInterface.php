<?php

namespace Terraformers\TurnstileCaptcha\Http;

use GuzzleHttp\Client;

interface ClientInterface
{
    public function getClient(): Client;
}
