<?php

//session_start();

define('IN_PHPBB', true);
$phpbb_root_path = (defined('PHPBB_ROOT_PATH')) ? PHPBB_ROOT_PATH : './';
$phpEx = substr(strrchr(__FILE__, '.'), 1);
include($phpbb_root_path . 'common.' . $phpEx);

// Start session management
$user->session_begin();
$auth->acl($user->data);
$user->setup();

//is this the callback file?

$provider = isset($_GET['provider']) ? $_GET['provider'] : null;
$action = isset($_GET['action']) ? $_GET['action'] : 'login';//lets default to login
$oauth_server = null;

//$oauth_user = null;

if ( !isset($provider) || !isset($action)) 
	die('the provider and your intention must be known');
	//just redirect to home and say notice

$providers = array(

	'facebook' => array(
		'id'     => '384172808330975',
		'secret' => '73c8df170d5b880129e3eae97350b06c',
		'scope'  => 'email',
	),
	'twitter' => array(
		'key'    => '',
		'secret' => '',		
	),
);


//servers
switch ($provider) {
case 'facebook':

  require './oauthorize/facebook/src/facebook.php';

  $oauth_server = $facebook = new Facebook(array( 'appId'  => $providers['facebook']['id'], 'secret' => $providers['facebook']['secret'] ));

  $oauth_user = $facebook->getUser(); // Get User ID

  // We may or may not have this data based on whether the user is logged in. If we have a $user id here, it means we know the user is logged into
  // Facebook, but we don't know if the access token is valid. An access token is invalid if the user logged out of Facebook.

  if ($oauth_user) {

    try {

      $oauth_profile = $facebook->api('/me'); // Proceed knowing you have a logged in user who's authenticated.

    } catch (FacebookApiException $e) {

      $oauth_profile = null;
      error_log($e); 

    }
  }

  break;

default:
  //# code...
  break;

} //end we should always have an oauth profile for data

//decide base on actions wanted, we need provider and oauth client id, and action as the intention

( isset($oauth_profile['id']) && ($oauth_username = $provider.'_'.$oauth_profile['id']) ) || redirect( $facebook->getLoginUrl( array('scope' => 'email')), false, true ); //get access token first
isset($oauth_column) || $oauth_column = 'pf_oauth_'.$provider.'_id';




switch ($action) {

case 'login':

    $facebook->destroySession(); //destroy session after getting fb data, id, username   
    
    $config['auth_method'] = "oauth"; // attempt oauth

    $auth->login($oauth_username, $oauth_column);

    if ($user->data['is_registered']) {

      //indicate that user was logged in by OAuth by registering session
      $session_oauth = array(
        'provider' => $provider,
        'id'       => $oauth_profile['id'],
        'username' => $oauth_profile['username'],
        'email'    => $oauth_profile['email'],
        );

      $sql = 'UPDATE ' . SESSIONS_TABLE . ' 
          SET session_oauth = "'.$db->sql_escape(serialize($session_oauth)).'"
          WHERE session_id = "' . $db->sql_escape($user->session_id) . '"';
      $db->sql_query($sql); 
      $db->sql_freeresult($result);
      
      $message = 'You were logged in through your [<a href="'.$oauth_profile['link'].'" target="_blank">'.$oauth_profile['name'].'</a>] '.$provider.' account. <a href="'.$phpbb_root_path.'oauthorize.php?mode=oauth&provider='.$provider.'&action=deauthorize">Deauthorize?</a> ';

      isset($_GET['in']) && $_GET['in'] == 'registration' && $message .= '<br />You already have an account with us.';
      isset($_GET['error']) && $_GET['error'] == 'duplicate' && $message .= '<br />Just one account is allowed.';

      meta_refresh(5, append_sid("{$phpbb_root_path}index.$phpEx"));
    
    } else {

      $message = 'No forum account is associated with this [<a href="'.$$oauth_profile['link'].'" target="_blank">'.$oauth_profile['name'].'</a>] '.$provider.' account. You may <a class="oauthorize_button" href="'.append_sid($phpbb_root_path.'oauthorize.php?provider='.$provider.'&action=register').'" >register a new account</a> or login as you normally would.';

      login_box(request_var('redirect', $phpbb_root_path.'oauthorize.php?mode=oauth&provider='.$provider.'&action=authorize'), $message);

    }
    trigger_error($message);

  break;

case 'authorize':
  //user should be logged in when in here
    if (!$user->data['is_registered']) {

      $message = 'You have to login first to authorize a forum account.';

      login_box(request_var('redirect', $phpbb_root_path.'oauthorize.php?mode=oauth&provider='.$provider.'&action=authorize'), $message);
      trigger_error($message);
    }
    


  $facebook->destroySession(); //destroy session after getting fb data, id, username 

  //bind only to one account, if there exists another account, autologin that
  //check if fb_id already mapped to any account because you dont want to bind it if its already being used   
  $sql='SELECT user_id FROM '.PROFILE_FIELDS_DATA_TABLE.' WHERE '.$oauth_column.'='. $oauth_profile['id'];
  $result = $db->sql_query($sql);
  $row = $db->sql_fetchrowset($result);
  $db->sql_freeresult($result);
  //redirect insted
  if ($row) redirect( $phpbb_root_path.'oauthorize.php?mode=oauth&provider='.$provider.'&action=login&error=duplicate');


  //check if there is really a profile field entry
  $sql='SELECT user_id FROM '.PROFILE_FIELDS_DATA_TABLE.' WHERE user_id ='. $user->data['user_id'];
  $result = $db->sql_query($sql);
  $row = $db->sql_fetchrowset($result);
  $db->sql_freeresult($result);  

  if (empty($row)) {

    $sql = 'INSERT INTO '.PROFILE_FIELDS_DATA_TABLE.' (`user_id`, `'.$oauth_column.'`) VALUES ('.$user->data['user_id'].', '.$oauth_profile['id'].')';
    $db->sql_query($sql);
    $message = 'Your [<a href="'.$oauth_profile['link'].'" target="_blank">'.$oauth_profile['name'].'</a>] '.$provider.' account is now mapped to this [<a href="./memberlist.php?mode=viewprofile&u='.$user->data['user_id'].'">'.$user->data['username'].'</a>] forum account.';
    
  }  else {

    $sql =  'UPDATE '.PROFILE_FIELDS_DATA_TABLE.' SET '.$oauth_column.' = '.$db->sql_escape($oauth_profile['id']).' WHERE user_id='.$user->data['user_id'];   
    $db->sql_query($sql);                
    $message = 'Your [<a href="'.$oauth_profile['link'].'" target="_blank">'.$oauth_profile['name'].'</a>] '.$provider.' account is now mapped to this [<a href="./memberlist.php?mode=viewprofile&u='.$user->data['user_id'].'">'.$user->data['username'].'</a>] forum account.';
  }

  //set something in session that shows account is fb authorized
  //indicate that user was logged in by OAuth by registering session
  $session_oauth = array(
    'provider' => $provider,
    'id'       => $oauth_profile['id'],
    'username' => $oauth_profile['username'],
    'email'    => $oauth_profile['email'],
    );

  $sql = 'UPDATE ' . SESSIONS_TABLE . ' 
      SET session_oauth = "'.$db->sql_escape(serialize($session_oauth)).'"
      WHERE session_id = "' . $db->sql_escape($user->session_id) . '"';
  $db->sql_query($sql);  

  meta_refresh(3, '/');
  trigger_error($message);

  break;

case 'deauthorize':

  $facebook->destroySession(); //destroy session after getting fb data, id, username 

  $sql =  'UPDATE '.PROFILE_FIELDS_DATA_TABLE.' SET '.$oauth_column.' = null WHERE user_id="'.$user->data['user_id'].'"';    
  
  $result = $db->sql_query($sql);
  $row = $db->sql_fetchrow($result);
  $db->sql_freeresult($result);

  //remove oauth data in session
  $sql = 'UPDATE ' . SESSIONS_TABLE . ' 
      SET session_oauth = ""
      WHERE session_id = "' . $db->sql_escape($user->session_id) . '"';
  $db->sql_query($sql); 
  $db->sql_freeresult($result);

  $message = 'Your [<a href="'.$oauth_profile['link'].'" target="_blank">'.$oauth_profile['name'].'</a>] '.$provider.' account is not linked with this forum account anymore.';

  meta_refresh(3, '/');

  trigger_error($message);

  break;

case 'register':

  $facebook->destroySession(); //destroy session after getting fb data, id, username

  //bind only to one account, if there exists another account, autologin that
  //check if fb_id already mapped to any account because you dont want to bind it if its already being used   
  $sql='SELECT user_id FROM '.PROFILE_FIELDS_DATA_TABLE.' WHERE '.$oauth_column.'='. $oauth_profile['id'];
  $result = $db->sql_query($sql);
  $row = $db->sql_fetchrowset($result);
  $db->sql_freeresult($result);
  //redirect insted
  if ($row) redirect( $phpbb_root_path.'oauthorize.php?mode=oauth&provider='.$provider.'&action=login&error=duplicate&in=registration');


  if ($user->data['is_registered']) {

    $message = 'You already have an account with us and is currently logged in.';

    meta_refresh(3, '/');

    trigger_error($message);
    
  } else {

    //set some session data
    $session_oauth = array(
      'provider' => $provider,
      'id'       => $oauth_profile['id'],
      'username' => $oauth_profile['username'],
      'email'    => $oauth_profile['email'],
      );

    $sql = 'UPDATE ' . SESSIONS_TABLE . ' 
        SET session_oauth = "'.$db->sql_escape(serialize($session_oauth)).'"
        WHERE session_id = "' . $db->sql_escape($user->session_id) . '"';
    $db->sql_query($sql); 
    $db->sql_freeresult($result);

    redirect( append_sid( $phpbb_root_path.'ucp.php?mode=register&type=oauth&provider='.$provider) );

  }

  break;
}

trigger_error($message);
