<?php
defined( 'ABSPATH' ) or die( 'Cheatin\' uh?' );

/*
Plugin Name: Rocket Uptime Monitoring
Plugin URI: https://wordpress.org/plugins/rocket-uptime-monitoring/
Description: Receive a notification by email when your website is down.
Version: 1.0
Author: WP Rocket
Author URI: http://wp-rocket.me

Text Domain: rocket-uptime-monitoring
Domain Path: languages

*/

define( 'RUM_VERSION'		, '1.0' );
define( 'RUM_NAME'			, 'Rocket Uptime Monitoring' );
define( 'RUM_SLUG'			, 'rocket_uptime_monitoring' );
define( 'RUM_API_URL'		, 'https://support.wp-rocket.me/api/monitoring/process.php' );
define( 'RUM_API_USER_AGENT', 'WP-Rocket' );

class Rocket_Uptime_Monitoring {
	/**
	 * Plugin Options
	 *
	 * @var array
	 * @access private
	 */
	private $options  = array();

	/**
	 * Options settings fields
	 *
	 * @var array
	 * @access private
	 */
	private $settings = array();

	/**
	 * Constructor
	 */
	function __construct() {
		// Tell WP what to do when the plugin is loaded
		add_action( 'plugins_loaded', array( &$this, 'init' ) );

		// Tell wp what to do when the plugin is activated
		register_activation_hook( __FILE__, array( &$this, 'activate' ) );

		// Tell wp what to do when plugin is deactivated
		register_deactivation_hook( __FILE__, array( &$this, 'deactivate' ) );
	}

	/**
	 * Fires all hooks.
	 *
	 * @since 1.0
	 *
	 * @access public
	 * @return void
	 */
	public function init() {
		// Load translations
		load_plugin_textdomain( 'rocket-uptime-monitoring', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
    
		// Set plugin options
		$this->options = get_option( RUM_SLUG );
		
		// Add menu page
		add_action( 'admin_menu', array( &$this, 'add_submenu' ) );

		// Add a link to the configuration page of the plugin
 		add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), array( &$this, 'action_links' ) );

		// Settings API
		add_action( 'admin_init', array( &$this, 'register_setting' ) );
		add_action( 'update_option_' . RUM_SLUG, array( &$this, 'update_option' ), 10, 2 );
	}

	/**
	 * This function is called when the plugin is activated.
	 *
	 * @since 1.0
	 *
	 * @access public
	 * @return void
	 */
	public function activate() {
		// Add default options
		if( ! $this->options ) {
			$this->options['email'] = get_option( 'admin_email' );
			update_option( RUM_SLUG, array( 'email' => $this->options['email'] ) );
		}

		// Call API
		$api = wp_remote_post(
			RUM_API_URL,
			array(
				'user-agent' => RUM_API_USER_AGENT,
				'timeout'	 => 10,
				'body'       => array(
					'action' => 'add',
					'url'    => home_url(),
					'email'  => $this->options['email']
				)
			)
		);
		$data = wp_remote_retrieve_body( $api );
		$data = json_decode( $data );

		if ( $data->status == 'success' ) {
			update_option( RUM_SLUG, array( 'email' => $this->options['email'], 'token' => $data->token ) );
		}
	}

	/**
	 * This function is called when plugin is deactivated.
	 *
	 * @since 1.0
	 *
	 * @access public
	 * @return void
	*/
	public function deactivate() {
		// Remove website's monitoring with the API.
		wp_remote_post(
			RUM_API_URL,
			array(
				'user-agent' => RUM_API_USER_AGENT,
				'timeout'	 => 10,
				'body'       => array(
					'action' => 'delete',
					'url'    => home_url(),
					'token'  => $this->options['token']
				)
			)
		);
	}

	/*
	 * Declare/Get all settings/fields
	 *
	 * @since 1.0
	 *
	 * @access private
	 * @return void
	*/
	private function get_settings() {
		$this->settings['email'] = array(
			'section' 	=> 'general',
			'title'		=> __( 'Email', 'rocket-uptime-monitoring' ),
			'type'		=> 'text',
			'std'		=> get_option( 'admin_email' ),
			'desc' 		=> __ ( 'Emails will be sent at this specific address.', 'rocket-uptime-monitoring' )
		);
	}

	/**
	 * HTML output for each kind of fields
	 *
	 * @since 1.0
	 *
	 * @access public
	 * @return string field output
	 */
	public function display_settings( $args = array() ) {
		switch ( $args['type'] ) {
			case 'text':
			default:
		 		echo '<input class="regular-text" type="text" id="' . esc_attr( $args['id'] ) . '" name="' . RUM_SLUG . '[' . esc_attr( $args['id'] ) . ']"  value="' . esc_attr( $this->options[$args['id']] ) . '" />';

		 		if ( ! empty( $args['desc'] ) ) {
		 			echo '<br/><p class="description">' . esc_html( $args['desc'] ) . '</p>';
		 		}
		 		break;
		}
	}

	/*
	 * Create fields with Settings API
	 *
	 * @since 1.0
	 *
	 * @access public
	 * @return void
	*/
	public function create_settings( $args = array() ) {
		$field_args = array(
			'type'      => $args['type'],
			'id'        => $args['id'],
			'desc'      => ( isset( $args['desc'] ) ) ? $args['desc'] : false,
			'label_for' => $args['id'],
			'std'		=> ( isset( $args['std'] ) ) ? $args['std'] : false,
			'choices'	=> ( isset( $args['choices'] ) ) ? $args['choices'] : false
		);

		add_settings_field( $args['id'], $args['title'], array( $this, 'display_settings' ), __FILE__, $args['section'], $field_args );
	}

	/**
	 * Register settings with the WP Settings API.
	 *
	 * @since 1.0
	 *
	 * @access public
	 * @return void
	 */
	public function register_setting() {
		register_setting( RUM_SLUG , RUM_SLUG, array( &$this, 'settings_callback' ) );
		add_settings_section( 'general', '', false, __FILE__);

		// Get the configuration of fields
		$this->get_settings();

		// Generate fields
		foreach ( $this->settings as $id => $setting ) {
			$setting['id'] = $id;
			$this->create_settings( $setting );
		}
	}

	/**
	 * Used to clean and sanitize the settings fields.
	 *
	 * @since 1.0
	 *
	 * @access public
	 * @param array $inputs
	 * @return array $inputs
	 */
	public function settings_callback( $inputs ) {
		if ( empty( $inputs['email'] ) ) {
			$inputs['email'] = $this->settings['email']['std'];
		}
		return $inputs;
	}

	/**
	 * When our settings are saved: update website email.
	 *
	 * @access public
	 * @param string $oldvalue
	 * @param string $value
	 * @return void
	 */
	public function update_option( $oldvalue, $value ) {
		if ( ! empty( $_POST ) && ( $oldvalue['email'] != $value['email'] ) ) {
			 wp_remote_post(
				RUM_API_URL,
				array(
					'user-agent' => RUM_API_USER_AGENT,
					'timeout'	 => 10,
					'body'       => array(
						'action' => 'update',
						'url'    => home_url(),
						'email'  => $value['email'],
						'token'  => $this->options['token']
					)
				)
			);
		}
	}

	/**
	 * Add submenu in menu "Settings"
	 *
	 * @since 1.0
	 *
	 * @access public
	 * @return void
	 */
	public function add_submenu() {
		add_options_page( RUM_NAME, RUM_NAME, 'manage_options', RUM_SLUG, array( &$this, 'display_page' ) );
	}

	/**
	 * Add a link to the configuration page of the plugin
	 *
	 * @since 1.0
	 *
	 * @access public
	 * @return array $actions
	 */
	public function action_links( $actions ) {
		array_unshift( $actions, sprintf( '<a href="%s">%s</a>', admin_url( 'options-general.php?page=' . RUM_SLUG ), __( 'Settings' ) ) );
	    return $actions;
	}

	/**
	 * Display the options page
	 *
	 * @since 1.0
	 *
	 * @access public
	 * @return string Options page output
	 */
	public function display_page() { ?>
		<div class="wrap">
			<h2><?php echo RUM_NAME . ' <small><sup>' . RUM_VERSION . '</sup></small>'; ?></h2>
			<p><?php _e( 'We check your website every 5 minutes.', 'rocket-uptime-monitoring' ); ?></p>
			<p><?php _e( 'If your website is down, you will be notified by email.', 'rocket-uptime-monitoring' ); ?>
			<br/>
			<?php _e( 'We will let you know once your website is back.', 'rocket-uptime-monitoring' ); ?>
			</p>
			<p><?php printf( __( 'This service is brought with love by <a href="%s">WP Rocket</a>.', 'rocket-uptime-monitoring' ), 'http://wp-rocket.me/?utm_source=wordpress&utm_medium=plugin&utm_campaign=rocket_uptime_monitoring' ); ?></p>
			<form method="post" action="options.php">
			    <input type="hidden" name="<?php echo RUM_SLUG; ?>[token]" value="<?php echo $this->options['token']; ?>" />
			    <?php
		    	settings_fields( RUM_SLUG );
		    	do_settings_sections(__FILE__);
				submit_button();
			    ?>
			</form>
		</div>
	<?php
	}
}

// Start this plugin once all other plugins are fully loaded
new Rocket_Uptime_Monitoring();