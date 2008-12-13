<?php
/*
Plugin Name: Widget Contexts
Plugin URI: http://www.semiologic.com/software/widgets/widget-contexts/
Description: Lets you manage whether widgets should show display or not based on the context.
Author: Denis de Bernardy
Version: 1.0.1
Author URI: http://www.getsemiologic.com
Update Service: http://version.semiologic.com/wordpress
Update Tag: widget_contexts
Update Package: http://www.semiologic.com/media/software/widgets/widget-contexts/widget-contexts.zip
*/

/*
Terms of use
------------

This software is copyright Mesoconcepts Ltd, and is distributed under the terms of the Mesoconcepts license. In a nutshell, you may freely use it for any purpose, but may not redistribute it without written permission.

http://www.mesoconcepts.com/license/
**/


class widget_contexts
{
	#
	# init()
	#

	function init()
	{
		if ( !is_admin() )
		{
			add_action('init', array('widget_contexts', 'widgetize'), 100);
		}
	} # init()
	
	
	#
	# widgetize()
	#
	
	function widgetize()
	{
		$options = widget_contexts::get_options();
		#dump($options);
		global $wp_registered_widgets;
		global $widget_contexts_callbacks;
		
		if ( class_exists('inline_widgets') || class_exists('feed_widgets') )
		{
			$sidebars_widgets = get_option('sidebars_widgets');
			$ignore = array_merge((array) $sidebars_widgets['inline_widgets'], (array) $sidebars_widgets['feed_widgets']);
		}
		else
		{
			$ignore = array();
		}

		foreach ( array_keys((array) $wp_registered_widgets) as $widget_id )
		{
			if ( !in_array($widget_id, $ignore) )
			{
				$widget_contexts_callbacks[$widget_id] = $wp_registered_widgets[$widget_id]['callback'];
				$wp_registered_widgets[$widget_id]['callback'] = create_function('$args, $widget_args = 1',
					'return widget_contexts::widget(\'' . $widget_id . '\', $args, $widget_args);');
			}
		}
	} # widgetize()
	
	
	#
	# widget()
	#
	
	function widget($widget_id, $args, $widget_args = 1)
	{
		global $widget_contexts_callbacks;
		
		static $options;
		static $page_templates;
		static $context;
		static $is_page = false;
		
		if ( !isset($context) )
		{
			$options = widget_contexts::get_options();
			
			if ( is_front_page() )
			{
				$context = 'home';
			}
			elseif ( is_home() )
			{
				$context = 'blog';
			}
			elseif ( is_single() )
			{
				$context = 'post';
			}
			elseif ( is_page() )
			{
				$is_page = true;
				
				$template = get_post_meta($GLOBALS['wp_query']->get_queried_object_id(), '_wp_page_template', true);
				
				switch ( $template )
				{
				case 'default':
					$post = get_post($GLOBALS['wp_query']->get_queried_object_id());
					
					while ( $post->post_parent != 0 )
					{
						$post = get_post($post->post_parent);
					}
					
					$context = 'section_' . $post->ID;
					break;
				
				default:
					$context = 'template_' . $template;
					break;
				}
			}
			elseif ( is_singular() )
			{
				$context = 'attachment';
			}
			elseif ( is_category() )
			{
				$context = 'category';
			}
			elseif ( is_tag() )
			{
				$context = 'tag';
			}
			elseif ( is_search() )
			{
				$context = 'search';
			}
			elseif ( is_404() )
			{
				$context = '404_error';
			}
			else
			{
				$context = 'archive';
			}
		}
		
		if ( isset($options[$widget_id][$context]) )
		{
			$active = $options[$widget_id][$context];
		}
		elseif ( !$is_page )
		{
			$active = !isset($options[$widget_id][$context]);
		}
		elseif ( $context == 'template_letter.php' )
		{
			$active = in_array($widget_id, array('entry_content', 'entry_comments'));
		}
		else
		{
			$active = !isset($options[$widget_id]['page']) || $options[$widget_id]['page'];
		}
		
		
		if ( $active )
		{
			call_user_func_array($widget_contexts_callbacks[$widget_id], array($args, $widget_args));
		}
	} # widget()
	
	
	#
	# get_options()
	#
	
	function get_options()
	{
		if ( ( $o = get_option('widget_contexts') ) === false )
		{
			$o = array();
			update_option('widget_contexts', $o);
		}
		
		return $o;
	} # get_options()
} # widget_contexts

widget_contexts::init();


if ( is_admin() )
{
	include dirname(__FILE__) . '/widget-contexts-admin.php';
}
?>