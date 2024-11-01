<?php

if ( ! class_exists('SimplePieTopMusicChartsSort')) {
	require_once ABSPATH . WPINC . '/class-simplepie.php';;

	/**
	 *  We can use the SimplePie library Wordpress provides, but we'll have to extend it to sort the feeds correctly
	 */
	class SimplePieTopMusicChartsSort extends SimplePie
	{

		public static function sort_items($a, $b)
		{
			return (int) $a->get_item_tags('', 'rank_this_week')[0]['data'] >= (int) $b->get_item_tags('', 'rank_this_week')[0]['data'];
		}

	}
}
