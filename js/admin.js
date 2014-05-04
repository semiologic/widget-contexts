
var widgetContexts = {
	toggle : function(elt) {
		var is_checked = jQuery(elt).prop('checked');
		jQuery(elt).closest('div').find(':checkbox').attr('checked', is_checked);

		var widgetId = jQuery('.widget-id');
		widgetId.change();
	},

	markChanged : function(elt) {
		var widget = jQuery('.customize-control.expanded');
		var control = widget.find('.widget-content').find('input').first();
		control.change();
	}
};

window.widgetContexts = widgetContexts;

jQuery(document).ready(function($) {
	jQuery('a.widget_contexts_setup').live('click', function() {
		var c = $(this).closest('div.widget_contexts'),
			b = c.children('input.widget_contexts_base').val(),
			s = c.children('input.widget_contexts_strip').val().split(','),
			h = $('#widget_contexts').html();
		
		c.fadeOut('fast', function() {
			c.html(h);
			c.find('input:checkbox').each(function() {
				var i, t = $(this), n = t.attr('name');
				for ( i = 0; i < s.length ; i++ ) {
					if ( n == '[' + s[i] + ']' ) {
						t.attr('checked', false);
						break;
					}
				}
				t.attr('name', b + n);
			});
		}).fadeIn('fast');
		
		return false;
	});

	var mw = $('.widget-content').css('maxWidth');
	if ( parseInt(mw) <= '350')
		$( '.widget-content').css('maxWidth', '350px');
});