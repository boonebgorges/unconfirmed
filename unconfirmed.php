<?php

/*
Plugin Name: Unconfirmed
Description: Allows admins on a WordPress Multisite network to manage unactivated users, by either activating them manually or resending the activation email.
Author: Boone Gorges
Network: true
*/

class BBG_Unconfirmed {
	var $users;
	
	function bbg_unconfirmed() {
		$this->__construct();
	}
	
	function __construct() {	
		$this->base_url = add_query_arg( 'page', 'unconfirmed', is_multisite() ? network_admin_url( 'users.php' ) : admin_url( 'users.php' ) );
		
		add_action( is_multisite() ? 'network_admin_menu' : 'admin_menu', array( $this, 'add_admin_panel' ) );
	}

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
		
		add_users_page( __( 'Unconfirmed', 'unconfirmed' ), __( 'Unconfirmed', 'unconfirmed' ), 'create_users', 'unconfirmed', array( $this, 'admin_panel_main' ) );
	}
	
	function setup_users( $args ) {
		global $wpdb;
		
		$sql['select'] 	= "SELECT * FROM $wpdb->signups";
		$sql['where'] 	= "WHERE active = 0";
		
		$sql['orderby'] = "ORDER BY " . $args['orderby'];
		$sql['order']	= strtoupper( $args['order'] );
		$sql['limit']	= "LIMIT " . $args['offset'] . ", " . $args['number'];
		
		$paged_query = apply_filters( 'unconfirmed_paged_query', join( ' ', $sql ), $sql, $args );

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
		$total_query = apply_filters( 'unconfirmed_total_query', join( ' ', $sql ), $sql, $args );
		
		$this->total_users = $wpdb->get_var( $wpdb->prepare( $total_query ) );
	}

	function activate_user() {
		// Did you mean to do this? HMMM???
		if ( !check_admin_referer( 'unconfirmed_activate_user' ) )
			return false;
		
		// Get the user's activation key out of the URL params
		if ( !isset( $_GET['unconfirmed_key'] ) ) {
			$redirect_url = add_query_arg( array(
				'page'			=> 'unconfirmed',
				'unconfirmed_status'	=> 'nokey'
			), $this->base_url );
			
			wp_redirect( $redirect_url );
		}
		
		$key = $_GET['unconfirmed_key'];
		
		$result = wpmu_activate_signup( $key );
		
		if ( is_wp_error( $result ) ) {
			$redirect_url = add_query_arg( array(
				'page'			=> 'unconfirmed',
				'unconfirmed_status'	=> 'couldnt_activate'
			), $this->base_url );	
		} else {
			$redirect_url = add_query_arg( array(
				'page'			=> 'unconfirmed',
				'unconfirmed_status'	=> 'activated'
			), $this->base_url );
		}
		
		wp_redirect( $redirect_url );
	}
	
	function resend_email() {
		global $wpdb;
		
		// Hubba hubba
		if ( !check_admin_referer( 'unconfirmed_resend_email' ) )
			return false;
			
		// Get the user's activation key out of the URL params
		if ( !isset( $_GET['unconfirmed_key'] ) ) {
			$redirect_url = add_query_arg( array(
				'page'			=> 'unconfirmed',
				'unconfirmed_status'	=> 'nokey'
			), $this->base_url );
			
			wp_redirect( $redirect_url );
		}
		
		$key = $_GET['unconfirmed_key'];
		
		$user = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $wpdb->signups WHERE activation_key = %s", $key ) );
		
		if ( !$user ) {
			$redirect_url = add_query_arg( array(
				'page'			=> 'unconfirmed',
				'unconfirmed_status'	=> 'no_user'
			), $this->base_url );
			
			wp_redirect( $redirect_url );
		}
		
		// We use a different email function depending on whether they registered with blog
		if ( !empty( $user->domain ) ) {
			$result = wpmu_signup_blog_notification( $user->domain, $user->path, $user->title, $user->user_login, $user->user_email, $user->activation_key, maybe_unserialize( $user->meta ) );
		} else {
			$result = wpmu_signup_user_notification( $user->user_login, $user->user_email, $user->activation_key, maybe_unserialize( $user->meta ) );
		}
		
		if ( $result ) {
			$redirect_url = add_query_arg( array(
				'page'			=> 'unconfirmed',
				'unconfirmed_status'	=> 'resent'
			), $this->base_url );
		} else {
			$redirect_url = add_query_arg( array(
				'page'			=> 'unconfirmed',
				'unconfirmed_status'	=> 'unsent'
			), $this->base_url );
		}
		
		wp_redirect( $redirect_url );		
	}
	
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
	
	function message_content() {
		?>
		
		<div id="message" class="<?php echo $this->status ?>">
			<p><?php echo $this->message ?></p>
		</div>
		
		<?php
	}
	
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
			<div class="ia-admin-pagination">
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
			
			<div class="ia-admin-pagination">
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