<?php
/******************************************************************
 * cdav is a Dolibarr module
 * It allows caldav and carddav clients to sync with Dolibarr
 * calendars and contacts.
 *
 * cdav is distributed under GNU/GPLv3 license
 * (see COPYING file)
 *
 * cdav uses Sabre/dav library http://sabre.io/dav/
 * Sabre/dav is distributed under use the three-clause BSD-license
 * 
 * Author : Befox SARL http://www.befox.fr/
 *
 ******************************************************************/

error_reporting(E_ALL & ~E_NOTICE);
ini_set("display_errors", 0);
ini_set("log_errors", 1);

function exception_error_handler($errno, $errstr, $errfile, $errline) {
	if(function_exists("debug_log"))
	{
		debug_log("Error $errno : $errstr - $errfile @ $errline");
		foreach(debug_backtrace(false) as $trace)
			debug_log(" - ".$trace['file'].'@'.$trace['line'].' '.$trace['function'].'(...)');
	}
	throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
}

// debug
//$debug_file = fopen( sys_get_temp_dir() . '/cdav_'.date('Ymd').'.log','a');
$debug_file = false;

function debug_log($txt)
{
	global $debug_file;
	if ($debug_file)
	{
		fputs($debug_file, '========' . date('H:i:s').': '.$txt."\n");
		fflush($debug_file);
	}
}

// HTTP auth workaround for php in fastcgi mode HTTP_AUTHORIZATION set by rewrite engine in .htaccess
if( isset($_SERVER['HTTP_AUTHORIZATION']) && 
	(!isset($_SERVER['PHP_AUTH_USER']) || !isset($_SERVER['PHP_AUTH_PW'])) )
{
	$rAuth = explode(':', base64_decode(substr($_SERVER['HTTP_AUTHORIZATION'], 6)));

	if(count($rAuth)>1)
	{
		$_SERVER['PHP_AUTH_USER'] = $rAuth[0];
		$_SERVER['PHP_AUTH_PW'] = $rAuth[1];
	}
}

// HTTP auth workaround for php in fastcgi mode REDIRECT_HTTP_AUTHORIZATION set by rewrite engine in .htaccess
if( isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION']) && 
	(!isset($_SERVER['PHP_AUTH_USER']) || !isset($_SERVER['PHP_AUTH_PW'])) )
{
	$rAuth = explode(':', base64_decode(substr($_SERVER['REDIRECT_HTTP_AUTHORIZATION'], 6)));

	if(count($rAuth)>1)
	{
		$_SERVER['PHP_AUTH_USER'] = $rAuth[0];
		$_SERVER['PHP_AUTH_PW'] = $rAuth[1];
	}
}

define('NOTOKENRENEWAL',1); 								// Disables token renewal
if (! defined('NOLOGIN')) define('NOLOGIN','1');
if (! defined('NOCSRFCHECK')) define('NOCSRFCHECK','1');	// We accept to go on this page from external web site.
if (! defined('NOREQUIREMENU')) define('NOREQUIREMENU','1');
if (! defined('NOREQUIREHTML')) define('NOREQUIREHTML','1');
if (! defined('NOREQUIREAJAX')) define('NOREQUIREAJAX','1');
// This server is stateless (HTTP Basic auth on each request) : without a dolibarr session,
// $_SESSION['dol_entity'] can not override the entity read from the url (see DOLENTITY below)
if (! defined('NOSESSION')) define('NOSESSION','1');
function llxHeader() { }
function llxFooter() { }

// Multicompany : the entity may be given as the first segment of the path,
// ex: /cdav/server.php/2/calendars/login/2-cal-login
// It has to be known before loading Dolibarr environment because master.inc.php
// reads the DOLENTITY constant to set $conf->entity.
$cdav_entity = 0;
$cdav_pathinfo = '';
if(isset($_SERVER['PATH_INFO']) && $_SERVER['PATH_INFO']!='')
{
	$cdav_pathinfo = $_SERVER['PATH_INFO'];
}
elseif(!empty($_SERVER['REQUEST_URI']) && !empty($_SERVER['SCRIPT_NAME']))
{
	// PATH_INFO is not always set (fastcgi, rewrite rules...) so rebuild it from REQUEST_URI
	$cdav_uri = $_SERVER['REQUEST_URI'];
	$cdav_pos = strpos($cdav_uri, '?');
	if($cdav_pos !== false)
		$cdav_uri = substr($cdav_uri, 0, $cdav_pos);
	$cdav_uri = rawurldecode($cdav_uri);
	if(strpos($cdav_uri, $_SERVER['SCRIPT_NAME']) === 0)
		$cdav_pathinfo = substr($cdav_uri, strlen($_SERVER['SCRIPT_NAME']));
}
// A numeric first segment can only be an entity : no dav collection is named with digits only
if(preg_match('#^/(\d+)(/|$)#', $cdav_pathinfo, $cdav_reg))
{
	$cdav_entity = (int) $cdav_reg[1];
	define('DOLENTITY', $cdav_entity);
}

// Load Dolibarr environment
$res = 0;
// Try main.inc.php into web root known defined into CONTEXT_DOCUMENT_ROOT (not always defined)
if (!$res && !empty($_SERVER["CONTEXT_DOCUMENT_ROOT"])) {
	$res = @include $_SERVER["CONTEXT_DOCUMENT_ROOT"]."/main.inc.php";
}
// Try main.inc.php into web root detected using web root calculated from SCRIPT_FILENAME
$tmp = empty($_SERVER['SCRIPT_FILENAME']) ? '' : $_SERVER['SCRIPT_FILENAME']; $tmp2 = realpath(__FILE__); $i = strlen($tmp) - 1; $j = strlen($tmp2) - 1;
while ($i > 0 && $j > 0 && isset($tmp[$i]) && isset($tmp2[$j]) && $tmp[$i] == $tmp2[$j]) {
	$i--;
	$j--;
}
if (!$res && $i > 0 && file_exists(substr($tmp, 0, ($i + 1))."/main.inc.php")) {
	$res = @include substr($tmp, 0, ($i + 1))."/main.inc.php";
}
if (!$res && $i > 0 && file_exists(dirname(substr($tmp, 0, ($i + 1)))."/main.inc.php")) {
	$res = @include dirname(substr($tmp, 0, ($i + 1)))."/main.inc.php";
}
// Try main.inc.php using relative path
if (!$res && file_exists("../main.inc.php")) {
	$res = @include "../main.inc.php";
}
if (!$res && file_exists("../../main.inc.php")) {
	$res = @include "../../main.inc.php";
}
if (!$res && file_exists("../../../main.inc.php")) {
	$res = @include "../../../main.inc.php";
}
if (!$res) {
	die("Include of main fails");
}

if(!defined('DOL_DOCUMENT_ROOT'))
	define('DOL_DOCUMENT_ROOT', $dolibarr_main_document_root);

require DOL_DOCUMENT_ROOT.'/core/lib/security2.lib.php';	// auth method
require_once DOL_DOCUMENT_ROOT.'/contact/class/contact.class.php';

if(!$conf->cdav->enabled)
	die('module CDav not enabled !');

//set_error_handler("exception_error_handler", E_ERROR | E_USER_ERROR |
//				E_CORE_ERROR | E_COMPILE_ERROR | E_RECOVERABLE_ERROR );


require_once './lib/cdav.lib.php';

// define CDAV_CONTACT_TAG if not
if(!defined('CDAV_CONTACT_TAG'))
{
	if(isset($conf->global->CDAV_CONTACT_TAG))
		define('CDAV_CONTACT_TAG', $conf->global->CDAV_CONTACT_TAG);
	else
		define('CDAV_CONTACT_TAG', '');
}

// define CDAV_URI_KEY if not
if(!defined('CDAV_URI_KEY'))
{
	if(isset($conf->global->CDAV_URI_KEY))
		define('CDAV_URI_KEY', $conf->global->CDAV_URI_KEY);
	else
		define('CDAV_URI_KEY', substr(md5($_SERVER['HTTP_HOST']),0,8));
}

// define CDAV_TASK_USER_ROLE if not
if(!defined('CDAV_TASK_USER_ROLE'))
{
	if(isset($conf->global->CDAV_TASK_USER_ROLE))
		define('CDAV_TASK_USER_ROLE', $conf->global->CDAV_TASK_USER_ROLE);
	else
		die('Module CDav is not properly configured : Project user role not set !');
}

// define CDAV_SYNC_PAST if not
if(!defined('CDAV_SYNC_PAST'))
{
	if(isset($conf->global->CDAV_SYNC_PAST))
		define('CDAV_SYNC_PAST', $conf->global->CDAV_SYNC_PAST);
	else
		die('Module CDav is not properly configured : Period to sync not set !');
}

// define CDAV_SYNC_FUTURE if not
if(!defined('CDAV_SYNC_FUTURE'))
{
	if(isset($conf->global->CDAV_SYNC_FUTURE))
		define('CDAV_SYNC_FUTURE', $conf->global->CDAV_SYNC_FUTURE);
	else
		die('Module CDav is not properly configured : Period to sync not set !');
}

// define CDAV_TASK_SYNC if not
if(!defined('CDAV_TASK_SYNC'))
{
	if(isset($conf->global->CDAV_TASK_SYNC))
		define('CDAV_TASK_SYNC', $conf->global->CDAV_TASK_SYNC);
	else
		define('CDAV_TASK_SYNC', '0');
}

// define CDAV_INTERV_SYNC if not
if(!defined('CDAV_INTERV_SYNC'))
{
	if(isset($conf->global->CDAV_INTERV_SYNC))
		define('CDAV_INTERV_SYNC', $conf->global->CDAV_INTERV_SYNC);
	else
		define('CDAV_INTERV_SYNC', '0');
}

// define CDAV_INTERV_USER_ROLE if not
if(!defined('CDAV_INTERV_USER_ROLE'))
{
	if(isset($conf->global->CDAV_INTERV_USER_ROLE))
		define('CDAV_INTERV_USER_ROLE', $conf->global->CDAV_INTERV_USER_ROLE);
	else
		define('CDAV_INTERV_USER_ROLE', '0');
}

// define CDAV_THIRD_SYNC if not
if(!defined('CDAV_THIRD_SYNC'))
{
	if(isset($conf->global->CDAV_THIRD_SYNC))
		define('CDAV_THIRD_SYNC', $conf->global->CDAV_THIRD_SYNC);
	else
		define('CDAV_THIRD_SYNC', '0');
}

// define CDAV_MEMBER_SYNC if not
if(!defined('CDAV_MEMBER_SYNC'))
{
	if(isset($conf->global->CDAV_MEMBER_SYNC))
		define('CDAV_MEMBER_SYNC', $conf->global->CDAV_MEMBER_SYNC);
	else
		define('CDAV_MEMBER_SYNC', '0');
}

// 0 < CDAV_ADDRESSBOOK_ID_SHIFT = Contacts
// CDAV_ADDRESSBOOK_ID_SHIFT   < 2*CDAV_ADDRESSBOOK_ID_SHIFT = Thirdparties
// 2*CDAV_ADDRESSBOOK_ID_SHIFT < 3*CDAV_ADDRESSBOOK_ID_SHIFT = Members
define('CDAV_ADDRESSBOOK_ID_SHIFT', 100000); 

// Sabre/dav configuration

use Sabre\DAV;
use Sabre\DAVACL;

// The autoloader
require DOL_DOCUMENT_ROOT.'/includes/sabre/autoload.php';
require './class/PrincipalsDolibarr.php';
require './class/CardDAVDolibarr.php';
require './class/CalDAVDolibarr.php';

$user = new User($db);

if(isset($_SERVER['PHP_AUTH_USER']) && $_SERVER['PHP_AUTH_USER']!='')
{
	// entity has to be forced : with the default -1, User::fetch searches in every entity
	// and returns nothing (USERDUPLICATEFOUND) when the same login exists in several ones
	$user->fetch('', $_SERVER['PHP_AUTH_USER'], '', 0, $conf->entity);
	$user->getrights();
}

$cdavLib = new CdavLib($user, $db, $langs);

// Authentication
$authBackend = new DAV\Auth\Backend\BasicCallBack(function ($username, $password)
{
	global $user;
	global $conf;
	global $dolibarr_main_authentication;
	
	
	if ( ! isset($user->login) || $user->login=='')
	{
		debug_log("Authentication failed 1 for user $username with pass ".str_pad('', strlen($password), '*'));
		return false;
	}
	if (!empty($user->societe_id) || !empty($user->socid)) // external user
	{
		debug_log("Authentication failed 2 for user $username with pass ".str_pad('', strlen($password), '*'));
		return false;
	}
	if ($user->login!=$username)
	{
		debug_log("Authentication failed 3 for user $username with pass ".str_pad('', strlen($password), '*'));
		return false;
	}
	/*if ($user->pass_indatabase_crypted == '' || dol_hash($password) != $user->pass_indatabase_crypted)
		return false;*/
	
	// Authentication mode
	// disable googlerecaptcha
	$dolibarr_main_authentication = str_replace('googlerecaptcha','dolibarr', $dolibarr_main_authentication);
	if (empty($dolibarr_main_authentication))
		$dolibarr_main_authentication='http,dolibarr';
	$authmode = explode(',',$dolibarr_main_authentication);
	$entity = (GETPOST('entity','int') ? GETPOST('entity','int') : (!empty($conf->entity) ? $conf->entity : 1));
	if( ((float) DOL_VERSION < 11.0) && checkLoginPassEntity($username,$password,$entity,$authmode)!=$username
		||
		((float) DOL_VERSION >= 11.0) && checkLoginPassEntity($username,$password,$entity,$authmode,'dav')!=$username )
	{
		debug_log("Authentication failed 4 for user $username with pass ".str_pad('', strlen($password), '*'));
		return false;
	}
	debug_log("Authentication OK for user $username ");
	return true;
});

$authBackend->setRealm('Dolibarr');

// The lock manager is reponsible for making sure users don't overwrite
// each others changes.
// Multicompany : dolibarr creates the module directories into DOL_DATA_ROOT/<entity> when entity > 1
$cdav_data_root = $dolibarr_main_data_root.($conf->entity > 1 ? '/'.$conf->entity : '');
// only when the entity data root exists, so that a bogus entity in the url creates nothing
if(is_dir($cdav_data_root) && !is_dir($cdav_data_root.'/cdav/public'))
	dol_mkdir($cdav_data_root.'/cdav/public', $dolibarr_main_data_root);

$lockBackend = new DAV\Locks\Backend\File($cdav_data_root.'/cdav/.locks');

// Principals Backend
$principalBackend = new DAVACL\PrincipalBackend\Dolibarr($user,$db);

// CardDav & CalDav Backend
$carddavBackend   = new Sabre\CardDAV\Backend\Dolibarr($user,$db,$langs);
$caldavBackend	= new Sabre\CalDAV\Backend\Dolibarr($user,$db,$langs, $cdavLib);

// Setting up the directory tree //
$nodes = array(
	// /principals
	new DAVACL\PrincipalCollection($principalBackend),
	// /addressbook
	new \Sabre\CardDAV\AddressBookRoot($principalBackend, $carddavBackend),
	// /calendars
	new \Sabre\CalDAV\CalendarRoot($principalBackend, $caldavBackend)
);
// / Public docs
if(is_dir($cdav_data_root.'/cdav/public'))
	$nodes[] = new DAV\FS\Directory($cdav_data_root.'/cdav/public');
// admin can access all dolibarr documents of his entity
if($user->admin && is_dir($cdav_data_root))
	$nodes[] = new DAV\FS\Directory($cdav_data_root);

// The server object is responsible for making sense out of the WebDAV protocol
$server = new DAV\Server($nodes);

// If your server is not on your webroot, make sure the following line has the
// correct information
$server->setBaseUri(dol_buildpath('cdav/server.php', 1).($cdav_entity ? '/'.$cdav_entity : '').'/');


$server->addPlugin(new \Sabre\DAV\Auth\Plugin($authBackend));
$server->addPlugin(new \Sabre\DAV\Locks\Plugin($lockBackend));
$server->addPlugin(new \Sabre\DAV\Browser\Plugin());
$server->addPlugin(new \Sabre\CardDAV\Plugin());
$server->addPlugin(new \Sabre\CalDAV\Plugin());
$DAVACL_plugin = new \Sabre\DAVACL\Plugin();
$DAVACL_plugin->allowUnauthenticatedAccess = false;
$server->addPlugin($DAVACL_plugin);

debug_log("Ready : ".$user->login);

// All we need to do now, is to fire up the server
$server->exec();

if (is_object($db)) $db->close();
