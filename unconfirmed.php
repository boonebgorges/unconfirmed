<?php

/*
Plugin Name: Unconfirmed
Plugin URI: http://github.com/boonebgorges/unconfirmed
Description: Allows admins on a WordPress Multisite network to manage unactivated users, by either activating them manually or resending the activation email.
Author: Boone B Gorges
Author URI: http://boonebgorges.com
Licence: GPLv3
Version: 1.0.3
Network: true
*/

class BBG_Unconfirmed {
	/**
	 * The list of users created in the setup_users() method
	 */
	var $users;
	
	/**
	 * PHP 4 constructor
	 *
	 * @package Unconfirmed
	 * @since 1.0
	 */
	function bbg_unconfirmed() {
		$this->__construct();
	}
	
	/**
	 * PHP 5 constructor
	 *
	 * This function sets up a base url to use for URL concatenation throughout the plugin. It
	 * also adds the admin menu with the network_admin_menu hook. At the moment, there appears
	 * to be support for non-multisite installations, but at the moment it is only partial. I 
	 * do not recommend that you bypass the is_multisite() check at the moment.
	 *
	 * @package Unconfirmed
	 * @since 1.0
	 */
	function __construct() {	
		if ( !is_multisite() )
			return;
			
		$this->base_url = add_query_arg( 'page', 'unconfirmed', is_multisite() ? network_admin_url( 'users.php' ) : admin_url( 'users.php' ) );
		
		add_action( is_multisite() ? 'network_admin_menu' : 'admin_menu', array( $this, 'add_admin_panel' ) );
	}

	/**
	 * Adds the admin panel and detects incoming admin actions
	 *
	 * When the admin submits an action like "activate" or "resend activation email", it will
	 * ultimately result in a redirect. In order to minimize the amount of work done in the
	 * interim page load (after the link is clicked but before the redirect happens), I check
	 * for these actions (out of $_GET parameters) before the admin panel is even added to the
	 * Dashboard.
	 *
	 * @package Unconfirmed
	 * @since 1.0
	 *
	 * @uses BBG_Unconfirmed::activate_user() to process manual activations
	 * @uses BBG_Unconfirmed::resend_email() to process manual activations
	 * @uses add_users_page() to add the admin panel underneath user.php
	 */
	function add_admin_panel() {
		// Look for actions first
		if ( isset( $_GET['unconfirmed_action'] ) ) {
			switch ( $_GET['unconfirmed_action'] ) {
				case 'activate' :
					$this->activate_user();					
					break;
				
				case 'resend' :
				default :
					$this->resend_email();
					break;
			}
		}
		
		$page = add_users_page( __( 'Unconfirmed', 'unconfirmed' ), __( 'Unconfirmed', 'unconfirmed' ), 'create_users', 'unconfirmed', array( $this, 'admin_panel_main' ) );
		add_action( "admin_print_styles-$page", array( $this, 'add_admin_styles' ) );
	}
	
	/**
	 * Enqueues the Unconfirmed stylesheet
	 *
	 * @package Unconfirmed
	 * @since 1.0.1
	 *
	 * @uses wp_enqueue_style()
	 */
	function add_admin_styles() {		
		wp_enqueue_style( 'unconfirmed-css', WP_PLUGIN_URL . '/unconfirmed/css/style.css' );
	}
	
	/**
	 * Queries and prepares a list of unactivated registrations for use elsewhere in the plugin
	 *
	 * This function is only called when such a list is required, i.e. on the admin pane
	 * itself. See BBG_Unconfirmed::admin_panel_main().
	 *
	 * @package Unconfirmed
	 * @since 1.0
	 *
	 * @uses apply_filters() Filter 'unconfirmed_paged_query' to alter the per-page query
	 * @uses apply_filters() Filter 'unconfirmed_total_query' to alter the total count query
	 *
	 * @param array $args See $defaults below for documentation
	 */
	function setup_users( $args ) {
		global $wpdb;
		
		/**
		 * Override the $defaults with the following parameters:
		 *   - 'orderby': Which column should determine the sort? Accepts: 'registered', 
		 *     'user_login', 'user_email', 'activation_key'
		 *   - 'order': In conjunction with 'orderby', how should users be sorted? Accepts:
		 *     'desc', 'asc'
		 *   - 'offset': Which user are we starting with? Eg for the third page of 10, use
		 *     31
		 *   - 'number': How many users to return?
		 */
		$defaults = array(
			'orderby' => 'registered',
			'order'   => 'desc',
			'offset'  => 0,
			'number'  => 10
		);
		
		$r = wp_parse_args( $args, $defaults );
		extract( $r );
		
		$sql['select'] 	= "SELECT * FROM $wpdb->signups";
		$sql['where'] 	= "WHERE active = 0";
		
		$sql['orderby'] = "ORDER BY " . $orderby;
		$sql['order']	= strtoupper( $order );
		$sql['limit']	= "LIMIT " . $offset . ", " . $number;
		
		$paged_query = apply_filters( 'unconfirmed_paged_query', join( ' ', $sql ), $sql, $args, $r );

		$users = $wpdb->get_results( $wpdb->prepare( $paged_query ) );
		
		// Now loop through the users and unserialize their metadata for nice display
		// Probably only necessary with BuddyPress
		foreach( $users as $key => $user ) {
			$meta = maybe_unserialize( $user->meta );
			foreach( (array)$meta as $mkey => $mvalue ) {
				$user->$mkey = $mvalue;
			}
			$users[$key] = $user;
		}
		
		$this->users = $users;
		
		// Gotta run a second query to get the overall pagination data
		unset( $sql['limit'] );
		$sql['select'] = "SELECT COUNT(*) FROM $wpdb->signups";
		$total_query = apply_filters( 'unconfirmed_total_query', join( ' ', $sql ), $sql, $args, $r );
		
		$this->total_users = $wpdb->get_var( $wpdb->prepare( $total_query ) );
	}

	/**
	 * Activates a user
	 *
	 * Depending on the result, the admin will be redirected back to the main Unconfirmed panel,
	 * with additional URL params that explain success/failure.
	 *
	 * @package Unconfirmed
	 * @since 1.0
	 *
	 * @uses wpmu_activate_signup() WP's core function for user activation on Multisite
	 */
	function activate_user() {
		// Did you mean to do this? HMMM???
		if ( !check_admin_referer( 'unconfirmed_activate_user' ) )
			return false;
		
		// Get the user's activation key out of the URL params
		if ( !isset( $_GET['unconfirmed_key'] ) ) {
			$redirect_url = add_query_arg( array(
				'unconfirmed_status'	=> 'nokey'
			), $this->base_url );
			
			wp_redirect( $redirect_url );
		}
		
		$key = $_GET['unconfirmed_key'];
		
		$result = wpmu_activate_signup( $key );
		
		if ( is_wp_error( $result ) ) {
			$redirect_url = add_query_arg( array(
				'unconfirmed_status'	=> 'couldnt_activate'
			), $this->base_url );	
		} else {
			$redirect_url = add_query_arg( array(
				'unconfirmed_status'	=> 'activated'
			), $this->base_url );
		}
		
		wp_redirect( $redirect_url );
	}
	
	/**
	 * Resends an activation email
	 *
	 * This sends exactly the same email the registrant originally got, using data pulled from
	 * their registration. In the future I may add a UI for customized emails.
	 *
	 * @package Unconfirmed
	 * @since 1.0
	 *
	 * @uses wpmu_signup_blog_notification() to notify users who signed up with a blog
	 * @uses wpmu_signup_blog_notification() to notify users who signed up without a blog
	 */
	function resend_email() {
		global $wpdb;
		
		// Hubba hubba
		if ( !check_admin_referer( 'unconfirmed_resend_email' ) )
			return false;
			
		// Get the user's activation key out of the URL params
		if ( !isset( $_GET['unconfirmed_key'] ) ) {
			$redirect_url = add_query_arg( array(
				'unconfirmed_status'	=> 'nokey'
			), $this->base_url );
			
			wp_redirect( $redirect_url );
		}
		
		$key = $_GET['unconfirmed_key'];
		
		$user = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $wpdb->signups WHERE activation_key = %s", $key ) );
		
		if ( !$user ) {
			$redirect_url = add_query_arg( array(
				'unconfirmed_status'	=> 'no_user'
			), $this->base_url );
			
			wp_redirect( $redirect_url );
		}
		
		// We use a different email function depending on whether they registered with blog
		if ( !empty( $user->domain ) ) {
			wpmu_signup_blog_notification( $user->domain, $user->path, $user->title, $user->user_login, $user->user_email, $user->activation_key, maybe_unserialize( $user->meta ) );
		} else {
			wpmu_signup_user_notification( $user->user_login, $user->user_email, $user->activation_key, maybe_unserialize( $user->meta ) );
		}
		
		// I can't do a true/false check on whether the email was sent because of the
		// crappy way that WPMU and BP work together to send these messages
		// See bp_core_activation_signup_user_notification()
		$redirect_url = add_query_arg( array(
			'unconfirmed_status'	=> 'resent'
		), $this->base_url );		
		wp_redirect( $redirect_url );		
	}
	
	/**
	 * Picks the error/success messages out of the URL and matches them with messages to be
	 * displayed in a confirmation box.
	 *
	 * @package Unconfirmed
	 * @since 1.0
	 *
	 * @uses BBG_Unconfirmed::message_content() to actually print the message to the screen
	 */
	function render_messages() {
		if ( isset( $_GET['unconfirmed_status'] ) ) {
			switch ( $_GET['unconfirmed_status'] ) {
				case 'nokey' :
					$status  = 'error';
					$message = __( 'No activation key was provided.', 'unconfirmed' ); 
					break;
				
				case 'couldnt_activate' :
					$status	 = 'error';
					$message = __( 'The user could not be activated. Please try again.', 'unconfirmed' );
					break;
				
				case 'activated' :
					$status	 = 'updated';
					$message = __( 'User activated!', 'unconfirmed' );
					break;
				
				case 'no_user' :
					$status  = 'error';
					$message = __( 'No user could be found with that activation key.', 'unconfirmed' );
					break;
				
				case 'resent' :
					$status  = 'updated';
					$message = __( 'Activation email resent!', 'unconfirmed' );
					break;
				
				case 'unsent' :
					$status  = 'error';
					$message = __( 'Email could not be sent.', 'unconfirmed' );
					break;
					
				default :
					break;
			}
		}
		
		if ( isset( $status ) ) {
			$this->status  = $status;
			$this->message = $message;
			$this->message_content();
		}
	}
	
	/**
	 * Echoes the error message to the screen.
	 *
	 * Uses the standard WP admin nag markup.
	 * 
	 * Not sure why I put this in a separate method. I guess, so you can override it easily?
	 *
	 * @package Unconfirmed
	 * @since 1.0
	 */
	function message_content() {
		?>
		
		<div id="message" class="<?php echo $this->status ?>">
			<p><?php echo $this->message ?></p>
		</div>
		
		<?php
	}
	
	/**
	 * Renders the main Unconfirmed Dashboard panel
	 *
	 * @package Unconfirmed
	 * @since 1.0
	 *
	 * @uses BBG_CPT_Pag aka Boone's Pagination
	 * @uses BBG_CPT_Sort aka Boone's Sortable Columns
	 * @uses BBG_Unconfirmed::setup_users() to get a list of unactivated users
	 */
	function admin_panel_main() {
		
		if ( !class_exists( 'BBG_CPT_Pag' ) )
			require_once( dirname( __FILE__ ) . '/lib/bbg-cpt-pag.php' );
		$pagination = new BBG_CPT_Pag;
		
		// Load the sortable helper
		if ( !class_exists( 'BBG_CPT_Sort' ) )
			require_once( dirname( __FILE__ ) . '/lib/bbg-cpt-sort.php' );
			
		$cols = array(
			array(
				'name'		=> 'user_login',
				'title'		=> __( 'User Login', 'unconfirmed' ),
				'css_class'	=> 'login'
			),
			array(
				'name'		=> 'user_email',
				'title'		=> __( 'Email Address', 'unconfirmed' ),
				'css_class'	=> 'email'
			),
			array(
				'name'		=> 'registered',
				'title'		=> 'Registered',
				'css_class'	=> 'registered',
				'default_order'	=> 'desc',
				'is_default'	=> true
			),
			array(
				'name'		=> 'activation_key',
				'title'		=> __( 'Activation Key', 'unconfirmed' ),
				'css_class'	=> 'activation-key'
			),
		);
		
		$sortable = new BBG_CPT_Sort( $cols );
		
		$offset = $pagination->get_per_page * ( $pagination->get_paged - 1 );
		
		$args = array(
			'orderby'	=> $sortable->get_orderby,
			'order'		=> $sortable->get_order,
			'number'	=> $pagination->get_per_page,
			'offset'	=> $offset,
		);
		
		$this->setup_users( $args );
		
		// Setting this up a certain way to make pagination/sorting easier
		$query = new stdClass;
		$query->users = $this->users;
		
		// In order for Boone's Pagination to work, this stuff must be set manually
		$query->found_posts = $this->total_users;
		$query->max_num_pages = ceil( $query->found_posts / $pagination->get_per_page );
		
		// Complete the pagination setup
		$pagination->setup_query( $query );
		
		?>
		<div class="wrap">
		
		<h2><?php _e( 'Unconfirmed', 'unconfirmed' ) ?></h2>
		
		<?php $this->render_messages() ?>
		
		<form action="" method="get">
		
		<?php if ( !empty( $this->users ) ) : ?>
			<div class="unconfirmed-pagination">
				<div class="currently-viewing">
					<?php $pagination->currently_viewing_text() ?>
				</div>
				
				<div class="pag-links">
					<?php $pagination->paginate_links() ?>
				</div>
			</div>
			
			<table class="wp-list-table widefat ia-invite-list">
			
			<thead>
				<tr>
					<th scope="col" id="cb" class="check-column">
						<input type="checkbox" />
					</th>
					
					<?php if ( $sortable->have_columns() ) : while ( $sortable->have_columns() ) : $sortable->the_column() ?>
						<?php $sortable->the_column_th() ?>
					<?php endwhile; endif ?>
					
				</tr>
			</thead>
	
			<tbody>
				<?php foreach ( $this->users as $user ) : ?>
				<tr>
					<th scope="row" class="check-column">
						<input type="checkbox" />
					</th>
					
					<td class="login">
						<?php echo $user->user_login ?>
						
						<div class="row-actions">
							<span class="edit"><a class="confirm" href="<?php echo wp_nonce_url( add_query_arg( array( 'unconfirmed_action' => 'resend', 'unconfirmed_key' => $user->activation_key ), $this->base_url ), 'unconfirmed_resend_email' ) ?>"><?php _e( 'Resend Activation Email', 'unconfirmed' ) ?></a></span>
							&nbsp;&nbsp;
							<span class="delete"><a class="confirm" href="<?php echo wp_nonce_url( add_query_arg( array( 'unconfirmed_action' => 'activate', 'unconfirmed_key' => $user->activation_key ), $this->base_url ), 'unconfirmed_activate_user' ) ?>"><?php _e( 'Activate', 'unconfirmed' ) ?></a></span>
							
						</div>
					</td>
					
					<td class="email">
						<?php echo $user->user_email ?>
					</td>
					
					<td class="registered">
						<?php echo $user->registered ?>
					</td>
					
					<td class="activation_key">
						<?php echo $user->activation_key ?>						
					</td>
					
				</tr>
				<?php endforeach ?>
			</tbody>
			</table>	
			
			<div class="unconfirmed-pagination">
				<div class="currently-viewing">
					<?php $pagination->currently_viewing_text() ?>
				</div>
				
				<div class="pag-links">
					<?php $pagination->paginate_links() ?>
				</div>
			</div>
			
		<?php else : ?>
		
			<p><?php _e( 'No unactivated members were found.', 'unconfirmed' ) ?></p>
		
		<?php endif ?>
			
		</form>
		
		</div>
		<?php
	}
}

$bbg_unconfirmed = new BBG_Unconfirmed;


?>
