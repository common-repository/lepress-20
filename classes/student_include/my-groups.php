<?php

/**
 * Student GROUPS page
 *
 * NOT COMPLETED NOT COMPLETED
 *
 * @todo ALL OF IT
 */

if(isSet($_POST['add-new'])) {
	$this->groups->addGroup($_POST['course-id'],$_POST['group-name']);
}

if(isSet($_GET['remove'])) {
	$this->groups->removeGroup($_GET['remove']);	
}

if(isSet($_GET['manage'])) {
	require_once('manage-group.php');	
} else {
?>

<div class="wrap">

<h2>My groups</h2>

	<table id="subscribed-courses" class="widefat fixed" cellspacing="0">

		<thead>

			<tr>

				<th scope="col" class="manage-column column-name">Name</th>
				<th scope="col" class="manage-column column-name">Course</th>

				<th scope="col" class="manage-column column-results">Participants</th>
				<th scope="col" class="manage-column column-results">Actions</th>

			</tr>

		</thead>
		<tfoot>
			<tr><td colspan="2">
				<form action="" method="post">
				Course: <select name="course-id">
				<?php
					$courses = $this->subscriptions->getApproved();
					foreach($courses as $course) {
						echo '<option value="'.$course->ID.'">'.$course->course_name.'</option>';	
					}
				?>
				</select>
					Group name: <input type="text" name="group-name" maxlength="250" size="40"/>
					<input type="submit" name="add-new"  class="button-primary" value="Add"/>
				</form>
			</td></tr>
		</tfoot>

		<tbody>

			<?php
				if($groups = $this->groups->getGroups()) {
					foreach($groups as $group) {
						$count = $this->groups->getGroupParticipantsCount($group->id);
						echo '<tr><td><a href="'.add_query_arg(array('manage' => $group->id), false).'">'.$group->group_name.'</a></td><td><a href="'.$group->course_url.'">'.$group->course_name.'</a></td><td>'.$count.'</td>';
						echo '<td><a href="'.add_query_arg(array('remove' => $group->id), false).'">Delete</a></td></tr>';
					}	
				}
				else {
					echo '<tr><td colspan="3">No groups.</td></tr>';	
				}

			?>

		</tbody>

	</table>

</div>

<div class="wrap">

	<h2>I belong to groups</h2>

	<table id="unsubscribed-courses" class="widefat fixed" cellspacing="0">

		<thead>

			<tr>

				<th scope="col" class="manage-column column-name">Name</th>
				<th scope="col" class="manage-column column-name">Course</th>

				<th scope="col" class="manage-column column-results">Participants</th>
				<th scope="col" class="manage-column column-results">Actions</th>

			</tr>

		</thead>

		<tbody>

			<?php
				if($groups = $this->groups->getGroups($owner = false)) {
					foreach($groups as $group) {
						$count = $this->groups->getGroupParticipantsCount($group->id);
						echo '<tr><td><a href="'.add_query_arg(array('manage' => $group->id), false).'">'.$group->group_name.'</a></td><td>kursus</td><td>'.$count.'</td>';
						echo '<td><a href="'.add_query_arg(array('remove' => $group->id), false).'">Delete</a></td></tr>';
					}	
				}
				else {
					echo '<tr><td colspan="3">No groups.</td></tr>';	
				}

			?>

		</tbody>

	</table>

</div>

<?php

}

?>