<?php

namespace WPGraphQL\Events;

use WPGraphQL\Utils\EventMonitor;

/**
 * Class EventRegistry
 *
 * @package WPGraphQL\Events
 */
class EventRegistry {

    /**
     * Array of registered events.
     *
     * @var array
     */
    protected static $registered_events = [];

    /**
     * EventRegistry constructor.
     *
     * @param array $default_events Optional. Array of event configurations to initialize with.
     */
    public function __construct( array $default_events = [] ) {
        $this->registered_events = $default_events;
    }

    /**
     * Initialize the Event Registry.
     *
     * @return void
     */
    public function init(): void {
        do_action( 'graphql_register_events', $this );
        $this->registered_events = apply_filters( 'graphql_registered_events', $this->registered_events, $this );
        $this->attach_events();
    }

    /**
     * Register a new event.
     *
     * Adds an event configuration to the static list of registered events.
     *
     * @param string   $name      Unique identifier for the event.
     * @param string   $hook_name The WordPress action hook to attach the event to.
     * @param callable $callback  Callback function to execute when the action fires.
     * @param int      $priority  Optional. Priority for the callback. Default 10.
     * @param int      $arg_count Optional. Number of arguments accepted by the callback. Default 1.
     *
     * @return void
     */
    public static function register_event( $name, $hook_name, $callback, $priority = 10, $arg_count = 1 ) {
        self::$registered_events[] = [
            'name'      => $name,
            'hook_name' => $hook_name,
            'callback'  => $callback,
            'priority'  => $priority,
            'arg_count' => $arg_count,
        ];
    }

    /**
     * Attach all registered events to their corresponding WordPress actions.
     *
     * @return void
     */
    public static function attach_events() {
        foreach ( self::$registered_events as $config ) {
            add_action( $config['hook_name'], function ( ...$hook_args ) use ( $config ) {
                // Skip event escape hatch.
                $maybe_skip = apply_filters( "graphql_event_should_handle_{$config['name']}", null, ...$hook_args );
                if ( null !== $maybe_skip && false === $maybe_skip ) {
                    return;
                }

                // Execute event callback.
                $payload = call_user_func( $config['callback'], ...$hook_args );

                // Handle errors.
                if ( is_wp_error( $payload ) ) {
                    do_action( "graphql_event_error_{$config['name']}", $payload, $hook_args );
                    return;
                }

                // Filter the payload before tracking.
                $payload = apply_filters( "graphql_event_payload_{$config['name']}", $payload, $hook_args );

                // Track event in a custom event monitor or DB table.
                EventMonitor::track( $config['name'], $payload );

            }, $config['priority'], $config['arg_count'] );
        }
    }
}