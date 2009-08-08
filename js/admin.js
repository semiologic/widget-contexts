jQuery(document).ready(function() {
	logScript('widget-contexts.js');
	jQuery('.widget_context_toggle').livequery('change', function() {
		var is_checked = jQuery(this).attr('checked');
		jQuery(this).closest('div').find(':checkbox').attr('checked', is_checked);
	});
	logScript('end widget-contexts.js');
});