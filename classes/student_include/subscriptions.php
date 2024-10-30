<?php

/**
 * This is student subscriptions page
 *
 * @author Raido Kuli
 */

/* Subscribe */
if(isSet($_POST['action'])) {
	$this->subscriptions->subscribe($this->safeStringInput($_POST['course_url'], true), $this->safeStringInput($_POST['course_message']));
}

/* Unsubscribe course */
if(isSet($_GET['uc'])) {
	$this->subscriptions->unsubscribe(base64_decode($_GET['uc']));
}

/* Clear declined courses list */
if(isSet($_GET['clear'])) {
	$this->subscriptions->clear($_GET['clear']);
}

/* Cancel subscription */
if(isSet($_GET['cancel'])) {
	$this->subscriptions->cancelSubscription(base64_decode($_GET['cancel']));
}

?>

<div class="wrap">

<h2><?php _e('Subscribed courses', lepress_textdomain); ?></h2>

	<table id="subscribed-courses" class="widefat fixed" cellspacing="0">

		<thead>

			<tr>

				<th scope="col" class="manage-column column-name"><?php _e('Course', lepress_textdomain); ?></th>

				<th scope="col" class="manage-column column-results"><?php _e('Results', lepress_textdomain); ?></th>

				<th scope="col" class="manage-column column-actions" colspan="2"><?php _e('Actions', lepress_textdomain); ?></th>

			</tr>

		</thead>

		<tbody id="subscribed-courses-body">

			<?php
				if($this->subscriptions->getApproved()) {
					foreach($this->subscriptions->getApproved() as $course) {
						echo '<tr><td><a href="'.$course->course_url.'">'.$course->course_name.'</a></td><td><a href="'.add_query_arg(array('page' => 'lepress-assignments'), admin_url().'admin.php').'">'.__('View assignments', lepress_textdomain).'</a></td>';
						echo '<td>';
						foreach($this->subscriptions->getTeachers($course->ID) as $teacher) {
							echo '<a href="mailto:'.$teacher->email.'">'.__('Send e-mail to', lepress_textdomain).' - '.$teacher->firstname.' '.$teacher->lastname.'</a><br/>';
						}
						echo '</td>';

						echo '<td><a href="'.add_query_arg(array('page' => $_GET['page'], 'uc' => base64_encode($course->ID)), admin_url().'admin.php').'">'.__('Unsubscribe', lepress_textdomain).'</a></td></tr>';
					}
				}
				else {
					echo '<tr><td colspan="4">'.__('There is no subscribed courses.', lepress_textdomain).'</td></tr>';
				}

			?>

		</tbody>

	</table>

</div>

<div class="wrap">

	<h2><?php _e('Awaiting authorization to courses', lepress_textdomain); ?></h2>

	<table id="unsubscribed-courses" class="widefat fixed" cellspacing="0">

		<thead>

			<tr>

				<th scope="col" class="manage-column column-results" ><?php _e('Course', lepress_textdomain); ?></th>

				<th scope="col" class="manage-column column-actions" ><?php _e('Address of Course', lepress_textdomain); ?></th>
				<th scope="col" class="manage-column column-message" ><?php _e('Message', lepress_textdomain); ?></th>
				<th scope="col" class="manage-column column-actions" ><?php _e('Action', lepress_textdomain); ?></th>

			</tr>

		</thead>

		<tbody id="unsubscribed-students-body">


				<?php

				if($this->subscriptions->getPending()) {
					foreach($this->subscriptions->getPending() as $course) {
						echo '<tr><td>'.$course->course_name.'</td><td>'.$course->course_url.'</td><td>'.$course->message.'</td><td><a href="'.add_query_arg(array('page' => $_GET['page'], 'cancel' => base64_encode($course->ID)), admin_url().'admin.php').'">'.__('Cancel', lepress_textdomain).'</a></td></tr>';
					}
				}
				else {
					echo '<tr><td colspan="4">'.__('There is no pending authorization requests.', lepress_textdomain).'</td></tr>';
				}

			?>

		</tbody>

	</table>

</div>

<div class="wrap">

	<h2><?php _e('Declined courses', lepress_textdomain); ?></h2>

	<table id="unsubscribed-courses" class="widefat fixed" cellspacing="0">

		<thead>
			<tr>
				<td colspan="3" style="text-align: right;"><a href="<?php echo add_query_arg(array('page' => $_GET['page'], 'clear' => 'declined'), admin_url().'admin.php'); ?>"><?php _e('Clear', lepress_textdomain); ?></a></td>
			</tr>

			<tr>

				<th scope="col" class="manage-column column-results" ><?php _e('Course', lepress_textdomain); ?></th>

				<th scope="col" class="manage-column column-actions" ><?php _e('Address of Course', lepress_textdomain); ?></th>
				<th scope="col" class="manage-column column-message" ><?php _e('Message', lepress_textdomain); ?></th>

			</tr>

		</thead>

		<tbody id="unsubscribed-students-body">


				<?php

				if($declined_courses = $this->subscriptions->getDeclined()) {
					foreach($declined_courses as $course) {
						echo '<tr><td>'.$course->course_name.'</td><td>'.$course->course_url.'</td><td>'.$course->message.'</td></tr>';
					}
				}
				else {
					echo '<tr><td colspan="3">'.__('There is no declined courses.', lepress_textdomain).'</td></tr>';
				}

			?>

		</tbody>

	</table>

</div>


<div class="wrap" align='center'>
<script type="text/javascript">
	function disableSubmit(form) {
		form.submit.disabled = "disabled";
		form.submit.value = "<?php _e('Subscribing...', lepress_textdomain); ?>";
	}
</script>
<?php
	$current_user = $this->getBlogOwnerUser();
	if(empty($current_user->first_name) || empty($current_user -> last_name)) {
		$disabled = "disabled='disabled'";
	} else {
		$disabled = '';
	}
 ?>

<h2><?php _e('Subscribe to Course', lepress_textdomain); ?></h2>

<form method="post" id="subscribe-form" action="<?php echo add_query_arg(array('page' => $_GET['page']), admin_url().'admin.php'); ?>" onsubmit="disableSubmit(this)">

	<input type="hidden" name="action" value="subscribeCourse" />

	<table class="form-table" >

		<tr class="form-field form-required">

			<th scope="row" valign="top"><label for="course_url"><?php _e('Address of Course:', lepress_textdomain) ?></label></th>

			<td><input name="course_url" <?php echo $disabled; ?>  id="course_url" type="text" value="<?php echo $error_found?$course_url: '';?>"/><br/><?php _e("Blog's Category address, i.e.: http://teacher.wordpress.org/somecourse", lepress_textdomain);?></td>

		</tr>

		<tr class="form-field">

			<th scope="row" valign="top"><label for="course_message"><?php _e('Message to Teacher:', lepress_textdomain) ?> (<?php _e('optional', lepress_textdomain) ?>)</label></th>

			<td><textarea name="course_message" <?php echo $disabled; ?>  id="course_message" rows="4"></textarea><br /><?php _e('Optional. Enter here text if you need to say something related to registration to this course.', lepress_textdomain);?></td>

		</tr>

	</table>


	<p class="submit"><input type="submit" class="button-primary" <?php echo $disabled; ?>  name="submit" value="<?php _e('Subscribe', lepress_textdomain); ?>" /></p>


</form>

</div>