<?php

/*
Plugin Name: Unconfirmed
Description: 
Author: Boone Gorges
Network: true
*/

class BBG_Unconfirmed {
	var $users;
	
	function bbg_unconfirmed() {
		$this->__construct();
	}
	
	function __construct() {
		add_action( is_multisite() ? 'network_admin_menu' : 'admin_menu', array( $this, 'add_admin_panel' ) );
	}

	function add_admin_panel() {
		add_users_page( __( 'Unconfirmed', 'unconfirmed' ), __( 'Unconfirmed', 'unconfirmed' ), 'create_users', 'unconfirmed', array( $this, 'admin_panel_main' ) );
	}
	
	function setup_users( $args ) {
		global $wpdb;
		
		$sql['select'] 	= "SELECT * FROM $wpdb->signups";
		$sql['where'] 	= "WHERE active = 0";
		
		$sql['orderby'] = "ORDER BY " . $args['orderby'];
		$sql['order']	= strtoupper( $args['order'] );
		$sql['limit']	= "LIMIT " . $args['offset'] . ", " . $args['number'];
		
		$sql = apply_filters( 'unconfirmed_query', join( ' ', $sql ), $sql, $args );
		var_dump( $sql );
		$users = $wpdb->get_results( $sql );
		
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
		
		// In order for pagination to work, this must be set manually
		$query->found_posts = count( $query->users );
		$query->max_num_pages = ceil( $query->found_posts / $pagination->get_per_page );
		
//		var_dump( $this->query ); die();
		// Complete the pagination setup
		$pagination->setup_query( $query );
		//var_dump( $this->users );
		
		$base_url = add_query_arg( 'page', 'unconfirmed', is_multisite() ? network_admin_url( 'users.php' ) : admin_url( 'users.php' ) );
		
		?>
		<div class="wrap">
		<h2><?php _e( 'Unconfirmed', 'unconfirmed' ) ?></h2>
		
		<?php if ( isset( $_GET['mm-all-done'] ) ) : ?>
			<div class="update-nag">All done!</div>
		<?php endif ?>
		
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
							<span class="edit"><a class="confirm" href="<?php echo wp_nonce_url( add_query_arg( array( 'resend' => $user->activation_key ), $base_url ), 'unconfirmed_resend_email' ) ?>"><?php _e( 'Resend Activation Email', 'unconfirmed' ) ?></a></span>
							&nbsp;&nbsp;
							<span class="delete"><a class="confirm" href="<?php echo wp_nonce_url( add_query_arg( array( 'activate' => $user->activation_key ), $base_url ), 'unconfirmed_activate_user' ) ?>"><?php _e( 'Activate', 'unconfirmed' ) ?></a></span>
							
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
			
		<?php endif ?>
		
		
		
		
		
		</form>
		
		</div>
		<?php
	}


}

$bbg_unconfirmed = new BBG_Unconfirmed;


?>