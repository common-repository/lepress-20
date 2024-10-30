<?php

require_once('teacher_include/class-course-meta.php');

/**
 * LePress Teacher role class 
 */

class LePressTeacherRole extends LePress {

	//Init punch of hooks and filters
    function __construct() {
    	$this->createDatabaseStructure();

    	//And now the rest
		add_action('category_add_form_fields', array(&$this, 'addExtraCategoryFields'));
		add_action('category_edit_form_fields', array(&$this, 'addExtraCategoryFields_editForm'));

		add_action('created_category', array(&$this, 'saveCategoryMeta'), 10, 2);
		add_action('edited_category', array(&$this, 'saveCategoryMeta'), 10, 2);
		add_action('delete_category', array(&$this, 'deleteCategoryMeta'), 10);

		add_filter('manage_edit-category_columns', array(&$this, 'addCategoryCustomColumn'), 10);
		add_filter('manage_category_custom_column', array(&$this, 'manageCategoryCustomColumn'), 10, 3);

		add_action('edit_user_profile', array(&$this, 'addProfileFields'));
		add_action('show_user_profile', array(&$this, 'addProfileFields'));

		add_action( 'personal_options_update', array(&$this, 'saveExtraProfileFields'));
		add_action( 'edit_user_profile_update', array(&$this, 'saveExtraProfileFields'));

		add_action('add_meta_boxes', array(&$this, 'addPostMetaboxes'));

		add_action('save_post', array(&$this, 'savePostMeta'), 1, 2);

		add_action('admin_init', array(&$this, 'registerScripts'));
		add_action('admin_init', array(&$this, 'downloadTemplate'));
		add_action('admin_head', array(&$this, 'addHeadJavascriptVars'));

    	add_action('admin_menu', array(&$this, 'createOptionsMenus'));

    	//Add LePress Teacher role
        add_role('lepress_teacher', __('LePress Teacher', lepress_textdomain), get_role('editor')->capabilities);
    	if($role = & get_role('lepress_teacher')) {
    		$role->add_cap('manage-lepress');
    	}

    	//Add user_deleted action, needed because foreign key can not be used
    	add_action('deleted_user', array(&$this, 'wp_deleted_user'));

    	//Ajax load awaiting count triggers and hooks
    	add_filter('query_vars', array(&$this, 'awaiting_add_trigger'));
		add_action('template_redirect', array(&$this,'awaiting_trigger_check'));
		add_filter('comment_row_actions', array(&$this, 'filterCommentRows'),10, 2);
		
		if(is_multisite()) {
			//Add comment_form actions and filters for direct submission of assignment via comment form
			add_action('comment_form_logged_in_after', array(&$this, 'commentFormFields'));
		}
    }
    
    /**
     * Template download handler
     */
     
    function downloadTemplate() {
    	if(isSet($_GET['dl_tpl'])) {
    		$course_meta = new CourseMeta(false);
    		$tpl_id = intval($_GET['dl_tpl']);
    		$file_content = false;
    		if($tpl_id > 0) {
    			$tpl = $course_meta->getTemplateByID($tpl_id);
    			if($tpl) {
					$xml = simplexml_load_string($tpl->content);
					$file_content = $xml->asXML();
    			}
    		} elseif($tpl_id == 'single_file') {
    			$file_content = $course_meta->getTemplatesAsSingleFile();
    		}
    		
    		if($file_content) {
    			header("Content-Description: File Transfer");
				header('Content-disposition: attachment; filename='.($tpl ? $tpl->name : date('Y-m-d')).' - lepress_template.xml');
				header('Content-type: text/xml');
				echo $file_content;
    		} else {
    			_e('Something went terribly wrong, could not fetch template, try again...', lepress_textdomain);
    		}
    		exit;
    	}
    }
    
    /** Return WPDB prefix */
    
    function get_wpdb_prefix() {
    	global $wpdb;
    	if(is_multisite() && $wpdb->blogid > 1) {
    		$wpdb_prefix = $wpdb->base_prefix.$wpdb->blogid.'_'.get_class($this).'_';
    	} else {
    		$wpdb_prefix = $wpdb->prefix.get_class($this).'_';
    	}
    	return $wpdb_prefix;
    }
    
    /** Add extra fields for comment form, if logged in and is student on the course */
    
    function commentFormFields() {
    	global $LePress;
    	$current_user = wp_get_current_user();
    	/* Return false if not multisite OR current_user is blog owner */
    	if(!is_multisite() || $LePress->getBlogOwnerUser()->ID == $current_user->ID) { return false; }
    	
    	/* Otherwise carry on */
    	$post = get_queried_object();
    	$cat_ID_found = 0;
    	$post_cat_url = '';
    	if($post && $post->post_type == 'post') {
    		$cats = wp_get_post_categories($post->ID);
    		foreach($cats as $cat_ID) {
    			$course_meta = new CourseMeta($cat_ID);
    			if($course_meta->getIsCourse()) {
    				$cat_ID_found = $cat_ID;
    				$post_cat_url = get_category_link($cat_ID_found);
    				break;
    			}
    		}
    	}
    	
    	/* Category ID found, let's move forward */
    	if($cat_ID_found > 0) {
    		if($course_meta->getStudentSubscriptionByEmail($current_user->user_email)) {
    			$comment = $LePress->fetchComment($post->ID, $current_user->user_email, 'LePressStudent');
				$meta_data = get_post_meta($post->ID, '_lepress-student-'.md5($post->ID.$current_user->user_email), true);
				if(!$comment) {
					echo '<p><strong>LePress</strong></p>';
					require_once('student_include/class-my_subscriptions.php');
					$user_blogs = get_blogs_of_user($current_user->ID);
					global $blog_id;
					$current_blog_id = $blog_id;
					$found_blog = false;
					//Iterate through all the user's blogs
					foreach($user_blogs as $blog) {
						switch_to_blog($blog->userblog_id);
						if($LePress->isStudentFeatures()) {
							//Let's check, if this is the one blog, where student is subscribed also
							$subscriptions = new StudentSubscriptions();
							$course_found = $subscriptions->getActiveCourseByURL($post_cat_url);
							//We have found the blog with specified course, break the foreach cycle
							if($course_found) {
								$found_blog = $blog;
								break;
							}
						}
					}
					//Revert to current blog
					switch_to_blog($current_blog_id);
					if($found_blog) {
						echo '<p>'.sprintf(__('You are trying to post a comment into LePress assignment post. Please note that you still haven\'t submitted your work. If you like to submit your work, please submit it %s; you will be redirected to your LePress dashboard.', lepress_textdomain), '<a href="'.add_query_arg(array('p' => $post->ID, 'c' => $course_found->ID), $found_blog->siteurl.'/wp-admin/post-new.php').'">'.__('clicking here', lepress_textdomain).'</a>', '<a href="'.$found_blog->siteurl.'">'.$found_blog->blogname.'</a>').'</p>';
						echo '<p>'.sprintf(__('Assignment deadline: %s', lepress_textdomain), $LePress->date_i18n_lepress(strtotime(get_post_meta($post->ID, '_lepress-assignment-end-date', true)))).'</p>';
					}
				}
			}
		}
    }
    
    /* Filter comment rows, add custom link "Grade & Feedback" */
    
    function filterCommentRows($actions = array(), $comment) {
    	if(get_comment_meta($comment->comment_ID, 'lepress-read', true) || get_comment_meta($comment->comment_ID, 'lepress-feedback-given', true)) {
			unset($actions['reply']);
			$actions_out = array();
			foreach($actions as $action => $value) {
				$actions_out[$action] = $value;
				if($action == 'unapprove') {
					$post_cats = wp_get_post_categories($comment->comment_post_ID);
					$cat_ID = $post_cats[0];
					$course_meta = new CourseMeta($cat_ID);
					if($course_meta->getIsCourse()) {
						$sub = $course_meta->getStudentSubscriptionByEmail($comment->comment_author_email);
						$actions_out['feedback'] = '<a href="'.add_query_arg(array('page' => 'lepress-classbook', 'p' => $comment->comment_post_ID, 'c' => $cat_ID, 's' => $sub->id), admin_url().'admin.php').'">'.__('Grade & Feedback', lepress_textdomain).'</a>';
					}
				}
			}
			return $actions_out;
    	}
    	return $actions;
    }
    
    /* Filter comments, needed for LePress Teacher role, if teacher not marked as teacher for course, hide comments */
    
    function filterComments($comments, $b) {
    	if(is_super_admin()) {
    		return $comments;
    	}
    	$comments_out = array();
    	foreach($comments as $comment) {
    		$post_id = $comment->comment_post_ID;
    		$cats = get_the_category($post_id);
    		
    		global $current_user;
    		get_currentuserinfo();
    		foreach($cats as $cat) {
    			$course_meta = new CourseMeta($cat->cat_ID, false);
    			if($course_meta->isTeacher($current_user->ID)) {
    				$comments_out[] = $comment;
    			}
    		}
    	}
    	return $comments_out;
    }
    
    /* Filter categories for LePress teacher role */
    
    function excludeCategories($query) {
    	if(is_super_admin()) {
    		return $query;
    	}
    	//Not superadmin, let's check with who are we dealing with
    	if($query->is_admin) {
    		if($query->get('post_type') == 'post') {
				$course_meta = new CourseMeta(false, false);
				global $current_user;
				get_currentuserinfo();
				$cat_ids_allowed = array();
				foreach($course_meta->getTeacherCats($current_user->ID) as $cat) {
					$cat_ids_allowed[] = $cat->cat_id;
				}
				if(!empty($cat_ids_allowed)) {
					$query->set('cat', implode(',', $cat_ids_allowed));
				}
    		}
    	}
    	return $query;
    }
    
    //Very simple check for allowed courses for user in LePress Teacher role
    function checkIsUserTeacher($cats, $b) {
    	if(is_super_admin()) {
    		return $cats;
    	}
    	//Not superadmin, let's check with who are we dealing with
    	global $current_user;
    	$cats_out = array();
    	get_currentuserinfo();
    	if(is_array($cats)) {
			foreach($cats as $cat) {
				$course_meta = new CourseMeta($cat->term_id);
				if($course_meta->isTeacher($current_user->ID)) {
					$cats_out[] = $cat;
				}
			}
			return $cats_out;
    	} else {
    		$course_meta = new CourseMeta($cats->term_id);
    		if($course_meta->isTeacher($current_user->ID)) {
    			return $cats;
    		}
    	}
    	return false;
    }

	/**
	 * Uninstall method
	 * Removes everything if wipe_out flag set
	 */
	 
	function initUninstall($wipe_out = false) {
		$cats = get_categories('hide_empty=0');
		foreach($cats as $cat) {
			$course_meta = new CourseMeta($cat->cat_ID);
			if($course_meta->getIsCourse()) {
				$course_meta->unsubcribeAllStudents();
			}
		}
		if($wipe_out) {
			$this->createDatabaseStructure($wipe_out);
		}
	}
	
	/**
	 * Create database structure or wipe it if flag set
	 */
	
    function createDatabaseStructure($wipe_out = false) {
		global $wpdb;
		$tables = array($this->get_wpdb_prefix().'course_meta' =>
									'CREATE TABLE {table_name} (
  									`cat_id` int(11) NOT NULL,
  									`is_course` tinyint(1) DEFAULT 0,
  									`access_type` tinyint(1) DEFAULT 0,
  									`advertise` tinyint(1) DEFAULT 0,
  									`is_closed` tinyint(1) DEFAULT 0,
  									PRIMARY KEY (`cat_id`), UNIQUE(`cat_id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8;',
  								$this->get_wpdb_prefix().'templates' =>
									'CREATE TABLE {table_name} (
  									`id` int(11) NOT NULL AUTO_INCREMENT,
  									`name` VARCHAR(250),
  									`content` TEXT,
  									`date` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  									PRIMARY KEY (`id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8;',
  								$this->get_wpdb_prefix().'students' =>
  									'CREATE TABLE {table_name} (
  									`id` int(11) NOT NULL AUTO_INCREMENT,
  									`first_name` varchar(50) DEFAULT NULL,
  									`last_name` varchar(50) DEFAULT NULL,
  									`blog_url` text,
  									`email` varchar(250) DEFAULT NULL,
  									PRIMARY KEY (`id`), UNIQUE(`email`)) ENGINE=InnoDB DEFAULT CHARSET=utf8;',
  								$this->get_wpdb_prefix().'invitations' => 'CREATE TABLE {table_name} (
  									`ID` int(11) NOT NULL AUTO_INCREMENT,
  									`cat_id` int(11) NOT NULL,
  									`invite_key` varchar(10) DEFAULT NULL,
  									`expire_time` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  									`new_student_email` VARCHAR(250) NOT NULL,
  									PRIMARY KEY (`ID`),
  									FOREIGN KEY (`cat_id`)
  									REFERENCES `'.$this->get_wpdb_prefix().'course_meta` (`cat_id`) ON DELETE CASCADE)
  									ENGINE=InnoDB DEFAULT CHARSET=utf8;',
  								$this->get_wpdb_prefix().'subscriptions' =>
  									'CREATE TABLE {table_name} (
								  `id` int(11) NOT NULL AUTO_INCREMENT,
								  `cat_id` int(11) NOT NULL,
								  `student_id` int(11) NOT NULL,
								  `status` tinyint(1) DEFAULT 0,
								  `accept_key` char(10) DEFAULT NULL,
								  `message` text,
								  PRIMARY KEY (`id`),
								  UNIQUE KEY(`cat_id`,`student_id`),
								  FOREIGN KEY (`cat_id`) REFERENCES `'.$this->get_wpdb_prefix().'course_meta` (`cat_id`) ON DELETE CASCADE,
								  FOREIGN KEY (`student_id`) REFERENCES `'.$this->get_wpdb_prefix().'students` (`id`) ON DELETE CASCADE
								) ENGINE=InnoDB DEFAULT CHARSET=utf8;',
								$this->get_wpdb_prefix().'teachers' => 'CREATE TABLE `{table_name}` (
								 `id` int(11) NOT NULL AUTO_INCREMENT,
								 `cat_id` int(11) NOT NULL,
								 `wp_user_id` bigint(20) unsigned NOT NULL,
								 PRIMARY KEY (`id`),
								 UNIQUE KEY `cat_id` (`cat_id`,`wp_user_id`),
								 KEY `wp_user_id` (`wp_user_id`),
								 FOREIGN KEY (`cat_id`) REFERENCES `'.$this->get_wpdb_prefix().'course_meta` (`cat_id`) ON DELETE CASCADE
								) ENGINE=InnoDB DEFAULT CHARSET=utf8;',
								$this->get_wpdb_prefix().'student_groups' => 'CREATE TABLE `{table_name}` (
								  `id` int(11) NOT NULL AUTO_INCREMENT,
								  `student_id` int(11) NOT NULL,
								  `group_name` varchar(250) DEFAULT NULL,
								  `group_key` char(32) DEFAULT NULL,
								  `type` tinyint(1) DEFAULT 0,
								  PRIMARY KEY (`id`),
								  UNIQUE KEY (`student_id`,`group_key`),
								  FOREIGN KEY (`student_id`) REFERENCES `'.$this->get_wpdb_prefix().'students` (`id`)
								) ENGINE=InnoDB DEFAULT CHARSET=utf8;');
		if($wipe_out) {
			$wpdb->query('SET FOREIGN_KEY_CHECKS = 0;');
		}
		foreach($tables as $table_name => $query) {
			//IF table doesn't exist, create it
			if(!$wipe_out) {
				if($wpdb->get_var('SHOW TABLES LIKE "'.$table_name.'"') != $table_name) {
					require_once(ABSPATH.'wp-admin/includes/upgrade.php');
					$query = str_replace('{table_name}', $table_name, $query);
					dbDelta($query);
					//echo $query;
				} else {
					//Update course_meta table and add extra column, if doesnt exist
					//TODO This will be removed in public release
					if($table_name == $this->get_wpdb_prefix().'course_meta') {
						if(!$wpdb->get_col('SELECT advertise FROM '.$table_name)) {
							$wpdb->query('ALTER TABLE '.$table_name.' ADD advertise TINYINT(1) DEFAULT 0');
						}
						if(!$wpdb->get_col('SELECT is_closed FROM '.$table_name)) {
							$wpdb->query('ALTER TABLE '.$table_name.' ADD is_closed TINYINT(1) DEFAULT 0');
						}
					}
				}
			} else {
				$wpdb->query('DROP TABLE '.$table_name);
			}
		}
		//Revert foreign key check to 1
		if($wipe_out) {
			$wpdb->query('SET FOREIGN_KEY_CHECKS = 1;');
		}
	}
	
	/**
	 * If Wordpress user deleted, check if user was teacher, if true, remove
	 */

	function wp_deleted_user($wp_user_id) {
		global $wpdb;
		if($wpdb->get_var('SELECT wp_user_id  FROM '.$this->get_wpdb_prefix().'teachers WHERE wp_user_id = '.esc_sql($wp_user_id))) {
			$wpdb->query('DELETE FROM '.$this->get_wpdb_prefix().'teachers WHERE wp_user_id = '.esc_sql($wp_user_id));
		}
	}

	/* Get role, probably not used at all */
	
	function getRole() {
		return 1;
	}

	/**
	 * Check permissions for user
	 */
	 
	function checkPermissions() {
		$current_user = wp_get_current_user();
		if(current_user_can('manage-lepress') || is_super_admin() || (get_bloginfo('admin_email') === $current_user->user_email)) {
			return true;
		}
		return false;
	}

	/**
	 * Create options menu
	 */
	 
	function createOptionsMenus() {
		if($this->checkPermissions()) {
			add_submenu_page('lepress', __("LePress Students Roster", lepress_textdomain),__("Students Roster", lepress_textdomain).'<span id="lepress-roster-count"></span>',3, 'lepress', array(&$this, 'getSubMenuPageContent' ));
			add_submenu_page('lepress', __("LePress Classbook", lepress_textdomain),__("Classbook", lepress_textdomain).'<span id="lepress-classbook-count"></span>',3, 'lepress-classbook', array(&$this, 'getSubMenuPageContent' ));
			add_submenu_page('lepress', __(false, lepress_textdomain),__("Categories / Courses", lepress_textdomain),3,'edit-tags.php?taxonomy=category');
			add_submenu_page('lepress', __('Import / Export', lepress_textdomain),__("Import / Export", lepress_textdomain),3,'lepress-import-export', array(&$this, 'getSubMenuPageContent' ));
		}
	}

	/**
	 * Awaiting count trigger, called by AJAX
	 */
	 
	function awaiting_trigger_check() {
		if(intval(get_query_var('lepress-teacher-awaiting')) == 1) {
			//WP default header is 404, have to override it
			header("HTTP/1.0 200 OK");
			if(isSet($_GET['w'])) {
				echo $this->getAwaitingBubble($_GET['w']);
			}
			exit;
		}
   	}

	/**
	 * Add new trigger to query vars
	 */
	 
	function awaiting_add_trigger($vars) {
		$vars[] = 'lepress-teacher-awaiting';
    	return $vars;
	}

	/**
	 * Get the actual awaiting bubble and count
	 */
	 
	function getAwaitingBubble($what) {
		switch($what) {
			case 'subscriptions':
				$course_meta = new CourseMeta(false);
				$count = $course_meta->getAllPendingSubscriptionsCount();
				break;
			case 'classbook':
				$course_meta = new CourseMeta(false);
				$count = $course_meta->getAllUngradedAssignmentCount();
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
	
	/**
	 * Get unread comments count
	 */
	
	function getUnreadCommentsCount() {
		global $LePress;
		$count = 0;
		foreach(get_comments() as $comment) {
			$meta = get_comment_meta($comment->comment_ID, 'lepress-read', true);
			if(($meta && $comment->comment_agent == 'LePressStudent') || wp_get_comment_status($comment->comment_ID) == 'unapproved') {
				$count++;
			}
			//This is used, when both roles enabled - and teacher JS function makes a call
			if($LePress->isStudentFeatures() && $meta && $comment->comment_agent == 'LePressTeacher') {
				$count++;
			}
		}
		return $count;
	}

	/**
	 * Add some JavaScript to the head
	 */
	 
	function addHeadJavascriptVars() {
		$page = end(explode('/', parse_url($_SERVER["REQUEST_URI"], PHP_URL_PATH)));
		echo '<script type="text/javascript">'."\n";
		echo 'var lepress_teacher_awaiting_url = "'.add_query_arg(array('lepress-teacher-awaiting' => 1), get_bloginfo('siteurl')).'";'."\n";
		$lang_vars = array('one_item' => __('item'),
							'two_item' => __('items'),
							'date_greater' => __("Assignment ending date must be greater than start date", lepress_textdomain));
		$role = 'no-filtering';
		if(($page == 'edit.php' && get_query_var('post_type') == 'post') || ($page == "edit-tags.php" && $_GET['taxonomy'] == 'category') || $page == "edit-comments.php") {
			$role = current_user_can('manage-lepress') && !is_super_admin() ? 'lepress-teacher' : 'super-admin';
		}
		$lepress_env = array('role' => $role);
		echo 'var lepress_env = '.json_encode($lepress_env).';'."\n";
		echo 'var lepress_lang_vars = '.json_encode($lang_vars).';'."\n";
		echo '</script>'."\n";
		//IF role lepress-teacher, override span css classes
		if($role != 'no-filtering' && !is_super_admin()) {
			echo '<style type="text/css">'."\n";
			echo 'span.displaying-num, span.count { display:none; }';
			echo '</style>';
		}
	}

	/**
	 * Register new JavaScripts in INIT hook
	 *
	 * Also add some more Wordpress hooks and filters
	 */
	 
    function registerScripts() {
    	global $current_user;
    	get_currentuserinfo();
    	//If we are not in our own blog, this means we have been added as user someone else blog.
    	if(get_bloginfo('admin_email') != $current_user->user_email && current_user_can('manage-lepress')) {
			//Add action for filtering posts query for display LePress Teacher role posts
			add_action( 'pre_get_posts', array(&$this, 'excludeCategories'));
			//Filter for displaying categories for LePress Teacher role
			add_filter('get_terms', array(&$this,'checkIsUserTeacher'), 10, 2);
			add_filter('get_term', array(&$this,'checkIsUserTeacher'), 10, 2);
			add_filter('the_comments', array(&$this,'filterComments'), 10, 2);
		}
    	//Registersome admin scripts
        //TODO It might be a good idea to start using a more recent version
        //that corresponds to the jQuery UI bundled with WordPress.
    	wp_register_style( 'jquery-ui-date-picker', lepress_http_abspath.'css/custom-	theme/jquery-ui-1.8.13.custom.css');
    	wp_enqueue_style( 'jquery-ui-date-picker' );

    	wp_register_style( 'jquery-ui-override-date-picker', lepress_http_abspath.'css/default.css');
    	wp_enqueue_style( 'jquery-ui-override-date-picker' );

    	wp_register_script('lepress-teacher-functions', lepress_http_abspath.'js/admin_functions-teacher.js');
    	wp_enqueue_script('lepress-teacher-functions');
		
		//First let's see what config.php tells
		if(defined(WPLANG)) {
    		$wp_lang = WPLANG;
    	}
    	if(empty($wp_lang)) {
    		$wp_lang = get_option('WPLANG');
    	} else {
    		//If wp-config WPLANG and option WPLANG are not equal
    		//prefer option value as language setting
    		if($wp_lang != get_option('WPLANG')) {
    			$wp_lang = get_option('WPLANG');
    		}
    	}
		$wp_lang = !empty($wp_lang) ? $wp_lang : 'en';
		//Load datepicker language, based on WP language set in WP config.
		if($wp_lang != 'en') {
			wp_register_script( 'jquery-ui-date-picker-lang', lepress_http_abspath.'js/datepicker_i18n/jquery.ui.datepicker-'.$wp_lang.'.js');
			wp_enqueue_script( 'jquery-ui-date-picker-lang' );
    	}

        //TODO Need to remove the custom jQuery UI from source.
    	//wp_register_script( 'jquery-ui-core-custom', lepress_http_abspath.'js/jquery-ui-1.8.13.custom.min.js');
    	//wp_enqueue_script( 'jquery-ui-core-custom' );
        //Default jQuery ui with datepicker module is used.
        wp_enqueue_script('jquery-ui-datepicker');

    	wp_register_script( 'jquery-ui-date-picker', lepress_http_abspath.'js/date_picker.js');
    	wp_enqueue_script( 'jquery-ui-date-picker' );
    }

	/**
	 * Get sub page contents
	 */
	 
    function getSubMenuPageContent() {
    	if(isSet($_GET['page'])) {
    		switch($_GET['page']) {
    			case 'lepress':
    			case 'lepress-student-roster':
    				require_once('teacher_include/subscriptions.php');
    				break;
    			case 'lepress-classbook':
    				require_once('teacher_include/classbook.php');
    				break;
    			case 'lepress-import-export':
    				require_once('teacher_include/import_export.php');
    				break;
    		}
    	}
    }

	/**
	 * Add custom columns to categories table
	 */

	function addCategoryCustomColumn($cols) {
		if($this->checkPermissions()) {
			$cols["isCourse"] = __("Course", lepress_textdomain);
			$cols["accessType"] = __("Enrollment", lepress_textdomain);
			$cols["subscriptions"] = __("Subscriptions Active/Pending", lepress_textdomain);
		}
		return $cols;
	}
	
	/**
	 * Filter categories table columns, fill previously added columns with data
	 */

	function manageCategoryCustomColumn($empty, $col, $cat_id) {
		if($this->checkPermissions()) {
			$course_meta = new CourseMeta($cat_id);
			if(!$course_meta instanceOf CourseMeta) { return false; }
			switch($col) {
				case 'isCourse':
					if($course_meta->getIsClosed()) {
						_e('Closed', lepress_textdomain);
					} else {
						echo $course_meta->getIsCourse() ? __('Yes', lepress_textdomain) : 'No';
					}
					break;
				case 'subscriptions':
					if($course_meta->getIsCourse()) {
						echo $course_meta->getApprovedCount()." / ".$course_meta->getPendingCount();
					}
					break;
				case 'accessType':
					if($course_meta->getIsCourse()) {
						echo $course_meta->getAccessType() == 1 ? __('Open', lepress_textdomain) : __('Moderated', lepress_textdomain);
					}
					break;
			}
		}
	}
	
	/**
	 * If category is deleted, remove metadata too
	 */

	function deleteCategoryMeta($cat_id) {
		$course_meta = new CourseMeta($cat_id);
		$course_meta->delete();
	}

	/**
	 * Save/update category metadata
	 */
	 
	function saveCategoryMeta($cat_id, $tt_id) {
		if($this->checkPermissions()) {
			$course_meta = new CourseMeta($cat_id);
			if(!$course_meta->hasSubscriptions()) {
				$course_meta->setIsCourse($_POST['lepress-course']);
			}
			$course_meta->setAccessType($_POST['lepress-course-open-access']);
			$course_meta->setTeachers($_POST['lepress-course-teachers']);
			//IF multisite, need to handle course advertisment data too
			if(is_multisite()) {
				$course_meta->setAdvertise($_POST['lepress-course-advertise']);
			}
			if(intval($_POST['lepress-course-locked']) == 1 && !$course_meta->getIsClosed()) {
				$course_meta->setIsClosed();
			}
		}
	}

	/**
	 * Add extra category form fields
	 */
	 
	function addExtraCategoryFields_editForm($tag) {
		$this->addExtraCategoryFields($tag, true);
	}
	
	/**
	 * Do the actual form fields print out
	 */

	function addExtraCategoryFields($tag, $edit_form = false) {
		if($this->checkPermissions()) {
			$form_row_title = __('This category is a course', lepress_textdomain);
			$form_row_desc = __('Is this category a course for students ?', lepress_textdomain);
			$form_row_title2 = __('Open access course', lepress_textdomain);
			$form_row_desc2 = __('Can participant subscribe to this course without teacher\'s verification ?', lepress_textdomain);
			$form_row_title3 = __('Course teachers', lepress_textdomain);
			$form_row_desc3 = __('Choose additional teachers for this course (Only current WordPress installation users).', lepress_textdomain);
			$form_row_title4 = __('Advertise this course', lepress_textdomain);
			$form_row_desc4 = __('Advertise this course on LePress Courses Sitewide widget ?', lepress_textdomain);
			$form_row_title5 = __('Close this course', lepress_textdomain);
			$form_row_desc5 = __('Close this course <b>CAUTION!</b> Cannot be undone! no changes can be made to course data!', lepress_textdomain);
			
			global $current_user;
			get_currentuserinfo();
			if(function_exists('get_users')) {
				$users = get_users(array('role' => 'lepress_teacher', 'exclude' => array($current_user->ID)));
			} else {
				$users = get_users_of_blog();
			}
			//Get also super admins, they are allowed to be teachers too
			if(is_super_admin()) {
				foreach(get_super_admins() as $super_user_login) {
					$user = get_user_by('login', $super_user_login);
					$users[] = $user;
				}
			}
			//If new category page
			if(!$edit_form) {
				echo '<div class="form-field lepress-field">';
				echo '<input type="checkbox" id="lepress-course" name="lepress-course" value="1"/>';
				echo '<label for="lepress-course">'.$form_row_title.'</label>';
				echo '<p>'.$form_row_desc.'</p>';
				echo '</div>';

				echo '<div class="form-field lepress-field">';
				echo '<input type="checkbox" id="lepress-course-open-access" name="lepress-course-open-access" value="1" />';
				echo '<label for="lepress-course-open-access">'.$form_row_title2.'</label>';
				echo '<p>'.$form_row_desc2.'</p>';
				echo '</div>';

				echo '<div style="margin:0 0 10px; padding: 8px;">';
				echo '<input type="hidden" value="'.$current_user->ID.'" name="lepress-course-teachers['.$current_user->ID.']" />';
				echo '<label><b>'.$form_row_title3.'</b></label>';
				foreach($users as $user) {
					if($user->ID != $current_user->ID) {
						$userdata = get_userdata($user->ID);
						echo '<input type="hidden" name="lepress-course-teachers['.$user->ID.']" value="0"/>';
						echo '<div style="margin-left: 4px;"><input type="checkbox" class="lepress-teacher" value="'.$user->ID.'" name="lepress-course-teachers['.$user->ID.']" /> '.(!empty($userdata->user_firstname) ? $userdata->user_firstname : $userdata->user_login).' '.$userdata->user_lastname.'</div>';
					}
				}
				//Found only one user - current user
				if(count($users) <= 1) {
					echo '<p><b>'.__('No LePress teachers found', lepress_textdomain).' <a href="'.admin_url().'user-new.php'.'">'.__('Add here', lepress_textdomain).'</a></b></p>';
				}
				echo '<p>'.$form_row_desc3.'</p>';
				echo '</div>';
				
				//IF multisite, add advertise checkbox
				if(is_multisite()) {
					echo '<div class="form-field lepress-field">';
					echo '<input type="checkbox" id="lepress-course-advertise" name="lepress-course-advertise" value="1" />';
					echo '<label for="lepress-course-advertise">'.$form_row_title4.'</label>';
					echo '<p>'.$form_row_desc4.'</p>';
					echo '</div>';
				}			
			} else { //If edit category page
				$course_meta = new CourseMeta($tag->term_id);
				echo '<tr class="form-field">';
				echo '<th scope="row" valign="top"><label for="lepress-course">'.$form_row_title.'</label></th>';
				echo '<td><input type="hidden" name="lepress-course" value="0"/>';
				echo '<input type="checkbox" class="lepress-edit-form-field" id="lepress-course" '.($course_meta->getIsCourse() ? 'checked=checked':'').' '.($course_meta->hasSubscriptions() ? 'disabled="disabled"' : '').' name="lepress-course" value="1"/><br />';
				echo '<span class="description">'.$form_row_desc.' '.__('You <b>cannot change</b> this, if course has <b>active subscriptions</b>', lepress_textdomain).'</span></td>';
				echo '</tr>';

				echo '<tr class="form-field">';
				echo '<th scope="row" valign="top"><label for="lepress-course-open-access">'.$form_row_title2.'</label></th>';
				echo '<td><input type="hidden" name="lepress-course-open-access" value="0" />';
				echo '<input type="checkbox" class="lepress-edit-form-field" id="lepress-course-open-access" '.($course_meta->getAccessType() ? 'checked=checked':'').' name="lepress-course-open-access" value="1"/><br />';
				echo '<span class="description">'.$form_row_desc2.'</span></td>';
				echo '</tr>';

				echo '<tr>';
				echo '<th scope="row" valign="top"><label for="lepress-course-open-access">'.$form_row_title3.'</label></th>';
				echo '<td>';
				foreach($users as $user) {
					if($user->ID != $current_user->ID) {
						$userdata = get_userdata($user->ID);
						$isTeacher = $course_meta->isTeacher($user->ID) ? 'checked="checked"' : '';
						echo '<input type="hidden" name="lepress-course-teachers['.$user->ID.']" value="0"/>';
						echo '<div><input class="lepress-edit-form-field" type="checkbox" value="'.$user->ID.'" '.$isTeacher.' name="lepress-course-teachers['.$user->ID.']" /> '.(!empty($userdata->user_firstname) ? $userdata->user_firstname : $userdata->user_login).' '.$userdata->user_lastname.'</div>';
					}
				}
				//Found only one user - current user
				if(count($users) <= 1) {
					echo '<p><b>'.__('No LePress teachers found', lepress_textdomain).' <a href="'.admin_url().'user-new.php'.'">'.__('Add here', lepress_textdomain).'</a></b></p>';
				}
				echo '<span class="description">'.$form_row_desc3.'</span></td>';
				echo '</tr>';
				
				//IF is multisite, add advertise checkbox
				if(is_multisite()) {
					echo '<tr class="form-field">';
					echo '<th scope="row" valign="top"><label for="lepress-course-open-access">'.$form_row_title4.'</label></th>';
					echo '<td><input type="hidden" name="lepress-course-advertise" value="0" />';
					echo '<input class="lepress-edit-form-field" type="checkbox" id="lepress-course-advertise" '.($course_meta->getAdvertise() ? 'checked=checked':'').' name="lepress-course-advertise" value="1"/><br />';
					echo '<span class="description">'.$form_row_desc4.'</span></td>';
					echo '</tr>';
				}
				
				if(!$course_meta->getIsClosed()) {
					echo '<tr class="form-field">';
					echo '<th scope="row" valign="top"><label for="lepress-course-locked">'.$form_row_title5.'</label></th>';
					echo '<td><input type="hidden" name="lepress-course-locked" value="0" />';
					echo '<input type="checkbox" class="lepress-edit-form-field" id="lepress-course-locked" '.($course_meta->getIsClosed() ? 'checked=checked':'').' name="lepress-course-locked" value="1"/><br />';
					echo '<span class="description">'.$form_row_desc5.'</span></td>';
					echo '</tr>';
				} else {
					echo '<tr class="form-field">';
					echo '<th scope="row" valign="top"><label for="lepress-course-locked">'.__('Export', lepress_textdomain).'</label></th>';
					echo '<td>';
					echo '<a href="">'.__('Export current state',lepress_textdomain).'</a><br />';
					echo '<a href="'.add_query_arg(array('page' => 'lepress-import-export', 'export_tpl' => $tag->term_id), admin_url().'admin.php').'">'.__('Export as template (all assignments without students related information)', lepress_textdomain).'</a><br />';
					echo '<span class="description">'.__('Export this course data. "Export current state" exports also classbook, "Export as template" exports assginments and creates a new template.', lepress_textdomain).'</span></td>';
					echo '</tr>';
				}
				
			}
		}
	}

	/**
	 * Add extra profile fields - organization
	 */
	 
	function addProfileFields($user) {
		if($this->checkPermissions()) {
			echo '<h3>LePress Teacher</h3>';
			echo '<table class="form-table">';
			echo '<tr>';
			echo	'<th><label for="lepress-organization">'.__('Organization', lepress_textdomain).'</label></th>';
			echo '<td><input type="text" name="lepress-organization" id="lepress-organization" value="'.esc_attr( get_the_author_meta( 'lepress-organization', $user->ID)).'" class="regular-text"/><br />';
			echo '<span class="description">'.__('Your organization - university, college, high school, primary school etc..', lepress_textdomain).'</span></td>';
			echo '</tr></table>';
		}
	}

	/**
	 * Handle extra profile fields saving
	 */
	 
	function saveExtraProfileFields($user_id) {
		if($this->checkPermissions()) {
			if ( !current_user_can( 'edit_user', $user_id ) ) { return false; }
			update_usermeta( $user_id, 'lepress-organization', $_POST['lepress-organization'] );
		}
	}
	
	/**
	 * Add new post page metaboxes
	 */

	function addPostMetaboxes() {
		$assignment_meta = get_post_meta($_GET['post'], '_lepress-assignment-meta', true);
		if($this->checkPermissions() && !isSet($_GET['p']) && !$assignment_meta) {
			add_meta_box('lepress-assignment-metabox', 'Assignment',  array(&$this, 'fillAssignmentMetabox'), 'post', 'side', 'high');
		}
	}
	
	/**
	 * Fill previously added metaboxes
	 */
	 
	function fillAssignmentMetabox($post) {
		$start_date = strtotime(get_post_meta($post->ID, '_lepress-assignment-start-date', true));
		$end_date = strtotime(get_post_meta($post->ID, '_lepress-assignment-end-date', true));
		if(!$start_date) {
			$start_date = time();
		}
		if(!$end_date) {
			$end_date = strtotime('+1 week');
		}
		echo '<div class="misc-pub-section">'.__('Start date', lepress_textdomain).': <input type="text" id="lepress-assignment-start-date" name="lepress-assignment-start-date" value="'.$start_date.'"/></div>';
		echo '<div class="misc-pub-section">'.__('End date', lepress_textdomain).': <input type="text" id="lepress-assignment-end-date" style="margin-left: 6px" name="lepress-assignment-end-date" value="'.$end_date.'"/></div>';
		$is_assignment = get_post_meta($post->ID, '_is-lepress-assignment', true);
		$error_msg = get_post_meta($post->ID, '_lepress-assignment-error', true);
		echo '<div class="misc-pub-section '.(!$error_msg ? 'misc-pub-section-last' : '').'">'.__('This post is an assignment', lepress_textdomain).' <input type="hidden" name="is-lepress-assignment" value="0"/><input type="checkbox" '.($is_assignment ? 'checked="checked"' : '').' id="is-lepress-assignment" style="margin-left: 6px" name="is-lepress-assignment" value="1"/></div>';

		$cat_ids = '';
		//Store category ids for JavaScript select box handler
		foreach(get_categories('hide_empty=0') as $cat) {
			$course_meta = new CourseMeta($cat->cat_ID);
			if($course_meta->getIsCourse() && !$course_meta->getIsClosed()) {
				$cat_ids .= $cat->cat_ID.',';
			}
		}
		$cat_ids = trim($cat_ids, ',');
		echo '<input type="hidden" id="cat_ids_course" value="'.$cat_ids.'" />';
		if($error_msg) {
			echo '<div  class="misc-pub-section misc-pub-section-last" style="font-weight: bold; color: red;" id="lepress-meta-error">'.$error_msg.'</div>';
		}
	}

	/**
	 * Save post metadata
	 *
	 * Handles saving post metadata, is assignment, check is dates are correct and only one category is selected
	 */
	 
	function savePostMeta($post_id, $post = false) {
		if(defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        	return $post_id;
		}
		if($this->checkPermissions()) {
			$update_post_cats = false;
			if(!isSet($_POST['course_id'])) {
				$post_cats = wp_get_post_categories($post_id);
			} else {
				$post_cats = array(intval($this->safeStringInput($_POST['course_id'], true)));
				$update_post_cats = true;
			}
			$error_msg = NULL;
			$is_assignment = intval($_POST['is-lepress-assignment']);
			if($is_assignment > 0) {
				if(count($post_cats) > 1) {
					$is_assignment = 0;
					$error_msg = __("You can only choose one course", lepress_textdomain);
				} else {
					$course_meta = new CourseMeta($post_cats[0]);
					if(!$course_meta->getisCourse()) {
						$is_assignment = 0;
						$error_msg = __("Chosen category is not a course", lepress_textdomain);
					} else {
						if($course_meta->getIsClosed()) {
							$is_assignment = 0;
							$error_msg = __("Chosen category is a closed course", lepress_textdomain);
						}
					}
				}
			}
			$start_date = ($in_start = $this->safeStringInput($_POST['lepress-assignment-start-date'], true)) ? $in_start : date('m/d/Y');
			$end_date = ($in_end = $this->safeStringInput($_POST['lepress-assignment-end-date'], true)) ? $in_end : date('m/d/Y', strtotime('+1 week'));

			$new_date = date('Y-m-d', strtotime($start_date));

			if(strtotime($start_date) >= strtotime($end_date) && $is_assignment > 0) {
				$error_msg = __("Assignment ending date must be greater than start date", lepress_textdomain);
				$is_assignment = 0;
			}

			if(!$post) {
				$post = get_post($post_id);
			}
			if($new_date != date('Y-m-d', strtotime($post->post_date)) && $is_assignment > 0) {
				$post->post_date = $new_date;
				$post->post_date_gmt = $new_date;
				if(new_date > date('Y-m-d', strtotime($post->post_date))) {
					$post->post_status = 'future';
				} else {
					$post->post_status = 'publish';
				}
				wp_update_post($post);
			}
			if(!is_null($error_msg)) {
				update_post_meta($post_id, '_lepress-assignment-error', $error_msg);
			} else {
				delete_post_meta($post_id, '_lepress-assignment-error');
			}
			
			if($update_post_cats) {
				 wp_set_post_categories($post_id, $post_cats);
			}
			update_post_meta($post_id, '_lepress-assignment-start-date', $start_date);
			update_post_meta($post_id, '_lepress-assignment-end-date', $end_date);
			update_post_meta($post_id, '_is-lepress-assignment', $is_assignment);
			
			if($course_meta instanceOf CourseMeta) {
				$notification_sent = get_post_meta($post_id, '_lepress-notification-sent', true);
				if($course_meta->getIsCourse() && !$course_meta->getIsClosed() && $is_assignment > 0 && $notification_sent != "done") {
					if($post->post_status == "publish") {
						$course_meta->notifyParticipantsAssignments($post_id, $end_date);
						update_post_meta($post_id, '_lepress-notification-sent', "done");
					}
				}
			}
		}
	}
}

?>
