<?php


namespace WPGraphQL\Admin;

use Carbon_Fields\Container;
use Carbon_Fields\Field;

class Settings {
    protected $plugins;
    protected $options;

    private $prefix = 'wpgql_option_';
    private $prefixInDB = '__wpgql_option_';

    /**
     * Settings constructor.
     */
    public function __construct() {
        $this->plugins = [];
        $this->options = [];

        \Carbon_Fields\Carbon_Fields::boot();

        self::actions();
//        self::set_all_initial_settings();
//        self::remove_all_settings();
    }

//    /**
//     * @param $plugin_name
//     * @param $config
//     *
//     * @return mixed
//     */
//    public function register_plugin( $plugin_name, $config ) {
//        if ( isset( $this->plugins[ $plugin_name ] ) ) {
//            // TODO: Throw exception
//            return $this->plugins[ $plugin_name ];
//        }
//
//        $this->plugins[ $plugin_name ] = $config;
//
//        return $this->plugins[ $plugin_name ];
//    }
//
//    /**
//     * @param $option_name
//     * @param $config
//     *
//     * @return mixed
//     */
//    public function register_option( $option_name, $config ) {
//        if ( isset( $this->options[ $option_name ] ) ) {
//            // TODO: Throw exception
//            return $this->options[ $option_name ];
//        }
//
//        $this->options[ $option_name ] = $config;
//
//        return $this->options[ $option_name ];
//    }

    public function get_initial_options() {
        return array(
            'enable_graphiql' => 'yes',
        );
    }

    private function set_all_initial_settings() {
        foreach ( self::get_initial_options() as $option => $value ) {
            add_option( ( $this->prefixInDB . $option ), $value );
        }
    }

    function startsWith( string $string, string $start ) {
        return substr( $string, 0, strlen( $start ) ) === $start;
    }

    private function remove_all_settings() {
        foreach ( wp_load_alloptions() as $option => $value ) {
            if ( self::startsWith( $option, $this->prefixInDB ) ) {
                delete_option( $option );
            }
        }
    }


    /**
     * Sets up actions to run at certain spots throughout WordPress and the WPGraphQL execution cycle
     */
    private function actions() {
        add_action( 'carbon_fields_register_fields', [ $this, 'wpgql_add_plugin_settings_page' ] );
        add_action( 'admin_menu', [ $this, 'wpgql_remove_initial_submenu' ], 99 );
    }

    /**
     * By default WordPress generates a submenu page from its parent menu. This deletes the default page that is not needed.
     */
    public function wpgql_remove_initial_submenu() {
        remove_submenu_page( 'wpgql-settings-main', 'wpgql-settings-main' );
    }


    public function wpgql_add_plugin_settings_page() {
        $basic_settings_container =
            Container::make( 'theme_options', __( 'WPGraphQL', 'wp-graphql' ) )
                     ->set_page_file( 'wpgql-settings-main' )
                     ->set_page_menu_title( 'WPGraphQL' )
                     ->set_icon( 'data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHZpZXdCb3g9IjAgMCA0MDAgNDAwIj48cGF0aCBmaWxsPSIjRTEwMDk4IiBkPSJNNTcuNDY4IDMwMi42NmwtMTQuMzc2LTguMyAxNjAuMTUtMjc3LjM4IDE0LjM3NiA4LjN6Ii8+PHBhdGggZmlsbD0iI0UxMDA5OCIgZD0iTTM5LjggMjcyLjJoMzIwLjN2MTYuNkgzOS44eiIvPjxwYXRoIGZpbGw9IiNFMTAwOTgiIGQ9Ik0yMDYuMzQ4IDM3NC4wMjZsLTE2MC4yMS05Mi41IDguMy0xNC4zNzYgMTYwLjIxIDkyLjV6TTM0NS41MjIgMTMyLjk0N2wtMTYwLjIxLTkyLjUgOC4zLTE0LjM3NiAxNjAuMjEgOTIuNXoiLz48cGF0aCBmaWxsPSIjRTEwMDk4IiBkPSJNNTQuNDgyIDEzMi44ODNsLTguMy0xNC4zNzUgMTYwLjIxLTkyLjUgOC4zIDE0LjM3NnoiLz48cGF0aCBmaWxsPSIjRTEwMDk4IiBkPSJNMzQyLjU2OCAzMDIuNjYzbC0xNjAuMTUtMjc3LjM4IDE0LjM3Ni04LjMgMTYwLjE1IDI3Ny4zOHpNNTIuNSAxMDcuNWgxNi42djE4NUg1Mi41ek0zMzAuOSAxMDcuNWgxNi42djE4NWgtMTYuNnoiLz48cGF0aCBmaWxsPSIjRTEwMDk4IiBkPSJNMjAzLjUyMiAzNjdsLTcuMjUtMTIuNTU4IDEzOS4zNC04MC40NSA3LjI1IDEyLjU1N3oiLz48cGF0aCBmaWxsPSIjRTEwMDk4IiBkPSJNMzY5LjUgMjk3LjljLTkuNiAxNi43LTMxIDIyLjQtNDcuNyAxMi44LTE2LjctOS42LTIyLjQtMzEtMTIuOC00Ny43IDkuNi0xNi43IDMxLTIyLjQgNDcuNy0xMi44IDE2LjggOS43IDIyLjUgMzEgMTIuOCA0Ny43TTkwLjkgMTM3Yy05LjYgMTYuNy0zMSAyMi40LTQ3LjcgMTIuOC0xNi43LTkuNi0yMi40LTMxLTEyLjgtNDcuNyA5LjYtMTYuNyAzMS0yMi40IDQ3LjctMTIuOCAxNi43IDkuNyAyMi40IDMxIDEyLjggNDcuN00zMC41IDI5Ny45Yy05LjYtMTYuNy0zLjktMzggMTIuOC00Ny43IDE2LjctOS42IDM4LTMuOSA0Ny43IDEyLjggOS42IDE2LjcgMy45IDM4LTEyLjggNDcuNy0xNi44IDkuNi0zOC4xIDMuOS00Ny43LTEyLjhNMzA5LjEgMTM3Yy05LjYtMTYuNy0zLjktMzggMTIuOC00Ny43IDE2LjctOS42IDM4LTMuOSA0Ny43IDEyLjggOS42IDE2LjcgMy45IDM4LTEyLjggNDcuNy0xNi43IDkuNi0zOC4xIDMuOS00Ny43LTEyLjhNMjAwIDM5NS44Yy0xOS4zIDAtMzQuOS0xNS42LTM0LjktMzQuOSAwLTE5LjMgMTUuNi0zNC45IDM0LjktMzQuOSAxOS4zIDAgMzQuOSAxNS42IDM0LjkgMzQuOSAwIDE5LjItMTUuNiAzNC45LTM0LjkgMzQuOU0yMDAgNzRjLTE5LjMgMC0zNC45LTE1LjYtMzQuOS0zNC45IDAtMTkuMyAxNS42LTM0LjkgMzQuOS0zNC45IDE5LjMgMCAzNC45IDE1LjYgMzQuOSAzNC45IDAgMTkuMy0xNS42IDM0LjktMzQuOSAzNC45Ii8+PC9zdmc+' );

        self::create_settings_subpage( $basic_settings_container, 1 );
        self::create_status_subpage( $basic_settings_container, 2 );
    }

    private function create_settings_subpage( $parent, $position ) {
        Container::make( 'theme_options', __( 'Settings', 'wp-graphql' ) )
                 ->set_page_position( $position )
                 ->set_page_file( 'wpgql-settings' )
                 ->set_page_parent( $parent ) // reference to a top level container
                 ->add_tab( __( 'General' ), array(
                Field::make( 'checkbox', $this->prefix . 'enable_graphiql', __( 'Enable GraphiQL', 'wp-graphql' ) ),
            ) )
                 ->add_tab( __( 'WPGatsby', 'wp-graphql' ), array(
                     Field::make( 'html', 'wpgql_information_text4' )
                          ->set_html( '<h2>WPGraphiQL</h2>' )
                 ) )
            ->add_tab( __( 'ACF', 'wp-graphql' ), array(
                Field::make( 'html', 'wpgql_information_text6' )
                     ->set_html( '<h2>WPGraphiQL</h2>' )
            ) )
            ->add_tab( __( 'WooCommerce', 'wp-graphql' ), array(
                Field::make( 'html', 'wpgql_information_text7' )
                     ->set_html( '<h2>WPGraphiQL</h2>' )
            ) );
    }


    private function create_status_subpage( $parent, $position ) {
        Container::make( 'theme_options', __( 'Status', 'wp-graphql' ) )
                 ->set_page_position( $position )
                 ->set_page_file( 'wpgql-status' )
                 ->set_page_parent( $parent ) // reference to a top level container
                 ->add_tab( __( 'Plugins' ),
                array(
                    Field::make( 'html', 'wpgql_information_text2' )
                         ->set_html( '<h2>Lorem ipsum</h2><p>Quisque mattis ligula.</p>' )
                ) )
                 ->add_tab( __( 'Error Logs', 'wp-graphql' ), array(
                         Field::make( 'html', 'wpgql_information_text3' )
                              ->set_html( '<h2>Lorem ipsum</h2><p>Quisque mattis ligula.</p>' )
                     )
                 );
    }

    private function create_extensions_subpage( $parent, $position ) {
        Container::make( 'theme_options', __( 'Extensions', 'wp-graphql' ) )
                 ->set_page_position( $position )
                 ->set_page_file( 'wpgql-extensions' )
                 ->set_page_parent( $parent ) // reference to a top level container
                 ->set_html( '<h2>Lorem ipsum</h2><p>Quisque mattis ligula.</p>' );
    }
}