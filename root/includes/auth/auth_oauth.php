<?php

/*	
*/
if (!defined('IN_PHPBB'))
{
   exit;
}

//requires string e.g. facebook_00203203020
function login_oauth(&$username, &$oauth_column)
{
	global $db;

	$oauth_arr = explode('_', $username);
	$provider = $oauth_arr[0];
	$oauth_id = $oauth_arr[1];

	isset($oauth_column) || $oauth_column = 'pf_oauth_'.$provider.'_id'; //change to oauth_provider_id

	if (!$username)
	{
		return array(
			'status'	=> LOGIN_ERROR_USERNAME,
			'error_msg'	=> 'NO_ERROR_OAUTH',
			'user_row'	=> array('user_id' => ANONYMOUS),
		);
	}

	$sql = 'SELECT user_id FROM '.PROFILE_FIELDS_DATA_TABLE.' WHERE '.$oauth_column.' = '.$oauth_id;
	$result = $db->sql_query($sql);
	$row = $db->sql_fetchrow($result);
	$db->sql_freeresult($result);
	
	if (sizeof($row) > 0) {	

		// Successful login... set user_login_attempts to zero...
		return array(
			'status'		=> LOGIN_SUCCESS,
			'error_msg'		=> false,
			'user_row'		=> $row,
		);

	} else {

		return array(
			'status'	=> LOGIN_ERROR_USERNAME,
			'error_msg'	=> 'LOGIN_ERROR_USERNAME',
			'user_row'	=> array('user_id' => ANONYMOUS),
		);

	}
	
}





























?>
