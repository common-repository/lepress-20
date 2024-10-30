<?php

/**
 * CourseMeta data handler - category data handler, assignments, feedbacks
 */

class CourseMeta {
	private $users = array();
	private $course_table, $students_table, $invitations_table, $subscriptions_table,$student_groups_table;
	private $wpdb;

	//Init db tables variables
	function __construct($cat_id, $accept_key = false) {
		global $wpdb, $LePressTeacher;
		$this->cat_ID = $cat_id;
		$prefix = $LePressTeacher->get_wpdb_prefix();
		
		$this->course_table = $prefix.'course_meta';
		$this->students_table = $prefix.'students';
		$this->student_groups_table = $prefix.'student_groups';
		$this->invitations_table = $prefix.'invitations';
		$this->subscriptions_table = $prefix.'subscriptions';
		$this->teachers_table = $prefix.'teachers';
		$this->templates_table = $prefix.'templates';
		$this->wpdb = $wpdb;
		$this->accept_key = $accept_key;
		if(!$this->metaDataExist()) {
			$this->wpdb->insert($this->course_table, array('cat_id' => $cat_id));
		}
		/* Runs everytime to clean out expired invitations keys */
		$this->removeInviteKeys();
	}
	
	/**
	 * Unsubscribe all students
	 *
	 * This is called on deactivation, role disable
	 */
	 
	function unsubcribeAllStudents() {
		$active_subscriptions = $this->getApprovedSubscriptions();
		$pending_subscriptions = $this->getPendingSubscriptions();
		
		foreach($active_subscriptions as $subs_act) {
			 $this->setSubscriptionStatus($subs_act->id, 2, $message = false);
		}
		
		foreach($pending_subscriptions as $subs_pend) {
			$this->setSubscriptionStatus($subs_pend->id, 2, $message = false);
		}
		
	}
	
	/**
	 * Send out email notification
	 */
	 
	function sendNotification($to, $subject, $message, $html = false) {
		global $LePress;
		if(is_user_logged_in()) {
			$current_user = wp_get_current_user();
		} else {
			$current_user = $LePress->getBlogOwnerUser();
		}
		$headers = 'From: '.$current_user->user_firstname.' '.$current_user->user_lastname.' <'.$current_user->user_email.'>' . "\r\n";
		$headers .= "Content-Type: text/".($html ? 'html' : 'plain')."; charset=utf-8\r\n";
		wp_mail($to, $subject, $message, $headers);
	}
	
	/**
	 * Send out new assignment notification to all the participants
	 */
	
	function notifyParticipantsAssignments($post_id, $end_date) {
		global $LePress;
		$students = $this->getApprovedSubscriptions();
		if($students) {
			$post = get_post($post_id);
			foreach($students as $student) {
				$to = $student->firstname." ".$student->lastname." <".$student->email.">";
				$subject = sprintf(__('New assignment - %s', lepress_textdomain), $post->post_title);
				$message = sprintf(__('New activity on course - %s', lepress_textdomain), '<a href="'.get_category_link($this->cat_ID).'">'.$this->getName().'</a>');
				$message .= "\r\n\r\n".sprintf(__('New assignment: %s', lepress_textdomain), '<a href="'.get_permalink($post_id).'">'.$post->post_title.'</a>');
				$message .= "\r\n\r\n".sprintf(__('Excerpt: %s', lepress_textdomain), (empty($post->post_excerpt) ? $LePress->mb_cutstr($post->post_content) : $post->post_excerpt));
				$message .= "\r\n\r\n".sprintf(__('Deadline: %s', lepress_textdomain), $LePress->date_i18n_lepress($end_date));
				$message = nl2br($message);
				$this->sendNotification($to, $subject, $message, true);
			}
		}
	}
	
	/**
	 * Verifiy access by blog url and email
	 */
	
	function verifyAccess($blog_url, $email, $action) {
		if($this->getIsCourse()) {
			//First check if we are not trying to subscribe on our own course
			if($blog_url == get_bloginfo('siteurl')) {
				return -3;
			}
			if(($subs = $this->getStudentSubscriptionByBlogUrl($blog_url, true)) || !$this->studentExist($email)) {
				if($action == 'subscribe' && $subs) {
					return -2;
				}
				return $blog_url;
			} else {
				if($action == 'subscribe') {
					return $blog_url;
				}
			}
		} else {
			return -1;
		}
		return false;
	}

	/**
	 * Get category name
	 */
	 
	function getName() {
		$cat = get_category($this->cat_ID);
		return $cat->cat_name;
	}
	
	/**
	 * MD5 access key, for iframe view 
	 */
	 
	function getMD5AccessKey($subscription_id) {
		$accept_key = $this->wpdb->get_var('SELECT accept_key FROM '.$this->subscriptions_table.' WHERE '.$this->subscriptions_table.'.id = '.$subscription_id.' AND cat_id = '.esc_sql($this->cat_ID));
		if($accept_key) {
			return md5(get_category_link($this->cat_ID).$accept_key);
		}
		return false;
	}

	/**
	 * Check if category metadata exists
	 */
	 
	private function metaDataExist() {
		return $this->wpdb->get_row('SELECT * FROM '.$this->course_table.' WHERE cat_id = '.esc_sql($this->cat_ID));
	}

	/**
	 * Set is course flag
	 */
	 
	function setIsCourse($bool) {
		if($this->getIsClosed()) { return true; }
		$this->wpdb->update($this->course_table, array('is_course' => $bool), array('cat_id' => $this->cat_ID));
	}

	/**
	 * Get is course flag
	 */
	 
	function getIsCourse() {
		return (bool) $this->wpdb->get_var('SELECT is_course FROM '.$this->course_table.' WHERE cat_id = '.esc_sql($this->cat_ID));
	}
	
	/**
	 * Set is closed flag
	 */
	 
	function setIsClosed() {
		if($this->getIsClosed()) { return true; }
		$this->wpdb->update($this->course_table, array('is_closed' => '1'), array('cat_id' => $this->cat_ID));
	}
	
	/**
	 * Get is closed flag
	 */
	 
	function getIsClosed() {
		return (bool) $this->wpdb->get_var('SELECT is_closed FROM '.$this->course_table.' WHERE cat_id = '.esc_sql($this->cat_ID));
	}
	
	/**
	 * Get advertise flag
	 *
	 * Wordpress Network used only 
	 */
	
	function getAdvertise() {
		if($this->getIsClosed()) { return false; }
		return (bool) $this->wpdb->get_var('SELECT advertise FROM '.$this->course_table.' WHERE cat_id = '.esc_sql($this->cat_ID));
	}
	
	/**
	 * Set advertise flag
	 *
	 * Wordpress Network used only 
	 */
	 
	function setAdvertise($bool) {
		if($this->getIsClosed()) { return true; }
		$this->wpdb->update($this->course_table, array('advertise' => $bool), array('cat_id' => $this->cat_ID));
	}
	
	/** End of Wordpress Network used only */

	/**
	 * Set access type
	 */
	 
	function setAccessType($bool) {
		$this->wpdb->update($this->course_table, array('access_type' => $bool), array('cat_id' => $this->cat_ID));
	}

	/**
	 * Get access type
	 */
	 
	 
	function getAccessType() {
		return (bool) $this->wpdb->get_var('SELECT access_type FROM '.$this->course_table.' WHERE cat_id = '.esc_sql($this->cat_ID));
	}

	/**
	 * Set teachers for course
	 * 
	 * Also sends out message to new teacher about adding or removing
	 */
	 
	function setTeachers($teachers) {
		if($this->getIsClosed()) { return true; }
		//Add additional teachers
		$current_user = wp_get_current_user();
		if(is_array($teachers)) {
			foreach($teachers as $user_id => $action) {
				$teacher = get_userdata($user_id);
				if($teacher) {
					if($action == 0) {
						$result = $this->wpdb->query('DELETE FROM '.$this->teachers_table.' WHERE cat_id = '.esc_sql($this->cat_ID).' AND wp_user_id='.esc_sql($user_id));
						if($result) {
							$title = __('You are no longer a LePress teacher', lepress_textdomain);
							$message = __('You are no longer a LePress teacher on blog', lepress_textdomain).' - '.get_bloginfo('siteurl')."\r\n";
						}
					} else {
						$data = array('cat_id' => $this->cat_ID, 'wp_user_id' => $user_id);
						$result = $this->wpdb->insert($this->teachers_table, $data);
						if($user_id != $current_user->ID) {
							if($result) {
								$title = __('You have been added as a LePress teacher', lepress_textdomain);
								$message = __('You have been added as a LePress teacher on blog', lepress_textdomain).' - '.get_bloginfo('siteurl')."\r\n";
							}
						}
					}
					if(!empty($message) && $user_id != $current_user->ID) {
						//Send email notification to new teacher
						$this->sendNotification($teacher->user_email, $title, $message);
					}
				}
			}
		}

		//If editing category and category has no teachers, add current user as a teacher
		if(!$this->getTeachers(true)) {
			global $current_user;
			get_currentuserinfo();
			$data = array('cat_id' => $this->cat_ID, 'wp_user_id' => $current_user->ID);
			$this->wpdb->insert($this->teachers_table, $data);
		}
	}

	/**
	 * Get teacher for course
	 */
	 
	function getTeachers($onlyCat_ID = false) {
		if($onlyCat_ID) {
			return $this->wpdb->get_results('SELECT wp_user_id  FROM '.$this->teachers_table.' WHERE '.$this->teachers_table.'.cat_id = '.esc_sql($this->cat_ID));	
		} else {
			return $this->wpdb->get_results('SELECT wp_user_id  FROM '.$this->teachers_table.', '.$this->subscriptions_table.' WHERE  accept_key = "'.esc_sql($this->accept_key).'" AND '.$this->teachers_table.'.cat_id = '.$this->subscriptions_table.'.cat_id  AND '.$this->subscriptions_table.'.cat_id = '.esc_sql($this->cat_ID));
		}
	}
	
	/**
	 * Get teacher categories/courses access 
	 */
	
	function getTeacherCats($user_id) {
		return $this->wpdb->get_results('SELECT cat_id  FROM '.$this->teachers_table.' WHERE '.$this->teachers_table.'.wp_user_id = '.esc_sql($user_id));
	}

	/**
	 * Get is user a teacher on course
	 */
	 
	function isTeacher($wp_user_id) {
		return $this->wpdb->get_var('SELECT wp_user_id  FROM '.$this->teachers_table.' WHERE '.$this->teachers_table.'.cat_id = '.esc_sql($this->cat_ID).' AND wp_user_id = '.esc_sql($wp_user_id));
	}

	/**
	 * Delete course metadata from DB
	 */
	 
	function delete() {
		return $this->wpdb->query('DELETE FROM '.$this->course_table.' WHERE cat_id = '.esc_sql($this->cat_ID));
	}

	/**
	 * Validate invitation key
	 */
	 
	function validateInviteKey($key, $email) {
		return $this->wpdb->get_var('SELECT id FROM '.$this->invitations_table.' WHERE cat_id = '.esc_sql($this->cat_ID).' AND new_student_email = "'.esc_sql($email).'" AND invite_key = "'.esc_sql($key).'" AND UNIX_TIMESTAMP(DATE_ADD(expire_time, INTERVAL 7 day)) >= UNIX_TIMESTAMP(NOW())');
	}
	
	/**
	 * Remove expired invitation keys
	 */

	function removeInviteKeys() {
		/* Removes only expired invitation keys */
		$this->wpdb->query('DELETE FROM '.$this->invitations_table.' WHERE UNIX_TIMESTAMP(DATE_ADD(expire_time, INTERVAL 7 day)) <= UNIX_TIMESTAMP(NOW())');
	}

	/**
	 * Get student by subscription ID
	 */
	 
	function getStudentBySubscription($subscription_id) {
		return $this->wpdb->get_row('SELECT '.$this->students_table.'.id, first_name, last_name, blog_url, email, accept_key FROM '.$this->subscriptions_table.', '.$this->students_table.' WHERE '.$this->subscriptions_table.'.id = '.$subscription_id.' AND student_id = '.$this->students_table.'.id AND cat_id = '.esc_sql($this->cat_ID));
	}

	/**
	 * Get student by blog url and accept key if passed
	 */
	 
	function getStudentSubscriptionByBlogUrl($blog_url, $ignore_accept_key = false) {
		if($ignore_accept_key) {
			return $this->wpdb->get_row('SELECT '.$this->subscriptions_table.'.id, first_name, last_name, blog_url, email, accept_key FROM '.$this->subscriptions_table.', '.$this->students_table.' WHERE blog_url = "'.esc_sql($blog_url).'" AND cat_id = '.esc_sql($this->cat_ID).' AND student_id = '.$this->students_table.'.id');
		} else {
			return $this->wpdb->get_row('SELECT '.$this->subscriptions_table.'.id, first_name, last_name, blog_url, email, accept_key FROM '.$this->subscriptions_table.', '.$this->students_table.' WHERE blog_url = "'.esc_sql($blog_url).'" AND accept_key = "'.esc_sql($this->accept_key).'" AND cat_id = '.esc_sql($this->cat_ID).' AND student_id = '.$this->students_table.'.id');
		}
	}
	
	/**
	 * Check if student has subscriptions
	 */

	function getStudentHasSubscription($student) {
		return $this->wpdb->get_var('SELECT count(id) FROM '.$this->subscriptions_table.' WHERE student_id = '.esc_sql($student->id));
	}

	/**
	 * Remove subscription by blog_url, accept_key or subscription id
	 */
	 
	function removeSubscription($blog_url, $accept_key, $subscription_id = false) {
		if($this->getIsClosed()) { return true; }
		
		if(!$subscription_id) {
			$subscription_id = $this->wpdb->get_var('SELECT '.$this->subscriptions_table.'.id FROM '.$this->subscriptions_table.', '.$this->students_table.' WHERE blog_url = "'.esc_sql($blog_url).'" AND student_id = '.$this->students_table.'.id AND accept_key = "'.esc_sql($accept_key).'"');
		}
		//If subscription found, proceed
		if($subscription_id) {
			$student = $this->getStudentBySubscription($subscription_id);
			$deleted = $this->wpdb->query('DELETE FROM '.$this->subscriptions_table.' WHERE ID = "'.esc_sql($subscription_id).'" AND accept_key = "'.esc_sql($accept_key).'"');
			if($deleted) {
				$this->sendNotification($student->email, __("You have been unsubscribed from course", lepress_textdomain), __("You have been unsubscribed from course", lepress_textdomain)." ".get_category_link($this->cat_ID));
				//Check, if students has no subscriptions left
				$student_has_subscriptions = $this->getStudentHasSubscription($student);
				if(!$student_has_subscriptions) {
					$this->removeStudent($student);
				}
				return true;
			} else {
				return false;
			}
		} else {
			return true;
		}
	}

	/**
	 * Add new subscription
	 * 
	 * @return course metadata XML
	 */
	 
	function addSubscription($firstname, $lastname, $email, $blog_url, $message, $invite_key = false) {
		if($this->getIsClosed()) { return -5; }
		
		$status = (int) $this->getAccessType();
		if($invite_key) {
			if(!$this->validateInviteKey($invite_key, $email) && $status == 0) {
				return false;
			} else {
				$status = 1;
			}
		}
		if(!$this->studentExist($email)) {
			$this->addStudent($firstname, $lastname, $email, $blog_url);
		}
		if($student_id = $this->studentExist($email)) {
			$accept_key = $this->generateAcceptKey();
			$result = $this->wpdb->insert($this->subscriptions_table, array('cat_id' => $this->cat_ID, 'student_id' => $student_id, 'status' => $status, 'accept_key' => $accept_key, 'message' => $message));
			if($result) {				
				$this->accept_key = $accept_key; //Need to set accept_key in order to getTeachers	
				$this->sendNotification($email, "You have successfully subscribed to course", "You have successfully subscribed to course ".get_category_link($this->cat_ID));
				//return course metadata for request
				return $this->getCourseMeta(array('course-status' => $status, 'accept-key' => $accept_key, 'lepress-message' => $message));
			} else {
				//UNIQUE OR FOREIGN KEY CONSTRAINT, student already subscribed
				return -2;
			}
		} else {
			//Add student has failed ?
			return -3;
		}
		return -4;
	}
	
	/**
	 * Add new student to DB
	 */
	 
	private function addStudent($firstname, $lastname, $email, $blog_url) {
		if($this->getIsClosed()) { return false; }
		return $this->wpdb->insert($this->students_table, array('first_name' => $firstname,
																				'last_name' =>	$lastname,
																				'email' => $email,
																				'blog_url' => $blog_url));
	}

	/**
	 * Remove student from DB
	 */
	 
	private function removeStudent($student) {
		if(!$this->getIsClosed()) {
			$this->wpdb->query('DELETE  FROM '.$this->students_table.' WHERE id = "'.esc_sql($student->id).'"');
		}
	}
	
	/**
	 * Check if student exists in DB
	 */
	 
	private function studentExist($email) {
		return $this->wpdb->get_var('SELECT id FROM '.$this->students_table.' WHERE email = "'.esc_sql($email).'"');
	}

	/**
	 * Check if student already subscribed
	 */
	 
	private function studentAlreadySubscribed($email) {
		return $this->wpdb->get_results('SELECT '.$this->subscriptions_table.'.id, first_name, last_name, email FROM '.$this->students_table.', '.$this->subscriptions_table.' WHERE student_id = '.$this->students_table.'.id AND email = "'.esc_sql($email).'" AND cat_id = '.esc_sql($this->cat_ID));
	}
	
	/**
	 * Get student subscription by email;
	 */
	 
	public function getStudentSubscriptionByEmail($email) {
		$s = $this->studentAlreadySubscribed($email);
		return $s[0];
	}

	/**
	 * Generate accept key, used by both ends of communication
	 */
	 
	private function generateAcceptKey($length = 10) {
		$chars = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789";
		$key = "";
		for ($i=0; $i<$length; $i++) {
			$key .= substr( $chars, rand( 0, strlen( $chars ) - 1), 1 );
		}
		return $key;
	}

	/**
	 * Get approved subscriptions count
	 */
	 
	function getApprovedCount() {
		return $this->wpdb->get_var('SELECT count(id) FROM '.$this->subscriptions_table.' WHERE cat_id = '.esc_sql($this->cat_ID).' AND status = 1');
	}
	
	/**
	 * Get pending subscriptions count
	 */

	function getPendingCount() {
		return $this->wpdb->get_var('SELECT count(id) FROM '.$this->subscriptions_table.' WHERE cat_id = '.esc_sql($this->cat_ID).' AND status = 0');
	}
	
	/**
	 * Get all pending (for all the courses) subscriptions count
	 */

	function getAllPendingSubscriptionsCount() {
		$cats = get_categories('hide_empty=0');
		$count = 0;
		foreach($cats as $cat) {
			$this->cat_ID = $cat->cat_ID;
			if($this->getIsCourse()) {
				if($subscriptions = $this->getPendingSubscriptions()) {
					foreach($subscriptions as $user) {
						$count++;
					}
				}
			}
		}
		return $count;
	}
	
	/**
	 * Get ungraded assignments count
	 */

	function getAllUngradedAssignmentCount() {
		$count = 0;
		$cats = get_categories('hide_empty=0');
		foreach($cats as $cat) {
			$this->cat_ID = $cat->cat_ID;
			if($this->getIsCourse()) {
				$assignments = get_posts(array('meta_query' => array(array('key' => '_is-lepress-assignment', 'value' => 1)), 'post_status' => 'draft|future|publish', 'numberposts' => -1, 'category' => $this->cat_ID));
				foreach($assignments as $post) {
					$count = $count + $this->getUngradedCount($post->ID);
				}
			}
		}
		return $count;
	}
	
	/**
	 * Get pending subscriptions
	 */

	function getPendingSubscriptions() {
		return $this->wpdb->get_results('SELECT '.$this->subscriptions_table.'.id, first_name, last_name, email, message, blog_url FROM '.$this->students_table.', '.$this->subscriptions_table.' WHERE student_id = '.$this->students_table.'.id AND status = 0 AND cat_id = '.esc_sql($this->cat_ID).' ORDER BY last_name ASC');
	}
	
	/**
	 * This functions clears out pending subscriptions, when course is closed
	 */
	
	function removePendingSubscriptions() {
		if($this->getIsClosed()) {
			return $this->wpdb->query('DELETE FROM '.$this->subscriptions_table.' WHERE status = 0 AND cat_id = '.esc_sql($this->cat_ID));
		}
		return false;
	}
	
	/**
	 * Get approved subscriptions
	 */

	function getApprovedSubscriptions() {
		return $this->wpdb->get_results('SELECT '.$this->subscriptions_table.'.id, '.$this->students_table.'.id as student_id, first_name, last_name, email, blog_url FROM '.$this->students_table.', '.$this->subscriptions_table.' WHERE student_id = '.$this->students_table.'.id AND status = 1 AND cat_id = '.esc_sql($this->cat_ID).' ORDER BY last_name ASC');
	}
	
	/**
	 * Check if course has pending or approved subscriptions
	 */
	 
	function hasSubscriptions() {
		if($this->getApprovedSubscriptions() || $this->getPendingSubscriptions()) {
			return true;
		}
		return false;
	}
	
	/**
	 * Get ungraded count
	 */

	function getUngradedCount($post_id) {
		global $LePress;
		$ungraded = 0;
		foreach($this->getApprovedSubscriptions() as $subscription){
			$comment = $LePress->fetchComment($post_id, $subscription->email, 'LePressStudent');
			$meta_data = get_post_meta($post_id, '_lepress-student-'.md5($post_id.$subscription->email), true);
			if($comment && !$meta_data['ungraded']) {
				$ungraded++;
			}
		}
		return $ungraded;
	}

	/**
	 * Alter subscription status
	 */
	 
	function setSubscriptionStatus($subscription_id, $status, $message = false) {
		if($this->getIsClosed()) { return true; }
		
		$student = $this->getStudentBySubscription($subscription_id);

		$data = array('status' => $status);
		$where = array('id' => $subscription_id);
		switch($status) {
			case 1:
				$updated = $this->wpdb->update($this->subscriptions_table, $data, $where);
				$this->sendNotification($student->email, __("You have been accepted to course", lepress_textdomain), __("You have been accepted to course", lepress_textdomain)." ".get_category_link($this->cat_ID)."\r\n\r\n".$message);
				break;
			case 0:
			case 2:
				$updated = $this->wpdb->query('DELETE FROM '.$this->subscriptions_table.' WHERE id = "'.esc_sql($subscription_id).'"');
				if($status == 2) {
					$this->sendNotification($student->email, __("You have been unsubscribed from course", lepress_textdomain), __("You have been unsubscribed from course", lepress_textdomain)." ".get_category_link($this->cat_ID));
				}
				break;
		}
		if($updated) {
			if($student) {
				$request = new LePressRequest(1, 'subscription-status-changed');
				$request->addParams(array('course-url' =>get_category_link($this->cat_ID), 'course-status' => $status, 'accept-key' => $student->accept_key, 'lepress-message' => $message));
				$request->setBlocking('false');
				$request->doPost($student->blog_url);
				if($status == 0) {
					$this->sendNotification($student->email, __("You have been declined to course", lepress_textdomain), __("You have been declined to course", lepress_textdomain)." ".get_category_link($this->cat_ID)."\r\n\r\n".$message);
				}
			}
			//Check, if students has no subscriptions left
			$student_has_subscriptions = $this->getStudentHasSubscription($student);
			if(!$student_has_subscriptions) {
				$this->removeStudent($student);
			}
		}
	}

	/**
	 * Add student submission data
	 *
	 * Creates new comment, if comment already exists, removes previous one and adds new
	 */
	 
	function addAnswer($post_id, $answer_url, $answer_content, $answer_post_id, $blog_url) {
		if($this->getIsClosed()) { return true; }
		
		global $LePress;
		$post = get_post($post_id);
		if($post) {
			$post_cats = wp_get_post_categories($post->ID);
			if(get_post_meta($post->ID, '_is-lepress-assignment', true) && in_array($this->cat_ID, $post_cats)) {
				$subscription = $this->getStudentSubscriptionByBlogUrl($blog_url);
				if($subscription) {
					$comment = $LePress->fetchComment($post->ID, $subscription->email, 'LePressStudent');
					$time = current_time('mysql');
					$data = array(
					    'comment_post_ID' => $post_id,
					    'comment_author' => $subscription->last_name.' '.$subscription->first_name,
					    'comment_author_email' => $subscription->email,
					    'comment_author_url' => $answer_url,
					    'comment_content' => $LePress->mb_cutstr(htmlspecialchars_decode($answer_content))."\n\n".'<a href="'.$answer_url.'">'.urldecode($answer_url).'</a>',
					    'comment_agent' => 'LePressStudent',
					    'comment_type' => '',
					    'comment_date' => $time,
					    'comment_parent' => 0,
					    'user_id' => 0,
					    'comment_approved' => 1);
					if(!$comment) {
						$comment_id = wp_insert_comment($data);
						update_comment_meta($comment_id, 'lepress-read', '1');
						update_post_meta($post->ID, '_lepress-student-'.md5($post->ID.$subscription->email), array('ungraded' => false, 'post_url' => $answer_url, 'post_id' => $answer_post_id));
					} else {
						//First check metadata status
						$lepress_read = get_comment_meta($comment->comment_ID, 'lepress-read', true);
						//Fetch feedback given meta
						$feedback_given = get_comment_meta($comment->comment_ID, 'lepress-feedback-given', true);
						//Delete previous comment and add new with old metadata
						$deleted = wp_delete_comment($comment->comment_ID, true);
						if($deleted) {
							$comment_id = wp_insert_comment($data);
							if($lepress_read) {
								update_comment_meta($comment_id, 'lepress-read', 1);
							}
							if($feedback_given) {
								update_comment_meta($comment_id, 'lepress-feedback-given', 1);
							}
						}
						//Update post meta - post answer url, maybe it has changed
						$meta_data = get_post_meta($post->ID, '_lepress-student-'.md5($post->ID.$subscription->email), true);
						if($meta_data) {
							$meta_data['post_url'] = $answer_url;
							update_post_meta($post->ID, '_lepress-student-'.md5($post->ID.$subscription->email), $meta_data);
						}
					}
				}
			}
		}
	}

	/**
	 * Send feedback to student
	 */
	 
	function sendFeedback($post_id, $student, $student_answer, $feedback, $grade) {
		if($this->getIsClosed()) { return -2; }
		
		$grade = strtoupper($grade);
		if($student_answer) {
			$attrs = $student_answer->attributes();
			$student_post_id = (int) $attrs['id'];
			global $current_user, $LePress;
			$md5_key = md5($post_id.$student->email);
			$meta_data = get_post_meta($post_id, '_lepress-student-'.$md5_key, true);
			if($meta_data) {
				if($LePress->mb_strlen($feedback) == $LePress->mb_strlen($meta_data['feedback']) && $grade == $meta_data['grade']) {
					return -3;
				}
			}
			if($this->isTeacher($current_user->ID)) {			
				$request = new LePressRequest(1, 'addFeedback');
				$request->addParams(array('course-url' => get_category_link($this->cat_ID), 'accept-key' => $student->accept_key, 'teacher_id' => $current_user->ID, 'post_id' => $student_post_id, 'feedback' => $LePress->safeStringInput($feedback), 'grade' => $LePress->safeStringInput($grade)));
				$request->doPost($student->blog_url);
				if($meta_data) {
					$meta_data['ungraded'] = true;
					$meta_data['grade'] = $grade;
					$meta_data['feedback'] = $feedback;
					update_post_meta($post_id,  '_lepress-student-'.$md5_key, $meta_data);
				}
				return $request->getStatusCode();
			} else {
				return -1;
			}
		}
	}

	/**
	 * Fetch student submission from his/her blog
	 *
	 * @return xml or boolean false
	 */
	 
	function getStudentAssignmentAnswer($student, $post_id) {
		$meta_data = get_post_meta($post_id, '_lepress-student-'.md5($post_id.$student->email), true);
		if($meta_data['post_id']){
			$request = new LePressRequest(1, 'getAnswerByID');
			$request->addParams(array('course-url' => get_category_link($this->cat_ID), 'accept-key' => $student->accept_key, 'post_id' => $meta_data['post_id']));
			$request->doPost($student->blog_url);
			if($request->getStatusCode() == 200) {
				$xml = @simplexml_load_string($request->getBody())->answer;
				if($xml) {
					return $xml;
				}
				return false;
			}
		}
		return false;
	}
	
	/**
	 * Output course metadata as XML
	 */

	function getCourseMeta($extra_nodes = array()) {
		global $LePress;
		$xml = new SimpleXMLElement(xml_root);
		$course = $xml->addChild('courseMeta');
		$cat = get_category($this->cat_ID);
		$course->addChild('name', '<![CDATA['.$LePress->safeStringInput($cat->name).']]>');
		$course->addChild('url', '<![CDATA['.$LePress->safeStringInput(get_category_link($this->cat_ID)).']]>');
		$course->addChild('is_closed', '<![CDATA['.$LePress->safeStringInput($this->getIsClosed()).']]>');
		if(count($extra_nodes) > 0) {
			$extras = $course->addChild('extras');
			foreach($extra_nodes as $node => $value) {
				$extras->addChild($node, '<![CDATA['.$LePress->safeStringInput($value).']]>');
			}
		}

		$teachers = $course->addChild('teachers');
		foreach($this->getTeachers() as $row) {
			$wp_user_id = $row->wp_user_id;
			$userdata = get_userdata($wp_user_id);
			$teacher = $teachers->addChild('teacher');
			$teacher->addAttribute('id', $wp_user_id);
			$teacher->addChild('firstname', '<![CDATA['.$LePress->safeStringInput((!empty($userdata->user_firstname) ? $userdata->user_firstname : $userdata->user_login)).']]>');
			$teacher->addChild('lastname', '<![CDATA['.$LePress->safeStringInput($userdata->user_lastname).']]>');
			$teacher->addChild('email', '<![CDATA['.$LePress->safeStringInput($userdata->user_email).']]>');
			$teacher->addChild('organization', '<![CDATA['.$LePress->safeStringInput(get_the_author_meta('lepress-organization', $wp_user_id)).']]>');
		}
		return htmlspecialchars_decode($xml->asXML());
	}

	/**
	 * Output assignments as XML
	 */
	 
	function getAssignments() {
		global $LePress;
		$posts = get_posts(array('meta_query' => array(array('key' => '_is-lepress-assignment', 'value' => 1)), 'post_status' => 'publish', 'numberposts' => -1, 'category' => $this->cat_ID));
		$xml = new SimpleXMLElement(xml_root);
		foreach($posts as $post) {
			$assignment = $xml->addChild('assignment');
			$assignment->addAttribute('post_id', $post->ID);
			$assignment->addChild('title', '<![CDATA['.$LePress->safeStringInput($post->post_title).']]>');
			$assignment->addChild('url', '<![CDATA['.$LePress->safeStringInput(get_permalink($post->ID)).']]>');
			if(!empty($post->post_excerpt)) {
				$assignment->addChild('excerpt', '<![CDATA['.$LePress->safeStringInput($post->post_excerpt).']]>');
			}
			$assignment->addChild('start_date', get_post_meta($post->ID, '_lepress-assignment-start-date', true));
			$assignment->addChild('end_date', get_post_meta($post->ID, '_lepress-assignment-end-date', true));
		}
		return htmlspecialchars_decode($xml->asXML());
	}
	
	/**
	 * Output classmates metadata as XML
	 */

	function getClassmates($blog_url) {
		global $LePress;
		$subscriptions = $this->getApprovedSubscriptions();
		$xml = new SimpleXMLElement(xml_root);
		foreach($subscriptions as $classmate) {
			if(trim($classmate->blog_url) != trim($blog_url)) { //Just in case trim
				$mate = $xml->addChild('classmate');
				$mate->addAttribute('id', $classmate->student_id);
				$mate->addChild('firstname', '<![CDATA['.$LePress->safeStringInput($classmate->first_name).']]>');
				$mate->addChild('lastname', '<![CDATA['.$LePress->safeStringInput($classmate->last_name).']]>');
				$mate->addChild('email', '<![CDATA['.$LePress->safeStringInput($classmate->email).']]>');
				$mate->addChild('blog_url', '<![CDATA['.$LePress->safeStringInput($classmate->blog_url).']]>');
			}
		}
		return htmlspecialchars_decode($xml->asXML());
	}

	/**
	 * Get assignment by ID
	 * This request is made by student blog
	 */
	 
	function getAssignmentByID($post_id) {
		global $LePress;
		$post = get_post($post_id);
		$xml = new SimpleXMLElement(xml_root);
		if($post && $post_id == $post->ID) {
			if(get_post_meta($post->ID, '_is-lepress-assignment', true)) {
				$assignment = $xml->addChild('assignment');
				$assignment->addAttribute('post_id', $post->ID);
				$assignment->addChild('title', '<![CDATA['.$LePress->safeStringInput($post->post_title).']]>');
				$assignment->addChild('url', '<![CDATA['.$LePress->safeStringInput(get_permalink($post->ID)).']]>');
				if(!empty($post->post_excerpt)) {
					$assignment->addChild('excerpt', '<![CDATA['.$LePress->safeStringInput($post->post_excerpt).']]>');
				}
				$assignment->addChild('content', '<![CDATA['.$LePress->safeStringInput($post->post_content).']]>');
				$assignment->addChild('start_date', get_post_meta($post->ID, '_lepress-assignment-start-date', true));
				$assignment->addChild('end_date', get_post_meta($post->ID, '_lepress-assignment-end-date', true));
			}
		}
		return htmlspecialchars_decode($xml->asXML());
	}
	
	/**
	 * Generate invitation message
	 */

	function inviteStudentMessage($email) {
		$already_subscribed = $this->studentAlreadySubscribed($email);
		if(!$already_subscribed) {
			$invite_key = $this->generateAcceptKey();
			$data = array('invite_key' => $invite_key, 'cat_id' => $this->cat_ID, 'new_student_email' => $email);
			$result = $this->wpdb->insert($this->invitations_table, $data);
			if($result) {
				$message = __('You have been invited to course', lepress_textdomain).' - '.$this->getName()."\n";
				$message .= add_query_arg(array('key' => $invite_key), get_category_link($this->cat_ID))."\n";
				return $message;
			}
		}
		return '';
	}

	/**
	 * Update student metadata, request made when student updates his/her profile
	 */
	 
	function updateStudentProfile($firstname, $lastname, $new_email, $old_email) {
		if($this->getIsClosed()) { return true; }
		if($student_id = $this->studentExist($old_email)) {
			$data = array('first_name' => $firstname, 'last_name' => $lastname, 'email' => $new_email);
			$where = array('id' => $student_id);
			$this->wpdb->update($this->students_table, $data, $where);
		}
	}
	
	/**
	 * Get has category any posts
	 */
	 
	 function getHasAssignments() {
	 	return get_posts(array('post_status' => 'publish|draft|future', 'numberposts' => -1, 'category' => $this->cat_ID));	
	 }
	
	/**
	 * Export as template 
	 */
	 
	 function exportAsTemplate() {
	 	$posts = get_posts(array('post_status' => 'publish|draft|future', 'numberposts' => -1, 'category' => $this->cat_ID));	
	 	if($posts) {
	 		global $LePress;
			$xml = new SimpleXMLElement(xml_root);
			$tpl = $xml->addChild('template');
			$tpl->addAttribute('name', $this->getName());
			foreach($posts as $post) {
				$is_assignment = get_post_meta($post->ID, '_is-lepress-assignment', true);
				$as = $tpl->addChild('post');
				if($is_assignment) {
					$as->addAttribute('assignment', 'true');
				} else {
					$as->addAttribute('assignment', 'false');
				}
				$as->addChild('title', '<![CDATA['.$LePress->safeStringInput($post->post_title).']]>');
				$as->addChild('content', '<![CDATA['.$LePress->safeStringInput($post->post_content).']]>');
				$as->addChild('excerpt', '<![CDATA['.$LePress->safeStringInput($post->post_excerpt).']]>');
			}
			
			$tpl_content = htmlspecialchars_decode($xml->asXML());
			$tpl_name = $this->getName();
			$data = array('name' => $tpl_name, 'content' => $tpl_content, 'date' => current_time('mysql', 1));
			return $this->wpdb->insert($this->templates_table, $data);
	 	}
	 	return false;
	 }
	 
	/**
	 * Import template(s) from file
	 */
	 
	 function importTemplateFromFile($xml_str) {
	 	$xml = simplexml_load_string($xml_str);
	 	if($xml) {
	 		global $LePress;
	 		if($xml->templates) {
	 			foreach($xml->templates->template as $template) {
	 				$tpl_xml = new SimpleXMLElement(xml_root);
	 				$tpl_in = $tpl_xml->addChild('template');
	 				$tpl_name = (string) $template->attributes()->name;
	 				$tpl_in->addAttribute('name', $tpl_name);
					foreach($template->post as $assignment) {
						$as = $tpl_in->addChild('post');
						$is_assignment = (string) $assignment->attributes()->assignment;
						$as->addAttribute('assignment', $is_assignment);
						$as->addChild('title', '<![CDATA['.$LePress->safeStringInput((string) $assignment->title).']]>');
						$as->addChild('content', '<![CDATA['.$LePress->safeStringInput((string) $assignment->content).']]>');
						$as->addChild('excerpt', '<![CDATA['.$LePress->safeStringInput((string) $assignment->excerpt).']]>');
					}
					$tpl_content = htmlspecialchars_decode($tpl_xml->asXML());
					$data = array('name' => $tpl_name, 'content' => $tpl_content, 'date' => current_time('mysql', 1));
	 				$this->wpdb->insert($this->templates_table, $data);
	 			}
	 		} else {
	 			$tpl_name = (string) $xml->template->attributes()->name;
	 			$data = array('name' => $tpl_name, 'content' => $xml_str, 'date' => current_time('mysql', 1));
	 			return $this->wpdb->insert($this->templates_table, $data);
	 		}
	 	}
	 }
	 
	/**
	 * Get templates as a single XML file
	 */
	 
	function getTemplatesAsSingleFile() {
		$tpls = $this->getTemplates();
		if($tpls) {
			global $LePress;
			$xml = new SimpleXMLElement(xml_root);
			$tpl_root = $xml->addChild('templates');
			foreach($tpls as $tpl) {
				$tpl_xml = simplexml_load_string($tpl->content);
				if($tpl_xml) {
					$assignments = $tpl_xml->template->post;
					$tpl_node = $tpl_root->addChild('template');
					$tpl_node->addAttribute('name', $tpl->name);
					$tpl_node->addAttribute('date', $tpl->date);
					foreach($assignments as $assignment) {
						$as = $tpl_node->addChild('post');
						$is_assignment = (string) $assignment->attributes()->assignment;
						$as->addAttribute('assignment', $is_assignment);
						$as->addChild('title', '<![CDATA['.$LePress->safeStringInput((string) $assignment->title).']]>');
						$as->addChild('content', '<![CDATA['.$LePress->safeStringInput((string) $assignment->content).']]>');
						$as->addChild('excerpt', '<![CDATA['.$LePress->safeStringInput((string) $assignment->excerpt).']]>');
					}
				}
			}
			return htmlspecialchars_decode($xml->asXML());
		}
		return false;
	}
	 
	/**
	 * Get all the templates 
	 */
	  
	function getTemplates() {
	  return $this->wpdb->get_results('SELECT * FROM '.$this->templates_table.' ORDER BY date DESC');
	}
	  
	/**
	 * Delete template
	 */
	 
	function deleteTemplate($tpl_id) {	
		return $this->wpdb->query('DELETE FROM '.$this->templates_table.' WHERE id="'.esc_sql($tpl_id).'"');
	}
	
	/**
	 * Get template by ID
	 */
	 
	function getTemplateByID($tpl_id) {
		return $this->wpdb->get_row('SELECT * FROM '.$this->templates_table.' WHERE id="'.esc_sql($tpl_id).'"');
	}
	
	/**
	 * Import from template to category
	 */
	
	function importTemplate($tpl_id) {
		$tpl = $this->getTemplateByID($tpl_id);
		if($tpl) {
			$xml = simplexml_load_string($tpl->content);
			if($xml) {
				foreach($xml->template->post as $assignment) {
					$post_data = array(
						 'post_title' => (string) $assignment->title,
						 'post_content' => (string) $assignment->content,
						 'post_excerpt' => (string) $assignment->excerpt,
						 'post_status' => 'draft',
						 'post_category' => array($this->cat_ID));
					$post_id = wp_insert_post($post_data);
					$flag = (string) $assignment->attributes()->assignment;
					if($flag == "true") {
						update_post_meta($post_id, '_is-lepress-assignment', 1);
					}
				}
				return true;
			}
		}
		return false;
	}
	 
	
	/* GROUP FUNCTIONS ARE NOT USED AND ARE DISABLED AT CURRENT RELEASE */
	
	/**
	 * Add participant to the group
	 */

	function addStudentGroup($group_name, $group_key, $student_email) {
		if($this->getIsClosed()) { return true; }
		$student_id = $this->studentExist($student_email);
		if($student_id) {
			$data = array('group_name' => $group_name, 'group_key' => $group_key, 'student_id' => $student_id);
			return $this->wpdb->insert($this->student_groups_table, $data);
		}
		return false;
	}
	
	/**
	 * Remove participant from the group
	 */
	 
	function removeStudentGroup($group_key, $student_email) {
		if($this->getIsClosed()) { return true; }
		$student_id = $this->studentExist($student_email);
		if($student_id) {
			return $this->wpdb->query('DELETE FROM '.$this->student_groups_table.' WHERE student_id = '.esc_sql($student_id).' AND group_key = "'.esc_sql($group_key).'"');
		}
		return false;
	}

	/**
	 * Get group by key
	 */
	 
	function getGroupByKey($group_key) {
		return $this->wpdb->get_row('SELECT * FROM '.$this->student_groups_table.' WHERE group_key ="'.esc_sql($group_key).'" AND type = 0 AND !ISNULL(group_name)');
	}
	
	/**
	 * Add requesting participant to the group
	 */

	function addMeToGroup($group_key, $blog_url) {
		if($this->getIsClosed()) { return true; }
		$subscription = $this->getStudentSubscriptionByBlogUrl($blog_url);
		if($subscription) {
			$student = $this->getStudentBySubscription($subscription->id);
			$group = $this->getGroupByKey($group_key);
			if($student) {
				$data = array('student_id' => $student->id, 'group_key' => $group_key, 'group_name' => $group->group_name, 'type' => 1);
				print_r($data);
				$this->wpdb->insert($this->student_groups_table, $data);
			}
		}
	}
}