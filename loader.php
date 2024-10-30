<?php
/**
 * Plugin Name: BP Birthday Greetings
 * Plugin URI:  https://prashantdev.wordpress.com
 * Description: Members will receive a birthday greeting as a notification
 * Author:      Prashant Singh
 * Author URI:  https://profiles.wordpress.org/prashantvatsh
 * Version:     1.0.6
 * Text Domain: bp-birthday-greetings
 * License:     GPLv2 or later
 */

defined( 'ABSPATH' ) || exit;

/**
 * Checks if buddypress active.
 *
 * @since 1.0.0
 */
function bp_birthday_check_is_buddypress() {
	if ( function_exists( 'bp_is_active' ) ) {
		require( __DIR__ . '/bp-birthday-greetings.php' );
		require( __DIR__ . '/bp-birthday-widget.php' );
	} else {
		add_action( 'admin_notices', 'bp_birthday_buddypress_inactive__error' );
	}
}
add_action( 'plugins_loaded', 'bp_birthday_check_is_buddypress' );

/**
 * Error if buddypress is inactive.
 *
 * @since 1.0.0
 */
function bp_birthday_buddypress_inactive__error() {
	$class   = 'notice notice-error';
	$message = __( 'BP Birthday Greetings requires BuddyPress to be active and running.', 'bp-birthday-greetings' );
	printf( '<div class="%1$s"><p>%2$s</p></div>', esc_attr( $class ), esc_html( $message ) );
}

/**
 * Cron on plugin activation.
 *
 * @since 1.0.0
 */
function bp_birthday_plugin_activation() {
	if ( ! wp_next_scheduled( 'bp_birthday_daily_event' ) ) {
		wp_schedule_event( time(), 'daily', 'bp_birthday_daily_event' );
	}
}
register_activation_hook( __FILE__, 'bp_birthday_plugin_activation' );

/**
 * Birthday serach and notification.
 *
 * @since 1.0.0
 */
function bp_birthday_do_this_daily() {
	global $wp, $bp, $wpdb;
	$bp_birthday_option_value = bp_get_option( 'bp-dob' );
	$sql = $wpdb->prepare( "SELECT profile.user_id, profile.value FROM {$bp->profile->table_name_data} profile INNER JOIN $wpdb->users users ON profile.user_id = users.id AND user_status != 1 WHERE profile.field_id = %d", $bp_birthday_option_value );
	$profileval = $wpdb->get_results( $sql );
	$birthdays = array();
	foreach ( $profileval as $profileobj ) {
		$timeoffset = get_option( 'gmt_offset' );
		if ( ! is_numeric( $profileobj->value ) ) {
			$bday = strtotime( $profileobj->value ) + $timeoffset;
		} else {
			$bday = $profileobj->value + $timeoffset;
		}
		if ( ( date_i18n( 'n' ) == date( 'n', $bday ) ) && ( date_i18n( 'j' ) == date( 'j', $bday ) ) )
			$birthdays[] = $profileobj->user_id;
		if ( ! empty( $birthdays ) ) {
			bp_birthday_happy_birthday_notification( $birthdays );
		}
	}
}
add_action( 'bp_birthday_daily_event', 'bp_birthday_do_this_daily' );

/**
 * Set notiofication for greetings.
 *
 * @param array $birthdays Birthday user IDs.
 * @since 1.0.0
 */
function bp_birthday_happy_birthday_notification( $birthdays ) {
	foreach ( $birthdays as $key => $value ) {
		bp_notifications_add_notification(
			array(
				'user_id'          => $value,
				'item_id'          => $value,
				'component_name'   => 'birthday',
				'component_action' => 'ps_birthday_action',
				'date_notified'    => bp_core_current_time(),
				'is_new'           => 1,
			)
		);
	}
}

/**
 * Set birthday component.
 *
 * @param array $component_names Component names.
 * @since 1.0.0
 */
function bp_birthday_get_registered_components( $component_names = array() ) {
	if ( ! is_array( $component_names ) ) {
		$component_names = array();
	}
	array_push( $component_names, 'birthday' );
	return $component_names;
}
add_filter( 'bp_notifications_get_registered_components', 'bp_birthday_get_registered_components' );

/**
 * Birthday notification text.
 *
 * @param string $content               Content of notification.
 * @param int    $item_id               Notification item ID.
 * @param int    $secondary_item_id     Notification secondary item ID.
 * @param int    $total_items           Number of notifications with the same action.
 * @param string $format                Format of return. Either 'string' or 'object'.
 * @param string $action                Canonical notification action.
 * @param string $component             Notification component ID.
 * @since 1.0.0
 */
function bp_birthday_buddypress_notifications( $content, $item_id, $secondary_item_id, $total_items, $format, $action, $component ) {
	if ( 'ps_birthday_action' === $action ) {
		if ( empty( $format ) ) {
			$format = 'string';
		}

		$site_title   = get_bloginfo( 'name' );
		$custom_title = sprintf(
			__( 'Wish you a very happy birthday. %s wishes you more success and peace in life.', 'bp-birthday-greetings' ),
			$site_title,
		);

		$custom_link = '';

		$custom_text = sprintf(
			__( 'Wish you a very happy birthday. %s wishes you more success and peace in life.', 'bp-birthday-greetings' ),
			$site_title,
		);

		if ( 'string' === $format ) {
			$return = apply_filters( 'ps_birthday_filter', '<a href="' . esc_url( $custom_link ) . '" title="' . esc_attr( $custom_title ) . '">' . esc_html( $custom_text ) . '</a>', $custom_text, $custom_link );
		} else {
			$return = apply_filters(
				'ps_birthday_filter',
				array(
					'text' => $custom_text,
					'link' => $custom_link,
				),
				$custom_link,
				(int) $total_items,
				$custom_text,
				$custom_title
			);
		}
		return $return;
	}
}
add_filter( 'bp_notifications_get_notifications_for_user', 'bp_birthday_buddypress_notifications', 10, 7 );

/**
 * Enqueue styles.
 *
 * @since 1.0.0
 */
function bp_birthday_enqueue_style() {
	wp_enqueue_style( 'birthday-style', plugin_dir_url( __FILE__ ) . 'assets/css/bp-birthday-style.css', array(), '1.0.6' );
}
add_action( 'wp_enqueue_scripts', 'bp_birthday_enqueue_style' );

/**
 * Shortcode function.
 *
 * @since 1.0.0
 */
function bp_birthday_shortcode() {
	global $wp, $bp, $wpdb;
	$bp_birthday_option_value = bp_get_option( 'bp-dob' );

	$sql = $wpdb->prepare( "SELECT profile.user_id, profile.value FROM {$bp->profile->table_name_data} profile INNER JOIN $wpdb->users users ON profile.user_id = users.id AND user_status != 1 WHERE profile.field_id = %d", $bp_birthday_option_value );

	$profileval = $wpdb->get_results( $sql );
	$birthdays  = array();

	foreach ( $profileval as $profileobj ) {
		$timeoffset = get_option( 'gmt_offset' );
		if ( ! is_numeric( $profileobj->value ) ) {
			$bday = strtotime( $profileobj->value ) + $timeoffset;
		} else {
			$bday = $profileobj->value + $timeoffset;
		}

		if ( ( date_i18n( 'n' ) == date( 'n', $bday ) ) && ( date_i18n( 'j' ) == date( 'j', $bday ) ) ) {
			$birthdays[] = $profileobj->user_id;
		}
	}

	if ( empty( $birthdays ) ) {
		$empty_message = apply_filters( 'bp_birthday_empty_message', __( 'No Birthdays Found Today.', 'bp-birthday-greetings' ) );
		return '<p>' . $empty_message . '</p>';
	} else {
		$bp_html = '<ul class="birthday-members-list">';
		foreach ( $birthdays as $birthday => $members_id ) {
			$member_name = bp_core_get_user_displayname( $members_id );
			$btn         = '';
			if ( bp_is_active( 'messages' ) ) {
				$defaults = array(
					'id'                => 'private_message-' . $members_id,
					'component'         => 'messages',
					'must_be_logged_in' => true,
					'block_self'        => true,
					'wrapper_id'        => 'send-private-message-' . $members_id,
					'wrapper_class'     => 'send-private-message',
					'link_href'         => wp_nonce_url( bp_loggedin_user_domain() . bp_get_messages_slug() . '/compose/?r=' . bp_members_get_user_slug( $members_id ) ),
					'link_title'        => __( 'Send a private message to this user.', 'bp-birthday-greetings' ),
					'link_text'         => __( 'Wish Happy Birthday', 'bp-birthday-greetings' ),
					'link_class'        => 'send-message',
				);

				if ( bp_loggedin_user_id() !== intval( $members_id ) ) {
					$btn = bp_get_button( $defaults );
				} else {
					$btn = '';
				}
			}

			$dp_width  = bp_get_option( 'bp-dp-width' );
			$dp_width  = ( empty( $dp_width ) ) ? 32 : $dp_width;
			$dp_height = bp_get_option( 'bp-dp-height' );
			$dp_height = ( empty( $dp_height ) ) ? 32 : $dp_height;
			$dp_type   = bp_get_option( 'bp-dp-type' );
			$dp_type   = ( empty( $dp_type ) ) ? 'thumb' : $dp_type;
			$cake_img  = apply_filters( 'bp_birthday_cake_img', '&#127874;' );
			$bp_html  .= '<li>' . bp_core_fetch_avatar( array( 'item_id' => $members_id, 'type' => $dp_type, 'width' => $dp_width, 'height' => $dp_height, 'class' => 'avatar', 'html' => true ) );
			$bp_html  .= esc_html__( 'Happy Birthday', 'bp-birthday-greetings' );
			$bp_html  .= ' ' . $member_name . ' ' . $cake_img . '</li>';
			$bp_html  .= $btn;
		}
		$bp_html .= '</ul>';
		return $bp_html;
	}
}
add_shortcode( 'ps_birthday_list', 'bp_birthday_shortcode' );
