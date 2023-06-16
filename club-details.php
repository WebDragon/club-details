<?php 
/*  Plugin Name: Club Details custom plugin for EFMLS
	Description: Custom post type and editing for club membership information (location, members, dues, etc), officers, and insurance info with additional shortcodes for use in Elementor templating
	Author: Scott R. Godin for MAD House Graphics
	Author URI: https://madhousegraphics.com
	Version: 0.24
	License: GPL3
 */

// NOTE	import/export/bulk-edit logic can be handled by https://www.wpallimport.com/export-advanced-custom-fields/
//		and does not need to be integrated into the plugin
// NOTE setting revision limits has been relegated to WP Revisions Control plugin, as it does a more comprehensive 
//		job of it, and also allows for purging excess revisions
//
// TODO	add check to make sure ACF Pro is enabled 

date_default_timezone_set("UTC");
setlocale( LC_MONETARY, 'en_US.UTF-8');

define( 'CLUB_ROOT',	plugins_url( '', __FILE__ ) );
define( 'CLUB_ASSETS',	plugin_dir_path( __FILE__ ) . 'assets/' );

//define( 'CLUB_IMAGES',	CLUB_ROOT . '/assets/images/' );
define( 'CLUB_STYLES',	CLUB_ROOT . '/assets/css/' );
define( 'CLUB_SCRIPTS',	CLUB_ROOT . '/assets/js/' );

//define( 'CLUB_INCL',	CLUB_ASSETS . 'inc/' );

// {{{ Register Custom Post Type for EFMLS Club Details 
function register_efmls_club() {

	$clublabels = array(
		'add_new'				=> __("Add New Club", 'efmls'),
		'add_new_item'			=> __("Add Mineral Club Details", 'efmls'),
		'all_items'				=> __("All Clubs", 'efmls'),
		'archives'				=> __("Club Archives", 'efmls'),
		'attributes'			=> __("Club attributes", 'efmls'),
		'edit_item'				=> __("Edit Club Details", 'efmls'),
		'featured_image'		=> __("Featured Image for this Club", 'efmls'),
		'filter_items_list'		=> __("Filter Club List", 'efmls'),
		'items_list'			=> __("Club list", 'efmls'),
		'items_list_navigation'	=> __("Club list navigation", 'efmls'),
		'menu_name'				=> __("Mineral Clubs", 'efmls'),
		'name'					=> __("Club Details", 'efmls'),
		'new_item'				=> __("New Club", 'efmls'),
		'not_found'				=> __("No Clubs found", 'efmls'),
		'not_found_in_trash'	=> __("No Clubs found in Trash", 'efmls'),
		'remove_featured_image'	=> __("Remove Featured Club image", 'efmls'),
		'search_items'			=> __("Search Clubs", 'efmls'),
		'set_featured_image'	=> __("Set Featured Club image", 'efmls'),
		'singular_name'			=> __("Club Details", 'efmls'),
		'use_featured_image'	=> __("Use as Featured Image for this Club", 'efmls'),
		'view_item'				=> __("View Club", 'efmls'),
		'view_items'			=> __("View Clubs", 'efmls'),
	);
	$clubsupports =  array(
		'author',
		'custom-fields',
		'editor',
		'page-attributes',
		'revisions',
		'thumbnail',
		'title',
	);
	$clubargs = array(
		'can_export'			=> true,
		'capability_type'		=> "post",
		'description'			=> "EFMLS Societies/Clubs featured on the website",
		'has_archive'			=> true,
		'hierarchical'			=> false,
		'labels'				=> $clublabels,
		'map_meta_cap'			=> true,
		'menu_icon'				=> "dashicons-hammer",
		'menu_position'			=> 10,
		'public'				=> true,
		'publicly_queryable'	=> true,
		'query_var'				=> true,
		'rest_base'				=> "",
		'rewrite'				=> array('slug' => 'clubdetails', 'with_front' => false),
		'show_in_menu'			=> true,
		'show_in_nav_menus'		=> true,
		'show_in_rest'			=> false,
		'show_ui'				=> true,
		'supports'				=> $clubsupports,
		'taxonomies'			=> array('post_tag'),
		'register_meta_box_cb'	=> 'rate_calculation_meta_box',
	);
	register_post_type('clubdetails', $clubargs );

    $clubregions = array(
        'labels' => array(
			'name' => 'Club Regions',
			'singular_item' => 'Club Region',
			'add_new_item' => 'Add New Club Region',
			'edit_item' => 'Edit Club Region',
			'menu_name' => 'Club Regions',
        ),
        'hierarchical' => true,
        'public' => true,
        'show_ui' => true,
        'show_admin_column' => true,
        'show_in_nav_menus' => true,
        'show_in_rest' => true,
        'show_tagcloud' => false,
    );
    register_taxonomy( 'clubregions', array( 'clubdetails' ), $clubregions );

}
add_action('init', 'register_efmls_club'); // }}}

// {{{ Flushing rewrite rules on plugin activation/deactivation for better working permalink structure
function club_activation_deactivation() {

	register_efmls_club();
	flush_rewrite_rules();

} 
register_activation_hook( __FILE__, 'club_activation_deactivation' ); // }}}

// {{{ Register custom Settings page for dues and insurance multipliers with ACF Pro
if ( function_exists('acf_add_options_page') ) {

	$settings_icon = efmls_inline_dashicon('admin-settings');

	acf_add_options_page( array( 
		'page_title'	=> "Dues and Insurance Rates",
		'menu_title'	=> "Dues and Insurance Rates {$settings_icon}", // Fine, icon shoehorned in because the below icon does not display
		'menu_slug'		=> 'rate-settings',
		'parent_slug'	=> 'edit.php?post_type=clubdetails',
		'capability'	=> 'manage_options',
		'icon_url'		=> 'dashicons-admin-settings', // why does the admin submenu not display this icon?
		'position'		=> false,
	));

} // }}}

// {{{ Add Meta Box for display of Dues & Insurance calculations
// included by callback in custom post type creation args above
function rate_calculation_meta_box() {

	add_meta_box(
		'rate-calculation',
		__("Dues & Insurance Rate Calculation", 'efmls'),
		'rate_calculation_callback',
		'clubdetails',
		'side', 'high', null
	);

}

// Meta Box Content
function rate_calculation_callback( $post ) {

	if ( function_exists('get_field') ) {

		wp_nonce_field('rate_calculation_nonce','rate_calculation_nonce');

		$dues_rate = get_field('dues_rate', 'options');
		$insurance_rate = get_field('insurance_rate', 'options');
		$year_valid = get_field('valid_for_year', 'options');
		$membership = get_field('membership');

		// silence noisy php notices
		$membership['adult_members']  = isset($membership['adult_members'])  ? $membership['adult_members']  : 0;
		$membership['junior_members'] = isset($membership['junior_members']) ? $membership['junior_members'] : 0;
		$total_members = $membership['adult_members'] + $membership['junior_members'];

		$fmt = new NumberFormatter("en_US", NumberFormatter::CURRENCY);
		$total_dues = $fmt->formatCurrency( $membership['adult_members'] * $dues_rate, "USD" );
		$total_insurance = $fmt->formatCurrency( $total_members * $insurance_rate, "USD" );

		// TODO : Optional - populate the data values live as numbers are entered in the respective fields for the custom post type member counts
		echo <<<HTML

		<p><b>Total Members</b>: <span id="totalmembers">{$total_members}</span><br>
			<small><i>( Adults: {$membership['adult_members']}, Juniors: {$membership['junior_members']} )</i></small></p>
		<p><b>Dues</b>: <span id="totaldues">{$total_dues}</span><br>
			<small><i>(rate: {$dues_rate} *Adults only)</i></small></p>
		<p><b>Insurance</b>: <span id="totalinsurance">{$total_insurance}</span><br>
			<small><i>(rate: {$insurance_rate})</i></small></p>
		<p><em>Dues and Insurance rates valid for calendar year:</em> <b>{$year_valid}</b></p>
HTML;
	}
}
// }}}

// {{{ Add Admin Notice for when member counts total zero
function admin_notice_members_warning () {
	$screen = get_current_screen();
	if ( $screen && 'edit' === $screen->parent_base && 'clubdetails' === $screen->id && function_exists('get_field') ) {
		$membership = get_field('membership');
		$total_members = (isset($membership['adult_members']) && isset($membership['junior_members']) ) ? ($membership['adult_members'] + $membership['junior_members']) : 0;
		if ( $total_members < 1 ) {

			echo <<<HTML

<div class="notice notice-warning">
	<h3><span class="wp-menu-image dashicons-before dashicons-warning"></span> Warning: Unable to calculate dues and insurance rates.</h3>
	<p>Please remember to add member counts for junior and senior club members.</p>
</div>
HTML;
		}
	}
}

add_action('admin_notices', 'admin_notice_members_warning');

// }}}

// {{{ Add Admin Notice for when user needs help with their form

function admin_notice_member_assistance () {
	$screen = get_current_screen();
	if ( $screen && 'edit' === $screen->parent_base && 'clubdetails' === $screen->id ) {
		echo <<<HTML

<div class="notice notice-info">
	<h3><span class="wp-menu-image dashicons-before dashicons-lightbulb"></span> Need Assistance? </h3>
	<p>Please Contact <a href="mailto:efmls.clubmanager@mad4.us"><span class="wp-menu-image dashicons-before dashicons-email-alt"></span> The EFMLS Clubs Manager</a> if you have any questions regarding editing your club information.</p>
</div>
HTML;
	}
}

add_action('admin_notices', 'admin_notice_member_assistance');

// }}}

// {{{ add shortcode to fancy up output of public contact information better than can be accomplished
// with the simple methods provided by dynamic tags in Elementor for ACF fields
function efmls_public_contact_shortcode( $atts, $content=null ) {
	extract( shortcode_atts( array(
		'email' => true,
		'phone' => true,
	), $atts), EXTR_PREFIX_ALL, 'efmls');
	$efmls_email = filter_var($efmls_email, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
	$efmls_phone = filter_var($efmls_email, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
	if ( !function_exists('get_field') ) { return "<h3> `get_field` function not available. Please make sure ACF is installed and enabled</h3>"; }
	$efmls_general = get_field('general_information');
	$efmls_public = array(
		'name' => $efmls_general['public_contact_name'],
		'email' => $efmls_general['public_contact_email'],
		'phone' => $efmls_general['public_contact_phone'],
	);
	$shortcode_return = '';
	if (
		(strlen($efmls_public['name']) <=1)
	 && (strlen($efmls_public['email']) <=1)
	 && (strlen($efmls_public['phone']) <=1) ) {

	 $shortcode_return .= <<<HTML
<em>No public contact information has been provided at this time</em>
HTML;

	}

	if ($efmls_email && (strlen($efmls_public['email']) > 1) && (strlen($efmls_public['name']) > 1) ) {

		$shortcode_return .= <<<HTML
<i class="fas fa-envelope"></i> <a href="mailto:{$efmls_public['email']}">{$efmls_public['name']}</a><br>
HTML;

	} elseif (strlen($efmls_public['name']) > 1) {

		$shortcode_return .= <<<HTML
<i class="fa fa-user"></i> {$efmls_public['name']}<br>
HTML;

	}

	if ( strlen( $efmls_public['phone'] ) > 1 ) {

		$shortcode_return .= <<<HTML
<i class="fas fa-phone"></i> <a href="tel:+1{$efmls_public['phone']}">{$efmls_public['phone']}</a><br>
HTML;

	}

	return <<<HTML

<div>
	{$shortcode_return}
</div>

HTML;
}
add_shortcode('club_contact_public','efmls_public_contact_shortcode');
// }}}

// {{{ add shortcode to generate fancy Directory for publication annually
//
function efmls_generate_directory( $atts, $content=null ) {
	// extract( shortcode_atts( array( '' => true, ), $atts), EXTR_PREFIX_ALL, 'efmls');
	if ( !class_exists('ACF') or  !function_exists('get_field') ) { return "<h3>`get_field` function not available. Please make sure ACF is installed and enabled</h3>"; }

	if ( !function_exists("efmls_tabledata") ) { /* {{{ */
		function efmls_tabledata( $title, $name, $phone, $email ) {

			$name = ( $name === null || $name === '' || preg_match("/VACANT|NONE|TBA/", $name) ) ? "OPEN" : $name;

			if ( $phone === null || $phone === '' ) {
				$phone = "N/A";
			}
			elseif ( preg_match( "/555-1212/", $phone) ) {
				$phone = "<span class='text-warning'>not supplied</span>";
			}
			else {
				$phone = <<<HTML
<a href="tel:+1{$phone}">{$phone}</a>
HTML;
			}

			if ( $email === null || $email === '' ) {
				$email = "N/A";
			}
			elseif ( preg_match( "/(no-?reply|nocontactemail)\@efmls\.org/", $email) ) {
				$email = "<span class='text-warning'>not supplied</span>";
			}
			else {
				$email = <<<HTML
<a href="mailto:{$email}">{$email}</a>
HTML;
			}

			return <<<HTML

						<tr>
							<td><b>$title</b></td>
							<td><span class="officer">$name</span></td>
							<td>$phone</td>
							<td>$email</td>
						</tr>
HTML;
		}
	} /* }}} */
	$shortcode_return = '';
	$ordered_data = array();

	$q = new WP_Query( array(
		'post_type'			=> 'clubdetails',
		'posts_per_page'	=> -1,
		'post_status'		=> 'publish',
		'no_found_rows'		=> true,
		// this doesn't work because sub-fields are serialized and cannot be queried in this manner:
		// 'meta_query'		=> [
		// 	'relation' => 'AND',
		// 	'state_clause' => [ 'key' => 'state', 'compare' => 'EXISTS' ],
		// 	'clubname_clause' => [ 'key' => 'club_name', 'compare' => 'EXISTS' ],
		// ],
		// 'orderby'			=> [
		// 	'state_clause' => 'ASC',
		// 	'clubname_clause' => 'ASC',
		// ],
		// this works:
		'orderby'			=> 'title',
		'order' => 'ASC',
	));

	while ( $q->have_posts() ) {
		$q->the_post();

		$efmls_general = get_field('general_information');
		$terms = wp_get_post_terms( get_the_ID(), 'clubregions', [ 'fields'=>'all', 'object_ids'=>$efmls_general['region'] ] );
		$region = $terms[0]->name; $regiondesc = $terms[0]->description;

		$efmls_member = get_field('membership');
		$efmls_officer = get_field('club_officers');

		$img = get_the_post_thumbnail(get_the_ID(), 'medium', ['class' => 'img-responsive']);
		$fallbackimg = <<<HTML
			<figure><img width="300" height="300" src=/wp-content/uploads/2022/05/EFMLS-Member-Logo.png" class="img-responsive wp-post-image" title="EFMLS Member Club Logo placeholder image" alt="EFMLS Member Club Logo placeholder image" loading="lazy" srcset="/wp-content/uploads/2022/05/EFMLS-Member-Logo.png 600w, /wp-content/uploads/2022/05/EFMLS-Member-Logo-300x300.png 300w, /wp-content/uploads/2022/05/EFMLS-Member-Logo-150x150.png 150w" sizes="(max-width: 600px) 100vw, 600px"></figure>
HTML;
		$img = ($img === null || $img === '') ? $fallbackimg : $img;
		$ordered_data[$region]['desc'] = $regiondesc;
		if (!isset( $ordered_data[$region]['clubs'] )) { $ordered_data[$region]['clubs'] = array(); } // fix php 8.0 warnings
		if (!isset( $ordered_data[$region]['clubs'][ $efmls_general['club_name'] ] )) { $ordered_data[$region]['clubs'][ $efmls_general['club_name'] ] = ""; } // fix php 8.0 warnings
		$ordered_data[$region]['clubs'][ $efmls_general['club_name'] ] .= <<<HTML

	<div class="club_directory_entry">
		<div class="club_directory_info">
			<div class="club_directory_logo">{$img}</div>
			<h3>{$efmls_general['club_name']} &ndash; <i class="fad fa-map-marker-alt fa-xs"></i> <i>{$region}</i></h3>

			<p><b>Club Website</b>: <a href="{$efmls_general['website_url']}">{$efmls_general['website_url']}</a><br>
			<b>Facebook</b>: <a href="{$efmls_general['facebook_url']}">{$efmls_general['facebook_url']}</a><br>
			<b>Meetings</b>: {$efmls_general['date_or_frequency']}, {$efmls_general['time']} - {$efmls_general['meeting_location']}<br>
			<b>Adults</b>: {$efmls_member['adult_members']}, <b>Juniors</b>: {$efmls_member['junior_members']} &bull; <b>Club Organized</b>: {$efmls_general['club_organized']} &bull; <b>Joined EFMLS</b>: {$efmls_general['joined_efmls']}
			</p>
			<table class="officers">
				<caption>
					<h5>Officers</h5> &ndash;
					<b>Elected</b>: {$efmls_member['officers_elected_month']} &bull; <b>Installed</b>: {$efmls_member['officers_installed_month']} &bull; <b>Take Office</b>: {$efmls_member['officers_takeoffice_month']}
				</caption>
				<thead>
					<tr>
						<th>Title</th>
						<th>Name</th>
						<th>Phone</th>
						<th>Email</th>
					</tr>
				</thead>
				<tbody>
HTML;

		$ordered_data[$region]['clubs'][ $efmls_general['club_name'] ] .= efmls_tabledata( "President", $efmls_officer['president_name'], $efmls_officer['president_phone'], $efmls_officer['president_email'] );
		$ordered_data[$region]['clubs'][ $efmls_general['club_name'] ] .= efmls_tabledata( "Vice President", $efmls_officer['vp_name'], $efmls_officer['vp_phone'], $efmls_officer['vp_email'] );
		$ordered_data[$region]['clubs'][ $efmls_general['club_name'] ] .= efmls_tabledata( "Secretary", $efmls_officer['secretary_name'], $efmls_officer['secretary_phone'], $efmls_officer['secretary_email'] );
		$ordered_data[$region]['clubs'][ $efmls_general['club_name'] ] .= efmls_tabledata( "Treasurer", $efmls_officer['treasurer_name'], $efmls_officer['treasurer_phone'], $efmls_officer['treasurer_email'] );
		$ordered_data[$region]['clubs'][ $efmls_general['club_name'] ] .= efmls_tabledata( "Liason", $efmls_officer['liason_name'], $efmls_officer['liason_phone'], $efmls_officer['liason_email'] );
		$ordered_data[$region]['clubs'][ $efmls_general['club_name'] ] .= efmls_tabledata( "Editor", $efmls_officer['editor_name'], $efmls_officer['editor_phone'], $efmls_officer['editor_email'] );
		$ordered_data[$region]['clubs'][ $efmls_general['club_name'] ] .= efmls_tabledata( "Webmaster", $efmls_officer['webmaster_name'], $efmls_officer['webmaster_phone'], $efmls_officer['webmaster_email'] );

		$ordered_data[$region]['clubs'][ $efmls_general['club_name'] ] .= <<<HTML
				</tbody>
			</table>
			<p><b>Bulletin/Newsletter</b>: {$efmls_general['bulletinnewsletter_title']}<br>
			<b>Show/Swap</b>: {$efmls_general['showswap_date']}, {$efmls_general['showswap_location']}
			</p>
		</div>
	</div>
HTML;

	}
	$club_names = array();
	ksort($ordered_data);
	foreach ($ordered_data as $region => $regiondata ) {
		$region_id = strtolower( preg_replace('/\s+/', '_', $region) );
		$shortcode_return .= <<<HTML
		<section class="clubregion" id="{$region_id}">
			<h2>{$region}</h2>
			<p class="regiondesc">{$regiondata['desc']}</p>
HTML;
		ksort($regiondata['clubs']);
		foreach ($regiondata['clubs'] as $club_name => $clubdata ) {
			$club_names[] = $club_name;
			$shortcode_return .= $clubdata;
		}
		$shortcode_return .= <<<HTML
		</section>
HTML;	
	}
	$alpha_list = <<<HTML
		<section class="alpha_list">
HTML;
	asort($club_names);
	foreach ($club_names as $club_name) {
		$alpha_list .= <<<HTML
			<p>{$club_name}</p>
HTML;
	}
	$alpha_list .= <<<HTML
		</section>
HTML;

	wp_reset_postdata();
	return <<<HTML
<h1 id="efmls-clubs-region">EFMLS Member Clubs by Region</h1>
<div class="club_directory">
	{$shortcode_return}
</div>
<h1 id="efmls-clubs-alpha">Alphabetical List of Clubs</h1>
<div class="alphabetical_clubs">
	{$alpha_list}
</div>
HTML;

}
add_shortcode('efmls_directory','efmls_generate_directory');

// }}}

// {{{ Add custom stylesheet for when we are editing the clubdetails custom post type
function efmls_add_cpt_styles () {
	global $post_type;
	if ( is_admin() && 'clubdetails' == $post_type ) {
		wp_enqueue_style('club-details-styles', CLUB_STYLES . 'style.css');
	}
}
add_action( 'admin_print_scripts-post-new.php', 'efmls_add_cpt_styles', 11 );
add_action( 'admin_print_scripts-post.php', 'efmls_add_cpt_styles', 11 );
// }}}

// {{{ Add custom stylesheet for annual directory on the front-end of the site
function efmls_add_front_styles() {
	if ( is_page( array( 'directory-test', 'annual-directory' ) ) ) { 
		wp_enqueue_style('club_details-front-styles', CLUB_STYLES . 'front-style.css', array('hello-elementor-theme-style', 'hello-elementor') );
	}	
}
add_action('wp_enqueue_scripts', 'efmls_add_front_styles');

// }}}

// include scripting to dynamically update values onscreen if changed
//function efmls_clubdetails_enqueue_scripts() {
//	wp_enqueue_script('club-details-js', CLUB_SCRIPTS . 'club-details.js', array(), '1.0.0', true);
//}
//add_action('acf/input/admin_enqueue_scripts', 'efmls_clubdetails_enqueue_scripts');

// icon shoehorn for submenu
function efmls_inline_dashicon( $icon_type ) {
	return <<<HTML
		<span class="wp-menu-image dashicons-before dashicons-{$icon_type}" aria-hidden="true"></span>
HTML;
}

/* vim600: set ft=php.html ts=4 sw=4 noexpandtab foldmethod=marker : */
?>
