<?php
/*
 * Plugin Name: TS Feed Spruce Plugin
 * Plugin URI: http://tecsmith.com.au
 * Description: Spruce-up your feeds! Add images and social media icons to your RSS2 and ATOM feeds
 * Author: Vino Rodrigues
 * Version: 0.0.2
 * Author URI: http://vinorodrigues.com
 *
 * @author Vino Rodrigues
 * @package TS-Feed-Spruce
 * @since TS-Feed-Spruce 0.0.3
 * @see http://snook.ca/archives/rss/add_logo_to_feed/
 * @see http://www.onenaught.com/posts/20/adding-a-logo-to-your-wordpress-rss-feed
**/

// Small fix to work arround windows and virtual paths while in dev env.
if ( defined('WP_DEBUG') && WP_DEBUG )
	define( 'FEED_SPRUCE_URL', plugins_url() . '/ts-feed-spruce' );
if (!defined('FEED_SPRUCE_URL'))
	define( 'FEED_SPRUCE_URL', plugins_url( '', __FILE__ ) );


include_once('feed-spruce-opts.php');


/** Helper */
function _ts_feed_spruce_link_button($img, $alt, $url = '', $popup = false) {
	$output = '';
	if (!empty($url)) {
		$output .= '<a href="' . $url . '"';
		if ($popup)
			$output .= ' onclick="if(window.open(\''  .
			urldecode($url) . '\', \'share-dialog\', \'width=626,height=436\')) return false;"';
		else $output .= '  target="_blank" rel="noreferrer"';
		$output .= ' title="' . $alt . '">';
	}
	$output .= '<img src="' . trailingslashit(FEED_SPRUCE_URL) .
		'img/' . $img . '-20x20.png' . '" width="20" height="20" alt="' .
		$alt . '" />';
	if (!empty($url)) $output .= '</a>';
	return $output;
}

/**
 * Global helper to generate the social links html
 */
function ts_feed_spruce_get_social_links($url, $title = '') {
	$output = '';

	/* ----- Twitter ---- */
	/** @see https://dev.twitter.com/docs/tweet-button#build-your-own */
	$link = 'https://twitter.com/share?url=' . urlencode($url) . '&text=' . urlencode($title);
	$output .= ' ' . _ts_feed_spruce_link_button('twitter', __('Twitter'), $link, true);

	/* ----- Facebook ----- */
	/** @see http://developers.facebook.com/docs/reference/plugins/share-links/ */
	$link = 'https://www.facebook.com/sharer/sharer.php?u=' . urlencode($url);
	$output .= ' ' . _ts_feed_spruce_link_button('facebook', __('Facebook'), $link, true);

	/* ----- Google+ ----- */
	/** @see https://developers.google.com/+/web/share/#sharelink-endpoint */
	$link = 'https://plus.google.com/share?url=' . urlencode($url);
	$output .= ' ' . _ts_feed_spruce_link_button('gplus', __('Google+'), $link);

	/* Linked In */
	/** can't find docs on this */
	$link = 'http://www.linkedin.com/shareArticle?url=' . urlencode($url) . '&title=' . urlencode($title);
	$output .= ' ' . _ts_feed_spruce_link_button('linkedin', __('Linked In'), $link, true);

	/* Digg */
	/** @see http://www.hypergurl.com/digg-generator.html */
	$link = 'http://digg.com/submit?url=' . urlencode($url) . '&title=' . urlencode($title);
	$output .= ' ' . _ts_feed_spruce_link_button('digg', __('Digg'), $link);

	/* Email */
	$link = 'mailto:?to=&subject=' . str_replace('+', '%20', urlencode($title)) .
		'&body=' . str_replace('+', '%20', urlencode($url));
	$output .= ' ' . _ts_feed_spruce_link_button('email', __('Email'), $link, false);

	return $output;
}

/*
function get_var_dump($expression) {
	ob_start();
	var_dump($expression);
	return '<pre>' . ob_get_clean() . '</pre>';
}  /* */

/**
 * Inject media namespace into RSS2 feed header
 * @see http://www.rssboard.org/media-rss
 */
function ts_feed_spruce_rss2_ns() {
	echo ' xmlns:media="http://search.yahoo.com/mrss/" ';
}

add_action('rss2_ns', 'ts_feed_spruce_rss2_ns');

/** Helper */
if (!function_exists('copyright_date')) :
function copyright_date() {
	return date('Y');
}
endif;

/**
 * Inject into RSS2 xml header
 * @see http://www.w3schools.com/rss/rss_reference.asp
 */
function ts_feed_spruce_rss2_head() {
	$options = ts_feed_spruce_get_options();
	if (!empty($options['rss2image'])) {
		echo "<image>";
		echo "<link>" . get_bloginfo_rss('url') . '</link>';
		echo "<title>" . get_bloginfo_rss('name') . get_wp_title_rss('&#187;') . '</title>';
		echo "<url>" . esc_url($options['rss2image']) . '</url>';
		echo "</image>";
	}

	echo '<copyright><![CDATA[';
	if (!empty($options['copyright'])) {
		echo ent2ncr(
			strip_tags(
				str_ireplace('%year%', copyright_date(), $options['copyright'])
			) );
	} else {
		echo ent2ncr('(c) ' . copyright_date() . ' ' . get_bloginfo( 'name' ));
	}
	echo ']]></copyright>';
}

add_action('rss2_head', 'ts_feed_spruce_rss2_head');

/** Helper */
function _ts_feed_spruce_mime_content_type($filename) {
	$matches = array();
	if (preg_match('|\.([a-z0-9]{2,4})$|i', $filename, $matches)) {
		$mime_types = array(
			'bmp' => 'image/bmp',
			'gif' => 'image/gif',
			'jpe' => 'gimage/jpeg',
			'jpg' => 'image/jpeg',
			'jpe' => 'image/jpeg',
			'png' => 'image/png',
			'ico' => 'image/x-icon',
			);
		if (array_key_exists($matches[1], $mime_types))
			return $mime_types[$matches[1]];
	}
	return 'unknown/unknown';
}

/** Helper for WP2.9- */
function _ts_feed_spruce_has_post_thumbnail() {
	if (function_exists('has_post_thumbnail')) return has_post_thumbnail();
	else return (bool)get_post_meta( get_the_ID(), '_thumbnail_id', true );
}

/**
 * Inject into the RSS item xml, after content etc.
 * @see http://www.w3schools.com/rss/rss_reference.asp
 * @see http://www.rssboard.org/media-rss
 */
function ts_feed_spruce_rss2_item() {
	global $post;
	$options = ts_feed_spruce_get_options();
	if (!$options['thumbnail'] || !_ts_feed_spruce_has_post_thumbnail()) return;

	$id = get_post_thumbnail_id();
	if (!empty($id)) {
		$img = wp_get_attachment_image_src($id, 'thumbnail', true);

		$intermediate = image_get_intermediate_size($id, 'thumbnail');
		if ($intermediate != false) {
			$wpud = wp_upload_dir();
			$filename = trailingslashit($wpud['basedir']) . $intermediate['path'];
			if (file_exists($filename)) $img[98] = @filesize($filename);
			else $img[98] = 0;
			$img[99] = $intermediate['mime-type'];
		} else {
			$img[98] = 0;
			$img[99] = _ts_feed_spruce_mime_content_type($img[0]);
		}

		// @see http://www.w3schools.com/rss/rss_tag_enclosure.asp
		echo '<enclosure url="' . $img[0] .
			'" length="' . $img[98] .
			'" type="' . $img[99] .
			'" />';
		// @see http://www.rssboard.org/media-rss#media-content
		echo '<media:content url="'. $img[0] .
			'" width="' . $img[1] .	'" height="' . $img[2] .
			'" fileSize="' . $img[98] .
			'" type="' . $img[99] .
			'" medium="image" />';
	} else {
		// TODO : Get first image, @see http://codex.wordpress.org/Function_Reference/get_children#Examples
	}
}

add_action('rss2_item', 'ts_feed_spruce_rss2_item');

/**
 * Inject into Atom xml header
 */
function ts_feed_spruce_atom_head() {
	$options = ts_feed_spruce_get_options();
	if (!empty($favicon))
		echo '<icon>' . esc_url($options['atomicon']) . '</icon>';
	if (!empty($options['atomlogo']))
		echo '<logo>' . esc_url($options['atomlogo']) . '</logo>';
}

add_action('atom_head', 'ts_feed_spruce_atom_head');

/**
 * Hook into the feed content
 * @param type $content
 * @param type $feed_type
 * @return $content
 * @see http://wp.smashingmagazine.com/2011/12/07/10-tips-optimize-wordpress-theme/
 */
function ts_feed_spruce_the_content_feed($content, $feed_type) {
	global $post;

	/* if (has_post_thumbnail($post->ID) )
		$content = '<p>' . get_the_post_thumbnail($post->ID, 'thumbnail') . '</p>' . $content; */

	$options = ts_feed_spruce_get_options();
	if ($options['sociallinks']) {
		$content .= '<p class="social-links">';
		$content .= ts_feed_spruce_get_social_links(
			get_permalink(), get_the_title_rss() );
		$content .= '</p>';
	}
	if (!empty($options['append'])) {
		$content .= '<p class="appended">';
		$content .= str_ireplace(
			array('%id%', '%url%'),
			array(get_the_ID(), apply_filters('the_permalink_rss', get_permalink())),
			$options['append']);
		$content .= '</p>';
	}
	return $content;
}

add_filter('the_content_feed', 'ts_feed_spruce_the_content_feed', 10, 2);

/**
 * Hook into the feed discription
 * @param type $content
 * @return $content
 */
function ts_feed_spruce_the_excerpt_rss($content) {
	if (get_option('rss_use_excerpt')) return ts_feed_spruce_the_content_feed($content, false);
	else return $content;
}

add_filter('the_excerpt_rss', 'ts_feed_spruce_the_excerpt_rss');

/**
 * Modify the guid to append modified date, ensuring edited posts get re-syndicated
 */
function ts_feed_spruce_get_the_guid($content) {
	// double ? is ok as guid is not a url
	// '&' gets esc_url'ed and doesn't work anyway
	$content .= '?d=' . get_the_modified_time('YmdHisT');
	if (function_exists('md5')) return md5($content);
	else return $content;
}

add_filter('get_the_guid', 'ts_feed_spruce_get_the_guid', 7);

/**
 * [feedonly] shortcode
 * @since 0.0.2
 * @see http://wp.smashingmagazine.com/2011/12/07/10-tips-optimize-wordpress-theme/
 */
function ts_feed_spruce_feedonly( $atts, $content = null ) {
	if( is_feed() ) return $content;
	else return;
}

add_shortcode( 'feedonly', 'ts_feed_spruce_feedonly' );


/* eof */
