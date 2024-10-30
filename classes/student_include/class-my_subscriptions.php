<?php

require_once(lepress_abspath.'classes/student.php');

/**
 * Student Subscriptions class, handles all the assignment, subscriptions adding/removing
 * @author Raido Kuli
 *
 */

class StudentSubscriptions {
	protected $courses_table, $teachers_table, $courses_teacher_rel_table;
	public $assignments_table;
	protected $groups_table, $classmates_table, $classmates_group_rel_table;
	protected $wpdb;

	/**
	 * Init subscriptions class
	 *
	 * Define DB tables variables
	 */
	 
	function __construct() {
		global $wpdb, $LePressStudent;
		if(!$LePressStudent instanceOf LePressStudentRole) {
			$LePressStudent = new LePressStudentRole();
		}
		$prefix = $LePressStudent->get_wpdb_prefix();
		
		$this->courses_table = $prefix.'courses';
		$this->assignments_table = $prefix.'assignments';
		$this->teachers_table = $prefix.'teachers';
		$this->courses_teacher_rel_table = $prefix.'courses_teacher_rel';
		$this->groups_table = $prefix.'groups';
		$this->classmates_table = $prefix.'classmates';
		$this->classmates_group_rel_table = $prefix.'classmates_group_rel';
		$this->wpdb = $wpdb;
	}

	/**
	 * Returns plain role number 
	 * @return int
	 */
	
	function getRole() {
		return 2;
	}

	/**
	 * Finish subscribing sequence
	 *
	 * This method gets called only on successful subscription
	 * @return local course id
	 */
	 
	function finishSubscribing($course_data) {
		if($course_data) {
			$course = $course_data->courseMeta;
			$course_exist = $this->getActiveCourseByURL($course->url);
			if(!$course_exist) {
				$data = array('course_name' => (string) $course->name, 'course_url' => (string) $course->url, 'status' => (string) $course->extras->{'course-status'}, 'accept_key' => (string) $course->extras->{'accept-key'}, 'message' => (string) $course->extras->{'lepress-message'});
				$result = $this->wpdb->insert($this->courses_table, $data);
				$local_course_id = $this->wpdb->insert_id;
				if($result) {
					//Add teachers
					foreach($course->teachers->teacher as $teacher) {
						$teacher_attr = $teacher->attributes();
						$teacher_ext_id = (int) $teacher_attr['id'];
						$this->addUpdateTeacher($this->wpdb->insert_id, $teacher_ext_id, $teacher->firstname, $teacher->lastname, $teacher->email, $teacher->organization);
					}
				}
				return $local_course_id;
			}
		}
	}

	/**
	 * Subscribe to course
	 * 
	 * This method does all the subscribing part, if success "finishSubscribing" method is called
	 * to handle XML returned by teacher blog. It also handles easy subscription method.
	 * @return result code
	 */
	 
	function subscribe($course_url, $course_message = '', $simple_subscribe = false) {
		global $LePress;
		//Now send actual request to Teacher blog
		$current_user = $LePress->getBlogOwnerUser();

		$studentRequest = new LePressRequest($this->getRole(), 'subscribe');
		$studentRequest->addParams(array('firstname' => !empty($current_user->user_firstname) ? $current_user->user_firstname : $current_user->user_login, 'lastname' => $current_user->user_lastname, 'email' => $current_user->user_email, 'lepress-message' => $course_message));

		if($has_key = $this->getQueryParam($course_url, 'key')) {
			$studentRequest->addParam('invite-key', $has_key);
		}
		$studentRequest->doPost($course_url);
		
		//If we are dealing with easy subscription method - request from widget or blog url
		if($simple_subscribe) {
			if($studentRequest->getStatusCode() == 202) {
				$course_data = @simplexml_load_string($studentRequest->getBody());
				$local_course_id = $this->finishSubscribing($course_data);
				$this->refreshAssignments($local_course_id);
				$this->refreshClassmates($local_course_id);
				return true;
			} elseif($studentRequest->getStatusCode() == 409) {
				return 2;
			} elseif($studentRequest->getStatusCode() == 405) {
				return 4;
			} else {
				return false;
			}
		}
		
		//Otherwise we are doing regular subscription
		switch($studentRequest->getStatusCode()) {
			case 202:
				//Everything went as it should, let's proceed
				$course_data = @simplexml_load_string($studentRequest->getBody());
				$local_course_id = $this->finishSubscribing($course_data);
				$LePress->echoNoticeDiv(__('You have successfully subscribed to course', lepress_textdomain));
				//Update assignments and classmates after successful subscription too, IF course->status == 1
				//this means IF open access to course, no moderation on teacher side
				$this->refreshAssignments($local_course_id);
				$this->refreshClassmates($local_course_id);
				break;
			case 404:
				$LePress->echoNoticeDiv(__('This course does not exist', lepress_textdomain), true);
				break;
			case 401:
				$LePress->echoNoticeDiv(__('Invitation key is expired or sent for another email', lepress_textdomain), true);
				break;
			case 405:
				$LePress->echoNoticeDiv(__('This course does not accept any new subscriptions', lepress_textdomain), true);
				break;
			case 412:
				$LePress->echoNoticeDiv(__('Could not establish secure connection', lepress_textdomain), true);
				break;
			case 409:
				$LePress->echoNoticeDiv(__('You are already subscribed on this course', lepress_textdomain), true);
				break;
			case 500:
				$LePress->echoNoticeDiv(__('Subscriber with the same email address already subcribed', lepress_textdomain), true);
				break;
			case 400:
				$LePress->echoNoticeDiv(__('You cannot subcribe on your own course', lepress_textdomain), true);
				break;
			default:
				$LePress->echoNoticeDiv(__('Something went wrong... Try again', lepress_textdomain), true);
		}
	}

	/**
	 * Get query parameter from the URL
	 * @return string param value or boolean false
	 */
	 
	function getQueryParam($url, $param) {
		$query_params = explode("&", parse_url($url, PHP_URL_QUERY));
		if(count($query_params) > 0) {
			foreach($query_params as $param_pair) {
				$param_parts = split("=", $param_pair);
				if($param_parts[0] == $param) {
					if(isSet($param_parts[1])) {
						return $param_parts[1];
					}
				}
			}
		}
		return false;
	}

	/**
	 * Unsubscribe or cancel subscription
	 */
	 
	function unsubscribe($local_course_id, $canceled = false, $uninstall = false) {
		global $LePress;
		if($local_course_id <= 0) {
			return false;
		}
		/* First request to teacher blog, if teacher blog returns 202/404 HTTP code, proceed on student side */
		$course = $this->getCourse($local_course_id);
		if($course && $course->archived == 0) {
			$studentRequest = new LePressRequest($this->getRole(), 'unsubscribe');
			$studentRequest->addParam('accept-key', $course->accept_key);
			if($uninstall) {
				$studentRequest->setBlocking('false');
			}
			$studentRequest->doPost($course->course_url);
			switch($studentRequest->getStatusCode()) {
				case 202:
						if(!$canceled) {
							//archive course on local database
							$this->archiveCourse($local_course_id);
						} else {
							$this->removeCourse($local_course_id);
						}
					$LePress->echoNoticeDiv(__('You have successfully unsubscribed from the course.', lepress_textdomain));
					break;
				case 500:
					$LePress->echoNoticeDiv(__('Subscription found, but could not remove: HTTP Status 500', lepress_textdomain));
					break;
				case 404:
					//archive course on local database
					$this->archiveCourse($local_course_id);
					break;
				case 412:
					$LePress->echoNoticeDiv(__('Unsubscribing failed: HTTP Status 412', lepress_textdomain));
					break;
			}
		}
	}

	/**
	 * Set course archived flag
	 */
	 
	function archiveCourse($local_course_id) {
		$data = array('archived' => 1);
		$where = array('ID' => $local_course_id);
		$this->wpdb->update($this->courses_table, $data, $where);
	}

	/**
	 * Remove course from databse 
	 */
	 
	function removeCourse($local_course_id) {
		$this->wpdb->query('DELETE FROM '.$this->courses_table.' WHERE ID = "'.esc_sql($local_course_id).'"');
	}

	/**
	 * Update courses metadata, triggered by cron or finishSubcribing method
	 */
	 
	function refreshCoursesMeta() {
		$course_teacher_ids = array();
		
		foreach($this->getCourses() as $course) {
			if(!$course->archived && ($course->status > -1)) {
				$local_course_id = $course->ID;

				$studentRequest = new LePressRequest($this->getRole(), 'getCourseMeta');
				$studentRequest->addParam('accept-key', $course->accept_key);
				$studentRequest->doPost($course->course_url);

				if($studentRequest->getStatusCode() == 200) {
					$course = @simplexml_load_string($studentRequest->getBody())->courseMeta;
					if($course) {
						foreach($course->teachers->teacher as $teacher) {
							$teacher_attr = $teacher->attributes();
							$teacher_ext_id = $teacher_attr['id'];
							$course_teacher_ids[] = $teacher_ext_id;
							$this->addUpdateTeacher($local_course_id, $teacher_ext_id, $teacher->firstname, $teacher->lastname, $teacher->email, $teacher->organization);
						}
						$data = array('course_name' => $course->name, 'course_url' => $course->url);
						$where = array('ID' => $local_course_id);
						$this->wpdb->update($this->courses_table, $data, $where);
						//If course has been closed, archived it
						if($course->is_closed == 1) {
							$this->archiveCourse($local_course_id);
						}
					}
				} elseif($studentRequest->getStatusCode() == 404 || $studentRequest->getStatusCode() == 412) {
					//If course is not found anymore, set it as archived - teacher has disabled LePress teacher role or deleted category
					$this->archiveCourse($local_course_id);
				}
			}
		}
		//Clear broken relations
		$this->clearTeachers($course_teacher_ids);
	}

	/**
	 * Clear declined courses from database
	 */
	 
	function clear($action) {
		switch($action) {
			case 'declined':
				$this->wpdb->query('DELETE FROM '.$this->courses_table.' WHERE status = "-1"');
				break;
		}
	}
	
	/**
	 * Unsubscribe all active courses and Mark courses as archived 
	 */
	
	function unsubscribeAllActive() {
		//iterate trough all the active courses
		foreach($this->getApproved() as $course) {
			$this->unsubscribe($course->ID, true);
		}
		
		//iterate trough all the pending courses
		foreach($this->getPending() as $course) {
			$this->cancelSubscription($course->ID, true);
		}
	}

	/**
	 * Wrapper function for unscribe method call
	 *
	 * Cancel subscription
	 */
	
	function cancelSubscription($local_course_id, $uninstall = false) {
		$this->unsubscribe($local_course_id, true, $uninstall);
	}
	
	/**
	 * Add OR update teacher data
	 */

	function addUpdateTeacher($local_course_id, $ext_teacher_id, $firstname, $lastname, $email, $organization) {
		$data = array('ext_user_id' => (string) $ext_teacher_id, 'firstname' => (string) $firstname, 'lastname' => (string) $lastname, 'email' => (string) $email, 'organization' => (string) $organization);
		$added = $this->wpdb->insert($this->teachers_table, $data);
		if(!$added) {
			$where = array('ext_user_id' => $ext_teacher_id);
			$this->wpdb->update($this->teachers_table, $data, $where);
		}
		//Add relation between course and teacher
		$data = array('rel_courses_id' => $local_course_id, 'rel_teacher_id' => $ext_teacher_id);
		$this->wpdb->insert($this->courses_teacher_rel_table, $data);
	}

	/**
	 * Clear unrelated teachers from database
	 */
	 
	function clearTeachers($teacher_ids) {
		if(count($teacher_ids) > 0) {
			$this->wpdb->query('DELETE FROM '.$this->teachers_table.' WHERE ext_user_id NOT IN ('.esc_sql(implode(',', $teacher_ids)).')');
		}
	}
	
	/**
	 * Update subscription status, usually triggered after teacher action accept/decline student
	 * @return sql update status
	 */

	function updateStatus($course_url, $course_status, $accept_key, $message) {
		switch($course_status) {
			case 0:
					$data = array('status' => -1, 'message' => $message);
				break;
			case 2:
					$data = array('archived' => 1);
				break;
			default:
					$data = array('status' => $course_status);
				break;
		}
		$where = array('course_url' => $course_url, 'accept_key' => $accept_key);
		return $this->wpdb->update($this->courses_table, $data, $where);
	}
	
	/**
	 * Verify teacher access to student's blog using course url and accept_key
	 * @return local course id
	 */
	 
	function verifyAccess($course_url, $accept_key) {
		return $this->wpdb->get_var('SELECT ID FROM '.$this->courses_table.' WHERE course_url = "'.esc_sql($course_url).'" AND accept_key = "'.esc_sql($accept_key).'" AND status > -1');
	}
	
	/**
	 * Verify teacher access to student's blog using md5 hash from course_url and accept_key
	 * @return local course id
	 */
	
	function verifyAccessMD5($md5_key) {
		return $this->wpdb->get_var('SELECT ID FROM '.$this->courses_table.' WHERE md5(concat(course_url, accept_key)) = "'.esc_sql($md5_key).'" AND status > -1');
	}
	
	/**
	 * Send submission to teacher blog
	 *
	 * Send submission to teacher blog, post data includes - post url, post content, post_id, accept_key
	 */
	
	function sendAnswerURL($meta_data, $post) {
		$course = $this->getCourse($meta_data->course_id);
		if($course) {
			global $LePress;
			$studentRequest = new LePressRequest($this->getRole(), 'addAnswer');
			$studentRequest->addParams(array('post_id' => $meta_data->post_id, 'answer-url' => get_permalink($post->ID), 'answer-content' => htmlspecialchars($LePress->mb_cutstr($post->post_content), ENT_COMPAT, $LePress->getBlogCharset(), false), 'answer-post-id' => $post->ID, 'accept-key' => $course->accept_key));
			$studentRequest->doPost($course->course_url);
		}
	}

	/**
	 * Add feedback to the submission
	 *
	 * Triggered by teacher blog request, if teacher gives feedback. Creates new comment on student blog and saves some metadata for that comment
	 */
	 
	function addFeedback($local_post_id, $teacher_id, $accept_key, $feedback, $grade) {
		global $LePress;
		$this->setGrade($local_post_id, $grade);
		$teacher = $this->getTeacherByID($teacher_id, $accept_key);
		$comment = $LePress->fetchComment($local_post_id, $teacher->email, 'LePressTeacher');
		$time = current_time('mysql');
		$data = array(
		    'comment_post_ID' => $local_post_id,
		    'comment_author' => $teacher->lastname.' '.$teacher->firstname,
		    'comment_author_email' => $teacher->email,
		    'comment_author_url' => $teacher->course_url,
		    'comment_content' => $feedback,
		    'comment_agent' => 'LePressTeacher',
		    'comment_type' => '',
		    'comment_date' => $time,
		    'comment_parent' => 0,
		    'user_id' => 0,
		    'comment_approved' => 1);
		if(!$comment) {
			$comment_id = wp_insert_comment($data);
			update_comment_meta($comment_id, 'lepress-read', '1');
		} else {
			$deleted = wp_delete_comment($comment->comment_ID, true);
			if($deleted) {
				$comment_id = wp_insert_comment($data);
				update_comment_meta($comment_id, 'lepress-read', '1');
			}
		}
	}
	
	/**
	 * Get is post assignment answer
	 * @return metadata object
	 */
	 
	function isAssignmentAnswer($post_id) {
		return is_object(get_post_meta($post_id, '_lepress-assignment-meta', true));
	}

	/**
	 * Get assignment status
	 *
	 * @return post id or boolean false
	 */
	 
	function getAssignmentStatus($post_id, $draft_allowed = false) {
		$posts = get_posts(array('meta_query' => array(array('key' => '_lepress-assignment-meta')), 'post_status' => 'publish|private|password|draft', 'numberposts' => -1));
		foreach($posts as $post) {
			$meta_data = get_post_meta($post->ID, '_lepress-assignment-meta', true);
			$post_status = $draft_allowed ? 'draft' : 'publish';
			if($meta_data->post_id == $post_id && ($post->post_status == 'publish' || $post->post_status == 'private' || $post->post_status == 'password' || $post->post_status == $post_status)) {
				return $post->ID;
			}
		}
		return false;
	}

	/**
	 * Get submission grade
	 * @return string
	 */
	 
	function getGrade($local_post_id) {
		if($local_post_id) {
			$meta_data = get_post_meta($local_post_id, '_lepress-assignment-meta', true);
			
			if(isSet($meta_data->grade)) {
				return $meta_data->grade;
			}
		}
		return "NA";
	}

	/**
	 * Set submission grade, called from addFeedback method
	 */
	 
	function setGrade($local_post_id, $grade) {
		if($local_post_id) {
			$meta_data = get_post_meta($local_post_id, '_lepress-assignment-meta', true);
			if($meta_data) {
				$meta_data->grade = $grade;
			} else {
				$meta_data = (object) array('grade' => $grade);
			}
			update_post_meta($local_post_id, '_lepress-assignment-meta', $meta_data);
		}
	}
	
	/**
	 * Get all the assignments for course
	 *
	 * Called by assignments.php page
	 * @return SQL results (objects)
	 */

	function getAssignments($course) {
		if(is_int($course)) {
			$course = $this->getCourse($course);
		}
		return $this->wpdb->get_results('SELECT post_id, title, excerpt, start_date, end_date, url FROM '.$this->assignments_table.' WHERE course_id = '.esc_sql($course->ID));
	}

	/**
	 * Update assignments data
	 *
	 * This method is called by LePress cron periodically. Updates assignments data for all the courses
	 */
	 
	function refreshAssignments($by_course_id = false) {
		$courses = array();
		if($by_course_id) {
			$course = $this->getCourse($by_course_id);
			if($course->status == 1) {
				$courses[] = $course;
			}
		} else {
			$courses = $this->getApproved();
		}
		
		//Iterate through courses
		foreach($courses as $course) {
			$studentRequest = new LePressRequest($this->getRole(), 'getAssignments');
			$studentRequest->addParam('accept-key', $course->accept_key);
			$studentRequest->doPost($course->course_url);

			if($studentRequest->getStatusCode() == 200) {
				$xml = @simplexml_load_string($studentRequest->getBody());
				if($xml) {
					$posts_found_ids = array();
					//Iterate through found assignments and update database
					foreach($xml->assignment as $assignment) {
						$attrs = $assignment->attributes();
						$post_id = (int) $attrs['post_id'];
						$title = (string) $assignment->title;
						$url = (string) $assignment->url;
						$excerpt = (string) $assignment->excerpt;
						$start_date = date('Y-m-d', strtotime((string) $assignment->start_date));
						$end_date = date('Y-m-d', strtotime((string) $assignment->end_date)).' 23:59:59';
	
						$data = array('post_id' => $post_id, 'title' => $title, 'excerpt' => $excerpt, 'url' => $url, 'start_date' => $start_date, 'end_date' => $end_date, 'course_id' => $course->ID);
						$result = $this->wpdb->insert($this->assignments_table, $data);
						if(!$result) {
							$where = array('post_id' => $post_id, 'course_id' => $course->ID);
							$this->wpdb->update($this->assignments_table, $data, $where);
						}
						$posts_found_ids[] = $post_id;
					}
					if(count($posts_found_ids) > 0) {
						//Delete assignments, which are not available anymore
						$this->wpdb->query('DELETE FROM '.$this->assignments_table.' WHERE post_id NOT IN ('.esc_sql(implode(",", $posts_found_ids)).') AND course_id = '.esc_sql($course->ID));
					}
				}
			} elseif($studentRequest->getStatusCode() == 404 || $studentRequest->getStatusCode() == 412) {
				//If course is not found anymore, set it as archived - teacher has disabled LePress teacher role or deleted category
				$this->archiveCourse($course->ID);
			}
		}
	}

	/**
	 * Update classmates data
	 *
	 * This method is called by LePress cron periodically. Updates classmates data for all the courses
	 */
	 
	function refreshClassmates($by_course_id = false) {
		$courses = array();
		if($by_course_id) {
			$course = $this->getCourse($by_course_id);
			if($course->status == 1) {
				$courses[] = $course;
			}
		} else {
			$courses = $this->getApproved();
		}
		//Iterate through all the courses
		foreach($courses as $course) {
			$studentRequest = new LePressRequest($this->getRole(), 'getClassmates');
			$studentRequest->addParam('accept-key', $course->accept_key);

			$studentRequest->doPost($course->course_url);
			if($studentRequest->getStatusCode() == 200) {
				$xml = @simplexml_load_string($studentRequest->getBody());
				//Delete previous data, then add new
				$this->wpdb->query('DELETE FROM '.$this->classmates_table.' WHERE course_id = '.esc_sql($course->ID));
				if($xml) {
					//Iterate through all the classmates and update database
					foreach($xml->classmate as $classmate) {
						$firstname = (string) $classmate->firstname;
						$lastname = (string) $classmate->lastname;
						$email = (string) $classmate->email;
						$blog_url = (string) $classmate->blog_url;
	
						$data = array('firstname' => $firstname, 'lastname' => $lastname, 'blog_url' => $blog_url, 'email' => $email, 'course_id' => $course->ID);
						$result = $this->wpdb->insert($this->classmates_table, $data);
						if(!$result) { //Probably never executed, due to DELETE query, was used in first prototype
							$where = array('blog_url' => $blog_url, 'course_id' => $course->ID);
							$this->wpdb->update($this->classmates_table, $data, $where);
						}
					}
				}
			} elseif($studentRequest->getStatusCode() == 404 || $studentRequest->getStatusCode() == 412) {
				//If course is not found anymore, set it as archived - teacher has disabled LePress teacher role or deleted category
				$this->archiveCourse($course->ID);
			}
		}
	}

	/**
	 * Get classmates by group
	 * @param $group_id Group id to look for
	 */
	 
	function getClassmatesByGroup($group_id) {
		return $this->wpdb->get_results('SELECT  '.$this->classmates_table.'.id as mate_id, firstname, lastname, email, blog_url, course_name, course_url FROM '.$this->classmates_table.','.$this->courses_table.','.$this->groups_table.' WHERE '.$this->classmates_table.'.course_id = '.$this->courses_table.'.id  AND '.$this->groups_table.'.course_id = '.$this->courses_table.'.id AND status= 1 AND archived = 0 AND '.$this->groups_table.'.id = '.esc_sql($group_id));
	}
	
	/**
	 * Get classmate by ID
	 * @param $mate_id Mates id to look for
	 */

	function getClassmateByID($mate_id) {
		return $this->wpdb->get_row('SELECT  '.$this->classmates_table.'.id as mate_id, firstname, lastname, email, blog_url FROM '.$this->classmates_table.','.$this->courses_table.' WHERE course_id = '.$this->courses_table.'.id AND status= 1 AND archived = 0 AND '.$this->classmates_table.'.id = '.esc_sql($mate_id));
	}
	
	/**
	 * Get all the classmates on the course
	 */

	function getClassmates($local_course_id) {
		return $this->wpdb->get_results('SELECT '.$this->classmates_table.'.id as mate_id, firstname, lastname, email, blog_url, course_name, course_url FROM '.$this->classmates_table.','.$this->courses_table.' WHERE '.$this->classmates_table.'.course_id = '.$this->courses_table.'.id AND '.$this->courses_table.'.id = '.esc_sql($local_course_id).' AND status= 1 AND archived = 0 ORDER BY lastname ASC');
	}
	
	/**
	 * Get assignment by ID
	 *
	 * This method retrieves assignment from teacher blog, to be displayed new post page / when writing a submission
	 * @return XML OR boolean false
	 */

	function getAssignmentByID($post_id, $local_course_id) {
		$course = $this->getCourse($local_course_id);
		if($course) {
			$studentRequest = new LePressRequest($this->getRole(), 'getAssignmentByID');
			$studentRequest->addParam('accept-key', $course->accept_key);
			$studentRequest->addParam('post-id', $post_id);
			$studentRequest->doPost($course->course_url);
			$xml = @simplexml_load_string($studentRequest->getBody());
			if($xml) {
				return $xml->assignment;
			}
		}
		return false;
	}

	/**
	 * Get all the courses
	 */
	 
	function getCourses() {
		return $this->wpdb->get_results('SELECT * FROM '.$this->courses_table);
	}
	
	/**
	 * Get course by id
	 */

	function getCourse($local_course_id) {
		return $this->wpdb->get_row('SELECT * FROM '.$this->courses_table.' WHERE ID = "'.esc_sql($local_course_id).'"');
	}
	
	/**
	 * Get active course by URL
	 */
	 
	function getActiveCourseByURL($course_url) {
		return $this->wpdb->get_row('SELECT * FROM '.$this->courses_table.' WHERE course_url = "'.esc_sql($course_url).'" AND status > -1 AND archived = 0');
	}
	
	/**
	 * Get all approved courses
	 */

	function getApproved() {
		return $this->wpdb->get_results('SELECT * FROM '.$this->courses_table.' WHERE status = 1 AND archived = 0');
	}

	/**
	 * Get all the teachers on the course
	 */
	 
	function getTeachers($local_course_id) {
		return $this->wpdb->get_results('SELECT firstname, lastname, email, organization, course_url FROM '.$this->courses_table.', '.$this->teachers_table.', '.$this->courses_teacher_rel_table.' WHERE status = 1 AND '.$this->courses_table.'.ID = rel_courses_id AND rel_teacher_id = ext_user_id AND rel_courses_id = "'.esc_sql($local_course_id).'" ORDER BY lastname ASC');
	}

	/**
	 * Get teacher by ID
	 */
	 
	function getTeacherByID($teacher_id, $accept_key) {
		return $this->wpdb->get_row('SELECT firstname, lastname, email, organization, course_url FROM '.$this->courses_table.', '.$this->teachers_table.', '.$this->courses_teacher_rel_table.' WHERE status = 1 AND '.$this->courses_table.'.ID = rel_courses_id AND rel_teacher_id = ext_user_id AND ext_user_id = "'.esc_sql($teacher_id).'" AND accept_key = "'.esc_sql($accept_key).'"');
	}
	
	/**
	 * Get all the pending courses
	 */

	function getPending() {
		return $this->wpdb->get_results('SELECT * FROM '.$this->courses_table.' WHERE status = 0 AND archived = 0');
	}
	
	/**
	 * Get pending courses count
	 * @return int
	 */

	function getPendingCount() {
		return $this->wpdb->get_var('SELECT count(ID) FROM '.$this->courses_table.' WHERE status = 0 AND archived = 0');
	}

	/**
	 * Get pending assignments count
	 * @return int
	 */
	 
	function getPendingAssignmentsCount() {
		$count = 0;
		foreach($this->getApproved() as $course) {
			$assignments = $this->getAssignments($course);
			foreach($assignments as $assignment) {
				if(!$this->getAssignmentStatus($assignment->post_id)) {
					$count++;
				}
			}
		}
		return $count;
	}
	
	/**
	 * Get all archived courses
	 */
	 
	function getArchived() {
		return $this->wpdb->get_results('SELECT * FROM '.$this->courses_table.' WHERE status = 1 AND archived = 1');
	}

	/**
	 * Get all declined courses
	 */
	 
	function getDeclined() {
		return $this->wpdb->get_results('SELECT * FROM '.$this->courses_table.' WHERE status = -1 AND (archived = 0 OR archived = 1)');
	}

	/**
	 * Get answer post by ID
	 * @return XML or boolean false
	 */
	 
	function getAnswerByPostID($post_id) {
		if(intval($post_id) > 0) {
			global $LePress;
			$post = get_post($post_id);
			if($post) {
				$xml = new SimpleXMLElement(xml_root);
				$answer = $xml->addChild('answer');
				$answer->addAttribute('id', $post_id);
				$answer->addChild('title', '<![CDATA['.$LePress->safeStringInput($post->post_title).']]>');
				$answer->addChild('url',  '<![CDATA['.$LePress->safeStringInput(get_permalink($post_id)).']]>');
				//$answer->addChild('content',  '<![CDATA['.$LePress->safeStringInput(apply_filters('the_content', $post->post_content)).']]>'); //This is not used for now, with new iframe solution
				return htmlspecialchars_decode($xml->asXML());
			}
		}
		return false;
	}
}
?>