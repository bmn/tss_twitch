<?php


namespace IPS\tsstwitch\modules\admin\settings;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * settings
 */
class _settings extends \IPS\Dispatcher\Controller
{
	/**
	 * Execute
	 *
	 * @return	void
	 */
	public function execute()
	{
		\IPS\Dispatcher::i()->checkAcpPermission( 'settings_manage' );
		parent::execute();
	}

	/**
	 * ...
	 *
	 * @return	void
	 */
	protected function manage()
	{
        $form = new \IPS\Helpers\Form;

        $prefix = 'tsstwitch_';

        $form->addMessage('form_intro');

        $form->add( new \IPS\Helpers\Form\Text($prefix.'client_id', \IPS\Settings::i()->tsstwitch_client_id,
            true, [], function($val) {
            if (!\preg_match('/^[a-z0-9]{30}[a-z0-9]*$/', $val)) {
                throw new \DomainException('form_bad_client_id');
            }
        }) );

        $form->add( new \IPS\Helpers\Form\Text($prefix.'client_secret', \IPS\Settings::i()->tsstwitch_client_secret,
            true, [], function($val) {
            if (!\preg_match('/^[a-z0-9]{30}$/', $val)) {
                throw new \DomainException('form_bad_client_secret');
            }
        }) );

        if ( $values = $form->values() )
        {
            $form->saveAsSettings();

            if (\IPS\tsstwitch\Twitch::i()->testClientSettings()) {
                $form->addMessage('form_saved_api_worked');
            }
            else {
                $form->addMessage('form_saved_api_failed');
            }
        }

        \IPS\Output::i()->title = \IPS\Member::loggedIn()->language()->addToStack('__app_tsstwitch');
        \IPS\Output::i()->output .= (string)$form;

    }
	
	// Create new methods with the same name as the 'do' parameter which should execute it
}