<?php 
/*  Plugin Name: Club Details custom plugin for EFMLS
	Description: Custom post type and editing for club membership information (location, members, dues, etc), officers, and insurance info
	Author: Scott R. Godin for MAD House Graphics
	Author URI: https://madhousegraphics.com
	Version: 0.13
	License: GPL3
 */

// NOTE	import/export/bulk-edit logic can be handled by https://www.wpallimport.com/export-advanced-custom-fields/
//		and does not need to be integrated into the plugin
//
// TODO	add check to make sure ACF Pro is enabled 
// TODO	add front-end display logic for the clubdetails Loop and for the single-posts 
//		[may not be internally required as it seems possible to do this within Elementor itself using Elementor Custom Templates]

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
		'add_new_item'			=> __("Add Club Details", 'efmls'),
		'all_items'				=> __("All Clubs", 'efmls'),
		'archives'				=> __("Club Archives", 'efmls'),
		'attributes'			=> __("Club attributes", 'efmls'),
		'edit_item'				=> __("Edit Club Details", 'efmls'),
		'featured_image'		=> __("Featured Image for this Club", 'efmls'),
		'filter_items_list'		=> __("Filter Club List", 'efmls'),
		'items_list'			=> __("Club list", 'efmls'),
		'items_list_navigation'	=> __("Club list navigation", 'efmls'),
		'menu_name'				=> __("Club Details", 'efmls'),
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
			'name' => 'Regions',
			'singular_item' => 'Region',
			'add_new_item' => 'Add New Region',
			'edit_item' => 'Edit Region',
        ),
        'hierarchical' => true,
        'public' => true,
        'show_ui' => true,
        'show_admin_column' => true,
        'show_in_nav_menus' => false,
        'show_in_rest' => false,
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
		$membership = get_field('membership');

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
HTML;
	}
}
// }}}

// {{{ Add Admin Notice for when member counts total zero
function admin_notice_members_warning () {
	$screen = get_current_screen();
	if ( $screen && 'edit' === $screen->parent_base && 'clubdetails' === $screen->id && function_exists('get_field') ) {
		$membership = get_field('membership');
		$total_members = $membership['adult_members'] + $membership['junior_members'];
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

// {{{ Add limits for revision counts to cut down on database storage of otherwise unlimited revisions
add_filter( 'wp_revisions_to_keep', 'efmls_clubdetails_revisions', 10, 2 );

function efmls_clubdetails_revisions( $num, $post ) {

    if( 'clubdetails' == $post->post_type ) {
        $num = 10;
    }
    return $num;
}
// }}}

// include scripting to dynamically update values onscreen if changed
//function efmls_clubdetails_enqueue_scripts() {
//	wp_enqueue_script('club-details-js', CLUB_SCRIPTS . 'club-details.js', array(), '1.0.0', true);
//	wp_enqueue_style('efmls-clubdetails-css', CLUB_STYLES . 'style.css', array(), '1.0.0', 'all');
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
