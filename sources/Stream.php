<?php

namespace IPS\tsstwitch;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
    header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
    exit;
}

class _Stream {

    public $isLive = false;

    protected $data = [];
    /*
        Field	        Type	    Description
        id	            string	    Stream ID.
        user_id	        string	    ID of the user who is streaming.
        user_login	    string	    Login of the user who is streaming.
        user_name	    string	    Display name corresponding to user_id.
        game_id	        string	    ID of the game being played on the stream.
        game_name	    string	    Name of the game being played.
        type	        string	    Stream type: "live" or "" (in case of error).
        title	        string	    Stream title.
        viewer_count	int	        Number of viewers watching the stream at the time of the query.
        started_at	    string	    UTC timestamp.
        language	    string	    Stream language. A language value is either the ISO 639-1 two-letter code for a supported stream language or “other”.
        thumbnail_url	string	    Thumbnail URL of the stream. All image URLs have variable width and height. You can replace {width} and {height} with any values to get that size image
        tag_ids	        string	    Shows tag IDs that apply to the stream.
        is_mature	    boolean	    Indicates if the broadcaster has specified their channel contains mature content that may be inappropriate for younger audiences.
        pagination	    {string}	A cursor value, to be used in a subsequent request to specify the starting point of the next set of results.
    */

    public function __construct(string $channelName = NULL, $seconds = 60) {
        if (isset($channelName)) {
            $streams = new Streams([$channelName], $seconds);
            if ($streams->liveChannelCount()) {
                $this->fromArrayExtraData($streams->channelData[0]);
            }
        }
    }

    public static function fromArray(array $array): Stream {
        $stream = new static();
        $stream->fromArrayExtraData($array);
        return $stream;
    }

    protected function fromArrayExtraData(array $array): void {
        $this->isLive = isset($array['started_at']);
        $this->data = $array;
    }

    public function url(): string {
        return "https://www.twitch.tv/{$this->user_login}";
    }

    public function thumbnailUrl(int $width = 640, int $height = 360): string {
        return str_replace(['{width}', '{height}'], [$width, $height], $this->thumbnail_url);
    }

    public function __get(string $key) {
        if (isset($this->data[$key])) {
            return $this->data[$key];
        }
    }

}
