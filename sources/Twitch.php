<?php

namespace IPS\tsstwitch;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
    header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
    exit;
}

class _Twitch {

    const NAMESPACE = 'tsstwitch';

    const BYPASS_AUTH = 1;
    const ADD_TOKEN = 2;

    public $isAuthenticated = false;
    public $token;
    public $tokenLastUpdateTime = 0;
    public $tokenNextUpdateTime = 0;
    public $tokenLastValidationTime = 0;
    public $tokenNextValidationTime = 0;
    public $requestFailures = 0;
    public $requestAllowedTime = 0;

    protected $clientId;
    protected $clientSecret;
    protected $httpServerErrors = 0;

    protected static $instance;
    public static function i(): _Twitch
    {
        if (!isset(self::$instance)) {
            self::$instance = new static();
        }
        return self::$instance;
    }

    public function __construct() {
        $this->clientId = \IPS\Settings::i()->tsstwitch_client_id;
        $this->clientSecret = \IPS\Settings::i()->tsstwitch_client_secret;

        if (isset(\IPS\Data\Store::i()->tsstwitch_auth)) {
            $store = \IPS\Data\Store::i()->tsstwitch_auth;

            if (isset($store['token']))
                $this->token = $store['token'];
            if (isset($store['tokenLastUpdateTime']))
                $this->tokenLastUpdateTime = (int)$store['tokenLastUpdateTime'];
            if (isset($store['tokenNextUpdateTime']))
                $this->tokenNextUpdateTime = (int)$store['tokenNextUpdateTime'];
            if (isset($store['tokenLastValidationTime']))
                $this->tokenLastValidationTime = (int)$store['tokenLastValidationTime'];
            if (isset($store['tokenLastUpdateTime']))
                $this->tokenNextValidationTime = (int)$store['tokenNextValidationTime'];
            if (isset($store['requestFailures']))
                $this->requestFailures = (int)$store['requestFailures'];
            if (isset($store['requestAllowedTime']))
                $this->requestAllowedTime = (int)$store['requestAllowedTime'];
        }
    }

    protected function updateDataStore(): void {
        \IPS\Data\Store::i()->tsstwitch_auth = $this;
    }

    protected function validateToken(bool $bypass = false): void {
        $this->isAuthenticated = false;

        if ($bypass) {
            return;
        }

        if (!isset($this->token)) {
            $this->renewToken();
            return;
        }

        if ($this->tokenNextValidationTime > time()) {
            $this->isAuthenticated = true;
            return;
        }

        try {
            $request = \IPS\Http\Url::external('https://id.twitch.tv/oauth2/validate')
                ->request()
                ->setHeaders([
                    'Authorization' => "Bearer " . $this->token
                ]);

            $response = $this->get($request, NULL, self::BYPASS_AUTH);

            $this->isAuthenticated = true;
            $this->setNextValidationTime();

            \IPS\Log::log('Validated Twitch Client Credentials Flow token successfully', self::NAMESPACE);

            $this->updateDataStore();
        }
        catch (Http401ErrorException $e) {
            // token is invalid, renew it
            $this->renewToken();
        }
        catch (RequestException $e) {
            \IPS\Log::log("Error {$e->getCode()} validating Twitch Client Credentials Flow token:\n{$e->getMessage()}", self::NAMESPACE);
        }
        catch (ApiNotSetException $e) {}
    }

    protected function renewToken(): void {
        try {
            if (
                (!\preg_match('/^[a-z0-9]{30}[a-z0-9]*$/', $this->clientId)) ||
                (!\preg_match('/^[a-z0-9]{30}$/', $this->clientSecret))
            ) {
                \IPS\core\AdminNotification::send(self::NAMESPACE, 'ClientSettingsNotSet', NULL, NULL, NULL, true);
                throw new ApiNotSetException('Twitch API settings have not been set up correctly yet.');
            }

            $request = \IPS\Http\Url::external('https://id.twitch.tv/oauth2/token')
                ->request();

            $response = $this->post($request, [
                'client_id' => $this->clientId,
                'client_secret' => $this->clientSecret,
                'grant_type' => 'client_credentials'
            ], self::BYPASS_AUTH);

            //self::setToken($response['access_token'], $response['expires_in']);
            $this->isAuthenticated = true;
            $this->token = $response['access_token'];
            $this->tokenLastUpdateTime = time();
            $this->tokenNextUpdateTime = time() + $response['expires_in'];
            $this->setNextValidationTime();

            \IPS\Log::log('Received new Twitch Client Credentials Flow token successfully', self::NAMESPACE);

            $this->updateDataStore();
        }
        catch (RequestException $e) {
            \IPS\Log::log("Error {$e->getCode()} requesting new Twitch Client Credentials Flow token:\n{$e->getMessage()}", self::NAMESPACE);
        }
        catch (ApiNotSetException $e) {}
    }

    protected function setNextValidationTime(): void {
        $this->tokenLastValidationTime = \time();
        $this->tokenNextValidationTime = \time() + 3600;
    }

    public function get(\IPS\HTTP\Request\Curl $request, $data = NULL, $options = 0) {
        $bypassAuth = $options & self::BYPASS_AUTH;
        $this->validateToken($bypassAuth);
        if ((!$bypassAuth) && (!$this->isAuthenticated)) {
            throw new RequestException();
        }

        if ($options & self::ADD_TOKEN) {
            $this->addTokenToRequest($request);
        }

        try {
            $response = $request->get($data);
            return $this->decodeResponse($response);
        }
        catch(Http503ErrorException $e) {
            // Try again once if we get a 503
            return $this->get($request, $data);
        }
        catch(\IPS\Http\Request\Exception $e) {

        }
        catch(\RuntimeException $e) {

        }
    }

    public function post(\IPS\HTTP\Request\Curl $request, $data = NULL, $options = 0) {
        $bypassAuth = $options & self::BYPASS_AUTH;
        $this->validateToken($bypassAuth);
        if ((!$bypassAuth) && (!$this->isAuthenticated)) {
            throw new RequestException();
        }

        if ($options & self::ADD_TOKEN) {
            $this->addTokenToRequest($request);
        }

        try {
            $response = $request->post($data);
            return $this->decodeResponse($response);
        }
        catch(Http503ErrorException $e) {
            // Try again once if we get a 503
            return $this->post($request, $data);
        }
        catch(\IPS\Http\Request\Exception $e) {

        }
        catch(\RuntimeException $e) {

        }
    }

    protected function addTokenToRequest(\IPS\HTTP\Request\Curl $request): void {
        $request->setHeaders([
            'Client-Id' => $this->clientId,
            'Authorization' => 'Bearer ' . $this->token
        ]);
    }

    protected function decodeResponse(\IPS\Http\Response $response) {
        if ($response->isSuccessful()) {
            $json = $response->decodeJson();

            if (!isset($json['status'])) {
                self::resetRequestFailures();
                return $json;
            }

            switch ($json['status']) {
                case 401: // token no longer valid
                    throw new Http401ErrorException($json['message']);
                    break;
                default:
                    self::incRequestFailures();
                    throw new RequestException($json['message'], $json['status']);
                    break;
            }
        }
        else {
            switch ($response->httpResponseCode) {
                case 429: // over rate limit
                    if (isset($response->httpHeaders['Ratelimit-Reset'])) {
                        $this->requestAllowedTime = $response->httpHeaders['Ratelimit-Reset'];
                    }
                    self::incRequestFailures();
                    throw new RequestException('over rate limit', 429);
                    break;
                case 503: // server error
                    if ($this->httpServerErrors++ < 1) {
                        throw new Http503ErrorException();
                    }
                    else {
                        self::incRequestFailures();
                        throw new RequestException('server error', 503);
                    }
                    break;
            }
        }
    }

    protected function incRequestFailures(): void {
        $this->requestFailures++;
        $minutes = max($this->requestFailures, 10);
        $this->requestAllowedTime = time() + ($minutes * 60);
        $this->updateDataStore();
    }

    protected function resetRequestFailures(): void {
        $this->requestFailures = 0;
        $this->requestAllowedTime = 0;
        $this->updateDataStore();
    }

    public function testClientSettings(): bool {
        try {
            $this->token = NULL;
            $this->requestAllowedTime = 0;
            $this->updateDataStore();
            $this->renewToken();
            return $this->isAuthenticated;
        }
        catch (\Exception $e) {
            return false;
        }
    }

}