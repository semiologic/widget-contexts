<?php
/*
Plugin Name: Widget Contexts
Plugin URI: http://www.semiologic.com/software/widget-contexts/
Description: Lets you manage whether widgets should display or not based on the context.
Version: 2.0 RC
Author: Denis de Bernardy
Author URI: http://www.getsemiologic.com
Text Domain: widget-contexts-info
Domain Path: /lang
*/

/*
Terms of use
------------

This software is copyright Mesoconcepts (http://www.mesoconcepts.com), and is distributed under the terms of the Mesoconcepts license. In a nutshell, you may freely use it for any purpose, but may not redistribute it without written permission.

http://www.mesoconcepts.com/license/
**/


load_plugin_textdomain('widget-contexts', null, dirname(__FILE__) . '/lang');


/**
 * widget_contexts
 *
 * @package Widget Contexts
 **/

add_action('admin_print_scripts-widgets.php', array('widget_contexts', 'admin_print_scripts'));
add_action('admin_print_styles-widgets.php', array('widget_contexts', 'admin_print_styles'));

add_action('save_post', array('widget_contexts', 'save_post'));
add_filter('body_class', array('widget_contexts', 'body_class'));

#add_action('register_widget', array('widget_contexts', 'register_widget'), 20);
add_filter('widget_display_callback', array('widget_contexts', 'display'), 0, 3);
add_filter('widget_update_callback', array('widget_contexts', 'update'), 30, 4);
add_action('in_widget_form', array('widget_contexts', 'form'), 30, 3);

class widget_contexts {
	/**
	 * admin_print_scripts()
	 *
	 * @return void
	 **/

	function admin_print_scripts() {
		$folder = plugin_dir_url(__FILE__) . 'js';
		wp_enqueue_script('jquery-livequery', $folder . '/jquery.livequery.js', array('jquery'),  '1.1', true);
		wp_enqueue_script( 'widget-contexts', $folder . '/admin.js', array('jquery-ui-sortable', 'jquery-livequery'),  '20090603', true);
	} # admin_print_scripts()
	
	
	/**
	 * admin_print_styles()
	 *
	 * @return void
	 **/

	function admin_print_styles() {
		$folder = plugin_dir_url(__FILE__) . 'css';
		wp_enqueue_style('widget-contexts', $folder . '/admin.css', null, '20090603');
		
		add_filter('admin_body_class', array('widget_contexts', 'admin_body_class'));
		
		global $wp_registered_widget_controls;
		foreach ( array_keys($wp_registered_widget_controls) as $widget_id ) {
			if ( !is_array($wp_registered_widget_controls[$widget_id]['callback']) )
				continue;
			if ( !is_a($wp_registered_widget_controls[$widget_id]['callback'][0], 'WP_Widget') )
				continue;
			if ( $wp_registered_widget_controls[$widget_id]['width'] >= 460 )
				continue;
			$wp_registered_widget_controls[$widget_id]['width'] = 460;
			$wp_registered_widget_controls[$widget_id]['callback'][0]->control_options['width'] = 460;
		}
	} # admin_print_styles()
	
	
	/**
	 * admin_body_class()
	 *
	 * @param array $classes
	 * @return array $classes
	 **/

	function admin_body_class($classes) {
		$contexts = widget_contexts::get_contexts();
		
		if ( class_exists('search_reloaded') && !$contexts['templates'] )
			$classes .= ' widget_contexts-no_entry_specials';
		
		if ( class_exists('search_reloaded') )
			$classes .= ' widget_contexts-search_reloaded';
		
		if ( !$contexts['templates'] )
			$classes .= ' widget_contexts-no_templates';
		
		return $classes;
	} # admin_body_class()
	
	
	/**
	 * save_post()
	 *
	 * @param int $post_id
	 * @return void
	 **/

	function save_post($post_id) {
		$post = get_post($post_id);
		
		if ( $post->post_type != 'page' )
			return;
		
		delete_transient('cached_section_ids');
	} # save_post()
	
	
	/**
	 * cache_section_ids()
	 *
	 * @return void
	 **/

	function cache_section_ids() {
		global $wpdb;
		
		$pages = $wpdb->get_results("
			SELECT	*
			FROM	$wpdb->posts
			WHERE	post_type = 'page'
			");
		
		update_post_cache($pages);
		
		$to_cache = array();
		foreach ( $pages as $page )
			$to_cache[] = $page->ID;
		
		update_postmeta_cache($to_cache);
		
		foreach ( $pages as $page ) {
			$parent = $page;
			while ( $parent->post_parent )
				$parent = get_post($parent->post_parent);
			
			if ( "$parent->ID" !== get_post_meta($page->ID, '_section_id', true) )
				update_post_meta($page->ID, '_section_id', "$parent->ID");
		}
		
		set_transient('cached_section_ids', 1);
	} # cache_section_ids()
	
	
	/**
	 * body_class()
	 *
	 * @param array $classes
	 * @return array $classes
	 **/

	function body_class($classes) {
		if ( !is_page() )
			return $classes;
		
		if ( !get_transient('cached_section_ids') )
			widget_contexts::cache_section_ids();
		
		global $wp_the_query;
		
		$section_id = get_post_meta($wp_the_query->get_queried_object_id(), '_section_id', true);
		$section = get_post($section_id);
		
		$classes[] = sanitize_html_class('section-' . $section->post_name, 'section-' . $section_id);
		
		return $classes;
	} # body_class()
	
	
	/**
	 * display()
	 *
	 * @param array $instance
	 * @param object $widget
	 * @param array $args
	 * @return array $instance
	 **/

	function display($instance, $widget, $args) {
		if ( $instance === false || in_array($args['id'], array('inline_widgets', 'feed_widgets', 'the_404')) )
			return $instance;
		
		$context = widget_contexts::get_context();
		
		if ( isset($instance['widget_contexts'][$context]) )
			$active = $instance['widget_contexts'][$context];
		elseif ( !is_page() )
			$active = !isset($instance['widget_contexts'][$context]);
		elseif ( is_page() && function_exists('is_letter') && is_letter() )
			$active = preg_match("/^entry_(?:content|comments)/", $args['widget_id']);
		else
			$active = !isset($instance['widget_contexts']['page'])
				|| isset($instance['widget_contexts']['page']) && $instance['widget_contexts']['page'];
		
		return $active ? $instance : false;
	} # display()
	
	
	/**
	 * update()
	 *
	 * @param array $instance the widget's instance
	 * @param array $new_instance the POST data
	 * @param array $old_instance the instance's previous value
	 * @param object $widget
	 * @return array $instance
	 **/

	function update($instance, $new_instance, $old_instance, $widget) {
		$sidebar_id = $_POST['sidebar'];
		
		if ( in_array($sidebar_id, array('inline_widgets', 'feed_widgets', 'the_404')) ) {
			$instance['widget_contexts'] = wp_parse_args($old_instance['widget_contexts'], array());
			return $instance;
		}
		
		$all_contexts = widget_contexts::get_contexts();
		
		foreach ( $all_contexts as $contexts )
			foreach ( array_keys($contexts) as $context )
				$instance['widget_contexts'][$context] = isset($new_instance['widget_contexts'][$context]);
		
		if ( !in_array($sidebar_id, array('the_entry', 'before_the_entries', 'after_the_entries')) ) {
			if ( isset($old_instance['widget_contexts']['template_letter']) )
				$instance['widget_contexts']['template_letter'] = $old_instance['widget_contexts']['template_letter'];
			else
				unset($instance['widget_contexts']['template_letter']);
		}
		
		if ( in_array($sidebar_id, array(
			'top_sidebar', 'top-sidebar', 'bottom_sidebar', 'bottom-sidebar',
			'wide_sidebar', 'wide-sidebar', 'ext_sidebar', 'ext-sidebar',
			'left_sidebar', 'left-sidebar', 'right_sidebar', 'right-sidebar',
			'sidebar-1', 'sidebar-2'
			)) ) {
			if ( isset($old_instance['widget_contexts']['template_monocolumn']) )
				$instance['widget_contexts']['template_monocolumn'] = $old_instance['widget_contexts']['template_monocolumn'];
			else
				unset($instance['widget_contexts']['template_monocolumn']);
		}
		
		if ( $sidebar_id == 'the_entry' ) {
			if ( isset($old_instance['widget_contexts']['error_404']) )
				$instance['widget_contexts']['error_404'] = $old_instance['widget_contexts']['error_404'];
			else
				unset($instance['widget_contexts']['error_404']);
			
			if ( class_exists('search_reloaded') ) {
				if ( isset($old_instance['widget_contexts']['search']) )
					$instance['widget_contexts']['search'] = $old_instance['widget_contexts']['search'];
				else
					unset($instance['widget_contexts']['search']);
			}
		}
		
		return $instance;
	} # update()
	
	
	/**
	 * form()
	 *
	 * @param object &$widget WP_Widget
	 * @param array $instance
	 * @return void
	 **/

	function form(&$widget, &$return, $instance) {
		$all_contexts = widget_contexts::get_contexts();
		
		$contexts = is_array($instance['widget_contexts']) ? $instance['widget_contexts'] : array();
		$contexts = wp_parse_args($contexts, array('page' => true));
		
		echo '<div class="widget_contexts">' . "\n"
			. '<div class="widget_contexts_float">' . "\n";
		
		
		echo '<div>' . "\n";
		
		foreach ( $all_contexts['normal'] as $context => $label ) {
			if ( !isset($contexts[$context]) )
				$contexts[$context] = true;
		}
		
		echo '<h3>'
			. '<label>'
			. '<span class="hide-if-no-js">'
			. '<input type="checkbox" class="check_toggle">'
			. '&nbsp;'
			. '</span>'
			. __('Widget Contexts', 'widget-contexts')
			. '</label>'
			. '</h3>' . "\n";
		
		echo '<ul>' . "\n";
		
		foreach ( $all_contexts['normal'] as $context => $label ) {
			echo '<li>'
				. '<label>'
				. '<input type="checkbox"'
					. ' name="' . $widget->get_field_name('widget_contexts') . '[' . $context . ']"'
					. checked($contexts[$context], true, false)
					. ' />'
				. '&nbsp;'
				. $label
				. '</label>'
				. '</li>' . "\n";
		}
		
		echo '</ul>' . "\n"
			. '</div>' . "\n";
		
		
		echo '</div>' . "\n"
			. '<div class="widget_contexts_float">' . "\n";
		
		
		echo '<div>' . "\n";
		
		foreach ( $all_contexts['sections'] as $context => $label ) {
			if ( !isset($contexts[$context]) )
				$contexts[$context] = $contexts['page'];
		}
			
		echo '<h4>'
			. '<label>'
			. '<span class="hide-if-no-js">'
			. '<input type="checkbox" class="check_toggle">'
			. '&nbsp;'
			. '</span>'
			. __('Page Sections', 'widget-contexts')
			. '</label>'
			. '</h4>' . "\n";
		
		echo '<ul>' . "\n";
		
		foreach ( $all_contexts['sections'] as $context => $label ) {
			echo '<li>'
				. '<label>'
				. '<input type="checkbox"'
					. ' name="' . $widget->get_field_name('widget_contexts') . '[' . $context . ']"'
					. checked($contexts[$context], true, false)
					. ' />'
				. '&nbsp;'
				. $label
				. '</label>'
				. '</li>' . "\n";
		}
		
		echo '</ul>' . "\n"
			. '<ul>' . "\n";
		
		echo '<li>'
			. '<label>'
			. '<input type="checkbox"'
				. ' name="' . $widget->get_field_name('widget_contexts') . '[page]"'
				. checked($contexts['page'], true, false)
				. ' />'
			. '&nbsp;'
			. __('New Section', 'widget-contexts')
			. '</label>'
			. '</li>' . "\n";
		
		echo '</ul>' . "\n"
			. '</div>' . "\n";
		
		
		echo '</div>' . "\n"
			. '<div class="widget_contexts_float widget_contexts-templates-specials">' . "\n";
		
		
		echo '<div class="widget_contexts-templates">' . "\n";
		
		foreach ( $all_contexts['templates'] as $context => $label ) {
			if ( !isset($contexts[$context]) ) {
				if ( $context == 'template_letter' )
					$contexts[$context] = preg_match("/^entry_(?:content|comments)/", $widget->id_base);
				else
					$contexts[$context] = $contexts['page'];
			}
		}
		
		echo '<h4>'
			. '<label>'
			. '<span class="hide-if-no-js">'
			. '<input type="checkbox" class="check_toggle">'
			. '&nbsp;'
			. '</span>'
			. __('Page Templates', 'widget-contexts')
			. '</label>'
			. '</h4>' . "\n";
		
		echo '<ul>' . "\n";
		
		foreach ( $all_contexts['templates'] as $context => $label ) {
			echo '<li'
				. ( in_array($context, array('template_letter', 'template_monocolumn'))
					? ( ' class="widget_context-' . $context . '"' )
					: ''
					)
				. '>'
				. '<label>'
				. '<input type="checkbox"'
					. ' name="' . $widget->get_field_name('widget_contexts') . '[' . $context . ']"'
					. checked($contexts[$context], true, false)
					. ' />'
				. '&nbsp;'
				. $label
				. '</label>'
				. '</li>' . "\n";
		}
		
		echo '</ul>' . "\n";
		
		
		echo '</div>' . "\n"
			. '<div class="widget_contexts-special">' . "\n";
		
		
		foreach ( $all_contexts['special'] as $context => $label ) {
			if ( !isset($contexts[$context]) )
				$contexts[$context] = true;
		}
		
		echo '<h4>'
			. '<label>'
			. '<span class="hide-if-no-js">'
			. '<input type="checkbox" class="check_toggle">'
			. '&nbsp;'
			. '</span>'
			. __('Special Contexts', 'widget-contexts')
			. '</label>'
			. '</h4>' . "\n";
		
		echo '<ul>' . "\n";
		
		foreach ( $all_contexts['special'] as $context => $label ) {
			echo '<li'
				. ( in_array($context, array('error_404'))
					? ( ' class="widget_context-' . $context . '"' )
					: ''
					)
				. '>'
				. '<label>'
				. '<input type="checkbox"'
					. ' name="' . $widget->get_field_name('widget_contexts') . '[' . $context . ']"'
					. checked($contexts[$context], true, false)
					. ' />'
				. '&nbsp;'
				. $label
				. '</label>'
				. '</li>' . "\n";
		}
		
		echo '</ul>' . "\n";
		
		echo '</div>' . "\n";
		
		
		echo '</div>' . "\n"
			. '<div style="clear: both;"></div>' . "\n"
			. '</div>' . "\n";
	} # form()
	
	
	/**
	 * get_context()
	 *
	 * @return string $context
	 **/

	function get_context() {
		static $context;
		
		if ( isset($context) )
			return $context;
		
		if ( is_front_page() ) {
			$context = 'home';
			
			# override for sales letter
			if ( is_page() && function_exists('is_letter') && is_letter() )
				$context = 'template_letter';
		} elseif ( is_home() ) {
			$context = 'blog';
		} elseif ( is_single() ) {
			$context = 'post';
		} elseif ( is_page() ) {
			global $wp_the_query;
			$page_id = $wp_the_query->get_queried_object_id();
			$template = get_post_meta($page_id, '_wp_page_template', true);
			
			switch ( $template ) {
			case 'default':
				if ( !get_transient('cached_section_ids') )
					widget_contexts::cache_section_ids();
				
				$section_id = get_post_meta($page_id, '_section_id', true);
				
				$context = 'section_' . $section_id;
				break;
			
			default:
				$template = trim(strip_tags($template));
				$template = str_replace('.php', '', $template);
				$template = sanitize_title($template);
				
				$context = 'template_' . $template;
				break;
			}
		} elseif ( is_singular() ) {
			$context = 'attachment';
		} elseif ( is_category() ) {
			$context = 'category';
		} elseif ( is_tag() ) {
			$context = 'tag';
		} elseif ( is_search() ) {
			$context = 'search';
		} elseif ( is_404() ) {
			$context = 'error_404';
		} else {
			$context = 'archive';
		}
		
		return $context;
	} # get_context()
	
	
	/**
	 * get_contexts()
	 *
	 * @return array $contexts
	 **/

	function get_contexts() {
		static $contexts;
		
		if ( isset($contexts) )
			return $contexts;
		
		global $wpdb;
		
		$page_templates = array();
		$templates = (array) get_page_templates();
		foreach ( $templates as $label => $file ) {
			$file = trim(strip_tags($file));
			$file = str_replace('.php', '', $file);
			$file = sanitize_title($file);
			$label = trim(strip_tags($label));
			$page_templates['template_' . $file] = $label;
		}
		
		$page_sections = array();
		$sections = (array) $wpdb->get_results("
			SELECT	ID,
					post_title
			FROM	$wpdb->posts
			WHERE	post_type = 'page'
			AND		post_parent = 0
			ORDER BY menu_order, post_title
			");
		
		if ( get_option('show_on_front') == 'page' ) {
			$home_page_id = get_option('page_on_front');
			$blog_page_id = get_option('page_for_posts');
			$ignore = array($home_page_id, $blog_page_id);
		} else {
			$ignore = array();
		}

		foreach ( $sections as $section ) {
			if ( in_array($section->ID, $ignore) )
				continue;
			$page_sections['section_' . $section->ID] = trim(strip_tags($section->post_title));
		}
		
		$contexts = array(
			'normal' => array(
				'home' => __('Front Page', 'widget-contexts'),
				'blog' => __('Blog on a Static Page', 'widget-contexts'),
				'post' => __('Post', 'widget-contexts'),
				'attachment' => __('Attachment', 'widget-contexts'),
				'category' => __('Category Archives', 'widget-contexts'),
				'tag' => __('Tag Archives', 'widget-contexts'),
				'author' => __('Author Archives', 'widget-contexts'),
				'archive' => __('Date Archives', 'widget-contexts'),
				),
			'special' => array(
				'search' => __('Search Results', 'widget-contexts'),
				'error_404' => __('Not Found Error (404)', 'widget-contexts'),
				),
			'sections' => $page_sections,
			'new_section' => array(
				'page' => false,
				),
			'templates' => $page_templates,
			);
		
		return $contexts;
	} # get_contexts()
} # widget_contexts
?>