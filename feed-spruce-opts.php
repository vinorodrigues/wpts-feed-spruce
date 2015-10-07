<?php
/**
 * @see http://www.phrases.org.uk/meanings/spruce-up.html
 */

// Small fix to work arround windows and virtual paths while in dev env.
if ( defined('WP_DEBUG') && WP_DEBUG )
	define( 'FEED_SPRUCE_SLUG',
		str_replace('-opts', '', basename(dirname(__FILE__)) . '/' . pathinfo(__FILE__, PATHINFO_FILENAME) . '.php' ) );
if ( ! defined('FEED_SPRUCE_SLUG') )
	define( 'FEED_SPRUCE_SLUG',
		str_replace('-opts', '', plugin_basename(__FILE__)) );


/**
 * Check if Settings API supported
 * 2.7 for settings page
 * 2.9 for post-thumbnail
 */
function ts_feed_spruce_requires_wordpress_version() {
	global $wp_version;
	$plugin = FEED_SPRUCE_SLUG;
	$plugin_data = get_plugin_data( __FILE__, false );

	if ( version_compare($wp_version, "2.9", "<" ) ) {
		if( is_plugin_active($plugin) ) {
			deactivate_plugins( $plugin );
			wp_die( "'".$plugin_data['Name']."' requires WordPress 2.9 or higher, and has been deactivated!" );
		}
	}
}

add_action( 'admin_init', 'ts_feed_spruce_requires_wordpress_version' );

/**
 * Delete options table entries ONLY when plugin deactivated AND deleted
 */
function ts_feed_spruce_register_uninstall_hook() {
	delete_option('ts_feed_spruce_options');
}

register_uninstall_hook( FEED_SPRUCE_SLUG, 'ts_feed_spruce_register_uninstall_hook' );

/**
 * Provides default options array & filters them
 */
function ts_feed_spruce_default_options() {
	return apply_filters( 'ts_feed_spruce_default_options', array(
		'atomicon' => '',
		'atomlogo' => '',
		'rss2image' => '',
		'copyright' => '',
		'thumbnail' => false,
		'sociallinks' => false,
		'append' => '',
		));
}

/**
 * Define default option settings
 */
function ts_feed_spruce_register_activation_hook() {
	$tmp = get_option('ts_feed_spruce_options');
	if( ! is_array($tmp) ) {
		delete_option('ts_feed_spruce_options');  /// may not be needed
		update_option('ts_feed_spruce_options', ts_feed_spruce_default_options());
	}
}

register_activation_hook( FEED_SPRUCE_SLUG, 'ts_feed_spruce_register_activation_hook' );

/** Helper for WP2.9- */
if (!function_exists('__return_false')) :
function __return_false() {
	return false;
}
endif;

/** Helper for WP3.0- */
function _ts_feed_spruce_submit_button() {
	if (function_exists('submit_button')) submit_button();
	else echo '<input type="submit" name="' . __('Submit') .
		'" class="button-primary" value="'. __('Save Changes') .'">';
}

/**
 * Register settings page
 */
function ts_feed_spruce_admin_init() {
	register_setting('ts_feed_spruce_plugin_options', 'ts_feed_spruce_options', 'ts_feed_spruce_options_validate');

	add_settings_section('rss2meta', __('RSS2 Feed Meta'), '__return_false', 'ts_feed_spruce_options');
	add_settings_section('atommeta', __('Atom Feed Meta'), '__return_false', 'ts_feed_spruce_options');
	add_settings_section('post', __('Feed Item/Entry'), '__return_false', 'ts_feed_spruce_options');

	add_settings_field( 'rss2image', __('<b class="feed-rss2">RSS2</b> Image file'), 'ts_feed_spruce_option_field_rss2image', 'ts_feed_spruce_options', 'rss2meta' );
	add_settings_field( 'copyright', __('<b class="feed-rss2">RSS2</b> Copyright Notice'), 'ts_feed_spruce_option_field_copyright', 'ts_feed_spruce_options', 'rss2meta' );
	add_settings_field( 'atomicon', __('<b class="feed-atom">Atom</b> Icon file'), 'ts_feed_spruce_option_field_atomicon', 'ts_feed_spruce_options', 'atommeta' );
	add_settings_field( 'atomlogo', __('<b class="feed-atom">Atom</b> Logo file'), 'ts_feed_spruce_option_field_atomlogo', 'ts_feed_spruce_options', 'atommeta' );

	add_settings_field( 'thumbnail', __('Featured Image'), 'ts_feed_spruce_option_field_thumbnail', 'ts_feed_spruce_options', 'post' );
	add_settings_field( 'sociallinks', __('Social Links'), 'ts_feed_spruce_option_field_sociallinks', 'ts_feed_spruce_options', 'post' );
	add_settings_field( 'append', __('Append Code'), 'ts_feed_spruce_option_field_append', 'ts_feed_spruce_options', 'post' );
}

add_action( 'admin_init', 'ts_feed_spruce_admin_init' );

/**
 * Thanks to Thomas Griffin
 * @see http://github.com/thomasgriffin/New-Media-Image-Uploader
 *
 * Thanks to Will Wilson
 * @see http://www.mojowill.com/developer/using-the-new-wordpress-3-5-media-manager-in-your-plugin-or-theme/
 */
function ts_feed_spruce_admin_scripts() {
	global $wp_version;
	if ( version_compare($wp_version, "3.5", "<" ) ) return;

	wp_enqueue_media();  // *new in WP3.5+

	/** @see http://jscompress.com */
	wp_deregister_script( 'ts-nmp-media' );
	wp_register_script( 'ts-nmp-media', FEED_SPRUCE_URL . '/js/ts-media' .
		((defined('SCRIPT_DEBUG') && SCRIPT_DEBUG) ? '' : '.min') .
		'.js', array( 'jquery' ), '1.0.0', true );
	wp_localize_script( 'ts-nmp-media', 'ts_nmp_media', array(
		'title' => __( 'Upload File or Select from Media Library' ),  // This will be used as the default title
		'button' => __( 'Insert' ),  // This will be used as the default button text
		) );
	wp_enqueue_script( 'ts-nmp-media' );
}

function ts_feed_spruce_admin_styles() {
	wp_enqueue_style('ts-feed-spruce', FEED_SPRUCE_URL . '/css/feed-spruce.css');
}

if (isset($_GET['page']) && $_GET['page'] == 'ts-feed-spruce') {
	add_action('admin_print_scripts', 'ts_feed_spruce_admin_scripts');
	add_action('admin_print_styles', 'ts_feed_spruce_admin_styles');
}

/**
 * Get options or defaults
 */
function ts_feed_spruce_get_options() {
	$saved = (array) get_option( 'ts_feed_spruce_options' );
	$defaults = ts_feed_spruce_default_options();
	$options = wp_parse_args( $saved, $defaults );
	$options = array_intersect_key( $options, $defaults );

	return $options;
}

/** Helper */
function _ts_feed_spruce_option_field_image($name = 'image', $value = '', $help = '', $empty = '', $preview_box = false) {
	?><div class="layout"><?php
	if ( ! empty($help) )
		echo '<p>' . $help . '</p>';

	?>
	<label for="<?php echo $name; ?>">
	<?php

	$_show = empty($value) ? $empty : $value;
	if (empty($_show)) {
		?><span class="image-not-found"><?php _e( 'No image set' ); ?></span><?php
	} else {
		if ($preview_box)
			$szs = ' width="' . $preview_box . '" height="' . $preview_box . '"';
		else
			$szs = '';
		?><img class="image-preview imgprev-<?php echo $name; ?>" src="<?php echo $_show ?>"<?php echo $szs; ?> /><?php
	}
	?><br />

		<span class="description"><?php _e( 'Enter image URL' ); ?></span><br />
		<input type="url" name="ts_feed_spruce_options[<?php echo $name; ?>]" id="<?php echo $name; ?>" value="<?php echo esc_attr( $value ); ?>" />

		<input class="ts-open-media button" type="button" value="<?php echo _e('Upload Image');?>" style="display:none" />

		<input type="button" class="button" value="<?php _e( 'Clear' ); ?>" onclick="jQuery('#<?php echo $name; ?>').val('')" />
	</label>
	</div>
	<?php
}

/** Helper */
function _ts_feed_spruce_option_field_bool($name, $value = false, $description = '') {
	?>
	<label for="<?php echo $name; ?>">
		<input type="checkbox" name="ts_feed_spruce_options[<?php echo $name; ?>]" id="<?php echo $name; ?>" <?php checked( '1', $value ); ?> />
		<?php if ( ! empty($description) ) : ?> &nbsp; <span class="description"><?php echo $description  ?></span><?php endif; ?>
	</label>
	<?php
}

/**
 * @see http://www.atomenabled.org/developers/syndication/
 */
function ts_feed_spruce_option_field_atomicon() {
	$options = ts_feed_spruce_get_options();

	$help = __('Identifies a small image which provides iconic visual identification for the <i class="feed-atom"></i>feed.');
	$help .= '<ul class="feed-ul">';
	$help .= '<li>' . __('Icons should be square.') . '</li>';
	$help .= '<li>' . __('If left blank the file <code>favicon.ico</code> on the root of the WordPress install will be used.') . '</li>';
	$help .= '</ul>';

	$empty = (empty($options['atomicon']) && @file_exists(ABSPATH . 'favicon.ico')) ?
		$empty = home_url( '/' ) . 'favicon.ico' :
		'';
	_ts_feed_spruce_option_field_image( 'atomicon', $options['atomicon'], $help, $empty, 16 );
}

function ts_feed_spruce_option_field_atomlogo() {
	$options = ts_feed_spruce_get_options();

	$help = __('Identifies a larger image which provides visual identification for the <i class="feed-atom"></i>feed.');
	$help .= '<ul class="feed-ul">';
	$help .= '<li>' . __('Images should be twice as wide as they are tall <i>(2:1 ratio)</i>.') . '</li>';
	$help .= '</ul>';

	_ts_feed_spruce_option_field_image( 'atomlogo', $options['atomlogo'], $help );
}

function ts_feed_spruce_option_field_rss2image() {
	$options = ts_feed_spruce_get_options();

	$help = __('Identifies a image which provides visual identification for the <i class="feed-rss2"></i>feed.');
	$help .= '<ul class="feed-ul">';
	$help .= '<li>' . __('Suggested size is <b>88x31 pixels</b>.') . '</li>';
	$help .= '<li>' . __('Maximum width is 144px') . '</li>';
	$help .= '<li>' . __('Maximum height is 400px.') . '</li>';
	$help .= '</ul>';

	_ts_feed_spruce_option_field_image( 'rss2image', $options['rss2image'], $help );
}

function ts_feed_spruce_option_field_copyright() {
	$options = ts_feed_spruce_get_options();
	$name = 'copyright';
	?><input  name="ts_feed_spruce_options[<?php echo $name; ?>]" id="<?php echo $name; ?>"
	type="text" value="<?php echo $options[$name]; ?>" class="regular-text code"><?php
}

function ts_feed_spruce_option_field_sociallinks() {
	$options = ts_feed_spruce_get_options();
	$name = 'sociallinks';
	$description = __('Append a band of social sharing buttons to each item.');
	_ts_feed_spruce_option_field_bool( $name, $options[$name], $description );
	echo '<br /><div class="social-links-preview image-preview">';
	echo ts_feed_spruce_get_social_links(get_bloginfo('url'), get_bloginfo('name'));
	echo '</div>';
}

function ts_feed_spruce_option_field_thumbnail() {
	$options = ts_feed_spruce_get_options();
	$name = 'thumbnail';
	$description = __('Provide the Featured Image for each item');
	_ts_feed_spruce_option_field_bool( $name, $options[$name], $description );
}

function ts_feed_spruce_option_field_append() {
	global $wp_version;
	$options = ts_feed_spruce_get_options();
	$name = 'append';
	?><fieldset><?php
	if ( version_compare($wp_version, "3.3", "<" ) ) {
		?>
		<textarea name="ts_feed_spruce_options[<?php echo $name; ?>]" id="<?php echo $name; ?>" rows="8" cols="40"
		  class="regular-text code"><?php echo $options[$name]; ?></textarea>
		<?php
	} else {
		$settings = array(
			'media_buttons' => false,
			'textarea_name' => 'ts_feed_spruce_options[' . $name . ']',
			'textarea_rows' => 8,
			'editor_class' => 'regular-text',
			'teeny' => true,
			'dfw' => true,
			);
		wp_editor( $options[$name], $name, $settings );
	}
	?><br /><label for="<?php echo $name ?>"><span class="description"><?php
	_e('Append text or code to each item. Use <code>%id%</code> to echo the post ID, or <code>%url%</code> for the permalink.');
	?></span></label></fieldset><?php
}

/**
 *
 */
function ts_feed_spruce_admin_options_page() {
	if ( ! current_user_can( 'manage_options' ) )
		wp_die( __( 'You do not have sufficient permissions to access this page.' ) );
?>
<div class="wrap">
	<?php screen_icon('feed'); ?>
	<h2><?php _e('TS Feed Spruce Plugin Options'); ?></h2>
	<p></p>

	<form method="post" action="options.php">
		<?php
			settings_fields( 'ts_feed_spruce_plugin_options' );
			do_settings_sections( 'ts_feed_spruce_options' );
			_ts_feed_spruce_submit_button();
		?>
	</form>
</div>
<?php
}

/**
 * Options validation
 */
function ts_feed_spruce_options_validate( $input ) {
	$output = array();

	if ( isset( $input['atomicon'] ) )
		$output['atomicon'] = ( $input['atomicon'] );

	if ( isset( $input['atomlogo'] ) )
		$output['atomlogo'] = ( $input['atomlogo'] );

	if ( isset( $input['rss2image'] ) )
		$output['rss2image'] = ( $input['rss2image'] );

	if ( isset( $input['copyright'] ) )
		$output['copyright'] = $input['copyright'];

	$output['sociallinks'] = isset( $input['sociallinks'] ) ? 1 : 0;
	$output['thumbnail'] = isset( $input['thumbnail'] ) ? 1 : 0;

	if ( isset( $input['append'] ) )
		$output['append'] = $input['append'];

	return apply_filters( 'ts_feed_spruce_options_validate', $output, $input );
}

/**
 *
 */
function ts_feed_spruce_plugin_action_links( $actions /* , $plugin_file, $plugin_data, $context */ ) {
	$menu_slug = 'ts-feed-spruce';
	$settings_link = '<a href="' . admin_url("options-general.php?page=") . $menu_slug . '">' . __('Settings') . '</a>';
	array_unshift( $actions, $settings_link );
	/*
	$ts_link = '<a href="http://tecsmith.com.au">' . __(':)') . '</a>';
	array_push( $actions, $ts_link );
	*/
	return $actions;
}

/**
 *
 */
function ts_feed_spruce_admin_menu() {
	add_filter( 'plugin_action_links_' . FEED_SPRUCE_SLUG, 'ts_feed_spruce_plugin_action_links', 10 /* , 4 */ );
	add_options_page(
		__('TS Feed Spruce Plugin Options'),
		__('TS Feed Spruce'),
		'manage_options',
		'ts-feed-spruce',
		'ts_feed_spruce_admin_options_page' );

}
add_action( 'admin_menu', 'ts_feed_spruce_admin_menu' );

/* EOL */ ?>
