<?php

$lang = array(
	'__app_tsstwitch'	=> "[TSS] Twitch Channel Status",
    'menu__tsstwitch_settings' => "[TSS] Twitch Status",
    'menu__tsstwitch_settings_settings' => 'Twitch API Settings',
    'tsstwitch_client_id' => "Twitch Client ID",
    'tsstwitch_client_secret' => "Twitch Client Secret",
    'form_bad_client_id' => 'The Client ID must be at least 30 characters a-z and 0-9.',
    'form_bad_client_secret' => 'The Client Secret must be 30 characters a-z and 0-9.',
    'form_saved_api_worked' => 'Saved and authenticated with the Twitch API successfully.',
    'form_saved_api_failed' => 'Saved, but the Twitch API did not accept this ID/Secret combination.',
    'form_intro' => <<<'html'
<p>This application needs a Client ID and Client Secret to communicate with the Twitch API. You can get some by registering IPS as an application in your <a href=https://dev.twitch.tv/console">Twitch Developer Console</a>.</p>
<p>For more information on registering an application, see <a href="https://dev.twitch.tv/docs/authentication/register-app">Registering Your App</a>.</p>
<p>Available values for live streams are at <a href="https://dev.twitch.tv/docs/api/reference#get-streams">Twitch API Reference &rarr; Get Streams</a>.</p>
html,
);
