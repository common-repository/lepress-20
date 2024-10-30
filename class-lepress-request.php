<?php

/**
 * LePress request class
 *
 * Handles POST/GET requests with needed headers and parameters
 *
 * @author Raido Kuli
 *
 */
 
class LePressRequest {
	private $params = array('header' => array(), 'body' => array(), 'timeout' => '30');
	
	/**
	 * On init store $role and $action, who we are and what we are going to do
	 */
	 
	function __construct($role = false, $action = false) {
		$this->role = $role;
		$this->action = $action;
	}
	
	/**
	 * Add extra parameter to GET/POST parameters
	 */
	 
	function addParam($param, $value) {
		$this->params['body'][$param] = $value;	
	}
	
	/**
	 * Set request blocking value, default value is 'false'
	 */
	 
	function setBlocking($value) {
		$this->params['blocking'] = $value;
	}
	
	/**
	 * Add a set of extra parametesr to GET/POST parameters
	 * @var array
	 */
	 
	function addParams($params_arr) {
		$this->params['body'] = array_merge($this->params['body'], $params_arr);
	}
	
	/**
	 * Convert all the params and header params into one array
	 * @return array
	 */
	 
	function toArray() {
		$this->params['body'] = array_merge(array('lepress-role' => $this->role, 'lepress-blog' => get_bloginfo('siteurl'), 'lepress-action' => $this->action), $this->params['body']);
		return $this->params;
	}
	
	/**
	 * Init POST request 
	 * @var request url, where to
	 */
	 
	function doPost($url) {
		$this->request = wp_remote_post(add_query_arg(array('lepress-service' => 1), $url), $this->toArray());
		return $this->request;
	}
	
	/**
	 * Init GET request 
	 * @var request url, where to
	 */
	
	function doGet($url) {
		$this->request = wp_remote_get(add_query_arg(array('lepress-service' => 1), $url), $this->toArray());
		return $this->request;
	}
	
	/**
	 * Get HTTP status code of the request
	 * @return int or boolean false
	 */
	 
	function getStatusCode() {
		return intval(wp_remote_retrieve_response_code($this->request));
	}
	
	/**
	 * Get request response body
	 * @return trimmed string or boolean false
	 */
	 
	function getBody() {
		return trim(wp_remote_retrieve_body($this->request));
	}
	
}

?>