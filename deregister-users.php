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
 * Version:     1.0
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

		// Should be checked
		$roles = array_intersect( array_keys( bbp_get_dynamic_roles() ), $this->get_post_roles() );

		// Get counts
		$total_users   = count( get_users() );
		$total_topics  = array_sum( array_values( (array) wp_count_posts( bbp_get_topic_post_type() ) ) );
		$total_replies = array_sum( array_values( (array) wp_count_posts( bbp_get_reply_post_type() ) ) ); ?>

		<div class="wrap">

			<?php screen_icon( 'tools' ); ?>

			<h2 class="nav-tab-wrapper"><?php bbp_tools_admin_tabs( __( 'Users', 'wpdu' ) ); ?></h2>

			<?php if ( ! empty( $_GET['updated'] ) ) : ?>
				<div id="message" class="updated"><p><?php _e( 'All done!', 'wpdu' ); ?></p></div>
			<?php endif; ?>

			<p><?php printf( __( 'You have %s users, %s topics, and %s replies to potentially deregister.', 'wpdu' ), '<strong>' . $total_users . '</strong>', '<strong>' . $total_topics . '</strong>', '<strong>' . $total_replies . '</strong>' ); ?></p>

			<form class="settings" method="post" action="">
				<table class="form-table">
					<tbody>
						<tr valign="top">
							<th scope="row"><?php _e( 'Skip these Roles:', 'wpdu' ) ?></th>
							<td>
								<fieldset>
									<legend class="screen-reader-text"><span><?php _e( 'Keymasters', 'wudu' ) ?></span></legend>

									<?php foreach ( bbp_get_dynamic_roles() as $role => $details ) : ?>

									<label><input type="checkbox" class="checkbox" name="wp-role[]" id="wp-role-<?php echo esc_attr( $role ); ?>" value="<?php echo esc_attr( $role ); ?>" <?php checked( in_array( $role, array_values( $roles ) ) ); ?> /> <?php echo $details['name']; ?></label><br />

									<?php endforeach; ?>

								</fieldset>
							</td>
						</tr>						

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

	/**
	 * Do da dang ding
	 *
	 * @since 0.1
	 * @return If it's not the right time or place for this to happen
	 */
	public function deregister_users() {

		// Bail if not a POST action
		if ( 'POST' !== strtoupper( $_SERVER['REQUEST_METHOD'] ) )
			return;

		// Bail if action is not wp-deregister-users
		if ( empty( $_POST['wp-deregister'] ) || ( '1' !== $_POST['wp-deregister'] ) )
			return;

		check_admin_referer( 'wp-deregister-users' );

		// Get all of the users on this site
		$users    = get_users();

		// Loop through all of the users
		foreach ( $users as $details ) {

			// Skip certain roles
			if ( in_array( bbp_get_user_role( $details->ID ), $this->get_post_roles() ) )
				continue;

			/** Topics ********************************************************/

			// Get the topics
			$topics = new WP_Query( array(
				'post_type'      => bbp_get_topic_post_type(),
				'post_status'    => 'all',
				'author'         => $details->ID,
				'posts_per_page' => -1
			) );

			// Loop through topics
			foreach ( (array) $topics->posts as $topic ) {
				$this->make_deregistered( $topic, $details );
			}

			/** Replies *******************************************************/

			// Get the topics
			$replies = new WP_Query( array(
				'post_type'      => bbp_get_reply_post_type(),
				'post_status'    => 'all',
				'author'         => $details->ID,
				'posts_per_page' => -1
			) );

			// Loop through topics
			foreach ( (array) $replies->posts as $reply ) {
				$this->make_deregistered( $reply, $details );
			}
		}

		// All done, redirect
		wp_safe_redirect( add_query_arg( array( 'updated' => '1', 'page' => 'bbp-deregister' ), admin_url( 'tools.php' ) ) );
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
	 * Commence da jigglin'
	 *
	 * @since 0.1
	 * @param WP_Post $post
	 * @param WP_User $user
	 */
	private function make_deregistered( $post, $user ) {
		global $user_ID;

		// Store the old user ID, just in case we want to revert
		update_post_meta( $post->ID, '_bbp_old_user_id',       $user->ID           );

		// Add the post meta
		update_post_meta( $post->ID, '_bbp_anonymous_name',    $user->display_name );
		update_post_meta( $post->ID, '_bbp_anonymous_email',   $user->user_email   );
		update_post_meta( $post->ID, '_bbp_anonymous_website', $user->user_url     );

		// Remove the users role
		bbp_get_user_role( $user->ID );

		// Set the global as 0, so it can get set as 0 in wp_insert_post()
		// Kind of a hack, but works fine enough for now
		$old_user_id = $user_ID;
		$user_ID     = 0;

		// Remove the post_author
		wp_update_post( array( 'ID' => $post->ID, 'post_author' => 0 ) );

		// Put back the original user ID - dehack the hack
		$user_ID = $old_user_id;
	}

	/**
	 * Roles posted to deregister
	 *
	 * @since 0.1
	 * @return array
	 */
	private static function get_post_roles() {
		return !empty( $_POST['wp-role'] ) ? (array) $_POST['wp-role'] : array( bbp_get_moderator_role(), bbp_get_keymaster_role() );
	}

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
