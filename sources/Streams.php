<?php

namespace IPS\tsstwitch;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
    header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
    exit;
}

class _Streams {

    const HASH_PREFIX = 'tsstwitch_channels_';

    public $hash;
    public $channelNames = [];
    public $channels = [];
    public $channelData = [];
    public $lastUpdateTime = 0;
    public $nextUpdateTime = 0;
    public $updated = false;

    protected $updateSeconds = 60;

    public function __construct(array $channels, int $updateSeconds = 60)
    {
        $this->channelNames = $channels;
        $this->hash = $this->hash();
        $fullHash = $this->hash(self::HASH_PREFIX);

        $this->updateSeconds = $updateSeconds;

        if (isset(\IPS\Data\Store::i()->$fullHash)) {
            $store = \IPS\Data\Store::i()->$fullHash;

            if (isset($store['channelData']))
                $this->channelData = $store['channelData'];
            if (isset($store['channelNames']))
                $this->channelNames = $store['channelNames'];
            if (isset($store['lastUpdateTime']))
                $this->lastUpdateTime = $store['lastUpdateTime'];
            if (isset($store['nextUpdateTime']))
                $this->nextUpdateTime = $store['nextUpdateTime'];
        }
    }

    public function channelCount(): int {
        return \count($this->channelNames);
    }

    public function liveChannelCount(): int {
        return \count($this->channels());
    }

    public function channels(): array {
        static $createdChannelObjects = false;

        if ($this->nextUpdateTime < time()) {
            $this->requestChannelStatus();
            $createdChannelObjects = false;
        }

        if (!$createdChannelObjects) {
            $this->channels = array_map(function ($channel): Stream {
                return Stream::fromArray($channel);
            }, $this->channelData);
            $createdChannelObjects = true;
        }

        return $this->channels;
    }

    public function firstChannel(): array {
        $channels = $this->channels();
        return (\count($channels)) ? $channels[0] : [];
    }

    public function requestChannelStatus() {
        if (!\count($this->channelNames)) {
            return;
        }

        $qsParts = [];
        foreach ($this->channelNames as $name) {
            $qsParts[] = "user_login=$name";
        }
        $queryString = \join('&', $qsParts);

        try {
            $request = \IPS\Http\Url::external("https://api.twitch.tv/helix/streams?$queryString")
                ->request();

            $response = Twitch::i()->get($request, NULL, Twitch::ADD_TOKEN);

            $this->channelData = $response['data'];
            $this->lastUpdateTime = time();
            $this->nextUpdateTime = time() + $this->updateSeconds;
            $this->updated = true;

            \IPS\Log::debug('Refreshed Twitch channel status successfully', Twitch::NAMESPACE);

            $this->updateDataStore();
        }
        catch(RequestException $e) {
            \IPS\Log::log("Error {$e->getCode()} refreshing Twitch channel status, using last good status instead:\n{$e->getMessage()}", Twitch::NAMESPACE);
        }
        catch (ApiNotSetException $e) {}
    }

    protected function hash(string $prefix = "", string $postfix = ""): string {
        return $prefix . md5(join(',', $this->channelNames)) . $postfix;    }

    protected function updateDataStore(): void {
        $hash = $this->hash(self::HASH_PREFIX);
        \IPS\Data\Store::i()->$hash = $this;
    }


}