=== Block editor assets filter ===
Contributors: enomoto celtislab
Tags: gutenberg, enqueue_block_editor_assets, remove_action, performance, conflict
Requires at least: 5.3
Tested up to: 5.5
Requires PHP: 7.2
Stable tag: 0.9.2
License: GPLv2
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Disable custom blocks not used in block editor editing (Admin Dashboard) to speed up JavaScript and prevent conflicts.

== Description ==

You may have many custom blocking plugins installed, but you may not want (or need) to enable them for all of your posts and pages. This plugin allows you to disable unwanted custom block plugins for each post or page.

Filtering the activation of custom block plugins will allow you to speed up editing in the block editor and use different custom blocks that cause conflicts.

For example, if you use Jetpack plugin, but you don't need Jetpack custom blocks and you want to clean up your editing screen.

[plugin load filter](https://wordpress.org/plugins/plugin-load-filter/) is recommended for speeding up and controlling the dynamic stopping of plugins on the front side.


For more detailed information, there is an introduction page.

[Documentation](https://celtislab.net/en/wp-block-editor-assets-filter )


== Installation ==

1. Upload the `block-editor-assets-filter` folder to the `/wp-content/plugins/` directory.
2. Activate the plugin through the `Plugins` menu in WordPress
3. Set up from `block-editor-assets-filter` to be added to the Settings menu of Admin mode.


Usage

 * Open the settings page.

  * The filter list for the "enqueue_block_editor_assets" action hook at the start of the block editor run is displayed
 
 * Select the action hook defaults.

  * Select 'add' for the filter to be activated and 'remove' for the filter to be deactivated

 * You can specify whether to set the default or re-set for each Post ID.

 * Note

  * Custom blocks loaded without using  'enqueue_block_editor_assets' action hook cannot be filtered


== Upgrade Notice ==


== Screenshots ==

1. Filter setting.
2. Setting of each post

== Changelog ==

= 0.9.2 =
* 2020-9-28
* fix : php warning in action_posts function

= 0.9.1 =
* 2020-9-24
* Fixed the use of hardcoats such as wp-content in program.
* Fix sanitization esc_html -> sanitize_text_field
* Use namespaces to avoid class name conflicts.  

= 0.9.0 =
* 2020-9-23  
 
