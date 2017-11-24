<?php

/**
 * Theme functions. Initializes the Vamtam Framework.
 *
 * @package  wpv
 */

require_once('vamtam/classes/framework.php');

new WpvFramework(array(
	'name' => 'church-event',
	'slug' => 'church-event'
));

// TODO remove next line when the editor is fully functional, to be packaged as a standalone module with no dependencies to the theme
define ('VAMTAM_EDITOR_IN_THEME', true); include_once THEME_DIR.'vamtam-editor/editor.php';

if ( class_exists( 'LS_Sources' ) ) {
	LS_Sources::addDemoSlider( WPV_SAMPLES_DIR . 'layerslider' );
}

remove_action( 'admin_head', 'jordy_meow_flattr', 1 );

function show_locations(){
?>   
<h2><?php _e( 'Select Locations: ' ); ?></h2>
	<?php wp_dropdown_categories( 'show_option_none=Select Locations&id=loc-id&taxonomy=mec_location&post_type=mec-events&value_field=slug' ); ?>
	<script type="text/javascript">
		<!--
		var dropdown = document.getElementById("loc-id");
		function onCatChange() {
			if ( dropdown.options[dropdown.selectedIndex].value != -1  ) {
				location.href = "<?php echo esc_url( home_url( '/' ) ); ?>/events-location/"+dropdown.options[dropdown.selectedIndex].value;
			}
		}
		dropdown.onchange = onCatChange;
		-->
	</script>

<?php
}

add_action( 'show_user_profile', 'my_show_extra_profile_fields' );
add_action( 'edit_user_profile', 'my_show_extra_profile_fields' );
function my_show_extra_profile_fields( $user ) { ?>
<h3>Extra profile information</h3>
    <table class="form-table">
<tr>
            <th><label for="phone">Phone Number</label></th>
            <td>
            <input type="text" name="phone" id="phone" value="<?php echo esc_attr( get_the_author_meta( 'phone', $user->ID ) ); ?>" class="regular-text" /><br />
                <span class="description">Please enter your phone number.</span>
            </td>
</tr>
</table>
<?php }

add_action( 'personal_options_update', 'my_save_extra_profile_fields' );
add_action( 'edit_user_profile_update', 'my_save_extra_profile_fields' );

function my_save_extra_profile_fields( $user_id ) {

if ( !current_user_can( 'edit_user', $user_id ) )
    return false;

update_usermeta( $user_id, 'phone', $_POST['phone'] );
}

add_shortcode('list_locations','show_locations');

function tml_registration_errors( $errors ) {
	if ( empty( $_POST['first_name'] ) )
		$errors->add( 'empty_first_name', '<strong>ERROR</strong>: Please enter your first name.' );
	return $errors;
}
add_filter( 'registration_errors', 'tml_registration_errors' );

function tml_user_register( $user_id ) {
	if ( !empty( $_POST['first_name'] ) )
		update_user_meta( $user_id, 'first_name', $_POST['first_name'] );
	if ( !empty( $_POST['last_name'] ) )
		update_user_meta( $user_id, 'last_name', $_POST['last_name'] );
	if ( !empty( $_POST['user_phone'] ) )
		update_user_meta( $user_id, 'phone', $_POST['user_phone'] );
}
add_action( 'user_register', 'tml_user_register' );

function topheader_countdown_func( $atts ) {
	$args = array(
					'post_type' => 'mec-events',
					'post_status' => 'publish',
					'posts_per_page' => 1,
					'orderby' => 'most_recent',
					'tax_query' => array(
						array(
							'taxonomy' => 'mec_category',
							'field' => 'slug',
							'terms' => 'big-event',
						),
					),
				);
	$the_query = new WP_Query($args);
	
	$allevents = $the_query->posts[0];
	$lat_id = $allevents->ID;

	if( empty($lat_id) || $lat_id == '' )
	return '';
	 
	$meta = get_post_meta( $lat_id );
	$result = $meta['mec_date']; 
	$date = unserialize($result[0]);

	$eventLink = get_permalink($lat_id);
	if( empty($eventLink) || $eventLink == '' )
	$eventLink = '#';
	

	/*$date =  array (
				'start' => array
					(
						'date' => '2016-10-08',
						'hour' => '8',
						'minutes' => '0',
						'ampm' => 'AM'
					),

				'end' => array
					(
						'date' => '2016-10-08',
						'hour' => '6',
						'minutes' => '0',
						'ampm' => 'AM'
					),

				'allday' => '0',
				'hide_time' => '0',
				'past' => '0'
			);
*/
	$start_date = (isset($date['start']) and isset($date['start']['date'])) ? $date['start']['date'] : date('Y-m-d H:i:s');

	$current_time = '';
	$current_time .= sprintf("%02d", $date['start']['hour']).':';
	$current_time .= sprintf("%02d", $date['start']['minutes']);
	$current_time .= trim($date['start']['ampm']);

	$start_time = date('D M j Y G:i:s', strtotime($start_date.' '.date('H:i:s', strtotime($current_time))));

	$d1 = new DateTime($start_time);
	$d2 = new DateTime(date("D M j Y G:i:s"));

	if($d1 < $d2)
	{
		$res =  '<div class="mec-end-counts topheader_countdown_notexist"><h3>'.__('The Event Is Finished', 'mec').'</h3></div>';
		$res .= '<script type="text/javascript">
					jQuery(document).ready(function()
					{
						var innerText = jQuery("#topheader_countdown_wrapper div.first").html();
						jQuery(".topheader_countdown_notexist").html("<h3>"+innerText+" The Event Is Finished</h3>");
						jQuery("#topheader_countdown_wrapper div.first").hide();
					});
				</script>';
		return $res;
	}

	// Generating html output of countdown module
	$res = '<div class="mec-countdown-details topheader_countdown_wrapper" id="mec_countdown_details">
				<div class="countdown-w ctd-simple">
					<ul class="clockdiv" id="topheader_countdown">
						<div class="days-w block-w">
							<li>
								<i class="icon-w li_calendar"></i>
								<span class="mec-days">00</span>
								<p class="mec-timeRefDays label-w">' . __('days', 'mec') . '</p>
							</li>
						</div>
						<div class="hours-w block-w">    
							<li>
								<i class="icon-w fa-clock-o"></i>
								<span class="mec-hours">00</span>
								<p class="mec-timeRefHours label-w">' . __('hours', 'mec') . '</p>
							</li>
						</div>  
						<div class="minutes-w block-w">
							<li>
								<i class="icon-w li_clock"></i>
								<span class="mec-minutes">00</span>
								<p class="mec-timeRefMinutes label-w">' . __('minutes', 'mec') . '</p>
							</li>
						</div>
						<div class="seconds-w block-w">
							<li>
								<i class="icon-w li_heart"></i>
								<span class="mec-seconds">00</span>
								<p class="mec-timeRefSeconds label-w">' . __('seconds', 'mec') . '</p>
							</li>
						</div>						
						<div class="link-w block-w">
							<li>
								<i class="icon-w li_link"></i>
								<p class="mec-timeReflink label-w"><a href="'.$eventLink.'">' . __('Read more', 'mec') . '</a></p>
							</li>
						</div>
					</ul>
				</div>
			</div>';
	// Generating javascript code of countdown module
	$res .= '<script type="text/javascript">
	jQuery(document).ready(function()
	{
		jQuery("#topheader_countdown").mecCountDown(
		{
			date: "'.$start_time.'",
			format: "off"
		},
		function()
		{
		});
	});
	</script>';

	return $res;
}
add_shortcode( 'topheader_countdown', 'topheader_countdown_func' );