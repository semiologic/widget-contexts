<?php

class widget_contexts_admin
{
	#
	# init()
	#
	
	function init()
	{
		add_action('init', array('widget_contexts_admin', 'widgetize'), 1000);
	} # init()
	
	
	#
	# widgetize()
	#
	
	function widgetize()
	{
		if ( strpos($_SERVER['REQUEST_URI'], 'wp-admin/widgets.php') === false
			|| in_array($_REQUEST['sidebar'], array('feed_widgets', 'inline_widgets', 'the_404'))
			) return;
		
		global $wp_registered_widgets;
		global $wp_registered_widget_controls;
		global $wp_registered_widget_updates;
		global $widget_contexts_controls;
		
		foreach ( array_keys((array) $wp_registered_widgets) as $widget_id )
		{
			if ( !isset($wp_registered_widget_controls[$widget_id]) )
			{
				wp_register_widget_control(
					$widget_id,
					$wp_registered_widgets[$widget_id]['name'],
					array('widget_contexts_admin', 'default_control')
					);
			}
		}
		
		#echo '<pre>';
		#var_dump($wp_registered_widget_controls);
		#echo '</pre>';

		foreach ( array_keys((array) $wp_registered_widget_controls) as $widget_id )
		{
			$widget_contexts_controls[$widget_id] = $wp_registered_widget_controls[$widget_id]['callback'];
			$wp_registered_widget_controls[$widget_id]['callback'] = create_function('$widget_args = 1',
				'return widget_contexts_admin::control(\'' . $widget_id . '\', $widget_args);'
				);
			$wp_registered_widget_controls[$widget_id]['width'] += 220;
			$wp_registered_widget_updates[$widget_id]['callback'] = $wp_registered_widget_controls[$widget_id]['callback'];
		}
	} # widgetize()
	
	
	#
	# default_control()
	#
	
	function default_control($widget_args = null)
	{
		echo __('There are no options for this widget');
	} # default_control()
	
	
	#
	# control()
	#
	
	function control($widget_id, $widget_args = 1)
	{
		global $wp_registered_widgets;
		global $widget_contexts_controls;
		
		static $updated = false;
		
		$options = widget_contexts::get_options();
		
		if ( !$updated && !empty($_POST['sidebar']) )
		{
			$sidebars_widgets = wp_get_sidebars_widgets(false);
			
			$widget_ids = (array) $_POST['widget-id'];
			
			foreach ( $sidebars_widgets as $sidebar_id => $sidebar )
			{
				if ( in_array($sidebar_id, array('feed_widgets', 'inline_widgets'))
					|| $sidebar_id == $_POST['sidebar'] )
				{
					continue;
				}
				
				$widget_ids = array_merge($widget_ids, (array) $sidebar);
			}
			
			# remove contexts for deleted widgets
			foreach ( array_keys($options) as $id )
			{
				if ( !in_array($id, $widget_ids) )
				{
					unset($options[$id]);
				}
			}
			
			# update contexts for updated widgets
			foreach ( $_POST['widget_contexts'] as $id => $contexts )
			{
				foreach ( array_keys(widget_contexts_admin::get_contexts()) as $context )
				{
					$contexts[$context] = isset($contexts[$context]);
				}

				$options[$id] = $contexts;
			}
			
			update_option('widget_contexts', $options);
			#dump($options); die;
			$updated = true;
		}
		
		echo '<table cellpadding="0" cellspacing="0" border="0" style="width:98%;">'
			. '<tr valign="top">';

		echo '<td style="padding: 2px; padding-right: 10px">';

		call_user_func_array($widget_contexts_controls[$widget_id], array($widget_args));
		
		echo '</td>';
		
		echo '<td style="width: 190px; padding: 2px; padding-left: 10px; border-left: dotted 1px Silver;">';
		
		echo '<h3 style="text-align: left;"><strong>' . __('Widget Contexts') . '</strong></h3>';
		
		echo '<p style="text-align: left;">';
		
		if ( is_array($widget_args) && $widget_args['number'] == -1 )
		{
			$widget_id = preg_replace("/-1$/", '-%i%', $widget_id);
		}
		
		#dump($widget_id);

		foreach ( widget_contexts_admin::get_contexts() as $context => $label )
		{
			if ( isset($options[$widget_id][$context]) )
			{
				$active = $options[$widget_id][$context];
			}
			elseif ( $context == 'template_letter.php' )
			{
				$active = in_array($widget_id, array('entry_content', 'entry_comments'));
			}
			elseif ( strpos($context, 'section_') === 0 )
			{
				$active = !isset($options[$widget_id]['page']) || $options[$widget_id]['page'];
			}
			else
			{
				$active = true;
			}
			
			echo '<label>'
				. '<input type="checkbox"'
					. ' name="widget_contexts[' . $widget_id . '][' . $context . ']"'
					. ( $active
						? ' checked="checked"'
						: ''
						)
					. ' />'
				. '&nbsp;'
				. $label
				. '</label>'
				. '<br />';
		}
		
		echo '</p>';
		
		echo '<p style="text-align: left;">'
			. '<a href="http://www.semiologic.com/resources/widgets/widget-contexts/">'
			. 'About these contexts'
			. '</a>'
			. '</p>';
		
		echo '</td>';
		
		echo '</tr>'
			. '</table>';
	} # control()
	
	
	#
	# get_contexts()
	#
	
	function get_contexts()
	{
		static $contexts;
		
		if ( !isset($contexts) )
		{
			global $wpdb;
			
			$sections = (array) $wpdb->get_results("
				SELECT	ID,
						post_title
				FROM	$wpdb->posts
				WHERE	post_type = 'page'
				AND		post_parent = 0
				ORDER BY lower(post_title)
				");
			
			$section_contexts = array();
			
			if ( get_option('show_on_front') == 'page' )
			{
				$home_page_id = get_option('page_on_front');
				$blog_page_id = get_option('page_for_posts');
				$ignore = array($home_page_id, $blog_page_id);
			}
			else
			{
				$ignore = array();
			}

			foreach ( $sections as $section )
			{
				if ( in_array($section->ID, $ignore) ) continue;
				$section_contexts['section_' . $section->ID] = 'Section / ' . trim(strip_tags($section->post_title));
			}

			$template_contexts = array();
			
			$templates = (array) get_page_templates();
			
			global $wp_registered_sidebars;
			if ( isset($_GET['sidebar']) && isset($wp_registered_sidebars[$_GET['sidebar']]) )
			{
				$sidebar = esc_attr($_GET['sidebar']);
			} elseif ( is_array($wp_registered_sidebars) && $wp_registered_sidebars )
			{
				$sidebar = array_shift($keys = array_keys($wp_registered_sidebars));
			}

			foreach ( $templates as $label => $file )
			{
				if ( $file == 'letter.php'
					&& !in_array($sidebar, array('the_entry', 'before_the_entries', 'after_the_entries'))
					) continue;
				
				$template_contexts['template_' . $file] = 'Template / ' . $label;
			}
			
			$contexts = array(
				'home' => 'Home',
				'blog' => 'Blog on a Static Page',
				'post' => 'Post',
				'page' => 'Page',
				)
				+ $section_contexts
				+ $template_contexts
				+ array (
				'attachment' => 'Attachment',
				'category' => 'Category',
				'tag' => 'Tag',
				'archive' => 'Archive',
				'search' => 'Search',
				);
			
			if ( $_REQUEST['sidebar'] != 'the_entry' )
			{
				$contexts += array('404_error' => '404 Error');
			}
		}
		
		return $contexts;
	} # get_contexts()
} # widget_contexts_admin

widget_contexts_admin::init();

?>