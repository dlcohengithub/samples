<?php

/**********************************************************

User

Type: 
Class

Use:
Manages users and user privileges in database.
Handles login/logout, global User object

Dabatase tables:
  bamboo_users
  bamboo_user_privileges

Access:
Class method

**********************************************************/
require_once('includes/email_functions.php');

class User
{
  protected $logged_in;
  protected $user_id;
  protected $username;
  protected $password;
  protected $first_name;
  protected $last_name;
  protected $full_name;
  protected $email;
  protected $email_pref;
//  protected $no_email;
//  protected $no_usage;
  protected $report_rows;
  protected $institution_id;
  protected $institution_name;
  protected $site_id;
  protected $site_name;
  protected $program_number;
  protected $privileges;
//  protected $login_start;
//  protected $login_count;
//  protected $login_first;
//  protected $tcpip;
  protected $collection_id;
  protected $rotation_id;
  protected $degree_1;
  protected $degree_2;
  protected $degree_3;
  protected $interface_type;
  protected $browser;
  
  private static $LastUserIdAdded = 0;
    
  private static $RankCache = array();
  private static $SpecialtyCache = array();
  private static $DegreeCache = array();

  /**********************************************************
  constructor: set session id
  **********************************************************/
  public function __construct($uid='')
  {
    if ($uid) $_SESSION['user_id'] = $uid;
    $this->resetUser();  
  }

  /**********************************************************
  resetUser: reset user object, clearing if session different
  **********************************************************/
  private function resetUser()
  {
    $this->clearUser();
    $this->user_id = isset($_SESSION['user_id'])?$_SESSION['user_id']:'';
    if ($this->user_id)
    {
      $info = self::getUser($this->user_id);
      $this->user_id = $info['user_id'];
    }
    if (!$this->user_id)
	{
	  $info = self::clearUserInfo();
	}

    $this->institution_id = $info['institution_id'];
    $this->institution_name = $info['institution_name'];
    $this->site_id = $info['site_id'];
    $this->site_name = $info['site_name'];
    $this->program_number = $info['program_number'];
    $this->username = $info['username'];
    $this->password = $info['password'];
    $this->first_name = $info['first_name'];
    $this->last_name = $info['last_name'];
    $this->full_name = self::makeFullName($info);
	$this->email = checkEmailUsername($info['email'],$info['username']);
    $this->email_pref = $info['email_pref'];
//    $this->no_email = $info['no_email'];
//    $this->no_usage = $info['no_usage'];
    $this->report_rows = $info['report_rows'];
    $this->privileges = $info['privileges'];
//    $this->login_start = $info['login_start'];
//    if (!$this->login_start) $login_start = '';
//    $this->login_count = $info['login_count'];
//    $this->login_first = $info['login_first'];
//    if (!$this->login_first) $login_first = '';
//    $this->tcpip = $info['tcpip'];
    $this->collection_id = $info['collection_id'];
    $this->rotation_id = $info['rotation_id'];
    $this->degree_1 = $info['degree_1'];
    $this->degree_2 = $info['degree_2'];
    $this->degree_3 = $info['degree_3'];
    $this->interface_type = $info['interface_type'];
      
    if ($this->user_id)
    {
      // Is this user_id logged in? 
      // Check session_id in user table against session_id in session table
      $sessid = session_id();
      $sessid_token = setQueryToken($sessid, true);
      $uid_token = setQueryToken($this->user_id, true);
      $sql = "SELECT * " .
             " FROM bamboo_users AS bu, bamboo_sessions AS bs" .
             " WHERE bu.session_id = bs.session_id " .
             "   AND bs.session_id = $sessid_token " .
             "   AND bu.user_id = $uid_token ";
//echo $sql;exit;             
	  $query_result = mysql_query($sql);
      handleSQLError($query_result, $sql);
      
      $rows = fetchAllAssocRows($query_result);
      $this->logged_in =  (count($rows) == 1);
    }
  }
  
  /**********************************************************
  clearUser: clear user object
  **********************************************************/
  private function clearUser()
  {
    $this->user_id = 0;
    $this->username = '';
    $this->password = '';
    $this->first_name = '';
    $this->last_name = '';
    $this->full_name = '';
    $this->email = '';
    $this->email_pref = EMAIL_PREF_DEFAULT;
//    $this->no_email = '0';
//    $this->no_usage = '1';
    $this->report_rows = DEFAULT_REPORT_ROWS;
    $this->logged_in = false;
    $this->institution_id = 0;
    $this->institution_name = '';
    $this->site_id = 0;
    $this->site_name = '';
    $this->program_number = '';
    $this->privileges = array();
//    $this->login_start = '';
//    $this->login_count = '';
//    $this->login_first = '';
//    $this->tcpip = '';
    $this->collection_id = 0;
    $this->rotation_id = 0;
	$this->security_question = '';
	$this->security_answer = '';
    $this->degree_1 = 1;
    $this->degree_2 = 1;
    $this->degree_3 = 1;
    $this->interface_type = INTERFACE_NONE;
    
    $this->browser = self::getBrowser(); // gets set even if not logged in
  }

  /**********************************************************
  clearUserInfo: clear user data
  **********************************************************/
  public static function clearUserInfo()
  {
    $info = array(
      'user_id'=>0,
      'username'=>'',
      'password'=>'',
	  'first_name'=>'',
	  'last_name'=>'',
	  'full_name'=>'',
	  'email'=>'',
      'email_pref'=>EMAIL_PREF_DEFAULT,
//      'no_email'=>0,
//      'no_usage'=>0,
      'report_rows'=>DEFAULT_REPORT_ROWS,
      'logged_in'=>false,
      'institution_id'=>0,
	  'institution_name'=>'',
      'site_id'=>0,
      'site_name'=>'',
      'download_file_format'=>'',
      'program_number'=>'',
      'privileges'=>array(),
//	  'login_start'=>'',
//      'login_count'=>'',
//      'login_first'=>'',
//      'tcpip'=>'',
      'collection_id'=>0,
      'rotation_id'=>0,
      'license_agree'=>1, // yes, default to agree when add/edit
	  'security_question'=>'',
	  'security_answer'=>'',
      'class'=>'',
      'rank'=>1,
      'rank_number'=>0,
      'rank_practicing'=>0,
      'specialty_1'=>1,
      'specialty_2'=>1,
      'specialty_3'=>1,
      'degree_1'=>1,
      'degree_2'=>1,
      'degree_3'=>1,
      'interface_type'=>INTERFACE_NONE,
      'medical_school'=>''
      );
	return $info;
  }

  /**********************************************************
  fakeUser: fake a login, for ip-based login
    ip_user: ip-based user
    site_name: name of site for login
  **********************************************************/
  public function fakeUser($ip_user,$site_name)
  // 'fake' a login - called everytime pages accessed using ip login
  {
    $this->clearUser();
    $sid = Site::getSiteId($site_name);
	if (!$sid) return;
    $this->site_id = $sid;
    $this->site_name = $site_name;
	if (!$this->site_name) return;
//    $this->tcpip = $_SERVER['REMOTE_ADDR'];
    $this->user_id = -1; // positive is valid user, 0 is invalid...
    $this->username = $ip_user;
    $this->institution_id = Site::getInstitutionForSite($sid);
    $this->institution_name = Institution::getInstitutionName($this->institution_id);
//	$this->privileges= Site::getSitePrivileges($sid);
	$this->privileges= array();
	$this->privileges[PRIV_CONNECT] = 1;
	$cid = Collection::getCollectionId('View-OMT');
	if ($cid) $this->privileges[PRIV_COLLECTION_BASE+$cid] = 1;
	$cid = Collection::getCollectionId('Extremity Eval');
	if ($cid) $this->privileges[PRIV_COLLECTION_BASE+$cid] = 1;
    $this->logged_in = true;

    // log top level pages hit by ip login
    if (defined('PAGE_TYPE') && (PAGE_TYPE == PAGE_TYPE_TOP))
    {
      $log_tcpip = $_SERVER['REMOTE_ADDR'];
      $log_page = $_SERVER['PHP_SELF'];
      $line = array("TCPIP: $log_tcpip","Page: $log_page");
      MyLog::writeLog(LOG_ADMIN_TYPE_IPLOGIN,$line);
    }
  }

  /**********************************************************
  isLoggedIn: is this user object logged in?
  **********************************************************/
  public function isLoggedIn()
  {
    return $this->logged_in;
  }
    
  /**********************************************************
  getUserId: return id for user object
  **********************************************************/
  public function getUserId()
  {
    if (!isset($this->user_id) || !$this->user_id) $this->user_id = 0; // cron?
    return $this->user_id;
  }
 
  /**********************************************************
  getPassword: return password for user object
  **********************************************************/
  public function getPassword()
  {
    return $this->password;
  }
 
  /**********************************************************
  getUsername: return username for user object
  **********************************************************/
  public function getUsername()
  {
    return $this->username;
  }
 
  /**********************************************************
  getFirstName: return first name for user object
  **********************************************************/
  public function getFirstName()
  {
    return $this->first_name;
  }
 
  /**********************************************************
  getLastName: return last name for user object
  **********************************************************/
  public function getLastName()
  {
    return $this->last_name;
  }

  /**********************************************************
  getFullName: return full name for user object
  **********************************************************/
  public function getFullName()
  {
    return $this->full_name;
  }

  /**********************************************************
  makeFullName: make full name from first/last name
    info: user data
  **********************************************************/
  public static function makeFullName($info)
  {
    $fn = '';
    $lname = isset($info['last_name'])?$info['last_name']:'';
    $fname = isset($info['first_name'])?$info['first_name']:'';
    $username = isset($info['username'])?$info['username']:'';
    if (strlen($lname)) $fn = $lname;
    if (strlen($fname)) 
    {
      if (strlen($fn)) $fn .= ", ";
      $fn .= $fname;
    }
    if (!strlen($fn)) $fn = $username;
    return $fn;
  }
   
  /**********************************************************
  getEmail: return email for user object
  **********************************************************/
  public function getEmail()
  {
    return $this->email;
//    if (!strlen($em)) $em = $this->user_id;
//    return $em;
  }
 
  /**********************************************************
  getEmailPref: return email preference for user object
  **********************************************************/
  public function getEmailPref()
  {
    return $this->email_pref;
  }
/*  
  /**********************************************************
  getNoEmail: return email preference for user object
  **********************************************************/
  public function getNoEmail()
  {
    return $this->no_email;
  }

  /**********************************************************
  getNoUsage: return usage preference for user object
  **********************************************************/
  public function getNoUsage()
  {
    return $this->no_usage;
  }
*/
  /**********************************************************
  getReportRows: return report rows for user object
  **********************************************************/
  public function getReportRows()
  {
    return $this->report_rows;
  }

  /**********************************************************
  getInstitutionId: return institution id for user object
  **********************************************************/
  public function getInstitutionId()
  {
    return $this->institution_id;
  }
  
  /**********************************************************
  getInstitutionName: return institution name for user object
  **********************************************************/
  public function getInstitutionName()
  {
    return $this->institution_name;
  }
 
  /**********************************************************
  getSiteId: return site id for user object
  **********************************************************/
  public function getSiteId()
  {
    return $this->site_id;
  }
 
  /**********************************************************
  getSiteName: return site name for user object
  **********************************************************/
  public function getSiteName()
  {
    return $this->site_name;
  }
 
  /**********************************************************
  getProgramNumber: return program number for user object
  **********************************************************/
  public function getProgramNumber()
  {
    return $this->program_number;
  }
 
  /**********************************************************
  getCollectionId: return current collection id for user object
  **********************************************************/
  public function getCollectionId()
  {
//    return $this->collection_id;
    $coll = $this->collection_id;
    $colls = Collection::getCollections();
    if ($this->hasPrivilege(PRIV_SYSTEM)) return $coll;
  	if ($this->hasPrivilege(PRIV_COLLECTION_BASE+$coll)) return $coll;
    // get valid one!
    foreach($colls as $key=>$values)
    {
      if ($this->hasPrivilege(PRIV_COLLECTION_BASE+$key)) return $key;
    }
    // still here?
    global $DEFAULT_COLLECTION_ID;
    return $DEFAULT_COLLECTION_ID;
  }

  /**********************************************************
  setCollectionId: set current collection id for user object
    collection_id: collection id
  **********************************************************/
  public function setCollectionId($collection_id)
  {
    global $DEFAULT_COLLECTION_ID;
    $coll = $collection_id;
    if ($this->hasPrivilege(PRIV_SYSTEM) || $this->hasPrivilege(PRIV_COLLECTION_BASE+$coll)) 
    {
    }
    else
    {
      $coll = $DEFAULT_COLLECTION_ID;
    }
    $uid = $this->user_id;
    $sql = "UPDATE bamboo_users " .
           "SET collection_id = $coll " .
           "WHERE user_id = $uid";

	$query_result = mysql_query($sql);
    handleSQLError($query_result, $sql);
  }

  /**********************************************************
  getRotationId: return current rotation id for user object
  **********************************************************/
  public function getRotationId()
  {
    return $this->rotation_id;
  }

  /**********************************************************
  getDegreeString: return full string for degree for user object
  **********************************************************/
  public function getDegreeString()
  {
    $degrees = array();
    $degree = self::getDegreeName($this->degree_1);
    if ($degree && strtolower($degree) != 'none') $degrees[] = $degree;
    $degree = self::getDegreeName($this->degree_2);
    if ($degree && strtolower($degree) != 'none') $degrees[] = $degree;
    $degree = self::getDegreeName($this->degree_3);
    if ($degree && strtolower($degree) != 'none') $degrees[] = $degree;
    $str = '';
    for($ix=0;$ix < count($degrees);$ix++)
    {
      $str .= ', ' . $degrees[$ix];
    }
    return $str;
  }

  /**********************************************************
  setRotationId: set rotation id for user object
    rotation_id: new rotation id
  **********************************************************/
  public function setRotationId($rotation_id)
  {
    $uid_token = setQueryToken($this->user_id, false);
    $rotation_id_token = setQueryToken($rotation_id, false);
    $sql = "UPDATE bamboo_users " .
           "SET rotation_id = $rotation_id_token " .
           "WHERE user_id = $uid_token";
           
	$query_result = mysql_query($sql);
    handleSQLError($query_result, $sql);
  }

  /**********************************************************
  getUserInfo: get full info for user
    uid: user id
  **********************************************************/
  public static function getUserInfo($uid)
  {
    return self::getUser($uid);
  }
  
  /**********************************************************
  getUser: get full info for user
    uid: user id or username
  **********************************************************/
  public static function getUser($uid)
  {
    if (is_numeric($uid)) // user id?
	{
      $uid_token = setQueryToken($uid, false);
      $sql = 
        "SELECT * " .
        " FROM bamboo_users " .
        " WHERE user_id = $uid_token";
	}
	else // username ?
	{
      $username_token = setQueryToken($uid, true);
      $sql = 
        "SELECT * " .
        "FROM bamboo_users " .
        "WHERE username = $username_token";
	}
 	$query_result = mysql_query($sql);
    handleSQLError($query_result, $sql);
    $rows = fetchAllAssocRows($query_result);
    
    if (count($rows))
    {
      $info = $rows[0];
      $info['institution_id']=Site::getInstitutionForSite($info['site_id']);
	  $info['institution_name']=Institution::getInstitutionName($info['institution_id']);
	  $info['site_name']=Site::getSiteName($info['site_id']);
      $info['full_name'] = self::makeFullName($info);
      $info['name'] = self::makeFullName($info);
      $info['privileges'] = self::getUserPrivileges($info['user_id']);
	  if (!$info['security_question'])
	  {
	    $info['security_question'] = 'What is the name of your site';
		$info['security_answer'] = $info['site_name'];
	  }
  	  $info['email'] = checkEmailUsername($info['email'],$info['username']);
    }
    else
    {
      $info = self::clearUserInfo();
    }
    return $info;
  }
 
  /**********************************************************
  addUser: add new user to database
    info: full user data
  **********************************************************/
  public static function addUser($info)
  {
    if (!User::validUsername($info['username'])) return false;
    DatabaseHelper::beginTransaction();

//    $iid_token = setQueryToken($info['institution_id'], false);
    $sid_token = setQueryToken($info['site_id'], false);
    $username_token = setQueryToken($info['username'], true);
    $password_token = setQueryToken($info['password'], true);
    $first_name_token = setQueryToken($info['first_name'], true);
    $last_name_token = setQueryToken($info['last_name'], true);
    $email_token = setQueryToken($info['email'], true);
    $program_number_token = setQueryToken($info['program_number'], true);
    $security_question_token = setQueryToken($info['security_question'], true);
    $security_answer_token = setQueryToken($info['security_answer'], true);
    $email_pref_token = setQueryToken($info['email_pref'], false);
//    $no_email_token = setQueryToken($info['no_email'], false);
//    $no_usage_token = setQueryToken($info['no_usage'], false);
    $report_rows_token = setQueryToken($info['report_rows'], false);
	// don't need to check license_agree - they aren't added unless it is set!
    
	if (!isset($info['class'])) // additional info set?
	{
	  $clear_info = self::clearUserInfo();
	  $info['class'] = $clear_info['class'];
	  $info['rank'] = $clear_info['rank'];
	  $info['rank_number'] = $clear_info['rank_number'];
	  $info['rank_practicing'] = $clear_info['rank_practicing'];
	  $info['specialty_1'] = $clear_info['specialty_1'];
	  $info['specialty_2'] = $clear_info['specialty_2'];
	  $info['specialty_3'] = $clear_info['specialty_3'];
	  $info['degree_1'] = $clear_info['degree_1'];
	  $info['degree_2'] = $clear_info['degree_2'];
	  $info['degree_3'] = $clear_info['degree_3'];
	  $info['medical_school'] = $clear_info['medical_school'];
	}
    $class_token = setQueryToken($info['class'], true);
    $rank_token = setQueryToken($info['rank'], false);
    $rank_number_token = setQueryToken($info['rank_number'], false);
    $rank_practicing_token = setQueryToken($info['rank_practicing'], false);
    $specialty_1_token = setQueryToken($info['specialty_1'], false);
    $specialty_2_token = setQueryToken($info['specialty_2'], false);
    $specialty_3_token = setQueryToken($info['specialty_3'], false);
    $degree_1_token = setQueryToken($info['degree_1'], false);
    $degree_2_token = setQueryToken($info['degree_2'], false);
    $degree_3_token = setQueryToken($info['degree_3'], false);
    $medical_school_token = setQueryToken($info['medical_school'], true);

 	$registration_time = MyDateTime::makeMySQLDateTimeFromSystem(time());
    $registration_time_token = setQueryToken($registration_time,true);
    
    $sql = "INSERT INTO bamboo_users " .
//           "SET institution_id = $iid_token, " .
           " SET site_id = $sid_token, " .
           "    program_number = $program_number_token, " .
           "    username = $username_token, " .
           "    password = $password_token, " .
           "    first_name = $first_name_token, " .
           "    last_name = $last_name_token, " .
           "    registration_time = $registration_time_token, " .
           "    email = $email_token, " .
		   "    security_question = $security_question_token, " .
		   "    security_answer = $security_answer_token, " .
		   "    email_pref = $email_pref_token, " .
//		   "    no_email = $no_email_token, " .
//		   "    no_usage = $no_usage_token, " .
		   "    report_rows = $report_rows_token, " .
//           "    tcpip = '', " .
           "    session_id = '', " .
		   "    class = $class_token, " .
		   "    rank = $rank_token, " .
		   "    rank_number = $rank_number_token, " .
		   "    rank_practicing = $rank_practicing_token, " .
		   "    specialty_1 = $specialty_1_token, " .
		   "    specialty_2 = $specialty_2_token, " .
		   "    specialty_3 = $specialty_3_token, " .
		   "    degree_1 = $degree_1_token, " .
		   "    degree_2 = $degree_2_token, " .
		   "    degree_3 = $degree_3_token, " .
		   "    medical_school = $medical_school_token, " .
           "    download_file_format = null";
           
  	$query_result = mysql_query($sql);
    handleSQLError($query_result, $sql);

    self::$LastUserIdAdded = mysql_insert_id();

//    $uid = self::getUserIdFromUsername($info['username']);
	$uid = mysql_insert_id();
    self::updateUserPrivileges($uid,$info['privileges']);

    DatabaseHelper::commit();
    return true;
  }

  /**********************************************************
  updateUser: update user in database
    uid: user id
    info: full user data
  **********************************************************/
  public static function updateUser($uid,$info)
  {
    if (!User::validUsername($info['username'])) return false;
    DatabaseHelper::beginTransaction();
    $set_password = false;
    $password_line = '';
    if (strlen($info['password'])) $set_password = true;

//    $iid_token = setQueryToken($info['institution_id'], false);
    $sid_token = setQueryToken($info['site_id'], false);
    $uid_token = setQueryToken($uid, false);
    $username_token = setQueryToken($info['username'], true);
    $first_name_token = setQueryToken($info['first_name'], true);
    $last_name_token = setQueryToken($info['last_name'], true);
    $email_token = setQueryToken($info['email'], true);
    $program_number_token = setQueryToken($info['program_number'], true);
    $security_question_token = setQueryToken($info['security_question'], true);
    $security_answer_token = setQueryToken($info['security_answer'], true);
    $email_pref_token = setQueryToken($info['email_pref'], false);
//    $no_email_token = setQueryToken($info['no_email'], false);
//    $no_usage_token = setQueryToken($info['no_usage'], false);
    $report_rows_token = setQueryToken($info['report_rows'], false);
    if ($set_password)
    {
      $password_token = setQueryToken($info['password'], true);
      $password_line = "    ,password = $password_token ";
    }
	if (!isset($info['class'])) // additional info set?
	{
	  $clear_info = self::clearUserInfo();
	  $info['class'] = $clear_info['class'];
	  $info['rank'] = $clear_info['rank'];
	  $info['rank_number'] = $clear_info['rank_number'];
	  $info['rank_practicing'] = $clear_info['rank_practicing'];
	  $info['specialty_1'] = $clear_info['specialty_1'];
	  $info['specialty_2'] = $clear_info['specialty_2'];
	  $info['specialty_3'] = $clear_info['specialty_3'];
	  $info['degree_1'] = $clear_info['degree_1'];
	  $info['degree_2'] = $clear_info['degree_2'];
	  $info['degree_3'] = $clear_info['degree_3'];
	  $info['medical_school'] = $clear_info['medical_school'];
	}
    $class_token = setQueryToken($info['class'], true);
    $rank_token = setQueryToken($info['rank'], false);
    $rank_number_token = setQueryToken($info['rank_number'], false);
    $rank_practicing_token = setQueryToken($info['rank_practicing'], false);
    $specialty_1_token = setQueryToken($info['specialty_1'], false);
    $specialty_2_token = setQueryToken($info['specialty_2'], false);
    $specialty_3_token = setQueryToken($info['specialty_3'], false);
    $degree_1_token = setQueryToken($info['degree_1'], false);
    $degree_2_token = setQueryToken($info['degree_2'], false);
    $degree_3_token = setQueryToken($info['degree_3'], false);
    $medical_school_token = setQueryToken($info['medical_school'], true);

    $sql = "UPDATE bamboo_users " .
           "SET site_id = $sid_token, " .
           "    program_number = $program_number_token, " .
           "    username = $username_token, " .
//           "    institution_id = $iid_token, " .
           "    first_name = $first_name_token, " .
           "    last_name = $last_name_token, " .
		   "    security_question = $security_question_token, " .
		   "    security_answer = $security_answer_token, " .
		   "    email_pref = $email_pref_token, " .
//		   "    no_email = $no_email_token, " .
//		   "    no_usage = $no_usage_token, " .
		   "    report_rows = $report_rows_token, " .
           "    email = $email_token, " .
		   "    class = $class_token, " .
		   "    rank = $rank_token, " .
		   "    rank_number = $rank_number_token, " .
		   "    rank_practicing = $rank_practicing_token, " .
		   "    specialty_1 = $specialty_1_token, " .
		   "    specialty_2 = $specialty_2_token, " .
		   "    specialty_3 = $specialty_3_token, " .
		   "    degree_1 = $degree_1_token, " .
		   "    degree_2 = $degree_2_token, " .
		   "    degree_3 = $degree_3_token, " .
		   "    medical_school = $medical_school_token " .
           $password_line .
           "WHERE user_id = $uid_token";
           
  	$query_result = mysql_query($sql);
    handleSQLError($query_result, $sql);

    self::updateUserPrivileges($uid,$info['privileges']);

    DatabaseHelper::commit();
    return true;
  }

  /**********************************************************
  deleteUser: delete user and related data from database
    uid: user id
  **********************************************************/
  public static function deleteUser($uid)
  {
    DatabaseHelper::beginTransaction();

    // remove quiz answers
    $uid_token = setQueryToken($uid, true);
    $sql = "DELETE FROM bamboo_quiz_user_answers " .
           "WHERE quiz_user_id IN " .
           " (SELECT quiz_user_id " .
           "  FROM bamboo_quiz_users bqu " .
           "  WHERE user_id = $uid_token)";
  	$query_result = mysql_query($sql);
    handleSQLError($query_result, $sql);

    // remove from quiz users
    $sql = "DELETE FROM bamboo_quiz_users " .
           "WHERE user_id = $uid_token";
  	$query_result = mysql_query($sql);
    handleSQLError($query_result, $sql);

    // remove privileges
    $privileges = array();
    self::updateUserPrivileges($uid,$privileges);

    // remove reviews
    $sql = "DELETE FROM bamboo_reviews " .
           "WHERE user_id = $uid_token";
  	$query_result = mysql_query($sql);
    handleSQLError($query_result, $sql);

    // finally remove user
    $sql = "DELETE FROM bamboo_users " .
           "WHERE user_id = $uid_token";
           
  	$query_result = mysql_query($sql);
    handleSQLError($query_result, $sql);
    
    // remove alerts
    Alert::deleteAlertsForUser($uid);

    // remove quiz workshops
    QuizWorkshop::deleteQuizWorkshop($uid);
    
    DatabaseHelper::commit();
    return true;
  }

  /**********************************************************
  getUserPrivileges: get privileges for user
    uid: user id
  **********************************************************/
  public static function getUserPrivileges($uid)
  {
    $uid_token = $uid;
    $info ['privileges'] = array();
    $sql = 
      "SELECT privilege_id " .
      "FROM bamboo_user_privileges " .
      "WHERE user_id = $uid_token";
   	$query_result = mysql_query($sql);
    handleSQLError($query_result, $sql);
    $rows = fetchAllAssocRows($query_result);
    $privileges = array();  
	if (count($rows))
	{
      foreach($rows as $row)
      {
        $priv = $row['privilege_id'];
        $privileges[$priv] = true;
      }
	}
	return $privileges;
  }

  /**********************************************************
  updateUserPrivileges: update privileges for user
    uid: user id
    privileges: array of privileges
  **********************************************************/
  public static function updateUserPrivileges($uid,$privileges)
  {
    DatabaseHelper::beginTransaction();
    $uid_token = setQueryToken($uid, false);
	$sql = "DELETE FROM bamboo_user_privileges WHERE user_id = $uid_token";
	$query_result = mysql_query($sql);
    handleSQLError($query_result, $sql);

    if (count($privileges))
    {
      foreach($privileges as $priv=>$priv_flag)
      {
        $priv_token = setQueryToken($priv, true);
        $sql = "INSERT INTO bamboo_user_privileges " .
               "SET user_id = $uid_token, " .
               "    privilege_id = $priv_token";
               
  	  	$query_result = mysql_query($sql);
        handleSQLError($query_result, $sql);
      }
    }
    DatabaseHelper::commit();
    return true;
  }

  /**********************************************************
  deleteInstitutionUsers: delete users for institution
    iid: institution id
  **********************************************************/
  /*
  public static function deleteInstitutionUsers($iid)
  {
    DatabaseHelper::beginTransaction();

    // delete quiz answers
    $iid_token = setQueryToken($iid, true);
    $sql = "DELETE FROM bamboo_quiz_user_answers " .
           "WHERE quiz_user_id IN " .
           " (SELECT quiz_user_id " .
           "  FROM bamboo_quiz_users bqu " .
           "  WHERE user_id IN " .
           "  (SELECT user_id " .
           "   FROM bamboo_users bu, bamboo_sites bs " .
           "   WHERE bu.site_id = bs.site_id AND bs.institution_id = $iid_token) " .
           "  )";
  	$query_result = mysql_query($sql);
    handleSQLError($query_result, $sql);

    // delete quiz users
    $sql = "DELETE FROM bamboo_quiz_users " .
           "WHERE user_id IN " .
           "  (SELECT user_id " .
           "   FROM bamboo_users bu, bamboo_sites bs " .
           "   WHERE bu.site_id = bs.site_id AND bs.institution_id = $iid_token) " .
           "  )";
  	$query_result = mysql_query($sql);
    handleSQLError($query_result, $sql);

    // delete user privileges
    $sql = "DELETE FROM bamboo_user_privileges " .
           "WHERE user_id IN " .
           "  (SELECT user_id " .
           "   FROM bamboo_users bu, bamboo_sites bs " .
           "   WHERE bu.site_id = bs.site_id AND bs.institution_id = $iid_token) " .
           "  )";
  	$query_result = mysql_query($sql);
    handleSQLError($query_result, $sql);

    // delete reviews
    $sql = "DELETE FROM bamboo_reviews " .
           "WHERE user_id IN " .
           "  (SELECT user_id " .
           "   FROM bamboo_users bu, bamboo_sites bs " .
           "   WHERE bu.site_id = bs.site_id AND bs.institution_id = $iid_token) " .
           "  )";
  	$query_result = mysql_query($sql);
    handleSQLError($query_result, $sql);

    // delete users
    $sql = "DELETE FROM bamboo_users " .
           "WHERE institution_id = $iid_token";
  	$query_result = mysql_query($sql);
    handleSQLError($query_result, $sql);
    
    DatabaseHelper::commit();
    return true;
  }
  */

  /**********************************************************
  updatePassword: update password for user
    uid: user id
    password: encrypted password
  **********************************************************/
  public static function updatePassword($uid, $pwd) 
  {
    $uid_token = setQueryToken($uid, false);
    $pwd_token = setQueryToken($pwd, true);

    $sql = "UPDATE bamboo_users " .
           "SET password = $pwd_token " .
           "WHERE user_id = $uid_token " .
           "LIMIT 1";
           
  	$query_result = mysql_query($sql);
    handleSQLError($query_result, $sql);
	
	return true;
  }

  /**********************************************************
  login: log user in
    uid: user id - not used now, get it from user object
  **********************************************************/
//  public function login($uid=0)
  public function login($login_type=0)
  {
    $sid = session_id();
//    if (!$uid) $uid = $this->user_id;
    $uid = $this->user_id;
    $uid_token = setQueryToken($uid, false);
//    $login_start = MyDateTime::makeMySQLDateTimeFromSystem(time());
//    $login_start_token = setQueryToken($login_start, true);
//    $login_first = $login_start;
//    $login_first_token = setQueryToken($login_first, true);
//    $tcpip = $_SERVER['REMOTE_ADDR'];
//    $tcpip_token = setQueryToken($tcpip, true);
    $sid_token = setQueryToken($sid, true);
    
    DatabaseHelper::beginTransaction();
    
    // this could tromp on any existing login - ie, user logged in
    // at location A, user logs in at location B, location A login overwritten
    $sql = "UPDATE bamboo_users " .
//           "SET login_start = $login_start_token, " .
//           "    login_end = NULL, " .
//           "    login_count = login_count + 1, " .
//           "    login_first = IFNULL(login_first,$login_first_token),  " .
//           "    tcpip = $tcpip_token, " .
//           "    session_id = $sid_token " .
           "SET session_id = $sid_token " .
           "WHERE user_id = $uid_token " .
           "LIMIT 1";
//echo $sql; exit;           
  	$query_result = mysql_query($sql);
    handleSQLError($query_result, $sql);

    LoginLog::addLoginLog($uid,$sid,$login_type);
    // could have same session id, just logged out, logged back in, error, etc
//    $sql = "INSERT INTO bamboo_login_log " .
//           "SET user_id = $uid_token, " .
//           "    session_id = $sid_token, " .
//           "    login_start = $login_start_token, " .
//           "    login_end = NULL, " .
//           "    login_type = $login_type, " .
//           "    login_tcpip = $tcpip_token";
//  	$query_result = mysql_query($sql);
//    handleSQLError($query_result, $sql);

    DatabaseHelper::commit();
    
    $this->logged_in = true;
    
//    Register::activateUser($uid,'',true); // record first login time
	return true;
  }

  /**********************************************************
  logout: log user out - get user id from current user
  **********************************************************/
  public function logout()
  {
    $sid = session_id();
    $uid = $this->user_id;
    $uid_token = setQueryToken($uid, false);
//    $login_end = MyDateTime::makeMySQLDateTimeFromSystem(time());
//    $login_end_token = setQueryToken($login_end, true);
    $sid_token = setQueryToken($sid, true);
    
    DatabaseHelper::beginTransaction();
    
    $sql = "UPDATE bamboo_users " .
//           "SET login_end = $login_end_token, " .
//           "    session_id = '' " .
           "  SET session_id = '' " .
           "WHERE user_id = $uid_token " .
           "  AND session_id = $sid_token " .
           "LIMIT 1";
  	$query_result = mysql_query($sql);
    handleSQLError($query_result, $sql);
    
    LoginLog::updateLoginLog($uid,$sid);
//    $sql = "UPDATE bamboo_login_log " .
//           "SET login_end = $login_end_token " .
//           "WHERE user_id = $uid_token " .
//           "  AND session_id = $sid_token " .
//           "  AND ISNULL(login_end) "; // could hit more than one if there are errors
//  	$query_result = mysql_query($sql);
//    handleSQLError($query_result, $sql);

    DatabaseHelper::commit();

    $this->clearUser();
    $_SESSION = array();
    session_destroy();
    session_regenerate_id();
	return true;
  }
  
  /**********************************************************
  logoutByUserId: log user out - specific one, for admin
    uid: user id
  **********************************************************/
  public static function logoutByUserId($uid)
  // logout another user
  {
    $info = self::getUser($uid);
    if (!$info['user_id']) return true;
    
    $uid_token = setQueryToken($uid, false);
//    $login_end = MyDateTime::makeMySQLDateTimeFromSystem(time());
//    $login_end_token = setQueryToken($login_end, true);
    $sid = $info['session_id'];
    $sid_token = setQueryToken($sid, true);

    DatabaseHelper::beginTransaction();

    $sql = "UPDATE bamboo_users " .
//           "SET login_end = $login_end_token, " .
           "  SET session_id = '' " .
           "WHERE user_id = $uid_token " .
           "  AND session_id = $sid_token " .
           "LIMIT 1";
  	$query_result = mysql_query($sql);
    handleSQLError($query_result, $sql);
    
    LoginLog::updateLoginLog($uid,$sid);
//    $sql = "UPDATE bamboo_login_log " .
//           "SET login_end = $login_end_token " .
//           "WHERE user_id = $uid_token " .
//           "  AND session_id = $sid_token " .
//           "  AND ISNULL(login_end) "; // could hit more than one if there are errors
//  	$query_result = mysql_query($sql);
//    handleSQLError($query_result, $sql);

    $sql = "DELETE FROM bamboo_sessions " .
           "WHERE session_id = $sid_token " .
           "LIMIT 1";
  	$query_result = mysql_query($sql);
    handleSQLError($query_result, $sql);
     
    DatabaseHelper::commit();
	return true;
  }
 
  /**********************************************************
  getLoggedInUsers: get list of currently logged in users
  **********************************************************/
  public static function getLoggedInUsers()
  {
    // assume if user has matching session id in session table, he is logged in
    // (could be more careful and check session data's user id) - DONE
    $sql = "SELECT user_id, session_data " .
           " FROM bamboo_users AS bu, bamboo_sessions AS bs" .
           " WHERE bu.session_id = bs.session_id ";
  	$query_result = mysql_query($sql);
    handleSQLError($query_result, $sql);
    
    $assoc = array();
    $rows = fetchAllAssocRows($query_result);
    if (count($rows))
    {
      foreach($rows as $row)
      {
        $sdassoc = DatabaseSession::parseSessionData($row['session_data']);
        if ($sdassoc['user_id'] == $row['user_id']) $assoc[$row['user_id']] = 1;
      }
    }
    return $assoc;
  }

  /**********************************************************
  hasPrivilege: see if current user object has this privilege
  **********************************************************/
  public function hasPrivilege($priv)
  {
    if (!is_numeric($priv))
    {
      $priv = self::privStringToInt($priv);
    }

    // if need specific privilege and have it, you're ok
    if (isset($this->privileges[$priv])) return true;

//if ($priv == 20001) 
//{
//echo PRIV_COLLECTION_BASE;
//print_r($this->privileges);
////echo $this->privileges[$priv];
//exit;
//}
    // next, check for any privileges that are hierarchical -
	// ie, need inst admin and have system admin, that's fine

    // hierarchy exception - can turn off access for anyone!
    if ($priv == PRIV_CONNECT) return false;

    // system admin can do anything    
    if (isset($this->privileges[PRIV_SYSTEM])) return true;

    // still need to check if site requested in this inst
    if (isset($this->privileges[PRIV_INSTITUTION_ADMIN]) && ($priv == PRIV_SITE_ADMIN)) return true;
    
//    // institution admin can do anything except system    
//    if (isset($this->privileges[PRIV_INSTITUTION_ADMIN]))
//    {
//      if ($priv != PRIV_SYSTEM) return true;
//    }
    return false;
  }

  /**********************************************************
  privStringToInt: turn privilege string to equivalent int
  **********************************************************/
  static private function privStringToInt($priv_string)
  {
    $priv_string = strtolower($priv_string);
    if (strpos($priv_string,'connect') === 0)
    {
      return PRIV_CONNECT;
    }
    elseif (strpos($priv_string,'institution') === 0)
    {
      return PRIV_INSTITUTION_ADMIN;
    }
    elseif (strpos($priv_string,'site') === 0)
    {
      return PRIV_SITE_ADMIN;
    }
    elseif (strpos($priv_string,'lecture') === 0)
    {
      return PRIV_LECTURE;
    }
    else
    {
      return PRIV_NONE;
    }
    
  }  
  
  /**********************************************************
  doesUsernameExist: see if username already in use
    username: username
  **********************************************************/
  static public function doesUsernameExist($username)
  {
    $username_token = setQueryToken($username, true);

    $sql = "SELECT user_id " .
           " FROM bamboo_users " .
           " WHERE LCASE(username) = LCASE($username_token)";
	$query_result = mysql_query($sql);
    handleSQLError($query_result, $sql);
    $rows = fetchAllAssocRows($query_result);
    if (count($rows)) return true;
	return false;
  }

  /**********************************************************
  getUserIdFromUsername: get user id from speciofic username
    username: username
  **********************************************************/
  static public function getUserIdFromUsername($username)
  {
    $username_token = setQueryToken($username, true);

    $sql = "SELECT user_id " .
           " FROM bamboo_users " .
           " WHERE LCASE(username) = LCASE($username_token)";
	$query_result = mysql_query($sql);
    handleSQLError($query_result, $sql);
    $rows = fetchAllAssocRows($query_result);
    if (!count($rows)) return 0;
	return $rows[0]['user_id'];
  }

  /**********************************************************
  getLastUserIdAdded: get user id of last user added to database
  **********************************************************/
  public static function getLastUserIdAdded()
  {
    return self::$LastUserIdAdded;
  }

  /**********************************************************
  getRanks: get list of ranks for user object
  **********************************************************/
  public static function getRanks() 
  {
    if (count(self::$RankCache)) return self::$RankCache;
    $sql = "SELECT * FROM bamboo_user_ranks ORDER BY ordering";
	$result = mysql_query($sql);
    handleSQLError($result, $sql);
    $rows = fetchAllAssocRows($result);
    $ranks = array();
    foreach($rows as $row)
    {
	  $id = $row['rank_id'];
	  $name = $row['rank_name'];
      $ranks[$id] = array('id'=>$id,'name'=>$name);
    }
	self::$RankCache = $ranks;
	return $ranks;
  }

  /**********************************************************
  getRankName: get rankname from rank id
    rank_id: rank id
  **********************************************************/
  public static function getRankName($rank_id)
  {
    if (!count(self::$RankCache)) self::getRanks();
	if (isset(self::$RankCache[$rank_id])) return self::$RankCache[$rank_id]['name'];
	return '';
  }

  /**********************************************************
  getRankId: get rank id from rank name
    rank_name: rank name
  **********************************************************/
  public static function getRankId($rank_name)
  {
    if (!count(self::$RankCache)) self::getRanks();
	foreach(self::$RankCache as $id=>$values)
	{
	  if (strtolower($rank_name) == strtolower($values['name'])) return $id;
	}
	return 0;
  }

  /**********************************************************
  getDegrees: get list of degrees for user object
  **********************************************************/
  public static function getDegrees() 
  {
    if (count(self::$DegreeCache)) return self::$DegreeCache;
    $sql = "SELECT * FROM bamboo_user_degrees ORDER BY ordering";
	$result = mysql_query($sql);
    handleSQLError($result, $sql);
    $rows = fetchAllAssocRows($result);
    $degrees = array();
    foreach($rows as $row)
    {
	  $id = $row['degree_id'];
	  $name = $row['degree_name'];
      $degrees[$id] = array('id'=>$id,'name'=>$name);
    }
	self::$DegreeCache = $degrees;
	return $degrees;
  }

  /**********************************************************
  getDegreeName: get degree name from degree id
    degree_id: degree id
  **********************************************************/
  public static function getDegreeName($degree_id)
  {
    if (!count(self::$DegreeCache)) self::getDegrees();
	if (isset(self::$DegreeCache[$degree_id])) return self::$DegreeCache[$degree_id]['name'];
	return '';
  }

  /**********************************************************
  getDegreeId: get degree id from degree name
    degree_name: degree name
  **********************************************************/
  public static function getDegreeId($degree_name)
  {
    if (!count(self::$DegreeCache)) self::getDegrees();
	foreach(self::$DegreeCache as $id=>$values)
	{
	  if (strtolower($degree_name) == strtolower($values['name'])) return $id;
	}
	return 0;
  }

  /**********************************************************
  getSpecialties: get list of specialties for user object
  **********************************************************/
  public static function getSpecialties() 
  {
    if (count(self::$SpecialtyCache)) return self::$SpecialtyCache;
//    $sql = "SELECT * FROM bamboo_user_specialties ORDER BY ordering";
    $sql = "SELECT * FROM bamboo_user_specialties ORDER BY LOWER(specialty_name)";
	$result = mysql_query($sql);
    handleSQLError($result, $sql);
    $rows = fetchAllAssocRows($result);
    $specialties = array();
    $none_id = -1;
    $none_name = 'None';
    $none_first = true;
    foreach($rows as $row)
    {
	  $id = $row['specialty_id'];
	  $name = $row['specialty_name'];
      if ($none_first  && (strtolower($none_name) == strtolower($name)))
      {
        $none_id = $id;
      }
      else
      {
        $specialties[$id] = array('id'=>$id,'name'=>$name);
      }
    }
    if ($none_first && ($none_id > 0))
    {
      // move none to top
      $new_specialties = array();
      $new_specialties[$none_id] = array('id'=>$none_id,'name'=>$none_name);
      if (count($specialties))
      {
        foreach($specialties as $key=>$val)
        {
          $new_specialties[$key] = $val;
        }
      }
      $specialties = $new_specialties;
    }
	self::$SpecialtyCache = $specialties;
	return $specialties;
  }

  /**********************************************************
  getSpecialtyName: get specialty name from specialty id
    specialty_id: specialty id
  **********************************************************/
  public static function getSpecialtyName($specialty_id)
  {
    if (!count(self::$SpecialtyCache)) self::getSpecialties();
	if (isset(self::$SpecialtyCache[$specialty_id])) return self::$SpecialtyCache[$specialty_id]['name'];
	return '';
  }

  /**********************************************************
  getSpecialtyId: get specialty id from specialty name
    specialty_name: specialty name
  **********************************************************/
  public static function getSpecialtyId($specialty_name)
  {
    if (!count(self::$SpecialtyCache)) self::getSpecialties();
	foreach(self::$SpecialtyCache as $id=>$values)
	{
	  if (strtolower($specialty_name) == strtolower($values['name'])) return $id;
	}
	return 0;
  }

  /**********************************************************
  getSiteForUser: get site id for specified user id
    uid: user id
  **********************************************************/
  public static function getSiteForUser($uid)
  {
    $sql = "SELECT site_id FROM bamboo_users WHERE user_id = $uid";
 	$query_result = mysql_query($sql);
    handleSQLError($query_result, $sql);
    $rows = fetchAllAssocRows($query_result);
    
    if (!count($rows)) return 0;
    return $rows[0]['site_id'];
  }

  /**********************************************************
  getInstitutionForUser: get institution id for specified user id
    uid: user id
  **********************************************************/
  public static function getInstitutionForUser($uid)
  {
    $sql = "SELECT bs.institution_id FROM bamboo_users bu,bamboo_sites bs WHERE bu.site_id = bs.site_id";
 	$query_result = mysql_query($sql);
    handleSQLError($query_result, $sql);
    $rows = fetchAllAssocRows($query_result);
    
    if (!count($rows)) return 0;
    return $rows[0]['institution_id'];
  }
  
  /**********************************************************
  doesUserManageUser: does the user object manage this user id
    info: info of user id to check if managed
  **********************************************************/
  public function doesUserManageUser($info)
  // does current user manage this user?
  {
    if ($info['user_id'] == $this->user_id) return 1;
    if ($this->hasPrivilege(PRIV_SYSTEM)) return 1;
    if ($this->hasPrivilege(PRIV_INSTITUTION_ADMIN) && ($info['institution_id'] == $this->institution_id)) return 1;
    if ($this->hasPrivilege(PRIV_SITE_ADMIN) && ($info['site_id'] == $this->site_id)) return 1;
    return 0;
  }
  
  /**********************************************************
  cleanupUsersByDate: remove users based on activation
    date: date
    iid: institution id
    sid: site id
    count_only: only count how many would be deleted
  **********************************************************/
  public static function cleanupUsersByDate($date,$iid=0,$sid=0,$count_only=0)
  {
/*  
    $sql = "SELECT user_id " .
           "FROM     
SELECT user_id FROM bamboo_users WHERE
(NOT ISNULL(login_start) AND (login_start < '2010-01-01')) 
OR (ISNULL(login_start) AND NOT ISNULL(activation_start) AND (activation_start < '2010-01-01'))
OR (ISNULL(login_start) AND ISNULL(activation_start) AND NOT ISNULL(registration_time) AND (registration_time < '2010-01-01'))
    if login_last is not null use that
    if login_last not set
      if activation_start set then use that
    else
      leave him be
    return 0;
*/    
  }

  /**********************************************************
  getBrowser: get browser info for user
  **********************************************************/
  public static function getBrowser()
  {
    $browser = new Browser();

    $name = $browser->getBrowser();
    $version = $browser->getVersion();
    $platform = $browser->getPlatform();
      
    $supported = array(
      Browser::BROWSER_OPERA,
      Browser::BROWSER_IE,
      Browser::BROWSER_FIREFOX,
      Browser::BROWSER_MOZILLA,
      Browser::BROWSER_SAFARI,
      Browser::BROWSER_CHROME,
      // just guessing here...
      Browser::BROWSER_POCKET_IE,
      Browser::BROWSER_IPHONE,
      Browser::BROWSER_IPOD,
      Browser::BROWSER_IPAD,
      Browser::BROWSER_ANDROID,
      Browser::BROWSER_NOKIA,
      Browser::BROWSER_NOKIA_S60,
      Browser::BROWSER_BLACKBERRY);

    $mobile = $browser->isMobile()?1:0;
//    if ($name == Browser::BROWSER_ANDROID) $mobile = 2; // lame!
//$mobile = 1;    
    if (in_array($name, $supported))
    {
      $supported = 1;
    }
    else
    {
      $supported = 0;
    }
    
    $browser = array('name'=>$name, 'version'=>$version, 'supported'=>$supported, 'mobile'=>$mobile, 'platform'=>$platform);
    return $browser;
  }
 
  /**********************************************************
  isMobile: is user on mobile device
    should be correct, but user can override
  **********************************************************/
  public function isMobile()
  {
    // 2012-10-08 this will actually be overridden at login....
//    return $this->browser['mobile'];
//    $interface_type = $this->getInterfaceType();
    if ($this->interface_type == INTERFACE_MOBILE)
      return 1;
    else
      return 0;
  }

  /**********************************************************
  setMobile: set if user on mobile device
  **********************************************************/
  public function setMobile($is_mobile=1)
  {
    // not really used anymore
//    $this->browser['mobile'] = $is_mobile;
  }

  /**********************************************************
  setInterfaceType: set whether to use standard or mobile interface for current user
  **********************************************************/
  public function setInterfaceType($interface_type)
  {
    if ($interface_type == INTERFACE_NONE) $interface_type = INTERFACE_STANDARD;
    $_COOKIE['interface_type'] = $interface_type;
    $this->interface_type = $interface_type;
    if (!$this->isLoggedIn()) return $interface_type;
    $uid = $this->user_id;
    $sql = "UPDATE bamboo_users " .
           "SET interface_type = $interface_type " .
           "WHERE user_id = $uid";
           
	$query_result = mysql_query($sql);
    handleSQLError($query_result, $sql);
    return true;
  }
  
  /**********************************************************
  getInterfaceType: get standard or mobile interface for current user
  **********************************************************/
  public function getInterfaceType()
  {
/*
    if ($this->isLoggedIn()) 
    {
      $uid = $this->user_id;
      $sql = "SELECT interface_type " .
             "FROM bamboo_users " .
             "WHERE user_id = $uid";
           
  	  $query_result = mysql_query($sql);
      handleSQLError($query_result, $sql);
      $rows = fetchAllAssocRows($query_result);
      if (count($rows)) 
      {
        $row = array_shift($rows);
        $interface_type = $row['interface_type'];
        return $interface_type;
      }
    }
*/    

    $user_interface_type = $this->interface_type;

    if (isset($_COOKIE['interface_type']) && ($_COOKIE['interface_type'] != ''))
      $cookie_interface_type = $_COOKIE['interface_type'];
    else
      $cookie_interface_type = INTERFACE_NONE;
    
//    if (($user_interface_type == INTERFACE_NONE) && ($cookie_interface_type == INTERFACE_NONE))


    // interface not set - figure it out
    if ($cookie_interface_type == INTERFACE_NONE)
    {
      $browser = new Browser();
//      if ($browser->isMobile()) 
//        $browser_interface_type = INTERFACE_MOBILE;
//      else
//        $browser_interface_type = INTERFACE_STANDARD;

      $browser_type = $browser->getBrowser();
      if ($browser_type != Browser::BROWSER_UNKNOWN)
      {
        if ($browser->isMobile()) 
          $browser_interface_type = INTERFACE_MOBILE;
        else
          $browser_interface_type = INTERFACE_STANDARD;
      }
      else
      {
        switch($browser->getPlatform())
        {
		  case Browser::PLATFORM_WINDOWS:
		  case Browser::PLATFORM_WINDOWS:
		  case Browser::PLATFORM_WINDOWS_CE:
		  case Browser::PLATFORM_APPLE:
		  case Browser::PLATFORM_LINUX:
		  case Browser::PLATFORM_OS2:
		  case Browser::PLATFORM_FREEBSD:
		  case Browser::PLATFORM_OPENBSD:
		  case Browser::PLATFORM_NETBSD:
		  case Browser::PLATFORM_SUNOS:
		  case Browser::PLATFORM_OPENSOLARIS:
	      case Browser::PLATFORM_IPAD:
            $browser_interface_type = INTERFACE_STANDARD;
            break;
          default:
            $browser_interface_type = INTERFACE_MOBILE;
            break;
        }
      }
      $this->setInterfaceType($browser_interface_type);
      $interface_type = $browser_interface_type;
    }
    elseif ($user_interface_type != $cookie_interface_type)
    {
      $this->setInterfaceType($cookie_interface_type);
      $interface_type = $cookie_interface_type;
    }
    else
    {
      $interface_type = $user_interface_type;
    }
    return $interface_type;
  }
  
  /**********************************************************
  validUsername: is username of valid format?
    username: username
  **********************************************************/
  public static function validUsername($username)
  {
    // analogous javascript function as well
    $username = trim($username);
    // must be at least this many chars, not completely numeric
    if ((strlen($username) < NAME_MIN_LENGTH) || !preg_match('/[a-zA-Z]/',$username)) return false;
    return true;
  }

  /**********************************************************
  getEmailUserIdMap: return associative array of users/emails
  **********************************************************/
  public static function getEmailUserIdMap()
  {
    $map = array();
    $sql = "SELECT bu.user_id, bu.site_id, bu.email, bs.institution_id " .
           "FROM bamboo_users bu, bamboo_sites bs " .
           "WHERE bu.site_id = bs.site_id";
	$query_result = mysql_query($sql);
    handleSQLError($query_result, $sql);
    $rows = fetchAllAssocRows($query_result);
	if (!count($rows)) return $map;
    foreach($rows as $row)
    {
      $lemail = strtolower($row['email']);
      if (!$lemail) continue;
      if (isset($map[$lemail])) continue; // dupe email! --- should not happen
      $map[$lemail] = array('user_id'=>$row['user_id'],'institution_id'=>$row['institution_id'],'site_id'=>$row['site_id']);
    }
    return $map;
  }

  /**********************************************************
  getIdsByEmail: get user info based on email
    email: email address
  **********************************************************/
  public static function getIdsByEmail($email)
  // Get user id, inst id, site id based on email - 
  // should not have more than one (used to allow this!), so just return first one found!
  // When user registers, they must enter unique email.
  {
    $ids = array('institution_id'=>0, 'site_id'=>0, 'user_id'=>0);
    $email_token = setQueryToken(strtolower($email),true);
    $sql = "SELECT bu.user_id, bu.site_id, bs.institution_id FROM bamboo_users bu, bamboo_sites bs where LOWER(bu.email) = $email_token AND bs.site_id = bu.site_id";
	$query_result = mysql_query($sql);
    handleSQLError($query_result, $sql);
    $rows = fetchAllAssocRows($query_result);
	if (!count($rows)) return $ids;
    $row = array_shift($rows); // could be more than 1 -- shouldn't!
    $ids['institution_id'] = $row['institution_id'];
    $ids['site_id'] = $row['site_id'];
    $ids['user_id'] = $row['user_id'];
    return $ids;
  }

  /**********************************************************
  getUserIdByEmail: get user id based on email
    email: email address
  **********************************************************/
  public static function getUserIdByEmail($email)
  // Get user id based on email - 
  // could have more than one (used to allow this!), so just return first one found!
  // When user registers, they must enter unique email.
  {
    $ids = self::getIdsByEmail($email);
    return $ids['user_id'];
  }

  /**********************************************************
  getUserIdByName: get user id based on first/last name
    first_name: first_name
    last_name: last_name
  **********************************************************/
  public static function getUserIdByName($first_name, $last_name)
  // Get user id based on first and last name - 
  // could have more than one, so just return first one found!
  // When user registers, they must enter unique first/last names.
  // However, when admin defines user in a form, can use more than once.
  {
    $first_name_token = setQueryToken(strtolower($first_name),true);
    $last_name_token = setQueryToken(strtolower($last_name),true);
    $sql = "SELECT user_id FROM bamboo_users where LOWER(first_name) = $first_name_token AND LOWER(last_name) = $last_name_token";
	$query_result = mysql_query($sql);
    handleSQLError($query_result, $sql);
    $rows = fetchAllAssocRows($query_result);
	if (!count($rows)) return 0;
    $row = array_shift($rows); // could be more than 1
    return $row['user_id'];
  }
  
  /**********************************************************
  getUsernameFromUserId: get user name for specified user id
    uid: user id
  **********************************************************/
  public static function getUsernameFromUserId($uid)
  {
    $sql = 
      "SELECT username " .
        " FROM bamboo_users " .
        " WHERE user_id = $uid";
 	$query_result = mysql_query($sql);
    handleSQLError($query_result, $sql);
    $rows = fetchAllAssocRows($query_result);
    if (!count($rows)) return '';
    return $rows[0]['username'];
  }

  /**********************************************************
  setRotationForUser: set current rotation for specified user
    uid: user id
    rotation_id: rotation id
  **********************************************************/
  public function setRotationIdForUser($uid,$rotation_id)
  {
    $sql = "UPDATE bamboo_users " .
           "SET rotation_id = $rotation_id " .
           "WHERE user_id = $uid";
           
	$query_result = mysql_query($sql);
    handleSQLError($query_result, $sql);
  }

  /**********************************************************
  isRealUser: is current user object real? or ip-based login?
  **********************************************************/
  public function isRealUser()
  {
    if ($this->getUserId() <= 0) return false;
    return true;
  }

  /**********************************************************
  convertToDomainUsername: convert specified username and other info to domain-based username
    iid: institution id
    sid: site id
    user_id_or_name: user id or username
  **********************************************************/
  public static function convertToDomainUsername($iid,$sid,$user_id_or_name)
  {
//    if (is_numeric($user_id_or_name))
//    {
//      $user_info = User::getUserInfo($user_id_or_name);
//      $institution_info = Institution::getInstitution($user_info['institution_id']);
//      $site_info = Site::getSite($user_info['site_id']);
//      $username = $user_info['username'];
//    }
//    else
    {
      $institution_info = Institution::getInstitution($iid);
      $site_info = Site::getSite($sid);
      $username = $user_id_or_name;
    }
    if (!$site_info['user_add_key']) return '';
    if (!$username) return '';
    $abbv = trim($institution_info['institution_abbv']);
    if (!$abbv) return '';
//    $abbv = strtolower($abbv);
    $abbv = str_replace('-','_',$abbv); // first '-' should be separator
    $abbv = $abbv .= '-';
        
    if (substr($username,0,strlen($abbv)) == $abbv) return $username; // already converted
    return $abbv.$username; // institution-abbreviation-username
  }

  /**********************************************************
  makeValidUsername: make a valid username with first and last name, adding numbers if conflicts
    email: email
    first: first name
    last: last name
  **********************************************************/
  public static function makeValidUsername($email,$first='',$last='')
  {
    if ($email) // use email part before domain
    {
      $at = strpos($email,'@');
      if ($at === false) $at = strlen($email);
      $username = substr($email,0,$at);
    }
    else // use first/last
    {
      $username=strtolower($first).'_'.strtolower($last);
    }
    // !User::validUsername($username)
    $username = preg_replace('/\s/','',$username);
    if (!preg_match('/[a-zA-Z]/',$username)) { $username = 'x'.$username; } // at least 1 char
    $cnt = 0;
    while(strlen($username) < NAME_MIN_LENGTH) 
    { 
      $cnt += 1;
      if ($cnt > 9) $cnt = 1;
      $username .= $cnt; 
    } // long enough?
    $cnt = 0;
    while(1)
    {
      $test_username = $username;
      if ($cnt > 0) $test_username .= $cnt;
      $info = self::getUser($test_username);
      if (!$info['user_id']) return $test_username;
      $cnt += 1;
    }
  }
  
  /**********************************************************
  getUsernames: get associative array of usernames/userids 
    uids: users ids
  **********************************************************/
  public static function getUsernames($uids)
  // get userid->username map
  {
    $uid_list = '(' . join($uids,',') . ')';
    $sql = 
      "SELECT user_id,username " .
        " FROM bamboo_users " .
        " WHERE user_id IN $uid_list ";
 	$query_result = mysql_query($sql);
    handleSQLError($query_result, $sql);
    $rows = fetchAllAssocRows($query_result);
    $users = array();
    if (count($rows))
    {
      foreach($rows as $row)
      {
        $users[$row['user_id']] = $row['username'];
      }
    }
    return $users;
  }
  
  /**********************************************************
  getEmailPrefTexts: return email preference text
  **********************************************************/
  public static function getEmailPrefTexts()
  {
    return
            array(
              EMAIL_PREF_UNSPECIFIED    =>'Unspecified',
              EMAIL_PREF_NONE           =>'No Status or Alerts',
              EMAIL_PREF_POSTED         =>'Alerts Only',
              EMAIL_PREF_WEEKLY         =>'Weekly Status Only',
              EMAIL_PREF_MONTHLY        =>'Monthly Status Only',
              EMAIL_PREF_WEEKLY_POSTED  =>'Alerts and Weekly Status',
              EMAIL_PREF_MONTHLY_POSTED =>'Alerts and Monthly Status');
  }

  /**********************************************************
  getEmailPrefText: return email preference text for preference
    email_pref: email preference
  **********************************************************/
  public static function getEmailPrefText($email_pref)
  {
    $email_pref_texts = self::getEmailPrefTexts();
    if (isset($email_pref_texts[$email_pref])) return $email_pref_texts[$email_pref];
    return $email_pref_texts[EMAIL_PREF_UNSPECIFIED];
  }
  
  /**********************************************************
  getEmailPrefUsageOk: see if specified email pref is allowed
    email_pref: email preference
  **********************************************************/
  public static function getEmailPrefUsageOk($email_pref)
  {
    $usage_ok = array(EMAIL_PREF_WEEKLY, EMAIL_PREF_MONTHLY, EMAIL_PREF_WEEKLY_POSTED, EMAIL_PREF_MONTHLY_POSTED);
    return in_array($email_pref,$usage_ok);
  }

  /**********************************************************
  getEmailPrefPostedOk: see if specified email pref is allowed
    email_pref: email preference
  **********************************************************/
  public static function getEmailPrefPostedOk($email_pref)
  {
    $posted_ok = array(EMAIL_PREF_POSTED, EMAIL_PREF_WEEKLY_POSTED, EMAIL_PREF_MONTHLY_POSTED);
    return in_array($email_pref,$posted_ok);
  }
}
?>
