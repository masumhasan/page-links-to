<?php
/*
Plugin Name: Page Links To
Plugin URI: http://txfx.net/code/wordpress/page-links-to/
Description: Allows you to set a "links_to" meta key with a URI value that will be be used when listing WP pages.  Good for setting up navigational links to non-WP sections of your 
Version: 1.4
Author: Mark Jaquith
Author URI: http://txfx.net/
*/

/*  Copyright 2005-2006  Mark Jaquith (email: mark.gpl@txfx.net)

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation; either version 2 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
*/

/*
=== INSTRUCTIONS ===
1) upload this file to /wp-content/plugins/
2) activate this plugin in the WordPress interface
3) create a new page with a title of your choosing, and with the parent page of your choosing.  Leave the content of the page blank.
4) add a meta key "links_to" with a full URI value (like "http://google.com/") (obviously without the quotes)

That's it!  Now, when you use wp_list_page(), that page should link to the "links_to" value, instead of its page

You can also use links_to_type to set the redirect type (default is 302, but you can specify 301)
You can also use links_to_target to set the target of the link (like _new).  This will only work for wp_list_pages()
*/ 

function txfx_get_page_links_to_meta () {
	global $wpdb, $page_links_to_cache;

	if ( !isset($page_links_to_cache) ) {
		$links_to = $wpdb->get_results(
		"SELECT post_id, meta_value " .
		"FROM $wpdb->postmeta, $wpdb->posts " .
		"WHERE post_id = ID AND meta_key = 'links_to' AND (post_status = 'static' OR post_status = 'publish')");
	} else {
		return $page_links_to_cache;
	}

	if ( !$links_to ) {
		$page_links_to_cache = false;
		return false;
	}

	foreach ( (array) $links_to as $link ) {
		$page_links_to_cache[$link->post_id] = $link->meta_value;
	}

	return $page_links_to_cache;
}

function txfx_get_page_links_to_targets () {
	global $wpdb, $page_links_to_target_cache;

	if ( !isset($page_links_to_target_cache) ) {
		$links_to = $wpdb->get_results(
		"SELECT post_id, meta_value " .
		"FROM $wpdb->postmeta, $wpdb->posts " .
		"WHERE post_id = ID AND meta_key = 'links_to_target' AND (post_status = 'static' OR post_status = 'publish')");
	} else {
		return $page_links_to_target_cache;
	}

	if ( !$links_to ) {
		$page_links_to_target_cache = false;
		return false;
	}

	foreach ( (array) $links_to as $link ) {
		$page_links_to_target_cache[$link->post_id] = $link->meta_value;
	}

	return $page_links_to_target_cache;
}

function txfx_filter_links_to_pages ($link, $post) {
	$page_links_to_cache = txfx_get_page_links_to_meta();
	
	// Really strange, but page_link gives us an ID and post_link gives us a post object
	$id = ($post->ID) ? $post->ID : $post;

	if ( $page_links_to_cache[$id] )
		$link = $page_links_to_cache[$id];

	return $link;
}

function txfx_redirect_links_to_pages () {
	if ( is_single() || is_page() ) :
		global $wp_query;

		$link = get_post_meta($wp_query->post->ID, 'links_to', true);

		if ( !$link )
			return;

		$redirect_type = get_post_meta($wp_query->post->ID, 'links_to_type', true);

		if ( $redirect_type && $redirect_type != '302' ) {
			// Only supporting 301 and 302 for now.
			// The others aren't widely supported or needed anyway
			header("HTTP/1.0 301 Moved Permanently");
			header("Status: 301 Moved Permanently");
			header("Location: $link");
			exit;
		}

		// If we got this far, it's a 302 redirect
		header("Status: 302 Moved Temporarily");
		wp_redirect($link);
		exit;
	endif;
}

function txfx_page_links_to_highlight_tabs($pages) {
	$page_links_to_cache = txfx_get_page_links_to_meta();
	$page_links_to_target_cache = txfx_get_page_links_to_targets();

	if ( !$page_links_to_cache && !$page_links_to_target_cache)
		return $pages;

	$this_url = 'http://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
	$targets = array();

	foreach ( (array) $page_links_to_cache as $id => $page ) {
		if ( $page_links_to_target_cache[$id] )
			$targets[$page] = $page_links_to_target_cache[$id];

echo "<!--TEST\n";
echo str_replace('http://www.', 'http://', trailingslashit(get_bloginfo('home'))) . "\n";
echo str_replace('http://www.', 'http://', trailingslashit($page)) . "\n";
echo "-->";

		if ( str_replace('http://www.', 'http://', $this_url) == str_replace('http://www.', 'http://', $page) || ( is_home() && str_replace('http://www.', 'http://', trailingslashit(get_bloginfo('home'))) == str_replace('http://www.', 'http://', trailingslashit($page)) ) ) {
			$highlight = true;
			$current_page = $page;
		}
	}

	if ( count($targets) ) {
		foreach ( $targets as  $p => $t ) {
			$pages = str_replace('<a href="' . $p . '" ', '<a href="' . $p . '" target="' . $t . '" ', $pages);
		}
	}

	if ( $highlight ) {
		$pages = str_replace(' class="page_item current_page_item"', ' class="page_item"', $pages);
		$pages = str_replace('<li class="page_item"><a href="' . $current_page . '"', '<li class="page_item current_page_item"><a href="' . $current_page . '"', $pages);
	}

	return $pages;
}

add_filter('wp_list_pages', 'txfx_page_links_to_highlight_tabs');
add_action('template_redirect', 'txfx_redirect_links_to_pages');
add_filter('page_link', 'txfx_filter_links_to_pages', 20, 2);
add_filter('post_link', 'txfx_filter_links_to_pages', 20, 2);
?>