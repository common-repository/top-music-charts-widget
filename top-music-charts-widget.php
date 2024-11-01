<?php
/**
 * @package Top Music Charts Widget
 */
/*
Plugin Name: Top Music Charts Widget
Plugin URI: https://wordpress.org/plugins/top-music-charts-widget/
Description: Display a widget showing the current top Billboard charts of your choosing.
Version: 1.1.0
Author: grimmdude
Author URI: http://grimmdude.com
Text Domain: top-music-charts-widget
License: GPLv2 or later
*/

/*
This program is free software; you can redistribute it and/or
modify it under the terms of the GNU General Public License
as published by the Free Software Foundation; either version 2
of the License, or (at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
*/

// Make sure we don't expose any info if called directly
if ( ! function_exists('add_action')) {
	echo "Hi there!  I'm just a plugin, not much I can do when called directly.";
	exit;
}


if ( ! class_exists('TopMusicChartsWidget')) {
	class TopMusicChartsWidget extends WP_Widget
	{

		private $charts;

		private $text_domain = 'top-music-charts-widget';


		/**
		  * Sets up the widgets name etc
		  */
		public function __construct()
		{
			$this->charts = require_once 'charts.php';

			$widget_ops = array( 
				'classname' => 'top_music_charts_widget',
				'description' => __('Shows the current top Billboard charts of your choosing.'),
			);

			parent::__construct( 'top_music_charts_widget', 'Top Music Charts', $widget_ops );
		}


		/**
		  * Wordpress' fetch_feed() function but using the our extended SimplePie class for sorting.
		  * There isn't a better way to sort SimplePie?
		  */
		private function fetch_feed($url)
		{
			return fetch_feed($url);
			require_once ABSPATH . WPINC . '/class-feed.php';
			require_once plugin_dir_path(__FILE__ ) . 'class-simplepie-sort.php';

			$feed = new SimplePieTopMusicChartsSort();

			$feed->set_sanitize_class( 'WP_SimplePie_Sanitize_KSES' );
			// We must manually overwrite $feed->sanitize because SimplePie's
			// constructor sets it before we have a chance to set the sanitization class
			$feed->sanitize = new WP_SimplePie_Sanitize_KSES();

			$feed->set_cache_class( 'WP_Feed_Cache' );
			$feed->set_file_class( 'WP_SimplePie_File' );

			$feed->set_feed_url( $url );
			/** This filter is documented in wp-includes/class-feed.php */
			$feed->set_cache_duration( apply_filters( 'wp_feed_cache_transient_lifetime', 12 * HOUR_IN_SECONDS, $url ) );
			/**
			 * Fires just before processing the SimplePie feed object.
			 *
			 * @since 3.0.0
			 *
			 * @param object &$feed SimplePie feed object, passed by reference.
			 * @param mixed  $url   URL of feed to retrieve. If an array of URLs, the feeds are merged.
			 */
			do_action_ref_array( 'wp_feed_options', array( &$feed, $url ) );
			$feed->init();
			$feed->set_output_encoding( get_option( 'blog_charset' ) );
			$feed->handle_content_type();

			if ( $feed->error() )
				return new WP_Error( 'simplepie-error', $feed->error() );

			return $feed;
		}


		/**
		  * Outputs the content of the widget
		  *
		  * @param array $args
		  * @param array $instance
		  */
		public function widget($args, $instance)
		{
			// outputs the content of the widget
			//$url = 'http://www.billboard.com/rss/charts/' . $instance['chart'];
			$url = 'http://feeds.musicchartfeeds.com/' . $instance['chart'] . '?format=xml';
			$feed = $this->fetch_feed($url);

			if ( ! is_wp_error($feed)) {
				$feed->enable_order_by_date(false);
				$available_count = $feed->get_item_quantity($instance['max']);
				$rss_items = $feed->get_items(0, $instance['max']);

				echo $args['before_widget'];

				if ( ! empty($instance['title'])) {
					echo $args['before_title'] . apply_filters('widget_title', $instance['title']) . $args['after_title'];
				}

				if ($available_count > 0) {
					?>
						<ul>
							<?php foreach($rss_items as $item): ?>
								<li>
									<strong><?php echo $item->get_item_tags('', 'title')[0]['data']; ?></strong>
								</li>
							<?php endforeach ?>
						</ul>
					<?php

				} else {
					echo '<p>' . __('No results', $this->text_domain) . '</p>';
				}

			} else {
				echo '<p>' . __('There was an error fetching the feed.', $this->text_domain) . '</p>';
			}

			echo $args['after_widget'];
		}


		/**
		  * Outputs the options form on admin
		  *
		  * @param array $instance The widget options
		  */
		public function form($instance)
		{
			?>
				<p>
					<label for="<?php echo $this->get_field_id('title'); ?>"><?php _e('Title:'); ?></label> 
					<input type="text" class="widefat" id="<?php echo $this->get_field_id('title'); ?>" name="<?php echo $this->get_field_name('title'); ?>" value="<?php echo array_key_exists('title', $instance) ? esc_attr($instance['title']) : ''; ?>" />
				</p>
				<p>
					<label for="<?php echo $this->get_field_id('chart'); ?>"><?php _e('Chart:'); ?></label> 
					<select class="widefat" id="<?php echo $this->get_field_id('chart'); ?>" name="<?php echo $this->get_field_name('chart'); ?>">
						<?php foreach ($this->charts as $group_name => $group): ?>
							<optgroup label="<?php echo $group_name; ?>">
								<?php foreach ($group as $chart_name => $url): ?>
									<option value="<?php echo $url; ?>" <?php echo array_key_exists('chart', $instance) && $instance['chart'] === $url ? 'selected="selected"' : ''; ?>><?php echo $chart_name; ?></option>
								<?php endforeach ?>
							</optgroup>
						<?php endforeach ?>
					</select>
				</p>
				<p>
					<label for="<?php echo $this->get_field_id('max'); ?>"><?php _e('Number of results:'); ?></label> 
					<input type="number" step="1" min="1" size="3" class="tiny-text" id="<?php echo $this->get_field_id('max'); ?>" name="<?php echo $this->get_field_name('max'); ?>" value="<?php echo array_key_exists('max', $instance) ? esc_attr($instance['max']) : '10'; ?>" />
				</p>
			<?php
		}


		/**
		  * Processing widget options on save
		  *
		  * @param array $new_instance The new options
		  * @param array $old_instance The previous options
		  */
		public function update($new_instance, $old_instance)
		{
			$instance = array();
			$instance['title'] = ( ! empty( $new_instance['title'] ) ) ? strip_tags( $new_instance['title'] ) : '';
			$instance['max'] = ( ! empty( $new_instance['max'] ) ) ? strip_tags( $new_instance['max'] ) : '10';
			$instance['chart'] = ( ! empty( $new_instance['chart'] ) ) ? strip_tags( $new_instance['chart'] ) : 'greatest-billboard-200-albums';

			return $instance;
		}

	}

	add_action('widgets_init', function () {
		register_widget('TopMusicChartsWidget');
	});
}