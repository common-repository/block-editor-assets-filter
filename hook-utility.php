<?php
/**
 * Hook filter utility
 * 
 * Description: アクションフック/フィルターフックから指定したフィルターを解除します
 * 
 * Version: 0.3.0 
 * Author: enomoto@celtislab
 * Author URI: https://celtislab.net/
 * License: GPLv2
 * 
 */
namespace BeafLib;

defined( 'ABSPATH' ) || exit;

class Hook_util {

	function __construct() {}
    
    /*=============================================================
     * テーマやプラグインで指定されているフックフィルター識別用IDの作成
     * 
     * $file : 対象フックフィルターを呼び出しているPHPファイル名
     * 　　　　　Plugin の場合は プラグインスラッグからの相対パス 例: jetpack/class.jetpack-gutenberg.php
     *          Theme  の場合は テーマスラッグ名からの相対パス   例: twentytwenty/functions.php
     * $callback : 対象フックフィルターのコールバック関数
     * 　　　　　グローバル関数の場合は、その関数名を指定
     * 　　　　　無名関数（クロージャ）の場合は、'closure' と指定
     *          クラスメソッドの場合は、'クラス名::メソッド名' を指定
     * $priority : 対象フックフィルターの優先度
     * $accepted_args : 対象フックフィルターの関数が取ることのできる引数の数
     */    
    public static function filter_id( $file, $callback, $priority=10, $accepted_args=1) {
        return( md5( "{$file}_{$callback}_{$priority}_{$accepted_args}" ) );
    }

    //フックされているフィルター情報の解析
    private static function hook_inf($priority, $filter, &$type, &$filter_id) {
        $hook_inf = array();
        try {                
            $callback = '';
            $accepted_args = 1;                
            if ( isset( $filter['function'] ) ) {
                if ( isset( $filter['accepted_args'] ) ) {
                    $accepted_args = (int)$filter['accepted_args'];                
                }
                $ref = null;
                if (is_string( $filter['function'] )){
                    $callback = $filter['function'];    //global function
                    $ref = new \ReflectionFunction( $filter['function'] );

                } elseif(is_object( $filter['function'] )){
                    $callback = 'closure';              //closure function
                    $ref = new \ReflectionFunction( $filter['function'] );

                } elseif(is_array( $filter['function'] )){
                    if (is_string( $filter['function'][0] )){   //static class
                        $class = $filter['function'][0];
                        $func = $filter['function'][1];
                        $callback = "$class::$func";
                        $ref = new \ReflectionMethod( $class, $func);
                        
                    } elseif(is_object( $filter['function'][0] )){ //instance class
                        $class = get_class( $filter['function'][0] ); 
                        $func = $filter['function'][1];
                        $callback = "$class::$func";
                        $ref = new \ReflectionMethod( $class, $func);
                    }                    
                } 
                if(is_object($ref)){
                    $file = wp_normalize_path( $ref->getFileName() );
                    $rootdir = wp_normalize_path( ABSPATH );
                    $plugin_root = wp_normalize_path( dirname( plugin_dir_path( __FILE__ ), 1)) . '/';
                    $theme_root  = wp_normalize_path( get_theme_root() ) . '/';
                    if ( strpos($file, $plugin_root) !== false) {
                        $type = 'plugins';
                        $file = str_ireplace( $plugin_root, '', $file);
                    } elseif ( strpos($file, $theme_root) !== false) {
                        $type = 'themes';
                        $file = str_ireplace( $theme_root, '', $file);
                    } else {
                        $type = 'core';
                        $file = str_ireplace( $rootdir, '', $file);
                    }
                    $filter_id = self::filter_id($file, $callback, $priority, $accepted_args);
                    $hook_inf = array('file' => $file, 'callback' => $callback, 'priority' => $priority, 'args' => $accepted_args);
                }
            }
        } catch ( Exception $e ) {
            return null;
        }
        return $hook_inf;
    }
    
    //指定したフックフィルターの情報を取得
    // $action : アクションフック名
    // $target_priority : 対象の優先度（未指定時はすべての優先度を対象）
    public static function get_hook($action, $target_priority=null) {
        global $wp_filter;
        $hooks = array();
        if ( is_object( $wp_filter[$action] ) ) {
            foreach($wp_filter[$action]->callbacks as $priority => $callbacks ){
                if($target_priority !== null && $priority != (int)$target_priority)
                    continue;
                foreach ($callbacks as $key => $filter) {
                    $type = $filter_id = '';
                    $hook = self::hook_inf($priority, $filter, $type, $filter_id);
                    if(!empty($hook)){
                        $hooks[$type][$filter_id] = $hook;
                    }
                }
            }
        }
        return $hooks;
    }  

    //指定したフィルター識別用IDのフックを解除
    // $action : アクションフック名
    // $remove_ids : 解除するフィルター識別用ID(複数時はカンマ区切りで指定)
    // $target_priority : 対象の優先度（未指定時はすべての優先度を対象）
    public static function remove_hook($action, $remove_ids, $target_priority=null) {
        global $wp_filter;
        if ( is_object( $wp_filter[$action] ) && !empty($remove_ids) ) {
            foreach($wp_filter[$action]->callbacks as $priority => $callbacks ){
                if($target_priority !== null && $priority != (int)$target_priority)
                    continue;
                foreach ($callbacks as $key => $filter) {
                    $type = $filter_id = '';
                    $hook = self::hook_inf($priority, $filter, $type, $filter_id);
                    if(!empty($hook) && false !== strpos($remove_ids, $filter_id)){
                        unset( $wp_filter[$action]->callbacks[$priority][$key] );
                    }
                }
            }
        }
    }     
}
