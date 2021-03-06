<?php

/**
 * @brief       Proxyclass Class
 * @author      -storm_author-
 * @copyright   -storm_copyright-
 * @package     IPS Social Suite
 * @subpackage  Dev Toolbox: Proxy Class Generator
 * @since       1.0.0
 * @version     -storm_version-
 */

namespace IPS\toolbox\Proxy;

use Exception;
use IPS\Data\Store;
use IPS\Patterns\Singleton;
use IPS\Settings;
use IPS\toolbox\GitHooks;
use IPS\toolbox\Proxy\Generator\Applications;
use IPS\toolbox\Proxy\Generator\Db as GeneratorDb;
use IPS\toolbox\Proxy\Generator\Extensions;
use IPS\toolbox\Proxy\Generator\Language;
use IPS\toolbox\Proxy\Generator\Moderators;
use IPS\toolbox\Proxy\Generator\Proxy;
use IPS\toolbox\Proxy\Generator\Templates;
use IPS\toolbox\Proxy\Generator\Url;
use IPS\toolbox\Shared\Read;
use IPS\toolbox\Shared\Replace;
use IPS\toolbox\Shared\Write;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use function array_keys;
use function asort;
use function chmod;
use function count;
use function defined;
use function file_get_contents;
use function header;
use function in_array;
use function is_array;
use function is_dir;
use function iterator_to_array;
use function json_decode;
use function json_encode;
use function mkdir;
use function preg_match;

if ( !defined( '\IPS\SUITE_UNIQUE_KEY' ) ) {
    header( ( $_SERVER[ 'SERVER_PROTOCOL' ] ?? 'HTTP/1.0' ) . ' 403 Forbidden' );
    exit;
}

/**
 * Class _Proxyclass
 *
 * @package IPS\toolbox\Proxy
 */
class _Proxyclass extends Singleton
{
    use Read, Write, Replace;

    /**
     * @inheritdoc
     */
    protected static $instance;

    /**
     * stder resource
     *
     * @var
     */
    protected static $fe;

    /**
     * the save location for the proxyclasses
     *
     * @var string
     */
    public $save = 'dtProxy';

    /**
     * build the proxy properties
     *
     * @var bool
     */
    public $doProps = \true;

    /**
     * build the header info
     *
     * @var bool
     */
    public $doHeader = \true;

    /**
     * build the proxy constants
     *
     * @var bool
     */
    public $doConstants = \true;

    /**
     * builds the metadata
     *
     * @var bool
     */
    public $doProxies = \true;

    /**
     * stores templates data
     *
     * @var array
     */
    public $templates = [];

    /**
     * @var bool
     */
    public $console = \false;

    /**
     * @var string
     */
    protected $meta = '';

    /**
     * _Proxyclass constructor.
     *
     * @param bool $console
     */
    public function __construct( bool $console = \false )
    {
        $this->console = $console;
        if ( defined( '\BYPASSPROXYDT' ) && \BYPASSPROXYDT === \true ) {
            $this->save = 'dtProxy2';
        }
        \IPS\toolbox\Application::loadAutoLoader();
        $this->blanks = \IPS\ROOT_PATH . '/applications/toolbox/data/defaults/';
        if ( !Settings::i()->dtproxy_do_props ) {
            $this->doProps = \false;
        }

        if ( !Settings::i()->dtproxy_do_constants ) {
            $this->doConstants = \false;
        }

        if ( !Settings::i()->dtproxy_do_proxies ) {
            $this->doProxies = \false;
        }
    }

    /**
     * this is used for the controller and the MR
     *
     * @param array $data
     *
     * @return array|null
     * @throws \InvalidArgumentException
     * @throws \OutOfRangeException
     */
    public function run( array $data = [] )
    {
        $i = 0;
        $totalFiles = 0;
        $iterator = 0;
        if ( isset( Store::i()->dtproxy_proxy_files ) ) {
            if ( isset( Store::i()->dtproxy_templates ) ) {
                $this->templates = Store::i()->dtproxy_templates;
            }

            /**
             * @var $iterator array
             */
            $iterator = Store::i()->dtproxy_proxy_files;
            $totalFiles = $data[ 'total' ] ?? 0;
            $limit = 1;
            if ( !isset( $data[ 'firstRun' ] ) ) {
                $limit = 250;
            }

            foreach ( $iterator as $key => $file ) {
                $i++;
                $filePath = $file;
                $this->build( $filePath );
                unset( $iterator[ $key ] );
                if ( $i === $limit ) {
                    break;
                }
            }

            unset( Store::i()->dtproxy_proxy_files );
        }

        if ( $i ) {
            if ( is_array( $iterator ) && count( $iterator ) ) {
                Store::i()->dtproxy_proxy_files = $iterator;
            }

            if ( is_array( $this->templates ) && count( $this->templates ) ) {
                Store::i()->dtproxy_templates = $this->templates;
            }

            if ( $data[ 'current' ] ) {
                $offset = $data[ 'current' ] + $i;
            }
            else {
                $offset = $i;
            }

            return [ 'total' => $totalFiles, 'current' => $offset, 'progress' => $data[ 'progress' ] ];
        }

        /**
         * @todo this is ugly, we should improve this!
         */
        $steps = 0;
        $step = $data[ 'step' ] ?? \null;
        $lastStep = $step;
        $complete = $data[ 'complete' ] ?? 0;
        if ( $this->doConstants ) {
            if ( $step === \null ) {
                $step = 'constants';
            }
            $steps++;
        }

        /**
         * @todo this will get annoying sooner or later, should find a better way to handle the "totals"
         */
        if ( $this->doProxies ) {
            $steps += 7;
        }
        else if ( $step === 'apps' ) {
            $step = \null;
        }

        if ( $step === 'constants' ) {
            Proxy::i()->buildConstants();
            $complete++;
            $lastStep = $step;
            $step = 'apps';
            return [ 'step' => $step, 'lastStep' => $lastStep, 'tot' => $steps, 'complete' => $complete ];
        }

        if ( $this->doProxies ) {
            $step = $this->makeToolboxMeta( $step );
            $complete++;
        }
        else {
            $step = \null;
        }

        if ( $step === \null ) {
            ( new GitHooks( \IPS\Application::applications() ) )->writeSpecialHooks();
            Proxy::i()->generateSettings();
            unset( Store::i()->dtproxy_proxy_files, Store::i()->dtproxy_templates );
            return \null;
        }

        return [ 'step' => $step, 'lastStep' => $lastStep, 'tot' => $steps, 'complete' => $complete ];
    }

    /**
     * this will take a file path, and create proxy classes from it
     *
     * @param $file
     */
    public function build( $file )
    {
        $finder = new \SplFileInfo( $file );
        $content = $this->_getFileByFullPath( $file );

        if ( $finder->getExtension() === 'phtml' ) {
            $methodName = $finder->getBasename( '.' . $finder->getExtension() );
            preg_match( '/^<ips:template parameters="(.+?)?" \/>(\r\n?|\n)/', $content, $params );

            if ( isset( $params[ 0 ] ) ) {
                $parameters = \null;
                if ( isset( $params[ 1 ] ) ) {
                    $parameters = $params[ 1 ];
                }

                $this->templates[ $file ] = [ 'method' => $methodName, 'params' => $parameters ];
            }
        }
        else if ( $finder->getExtension() === 'php' ) {
            Proxy::i()->create( $content );
        }
    }

    /**
     * makes the files for php-toolbox plugin
     *
     * @param $step
     *
     * @return null|string
     */
    public function makeToolboxMeta( $step )
    {
        if ( $this->doProxies ) {
            switch ( $step ) {
                default:
                    $path = \IPS\ROOT_PATH . '/applications/toolbox/data/defaults/';
                    $jsonMeta = json_decode( file_get_contents( $path . 'defaults.json' ), \true );
                    $jsonMeta2 = json_decode( file_get_contents( $path . 'defaults2.json' ), \true );
                    $jsonMeta += $jsonMeta2;
                    Store::i()->dt_json = $jsonMeta;
                    Applications::i()->create();
                    $step = 'db';
                    break;
                case 'db':
                    GeneratorDb::i()->create();
                    $step = 'lang';
                    break;
                case 'lang':
                    Language::i()->create();
                    $step = 'ext';
                    break;
                case 'ext':
                    Extensions::i()->create();
                    $step = 'temp';
                    break;
                case 'temp':
                    Templates::i()->create();
                    $step = 'mod';
                    break;
                case 'mod':
                    Moderators::i()->create();
                    $step = 'url';
                    break;
                case 'url':
                    Url::i()->create();
                    $step = 'json';
                    break;
                case 'json':
                    $this->makeJsonFile();
                    $step = \null;
                    break;
            }
        }
        else {
            $step = \null;
        }

        return $step;
    }

    /**
     * creates the .ide-toolbox.metadta.json
     */
    public function makeJsonFile()
    {
        if ( isset( Store::i()->dt_json ) ) {
            $content = json_encode( Store::i()->dt_json, \JSON_PRETTY_PRINT );
            $this->_writeFile( '.ide-toolbox.metadata.json', $content, \IPS\ROOT_PATH . '/' . $this->save );
            unset( Store::i()->dt_json );
        }
    }

    /**
     * closes the output buffer
     */
    public function consoleClose()
    {
        if ( static::$fe ) {
            \fclose( static::$fe );
        }
    }

    /**
     * @throws \InvalidArgumentException
     * @throws \OutOfRangeException
     */
    public function cli( $fp )
    {
        if ( is_array( $this->templates ) && count( $this->templates ) ) {
            Store::i()->dtproxy_templates = $this->templates;
        }
        Proxyclass::i()->console = \true;
        $files = Proxyclass::i()->dirIterator( $fp, \true );
        $total = $files->count();

        $processed = 0;
        foreach ( $files as $file ) {
            $filePath = $file->getRealPath();
            //        Proxyclass::i()->console('Processing File: ' . $filePath);
            $this->build( $filePath );
            $processed++;
            $this->bump( $total, $processed );
        }
        Store::i()->dtproxy_templates = $this->templates;
        $this->console( 'Generating Additional Proxy Files and PHP-Toolbox files' );
        Proxy::i()->buildConstants();
        $this->console( 'Constants 1/10' );
        Proxy::i()->generateSettings();
        $this->console( 'Settings 2/10' );
        if ( $this->doProxies ) {
            $path = \IPS\ROOT_PATH . '/applications/toolbox/data/defaults/';
            $jsonMeta = json_decode( file_get_contents( $path . 'defaults.json' ), \true );
            $jsonMeta2 = json_decode( file_get_contents( $path . 'defaults2.json' ), \true );
            $jsonMeta += $jsonMeta2;
            Store::i()->dt_json = $jsonMeta;

            Applications::i()->create();
            $this->console( 'App List 3/10' );

            GeneratorDb::i()->create();
            $this->console( 'DB List 4/10' );

            Language::i()->create();
            $this->console( 'Language List 5/10' );

            Extensions::i()->create();
            $this->console( 'Extensions List 6/10' );

            Templates::i()->create();
            $this->console( 'Templates List 7/10' );

            Moderators::i()->create();
            $this->console( 'Moderator Perms List 8/10' );

            Url::i()->create();
            $this->console( 'URL/FURL List 9/10' );

            $this->makeJsonFile();
            $this->console( 'Generating PHP-Toolbox json 10/10' );
        }

        unset( Store::i()->dtproxy_proxy_files, Store::i()->dtproxy_templates );
    }

    /**
     * adds a # for percents (10%)
     *
     * @param $total
     * @param $done
     */
    public function bump( $total, $done )
    {
        $old = $total;
        $total /= 10;

        if ( $done % $total === 0 ) {
            if ( static::$fe === \null ) {
                static::$fe = \fopen( 'php://stdout', 'wb' );
            }
            \fwrite( static::$fe, '#' );
        }

        if ( $old === $done ) {
            $this->console( \PHP_EOL . 'File Processing Done!' );
        }
    }

    /**
     * prints to console
     *
     * @param $msg
     */
    public function console( $msg )
    {
        if ( $this->console ) {
            if ( static::$fe === \null ) {
                static::$fe = \fopen( 'php://stdout', 'wb' );
            }
            \fwrite( static::$fe, $msg . \PHP_EOL );
        }
    }

    /**
     * this will iterator over directorys to find a list of php files to process, used in both the MR and CLI.
     *
     * @param null $dir
     * @param bool $returnIterator
     *
     * @return int|Finder
     */
    public function dirIterator( $dir = \null, $returnIterator = \false )
    {
        $this->console( 'Starting File Processing' );
        $ds = \DIRECTORY_SEPARATOR;
        $root = \IPS\ROOT_PATH;
        $save = $root . $ds . $this->save . $ds;
        $finder = new Finder();
        try {
            if ( $dir === \null ) {

                if ( is_dir( $save ) ) {
                    $this->emptyDirectory( $save );
                }

                if ( !mkdir( $save ) && !is_dir( $save ) ) {
                    chmod( $save, 0777 );
                }

                if ( !mkdir( $save . 'class' . $ds ) && !is_dir( $save . 'class' . $ds ) ) {
                    chmod( $save . 'class/', 0777 );
                }

                if ( !mkdir( $save . 'templates' . $ds ) && !is_dir( $save . 'templates' . $ds ) ) {
                    chmod( $save . 'templates' . $ds, 0777 );
                }

                if ( !mkdir( $save . 'extensions' . $ds ) && !is_dir( $save . 'extensions' . $ds ) ) {
                    chmod( $save . 'extensions' . $ds, 0777 );
                }

                foreach ( $this->lookIn() as $dirs ) {
                    if ( is_dir( $dirs ) ) {
                        $finder->in( $dirs );
                    }
                }
            }
            else {
                $finder->in( $dir );
            }

            foreach ( $this->excludedDir() as $dirs ) {
                $finder->exclude( $dirs );
            }

            foreach ( $this->excludedFiles() as $file ) {
                $finder->notName( $file );
            }

            $filter = function ( \SplFileInfo $file )
            {
                if ( !in_array( $file->getExtension(), [ 'php', 'phtml' ] ) ) {
                    return \false;
                }
                return \true;
            };

            if ( isset( Store::i()->dtproxy_proxy_files ) ) {
                unset( Store::i()->dtproxy_proxy_files );
            }

            $finder->filter( $filter )->files();

            if ( $returnIterator ) {
                return $finder;
            }

            $files = array_keys( iterator_to_array( $finder ) );
            asort( $files );
            Store::i()->dtproxy_proxy_files = $files;
            $this->console( 'Folder processing done.' );
            return $finder->count();
        } catch ( Exception $e ) {
            return 0;
        }
    }

    /**
     * empties a directroy, use with caution!
     *
     * @param $dir
     *
     * @throws \Symfony\Component\Filesystem\Exception\IOException
     */
    public function emptyDirectory( $dir )
    {
        $fs = new Filesystem();
        $fs->remove( $dir );
    }

    /**
     * paths to look in for php and phtml files in dirIterator
     *
     * @return array
     */
    protected function lookIn(): array
    {
        $ds = \DIRECTORY_SEPARATOR;

        return [
            \IPS\ROOT_PATH . $ds . 'applications',
            \IPS\ROOT_PATH . $ds . 'system',
        ];
    }

    /**
     * directories to exclude when dirIterator runs
     *
     * @return array
     */
    protected function excludedDir(): array
    {
        return [
            'api',
            'interface',
            'data',
            'hooks',
            'extensions',
            'setup',
            'tasks',
            'widgets',
            '3rdparty',
            '3rd_party',
            'vendor',
            'themes',
            'StormTemplates',
            'ckeditor',
            'hook_templates',
            'dtbase_templates',
            'hook_temp',
            'dtProxy',
            'plugins',
            'uploads',
            'oauth',
            'app',
            'web',
        ];
    }

    /**
     * files excluded when dirIterator runs
     *
     * @return array
     */
    protected function excludedFiles(): array
    {
        return [
            '.htaccess',
            'lang.php',
            'jslang.php',
            'HtmlPurifierDefinitionCache.php',
            'HtmlPurifierInternalLinkDef.php',
            'HtmlPurifierSrcsetDef.php',
            'HtmlPurifierSwitchAttrDef.php',
            'sitemap.php',
            'conf_global.php',
            'conf_global.dist.php',
            '404error.php',
            'error.php',
            'test.php',

        ];
    }

    /**
     * @param      $file
     * @param bool $isTemplate
     */
    public function buildAndMake( $file, $isTemplate = \false )
    {
        $this->build( $file );
        if ( $isTemplate ) {
            $this->makeTemplateList();
        }
    }
}
