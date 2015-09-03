=== Widget Contexts ===
Contributors: Denis-de-Bernardy, Mike_Koepke
Donate link: https://www.semiologic.com/donate/
Tags: semiologic, widgets
Requires at least: 3.1
Tested up to: 4.3
Stable tag: trunk

Allows to turn widgets on and off based on the context.


== Description ==

The Widget Contexts plugin for WordPress will let you turn widgets on and off based on the context.



= Help Me! =

The [Semiologic Support Page](https://www.semiologic.com/support/) is the best place to report issues.


== Installation ==

1. Upload the plugin folder to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress


== Change Log ==

= 2.6 =

- Fix a bunch of missed static/non-static php calling warnings that got missed
- WP 4.3 compat
- Tested against PHP 5.6

= 2.5 =

- WP 4.0 compat

= 2.4 =

- Compatible with WP 3.9 widget customizer
- Changes to Page Sections not always flushing widget context caching.
- Code refactoring
- WP 3.9 compat

= 2.3.1 =

- Further tweaks around the widget context caching

= 2.3 =

- Fix issue where widgets were assigned to new pages even if the New Sections setting was off.
- Improved context caching to work better with page revisions and auto-saves.
- Fixed font in Customize Headings due to WP 3.8 admin changes
- WP 3.8 compat

= 2.2 =

- WP 3.6 compat
- PHP 5.4 compat

= 2.1.2 =

- Fix svn versioning issue

= 2.1.1 =

- Correct unknown index warnings

= 2.1 =

- WP 3.5 compat
- Recoded for removed _get_post_ancestors function
- Fixed section toggles in customize to uncheck all.  Requires use of .prop instead of .attr with jQuery 1.6+

= 2.0.4 =

- Change unexpected behavior (page sections vs page template).

= 2.0.3 =

- WP 3.0 compat

= 2.0.2 =

- Cache improvements

= 2.0.1 =

- WP 2.9 compat

= 2.0 =

- Complete Rewrite
- Localization
- Code enhancements and optimizations
- Use WP_Widgets