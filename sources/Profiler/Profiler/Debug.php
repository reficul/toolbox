<?php

/**
 * @brief       Debug Active Record
 * @author      -storm_author-
 * @copyright   -storm_copyright-
 * @package     IPS Social Suite
 * @subpackage  toolbox
 * @since       -storm_since_version-
 * @version     -storm_version-
 */

namespace IPS\toolbox\Profiler\Profiler;

use Exception;
use IPS\Db;
use IPS\Patterns\ActiveRecord;
use IPS\Patterns\ActiveRecordIterator;
use IPS\Request;
use IPS\Theme;
use function count;
use function defined;
use function get_class;
use function header;
use function htmlentities;
use function is_array;
use function json_decode;
use function json_encode;
use function method_exists;
use function nl2br;
use function time;

if ( !defined( '\IPS\SUITE_UNIQUE_KEY' ) ) {
    header( ( $_SERVER[ 'SERVER_PROTOCOL' ] ?? 'HTTP/1.0' ) . ' 403 Forbidden' );
    exit;
}

/**
 * Class _Debug
 *
 * @package IPS\toolbox\Profiler
 * @mixin \IPS\toolbox\Profiler\Debug
 */
class _Debug extends ActiveRecord
{
    /**
     * @brief    [ActiveRecord] Database Prefix
     */
    public static $databasePrefix = 'debug_';

    /**
     * @brief    [ActiveRecord] Database table
     */
    public static $databaseTable = 'dtprofiler_debug';

    /**
     * @brief   Bitwise keys
     */
    public static $bitOptions = [
        'bitoptions' => [
            'bitoptions' => [],
        ],
    ];

    /**
     * @brief    [ActiveRecord] Multiton Store
     */
    protected static $multitons = [];

    /**
     * adds a debug message to the log
     *
     * @param $key
     * @param $message
     */
    public static function add( $key, $message, $ajax = \false )
    {
        if ( !\IPS\QUERY_LOG && defined( '\DTPROFILER' ) && !\DTPROFILER ) {
            return;
        }

        $debug = new static;
        $debug->key = $key;

        if ( $message instanceof Exception ) {
            $data[ 'class' ] = get_class( $message );
            $data[ 'ecode' ] = $message->getCode();

            if ( method_exists( $message, 'extraLogData' ) ) {
                $data[ 'message' ] = $message->extraLogData() . "\n" . $message->getMessage();
            }
            else {
                $data[ 'message' ] = $message->getMessage();
            }

            $data[ 'backtrace' ] = nl2br( htmlentities( $message->getTraceAsString() ) );
            $type = 'exception';
            $message = json_encode( $data );
        }
        else if ( is_array( $message ) ) {
            $message = json_encode( $message );
            $type = 'array';
        }
        else {
            $type = 'string';
        }

        $debug->type = $type;
        $debug->log = $message;
        $debug->time = time();
        if ( Request::i()->isAjax() || $ajax === \true ) {
            $debug->ajax = 1;
        }
        $debug->save();
    }

    /**
     * @return null
     * @throws \UnexpectedValueException
     */
    public static function build()
    {
        $sql = Db::i()->select( '*', 'dtprofiler_debug', [
            'debug_ajax = ? AND debug_viewed = ?',
            0,
            0,
        ], 'debug_id DESC', [ 0, 100 ] );
        $iterators = new ActiveRecordIterator( $sql, Debug::class );
        $list = [];
        $last = 0;

        /* @var \IPS\toolbox\Profiler\Debug $obj */
        foreach ( $iterators as $obj ) {
            $list[] = $obj->body();
            $obj->viewed();
            $last = $obj->id;
        }
        try {
            Db::i()->update( 'dtprofiler_debug', [ 'debug_viewed' => 1 ] );
        } catch ( Db\Exception $e ) {
        }

        $count = count( $list ) ?: 0;
        return Theme::i()->getTemplate( 'generic', 'toolbox', 'front' )->button( 'Debug', 'debug', 'List of debug messages', $list, $count, 'bug', \true, $count ? \false : \true, $last, \true );

    }

    /**
     * the body of the message
     *
     * @throws \UnexpectedValueException
     */
    public function body(): string
    {
        if ( $this->type === 'exception' || $this->type === 'array' ) {
            $message = json_decode( $this->log, \true );
            $list = Theme::i()->getTemplate( 'generic', 'toolbox', 'front' )->keyvalue( $message, $this->name );
        }
        else {
            $list = Theme::i()->getTemplate( 'generic', 'toolbox', 'front' )->string( $this->log, $this->name );
        }

        return $list;
    }

    /**
     * updates a debug viewed status
     */
    public function viewed()
    {
        $this->viewed = 1;
        $this->save();
    }

    /**
     * @return string
     */
    public function get_name(): string
    {
        return '#' . $this->_data[ 'id' ] . ' ' . $this->_data[ 'key' ];
    }
}
