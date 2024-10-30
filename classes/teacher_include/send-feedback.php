<?php

/**
 * Send feedback to student
 *
 * Handles feedback and grade
 */

//Get some parameters from the URL
$course_id = intval($this->safeStringInput($_GET['c'], true));
$post_id = intval($this->safeStringInput($_GET['p'], true));
$subscription_id = intval($this->safeStringInput($_GET['s'], true));

//IF we have active course proceed
$course_meta = new CourseMeta($course_id);
if($course_meta->getIsCourse()) {
	$student = $course_meta->getStudentBySubscription($subscription_id);
	$student_answer = $course_meta->getStudentAssignmentAnswer($student, $post_id);
	
	//If we have post request, this means "Send feedback" button was pressed
	if(isSet($_POST)) {
		if($_POST['action'] == 'sendFeedback') {
			$feedback_status = $course_meta->sendFeedback($post_id, $student, $student_answer, $_POST['feedback'], $_POST['grade']);	
		}
	}
	
	//Get some metadata from DB
	$meta_data = get_post_meta($post_id, '_lepress-student-'.md5($post_id.$student->email), true);
	$display_name_order = $this->getDisplayNames();
	$name = $display_name_order == 1 ? $student->first_name.' '.$student->last_name : $student->last_name.' '.$student->first_name;
	
	//Get some posts
	$posts_query = get_posts(array('meta_query' => array(array('key' => '_is-lepress-assignment', 'value' => 1)), 'p' => $post_id, 'category' => $course_id, 'post_status' => 'publish', 'numberposts' => 1));
	$post = $posts_query[0];
	
	/* Display some feedback to user */
	$js_redirect = false;
	if($feedback_status == 200) { //Everything went well
		$str = str_replace('{name}', '<b>'.$name.'</b>', __('Your feedback to student {name} is sent. Redirecting to the assignment page...', lepress_textdomain));
		$this->echoNoticeDiv($str);
		//Delete comment metadata lepress-read
		$comment = $this->fetchComment($post_id, $student->email, 'LePressStudent');
		delete_comment_meta($comment->comment_ID, 'lepress-read');
		update_comment_meta($comment->comment_ID, 'lepress-feedback-given', 1);
		$js_redirect = true;
	} elseif($feedback_status == -1) { //User not a teacher on this course
		$this->echoNoticeDiv(__('You are <b>not teacher</b> on this course, sorry... Cannot allow sending feedback.', lepress_textdomain), true);
	} elseif($feedback_status == -2) { //Course has been closed
		$comment = $this->fetchComment($post_id, $student->email, 'LePressStudent');
		delete_comment_meta($comment->comment_ID, 'lepress-read');
		update_comment_meta($comment->comment_ID, 'lepress-feedback-given', 1);
		$str = __('Your feedback was not sent, because course of this assignment is set as closed', lepress_textdomain);
		$this->echoNoticeDiv($str, true);
	} elseif($feedback_status == -3) { //No changes end feedback, text/grade not sending request
		$str = __('No changes detected, not sending feeback. Redirecting to the assignment page...', lepress_textdomain);
		$this->echoNoticeDiv($str, false);
		$js_redirect = true;
	} elseif(isSet($feedback_status)) { //General error, something went terribly wrong
		$str = str_replace('{name}', '<b>'.$name.'</b>', __('Your feedback to student {name} was <b>not sent</b>. Try again...', lepress_textdomain));
		$this->echoNoticeDiv($str, true);
	}
	
	$back_url = admin_url().'admin.php?page=lepress-classbook&p='.$post_id.'&c='.$course_id;
	//Redirect to assignment page with JS code
	if($js_redirect) {
	?>
		<script type="text/javascript">
			setTimeout(function() {
					window.location = "<?php echo $back_url; ?>";
				}, 2250);
		</script>
	<?php
		}
	?>
	
	<div class="wrap">
		<?php echo '<p><a href="'.$back_url.'">'.__('Go back').'</a></p>'; ?>
	
	<h2><?php _e('Write assesment feedback', lepress_textdomain); ?></h2>
	
	<?php if($post) { ?>
		<div id="poststuff" class="metabox-holder">
		
			<div id="post-body" class="meta-box">
		
			<div id="post-body-content">
				<div class="postbox">
					
					<h3><span><?php echo '<a href="'.get_permalink($post_id).'" target="_blank">'.$post->post_title; ?></a></span></h3>
					<div class="inside">
						<p>
						<?php
						if(!$js_redirect) {
							if($post) {
								$tmp_md5 = md5(time());
								update_post_meta($post_id, 'iframe-key', $tmp_md5);
								$post_if_src = add_query_arg(array('lepress-iframe' => 't-'.$tmp_md5), get_permalink($post_id));
								echo '<iframe id="post-content-iframe" height="30px" width="100%" scrolling="no" style="border: 1px solid #DFDFDF" frameborder="0" src="'.$post_if_src.'"></iframe>';
							} else {
								_e('Sorry, post not found...', lepress_textdomain);
							}
						} else {
							_e('Redirecting to the assignment page...', lepress_textdomain);
						}
						?>
						</p>
					</div>
	
				</div>
		</div>
	<?php 
		} else { 
		echo "<h3>".__('This is somewhat embarrasing, isn\'t it?', lepress_textdomain)."</h3> <p>".__('It seems we can\'t find what you\'re looking for.', lepress_textdomain)."</p>";	
	 } 
	?>
	
	
	<?php			
	if($post) {
	?>
	
		<form method="post">
	
		<input type="hidden" name="action" value="sendFeedback" />
	
		<div id="poststuff" class="metabox-holder">
	
		<div id="post-body" class="meta-box">
	
		<div id="post-body-content">
			<div class="postbox">
				<h3><span><?php _e('Submission of', lepress_textdomain); ?> <?php echo $name; echo ' - <a href="'.$student_answer->url.'" target="_blank">'.$student_answer->title; ?></a></span></h3>
				<div class="inside">
					<p>
					<?php
						if(!$js_redirect) {
							$md5_key = $course_meta->getMD5AccessKey($subscription_id);
							if($md5_key) {
								$iframe_url_student = add_query_arg(array('lepress-iframe' => 's-'.$meta_data['post_id'].'-'.$md5_key), $meta_data['post_url']);
								$md5_key_local = md5(time());
								//Let's cache student iframe url for wrapper
								set_transient($md5_key_local, $iframe_url_student, 120);
								$iframe_url_local = add_query_arg(array('lepress-iframe' => 't-'.$md5_key_local), home_url());
								echo '<iframe id="student-answer-iframe" height="30px" width="100%" scrolling="no" style="border: 1px solid #DFDFDF" frameborder="0" src="'.$iframe_url_local.'"></iframe>';
							}
						} else {
							_e('Redirecting to the assignment page...', lepress_textdomain);
						}
					?>
					</p>
				</div>
			</div>
	
			<div class="postbox">
	
				<h3><span><?php _e('Feedback', lepress_textdomain); ?></span></h3>
	
				<div class="inside">
	
					<textarea style="color:black;" rows="10" cols="40" name="feedback" tabindex="2" id="content"><?php echo $meta_data ? $meta_data['feedback'] : ''; ?></textarea>
	
				</div>
	
				<div class="inside">
	
					<p class="meta-options">
	
						<label class="selectit"><?php _e('Grade', lepress_textdomain); ?>
	
							<input name="grade" size="1" tabindex="1" value="<?php echo $meta_data ? $meta_data['grade'] : ''; ?>" id="submission_grade" type="text" maxlength="2">
	
						</label>
	
					</p>
	
				</div>
	
				<div class="inside">
	
					<p class="meta-options">
	
						<label><?php _e('Feedback to submission', lepress_textdomain); ?>: 
	
							<?php
	
								echo '<a href="'.$student_answer->url.'" target="_blank">'.urldecode($student_answer->url).'</a>';
	
							?>
	
						</label>
	
					</p>
	
				</div>
	
			</div>
	
			<div align='right'>
	
				<input type='submit' class="button-primary" value='<?php _e('Send feedback', lepress_textdomain); ?>' />
	
			</div>
	
		</div>
	
		</div>
	
		</div>
	
	</form>
<?php
		} else {
			echo "<h3>".__('This is somewhat embarrasing, isn\'t it?', lepress_textdomain)."</h3> <p>".__('It seems we can\'t find what you\'re looking for.', lepress_textdomain)."</p>";
		}
	} else {
		echo "<h3>".__('This is somewhat embarrasing, isn\'t it?', lepress_textdomain)."</h3> <p>".__('It seems we can\'t find what you\'re looking for.', lepress_textdomain)."</p>";
	}
?>

</div>