<?php
/*
 * Plugin Name: IMS Basic Learning Tools Interoperability
 * @name Load Blog Type 
 * @abstract Processes incoming requests for IMS Basic LTI and apply wordpress with blogType parametrer. This code is developed based on Chuck Severance code
 * @author Chuck Severance 
 * @author Antoni Bertran (abertranb@uoc.edu)
 * @copyright 2010-2012 Universitat Oberta de Catalunya
 * @license GPL
 * Date December 2010
*/

require_once( ABSPATH . WPINC . '/registration-functions.php');
require_once( ABSPATH . WPINC . '/ms-functions.php');
require_once( ABSPATH . WPINC . '/ms-load.php');
require_once( ABSPATH  . '/wp-admin/includes/plugin.php');
require_once( ABSPATH  . '/wp-admin/includes/bookmark.php');

require_once dirname(__FILE__).'/IMSBasicLTI/uoc-blti/bltiUocWrapper.php';
require_once dirname(__FILE__).'/blogType/blogTypeLoader.php';

require_once dirname(__FILE__).'/blogType/Constants.php';
require_once dirname(__FILE__).'/blogType/utils/UtilsPropertiesWP.php';


function lti_parse_request($wp) {
    if ( ! is_basic_lti_request() ) { 

    	$good_message_type = $_REQUEST[LTI_MESSAGE_TYPE] == LTI_MESSAGE_TYPE_VALUE;
    	$good_lti_version = $_REQUEST[LTI_VERSION] == LTI_VERSION_VALUE;
    	$resource_link_id = $_REQUEST[RESOURCE_LINK_ID];
    	if ($good_message_type && $good_lti_version && !isset($resource_link_id) ) {
    		$launch_presentation_return_url = $_REQUEST[LAUNCH_PRESENTATION_URL];
    		if (isset($launch_presentation_return_url)) {
    			header('Location: '.$launch_presentation_return_url);
    			exit();
    		}
    	}
    	return;
    }
    // See if we get a context, do not set session, do not redirect
    $secret = lti_get_secret_from_consumer_key();
    $context = new bltiUocWrapper(false, false, null, $secret);
    if ( ! $context->valid ) {
        wp_die("BASIC LTI Authentication Failed, not valid request (make sure that consumer is authorized and secret is correct) ".$context->message);
        return;
    }
    $error=is_lti_error_data($context);
    if ($error!==FALSE) {
		$launch_presentation_return_url = $_REQUEST[LAUNCH_PRESENTATION_URL];
    	if (isset($launch_presentation_return_url)) {
    		$error = '<p>'.$error.'</p><p>Return to site <a href="'.$launch_presentation_return_url.'">'.$launch_presentation_return_url.'</a></p>';
    	}
    	wp_die($error,'');
    }
    
    $blogType = new blogTypeLoader($context);
    
    if ($blogType->error<0) {
       wp_die("BASIC LTI loading Types Aula Failed ".$blogType->error_miss);
       return ;
    }

    // Set up the user...
    $userkey = $context->getUserKey();
    $userkey = str_replace(':','-',$userkey);  // TO make it past sanitize_user
    $userkey = sanitize_user($userkey);
    
   	try {
    	if (isset($context->info[USERNAME_FIELD])) {
    		$configuration = new UtilsPropertiesWP(dirname(__FILE__).'/blogType/configuration/general.cfg');
    		if ($configuration->getProperty('use_username')==1) {
    			$userkey = $context->info[USERNAME_FIELD];
    		}
   		}
   	} catch (Exception $e) {}
    
    $userkey = apply_filters('pre_user_login', $userkey);
    $userkey = trim($userkey);
    
    if ( empty($userkey) )
    	wp_die('<p>Empty username</p><p>Cannot create a user without username</p>' );
    
  
    $uinfo = get_user_by('login', $userkey);
    if(isset($uinfo) && $uinfo!=false) 
    {
        $ret_id = wp_insert_user(array(
             'ID' => $uinfo->ID,
             'user_login' => $userkey,
             'user_nicename'=> $context->getUserName(),
        	 'first_name'=> $context->getUserFirstName(),
        	 'last_name'=> $context->getUserLastName(),
             'user_email'=> $context->getUserEmail(),
             'user_url' => 'http://',
             'display_name' => $context->getUserName(),
             'role' => get_option('default_role')
          ));
    	if (is_object($ret_id) && isset($ret_id->errors)){
    		$msg = '';
    		foreach ($ret_id->errors as $key => $error){
    			$msg .= "<p><b>$key</b> ";
    			foreach($error as $erroMsg){
    				$msg .= "<p> $erroMsg</p>";
    			}
    			$msg .= "</p>";
    		}
        	wp_die($msg);
        }
    }
    else
    { // new user!!!!
        $ret_id = wp_insert_user(array(
             'user_login' => $userkey,
             'user_nicename'=> $context->getUserName(),
             'first_name'=> $context->getUserFirstName(),
        	 'last_name'=> $context->getUserLastName(),
             'user_email'=> $context->getUserEmail(),
             'user_url' => 'http://',
             'display_name' => $context->getUserName(),
             ) );
    	if (is_object($ret_id) && isset($ret_id->errors)){
    		$msg = '';
    		foreach ($ret_id->errors as $key => $error){
    			$msg .= "<p><b>$key</b> ";
    			foreach($error as $erroMsg){
    				$msg .= "<p> $erroMsg</p>";
    			}
    			$msg .= "</p>";
    		}
        	wp_die($msg);
        }
        $uinfo = get_user_by('login', $userkey);
    }

    //Eliminem del blog Principal (si no es admin) http://jira.uoc.edu/jira/browse/BLOGA-218
    if (!$is_admin){
    	$user = new WP_User($uinfo->ID);
		$user->remove_all_caps();
    }
    
    $_SERVER['REMOTE_USER'] = $userkey;
    $password = md5($uinfo->user_pass);
  
    // User is now authorized; force WordPress to use the generated password
    //login, set cookies, and set current user
    wp_authenticate($userkey, $password);
    wp_set_auth_cookie($user->ID, false);
    wp_set_current_user($user->ID, $userkey);
    $siteUrl = substr( get_option("siteurl"), 7); // - "http://"
    $siteUrlArray = explode("/", $siteUrl);
    $domain = $siteUrlArray[0];
    unset($siteUrlArray[0]);
    
    $course = $blogType->getCoursePath($context, $siteUrlArray, $domain);
    if (isset($context->info[RESOURCE_LINK_ID]) && $context->info[RESOURCE_LINK_ID]) {
    	$course .= '-'.	$context->info[RESOURCE_LINK_ID];
    }
    
    $course = sanitize_user($course, true);
    //Bug wordpress doesn't get stye sheet if has a dot
    $course = str_replace('.','_',$course);

    $path_base = "/".implode("/",$siteUrlArray)."/".$course;
    $path_base = str_replace('//','/',$path_base);
    $path = $path_base."/";
	$path = str_replace('//','/',$path);
    
    $blog_created = false;
    $overwrite_plugins_theme = isset($context->info[OVERWRITE_PLUGINS_THEME])?$context->info[OVERWRITE_PLUGINS_THEME]==1:false;
    $overwrite_roles = isset($context->info[OVERWRITE_ROLES])?$context->info[OVERWRITE_ROLES]==1:false;
    
    $blog_id=domain_exists($domain, $path);
    $blog_is_new  = false;
    if ( ! isset($blog_id) ) {
        $title = __("Blog ").$blogType->getCourseName($context);
    	$blog_is_new  = true;

        $meta = $blogType->getMetaBlog($context);
        $old_site_language = get_site_option( 'WPLANG');
        $blogType->setLanguage($context);
        $blog_id = wpmu_create_blog($domain, $path, $title, $user_id, $meta);
        update_site_option( 'WPLANG', $old_site_language );
		$blogType->checkErrorCreatingBlog($blog_id, $path);
		$blog_created = true;
   }

    // Connect the user to the blog
    if ( isset($blog_id) ) {
    	
    	switch_to_blog($blog_id);
    	ob_start();
    	if ($overwrite_plugins_theme || $blog_created) {
    		$blogType->loadPlugins();
	    	$blogType->changeTheme();
    	}
    	//Agafem el rol anterior 
    	$old_role = null;
    	if (!$blog_created && !$overwrite_roles) {
    		$old_role_array = get_usermeta($user->id, 'wp_'.$blog_id.'_capabilities');
    		if (count($old_role_array)>0) {
    			foreach ($old_role_array as $key => $value) {
    				if ($value==true) {
    					$old_role = $key;
    				}
    			}
    		}
    	}
    	remove_user_from_blog ($uinfo->ID, $blog_id); 
    	$obj = new stdClass();
    	$obj->blog_id = $blog_id;
    	$obj->userkey = $userkey;
    	$obj->path_base = $path_base;
    	$obj->domain = $domain;
    	$obj->context = $context;
    	$obj->uinfoID = $uinfo->ID;
    	$obj->blog_is_new = $blog_is_new;
    	if ($overwrite_roles || $old_role==null ) {
    		$obj->role = $blogType->roleMapping($context->info[FIELD_ROLE_UOC_CAMPUS], $context->info);
    	} else {
    		$obj->role = $old_role;
    	}
    	$blogType->postActions($obj);
    	add_user_to_blog($blog_id, $uinfo->ID, $obj->role);
		//Si posem el restore_current_blog ens va al principi
    	//    	restore_current_blog();
    	ob_end_clean();	
    	
    }
    
    $redirecturl = get_option("siteurl");
    wp_redirect($redirecturl);
    exit();
}

add_filter('parse_request', 'lti_parse_request');

/**
*
* Check if there is any error
* @param unknown_type $context
* @return boolean
*/
function  is_lti_error_data($context){
	$error = false;
	if (!isset($context->info[CONTEXT_ID]) || strlen($context->info[CONTEXT_ID])==0) {
		$error = "Error: lti context_id is needed. Contact with the administrator of LMS.";
	}
	else {
		$userkey = $context->getUserKey();
		try {
			if (isset($context->info[USERNAME_FIELD])) {
				$configuration = new UtilsPropertiesWP(dirname(__FILE__).'/blogType/configuration/general.cfg');
				if ($configuration->getProperty('use_username')==1) {
					$userkey = $context->info[USERNAME_FIELD];
				}
			}
		} catch (Exception $e) {
		}
		$userkey = trim($userkey);
		
		if ( empty($userkey) )
			$error = 'Error: Empty username. Cannot create a user without username';
	}
	return $error;
}

function lti_get_secret_from_consumer_key() {
	global $wpdb;
	lti_maybe_create_db();
	$secret = null;
	$consumer_key = $_POST[ 'oauth_consumer_key' ] ;
	$row = $wpdb->get_row( $wpdb->prepare( "SELECT secret FROM {$wpdb->ltitable} WHERE consumer_key = %s and active='1'", $consumer_key ) );
	if (isset($row) && isset($row->secret))
		$secret = $row->secret; 
	return $secret;
}

function lti_consumer_keys_admin() {
	global $wpdb, $current_site;
	if ( false == lti_site_admin() ) {
		return false;
	}

	switch( $_POST[ 'action' ] ) {
		default:
	}
	lti_maybe_create_db();
	echo '<h2>' . __( 'LTI: Consumers Keys', 'wordpress-mu-lti' ) . '</h2>';
	if ( !empty( $_POST[ 'action' ] ) ) {
		check_admin_referer( 'lti' );
		$id = strtolower( $_POST[ 'id' ] );
		switch( $_POST[ 'action' ] ) {
			case "edit":
				$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->ltitable} WHERE id = %d", $id ) );
				if ( $row ) {
					lti_edit( $row );
				} else {
					echo "<h3>" . __( 'Provider not found', 'wordpress-mu-lti' ) . "</h3>";
				}
				break;
			case "save":
				if ( $id > 0 ) {
					$wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->ltitable} SET consumer_key = %s, secret = %s, active = %d WHERE id = %d", $_POST[ 'consumer_key' ], $_POST[ 'secret' ], $_POST[ 'active' ], $id ) );
					echo "<p><strong>" . __( 'Provider Updated', 'wordpress-mu-lti' ) . "</strong></p>";
				} else {
					$wpdb->query( $wpdb->prepare( "INSERT INTO {$wpdb->ltitable} ( `consumer_key`, `secret`, `active` ) VALUES ( %s, %s, %d )", $_POST[ 'consumer_key' ], $_POST[ 'secret' ], $_POST[ 'active' ] ) );
					echo "<p><strong>" . __( 'Provider Added', 'wordpress-mu-lti' ) . "</strong></p>";
				}
				break;
			case "del":
				$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->ltitable} WHERE id = %d", $id ) );
				echo "<p><strong>" . __( 'Provider Deleted', 'wordpress-mu-lti' ) . "</strong></p>";
				break;
			case "search":
				$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->ltitable} WHERE consumer_key LIKE %s", $_POST[ 'consumer_key' ]) );
				lti_listing( $rows, sprintf( __( "Searching for %s", 'wordpress-mu-lti' ), esc_html(  $_POST[ 'consumer_key' ] ) ) );
				break;
		}
	}

	echo "<h3>" . __( 'Search Domains', 'wordpress-mu-lti' ) . "</h3>";
	echo '<form method="POST">';
	wp_nonce_field( 'lti' );
	echo '<input type="hidden" name="action" value="search" />';
	echo '<p>';
	echo _e( "Consumer key:", 'wordpress-mu-lti' );
	echo " <input type='text' name='consumer_key' value='' /></p>";
	echo "<p><input type='submit' class='button-secondary' value='" . __( 'Search', 'wordpress-mu-lti' ) . "' /></p>";
	echo "</form><br />";
	lti_edit();
	$rows = $wpdb->get_results( "SELECT * FROM {$wpdb->ltitable} ORDER BY id DESC LIMIT 0,20" );
	lti_listing( $rows );
}


function lti_edit( $row = false ) {
	if ( is_object( $row ) ) {
		echo "<h3>" . __( 'Edit LTI', 'wordpress-mu-lti' ) . "</h3>";
	}  else {
		echo "<h3>" . __( 'New LTI', 'wordpress-mu-lti' ) . "</h3>";
		$row->id = -1;
		$row->consumer_key = '';
		$row->secret = '';
		$row->active = 1;
	}

	echo "<form method='POST'><input type='hidden' name='action' value='save' />";
	wp_nonce_field( 'lti' );
	echo "<input type='hidden' name='id' value='{$row->id}' /><table class='form-table'>\n";
	echo "<tr><th>" . __( 'Consumer key', 'wordpress-mu-lti' ) . "</th><td><input type='text' name='consumer_key' value='{$row->consumer_key}' /></td></tr>\n";
	echo "<tr><th>" . __( 'Secret', 'wordpress-mu-lti' ) . "</th><td><input type='text' name='secret' value='{$row->secret}' /></td></tr>\n";
	echo "<tr><th>" . __( 'Active', 'wordpress-mu-lti' ) . "</th><td><input type='checkbox' name='active' value='1' ";
	echo $row->active == 1 ? 'checked=1 ' : ' ';
	echo "/></td></tr>\n";
	echo "</table>";
	echo "<p><input type='submit' class='button-primary' value='" .__( 'Save', 'wordpress-mu-lti' ). "' /></p></form><br /><br />";
}


function lti_network_warning() {
	echo "<div id='lti-warning' class='updated fade'><p><strong>".__( 'LTI Disabled.', 'lti_network_warning' )."</strong> ".sprintf(__('You must <a href="%1$s">create a network</a> for it to work.', 'wordpress-mu-lti' ), "http://codex.wordpress.org/Create_A_Network")."</p></div>";
}

/*function lti_add_pages() {
	global $current_site, $wpdb, $wp_db_version, $wp_version;

	if ( !isset( $current_site ) && $wp_db_version >= 15260 ) {
		// WP 3.0 network hasn't been configured
		add_action('admin_notices', 'lti_network_warning');
		return false;
	}

	if ( lti_site_admin() && version_compare( $wp_version, '3.0.9', '<=' ) ) {
		if ( version_compare( $wp_version, '3.0.1', '<=' ) ) {
			add_submenu_page('wpmu-admin.php', __( 'LTI Consumer Keys', 'wordpress-mu-lti' ), __( 'LTI Consumer Keys', 'wordpress-mu-lti'), 'manage_options', 'lti_admin_page', 'lti_admin_page');
		} else {
			add_submenu_page('ms-admin.php', __( 'LTI Consumer Keys', 'wordpress-mu-lti' ), 'LTI Consumer Keys', 'manage_options', 'lti_admin_page', 'lti_admin_page');
		}
	}
}
add_action( 'admin_menu', 'lti_add_pages' );*/


function lti_network_pages() {
	add_submenu_page('settings.php', 'LTI Consumers Keys', 'LTI Consumers Keys', 'manage_options', 'lti_consumer_keys_admin', 'lti_consumer_keys_admin');
}
add_action( 'network_admin_menu', 'lti_network_pages' );

function get_lti_hash() {
	$remote_login_hash = get_site_option( 'lti_hash' );
	if ( null == $remote_login_hash ) {
		$remote_login_hash = md5( time() );
		update_site_option( 'lti_hash', $remote_login_hash );
	}
	return $remote_login_hash;
}

/**
 * 
 * Create table to store the consumers ands passwords if not exists
 */
function lti_maybe_create_db() {
	global $wpdb;

	get_lti_hash(); // initialise the remote login hash

	$wpdb->ltitable = $wpdb->base_prefix . 'lti';
	if ( lti_site_admin() ) {
		$created = 0;
		if ( $wpdb->get_var("SHOW TABLES LIKE '{$wpdb->ltitable}'") != $wpdb->ltitable ) {
			$wpdb->query( "CREATE TABLE IF NOT EXISTS `{$wpdb->ltitable}` (
				`id` bigint(20) NOT NULL auto_increment,
				`consumer_key` varchar(255) NOT NULL,
				`secret` varchar(255) NOT NULL,
				`active` tinyint(4) default '1',
				PRIMARY KEY  (`id`)
			);" );
			$created = 1;
		}
		if ( $created ) {
			?> <div id="message" class="updated fade"><p><strong><?php _e( 'LTI database table created.', 'wordpress-mu-lti' ) ?></strong></p></div> <?php
		}
	}

}

/**
 * 
 * Check if current user is admin
 */
function lti_site_admin() {
	if ( function_exists( 'is_super_admin' ) ) {
		return is_super_admin();
	} elseif ( function_exists( 'is_site_admin' ) ) {
		return is_site_admin();
	} else {
		return true;
	}
}

function lti_listing( $rows, $heading = '' ) {
	if ( $rows ) {
		if ( file_exists( ABSPATH . 'wp-admin/network/site-info.php' ) ) {
			$edit_url = network_admin_url( 'site-info.php' );
		} elseif ( file_exists( ABSPATH . 'wp-admin/ms-sites.php' ) ) {
			$edit_url = admin_url( 'ms-sites.php' );
		} else {
			$edit_url = admin_url( 'wpmu-blogs.php' );
		}
		if ( $heading != '' )
			echo "<h3>$heading</h3>";
		echo '<table class="widefat" cellspacing="0"><thead><tr><th>'.__( 'Consumer key', 'wordpress-mu-lti' ).'</th><th>'.__( 'Active', 'wordpress-mu-lti' ).'</th><th>'.__( 'Edit', 'wordpress-mu-lti' ).'</th><th>'.__( 'Delete', 'wordpress-mu-lti' ).'</th></tr></thead><tbody>';
		foreach( $rows as $row ) {
			echo "<tr><td>{$row->consumer_key}</td><td>";
		echo $row->active == 1 ? __( 'Yes',  'wordpress-mu-lti' ) : __( 'No',  'wordpress-mu-lti' );
		echo "</td><td><form method='POST'><input type='hidden' name='action' value='edit' /><input type='hidden' name='id' value='{$row->id}' />";
		wp_nonce_field( 'lti' );
		echo "<input type='submit' class='button-secondary' value='" .__( 'Edit', 'wordpress-mu-lti' ). "' /></form></td><td><form method='POST'><input type='hidden' name='action' value='del' /><input type='hidden' name='id' value='{$row->id}' />";
			wp_nonce_field( 'lti' );
			echo "<input type='submit' class='button-secondary' value='" .__( 'Del', 'wordpress-mu-lti' ). "' /></form>";
			echo "</td></tr>";
		}
		echo '</table>';
	}
}
?>
