<?php
/**
 * This is student assignments page
 *
 * @author Raido Kuli
 */
?>
<div class="wrap">


<h2><?php _e('Manage assignments', lepress_textdomain); ?></h2>

	<div>

		<?php
			foreach($this->subscriptions->getApproved() as $course) {
				$subscribed_to_course = true;
				$assignments_found = false;

				echo '<h3>' . $course->course_name . '</h3>';

				?>

				<table class="widefat fixed" cellspacing="0">

					<thead>

						<tr>

							<th scope="col" class="manage-column column-name"><?php _e('Assignment', lepress_textdomain); ?></th>
							<th scope="col" class="manage-column column-deadline"><?php _e('Start', lepress_textdomain); ?></th>
							<th scope="col" class="manage-column column-deadline"><?php _e('End', lepress_textdomain); ?></th>

							<th scope="col" class="manage-column column-status"><?php _e('Status', lepress_textdomain); ?></th>

							<th scope="col" class="manage-column column-grade"><?php _e('Grade', lepress_textdomain); ?></th>

							<th scope="col" class="manage-column column-actions" colspan="2"><?php _e('Actions', lepress_textdomain); ?></th>

						</tr>

					</thead>

					<tbody id="assignments-body">

						<?php
							foreach($this->subscriptions->getAssignments($course) as $course_assignment) {
									$assignments_found = true;
									$status = $this->subscriptions->getAssignmentStatus($course_assignment->post_id);
									echo '<tr><td><a href="'.$course_assignment->url.'" title="'.$course_assignment->excerpt.'">'.$course_assignment->title.'</a></td>';
									echo '<td>'.$this->date_i18n_lepress($course_assignment->start_date).'</td>';
									echo '<td>'.$this->date_i18n_lepress($course_assignment->end_date).'</td>';
									echo '<td>';
										if($status) {
											echo __('Ready', lepress_textdomain).' <a href="'.get_permalink($status).'">'.__('View', lepress_textdomain).'</a>';
										} else {
											$post_id = $this->subscriptions->getAssignmentStatus($course_assignment->post_id, true);
											if($post_id) {
												_e('Draft', lepress_textdomain);
											} else {
												_e('Not ready', lepress_textdomain);
											}
										}
									$grade = $this->subscriptions->getGrade($status);
									$comment = $this->fetchComment($status, false, 'LePressTeacher');
									echo '</td>';
									echo '<td>'.$grade.' '.($grade != "NA" ? '<a href="'.get_comment_link($comment).'">'.__('View feedback', lepress_textdomain).'</a>' : '').'</td>';
									echo '<td>';
										if(!$status) {
											$post_id = $this->subscriptions->getAssignmentStatus($course_assignment->post_id, true);
											$post = get_post($post_id);
											if($post && ($post->post_status == "draft" || $post->post_status == "future")) {
												echo '<a href="'.add_query_arg(array('post' => $post_id, 'action' => 'edit'), admin_url().'post.php').'">'.__('Edit draft', lepress_textdomain).'</a>';
											} else {
												echo '<a href="'.add_query_arg(array('p' => $course_assignment->post_id, 'c' => $course->ID), admin_url().'post-new.php').'">'.__('Submit work', lepress_textdomain).'</a>';
											}
										} else {
											$post = get_post($status);
											echo __('Assignment is completed at', lepress_textdomain).' '.$this->date_i18n_lepress($post->post_date_gmt, false, false, true);
										}
									echo '</td>';
									echo '<td>';
										foreach($this->subscriptions->getTeachers($course->ID) as $teacher) {
											echo '<a href="mailto:'.$teacher->email.'">'.__('Send e-mail to', lepress_textdomain).' - '.$teacher->firstname.' '.$teacher->lastname.'</a><br/>';
										}
									echo '</td>';
									echo '</tr>';
							}
							if(!$assignments_found) {
								echo "<tr><td colspan='7'>".__('There are no assignments in this course.', lepress_textdomain)."</td></tr>";
							}

						?>

					</tbody>

				</table>

				<?php

			}
		if(!$subscribed_to_course) {
			echo __('No subscribed courses.', lepress_textdomain)." <a href='?page=LePressStudent/main.php' title='Subscribe to course' style='text-decoration: none;'>".__('Subscribe to course', lepress_textdomain)."</a>";
		}

		?>

	</div>



<h2><?php _e('Archived assignments', lepress_textdomain); ?></h2>
<?php

	foreach($this->subscriptions->getArchived() as $course) {
		$archived_courses = true;
		$assignments_found = false;

		echo '<h3>' . $course->course_name . '</h3>';

		?>

		<table class="widefat fixed" cellspacing="0">

			<thead>

				<tr>

					<th scope="col" class="manage-column column-name"><?php _e('Assignment', lepress_textdomain); ?></th>

					<th scope="col" class="manage-column column-status"><?php _e('Status', lepress_textdomain); ?></th>

					<th scope="col" class="manage-column column-grade"><?php _e('Grade', lepress_textdomain); ?></th>

					<th scope="col" class="manage-column column-actions" colspan="2"><?php _e('Actions', lepress_textdomain); ?></th>

				</tr>

			</thead>

			<tbody>

				<?php
					foreach($this->subscriptions->getAssignments($course) as $course_assignment) {
						$assignments_found = true;
						$status = $this->subscriptions->getAssignmentStatus($course_assignment->post_id);
						echo '<tr><td><a href="'.$course_assignment->url.'" title="'.$course_assignment->excerpt.'">'.$course_assignment->title.'</a></td>';
						echo '<td>';
							if($status) {
								echo __('Ready', lepress_textdomain).' <a href="'.get_permalink($status).'">'.__('View', lepress_textdomain).'</a>';
							} else {
								_e('Not ready', lepress_textdomain);
							}
						$grade = $this->subscriptions->getGrade($status);
						$comment = $this->fetchComment($status, false, 'LePressTeacher');
						echo '</td>';
						echo '<td>'.$grade.' '.($grade != "NA" ? '<a href="'.get_comment_link($comment).'">'.__('View feedback', lepress_textdomain).'</a>' : '').'</td>';
						echo '<td>';
							if($comment) {
								_e('Assignment is completed at', lepress_textdomain);
								echo ' '.$this->date_i18n_lepress($comment->comment_date_gmt, false, false, true);
							}
						echo '</td>';
						echo '<td>';
							foreach($this->subscriptions->getTeachers($course->ID) as $teacher) {
								echo '<a href="mailto:'.$teacher->email.'">'.__('Send e-mail to', lepress_textdomain).' - '.$teacher->firstname.' '.$teacher->lastname.'</a><br/>';
							}
						echo '</td>';
						echo '</tr>';

					}

					if(!$assignments_found) {
						echo "<tr><td colspan='5'>".__('There are no archived assignments in this course.', lepress_textdomain)."</td></tr>";
					}

				?>

			</tbody>

		</table>

		<?php
		} // course foreach end

		if(!$archived_courses) {
			 _e('No archived courses.', lepress_textdomain);
		}
		 ?>
</div>