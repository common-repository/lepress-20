<?php

/**
 * Student GROUPS management page
 *
 * NOT COMPLETED NOT COMPLETED
 *
 * @todo ALL OF IT
 */
 
$group_id = intval($_GET['manage']);
if(isSet($_POST['classmates_id'])) {
	$this->groups->inviteStudentsToGroup($_POST['classmates_id'], $group_id);	
}
?>

<div class="wrap">

	<h2>Manage group</h2>

	<table id="unsubscribed-courses" class="widefat fixed" cellspacing="0">

		<thead>

			<tr>

				<th scope="col" class="manage-column column-results" >Name</th>

				<th scope="col" class="manage-column column-actions" >Blog</th>

			</tr>

		</thead>

		<tbody>

				
				<?php 
				
				if($group_mates = $this->groups->getGroupParticipants($group_id)) {
					foreach($group_mates as $group_mate) {
						echo '<tr><td><a href="mailto:'.$group_mate->email.'">'.$group_mate->firstname.' '.$group_mate->lastname.'</a></td><td>'.$group_mate->blog_url.'</td></tr>';
					}	
				}
				else {
					echo '<tr><td colspan="2">No participants</td></tr>';	
				}

			?>

		</tbody>

	</table>
	
	<h2>Invite classmates</h2>
	<form action="" method="post">

	<table id="unsubscribed-courses" class="widefat fixed" cellspacing="0">

		<thead>

			<tr>
				<th scope="col" id="cb" class="manage-column column-cb check-column"><input type="checkbox"></th>

				<th scope="col" class="manage-column column-results" >Name</th>

				<th scope="col" class="manage-column column-actions" >Blog</th>
				<th scope="col" class="manage-column column-actions" >Status</th>

			</tr>

		</thead>

		<tbody>

				
				<?php 
				
				if($mates = $this->subscriptions->getClassmatesByGroup($group_id)) {
					$no_mates = true;
					foreach($mates as $mate) {
						$status = $this->groups->getClassmateGroupStatus($mate->mate_id, $group_id);
						if($status != 1) {
							$no_mates = false;
							$status_txt = '';
							if($status == 0 && !is_null($status)) {
								$status_txt = "Waiting for response";
							}
							echo '<tr><th scope="row" class="check-column"><input type="checkbox" name="classmates_id[]" value="'.$mate->mate_id.'" /></th>';
							echo '<td><a href="mailto:'.$mate->email.'">'.$mate->firstname.' '.$mate->lastname.'</a></td><td>'.$mate->blog_url.'</td><td>'.$status_txt.'</td></tr>';
						}
					}	
				}
				if($no_mates) {
					echo '<tr><td colspan="3">No classmates to invite</td></tr>';	
				} else {
					echo '<tr><td colspan="3"><input type="submit" class="button-primary" name="invite-classmates" value="Invite" /></td></tr>';	
				}

			?>

		</tbody>

	</table>
	</form>

</div>
