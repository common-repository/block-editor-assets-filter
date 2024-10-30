<?php
/*
  Plugin Name: Block editor assets filter
  Description: Disable custom blocks not used in block editor editing to speed up JavaScript and prevent conflicts.
  Version: 0.9.2
  Plugin URI: https://celtislab.net/en/wp-block-editor-assets-filter
  Author: enomoto@celtislab
  Author URI: https://celtislab.net/
  License: GPLv2
  Text Domain: beaf
  Domain Path: /languages
 */
defined( 'ABSPATH' ) || exit;

    
/***************************************************************************
 * plugin activation / deactivation / uninstall
 **************************************************************************/
if(is_admin()){ 
    //deactivation
    function block_editor_assets_filter_deactivation( $network_deactivating ) {
    }
    register_deactivation_hook( __FILE__,   'block_editor_assets_filter_deactivation' );

    //uninstall
    function block_editor_assets_filter_uninstall() {        
        if ( !is_multisite()) {
            delete_option('beaf_option' );
        } else {
            global $wpdb;
            $current_blog_id = get_current_blog_id();
            $blog_ids = $wpdb->get_col( "SELECT blog_id FROM $wpdb->blogs" );
            foreach ( $blog_ids as $blog_id ) {
                switch_to_blog( $blog_id );
                delete_option('beaf_option' );
            }
            switch_to_blog( $current_blog_id );
        }        
    }
    register_uninstall_hook(__FILE__, 'block_editor_assets_filter_uninstall');    
}

$Beaf_setting = new Beaf_setting();

class Beaf_setting {

    static  $hooks = null;
    static  $filter = array();  //filter option data
        
    /***************************************************************************
     * Style Sheet
     **************************************************************************/
    function beaf_css() { ?>
    <style type="text/css">
    #wrap_registration-table { overflow:auto; height:560px; position: relative;}
    #registration-table input[type=radio], #registration-table input[type=checkbox] { height: 25px; width: 25px; opacity: 0;}
    #registration-table th {text-align: center;}

    #asset-filter-select {margin-top: 12px;}
    #asset-filter-select p {margin: 1em 0;}
    #asset-filter-stat { margin-top: 12px;}
    #singleopt-table { padding-bottom: 8px; border: 1px solid #eee;}
    #singleopt-table th { text-align: center;}
    #singleopt-table td { font-size: 97%;}
    #singleopt-table input[type=radio], #singleopt-table input[type=checkbox] { height: 25px; width: 25px; opacity: 0;}    

    thead, tbody { display: block;}
    .widefat * { word-wrap: break-word !important;}
    .widefat thead { position:sticky; top:0px; z-index:1;}
    .widefat tr:first-of-type th:first-of-type {position: sticky; left: 0px; text-align: left; background-color: aliceblue; z-index:3;}    
    .widefat th { padding: 8px;}
    .widefat td { padding: 8px;}
    .widefat td:first-of-type { position: sticky; left: 0px; text-align: left; background-color: white;}    

    thead .filter-asset-name { background-color: aliceblue;}
    thead .filter-asset-name, tbody .filter-asset-name { min-width: 640px; max-width: 640px;}
    thead .asset-filename, tbody .asset-filename, thead .asset-callback, tbody .asset-callback { min-width: 240px; max-width: 240px;}
    thead .asset-type, tbody .asset-type, thead .asset-priority, tbody .asset-priority, thead .asset-args, tbody .asset-args { min-width: 48px; max-width: 48px; text-align: center;}
    thead .asset-type, thead .asset-filename, thead .asset-callback, thead .asset-priority, thead .asset-args { background-color: aliceblue;}
    thead .ckbox-type, tbody .ckbox-type { min-width: 48px; max-width: 48px; text-align: center;}
    .filter-none, .filter-add, .filter-remove { background-color: lavenderblush;}
    .asset-type.plugins {border-left: 4px solid #00a0d2;}
    .asset-type.themes {border-left: 4px solid #46b450;}

    .dashicons-yes:before { font-size: 20px; border: 1px solid #eee; background-color: whitesmoke;}
    .ckbox-type label { color: whitesmoke;  margin-left: -32px;}
    .ckbox-type input[type="radio"]:checked + span.dashicons-yes:before { background-color: yellowgreen;}    
    .radio-green input[type="radio"]:checked + span.dashicons-yes:before { background-color: #4caf50;}    
    .radio-red input[type="radio"]:checked + span.dashicons-yes:before { background-color: tomato}        
    .filter-description { padding: 0 10px;}
    thead .s-asset-filename, tbody .s-asset-filename { min-width: 128px; max-width: 128px; padding: 3px; word-break: break-word;}
    thead .s-asset-filename { background-color: aliceblue;}
    </style>
    <?php }    

    /***************************************************************************
     * Block editor assets filter Option Setting
     **************************************************************************/

    public function __construct() {
        
        load_plugin_textdomain('beaf', false, basename( dirname( __FILE__ ) ).'/languages' );

        require_once ( __DIR__ . '/hook-utility.php');
            
        self::$filter = get_option('beaf_option');
        if(empty(self::$filter['optver']) || self::$filter['optver'] < '2'){
            self::$filter['optver'] = '2';
            //ここにデータフォーマットが変わった場合の変換処理を記述
        }
        if(is_admin()) {
            add_action( 'plugins_loaded', array($this, 'beaf_admin_start'), 9999 );
            add_action( 'admin_init', array($this, 'action_posts'));
        	add_action( 'enqueue_block_editor_assets', array( $this, 'block_editor_assets_filter' ), defined( 'PHP_INT_MIN' ) ? PHP_INT_MIN : ~PHP_INT_MAX );
        }
        add_action( 'wp_ajax_block_editor_assets_filter', array($this, 'beaf_ajax_postidfilter'));
    }
        
    //admin setting start 
    public function beaf_admin_start() {
        add_action('admin_menu', array($this, 'beaf_option_menu')); 
    }
    
    //menu add
    public function beaf_option_menu() {
        if(current_user_can( 'activate_plugins' )){
            $page = add_options_page( 'Block editor assets filter', __('Block editor assets filter', 'beaf'), 'manage_options', 'block_editor_assets_filter', array(&$this,'beaf_option_page'));
            add_action( 'admin_print_scripts-'.$page, array($this, 'beaf_scripts') );
        }
    }

    //Block editor assets filter setting page script 
    function beaf_scripts() {
        wp_enqueue_script( 'jquery' );
        add_action( 'admin_head', array($this, 'beaf_css' ));
    }

    //option action request (add, update, delete)
    function action_posts() {
        if (current_user_can( 'activate_plugins' )) {
            if( isset($_POST['save_filter']) ) {
                if(isset($_POST['beafregist'])){
                    check_admin_referer('block_editor_assets_filter');

                    $assets = array();
                    foreach ( $_POST['beafregist'] as $key => $val ) {
                        if(preg_match('/^([a-fA-F0-9]{32})$/u', $key) && in_array($val, array('_add', '_remove')))
                            $assets[$val][$key] = 1;
                    }
                    self::$filter['_add'] = (!empty($assets['_add']))? implode(",", array_keys($assets['_add'])) : '';
                    self::$filter['_remove'] = (!empty($assets['_remove']))? implode(",", array_keys($assets['_remove'])) : '';
                    update_option('beaf_option', self::$filter );
                }
                header('Location: ' . admin_url('options-general.php?page=block_editor_assets_filter'));
                exit;
                
            } elseif( isset($_POST['clear_filter']) ) {
                check_admin_referer('block_editor_assets_filter');
                
                self::$filter['_add'] = '';
                self::$filter['_remove'] = '';
                update_option('beaf_option', self::$filter );
                header('Location: ' . admin_url('options-general.php?page=block_editor_assets_filter'));
                exit;                
            }
        }
    }

    function block_editor_assets_filter() {
        //フックフィルター初期値を取得
        self::$hooks = BeafLib\Hook_util::get_hook('enqueue_block_editor_assets');
        //block editor 使用時のみメタボックス有効化
        add_action( 'add_meta_boxes', array($this, 'load_meta_boxes'), 10, 2 );
        //remove_action
        global $post;
        $remove_ids = '';
        if(!empty($post->ID)){
            $option = get_post_meta( $post->ID, '_beaf_filter', true );
            if(empty($option) || $option['singleopt'] !== 'use'){
                if(!empty(self::$filter['_remove']))
                    $remove_ids = self::$filter['_remove'];
            } else {
                if(!empty($option['_remove'])){
                    $remove_ids = '';
                    $arids = explode(',', $option['_remove']);
                    foreach ($arids as $key) {
                        //ノーマルなら個別設定より優先されるので除外
                        if(false === strpos(self::$filter['_add'], $key) && false === strpos(self::$filter['_remove'], $key))
                            continue;
                        $remove_ids .= $key . ',';
                    }
                    $remove_ids = trim($remove_ids, ',');
                }
            }
        }
        BeafLib\Hook_util::remove_hook('enqueue_block_editor_assets', $remove_ids);
    }

    public function beafregist_item($type, $asset, $filter) {
        $filter_id = BeafLib\Hook_util::filter_id( $asset['file'], $asset['callback'], $asset['priority'], $asset['args']);
        $opt_name = "beafregist[$filter_id]";
        ?>
        <tr id="beafregist_<?php echo $filter_id; ?>">
          <td class="asset-type <?php echo $type; ?>"><?php echo $type; ?></td>
          <td class="asset-filename"><?php echo $asset['file']; ?></td>
          <td class="asset-callback"><?php echo $asset['callback']; ?></td>
          <td class="asset-priority"><?php echo $asset['priority']; ?></td>
          <td class="asset-args"><?php echo $asset['args']; ?></td>
          <?php
            $radio = '';
            if(!empty($filter['_add']) && false !== strpos($filter['_add'], $filter_id)){
                $radio = '_add';
            } elseif(!empty($filter['_remove']) && false !== strpos($filter['_remove'], $filter_id)){
                $radio = '_remove';
            }
          ?>
          <td class="ckbox-type"><label><input type="radio" name="<?php echo $opt_name; ?>" value='' <?php checked('', $radio); ?>/><span class="dashicons dashicons-yes"></span></label></td>
          <td class="ckbox-type radio-green"><label><input type="radio" name="<?php echo $opt_name; ?>" value="_add" <?php checked('_add', $radio); ?>/><span class="dashicons dashicons-yes"></span></label></td>
          <td class="ckbox-type radio-red"><label><input type="radio" name="<?php echo $opt_name; ?>" value="_remove" <?php checked('_remove', $radio); ?>/><span class="dashicons dashicons-yes"></span></label></td>
        </tr>
        <?php
    }

    public function beafregist_table($hook_inf, $filter) {
    ?>
    <div id="wrap_registration-table">        
    <table id="registration-table" class="widefat">
        <thead>
           <tr>
               <th class="filter-asset-name" colspan="5"><?php _e('Assets', 'beaf'); ?></th>
               <th class="filter-remove" colspan="3"><?php _e('Default Action Hook', 'beaf'); ?></th>
           </tr>
           <tr>
               <th class="asset-type"><span style="font-size:smaller"><?php _e('Type', 'beaf'); ?></span></th>
               <th class="asset-filename"><span style="font-size:smaller"><?php _e('File', 'beaf'); ?></span></th>
               <th class="asset-callback"><span style="font-size:smaller"><?php _e('Callback', 'beaf'); ?></span></th>
               <th class="asset-priority"><span style="font-size:smaller"><?php _e('Priority', 'beaf'); ?></span></th>
               <th class="asset-args"><span style="font-size:smaller"><?php _e('Args', 'beaf'); ?></span></th>
               <th class="ckbox-type filter-none"><span style="font-size:smaller"><?php _e('Normal', 'beaf'); ?></span></th>
               <th class="ckbox-type filter-add"><span style="font-size:smaller"><?php _e('Add', 'beaf'); ?></span></th>
               <th class="ckbox-type filter-remove"><span style="font-size:smaller"><?php _e('Remove', 'beaf'); ?></span></th>
           </tr>
        </thead>
        <tbody class="assets-table-body">
        <?php
        foreach( array('plugins','themes') as $type){ //WP core の asset は除外
            if(!empty($hook_inf[$type])){
                foreach ( $hook_inf[$type] as $key => $asset ) {
                    if($type === 'plugins' && false !== strpos($asset['file'], 'block-editor-assets-filter/block-editor-assets-filter.php'))
                        continue;   //自身のプラグインは除外
                    $this->beafregist_item($type, $asset, $filter);
                }
            }
        }
        ?>
        </tbody>
    </table>
    </div>
    <p><strong>[ <?php _e('enqueue_block_editor_assets - Action Hook', 'beaf'); ?> ]</strong></p>
    <div class="filter-description">
        <?php _e('<strong>Normal</strong> - This asset is executed with `enqueue_block_editor_assets` action hook as usual.', 'beaf'); ?><br />
        <?php _e('<strong>Add</strong> - This asset is to be filtered. It defaults to `add`.', 'beaf'); ?><br />
        <?php _e('<strong>Remove</strong> - This asset is to be filtered. It defaults to `remove`.', 'beaf'); ?>
        <p><?php _e('* Assets set to `add/remove` can be reconfigured for each post in block editors edit screen.', 'beaf'); ?></p>
    </div>
    <?php
    }
    
    public function beaf_option_page() {
        $clear_dialog = __('Block editor assets filter Settings\nClick OK to clear it.', 'beaf');
        $beaf_hook_inf = BeafLib\Hook_util::get_hook('enqueue_block_editor_assets');
        $updfg = false;
        if(empty(self::$filter['plugins']) || self::$filter['plugins'] != $beaf_hook_inf['plugins']){
            self::$filter['plugins'] = (!empty($beaf_hook_inf['plugins']))? $beaf_hook_inf['plugins']: array();
            $updfg = true;
        }
        if(empty(self::$filter['themes']) || self::$filter['themes'] != $beaf_hook_inf['themes']){
            self::$filter['themes'] = (!empty($beaf_hook_inf['themes']))? $beaf_hook_inf['themes'] : array();
            $updfg = true;
        }
        if($updfg){
            update_option('beaf_option', self::$filter );
        }        
    ?>
    <h2><?php _e('Block editor assets filter', 'beaf'); ?></h2>
    <p><?php _e('Disable custom blocks not used in block editor editing to speed up JavaScript and prevent conflicts.', 'beaf'); ?></p>
    <div id="beaf-setting">
        <div id="beaf-registration">               
            <form method="post" autocomplete="off">
                <?php wp_nonce_field( 'block_editor_assets_filter'); ?>
                <?php $this->beafregist_table($beaf_hook_inf, self::$filter); ?>
                <p class="submit">
                    <input type="submit" class="button-primary" name="clear_filter" value="<?php _e('Clear', 'beaf'); ?>" onclick="return confirm('<?php echo $clear_dialog; ?>')" />&nbsp;&nbsp;&nbsp;
                    <input type="submit" class="button-primary" name="save_filter" value="<?php _e('Save', 'beaf'); ?>" />
                </p>
            </form>
        </div>
    </div>
    <?php
    }

    /***************************************************************************
     * Meta box
     **************************************************************************/
    function load_meta_boxes( $post_type, $post ) {
        if ( current_user_can('activate_plugins', $post->ID) ) { 
          	add_meta_box( 'beaf-meta-div', __( 'Block editor assets filter', 'beaf' ), array($this, 'beaf_meta_box'), null, 'side' );
            add_action( 'admin_footer', array($this, 'beaf_meta_script' ));
        }
    }

    function _beaf_assets_select( $key, $asset, $filter, $option ) {
        $html = '';
        $radio = '';
        if(!empty($option['singleopt']) && $option['singleopt'] === 'use'){
            if(false === strpos($filter['_add'], $key) && false === strpos($filter['_remove'], $key)){
                //ノーマルなら個別設定より優先される
            } elseif(false !== strpos($option['_add'], $key)){
                $radio = '_add';
            } elseif(false !== strpos($option['_remove'], $key)){
                $radio = '_remove';
            } elseif(false !== strpos($filter['_add'], $key) || false !== strpos($filter['_remove'], $key)){
                //個別設定データになくてもデフォルトに追加されている場合は有効フィルター(仮　_add)として選択できるように表示
                $radio = '_add';
            }
        } else {
            if(false !== strpos($filter['_add'], $key)){
                $radio = '_add';
            } elseif(false !== strpos($filter['_remove'], $key)){
                $radio = '_remove';
            }
        }
        if(!empty($radio)){
            $file = $asset['file'];
            $hint = "File : {$asset['file']}". PHP_EOL ."Callback : {$asset['callback']}". PHP_EOL ."Priority : {$asset['priority']}". PHP_EOL ."Args : {$asset['args']}". PHP_EOL;
            $opt_name = "beaf_option[$key]";
            $html .= '<tr>';
            $html .=  '<td title="' . $hint .'" class="s-asset-filename">' . $file . '</td>';
            $html .=  '<td class="ckbox-type radio-green"><label><input type="radio" name="' . $opt_name . '" value="_add" ' . checked('_add', $radio, false). '/><span class="dashicons dashicons-yes"></span></label></td>';
            $html .=  '<td class="ckbox-type radio-red"><label><input type="radio" name="' . $opt_name . '" value="_remove" ' . checked('_remove', $radio, false). '/><span class="dashicons dashicons-yes"></span></label></td>';
            $html .= '</tr>';
        }
        return $html;
    }
    
    //Assets filter Select
    function beaf_assets_select( $filter, $option ) {        
        if(empty($filter))
            return __('Block editor assets filter is not registered', 'beaf');
        
        $html = '<table id="singleopt-table">';
        $html .= '<thead><tr>';
        $html .=  '<th class="s-asset-filename">'. __('Assets') . '</th>';
        $html .=  '<th class="ckbox-type filter-add"><span style="font-size:smaller">'. __('Add', 'beaf'). '</span></th>';
        $html .=  '<th class="ckbox-type filter-remove"><span style="font-size:smaller">'. __('Remove', 'beaf'). '</span></th>';
        $html .= '</tr></thead>';
        $html .= '<tbody class="assets-table-body meta-boxes-assets-table">';
        foreach( array('plugins','themes') as $type){
            if(!empty($filter[$type])){
                foreach ( $filter[$type] as $key => $asset ) {
                    if(!empty(self::$hooks[$type][$key])){
                        $html .= $this->_beaf_assets_select( $key, $asset, $filter, $option );                    
                    }
                }
            }
        }
        $html .= '</tbody>';
        $html .='</table>';
        return $html;
    }
    
    function beaf_meta_box( $post, $box ) {     
        if(is_object($post)){
            $myfilter = get_post_meta( $post->ID, '_beaf_filter', true );
            $default = array( 'singleopt' => 'default', '_add' => '', '_remove' => '');
            $option = (!empty($myfilter))? $myfilter : $default;
            $option = wp_parse_args( $option, $default);
			$ajax_nonce = wp_create_nonce( 'block_editor_assets_filter-' . $post->ID );
            $this->beaf_css();            
            ?>
            <div id="asset-filter-select">
                <p><?php _e( 'Assets filter for Single post', 'beaf' ); ?></p>
                <label><input type="radio" name="singleopt" value="default" <?php checked('default', $option['singleopt']); ?>/><?php _e(' Use Default', 'beaf' ); ?></label>
                <label><input type="radio" name="singleopt" value="use" <?php checked('use', $option['singleopt']); ?>/><?php _e('Use single option', 'beaf'); ?></label>
                <div id="asset-filter-stat">
                <?php echo $this->beaf_assets_select( self::$filter, $option ); ?>
                </div>
                <?php echo '<p class="hide-if-no-js"><a id="beaf-filter-submit" class="button" href="#beaf-meta-div" onclick="BEAF_Post_Filter(\'' . $ajax_nonce . '\');return false;" >'. __('Save') .'</a></p>'; ?>
            </div>
            <?php
        }
    }    

    //wp_ajax_block_editor_assets_filter called function
    function beaf_ajax_postidfilter() {
        if ( isset($_POST['post_id']) ) {
            $pid = (int) $_POST['post_id'];
            if ( !current_user_can( 'activate_plugins', $pid ) )
                wp_die( -1 );            
            check_ajax_referer( "block_editor_assets_filter-$pid" );
            
            $myfilter = get_post_meta( $pid, '_beaf_filter', true );
            $default = array( 'singleopt' => 'default', '_add' => '', '_remove' => '');
            $option = (!empty($myfilter))? $myfilter : $default;
            $option = wp_parse_args( $option, $default);
            $option["singleopt"] = (!empty($_POST['singleopt']) && in_array($_POST['singleopt'], array('default', 'use')))? $_POST['singleopt'] : 'default';
            if('use' == $option["singleopt"]){
                foreach (array('_add', '_remove') as $type) {
                    if(!empty($_POST[ $type ])){
                        $keys = array();
                        if( preg_match_all('/beaf_option\[(.+?)\]/u', sanitize_text_field($_POST[ $type ]), $matches)){
                            if(!empty($matches[1])){ 
                                foreach ($matches[1] as $key){
                                    $keys[] = $key;
                                }
                                $option[ $type ] = implode(",", $keys);
                            }
                        }
                    } else {
                        $option[ $type ] = '';
                    }
                }
                update_post_meta( $pid, '_beaf_filter', $option );
            } else {
                update_post_meta( $pid, '_beaf_filter', $option );
            }            
            $html = $this->beaf_assets_select( self::$filter, $option );
            wp_send_json_success($html);
        }
        wp_die( 0 );
    }
    
    /***************************************************************************
     * Javascript 
     **************************************************************************/
    function beaf_meta_script() { 
        $reload_dialog = __('Block editor assets filter setting has been updated.\nClick OK to reload the page.', 'beaf');
    ?>
    <script type='text/javascript' >
    BEAF_Post_Filter = function(nonce){ 
        jQuery.ajax({ 
            type: 'POST', 
            url: ajaxurl, 
            data: { 
                action: "block_editor_assets_filter", 
                post_id : jQuery( '#post_ID' ).val(), 
                _ajax_nonce: nonce,
                singleopt: jQuery("input[name='singleopt']:checked").val(),
                _add: jQuery('.meta-boxes-assets-table td.radio-green input:checked').map(function(){ return jQuery(this).attr("name"); }).get().join(','), 
                _remove: jQuery('.meta-boxes-assets-table td.radio-red input:checked').map(function(){ return jQuery(this).attr("name"); }).get().join(','),
            }, 
            dataType: 'json', 
        }).then(
            function (response, dataType) {
                jQuery('#asset-filter-stat').html(response.data);
                if(window.confirm('<?php echo $reload_dialog; ?>')){location.reload();}
            },
            function () { /* alert("ajax error"); */ }
        );
    };
    </script>  
    <?php }
}