<?php

/** 
 * LePress Student role class 
 *
 * @author Raido Kuli
 */

require_once("student_include/class-my_subscriptions.php");
require_once("student_include/class-student_groups.php");
require_once(lepress_abspath."class-lepress-cron.php");

class LePressStudentRole extends LePress {

	/* Init class, add some actions and filters, create database structure */
	
    function __construct() {
    	add_action('admin_menu', array(&$this, 'addSubMenus'));
    	add_action('add_meta_boxes', array(&$this, 'addPostMetaboxes'));
    	add_action('admin_init', array(&$this, 'registerScripts'));
    	add_action('admin_head', array(&$this, 'addHeadJavascriptVars'));
    	add_action('save_post', array(&$this, 'savePostMeta'), 1, 2);
    	add_action('profile_update', array(&$this, 'profileUpdated'),1, 2);
    	add_filter('pre_update_option_lepress-settings', array(&$this, 'spawnOptionsChanged'),10, 2);
    	
    	$this->createDatabaseStructure();
    }
    
    /* Add additional hooks and start our cron */
    
    function runAfterInit() {
    	$this->subscriptions = new StudentSubscriptions();
    	//$this->groups =  new StudentGroups();
    	//Custom actions
    	add_action('refreshCoursesMeta', array(&$this->subscriptions, 'refreshCoursesMeta'));
    	add_action('refreshAssignments', array(&$this->subscriptions, 'refreshAssignments'));
    	add_action('refreshClassmates', array(&$this->subscriptions, 'refreshClassmates'));

    	//Ajax load awaiting count triggers and hooks
    	add_filter('query_vars', array(&$this, 'awaiting_add_trigger'));
		add_action('template_redirect', array(&$this,'awaiting_trigger_check'));
		
		//Comment content filter on home page
		add_filter('get_comment_text', array(&$this, 'filterCommentText'), 10, 2);
		
    	//Load cron after adding custom actions
    	$this->cron = new LePressCron();

    	//Add LePress pseudo-cron calls
    	if(!$this->cron->get_next_scheduled('refreshCoursesMeta')) {
    		$ms = $this->get_option('courseRefreshTime') * 60;
    		if($ms > 0) {
				$this->cron->add_schedule('refreshCoursesMeta', time()+$ms);
    		}
		}
		if(!$this->cron->get_next_scheduled('refreshAssignments')) {
			$ms = $this->get_option('assignmentsRefreshTime') * 60;
    		if($ms > 0) {
				$this->cron->add_schedule('refreshAssignments', time()+$ms);
    		}
		}
		if(!$this->cron->get_next_scheduled('refreshClassmates')) {
			$ms = $this->get_option('classmatesRefreshTime') * 60;
    		if($ms > 0) {
				$this->cron->add_schedule('refreshClassmates', time()+$ms);
    		}
		}
    }
    
     /**
      * Return WPDB prefix 
      *
      * This method returns wpdb->prefix value, with multisite compatibility
      *
      * @return string
      */
    
    function get_wpdb_prefix() {
    	global $wpdb;
    	if(is_multisite() && $wpdb->blogid > 1) {
    		$wpdb_prefix = $wpdb->base_prefix.$wpdb->blogid.'_'.get_class($this).'_';
    	} else {
    		$wpdb_prefix = $wpdb->prefix.get_class($this).'_';
    	}
    	return $wpdb_prefix;
    }
	
	/**
	 * Uninstall initer
	 *
	 * @param $wipe_out This flag is not used on student side
	 */
	
	function initUninstall($wipe_out = false) {
		$this->subscriptions->unsubscribeAllActive();
		if($wipe_out) {
			$this->createDatabaseStructure($wipe_out);
		}
	}
	
	/**
	 * Create database and check if all tables exist on each load
	 *
	 * @param $wipe_out This flag is not used on student side
	 */
	 
    function createDatabaseStructure($wipe_out = false) {
		global $wpdb;

		$tables = array($this->get_wpdb_prefix().'courses' => 'CREATE TABLE `{table_name}` (
							  `ID` int(11) NOT NULL AUTO_INCREMENT,
							  `status` tinyint(1) DEFAULT 0,
							  `archived` tinyint(1) DEFAULT 0,
							  `accept_key` char(10) NOT NULL,
							  `course_name` varchar(250) NOT NULL,
							  `course_url` varchar(250) DEFAULT NULL,
							  `message` text,
							  PRIMARY KEY (`ID`)
							) ENGINE=InnoDB DEFAULT CHARSET=utf8',
							$this->get_wpdb_prefix().'teachers' => 'CREATE TABLE `{table_name}` (
							  `ext_user_id` int(11) NOT NULL,
							  `firstname` varchar(250) DEFAULT NULL,
							  `lastname` varchar(250) DEFAULT NULL,
							  `email` varchar(250) DEFAULT NULL,
							  `organization` varchar(250) DEFAULT NULL,
							  PRIMARY KEY (`ext_user_id`)
							) ENGINE=InnoDB DEFAULT CHARSET=utf8;',
							$this->get_wpdb_prefix().'courses_teacher_rel' => 'CREATE TABLE `{table_name}` (
							  `id` int(11) NOT NULL AUTO_INCREMENT,
							  `rel_courses_id` int(11) NOT NULL,
							  `rel_teacher_id` int(11) NOT NULL,
							  PRIMARY KEY (`id`),
							  UNIQUE KEY `rel_courses_id` (`rel_courses_id`,`rel_teacher_id`),
							  FOREIGN KEY (`rel_courses_id`) REFERENCES `'.$this->get_wpdb_prefix().'courses` (`ID`) ON DELETE CASCADE,
							  FOREIGN KEY (`rel_teacher_id`) REFERENCES `'.$this->get_wpdb_prefix().'teachers` (`ext_user_id`) ON DELETE CASCADE
							) ENGINE=InnoDB DEFAULT CHARSET=utf8;',
							$this->get_wpdb_prefix().'assignments' => 'CREATE TABLE `{table_name}` (
							  `id` int(11) NOT NULL AUTO_INCREMENT,
							  `post_id` int(11) NOT NULL,
							  `title` text,
							  `excerpt` text,
							  `url` VARCHAR(250),
							  `start_date` timestamp NOT NULL DEFAULT "0000-00-00 00:00:00",
							  `end_date` timestamp NOT NULL DEFAULT "0000-00-00 00:00:00",
							  `course_id` int(11) NOT NULL,
							  PRIMARY KEY (`id`),
							  UNIQUE (`course_id`,`post_id`),
							  FOREIGN KEY (`course_id`) REFERENCES `'.$this->get_wpdb_prefix().'courses` (`ID`) ON DELETE CASCADE
							) ENGINE=InnoDB DEFAULT CHARSET=utf8;',
							$this->get_wpdb_prefix().'groups' => ' CREATE TABLE `{table_name}` (
							  `id` int(11) NOT NULL AUTO_INCREMENT,
							  `group_name` varchar(250) NOT NULL,
							  `group_key` char(32) DEFAULT NULL,
							  `type` tinyint(1) DEFAULT 0,
							  `course_id` int(11) NOT NULL,
							  PRIMARY KEY (`id`), UNIQUE(`group_name`),
							  FOREIGN KEY (`course_id`) REFERENCES `'.$this->get_wpdb_prefix().'courses` (`ID`) ON DELETE CASCADE
							) ENGINE=InnoDB DEFAULT CHARSET=utf8;',
							$this->get_wpdb_prefix().'classmates' => 'CREATE TABLE `{table_name}` (
							  `id` int(11) NOT NULL AUTO_INCREMENT,
							  `firstname` varchar(250) DEFAULT NULL,
							  `lastname` varchar(250) DEFAULT NULL,
							  `blog_url` text,
							  `email` varchar(250) DEFAULT NULL,
							  `course_id` int(11) NOT NULL,
							  PRIMARY KEY (`id`),
							  UNIQUE KEY `email` (`email`,`course_id`),
							  FOREIGN KEY (`course_id`) REFERENCES `'.$this->get_wpdb_prefix().'courses` (`ID`) ON DELETE CASCADE
							) ENGINE=InnoDB DEFAULT CHARSET=utf8;',
							$this->get_wpdb_prefix().'classmates_group_rel' => 'CREATE TABLE `{table_name}` (
							  `group_id` int(11) NOT NULL,
							  `classmate_id` int(11) NOT NULL,
							  `status` int(11) DEFAULT 0,
							  PRIMARY KEY (`group_id`),
							  UNIQUE KEY `group_id` (`group_id`,`classmate_id`),
							  FOREIGN KEY (`group_id`) REFERENCES `'.$this->get_wpdb_prefix().'groups` (`id`) ON DELETE CASCADE,
							  FOREIGN KEY (`classmate_id`) REFERENCES `'.$this->get_wpdb_prefix().'classmates` (`id`) ON DELETE CASCADE
							) ENGINE=InnoDB DEFAULT CHARSET=utf8;',
							$this->get_wpdb_prefix().'cron' => 'CREATE TABLE `{table_name}` (
							  `hook` VARCHAR(250) NOT NULL,
							  `scheduled_time` INT(11) NOT NULL,
							  PRIMARY KEY (`hook`)
							) ENGINE=InnoDB DEFAULT CHARSET=utf8;');
		if($wipe_out) {
			$wpdb->query('SET FOREIGN_KEY_CHECKS = 0;');
		}
		foreach($tables as $table_name => $query) {
			if(!$wipe_out) {
				//IF table doesn't exist, create it
				if($wpdb->get_var('SHOW TABLES LIKE "'.$table_name.'"') != $table_name) {
					require_once(ABSPATH.'wp-admin/includes/upgrade.php');
					$query = str_replace('{table_name}', $table_name, $query);
					dbDelta($query);
					//echo $query;
				}
			} else {
				$wpdb->query('DROP TABLE '.$table_name);
			}
		}
		
		//Revert foreign_key_checks to 1
		if($wipe_out) {
			$wpdb->query('SET FOREIGN_KEY_CHECKS = 1;');
		} else {
			//Check also for some default options
			//If not found, add default values
			if($general_options = get_option('lepress-settings')) {
				if(is_null($this->get_option('courseRefreshTime'))) {
					$general_options['courseRefreshTime'] = 60;
				}
				if(is_null($this->get_option('classmatesRefreshTime'))) {
					$general_options['classmatesRefreshTime'] = 15;
				}
				if(is_null($this->get_option('assignmentsRefreshTime'))) {
					$general_options['assignmentsRefreshTime'] = 2;
				}
				if(array_diff($general_options, get_option('lepress-settings'))) {
					update_option('lepress-settings', $general_options);
				}
			}
		}
    }

	/**
	 * Filter comments content
	 *
	 * If new comment, add bold tag
	 *
	 * @return content string
	 */
	 
	function filterCommentText($content, $comment) {
		$grade = $this->subscriptions->getGrade($comment->comment_post_ID);
		if($grade != "NA") {
			if(is_admin()) {
				$unread = get_comment_meta($comment->comment_ID, 'lepress-read', true);
				//If admin side and viewing comments, delete lepress-read metadata - assume user has read answer feedback
				delete_comment_meta($comment->comment_ID, 'lepress-read');
			}
			if(is_user_logged_in() && $unread) {
				return '<b>'.$content.'</b>';
			}
		}
		return $content;
	}
	
	/**
	 * Add additional comments form column - Grade
	 *
	 * @return $cols
	 */
	 
	function addCommentsCustomColumn($cols) {
    	$cols['lepress-grade'] = __('Grade', lepress_textdomain);
    	return $cols;
    }
    
    /**
	 * Filter comment form columns, if grade column found fill it
	 */
	 
    function manageCommentsCustomColumn($col, $comment_ID) {
    	if($col == 'lepress-grade') {
    		$comment = get_comment($comment_ID);
    		$grade = $this->subscriptions->getGrade($comment->comment_post_ID);
    		if($grade != "NA") {
    			echo $grade;
    		}
    	}
    }
    
    /**
	 * Return awaiting counts (comments, subscriptions, assignments) to AJAX call
	 */
	 
	function awaiting_trigger_check() {
		if(intval(get_query_var('lepress-student-awaiting')) == 1) {
			//WP default header is 404, have to override it
			header("HTTP/1.0 200 OK");
			if(isSet($_GET['w'])) {
				echo $this->getAwaitingBubble($_GET['w']);
			}
			exit;
		}
   	}

	/**
	 * Add awaiting AJAX call trigger to query vars
	 *
	 * @return query vars
	 */
	 
	function awaiting_add_trigger($vars) {
		$vars[] = 'lepress-student-awaiting';
    	return $vars;
	}

	/**
	 * Fetch actual awaiting count for request
	 *
	 * @return html string with count
	 */
	 
	function getAwaitingBubble($what) {
		switch($what) {
			case 'subscriptions':
				$count = $this->subscriptions->getPendingCount();
				break;
			case 'assignments':
				$count = $this->subscriptions->getPendingAssignmentsCount();
				break;
			case 'comments':
				$count = $this->getUnreadCommentsCount();
				break;
			default:
				$count = 0;
		}
		if($count > 0) {
			$wp_ver = get_bloginfo('version');
			if($wp_ver > '3.1.4') {
				return '<span class="awaiting-mod count-'.$count.'"><span class="pending-count-lepress">' . $count . '</span></span>';
			} else {
				return '<span id="awaiting-mod" class="count-'.$count.'"><span class="pending-count">'.$count.'</span></span>';
			}
		}
	}
	
	/* Fetch unread comments count */
	
	function getUnreadCommentsCount() {
		$count = 0;
		foreach(get_comments() as $comment) {
			$meta = get_comment_meta($comment->comment_ID, 'lepress-read', true);
			if($meta || !$comment->comment_approved) { //Comment has lepress-read metadata or Wordpress comment which is not approved
				$count++;
			}
		}
		return $count;
	}

	/**
	 * Add JavaScript via wp_head hook
	 *
	 * JavaScript language vars and student awaiting count path (URL)
	 */
	 
	function addHeadJavascriptVars() {
		echo '<script type="text/javascript">'."\n";
		echo 'var lepress_student_awaiting_url = "'.add_query_arg(array('lepress-student-awaiting' => 1), get_bloginfo('siteurl')).'";'."\n";
		$lang_vars = array('submission_for' => __('Submission for', lepress_textdomain),
							'hide' => __('Hide', lepress_textdomain), 
							'expand' => __('Expand', lepress_textdomain));
		echo 'var lepress_lang_vars_student = '.json_encode($lang_vars).';'."\n";
		echo '</script>'."\n";
	}
	
	/**
	 * ProfileUpdated hook filter, sends update profile data to teacher blog
	 */

    function profileUpdated($user_id, $old_user_data) {
    	if(isSet($_POST)) {
    		$user_data = get_userdata($user_id);
			$first_name = $user_data->first_name;
			$last_name = $user_data->last_name;
			$email = $user_data->user_email;
			if(!empty($email) && (!empty($first_name) || !empty($last_name))) {
				$courses = $this->subscriptions->getApproved();

				//Send out update info to all the subscribed courses - teacher blog database update.
				foreach($courses as $course) {
					$studentRequest = new LePressRequest(2, 'profileUpdated');
					$studentRequest->addParams(array('first_name' => $first_name, 'last_name' => $last_name, 'new_email' => $email, 'old_email' => $old_user_data->user_email, 'accept-key' => $course->accept_key));
					$studentRequest->doPost($course->course_url, false); //Do request without blocking
					//Keeping fingers crossed that request is successful
				}
			}
		}
    }

	/**
	 * Check permissions, does current user has access
	 *
	 * @return boolean
	 */
	 
    function checkPermissions() {
    	//If blog owner or super user
    	$current_user = wp_get_current_user();
		if(is_super_admin() || (get_bloginfo('admin_email') === $current_user->user_email)) {
			return true;
		}
		return false;
	}
	
	/**
	 * Filter cron schedule intervals settings
	 *
	 * Delete old schedules, to init new schedules add with updated interval
	 *
	 * @return new settings value
	 */

	function spawnOptionsChanged($newvalue, $oldvalue) {
		if($this->cron) {
			if($newvalue['courseRefreshTime'] != $oldvalue['courseRefreshTime']) {
				$this->cron->delete_schedule('refreshCoursesMeta');
			}
			if($newvalue['classmatesRefreshTime'] != $oldvalue['classmatesRefreshTime']) {
				$this->cron->delete_schedule('refreshClassmates');
			}
			if($newvalue['assignmentsRefreshTime'] != $oldvalue['assignmentsRefreshTime']) {
				$this->cron->delete_schedule('refreshAssignments');
			}
		}
		return $newvalue;
	}

	/**
	 * Save post hook handler
	 *
	 * If post saved and published, send request to teacher blog
	 */
	 
	function savePostMeta($post_id, $post = false) {
		if(defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        	return $post_id;
		}
		if(isSet($_POST['lepress-assignment-meta'])) {
			$meta_data = (object) $_POST['lepress-assignment-meta'];
			update_post_meta($post_id, '_lepress-assignment-meta', $meta_data);
			if($post->post_status == "publish" || $post->post_status == "private" || $post->post_status == "password") {
				//Send feedback finally to teacher blog
				$this->subscriptions->sendAnswerURL($meta_data, $post);
			}
		}
	}

	/**
	 * Add JavaScript files via INIT hook + custom columns hooks for comment form
	 */
	 
	function registerScripts() {
		//Comments custom column hook, for grade
		if($this->checkPermissions()) {
			add_filter('manage_edit-comments_columns', array(&$this, 'addCommentsCustomColumn'), 10);
			add_action('manage_comments_custom_column', array(&$this, 'manageCommentsCustomColumn'), 10, 2);
		}
    	wp_register_script('lepress-student-functions', lepress_http_abspath.'js/admin_functions-student.js');
    	wp_enqueue_script('lepress-student-functions');
    	add_settings_section('lepress-student-refresh', '', array(&$this, 'refreshIntervalHeader'), 'lepress');
    	add_settings_field('lepress-student-course-refresh', __("Courses", lepress_textdomain), array(&$this, 'addRefreshTimeField'), 'lepress', 'lepress-student-refresh', 'courseRefreshTime');
    	add_settings_field('lepress-student-classmates-refresh', __("Classmates", lepress_textdomain), array(&$this, 'addRefreshTimeField'), 'lepress', 'lepress-student-refresh', 'classmatesRefreshTime');
    	add_settings_field('lepress-student-assignments-refresh', __("Assignments", lepress_textdomain), array(&$this, 'addRefreshTimeField'), 'lepress', 'lepress-student-refresh', 'assignmentsRefreshTime');
    	
	}
	
	/**
	 * Settings refresh interval sections header
	 */
	 
	function refreshIntervalHeader() {
		echo "<h3>".__('Adjust cache update interval (student)', lepress_textdomain)."</h3>";
	}

	/**
	 * Print interval dropdown menus on settings page
	 */
	 
	function addRefreshTimeField($what) {
		$current_value = $this->get_option($what);
		$select_options = array(1 => __("every minute", lepress_textdomain),
												2 => __("every 2 minutes", lepress_textdomain),
												5 => __("every 5 minutes", lepress_textdomain),
												10 => __("every 10 minutes", lepress_textdomain),
												15 => __("every 15 minutes", lepress_textdomain),
												30 => __("every 30 minutes", lepress_textdomain),
												60 => __("every hour", lepress_textdomain),
												1440 => __("every day", lepress_textdomain));

		echo '<select name="lepress-settings['.$what.']">';
		foreach($select_options as $value => $option_name) {
			if($current_value == $value) {
				echo '<option value="'.$value.'" selected="selected">'.$option_name.'</option>';
			} else {
				echo '<option value="'.$value.'">'.$option_name.'</option>';
			}
		}
		echo '</select>';
		switch($what) {
			case 'courseRefreshTime':
				$msg = __('How often to update course metadata info from teachers blogs ? This field is <b>recommended</b> to set greater or equal to 1 hour.', lepress_textdomain);
				break;
			case 'classmatesRefreshTime';
				$msg = __('How often to update classmates info from teachers blogs ? This field is <b>recommended</b> to set greater or equal to 15 minutes.', lepress_textdomain);
				break;
			case 'assignmentsRefreshTime':
				$msg = __('How often to update list of assignments from teachers blogs ? This field is <b>recommended</b> to set less than 15 minutes.', lepress_textdomain);
				break;
		}
		echo ' <span class="description">'.$msg.'</span>';
	}

	/**
	 * Add post metabox to new post page
	 *
	 * Metabox is added only if assignment_metadata or $_GET['p'] 
	 */
	 
	function addPostMetaboxes() {
		$assignment_meta = get_post_meta($_GET['post'], '_lepress-assignment-meta', true);
		if(isSet($_GET['p']) || $assignment_meta) {
			add_meta_box('lepress-submission-metabox', __('Submission', lepress_textdomain),  array(&$this, 'fillAssignmentMetabox'), 'post', 'side', 'high');
		}
	}

	/**
	 * Fill previously added metabox
	 */
	 
	function fillAssignmentMetabox($post) {
		if(!isSet($_GET['p'])) {
			$assignment_meta = get_post_meta($post->ID, '_lepress-assignment-meta', true);
			$assignment_id = $assignment_meta->post_id;
			$course_id = $assignment_meta->course_id;
		} else {
			$assignment_id = $_GET['p'];
			$course_id = $_GET['c'];
		}
		$assignment = $this->subscriptions->getAssignmentByID($assignment_id, $course_id);
		//If assignment found, proceed
		if($assignment) {
			$post_id = $this->subscriptions->getAssignmentStatus($assignment_id, true);
			if(!$post_id || $post_id == $post->ID) {
				echo '<input type="hidden" name="lepress-assignment-meta[post_id]" value="'.$assignment_id.'" />';
				echo '<input type="hidden" name="lepress-assignment-meta[course_id]" value="'.$course_id.'" />';
				echo '<div class="misc-pub-section" style="margin-top: -6px">'.__('Assignment', lepress_textdomain).': <a href="'.$assignment->url.'" target="_blank" id="lepress-assignment-title">'.$assignment->title.'</a></div>';
				echo '<div class="misc-pub-section">'.__('Start date', lepress_textdomain).': <b>'.$assignment->start_date.'</b></div>';
				echo '<div class="misc-pub-section">'.__('End date', lepress_textdomain).':  <b>'.$assignment->end_date.'</b></div>';
				echo '<div class="misc-pub-section">'.__('Excerpt', lepress_textdomain).':  <b>'.$this->mb_cutstr((empty($assignment->excerpt) ? $assignment->content : $assignment->excerpt)).'</b></div>';
				echo '<div id="lepress-assignment-title" style="display:none;">'.$assignment->title.'</div>';
				echo '<div id="lepress-assignment-content" style="display:none;"><p>'.nl2br(htmlspecialchars_decode($assignment->content, ENT_COMPAT)).'</p></div>';
				echo '<div class="misc-pub-section" style="text-align:right;"><a href="#" onclick="return expandAssignment(this)">'.__('Expand', lepress_textdomain).'</a></div>';
				echo '<div class="misc-pub-section">'.__('Your submission will not be sent until you <b>publish</b> your post.', lepress_textdomain).'</div>';
				echo '<div class="misc-pub-section misc-pub-section-last"><input type="checkbox" '.($post->post_status == "private" ? 'checked="checked"': '').' onclick="setPostVisibility(this, \''.__('Public').'\', \''.__('Private').'\')"> '.__('Hide post from other course members', lepress_textdomain).'</div>';
			} else {
				echo '<div class="misc-pub-section misc-pub-section-last">'.__('You have already answered this assignment!', lepress_textdomain).' <br/><a href="'.add_query_arg(array('post' => $post_id, 'action' => 'edit'), admin_url().'post.php').'">'.__('Edit previous post', lepress_textdomain).'</a></div>';
			}
		} else {
			//Assignment not found
			echo '<div class="misc-pub-section misc-pub-section-last">'.__('Could not load assignment data', lepress_textdomain).'</div>';
		}
	}

	/**
	 * Add submenus on the left LePress section
	 */
	 
    function addSubMenus() {
    	if($this->checkPermissions()) {
		   	$first_item_parent = $this->isTeacherFeatures() ? 'lepress-my-subscriptions' : 'lepress';
		   	add_submenu_page('lepress', __("Manage my subscriptions", lepress_textdomain),__(" My subscriptions", lepress_textdomain).'<span id="lepress-student-subs-count"></span>',3, $first_item_parent,array(&$this, 'getSubMenuPageContent' ));
		   	add_submenu_page('lepress', __("Manage assignments", lepress_textdomain),__("Assignments", lepress_textdomain).'<span id="lepress-student-assignments-count"></span>',3, 'lepress-assignments',array(&$this, 'getSubMenuPageContent' ));
		   	//add_submenu_page('lepress', __("Manage groups", lepress_textdomain),__("My groups", lepress_textdomain),3, 'lepress-groups',array(&$this, 'getSubMenuPageContent' ));
    	}
    }

	/**
	 * Filter submenu calls
	 *
	 * Show page according to $_GET['page'] value
	 */
	 
    function getSubMenuPageContent() {
    	if(isSet($_GET['page'])) {
    		switch($_GET['page']) {
    			case 'lepress-my-subscriptions':
    			case 'lepress':
    				require_once('student_include/subscriptions.php');
    				break;
    			case 'lepress-assignments':
    				require_once('student_include/assignments.php');
    				break;
    			case 'lepress-groups':
    				require_once('student_include/my-groups.php');
    				break;
    			default:
    				echo "tere";
    				break;
    		}
    	}
    }
}

?>