<?php

/**
 * LePress Configuration File
 * @author Raido Kuli
 */

/* xml_root constant used when returning data as XML */

define('xml_root', '<?xml version="1.0" encoding="UTF-8"?><LePress></LePress>');

/* Plugin textdomain used for translation functionality */

define('lepress_textdomain', 'lepress-lang');

/* Plugin include ABSPATH */

define('lepress_abspath', dirname(__FILE__).'/');

/* Plugin HTTP ABSPATH */

define('lepress_http_abspath',  plugins_url('', __FILE__).'/');

?>