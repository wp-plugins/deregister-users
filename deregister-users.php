<?php

/**
 * Deregister Users
 *
 * Turns registered users into anonymous users, by creating the necessary
 * post-meta for each topic and reply, and zeroing out the post_author.
 *
 * $Id$
 *
 * @package DeregisterUsers
 * @subpackage Main
 */

/**
 * Plugin Name: Deregister Users
 * Plugin URI:  http://wordpress.org/extend/plugins/deregister-users/
 * Description: Converts topic and reply authors into anonymous, logged out users
 * Author:      John James Jacoby
 * Author URI:  http://johnjamesjacoby.com
 * Version:     0.1
 * Text Domain: wpdu
 */

// Exit if accessed directly
if ( !defined( 'ABSPATH' ) ) exit;

if ( !class_exists( 'WP_Deregister_Users' ) ) :
/**
 * Main Class
 *
 * @since 0.1
 */
final class WP_Deregister_Users {

	/** Magic *****************************************************************/

	/**
	 * We use several variables, most of which can be filtered to customize
	 * the way that it works. To prevent unauthorized access, these variables
	 * are stored in a private array that is magically updated using PHP 5.2+
	 * methods. This is to prevent third party plugins from tampering with
	 * essential information indirectly, which would cause issues later.
	 *
	 * @see WP_Deregister_Users::setup_globals()
	 * @var array
	 */
	private $data;

	/** Singleton *************************************************************/

	/**
	 * @var WP_Deregister_Users The one true instance
	 */
	private static $instance;

	/**
	 * Main Instance
	 *
	 * Insures that only one instance exists in memory at any one time. Also
	 * prevents needing to define globals all over the place.
	 *
	 * @since 0.1
	 *
	 * @staticvar array $instance
	 * @uses WP_Deregister_Users::setup_globals() Setup the globals needed
	 * @uses WP_Deregister_Users::setup_actions() Setup the hooks and actions
	 * @return The one true instance
	 */
	public static function instance() {
		if ( ! isset( self::$instance ) ) {
			self::$instance = new WP_Deregister_Users();
			self::$instance->setup_globals();
			self::$instance->setup_actions();
		}
		return self::$instance;
	}

	/** Magic Methods *********************************************************/

	/**
	 * A dummy constructor to prevent loading more than once.
	 *
	 * @since 0.1
	 * @see WP_Deregister_Users::instance()
	 */
	private function __construct() { /* Do nothing here */ }

	/**
	 * A dummy magic method to prevent cloning
	 *
	 * @since 0.1
	 */
	public function __clone() { _doing_it_wrong( __FUNCTION__, 'Doing it wrong, chief.', '0.1' ); }

	/**
	 * A dummy magic method to prevent unserialization
	 *
	 * @since 0.1
	 */
	public function __wakeup() { _doing_it_wrong( __FUNCTION__, 'Doing it wrong, chief.', '0.1' ); }

	/**
	 * Magic method for checking the existence of a certain custom field
	 *
	 * @since 0.1
	 */
	public function __isset( $key ) { return isset( $this->data[$key] ); }

	/**
	 * Magic method for getting varibles
	 *
	 * @since 0.1
	 */
	public function __get( $key ) { return isset( $this->data[$key] ) ? $this->data[$key] : null; }

	/**
	 * Magic method for setting varibles
	 *
	 * @since 0.1
	 */
	public function __set( $key, $value ) { $this->data[$key] = $value; }

	/**
	 * Magic method to prevent notices and errors from invalid method calls
	 *
	 * @since 0.1
	 */
	public function __call( $name = '', $args = array() ) { unset( $name, $args ); return null; }

	/** Private Methods *******************************************************/

	/**
	 * Set some smart defaults to class variables. Allow some of them to be
	 * filtered to allow for early overriding.
	 *
	 * @since 0.1
	 *
	 * @access private
	 * @uses plugin_dir_path() To generate plugin path
	 * @uses plugin_dir_url() To generate plugin url
	 * @uses apply_filters() Calls various filters
	 */
	private function setup_globals() {

		/** Versions **********************************************************/

		$this->version    = '0.1';
		$this->db_version = '100';

		/** Paths *************************************************************/

		// Setup some base path and URL information
		$this->file       = __FILE__;
		$this->basename   = plugin_basename( $this->file );
		$this->plugin_dir = plugin_dir_path( $this->file );
		$this->plugin_url = plugin_dir_url ( $this->file );
	}

	/**
	 * Setup the default hooks and actions
	 *
	 * @since 0.1
	 * @access private
	 * @uses add_action() To add various actions
	 */
	private function setup_actions() {

		// If being deactivated, do not add any actions
		if ( $this->is_deactivation( $this->basename ) )
			return;

		// Menu actions
		add_action( 'bbp_admin_menu',       array( $this, 'admin_menu'       ) );
		add_action( 'bbp_admin_head',       array( $this, 'admin_head'       ) );

		// Action action
		add_action( 'bbp_admin_init',       array( $this, 'deregister_users' ) );

		// Add the 'Users' tab to tools
		add_filter( 'bbp_tools_admin_tabs', array( $this, 'tools_tab' ) );
	}

	/** Actions ***************************************************************/
	
	/**
	 * Add the faux menu page, removed in admin_head
	 *
	 * @since 0.1
	 */
	public function admin_menu() {
		$hooks[] = add_management_page(
			__( 'Deregister Users', 'bbpress' ),
			__( 'Deregister Users', 'bbpress' ),
			'manage_options',
			'bbp-deregister',
			array( $this, 'display' )
		);

		// Fudge the highlighted subnav item when on a bbPress admin page
		foreach( $hooks as $hook ) {
			add_action( "admin_head-$hook", 'bbp_tools_modify_menu_highlight' );
		}
	}

	/**
	 * Remove the faux menu page
	 *
	 * @since 0.1
	 */
	public function admin_head() {
		remove_submenu_page( 'tools.php', 'bbp-deregister' );
	}

	/**
	 * Output for the page
	 *
	 * @since 0.1
	 */
	public function display() {

		// Get counts
		$total_users   = count( get_users() );
		$total_topics  = array_sum( array_values( (array) wp_count_posts( bbp_get_topic_post_type() ) ) );
		$total_replies = array_sum( array_values( (array) wp_count_posts( bbp_get_reply_post_type() ) ) ); ?>

		<div class="wrap">

			<?php screen_icon( 'tools' ); ?>

			<h2 class="nav-tab-wrapper"><?php bbp_tools_admin_tabs( __( 'Users', 'wpdu' ) ); ?></h2>

			<p><?php printf( __( 'You have %s users, %s topics, and %s replies to potentially deregister.', 'wpdu' ), '<strong>' . $total_users . '</strong>', '<strong>' . $total_topics . '</strong>', '<strong>' . $total_replies . '</strong>' ); ?></p>

			<form class="settings" method="post" action="">
				<table class="form-table">
					<tbody>
						<tr valign="top">
							<th scope="row"><?php _e( 'Deregister Users:', 'wpdu' ) ?></th>
							<td>
								<fieldset>
									<legend class="screen-reader-text"><span><?php _e( 'Do it', 'wudu' ) ?></span></legend>
									<label><input type="checkbox" class="checkbox" name="wp-deregister" id="wp-deregister" value="1" /> <?php _e( 'I am totally ready to do this.', 'wpdu' ); ?></label>
								</fieldset>
							</td>
						</tr>
					</tbody>
				</table>

				<fieldset class="submit">
					<input class="button-primary" type="submit" name="submit" value="<?php esc_attr_e( 'Deregister Users', 'wpdu' ); ?>" />
					<?php wp_nonce_field( 'wp-deregister-users' ); ?>
				</fieldset>
			</form>
		</div>

	<?php
	}

	public function deregister_users() {
		
		// Bail if not a POST action
		if ( 'POST' !== strtoupper( $_SERVER['REQUEST_METHOD'] ) )
			return;

		// Bail if action is not wp-deregister-users
		if ( empty( $_POST['action'] ) || ( 'wp-deregister-users' !== $_POST['action'] ) )
			return;

		$users = get_users();
		
		foreach ( $users as $user_id => $details ) {
			
		}
	}
	
	/** Filters ***************************************************************/

	/**
	 * Add the tab for users to bbPress's tools
	 *
	 * @since 0.1
	 *
	 * @param array $tabs Associative array of tabs and their details
	 * @return array
	 */
	public function tools_tab( $tabs = array() ) {
		$tabs['3'] = array(
			'href' => get_admin_url( '', add_query_arg( array( 'page' => 'bbp-deregister' ), 'tools.php' ) ),
			'name' => __( 'Users', 'wpdu' )
		);

		return $tabs;
	}

	/** Helpers ***************************************************************/

	/**
	 * Are we being deactivated?
	 *
	 * @param type $basename
	 * @return boolean
	 */
	private function is_deactivation() {

		$action = false;
		if ( ! empty( $_REQUEST['action'] ) && ( '-1' != $_REQUEST['action'] ) ) {
			$action = $_REQUEST['action'];
		} elseif ( ! empty( $_REQUEST['action2'] ) && ( '-1' != $_REQUEST['action2'] ) ) {
			$action = $_REQUEST['action2'];
		}

		// Bail if not deactivating
		if ( empty( $action ) || !in_array( $action, array( 'deactivate', 'deactivate-selected' ) ) ) {
			return false;
		}

		// The plugin(s) being deactivated
		if ( $action == 'deactivate' ) {
			$plugins = isset( $_GET['plugin'] ) ? array( $_GET['plugin'] ) : array();
		} else {
			$plugins = isset( $_POST['checked'] ) ? (array) $_POST['checked'] : array();
		}

		return in_array( $this->basename, $plugins );
	}
}
WP_Deregister_Users::instance();
endif; // class_exists check
