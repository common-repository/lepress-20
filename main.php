<?php
/*
Plugin Name: LePress
Plugin URI: http://trac.htk.tlu.ee/lepress/
Version: 2.0.2
Author: <a href="mailto:raido357@gmail.com">Raido Kuli</a>
Description: A plugin for a <a href="http://trac.htk.tlu.ee/lepress/">Centre for the Educational Technology</a>.
*/

require_once('config.php');
require_once('class-lepress-request.php');
require_once('service.php');

/**
 * Main LePress class
 *
 * @author Raido Kuli
 */
 
class LePress {
	
	/**
	 * Init plugin core
	 *
	 * Add some actions and filters, init widget and service
	 * if multisite load also multisite courses widget
	 */

    function __construct() {
    	//load translation file
		load_plugin_textdomain(lepress_textdomain, false, basename(lepress_abspath).'/languages');
		
		//Add action/filter hooks
		add_action('admin_menu', array(&$this, 'createOptionsMenus'));
		add_action('admin_init', array(&$this, 'registerDefaultSettings'));
		add_action('admin_notices', array(&$this, 'checkIsProfileFilled'));
		add_filter('pre_update_option_lepress-settings', array(&$this, 'filterOptionChange'), 10, 2);
		register_deactivation_hook( __FILE__, array(&$this, 'uninstall_network'));
		//Init service
		$this->service = new LePressService();
		//Load widget
		require_once('widget.php');
		
		if(is_multisite()) {
			//Load courses sitewide widget
			require_once('widget-courses-sitewide.php');
		}
    }
	
	/**
	 * Filter settings/option change
	 *
	 * If user is changing LePress settings, we filter the changes
	 * to know if roles where disabled or enabled
	 *
	 * @example If user disables student role, we init unsubscribing sequence on all the active courses
	 * so there would not be any linger subscriptions left on teacher blog
	 *
	 * @return $option variable
	 */
	 
	function filterOptionChange($option, $oldvalue) {
		if(!isSet($option['user-role']['student']) && $this->isStudentFeatures()) {
			//This means user has been in role "student" and now disabling it
			global $LePressStudent;
			$LePressStudent->initUninstall(); //Unsubscribes from all the courses, rest of the data is kept intact;
		}
		
		if(!isSet($option['user-role']['teacher']) && $this->isTeacherFeatures()) {
			//This means user has been in role "teacher" and now disabling it
			global $LePressTeacher;
			$LePressTeacher->initUninstall(); //Remove all subscriptions, rest of the data is kept intact
		}
		return $option;
	}
	
	/**
	 * Add LePress settings	 
	 *
	 * Register LePress settings, add sections and fields
	 */
	 
    function registerDefaultSettings() {
    	register_setting('lepress-settings', 'lepress-settings');
    	add_settings_section('lepress-general', '', array(&$this, 'generalSectionHeader'), 'lepress');
    	add_settings_field('lepress-user-role', __("Choose your role", lepress_textdomain), array(&$this, 'user_role_input'), 'lepress', 'lepress-general');
    	if($this->isTeacherFeatures() || $this->isStudentFeatures()) {
    		add_settings_field('lepress-display-names', __("Display names as", lepress_textdomain), array(&$this, 'display_names_input'), 'lepress', 'lepress-general');
    	}
    }

	/**
	 * Get blog owner user object
	 *
	 * @return user object
	 */
	 
	function getBlogOwnerUser() {
		$blog_owner_email = get_bloginfo('admin_email');
		$blog_owner = get_user_by('email', $blog_owner_email);
		return $blog_owner;
	}
	
	/**
	 * Check is user profile is filled or not
	 *
	 * If profile is not filled, notice will be displayed on admin side of Wordpress
	 */
	 
	function checkIsProfileFilled() {
		$blog_owner = $this->getBlogOwnerUser();
		$current_user = wp_get_current_user();
		$access_granted = false;
		if(!function_exists('get_users')) {
			$users = get_users_of_blog();
		} else {
			$users = get_users();
		}
		foreach($users as $user) {
			if($user->ID == $current_user->ID) {
				$access_granted = true;
				break;
			}
		}
		if((empty($current_user->first_name) || empty($current_user->last_name)) && $access_granted) {
			$this->echoNoticeDiv(''.__('Please edit your profile and fill <b>Firstname</b> and <b>Lastname</b> fields, otherwise some <b>features</b> of LePress are <b>disabled</b>', lepress_textdomain).'. <a href="'.admin_url().'profile.php'.'">'.__('Edit profile', lepress_textdomain).'</a>');
		} else {
			if(!$access_granted) {
				//Not blog owner, maybe needed in future
			}
		}
	}
	
	/**
	 * Display notice on Wordpress admin interface
	 * 
	 * Prints HTML div with selected class (updated || error)
	 */
	 
	 
	function echoNoticeDiv($str, $error = false) {
		$class = 'updated';
		if($error) {
			$class = 'error';
		}
		echo '<div id="message" class="'.$class.'"><p>'.$str.'</p></div>';
	}
	
	/**
	 * Filter string input
	 *
	 * @return filtered string
	 */
	 
	 
	function safeStringInput($str, $strip_tags = false) {
		if($strip_tags) {
			return trim(strip_tags($str));
		} else {
			return htmlspecialchars(trim($str), ENT_COMPAT, $this->getBlogCharset(), false);
		}
	}
	
	/**
	 * Fail-safe blog charset retriever
	 *
	 * @return string charset
	 */
	
	function getBlogCharset() {
		return get_option('blog_charset', 'UTF-8');
	}
	
	/**
	 * Get student role enabled flag
	 *
	 * @return int (role value) or boolean false
	 */
	 
    function isStudentFeatures() {
    	$user_roles = $this->get_option('user-role');
    	return isSet($user_roles['student']) ? $user_roles['student'] : false;
    }

	/**
	 * Get teacher role enabled flag
	 *
	 * @return int (role value) or boolean false
	 */
	 
    function isTeacherFeatures() {
    	$user_roles = $this->get_option('user-role');
    	return isSet($user_roles['teacher']) ? $user_roles['teacher'] : false;
    }
    
    /**
	 * Get display names order flag
	 *
	 * int 1 = Firstname Lastname, int 2 = Lastname Firstname
	 * @return int (1 || 2)
	 */
	 
    function getDisplayNames() {
    	$set = $this->get_option('display-names');
    	return $set ? $set : 1;
    }
    
    /**
	 * Print user roles fields on settings page
	 *
	 * If profile is not filled, fields will be displayed as disabled
	 */

    function user_role_input() {
    	$current_user = $this->getBlogOwnerUser();
    	$disabled = '';
    	if(empty($current_user->user_firstname) || empty($current_user->user_lastname)) {
    		$disabled = 'disabled="disabled"';
    	}
    	echo '<input type="checkbox" '.$disabled.' name="lepress-settings[user-role][student]" '.($this->isStudentFeatures() == 2 && $this->isStudentFeatures() != NULL ? 'checked=checked':'').' id="lepress-settings[user-role][student]" value="2"/> <label for="lepress-settings[user-role][student]">'.__('Student', lepress_textdomain).'</label><br />';
    	echo '<input type="checkbox" '.$disabled.' name="lepress-settings[user-role][teacher]" '.($this->isTeacherFeatures() == 1 && $this->isTeacherFeatures() != NULL ? 'checked=checked':'').' id="lepress-settings[user-role][teacher]" value="1"/> <label for="lepress-settings[user-role][teacher]">'.__('Teacher', lepress_textdomain).'</label>';
    }
    
    /**
	 * Print display names fields on settings page
	 */
    
    function display_names_input() {
		$setting = $this->getDisplayNames();
		$current_user = $this->getBlogOwnerUser();
		echo '<input type="radio" name="lepress-settings[display-names]" '.($setting == 1 ? 'checked="checked"':'').' value="1" id="display-names-1"/> <label for="display-names-1">'.__('Given name Surname', lepress_textdomain).' ('.trim(($current_user->first_name ? $current_user->first_name : $current_user->user_login).' '.$current_user->last_name).')</label><br />';
		echo '<input type="radio" name="lepress-settings[display-names]" '.($setting == 2 ? 'checked="checked"':'').' value="2" id="display-names-2"/> <label for="display-names-2">'.__('Surname Given name', lepress_textdomain).' ('.trim(($current_user->last_name ? $current_user->last_name : $current_user->user_login).' '.$current_user->first_name).')</label>';
    }

	/**
	 * Print general LePress settings section header
	 */
	 
    function generalSectionHeader() {
    	echo '<h3>'.__('General settings', lepress_textdomain).'</h3>';
    }

    /**
     * Add LePress menu on the left 
     */

    function createOptionsMenus() {
    	$current_user = wp_get_current_user();
    	if(current_user_can('manage-lepress') || is_super_admin() || (get_bloginfo('admin_email') === $current_user->user_email)) {
	    	if(!$this->isTeacherFeatures() && !$this->isStudentFeatures()) {
	    		add_menu_page( 'LePress', 'LePress', 3, 'options-general.php?page=lepress-settings');
	    	} else {
	    		add_menu_page( 'LePress', 'LePress', 3, 'lepress');
	    	}
		    add_options_page(__("LePress settings", lepress_textdomain), __("LePress", lepress_textdomain), 'manage_options', 'lepress-settings', array(&$this, 'createDefaultOptionsPage'));
    	}
	}

	/**
	 * Generates default options page 
	 *
	 * Also calls settings section trigger
	 */

	function createDefaultOptionsPage() {
		echo '<div class="wrap">';
		screen_icon();
		echo '<h2>LePress</h2>';
		echo '<form action="options.php" method="post">';
		settings_fields('lepress-settings');
		do_settings_sections('lepress');
		echo '<input name="Submit" type="submit" value="'.__('Save').'"/>';
		echo '</form></div>';
	}

	/**
	 * Get option from lepress-settings options array 
	 * @return option value or boolean false
	 */

	function get_option($option) {
		$options = get_option('lepress-settings');
    	return $options ? $options[$option] : false;
	}

	/**
	 * Plugin deactivation hook, deletes all settings data
	 *
	 * If wipe_out flag set, all data will be removed, wipe out
	 * flag can be set only if teacher role enabled, not meant to be used by students
	 * this method will be called on network installation deactivation too.
	 */

	function _uninstall() {
		$wipe_out = $this->get_option('wipe_out_flag');
		/* Init student data remove */
		if($this->isStudentFeatures() || $wipe_out) {
			global $LePressStudent;
			//Always ensure we are just in case trying to wipe student tables too
			//maybe student role was active, then was disabled
			if(!$LePressStudent instanceOf LePressStudentRole) {
				require_once('classes/student.php');
				$LePressStudent = new LePressStudentRole();
				$LePressStudent->runAfterInit();
			}
			$LePressStudent->initUninstall(); //Unsubscribes from all the courses, rest of the data is kept intact;
			//If passing $wipe_out to initUninstall, database tables will be deleted, if $wipe_out == true
		}
		
		if($this->isTeacherFeatures() || $wipe_out) {
			global $LePressTeacher;
			//Always ensure we are just in case trying to wipe teacher tables too
			//maybe teacher role was active, then was disabled
			if(!$LePressTeacher instanceOf LePressTeacherRole) {
				require_once('classes/teacher.php');
				$LePressTeacher = new LePressTeacherRole();
			}
			$LePressTeacher->initUninstall($wipe_out); //Remove all subscriptions, rest of the data is kept intact
		}
		
		/* Finally remove main settings data */
		unregister_setting('lepress-settings', 'lepress-settings');
		delete_option('lepress-settings');
	}
	
	/**
	 * Plugin Uninstall trigger with Network check 
	 *
	 * If multisite, all blogs will be iterated and deactivation will be hook
	 */
	
	function uninstall_network() {	
		//Network deactivation fix	
		if(is_multisite()) {
			if(isSet($_GET['networkwide']) && ($_GET['networkwide'] == 1)) {
				global $wpdb;
				$old_blog = $wpdb->blogid;
				// Get all blog ids
				$blogids = $wpdb->get_col($wpdb->prepare("SELECT blog_id FROM $wpdb->blogs WHERE blog_id != $old_blog"));
				foreach ($blogids as $blog_id) {
					switch_to_blog($blog_id);
					$this->_uninstall();
				}
				switch_to_blog($old_blog);
			}
		}
		$this->_uninstall();
	}

	/**
	 * Date + timezone fix with i18n 
	 *
	 * This function uses Wordpress date_format option and time_format option
	 * to output date string + adds gmt_offset to the time
	 *
	 * @return formatted date string
	 */

	function date_i18n_lepress($tstamp, $to_int = false, $format = false, $time = false) {
	    if($to_int) {
	        $tstamp = intval($tstamp);
	    }
	    $tstamp = !is_numeric($tstamp) ? strtotime($tstamp) : $tstamp;
	    $gmt_offset = is_string(get_option('gmt_offset')) ? get_option('gmt_offset') : 0;
		return trim(date_i18n((!$format ? get_option('date_format') : $format).' '.($time ? get_option('time_format') :''), (int) $tstamp + ($gmt_offset * 3600)));
	}
	
	/**
	 * Multibyte strlen with fallback
	 *
	 * This functions fallsback to strlen method, if multibyte library not loaded.
	 *
	 * @return string length
	 */
	
	function mb_strlen($str) {
		if(function_exists('mb_strlen')) {
			return mb_strlen(trim($str), $this->getBlogCharset());
		} else {
			return strlen(trim($str));
		}
	}
	
	/**
	 * Multibyte cut string with fallback
	 *
	 * This functions fallsback to strlen method, if multibyte library not loaded.
	 *
	 * @return trimmed string
	 */

	function mb_cutstr($str, $end = 200, $start = 0) {
		$str = strip_tags($str);
		//Need check, in case Windows Server, with php_mbstring disabled
		if(function_exists('mb_strlen') && function_exists('mb_substr')) {
			if(mb_strlen($str, $this->getBlogCharset()) <= $end) {
				return $str;   
			}
			$arr = explode(" ", mb_substr($str, $start, $end, $this->getBlogCharset()));
	    } else {
	    	if(strlen($str) <= $end) {
	    		return $str;
	    	}
	    	$arr = explode(" ", substr($str, $start, $end));
	    }
	    //Pop last element, which is probably half cut word
	    array_pop($arr);
	    return (string) implode(" ", $arr)."...";    
	}
	
	/**
	 * Add advanced section to LePress settings page
	 */
	 
	function registerAdvancedSection() {
		if($this->isTeacherFeatures()) {
			add_settings_section('lepress-advanced', '', array(&$this, 'advancedSectionHeader'), 'lepress');
			add_settings_field('lepress-wipe-out', __("Wipe data on plugin deactivation", lepress_textdomain), array(&$this, 'permanentDeleteField'), 'lepress', 'lepress-advanced');
		}
	}
	
	/**
	 * Print wipe out field on settings page
	 */
	 
	function permanentDeleteField() {
		$wipe_out = $this->get_option('wipe_out_flag');
		echo '<input type="checkbox" name="lepress-settings[wipe_out_flag]" '.($wipe_out ? 'checked="checked"' : '').' id="lepress-settings[wipe_out_flag]" value="1"/> <label for="lepress-settings[wipe_out_flag]"><b>'.__('CAUTION! ALL THE DATA WILL BE DELETED!', lepress_textdomain).'</b></label> <span class="description">'.__('If checked, LePress database tables will be deleted. Data saved as Wordpress metadata will not be altered.', lepress_textdomain).'</span>';
	}
	
	/**
	 * Print advanced sections header
	 */
	
	function advancedSectionHeader() {
    	echo '<h3>'.__('Advanced', lepress_textdomain).'</h3>';
    }
    
    /**
     * Comment fetcher
     *
     * Retrieve comment using post_id, user_email and agent fields
     *
     * @return comment object
     */
    
    function fetchComment($post_ID, $user_email, $agent = '') {
    	$args = array('post_id' => $post_ID, 'number' => -1);
    	if($user_email) {
    		$args['author_email'] = $user_email;
    	}
    	$comments = get_comments($args);
		foreach($comments as $comment) {
			if($comment->comment_agent == $agent) {
				return $comment;
			}
		}
    }

}

if(class_exists('LePress')) {
    //Define global variable $LePress
    $LePress = new LePress();
    
	/* If teacher role checked, load teacher features */
    if($LePress->isTeacherFeatures()) {
		require_once('classes/teacher.php');
		$LePressTeacher = new LePressTeacherRole();
    }
    
    /* If student role checked, load student features */
    if($LePress->isStudentFeatures()) {
		require_once('classes/student.php');
		$LePressStudent = new LePressStudentRole();
		//Run after init function
		$LePressStudent->runAfterInit();
    }
    
    //Add extra advanced section
    add_action('admin_init', array(&$LePress, 'registerAdvancedSection'));
}

?>
