<?php

/**
 * @brief       Build Class
 * @author      -storm_author-
 * @copyright   -storm_copyright-
 * @package     IPS Social Suite
 * @subpackage  Dev Toolbox: Base
 * @since       1.2.0
 * @version     -storm_version-
 */

namespace IPS\toolbox;

use Exception;
use IPS\Application;
use IPS\Application\BuilderIterator;
use IPS\Data\Store;
use IPS\Http\Url;
use IPS\Log;
use IPS\Member;
use IPS\Output;
use IPS\Patterns\Singleton;
use IPS\Request;
use Phar;
use PharData;
use RuntimeException;
use Slasher\Slasher;
use ZipArchive;
use function chmod;
use function copy;
use function explode;
use function implode;
use function is_dir;
use function is_file;
use function mkdir;
use function sprintf;

class _Build extends Singleton
{
    protected static $instance;

    public function export()
    {
        if ( !Application::appIsEnabled( 'toolbox' ) || !\IPS\IN_DEV ) {
            throw new \InvalidArgumentException( 'toolbox not installed' );
        }

        $app = Request::i()->appKey;
        \IPS\toolbox\Application::loadAutoLoader();
        $application = Application::load( $app );
        $title = $application->_title;
        Member::loggedIn()->language()->parseOutputForDisplay( $title );
        $e = [];
        $newLong = $application->long_version + 1;
        $exploded = explode( '.', $application->version );
        $newShort = "{$exploded[0]}.{$exploded[1]}." . ( (int)$exploded[ 2 ] + 1 );
        $e = [];

        $e[] = [
            'name'     => 'toolbox_long_version',
            'class'    => '#',
            'label'    => 'Long Version',
            'required' => \true,
            'default'  => $newLong,
        ];
        $e[] = [
            'name'     => 'toolbox_short_version',
            'label'    => 'Short Version',
            'required' => \true,
            'default'  => $newShort,
        ];
        $e[] = [
            'name'        => 'toolbox_use_imports',
            'label'       => 'Use Imports',
            'description' => 'Instead of backslashing methods/constants, it will create imports.',
            'class'       => 'yn',
            'default'     => \true,
        ];
        $e[] = [
            'name'        => 'toolbox_skip',
            'class'       => 'stack',
            'label'       => 'Skip',
            'default'     => [ '3rdparty', 'vendor' ],
            'description' => 'Files or folders to skip using slasher on.',
        ];
        $form = Forms::execute( [ 'elements' => $e ] );

        if ( $values = $form->values() ) {
            $long = $values[ 'toolbox_long_version' ];
            $short = $values[ 'toolbox_short_version' ];
            $application->long_version = $long;
            $application->version = $short;
            $application->save();
            unset( Store::i()->applications );
            $slasherPath = \IPS\ROOT_PATH . '/applications/toolbox/sources/vendor/slasher.php';
            require_once $slasherPath;

            //lets slash them before we go forward
            $appPath = \IPS\ROOT_PATH . '/applications/' . $application->directory . '/';
            $args = [
                'foo.php',
                $appPath,
                '-all',
            ];

            if ( isset( $values[ 'toolbox_use_imports' ] ) && $values[ 'toolbox_use_imports' ] ) {
                $args[] = '-use';
            }

            if ( isset( $values[ 'toolbox_skip' ] ) && \is_array( $values[ 'toolbox_skip' ] ) ) {
                $skip = implode( ',', $values[ 'toolbox_skip' ] );
                $args[] = '-skip=' . $skip;
            }
            try {
                ( new Slasher( $args, \true ) )->execute();

                $path = \IPS\ROOT_PATH . '/' . $application->directory . '/' . $short . '/';

                try {
                    $application->build();
                    $application->assignNewVersion( $long, $short );
                    if ( !is_dir( $path ) ) {
                        if ( !mkdir( $path, \IPS\IPS_FOLDER_PERMISSION, \true ) && !is_dir( $path ) ) {
                            throw new RuntimeException( sprintf( 'Directory "%s" was not created', $path ) );
                        }
                        chmod( $path, \IPS\IPS_FOLDER_PERMISSION );
                    }
                    $pharPath = $path . $application->directory . '.tar';
                    $download = new PharData( $pharPath, 0, $application->directory . '.tar', Phar::TAR );
                    $download->buildFromIterator( new BuilderIterator( $application ) );

                } catch ( Exception $e ) {
                    Log::log( $e, 'phar' );
                }

            } catch ( \Exception $e ) {
            }

            $directions = \IPS\ROOT_PATH . '/applications/' . $application->directory . '/data/defaults/instructions.txt';
            $apps = [];

            if ( is_file( $directions ) ) {
                copy( $directions, $path . 'instructions.txt' );
                $apps[] = 'instructions.txt';
            }


            $apps[] = $application->directory . '.tar';

            $zip = new ZipArchive;
            if ( $zip->open( $path . $title . ' - ' . $short . '.zip', ZIPARCHIVE::CREATE ) === \true ) {
                foreach ( $apps as $app ) {
                    $zip->addFile( $path . $app, $app );
                }
                $zip->close();
            }
            unset( Store::i()->applications, $download );
            \Phar::unlinkArchive( $pharPath );
            $url = Url::internal( 'app=core&module=applications&controller=applications' );
            Output::i()->redirect( $url, $application->_title . ' successfully built!' );
        }

        Output::i()->title = 'Build ' . $application->_title;
        Output::i()->output = $form;
    }

}
