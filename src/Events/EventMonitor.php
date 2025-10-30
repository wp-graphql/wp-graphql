<?php
namespace WPGraphQL\Events;

/**
 * Class EventMonitor
 *
 * @package WPGraphQL\Events
 */
class EventMonitor {
	/**
	 * Tracks an event by firing a corresponding WordPress action.
	 *
	 * @param string $event_name The unique name of the event being tracked.
	 * @param mixed  $payload    The data payload associated with the event. Can be any type of data relevant to the event.
	 *
	 * @return void
	 */
	public static function track( $event_name, $payload ) {
		do_action( "wpgraphql_event_tracked_{$event_name}", $payload );
	}

}