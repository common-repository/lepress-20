<?php

/**
 * LePress Student groups class
 *
 * NOT COMPLETED NOT COMPLETED
 * METHODS DEFINED HERE PROBABLY WON'T WORK ANYMORE
 */
 
class StudentGroups extends StudentSubscriptions {
	
	function __construct() {
		parent::__construct();
		//Check for confirmation link
		if(isSet($_GET['lcg'])) {
			$this->confirmGroup($_GET['lcg']);	
		}
	}
	
	function confirmGroup($group_key) {
		$group = $this->getGroupByKey($group_key);
		//if($group->type == -1) {
			$data = array('type' => 1);
			$where = array('group_key' => $group_key);
			$updated = $this->wpdb->update($this->groups_table, $data, $where);
			//if($updated) {
				//Notify teacher's blog too			
				$params = array('body' => array('lepress-role' => 2, 'lepress-action' => 'addMeToGroup', 'lepress-blog' => get_bloginfo('siteurl'), 'securityHash' => $this->setLastRequestHash(),
														'group_key' => $group_key, 'accept-key' => $group->accept_key));
				$result = wp_remote_post($group->course_url, $params);
				var_dump(wp_remote_retrieve_body($result));
			//}
		//}
	}
	
	function addGroup($course_id, $group_name) {
		if(mb_strlen(trim($group_name), 'UTF-8')) {
			$group_key = md5(time().$group_name);
			$data = array('course_id' => $course_id, 'group_name' => trim($group_name), 'group_key' => $group_key);
			$wpdb_result = $this->wpdb->insert($this->groups_table, $data);
			if($wpdb_result) {
				global $current_user;
				$course = $this->getCourse($course_id);
				$params = array('body' => array('lepress-role' => 2, 'lepress-action' => 'addMyGroup', 'lepress-blog' => get_bloginfo('siteurl'), 'securityHash' => $this->setLastRequestHash(),
														'group_name' => trim($group_name), 'group_key' => $group_key, 'email' => $current_user->user_email));
				$result = wp_remote_post($course->course_url, $params);
				//TODO 201 check, if success on create 
			}
		}
	}
	
	function removeGroup($group_id) {
		$group = $this->getGroup($group_id);
		if($group) {
			global $current_user;
			$params = array('body' => array('lepress-role' => 2, 'lepress-action' => 'removeMyGroup', 'lepress-blog' => get_bloginfo('siteurl'), 'securityHash' => $this->setLastRequestHash(),
													'group_key' => $group->group_key, 'email' => $current_user->user_email));
			$result = wp_remote_post($group->course_url, $params);
			var_dump(wp_remote_retrieve_body($result));
			if(wp_remote_retrieve_response_code($result) == 200) {
				$this->wpdb->query('DELETE FROM '.$this->groups_table.' WHERE id = '.$group_id);
			} else {
				echo "error";	
			}
		}
	}
	
	function getGroups($owner = true) {
		$type = $owner ? 0 : 1;
		return $this->wpdb->get_results('SELECT '.$this->groups_table.'.id, group_name, course_name, course_url FROM '.$this->groups_table.','.$this->courses_table.' WHERE type = '.$type.' AND course_id = '.$this->courses_table.'.id');
	}
	
	function getGroupByKey($group_key) {
		return $this->wpdb->get_row('SELECT group_name, group_key, course_url, type, accept_key FROM '.$this->groups_table.','.$this->courses_table.' WHERE course_id = '.$this->courses_table.'.id AND group_key = "'.$group_key.'"');
	}
	
	function getGroup($group_id) {
		return $this->wpdb->get_row('SELECT group_name, group_key, course_url, type FROM '.$this->groups_table.','.$this->courses_table.' WHERE '.$this->groups_table.'.id = '.$group_id.' AND course_id = '.$this->courses_table.'.id');
	}
	
	function getClassmateGroupStatus($mate_id, $group_id) {
		return $this->wpdb->get_var('SELECT status FROM '.$this->classmates_group_rel_table.' WHERE classmate_id = '.$mate_id.' AND group_id = '.$group_id.' LIMIT 1');
	}
	
	function inviteStudentsToGroup($classmates_ids, $group_id) {
		if(!is_array($classmates_ids)) {
			return false;	
		}	
		foreach($classmates_ids as $mate_id) {
			echo $mate_id;
			$mate = $this->getClassmateByID($mate_id);
			$group = $this->getGroup($group_id);
			$group_invite_url = add_query_arg(array('confirm_group' => $group->group_key), $mate->blog_url);
			//echo $group_invite_url;
			
			$params = array('body' => array('lepress-role' => 22, 'lepress-action' => 'inviteMeToGroup', 'lepress-blog' => get_bloginfo('siteurl'), 'securityHash' => $this->setLastRequestHash(),
														'group_name' => $group->group_name, 'group_key' => $group->group_key, 'email' => $current_user->user_email, 'course_url' => $group->course_url));
			$result = wp_remote_post($mate->blog_url, $params);
			var_dump(wp_remote_retrieve_body($result));


			$data = array('classmate_id' => $mate_id, 'group_id' => $group_id);
			//$this->wpdb->insert($this->classmates_group_rel_table, $data);
		}
	}
	
	
	function getGroupParticipants($group_id) {	
		return $this->wpdb->get_results('SELECT  '.$this->classmates_table.'.id as mate_id, firstname, lastname, email, blog_url, course_name, course_url FROM '.$this->classmates_table.','.$this->classmates_group_rel_table.','.$this->courses_table.','.$this->groups_table.' WHERE '.$this->classmates_table.'.course_id = '.$this->courses_table.'.id  AND '.$this->groups_table.'.course_id = '.$this->courses_table.'.id AND classmate_id = '.$this->classmates_table.'.id AND '.$this->classmates_group_rel_table.'.status = 1 AND group_id = '.$group_id);	
	}
	
	function getGroupParticipantsCount($group_id) {
		return $this->wpdb->get_var('SELECT  count('.$this->classmates_table.'.id)  FROM '.$this->classmates_table.','.$this->classmates_group_rel_table.','.$this->courses_table.' WHERE course_id = '.$this->courses_table.'.id AND classmate_id = '.$this->classmates_table.'.id AND '.$this->classmates_group_rel_table.'.status = 1 AND group_id = '.$group_id);
	}	
	
	// Remote requested functions
	
	function addMeToGroup($group_name, $group_key, $course_url) {
		$course = $this->getActiveCourseByURL($course_url);
		if($course) {
			$data = array('group_name' => $group_name, 'group_key' => $group_key, 'type' => -1, 'course_id' => $course->ID);
			$result = $this->wpdb->insert($this->groups_table, $data);
		}
	}
}