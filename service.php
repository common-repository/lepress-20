<?php

/**
 * LePress service class
 *
 * Handles all the data interaction between blogs
 *
 * @author Raido Kuli
 */

class LePressService {

	/**
	 * Init add actions for template_redirect and query_vars
	 */

	function __construct() {
		add_filter('query_vars', array(&$this, 'service_add_trigger'));
		add_action('template_redirect', array(&$this,'service_trigger_check'));
		add_action('wp_head', array(&$this, 'displayMessage'));
	}
	
	/**
	 * IFrame HTML output handler
	 *
	 * This method handles HTML output for iFrame. "wp_head" action is called and collected using
	 * output buffering, then depending on the $post ja $student flag, post from teacher or student blog will be retrieved
	 *
	 * @return HTML string
	 */
	
	function handleIFrame($post, $student = false) {
		require_once('libs/simple_html_dom.php');				
		ob_start();
		do_action('wp_head');
		$head_str = ob_get_contents();
		ob_clean();
		
		//Get post content, from post object
		if($post) {
			if($student) {
				global $LePressStudent;
				$status =  $LePressStudent->subscriptions->isAssignmentAnswer($post->ID);
				if($status) {
					$content = apply_filters('the_content', $post->post_content);
				} else {
					$content = __("Requested student submission was not found in student's blog", lepress_textdomain);
				}
			} else {
				$content = apply_filters('the_content', $post->post_content);
			}
		} else {
			if($student) {
				$content = __("Requested student submission was not found in student's blog", lepress_textdomain);
			} else {
				$content = __('Could not load post content, try again...', lepress_textdomain);
			}
		}
		$html = '<!DOCTYPE HTML><html><head>'.$head_str.'<script type="text/javascript" src="'.lepress_http_abspath.'js/iframe-expander.js"></script>';
		$html .= '<link rel="stylesheet" href="'.home_url().'/wp-admin/load-styles.php?load=global" type="text/css" media="all" /></head>';
		$html .= '<body style="min-height: 30px; overflow: hidden; background-color: #FFFFFF; margin:10px;"></body></html>';
		
		$html_dom = str_get_html($html);
		
		/* Remove all link tags which are not stylesheet */
		foreach($html_dom->find('link') as $link) {
			if($link->rel != "stylesheet") {
				$link->outertext = '';
			}
		}
		/* Remove all meta tags */
		foreach($html_dom->find('meta') as $meta) {
				$meta->outertext = '';
		}
		/* Remove all comments */
		foreach($html_dom->find('comment') as $comment) {
				$comment->outertext = '';
		}
		
		$html_dom->find('body', 0)->innertext = $content;
		//Finally output the HTML string
		echo (string) $html_dom;
		//Clear memory
		$html_dom->clear(); 
		unset($html_dom);
	}
	
	/**
	 * Set additional query_vars
	 *
	 * @return query_vars
	 */
	 
	function service_add_trigger($vars) {
		$vars[] = 'lepress-service';
		$vars[] = 'lepress-add'; //parameter for simple subscribe method
		$vars[] = 'lepress-iframe';
		return $vars;
	}
	
	/**
	 * Service call handler
	 *
	 * Handles iFrame, teacher and student requests
	 */
	 
	function service_trigger_check() {
	
		/* If we are dealing with IFrame call */
		if(strlen(get_query_var('lepress-iframe')) >= 33) {
			$to_home = false;
			$var = get_query_var('lepress-iframe');
			$query = split("-", $var);
			$role = $query[0];
			switch($role) {
				case 't':	//teacher post request
					if(is_user_logged_in()) {
						global $wp_query;
						$post_id = $wp_query->get_queried_object()->ID;
						if($post_id) {
							$post = get_post($post_id);
							$meta_key = get_post_meta($post_id, 'iframe-key', true);
							if(isSet($query[1]) && $meta_key == $query[1]) {
								$this->handleIFrame($post);
							}
						} else {
							/* This IS a WRAPPER for iframe call from student blog
							* That way JavaScript iframe-expander.js still works,
							* because frame url local, although content is not.
							*/
							
							$get_student_if_url = get_transient($query[1]);
							if($get_student_if_url) {
								$req = wp_remote_get($get_student_if_url);
								if($req) {
									echo wp_remote_retrieve_body($req);
								} else {
									__("Requested student submission could not be loaded, try again...", lepress_textdomain);
								}
							} else {
								$to_home = true;
							}
						}
					} else {
						$to_home = true;
					}
					exit;
					break;
				case 's': //student post request
					global $LePressStudent;
					if($LePressStudent instanceOf LePressStudentRole) {
						$access = $LePressStudent->subscriptions->verifyAccessMD5($query[2]);
						if($access) {
							$post_id = isSet($query[1]) ? intval($query[1]) : false;
							if($post_id > 0) {
								$post = get_post($post_id);
								$this->handleIFrame($post, true);
							} else {
								$to_home = true;
							}
						} else {
							$to_home = true;
						}
					}
					break;
				default:
					$to_home = true;
					break;
			}
			if($to_home) {
				header('Location: '.home_url());
			}
			exit;
		}
		
		/* If we are dealing with reqular lepress-service call */
		
		if(intval(get_query_var('lepress-service')) == 1) {
			$this->handleRequest(); //This function call ends with exit();
		}
		
		/* If we are dealing with easy subscription form */
		if($course_url = get_query_var('lepress-add')) {
			$course_url = base64_decode($course_url);
			global $LePressStudent;
			if($LePressStudent instanceof LePressStudentRole) {
				//If not logged in, redirect to user's blogs
				if(!is_user_logged_in()) {
					$redirect = wp_login_url(add_query_arg(array('lepress-add' => base64_encode($course_url)), site_url()));
					header('Location:'.$redirect);
				} else {
				//Otherwise return result to the AJAX handler
					if(current_user_can('edit_posts')) {
						$current_user = wp_get_current_user();
						if(!empty($current_user->user_firstname) && !empty($current_user->user_lastname)) {
							$result = $LePressStudent->subscriptions->subscribe($course_url, '', true);
							if($result) {
								update_option('lepress-simple-subscribe-success', $result);
							} else {
								update_option('lepress-simple-subscribe-success', 0);
							}
						} else {
							update_option('lepress-simple-subscribe-success', 3);
						}
					}
					header('Location:'.site_url());
				}
			} else {
				header('Location:'.site_url());
			}
			exit();
		}
	}
	
	/**
	 * Easy subscriptions notice displayer
	 *
	 * This method is called, when student subscribes to course using blog url on the teacher widget
	 * and gets redirected to his/her blog
	 */
	
	function displayMessage() {
		$flag = get_option('lepress-simple-subscribe-success', -1);
		if($flag > -1) {
			if($flag == 1) {
				$txt = __('You have successfully confirmed your LePress course subscription!', lepress_textdomain);
			} elseif($flag == 2) {
				$txt = __('You are already subscribed on this course', lepress_textdomain);
			} elseif($flag == 4) {
				$txt = __('This course does not accept any new subscriptions', lepress_textdomain);
			} elseif($flag == 3) {
				$txt = '<span style="color: #FF0000"><strong>'.__('Subscribing failed', lepress_textdomain).'</strong></span><br />'.__('Your profile is not filled (firstname/lastname)', lepress_textdomain);
			} else {
				$txt = __('Confirming your LePress subscription failed! Try again...', lepress_textdomain);
			}
			echo '<div class="lepress-updated"><h3>'.$txt.'</h3></div>';
			delete_option('lepress-simple-subscribe-success');
		}
	}

	/**
	 * Get current request role
	 *
	 * @return int or boolean false
	 */
	 
	function getRole() {
		return $this->role ? $this->role : false;
	}

	/**
	 * Get current call action
	 *
	 * @return string or boolean false
	 */
	 
	function getAction() {
		return $this->action ? $this->action : false;
	}

	/**
	 * Handle request method
	 *
	 */
	 
	function handleRequest() {
		/* IF we have a POST request */
		if(isSet($_POST)) {
			//Store role and action values, if any
			$this->role = $_POST['lepress-role'] ? strip_tags($_POST['lepress-role']) : false;
			$this->action = $_POST['lepress-action'] ? strip_tags($_POST['lepress-action']) : false;
		}
		
		/* IF we a easy subscription form post call, handle that 
		 * Request is sent via AJAX
		 */
		if(isSet($_POST['simple-subscriber-blog']) || isSet($_POST['lepress_user_blog_id'])) {
			if(isSet($_POST['lepress_user_blog_id'])) {
				if(is_user_logged_in() && is_multisite()) {
					$current_user = wp_get_current_user();
					if($current_user->ID == intval($_POST['lepress_user_ID'])) {
						$blog_id_student = intval($_POST['lepress_user_blog_id']);
						foreach(get_blogs_of_user($current_user->ID) as $blog) {
							if($blog->userblog_id == $blog_id_student) {
								//It really is user's blog, let's switch
								$switched = switch_to_blog($blog_id_student, true);
								if($switched) {
									require_once('classes/student.php');
									$LePressStudent = new LePressStudentRole();
									if($LePressStudent instanceof LePressStudentRole) {
										$LePressStudent->runAfterInit();
										if(!empty($current_user->user_firstname) && !empty($current_user->user_lastname)) {
											$result = $LePressStudent->subscriptions->subscribe($_POST['course-url'], '', true);
											if($result && is_bool($result)) {
												echo 1; //Success, we are subscribed
											} elseif($result == 4) {
												echo 4; //Course locked by teacher
											} else {
												echo 0; //General error, already subscribed ?
											}
										} else {
											echo 2; //Profile not filled
										}
									}
								}
								break;
							}
						}
						//Return to current blog
						restore_current_blog();
					}
				}
			} else {
				//If not AJAX call, i.e user not logged in and subscribes using his blog url
				$blog_url = trim(strip_tags($_POST['simple-subscriber-blog']));
				if(filter_var($blog_url, FILTER_VALIDATE_URL)) {
					$course_ID = intval(trim($_POST['lepress-course-id']));
					if($course_ID > 0) {
						$course_meta = new CourseMeta($course_ID);
						if($course_meta->getIsCourse()) {
							$special_url = add_query_arg(array('lepress-add' => base64_encode(get_category_link($course_ID))), $blog_url);
							header('Location: '.$special_url);
						}
					}
				} else {
					echo 3; //Blog URL not valid
				}
			}
			exit;
		}
		
		//IF request role == 1 - this means teacher makes request to student blog
		if($this->getRole() == 1) {
			$this->parseTeacherRequest();
		}
		//IF request role == 2 - this means student makes request to teacher blog
		if($this->getRole() == 2) {
			$this->parseStudentRequest();
		}
		
		//IF request role == 22 - this means student makes request to other student's blog
		if($this->getRole() == 22) {
			$this->parseMateRequest();
		}
	}

	/**
	 * Student to student request handler
	 */
	 
	function parseMateRequest() {
		global $LePressStudent;
		switch($this->getAction()) {
			case 'inviteMeToGroup':
					$LePressStudent->groups->addMeToGroup($_POST['group_name'], $_POST['group_key'], $_POST['course_url']);
				break;
		}
		exit();
	}

	/**
	 * Teacher request to student handler
	 */

	function parseTeacherRequest() {
		global $LePressStudent;
		if($LePressStudent->subscriptions->verifyAccess($_POST['course-url'], $_POST['accept-key'])) {
			switch($this->getAction()) {
				case 'subscription-status-changed':
					$LePressStudent->subscriptions->updateStatus($_POST['course-url'], $_POST['course-status'], $_POST['accept-key'], $_POST['lepress-message']);
					break;
				case 'getAnswerByID':
					//Return answer data as XML, no content included
					if($answer = $LePressStudent->subscriptions->getAnswerByPostID($_POST['post_id'])) {
	                	echo $answer;
	                } else {
	                      header("HTTP/1.1 404 Not found");
	                }
					break;
				case 'addFeedback':
					$LePressStudent->subscriptions->addFeedback($_POST['post_id'], $_POST['teacher_id'], $_POST['accept-key'], $_POST['feedback'], $_POST['grade']);
					break;
			}
		} else {
			header('HTTP/1.1 401 Unauthorized');
		}
		exit();
	}
	
	/**
	 * Student request to teacher handler
	 */

	function parseStudentRequest() {
		if(($cat_id = get_query_var('cat')) > 0 && class_exists('CourseMeta')) {
			global $LePressTeacher;
			$course_meta = new CourseMeta($cat_id, $_POST['accept-key']);
			$blog_url_access = $course_meta->verifyAccess($_POST['lepress-blog'], $_POST['email'], $this->getAction());
			if($blog_url_access > -1) {
				$blog_url = $blog_url_access;
				switch($this->getAction()) {
					case 'subscribe':
						//If subscribe action
						$firstname = $_POST['firstname'];
						$lastname = $_POST['lastname'];
						$email = $_POST['email'];
						$invite_key = isSet($_POST['invite-key']) ? $_POST['invite-key'] : false;
						$message = $_POST['lepress-message'];
						$result = $course_meta->addSubscription($firstname, $lastname, $email, $blog_url, $message, $invite_key);
						if(!$result && is_bool($result)) {
							//Invite key check failed
							header('HTTP/1.1 401 Unauthorized');
						} elseif($result == -5) {
							header('HTTP/1.1 405 Method Not Allowed');
						} elseif($result <= -2 && $result > -5) {
							header('HTTP/1.1 500 Internal Server Error');
						}  else {
							header('HTTP/1.1 202 Accepted');
							echo $result; // Returning course meta to student
						}
						break;
					case 'getCourseMeta';
						echo $course_meta->getCourseMeta();
						break;
					case 'profileUpdated';
						$course_meta->updateStudentProfile($_POST['first_name'], $_POST['last_name'], $_POST['new_email'], $_POST['old_email']);
						header('HTTP/1.1 202 Accepted');
						break;
					case 'unsubscribe':
						$result = $course_meta->removeSubscription($blog_url, $_POST['accept-key']);
						if($result) {
							header('HTTP/1.1 202 Accepted');
						} else {
							header('HTTP/1.1 500 Internal Server Error');
						}
						break;
					case 'getAssignments':
		   				echo $course_meta->getAssignments();
						break;
					case 'getAssignmentByID':
		   				echo $course_meta->getAssignmentByID($_POST['post-id']);
						break;
					case 'addAnswer':
		   				$course_meta->addAnswer($_POST['post_id'], $_POST['answer-url'], $_POST['answer-content'], $_POST['answer-post-id'], $blog_url);
						break;
					case 'getClassmates':
			   			echo $course_meta->getClassmates($blog_url);
						break;
					/* This is disabled, because development of this feature was halted
					 * probably will be completed in near future
					case 'addMyGroup':
						$result = $course_meta->addStudentGroup($_POST['group_name'], $_POST['group_key'], $_POST['email']);
						if($result) {
							header('HTTP/1.1 201 Created');
						} else {
							header('HTTP/1.1 500 Internal Server Error');
						}
						break;
					case 'addMeToGroup':
						$course_meta->addMeToGroup($_POST['group_key'], $blog_url);
						break;
					case 'removeMyGroup':
						$result = $course_meta->removeStudentGroup($_POST['group_key'], $_POST['email']);
						if(!$result) {
							header('HTTP/1.1 500 Internal Server Error');
						}
						break;
						*/
					}
			} elseif($blog_url_access == -1)  {
				header("HTTP/1.1 404 Not found");
			} elseif($blog_url_access == -2) {
				header("HTTP/1.1 409 Conflict");
			} elseif($blog_url_access == -3) {
				header("HTTP/1.1 400 Bad Request");
			} elseif(!$blog_url_access && is_bool($blog_url_access)) {
				header('HTTP/1.1 412 Precondition Failed');
			}
		} else {
			header("HTTP/1.1 404 Not found");
		}
		exit();
	}
}
?>