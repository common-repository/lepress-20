<?php

/**
 * Subscriptions managament page
 *
 * Handles invite, accept/decline, remove
 *
 */

$display_name_order = $this->getDisplayNames();

$accepted_count = 0;
$declined_count = 0;
$invited_count = 0;
$failed_emails = array();

if($_POST['action'] == 'acceptUsers') {
	if(isSet($_POST['accept_decline'])) {
		foreach($_POST['accept_decline'] as $subscription_id => $data) {
			foreach($data as $cat_id => $response) {
				$course_meta = new CourseMeta($cat_id);
				if($course_meta instanceOf CourseMeta) {
					$action = $response['action'];
					$message = $this->safeStringInput($response['message'], true);

					switch($action) {
						case 2: //Decline student
							$declined_count++;
							$course_meta->setSubscriptionStatus($subscription_id, 0, $message);
							break;
						case 1: //Accept student
							$accepted_count++;
							$course_meta->setSubscriptionStatus($subscription_id, 1, $message);
							break;
					}
				}
			}
		}
	}
}

if($_POST['action'] == 'inviteStudents') {
	$emails = split("\n", $_POST['emails']);
	//Uploaded emails file handler
	if($_FILES["invite_emails_file"]["error"] == 0) {
		$emails_file = split("\n", file_get_contents($_FILES['invite_emails_file']['tmp_name']));
		$emails = array_merge($emails_file, $emails);	
	}
	/* Trim all the emails, otherwise array_unique failes */
	$email_trimmed = array();
	foreach($emails as $email) {
		$email_trimmed[] = trim($email);
	}
	//Use only unique emails
	$emails = array_unique($email_trimmed);
	$course_ids = $_POST['course_cat_id'];
	if(count($emails) > 0) {
		foreach($emails as $email) {
			//IF email is valid
			if(filter_var($email, FILTER_VALIDATE_EMAIL)) {
				$invited_count++;
				if(count($course_ids) > 0) {
					$message = '';
					//Iterate through all the courses and generate message body
					foreach($course_ids as $cat_id) {
						$course_meta = new CourseMeta($cat_id);
						if($course_meta->getIsCourse() && !$course_meta->getIsClosed()) {
							$message .= $course_meta->inviteStudentMessage($email)."\n";
						} else {
							$this->echoNoticeDiv(sprintf(__('Course "%s" is closed, cannot invite students', lepress_textdomain), $course_meta->getName()));
							if(count($course_ids) == 1) {
								$invited_count = 0;
							}
						}
					}
					if(!empty($message) && !$course_meta->getIsClosed()) {
						global $current_user;
						$headers = 'From: '.$current_user->user_firstname.' '.$current_user->user_lastname.' <'.$current_user->user_email.'>' . "\r\n";
						$teacher_personal_message = $this->safeStringInput($_POST['invite_message'], true);
						$message = (!empty($teacher_personal_message) ? $teacher_personal_message."\n\n" : '').$message;
						$message .= 'Enter course URL on the "Addrss of Course" field on LePress -> Subscriptions page.';
						$message .= "\n\n".'Invitation key(s) expire(s) in 7 days and is only valid for this email address.'."\n\n";
						$message .= 'Be sure you have set the same email address in your Wordpress profile, otherwise subscribing to course fails.';
						wp_mail($email, 'You have been invited to course(s)', $message, $headers);
					}
				}
			} else {
				$failed_emails[] = $email;
			}
		}
	}
}

//Unsubscribing a student

if(isSet($_GET['rm'])) {
	$data = explode('-', base64_decode($_GET['rm']));
	if(is_array($data) && count($data) == 2) {
		$got_cat_ID = intval($data[0]);
		$subscription_id = intval($data[1]);
		if(intval($got_cat_ID) > 0) {
			if($subscription_id > 0) {
				$course_meta = new CourseMeta($got_cat_ID);
				$student = $course_meta->getStudentBySubscription($subscription_id);
				if($student) {
					$course_meta->setSubscriptionStatus($subscription_id, 2, $message = false);
					$name = $display_name_order == 1 ? $student->first_name.' '.$student->last_name : $student->last_name.' '.$student->first_name;
					$this->echoNoticeDiv(__('You have unsubscribed student', lepress_textdomain).' - '.$name);
				}
			}
		}
	}
}

//Display accepted students count

if($accepted_count > 0) {
	$this->echoNoticeDiv(sprintf(_n(__("You have accepted %d student.", lepress_textdomain), __("You have accepted %d students.", lepress_textdomain), $accepted_count, lepress_textdomain), $accepted_count));
}

//Display declined students count

if($declined_count > 0) {
	$this->echoNoticeDiv(sprintf(_n(__("You have declined %d student.", lepress_textdomain), __("You have declined %d students.", lepress_textdomain), $declined_count, lepress_textdomain), $declined_count));
}

//Display invited students count

if($invited_count > 0) {
	$this->echoNoticeDiv(sprintf(_n(__("You have invited %d student.", lepress_textdomain), __("You have invited %d students.", lepress_textdomain), $invited_count, lepress_textdomain), $invited_count));
}

//Display failed invites count

if(($f = count($failed_emails)) > 0) {
	$this->echoNoticeDiv(sprintf(_n(__("%d student email was not valid.", lepress_textdomain), __("%d students emails were not valid.", lepress_textdomain), $f, lepress_textdomain), $f));
}
?>

<div class="wrap">

	<h2><?php _e('Subscribed students', lepress_textdomain); ?></h2>
		<form action="<?php echo add_query_arg(array('page' => $_GET['page']), admin_url().'admin.php'); ?>" method="post">

		<table id="subscribed-students" class="widefat" cellspacing="0">

			<thead>

				<tr>

					<th scope="col" class="manage-column column-table-name" colspan="6"><?php _e('Course (Category)', lepress_textdomain)?>

						<select name="c" onchange="this.form.submit()">

							<?php
								$selected_cat_ID = 0;
								$cats = get_categories('hide_empty=0');
							   	foreach($cats as $cat) {
							   		$selected = "";
							   		$course_meta = new CourseMeta($cat->cat_ID);
								   	if($course_meta instanceOf CourseMeta) {
								   		if($course_meta->getIsCourse()) {
							   				if($selected_cat_ID == 0) {
									   			$selected_cat_ID = $cat->cat_ID;
									   		}
									   		if(isSet($_REQUEST['c'])) {
									   			$selected_cat_ID = intval($_REQUEST['c']);
									   		} elseif(isSet($got_cat_ID)) {
									   			$selected_cat_ID = $got_cat_ID;
									   		}

									   		if($cat->cat_ID == $selected_cat_ID) {
									   			$selected = 'selected="selected"';
									   		}
								   			echo	'<option value="'.$cat->cat_ID.'" '.$selected.'>'.$cat->name.'</option>';
								   			$course_exist = true;
								   		}
								   	}
							   	}


								if(!$course_exist) {
									echo '<option value="-1">'.__('No courses', lepress_textdomain).'</option>';
								}

							?>

						</select>
						
						<?php 
						$course_meta = new CourseMeta($selected_cat_ID);
						if($course_meta->getIsClosed()) {
							_e('This course is closed, no changes can be made!', lepress_textdomain);
							//Remove any pending subscriptions
							$course_meta->removePendingSubscriptions();
						} ?>

					</th>

				</tr>

				<tr>

					<th scope="col" class="manage-column column-name"><?php _e('Name', lepress_textdomain)?></th>
					<th scope="col" class="manage-column column-email" ><?php _e('E-mail', lepress_textdomain)?></th>

					<th scope="col" class="manage-column column-results"><?php _e('Results', lepress_textdomain)?></th>

					<th scope="col" class="manage-column column-blog"><?php _e('Blog', lepress_textdomain)?></th>

					<th scope="col" class="manage-column column-actions" colspan="2"  style="width:30px"><?php _e('Actions', lepress_textdomain)?></th>

				</tr>

			</thead>

			<tbody id="subscribed-students-body">
			<?php

				   	if($course_meta instanceOf CourseMeta) {
				   		$all_users = $course_meta->getApprovedSubscriptions();
				   		if($all_users) {
				   			foreach($all_users as $user) {
				   				//Set up secure delete hash
								$encoded = base64_encode($selected_cat_ID."-".$user->id);
				   				$name = $display_name_order == 1 ? $user->first_name.' '.$user->last_name : $user->last_name.' '.$user->first_name;
			   					$cat_permalink = get_category_link($selected_cat_ID);
			   					echo '<tr><td>'.$name.'</td>';
			   					echo '<td><a href="mailto:'.$user->email.'">'.$user->email.'</a></td>';
			   					echo '<td><a href="'.admin_url().'admin.php?page=lepress-classbook&c='.$selected_cat_ID.'">'.__('View assignments', lepress_textdomain).'</a></td>';
			   					echo '<td><a href="'.$user->blog_url.'" target="_blank">'.$user->blog_url.'</a></td>';
			   					if($course_meta->getIsClosed()) {
			   						echo '<td>&nbsp;</td>';
			   					} else {
			   						echo '<td><a href="'.add_query_arg(array('page' => $_GET['page'], 'rm' =>$encoded), admin_url().'admin.php').'">'.__('Remove', lepress_textdomain).'</a></td>';
			   					}
			   					echo '</tr>';
				   			}
				   		} else {
				   			echo '<tr><td colspan="6">'.__('There are no participants on selected course.', lepress_textdomain).'</td></tr>';
				   		}
				   	} 
			?>

			</tbody>

		</table>
		</form>

	</div>

	<div class="wrap">

		<h2><?php _e('Students awaiting authorization', lepress_textdomain); ?></h2>

		<form method="post" id="lepress-subscriptions">

			<input type="hidden" name="action" value="acceptUsers" />

			<table id="unsubscribed-students" class="widefat fixed" cellspacing="0">

				<thead>

					<tr>

						<th scope="col" class="manage-column column-name"><?php _e('Name', lepress_textdomain); ?></th>
						<th scope="col" class="manage-column column-email" ><?php _e('E-mail', lepress_textdomain); ?></th>

						<th scope="col" class="manage-column column-results"><?php _e('Course', lepress_textdomain); ?></th>
						<th scope="col" class="manage-column column-message"><?php _e('Message', lepress_textdomain); ?></th>

						<th scope="col" class="manage-column column-blog" style="width:60px; text-align: center"><?php _e('Accept', lepress_textdomain); ?></th>

						<th scope="col" class="manage-column column-actions" style="width:60px; text-align: center"><?php _e('Decline', lepress_textdomain); ?></th>

						<th scope="col" class="manage-column column-actions"><?php _e('Response', lepress_textdomain); ?></th>

					</tr>

				</thead>

				<tbody id="unsubscribed-students-body">

					<?php
					   	$cats = get_categories('hide_empty=0');
					   	$noAwaitingAuths = true;
					   	foreach($cats as $cat) {
						   	$course_meta = new CourseMeta($cat->cat_ID);
						   	if($course_meta instanceOf CourseMeta) {
						   		if($subscriptions = $course_meta->getPendingSubscriptions()) {
						   			foreach($subscriptions as $user) {
						   				$name = $display_name_order == 1 ? $user->first_name.' '.$user->last_name : $user->last_name.' '.$user->first_name;
					   					$cat_permalink = get_category_link($cat->cat_ID);
					   					echo '<tr><td>'.$name.'</td>';
					   					echo '<td><a href="mailto:'.$user->email.'">'.$user->email.'</a></td>';
					   					echo '<td><a href="'.$cat_permalink.'">'.$course_meta->getName().'</a></td>';
					   					echo '<td>'.$user->message.'</td>';
					   					echo '<td style="text-align: center"><input type="radio" name="accept_decline['.$user->id.']['.$cat->cat_ID.'][action]" value="1"/></td>';
					   					echo '<td style="text-align: center"><input type="radio" name="accept_decline['.$user->id.']['.$cat->cat_ID.'][action]" value="2"/></td>';
					   					echo '<td><input type="text" style="width: 100%" name="accept_decline['.$user->id.']['.$cat->cat_ID.'][message]"/></td>';
					   					echo '</tr>';
					   					$noAwaitingAuths = false;
						   			}
						   		}
						   	}
					   	}

						if($noAwaitingAuths) {
							echo '<tr><td colspan="7">'.__('There is no pending authorizations', lepress_textdomain).'.</td></tr>';
						} else {
							echo "<tr><td colspan='7' align='right' ><input type='submit' class='button-primary' value='".__('Confirm', lepress_textdomain)."' /></td></tr>";
						}


					?>

				</tbody>

			</table>

		</form>

	</div>

	<div class="wrap">

	<h2><?php _e('Invite students', lepress_textdomain); ?></h2>

	<form method="post" action="<?php echo add_query_arg(array('page' => $_GET['page']), admin_url().'admin.php'); ?>" enctype="multipart/form-data">

		<input type="hidden" name="action" value="inviteStudents" />

		<table class="form-table" >

			<tr class="form-field">

				<th scope="row" valign="top"><label for="importList"><?php _e('Import list of students', lepress_textdomain) ?>:</label></th>

				<td><input type="file" name="invite_emails_file" id="file" /> <br /><?php _e('Text file with emails separated by new line can upload here.', lepress_textdomain);?> </td>

			</tr>

			<tr class="form-field">

				<th scope="row" valign="top"><label for="emails"><?php _e('Or add manually:', lepress_textdomain); echo '<br/>('; _e('Seperate with new row', lepress_textdomain); ?>)</label></th>

				<td><textarea id="emails" name="emails" rows=6><?php

				if(count($failed_emails) > 0) {
					echo implode("\n", $failed_emails);
				}

				?></textarea><br/><?php _e('Separate each email with new line.', lepress_textdomain)?></td>

			</tr>

			<tr>

				<th scope="row" valign="top"><label for="course_id"><?php _e('Category (Course) to invite:', lepress_textdomain) ?></label></th>

				<td>

					<?php
						$course_exist = false;
						$cats = get_categories('hide_empty=0');
					   	foreach($cats as $cat) {
					   		$selected = "";
					   		$course_meta = new CourseMeta($cat->cat_ID);
						   	if($course_meta instanceOf CourseMeta) {
						   		if($course_meta->getIsCourse() && !$course_meta->getIsClosed()) {
						   			echo '<input type="checkbox" name="course_cat_id[]" value="'.$cat->cat_ID.'" id="lepress-course-'.$cat->cat_ID.'" /> <label for="lepress-course-'.$cat->cat_ID.'">'.$cat->cat_name.'</label><br />';
						   			$course_exist = true;
						   		}
						   	}
					   	}

						if(!$course_exist) {
							echo __('Please add categories as course or mark existing category as a course', lepress_textdomain).' <a  href="'.admin_url().'edit-tags.php?taxonomy=category">'.__('here', lepress_textdomain).'</a>.';
						} else {

							_e('Mark the courses to which you want to invite students.', lepress_textdomain);
						}
						?>

				</td>

			</tr>

			<tr class="form-field">

				<th scope="row" valign="top"><label for="invite_message"><?php _e('Message to student/s:', lepress_textdomain) ?> (<?php _e('optional', lepress_textdomain) ?>)</label></th>

				<td><textarea name="invite_message" id="invite_message" rows="4"></textarea><br /><?php _e('Optional. Here you can write some invitation and additional information to identify your email for student.', lepress_textdomain); ?></td>

			</tr>

		</table>

		<p class="submit"><input type="submit" class="button-primary" name="submit" value="<?php _e('Invite students', lepress_textdomain); ?>" /></p>

	</form>

	</div>