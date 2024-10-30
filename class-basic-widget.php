<?php

/**
 * Basic LePress sidebar widget class
 *
 * @author Raido Kuli
 *
 */

class LePressBasicWidget {
	
	/**
	 * This method returns current selected category ID
	 *
	 * @return int or boolean false
	 */
	 
	public function getSelectedCatID() {
		if(isSet($_GET['c'])) {
   			$params = explode('-',$_GET['c']);
   			if(!empty($params[1])) {
   				return intval($params[1]);
   			} 
   		}
   		return false;
	}
	
	/**
	 * This method returns course owner i.e teacher or student
	 *
	 * @return string(teacher || student) or boolean false
	 */
	 
	public function getCourseOwner($who = false) {
		if(isSet($_GET['c'])) {
   			$params = explode('-',$_GET['c']);
   			if(!empty($params[0]) && $who) {
   				return ($params[0] == $who);
   			} else {
   				return $params[0];	
   			}
   		}
   		return false;
	}	
}

?>