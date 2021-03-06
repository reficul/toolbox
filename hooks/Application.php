//<?php


use IPS\toolbox\DevCenter\Headerdoc;
use IPS\toolbox\DevFolder\Applications;

if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) ) {
    exit;
}

/**
 * Class toolbox_hook_Application
 *
 * @mixin \IPS\Application
 */
class toolbox_hook_Application extends _HOOK_CLASS_
{
    /**
     * @inheritdoc
     */
    public function assignNewVersion( $long, $human )
    {
        parent::assignNewVersion( $long, $human );
        if ( static::appIsEnabled( 'toolbox' ) ) {
            $this->version = $human;
            Headerdoc::i()->process( $this );
        }
    }

    /**
     * @inheritdoc
     */
    public function build()
    {
        if ( static::appIsEnabled( 'toolbox' ) ) {
            Headerdoc::i()->addIndexHtml( $this );
        }
        parent::build();
    }

    /**
     * @inheritdoc
     */
    public function installOther()
    {
        if ( \IPS\IN_DEV ) {
            $dir = \IPS\ROOT_PATH . '/applications/' . $this->directory . '/dev/';
            if ( !\file_exists( $dir ) ) {
                try {
                    $app = new Applications( $this );
                    $app->addToStack = \true;
                    $app->email();
                    $app->javascript();
                    $app->language();
                    $app->templates();
                } catch ( Exception $e ) {
                }
            }
        }

        parent::installOther();
    }


}
