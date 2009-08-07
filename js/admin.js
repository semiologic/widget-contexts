jQuery(document).ready(function(){
	jQuery("div.widget_contexts input.check_toggle").livequery('change', function() {
		var is_checked = jQuery(this).attr('checked');
		jQuery(this).parent().parent().parent().parent().children("ul").children("li").children("label").children("input:checkbox").attr('checked', is_checked);
	});
});