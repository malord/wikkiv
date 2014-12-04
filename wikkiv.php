<?php
//
// wikkiv 1.0.1
// Copyright (c) 2006-2012 Mark H. P. Lord.
//
// This software is provided 'as-is', without any express or implied warranty.
// In no event will the author be held liable for any damages arising from the
// use of this software.
//
// Permission is granted to anyone to use this software for any purpose,
// including commercial applications, and to alter it and redistribute it
// freely, subject to the following restrictions:
//
// 1. The origin of this software must not be misrepresented; you must not
// claim that you wrote the original software. If you use this software in a
// product, an acknowledgement in the product documentation would be
// appreciated but is not required.
//
// 2. Altered source versions must be plainly marked as such, and must not be
// misrepresented as being the original software.
//
// 3. This notice may not be removed or altered from any source distribution.
//

require_once("markdown.php");
require_once("eyesonly.php");  // if possible, alter this so that the file isn't public

//
// Returns a newly constructed Database instance that can be used to access
// the wiki's data. This is intentionally the first piece of code in this
// file since it's where the database settings (which you'll need to adjust
// for the database you are using) are kept.
//

function &constructDatabase() {
	// These globals courtesy of eyesonly.php.
	global $DB_HOST, $DB_USER, $DB_PASS, $DB_NAME, $DB_PREFIX, $USERS_TABLE_NAME;
		
	return new Database($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME, $DB_PREFIX, $USERS_TABLE_NAME);
}

//
// Utility functions
//

function stringEndsWith($string, $with) {
	$withLen = strlen($with);
	$stringLen = strlen($string);
	
	if ($withLen > $stringLen) {
		return false;
	}
	
	return substr($string, $stringLen - $withLen, $withLen) == $with;
}

function gmt() {
	return gmstrftime("%Y-%m-%d %H:%M:%S");
}

function stripAutoSlashes($from) {
	if (get_magic_quotes_gpc()) {
		return stripslashes($from);
	}
	
	return $from;
}

function listArrayContents(&$array) {
	$c = "";
	$c .= "<div>";
	foreach ($array as $key => $value) {
		$c .= htmlspecialchars($key) . " =&gt; " . htmlspecialchars($value);
		$c .= "<br />";
	}
	$c .= "</div>";
	
	return $c;
}

function listSuperGlobals() {
	$c = "";

	$c .= listArrayContents($_SERVER);
	$c .= listArrayContents($_GET);
	$c .= listArrayContents($_POST);
	$c .= listArrayContents($_ENV);
	
	return $c;
}

function unhtmlspecialchars($string) {
	$trans_tbl = get_html_translation_table(HTML_ENTITIES);
	$trans_tbl = array_flip($trans_tbl);
	return strtr($string, $trans_tbl);
}

function splitSectionAndId($fullId, &$section, &$id) {
	$idx = strpos($fullId, "_");
	if ($idx === false) {
		$section = null;
		$id = $fullId;
	} else {
		$section = strtolower(substr($fullId, 0, $idx));
		$id = substr($fullId, $idx + 1);
	}
}

// Returns the URL of this script with the specified query string replacing
// the current query string. This should be used for dynamically generated
// links, and not for links stored within the pages.
function redirect($pageId, $queryString) {
	global $PAGE_URL_PREFIX;
	
	splitSectionAndId($pageId, $section, $id);
	
	if (! $id) {
		if (! $section) {
			$pageId = $PAGE_URL_PREFIX;
		} else {
			$pageId = $PAGE_URL_PREFIX . urlencode($section) . "/";
		}
	} else {
		if (! $section) {
			$pageId = $PAGE_URL_PREFIX . urlencode($id) . ".html";
		} else {
			$pageId = $PAGE_URL_PREFIX . urlencode($section) . "/" . urlencode($id) .".html";
		}
	}
	
	if ($queryString != "") {
		return $pageId . "?" . $queryString;
	} else {
		return $pageId;
	}
}

$scriptName = null;
function computeScriptName() {
	global $scriptName;
	
	if ($scriptName === null) {
		// TODO: work out and cache the script name! (Just assuming 
		// index.php for now!)
		$scriptName = "";
	}
	
	return $scriptName;
}

function pageLink($pageId) {
	// Merge the page ID in to the query string
	return redirect($pageId, "");
	//return computeScriptName() . "?p=" . urlencode($pageId);
}

function normaliseText($text) {
	$text = strtolower($text);
	$text = ereg_replace("[']", "", $text);
	$text = ereg_replace("[^a-z0-9\\-\\.]", "-", $text);
	$text = ereg_replace("-+", "-", $text);
	$text = trim($text, "-");
	return $text;
}

function truncateIfLonger($text, $maxLength) {
	if (strlen($text) > $maxLength) {
		return substr($text, 0, $maxLength);
	}
	
	return $text;
}

function newStyleId($section, $id) {
	if ($section) {
		$newId = normaliseText($section) . "_" . normaliseText($id);
	} else {
		$newId = normaliseText($id);
	}
	
	return truncateIfLonger($newId, 60);
}

function fixOldStyleId($oldId) {
	splitSectionAndId($oldId, $section, $id);
	return newStyleId($section, $id);
}

function joinSectionAndId($section, $innerId) {
	if (! $section) {
		return $innerId;
	} else {
		return $section . "_" . $innerId;
	}
}

function splitWikiLink($link, &$id, &$title, &$explicitTitle) {
	$idx = strpos($link, "}");
	if ($idx === false) {
		// Simple wiki entry
		$id = $link;
		$title = $link;
		
		$explicitTitle = false;
	} else {
		$idx2 = strpos($link, "{", $idx);
		if ($idx2 == false) {
			return "(malformed wiki link)";
		}
		
		$title = substr($link, 0, $idx);
		$id = substr($link, $idx2 + 1);
		
		$explicitTitle = true;
	}
}

function findWithinHtml($html, $before, $after) {
	$lhtml = strtolower($html);
	$idx = strpos($lhtml, $before);
	if ($idx === false) {
		return null;
	}
	
	$idx2 = strpos($lhtml, $after, $idx);
	if ($idx2 === false) {
		return null;
	}
	
	$beforelen = strlen($before);
	
	return unhtmlspecialchars(trim(strip_tags(substr($html, $idx + $beforelen, $idx2 - $idx - $beforelen))));
}

//
// Provides access to the results of an SQL query on a Database instance.
//

class QueryResult {
	function QueryResult($sql, &$conn) {
		if ($conn) {
			$this->_result = mysql_query($sql, $conn);
			
			if ($this->_result == false) {
				$this->_error = mysql_error($conn);
			} else {
				$this->_error = null;
			}
		} else {
			$this->_result = null;
			$this->_error = "Unable to connect to database.";
		}
		
		$this->_row = null;
	}
	
	function error() {
		return $this->_error;
	}
	
	function hasRow() {
		if (! $this->_row) {
			if (! $this->_error && $this->_result) {
				$this->_row = @mysql_fetch_array($this->_result, MYSQL_BOTH);
				if ($this->_row == false) {
					$this->_result = null;
				}
			}
		}
		
		return $this->_row ? true : false;
	}
	
	function nextRow() {
		$this->hasRow();
		$ret = $this->_row;

		$this->_row = null;
		$this->hasRow();
		
		return $ret;
	}
}

//
// Encapsulates a database's parameters and hides the details of connecting
// to a database and executing a query.
//
	
class Database {
	function Database($host, $username, $password, $dbName, $prefix, $usersTableName) {
		$this->_host = $host;
		$this->_username = $username;
		$this->_password = $password;
		$this->_dbName = $dbName;
		$this->_prefix = $prefix;
		$this->_conn = null;
		$this->_usersTableName = $usersTableName;
	}
	
	function prefix($value) {
		return $this->_prefix . $value;
	}
	
	function usersTableName() {
		return $this->_usersTableName;
	}
	
	function connection() {
		if ($this->_conn) {
			return $this->_conn;
		}
		
		$this->_conn = @mysql_pconnect($this->_host, $this->_username, $this->_password);
		if (! $this->_conn) {
			return null;
		}
		
		if (! @mysql_select_db($this->_dbName)) {
			$this->_conn = null;
			return null;
		}
		
		return $this->_conn;
	}
	
	function &query($sql) {
		return new QueryResult($sql, $this->connection());
	}
}

//
// Encapsulates all information on the Wiki, and provides commonly used
// functions that are specific to the Wiki's database.
//

class Wiki {
	function Wiki() {
		$this->_db =& constructDatabase();
	}
	
	function &db() {
		return $this->_db;
	}
	
	function pagesTableName() {
		return $this->_db->prefix("pages");
	}
	
	function &query($sql) {
		return $this->_db->query($sql);
	}
	
	function userIdSessionVarName() {
		return $this->_db->prefix("userId");
	}
	
	function userId() {
		$var = $this->userIdSessionVarName();

		if (isset($_SESSION[$var])) {
			return $_SESSION[$var];
		}

		return null;
	}
	
	function user() {
		if (! isset($this->_user)) {
			$this->_user =& new User($this, $this->userId());
		}
		
		return $this->_user;
	}
	
	function login($userId) {
		$var = $this->userIdSessionVarName();
		$_SESSION[$var] = $userId;
		return true;
	}
	
	function logout() {
		$_SESSION[$this->userIdSessionVarName()] = null;
	}
	
	function pathToFolder($name) {
		// This global courtesy of eyesonly.php.
		global $PATH_TO_FILES_ON_SERVER;
		
		if (stringEndsWith($PATH_TO_FILES_ON_SERVER, "/")) {
			return $PATH_TO_FILES_ON_SERVER . $name;
		} else {
			return $PATH_TO_FILES_ON_SERVER . "/" . $name;
		}
	}
	
	function urlToFolder($name) {
		// This global courtesy of eyesonly.php.
		global $PATH_TO_FILES_ON_WEB;

		if (stringEndsWith($PATH_TO_FILES_ON_WEB, "/")) {
			return $PATH_TO_FILES_ON_WEB . $name;
		} else {		
			return $PATH_TO_FILES_ON_WEB . "/" . $name;
		}
	}
	
	function setOutputProperties(&$outputManager, $page) {
		$user =& $this->user();
		
		if ($page) {
			$pageId = $page->id();
			$editLink = redirect($pageId, "edit");
			$filesLink = redirect($pageId, "files");
			$viewLink = redirect($pageId, "");
			$userAdminLink = redirect($pageId, "users");
		} 
		
		if ($user->isAdmin()) {
			$outputManager->setProperty("userAdmin", "<a href=\"$userAdminLink\">User&nbsp;admin</a>");
		} else {
			$outputManager->setProperty("userAdmin", "");
		}
		
		if ($page && $page->innerId()) {
			// i.e., this isn't the front page of a section
			$section = $page->section();
			$sectionIndex = joinSectionAndId($section, "");
			$frontPageLink = redirect($sectionIndex, "");
			$outputManager->setProperty("frontPage", "<a href=\"$frontPageLink\">Front&nbsp;page</a>");
		} else {
			$outputManager->setProperty("frontPage", "");
		}
		
		if ($page && $page->userCanEdit()) {
			$outputManager->setProperty("editThisPage", "<a href=\"$editLink\">Edit&nbsp;this&nbsp;page</a>");
			$outputManager->setProperty("fileManager", "<a href=\"$filesLink\">File&nbsp;manager</a>");
		} else {
			$outputManager->setProperty("editThisPage", "");
			$outputManager->setProperty("fileManager", "");
		}

		if ($user->isValid()) {
			$logoutLink = redirect($pageId, "signout");
			$outputManager->setProperty("signInOut", "<a href=\"$logoutLink\">Sign&nbsp;out</a>");
		} else {
			$loginLink = redirect($pageId, "signin");
			$outputManager->setProperty("signInOut", "<a href=\"$loginLink\">Sign&nbsp;in</a>");
		}
		
		if ($page && $page->userCanView()) {
			$outputManager->setProperty("viewThisPage", "<a href=\"$viewLink\">Back&nbsp;to&nbsp;page</a>");
		}
	}
}

//
// Represents a user. Has the ability to read/write a user's details from/to
// a database.
//

class User {
	function User(&$wiki, $id) {
		$this->_wiki =& $wiki;
		$this->_id = $id;
		
		if (! $id) {
			$this->_valid = false;
			$this->_id = null;
		} else {
			$this->tryLoad();
		}
	}
	
	function loginIs($isWhat) {
		if (strtolower($isWhat) == strtolower($this->_id)) {
			return true;
		}
		
		return false;
	}
	
	function tableName() {
		$db = $this->_wiki->db();
		return $db->usersTableName();
	}
	
	function tryLoad() {
		$tableName = $this->tableName();
		$escapedId = addslashes(strtolower($this->_id));
		
		$qr =& $this->_wiki->query("SELECT * FROM $tableName WHERE Login='$escapedId'");
		
		if ($qr->error() || ! $qr->hasRow()) {
			$this->_valid = false;
			return false;
		}
		
		$this->_fields = $qr->nextRow();
		$this->_password = $this->_fields["Password"];
		$this->_admin = !! $this->_fields["Admin"];
		$this->_valid = true;
		
		return true;
	}
	
	function setPassword($newPassword, $newAdmin) {
		$tableName = $this->tableName();
		$escapedId = addslashes(strtolower($this->_id));
		$escapedPassword = addslashes($newPassword);
		
		if ($newAdmin) {
			$escapedAdmin = '1';
		} else {
			$escapedAdmin = '0';
		}
		
		if (! $this->_valid) {
			$qr =& $this->_wiki->query("INSERT INTO $tableName(Login,Password,Admin) VALUES('$escapedId','$escapedPassword',$escapedAdmin)");
		} else {
			$qr =& $this->_wiki->query("UPDATE $tableName SET Password='$escapedPassword',Admin=$escapedAdmin WHERE Login='$escapedId'");
		}
		
		if ($qr->error()) {
			return false;
		}
		
		$this->_password = $newPassword;
		$this->_admin = $newAdmin;
		$this->_valid = true;
		
		return true;
	}
	
	function deleteUser() {
		$tableName = $this->tableName();
		$escapeId = addslashes(strtolower($this->_id));
		
		$qr =& $this->_wiki->query("DELETE FROM $tableName WHERE Login='$escapeId'");

		if ($qr->error()) {
			return false;
		}

		$this->_valid = false;
		return true;
	}
	
	function id() {
		return $this->_id;
	}
	
	function idForPermissionCheck() {
		if (! $this->_id) {
			return "world";
		} else {
			return $this->_id;
		}
	}
	
	function isValid() {
		return $this->_valid;
	}
	
	function passwordIs($test) {
		if (! $this->_valid) {
			return false;
		}
		
		return $this->_password == $test;
	}
	
	function isAdmin() {
		if (! $this->isValid()) {
			return false;
		}
		
		return $this->_admin;
	}
	
	function canCreateSections() {
		return $this->isAdmin();
	}
}

//
// Represents a folder within the files section of the web-site.
//

class Folder {
	function Folder(&$wiki, $id) {
		$this->_wiki =& $wiki;
		$this->_id = $id;
		$this->_urlId = rawurlencode(strtolower($id));
		$this->_path = $wiki->pathToFolder($this->_urlId);
		$this->_url = $wiki->urlToFolder(urlencode($this->_urlId));
	}
	
	function create() {
		$oldUmask = @umask(0);
		@mkdir($this->_path, 0777);
		@umask($oldUmask);
		
		return true;
	}
	
	function pathToFile($name) {
		if (stringEndsWith($this->_path, "/")) {
			return $this->_path . $name;
		} else {
			return $this->_path . "/" . $name;
		}
	}
	
	function urlToFile($name) {
		if (stringEndsWith($this->_url, "/")) {
			return $this->_url . $name;
		} else {
			return $this->_url . "/" . $name;
		}
	}
	
	function &listFiles() {
		$dir = @opendir($this->_path);
		if (! $dir) {
			return array();
		}
		
		$a = array();
		
		while ($file = @readdir($dir)) {
			if (substr($file, 0, 1) == ".") {
				continue;
			}
			
			$fullName = $this->pathToFile($file);
			
			if (! is_file($fullName)) {
				continue;
			}
			
			$a[] = $file;
		}
		
		@closedir($dir);
		return $a;
	}
	
	function exists($name) {
		$fullname = $this->pathToFile($name);
		
		return file_exists($fullname);
	}
	
	function delete($name) {
		return @unlink($this->pathToFile($name));
	}
}

//
// Represents an individual page in the wiki. Can load pages from the 
// database or write them to the database.
//

class Page {
	function Page(&$wiki, $id, $load = true) {
		$this->_loaded = false;
		$this->_wiki =& $wiki;
		$this->_id = $id;
		$this->_related = array();
		
		splitSectionAndId($id, $this->_section, $this->_innerId);
		
		if ($load) {
			if ($this->_innerId == "") {
				$this->_sectionPage = null;
			} else {
				$sectionPageId = joinSectionAndId($this->_section, "");
			
				$this->_sectionPage =& new Page($wiki, $sectionPageId);
			}
		
			$this->tryLoad();
		
			if (! $this->_loaded && $this->_innerId == "") {
				$this->_createSectionPage();
			
				$this->tryLoad();
			}
		}
	}
	
	function &relatedPage($relation) {
		$relation = strtolower($relation);
		if (isset($this->_related[$relation])) {
			return $this->_related[$relation];
		}

		$relAppend = "$relation";
		
		$p =& new Page($this->_wiki, $this->_id . $relAppend);
		if (! $p->_loaded) {
			$p =& new Page($this->_wiki, joinSectionAndId($this->_section, $relAppend));
				
			if (! $p->_loaded) {
				$p =& new Page($this->_wiki, $relAppend);
			}
		}
		
		if ($p->_loaded) {
			$this->_related[$relation] =& $p;
			return $p;
		}
		
		return null;
	}
	
	function &wiki() {
		return $this->_wiki;
	}
	
	function _createSectionPage() {
		$user =& $this->_wiki->user();
		
		if (! $user->canCreateSections()) {
			return null;
		}
		
		$newPage =& new Page($this->_wiki, joinSectionAndId($this->_section, ""), false);

		$newPage->setField("rawText", "");
		$newPage->setField("html", "");
		$newPage->setField("title", "");
		$newPage->setField("template", "");
		$newPage->setField("allowView", "");
		$newPage->setField("denyView", "");
		$newPage->setField("allowEdit", "");
		$newPage->setField("denyEdit", "");
		$newPage->setField("allowAdmin", $this->_wiki->userId());
		$newPage->setField("userId", $this->_wiki->userId());
		
		$newPage->insertRecord();
		
		return $newPage;
	}
	
	function loaded() {
		return $this->_loaded;
	}
	
	function id() {
		return $this->_id;
	}
	
	function fixId() {
		$this->_id = fixOldStyleId($this->_id);
	}
	
	function title() {
		if ($this->_loaded && isset($this->_fields["title"])) {
			$title = $this->_fields["title"];
			if ($title !== null && $title !== "") {
				return $title;
			}
		}
		
		splitSectionAndId($this->id(), $section, $innerId);
		if (! $innerId) {
			return $section;
		} 

		return $innerId;
	}
	
	function section() {
		return $this->_section;
	}
	
	function innerId() {
		return $this->_innerId;
	}
	
	function tryLoad() {
		$tableName = $this->_wiki->pagesTableName();
		$escapedId = addslashes(strtolower($this->_id));
		
		$qr =& $this->_wiki->query("SELECT * FROM $tableName WHERE id='$escapedId' ORDER BY modified DESC LIMIT 1");
		
		if ($qr->error() || ! $qr->hasRow()) {
			$this->_loaded = false;
			return false;
		}
		
		$this->_fields = $qr->nextRow();
		$this->_loaded = true;
		
		return true;
	}
	
	function field($name) {
		if (isset($this->_fields) && isset($this->_fields[$name])) {
			return $this->_fields[$name];
		} else {
			return "";
		}
	}
	
	function derivedField($name) {
		if (isset($this->_fields) && isset($this->_fields[$name])) {
			$value = $this->_fields[$name];
			if ($value !== "") {
				return $value;
			}
		}
		
		if ($this->_sectionPage !== null) {
			return $this->_sectionPage->field($name);
		} else {
			return "";
		}
	}
	
	function _userIdAllowedView($userId) {
		if (! $this->_loaded) {
			return true;
		}
		
		return strpos(" " . $this->field("allowView") . " ", " $userId ") 
			!== false;
	}
	
	function _userIdDeniedView($userId) {
		if (! $this->_loaded) {
			return false;
		}
		
		return strpos(" " . $this->field("denyView") . " ", " $userId ") 
			!== false;
	}
	
	function _userIdAllowedEdit($userId) {
		if (! $this->_loaded) {
			return false;
		}
		
		return strpos(" " . $this->field("allowEdit") . " ", " $userId ") 
			!== false;
	}

	function _userIdDeniedEdit($userId) {
		if (! $this->_loaded) {
			return false;
		}
		
		return strpos(" " . $this->field("denyEdit") . " ", " $userId ") 
			!== false;
	}

	function _userIdAllowedAdmin($userId) {
		if (! $this->_loaded) {
			return false;
		}
		
		return strpos(" " . $this->field("allowAdmin") . " ", " $userId ") 
			!== false;
	}

	function userCanAdmin() {
		$user =& $this->_wiki->user();
		$userId = $user->idForPermissionCheck();

		if ($user->isAdmin()) {
			return true;
		}
		
		if ($this->_userIdAllowedAdmin($userId)) {
			return true;
		}
		
		if ($this->_sectionPage !== null) {
			return $this->_sectionPage->userCanAdmin();
		}
		
		return false;
	}
	
	function userCanEdit() {
		$user =& $this->_wiki->user();
		if ($this->userCanAdmin()) {
			return true;
		}

		$userId = $user->idForPermissionCheck();
		
		if ($this->_userIdAllowedEdit($userId)
				|| $this->_userIdAllowedEdit("world")) {
			return true;
		}		
		
		if ($this->_userIdDeniedEdit($userId)
				|| $this->_userIdDeniedEdit("world")) {
			return false;
		}		
		
		if ($this->_sectionPage !== null) {
			return $this->_sectionPage->userCanEdit();
		}
		
		return false;
	}
	
	function userCanView() {
		$user =& $this->_wiki->user();
		if ($this->userCanAdmin()) {
			return true;
		}
		
		if ($this->userCanEdit()) {
			return true;
		}
		
		$userId = $user->idForPermissionCheck();
		
		if ($this->_userIdAllowedView($userId)
				|| $this->_userIdAllowedView("world")) {
			return true;
		}
		
		if ($this->_userIdDeniedView($userId)
				|| $this->_userIdDeniedView("world")) {
			return false;
		}
		
		if ($this->_sectionPage !== null) {
			return $this->_sectionPage->userCanView();
		}
		
		return true;
	}
	
	function setField($name, $value) {
		if (! isset($this->_fields)) {
			$this->_fields = array();
		}
		
		$this->_fields[$name] = $value;
	}
	
	function insertRecord() {
		$escapedPageId = addslashes($this->_id);
		$escapedRawText = addslashes($this->field("rawText"));
		$escapedHtml = addslashes($this->field("html"));
		$escapedUserId = addslashes($this->field("userId"));
		$escapedAllowView = addslashes($this->field("allowView"));
		$escapedDenyView = addslashes($this->field("denyView"));
		$escapedAllowEdit = addslashes($this->field("allowEdit"));
		$escapedDenyEdit = addslashes($this->field("denyEdit"));
		$escapedAllowAdmin = addslashes($this->field("allowAdmin"));
		$escapedTitle = addslashes($this->field("title"));
		$escapedTemplate = addslashes($this->field("template"));

		$modified = gmt();
		$pageTableName = $this->_wiki->pagesTableName();

		$sql = "INSERT INTO $pageTableName(id, rawText, html, modified, userId, allowView, denyView, allowEdit, denyEdit, allowAdmin, title, template) " .
		"VALUES ('$escapedPageId', '$escapedRawText', '$escapedHtml','$modified','$escapedUserId','$escapedAllowView','$escapedDenyView','$escapedAllowEdit','$escapedDenyEdit','$escapedAllowAdmin','$escapedTitle','$escapedTemplate')";
			
		$result =& $this->_wiki->query($sql);
				
		return $result->error();
	}
}

//
// Parses the input from the "edit page" form and converts it in to HTML.
//

class Parser {
	function Parser(&$page, $text) {
		$this->_page =& $page;
		$this->_wiki =& $page->wiki();
		$this->_html = null;
		$this->_title = null;
		$this->_parse($text);
	}

	function normalizeId($id, &$newId, &$innerId) {
		splitSectionAndId($id, $section, $innerId);
		
		if ($section === null) {
			$section = $this->_page->section();
		}
		
		$newId = newStyleId($section, $innerId);
	}
	
	function fileLink($link, $id, $title, $explicitTitle) {
		if (! preg_match("/^\\?[ ]*(.*)$/", $id, $matches)) {
			return "{{" . $link . "}}";
		}
		
		$fileName = $matches[1];
		
		$slashPos = strpos($fileName, "/");
		if ($slashPos !== false) {
			$filePage = substr($fileName, 0, $slashPos);
			$fileName = substr($fileName, $slashPos + 1);
			
			$this->normalizeId($filePage, $filePage, $filePageInnerId);
			
			$folder =& new Folder($this->_wiki, $filePage);
		} else {		
			$folder =& new Folder($this->_wiki, $this->_page->id());
		}
		
		if (! $explicitTitle) {
			$title = $fileName;
		}
		
		$url = $folder->urlToFile($fileName);
		$htmlTitle = htmlspecialchars($title);
		
		return "<a href=\"$url\">$htmlTitle</a>";
	}

	function pictureLink($link, $id, $title, $explicitTitle) {
		if (! preg_match("/^![ ]*(left|right)?[ ]*(.*)$/", $id, $matches)) {
			return "{{" . $link . "}}";
		}
		
		if ($matches[1] == "left") {
			$style = "style=\"float: left; padding-right: 6px;\" ";
		} else if ($matches[1] == "right") {
			$style = "style=\"float: right; padding-left: 6px;\" ";
		} else {
			$style = "";
		}
		
		$fileName = $matches[2];
		
		$slashPos = strpos($fileName, "/");
		if ($slashPos !== false) {
			$filePage = substr($fileName, 0, $slashPos);
			$fileName = substr($fileName, $slashPos + 1);
			
			$this->normalizeId($filePage, $filePage, $filePageInnerId);
			
			$folder =& new Folder($this->_wiki, $filePage);
		} else {		
			$folder =& new Folder($this->_wiki, $this->_page->id());
		}
		
		if (! $explicitTitle) {
			$title = $fileName;
		}
		
		$url = $folder->urlToFile($fileName);
		$escapedTitle = htmlspecialchars($title);
		
		return "<img $style src=\"$url\" alt=\"$escapedTitle\" title=\"$escapedTitle\" />";
	}
	
	function wikiLink($link) {
		splitWikiLink($link, $id, $title, $explicitTitle);
		
		if (strlen($id) >= 1 && substr($id, 0, 1) == "!") {
			return $this->pictureLink($link, $id, $title, $explicitTitle);
		}

		if (strlen($id) >= 1 && substr($id, 0, 1) == "?") {
			return $this->fileLink($link, $id, $title, $explicitTitle);
		}
		
		$this->normalizeId($id, $id, $innerId);

		if (! $explicitTitle) {
			// If an explicit title hasn't been given, make sure the section
			// name is removed from the title.
			$title = $innerId;
		}

		$urlId = urlencode($id);
		$link = pageLink($id);
		
		if ($title == "" || $title == "_") {
			// Might want to do this in more situations? Why doesn't this
			// work: {{}{_contact}} (i.e., an explicit, empty title).
			$page =& new Page($this->_page->wiki(), $id);
			if ($page->loaded()) {
				$htmlTitle = htmlspecialchars($page->title());
			} else {
				$htmlTitle = "Front page";
			}
		} else {
			$htmlTitle = htmlspecialchars($title);
		}
		
		return "<a href=\"$link\">$htmlTitle</a>";
	}

	function _computeTitleFromHtml($html) {
		$title = findWithinHtml($html, "<h1>", "</h1>");
		if ($title !== null) {
			return $title;
		}
		
		$title = findWithinHtml($html, "<!--title:", "-->");
		
		return $title;
	}
	
	function _parse($what) {
		$idx = 0;
		for (;;) {
			$idx = strpos($what, "{{", $idx);
			if ($idx === false) {
				break;
			}

			$endIdx = strpos($what, "}}", $idx);
			if ($endIdx === false) {
				break;
			}

			$before = substr($what, 0, $idx);
			$after = substr($what, $endIdx + 2);
			
			$chBefore = ($idx == 0) ? "" : substr($what, $idx - 1, 1);

			if ($chBefore == "\\") {
				// TODO: support \\{{blah}} (i.e., escape the escape)
				$what = substr($before, 0, strlen($before) - 1) . substr($what, $idx);
				$idx = $endIdx - 1;
				continue;
			}

			$replacement = $this->wikiLink(substr($what, $idx + 2, $endIdx - $idx - 2));
			$what = $before . $replacement . $after;
			$idx = $idx + strlen($replacement);
		}

		$what = Markdown($what);
		
		$this->_title = $this->_computeTitleFromHtml($what);
		
		// TODO: extract "sections"
		
		$this->_html = $what;

		return $what;
	}
	
	function html() {
		return $this->_html;
	}
	
	function title() {
		return $this->_title;
	}
}

//
// An Action instance is responsible for rendering the primary content of
// a page, as well as the page's title. The content and title are then
// included in the final HTML output, which is composed by an OutputTemplate.
//

class Action {
	function content(&$outputManager) {
		return "<p>No Action.</p>";
	}
	
	function title(&$outputManager) {
		return "No title";
	}
	
	// Callback mechanism for output templates.
	function request($name) {
		return null;
	}
	
	// Read a variable from the page.
	function pageVariable($name) {
		return null;
	}
}

//
// Extends Action specifically for actions that use a Page.
//

class PageAction extends Action {
	function PageAction(&$page) {
		$this->_page =& $page;
	}
	
	function request($name) {
		$rel = $this->_page->relatedPage($name);
		if ($rel === null)
		    return null;
		    
		if (! $rel->userCanView())
		    return null;
		    
		return $rel->field("html");
	}

	function pageVariable($name) {
		return findWithinHtml($this->_page->field("html"), "<!--$name:", "-->");
	}
	
	function title(&$outputManager) {
		return htmlspecialchars($this->_page->title());
	}
}

//
// An OutputTemplate is responsible for generating a full page of HTML (i.e.,
// including all HTML headers, copyright notices, sidebars and so forth) 
// given an array of properties, an array of flashes (messages to be emitted, 
// e.g., error messages) and the page content and title. 
//

class OutputTemplate {
	
	function OutputTemplate($filename = null) {
		$this->_template = $filename;
	}
	
	// This default implementation of compose yields totally non-styled
	// minimalistic pages.
	function emit($content, $title, &$flashes, &$props, &$callback) {
		$htmlFlashes = "";
		if ($flashes) {
			foreach ($flashes as $flash) {
				if (substr($flash, 0, 6) == "Error:") {
					$flashStyle = "errorFlash";
				} else {
					$flashStyle = "flash";
				}
				$htmlFlashes .= "<p class=\"$flashStyle\">" . $flash . "</p>\r\n";
			}
		}

		include($this->_template);

		return doTemplate($content, $title, $htmlFlashes, $props, $callback);
	}
}

//
// Manages rendering. Consists of an Action instance, any number of "flash"
// messages (which are just strings), and any number of additional properties 
// (which are strings keyed by strings). The output is compiled in to an HTML
// page by an OutputTemplate.
//

class OutputManager {
	function OutputManager() {
		$this->_action = null;
		$this->_flash = array();
		$this->_props = array();
		$this->_template = null;
	}
	
	function setAction(&$action) {
		$this->_action =& $action;
	}
	
	function addFlash($flash) {
		$this->_flash[] = $flash;
	}
	
	function setProperty($key, $value) {
		if ($value === "" || $value === null) {
			unset($this->_props[$key]);
		} else {
			$this->_props[$key] = $value;
		}
	}
	
	function emit(&$template) {
		$content = $this->_action->content($this);
		$title = $this->_action->title($this);
			
		$template->emit($content, $title, $this->_flash, $this->_props, $this->_action);
	}
}

//
// Action implementation for the editing page.
//

class EditPageAction extends PageAction {
	function EditPageAction(&$page) {
		$this->PageAction($page);
		$this->_wiki =& $page->wiki();
		$this->_isNew = false;
	}
	
	function setIsNew($value) {
		$this->_isNew = $value;
	}
	
	function postOrField($fieldName) {
		if (isset($_POST[$fieldName])) {
			return stripAutoSlashes($_POST[$fieldName]);
		}
		
		if ($this->_page->loaded()) {
			return $this->_page->field($fieldName);
		}
		
		return "";
	}
	
	function content(&$outputManager) {
		$c = "";

		$pageId = $this->_page->id();
		$urlPageId = urlencode($pageId);
		
		$posted = isset($_POST["posted"]);

		if (! $this->_page->loaded()) {
			$outputManager->addFlash("This is a new page.");
		}
		$done = isset($_POST["done"]);
		
		$text = $this->postOrField("rawText");
		$allowView = $this->postOrField("allowView");
		$denyView = $this->postOrField("denyView");
		$allowEdit = $this->postOrField("allowEdit");
		$denyEdit = $this->postOrField("denyEdit");
		$allowAdmin = $this->postOrField("allowAdmin");
		$template = $this->postOrField("template");
		
		if ($posted) {
			$parser =& new Parser($this->_page, $text);
			$html = $parser->html();
			$title = $parser->title();
			if ($title === null) {
				$title = $this->_page->title();
			}
			
			if ($html != $this->_page->field("html")
					|| $allowView != $this->_page->field("allowView")
					|| $denyView != $this->_page->field("denyView")
					|| $allowEdit != $this->_page->field("allowEdit")
					|| $denyEdit != $this->_page->field("denyEdit")
					|| $allowAdmin != $this->_page->field("allowAdmin")
					|| $template != $this->_page->field("template")
					|| $title != $this->_page->title()) {
				$changed = true;
			} else {
				$changed = false;
			}

			if ($changed) {
				$newPage =& new Page($this->_wiki, $pageId, false);
				$newPage->setField("rawText", $text);
				$newPage->setField("html", $html);
				$newPage->setField("title", $title);
				$newPage->setField("allowView", $allowView);
				$newPage->setField("denyView", $denyView);
				$newPage->setField("allowEdit", $allowEdit);
				$newPage->setField("denyEdit", $denyEdit);
				$newPage->setField("allowAdmin", $allowAdmin);
				$newPage->setField("template", $template);
				$newPage->setField("userId", $this->_wiki->userId());
				
				$error = $newPage->insertRecord();
			} else {
				$error = false;
			}
			
			if ($error) {
				$outputManager->addFlash($error);
			} else if ($done) {
				$redirect = redirect($pageId, "");
				header("Location: $redirect");
				exit;
			} else {
				if ($changed) {
					$outputManager->addFlash("Page successfully saved.");
				} else {
					$outputManager->addFlash("Page was not saved because it hasn't been modified.");
				}
			}
		}
		
		$editRedirectUrl = redirect($pageId, "edit");
		
		$htmlText = htmlspecialchars($text);
		$htmlAllowView = htmlspecialchars($allowView);
		$htmlDenyView = htmlspecialchars($denyView);
		$htmlAllowEdit = htmlspecialchars($allowEdit);
		$htmlDenyEdit = htmlspecialchars($denyEdit);
		$htmlAllowAdmin = htmlspecialchars($allowAdmin);
		$htmlTemplate = htmlspecialchars($template);

		$c .= "<form action=\"$editRedirectUrl\" method=\"post\">";
		$c .= "<textarea rows=\"40\" cols=\"80\" name=\"rawText\">";
		$c .= $htmlText;
		$c .= "</textarea>";
		$c .= "<br />";
		$c .= "<input type=\"submit\" value=\"Done\" name=\"done\" />";
		$c .= "<input type=\"submit\" value=\"Save &amp; Continue Editing\" name=\"save\" />";
		$c .= "<input type=\"hidden\" value=\"posted\" name=\"posted\" />";
		$c .= "<br />";
		
		if ($this->_page->userCanAdmin()) {
			$c .= "<h3>Permissions</h3>";
			$c .= "<p><em>Only attempt to edit these settings if you're sure you know what you're doing.</em> If you get it wrong, you could lock yourself out from your own pages!</p>";
			$c .= "Allow these users to view this page: ";
			$c .= "<input type=\"text\" name=\"allowView\" size=\"40\" maxchars=\"100\" value=\"$htmlAllowView\" /><br />";
			$c .= "Prevent these users from viewing this page: ";
			$c .= "<input type=\"text\" name=\"denyView\" size=\"40\" maxchars=\"100\" value=\"$htmlDenyView\" /><br />";
			$c .= "Allow these users to edit this page: ";
			$c .= "<input type=\"text\" name=\"allowEdit\" size=\"40\" maxchars=\"100\" value=\"$htmlAllowEdit\" /><br />";
			$c .= "Prevent these users from editing this page: ";
			$c .= "<input type=\"text\" name=\"denyEdit\" size=\"40\" maxchars=\"100\" value=\"$htmlDenyEdit\" /><br />";
			$c .= "Allow these users to edit this page's permissions: ";
			$c .= "<input type=\"text\" name=\"allowAdmin\" size=\"40\" maxchars=\"100\" value=\"$htmlAllowAdmin\" /><br />";
			$c .= "Template: ";
			$c .= "<input type=\"text\" name=\"template\" size=\"40\" maxchars=\"80\" value=\"$htmlTemplate\" /><br />";
		}
	
		$c .= "</form>";

		$this->_wiki->setOutputProperties($outputManager, $this->_page);
		$outputManager->setProperty("editThisPage", null);
		
		return $c;
	}
	
	function title(&$outputManager) {
		return "Editing: " . htmlspecialchars($this->_page->title());
	}
}

//
// Action implementation that logs the user out.
//

class LogoutAction extends PageAction {
	function LogoutAction(&$page) {
		$this->PageAction($page);
		$this->_wiki =& $page->wiki();
	}
	
	function content(&$outputManager) {
		$this->_wiki->logout();
		$outputManager->addFlash("You are now logged out.");
		$redirectTo = redirect($this->_page->id(), "");
		header("Location: $redirectTo");
		exit;
	}
	
	function title(&$outputManager) {
		return "Sign out";
	}
}

//
// Action implementation for logging in to the site.
//

class LoginAction extends PageAction {
	function LoginAction(&$page) {
		$this->PageAction($page);
		$this->_wiki =& $page->wiki();
	}
	
	function content(&$outputManager) {
		$c = "";
		
		$posted = isset($_POST["posted"]);
		$done = false;
		$error = false;
		
		if ($posted) {
			$username = stripAutoSlashes($_POST["username"]);
			$password = stripAutoSlashes($_POST["password"]);
			
			$user =& new User($this->_wiki, $username);
			if ($user->isValid() && $user->passwordIs($password)) {			
				$done = $this->_wiki->login($username);
			} else if ($user->isValid()) {
				$error = "Invalid password.";
			} else {
				$error = "Invalid user name.";
			}
		} else {
			$username = $password = "";
		}
			
		if ($error) {
			$outputManager->addFlash($error);
		} else if ($done) {
			$redirect = redirect($this->_page->id(), "");
			header("Location: $redirect");
			exit;
		}
		
		$formUrl = redirect($this->_page->id(), "signin");
		$htmlUserName = htmlspecialchars($username);
		$htmlPassword = htmlspecialchars($password);
		
		$c .= "<form action=\"$formUrl\" method=\"post\" name=\"f\">";
		$c .= "<table border=\"0\" cellspacing=\"0\" cellpadding=\"4\">";
		$c .= "<tr><td>User name:</td><td><input type=\"text\" name=\"username\" size=\"40\" value=\"$htmlUserName\" /></td>";
		$c .= "<tr><td>Password:</td><td><input type=\"password\" name=\"password\" size=\"40\" value=\"$htmlPassword\" /></td>";
		$c .= "<tr><td colspan=\"2\" align=\"right\"><input type=\"submit\" value=\"Sign in\" name=\"signin\" />";
		$c .= "<input type=\"hidden\" value=\"posted\" name=\"posted\" /></td></tr>";
		$c .= "</table>";
		$c .= "</form>";
		$c .= "<script language=\"javascript\">\r\n";
		$c .= "<!--\r\n";
		$c .= "document.f.username.focus();\r\n";
		$c .= "--></script>\r\n";

		$this->_wiki->setOutputProperties($outputManager, $this->_page);
		$outputManager->setProperty("editThisPage", null);
		$outputManager->setProperty("fileManager", null);
		
		return $c;
	}
	
	function title(&$outputManager) {
		return "Sign in";
	}
}

//
// Action implementation for viewing a page.
//

class ViewPageAction extends PageAction {
	function ViewPageAction(&$page) {
		$this->PageAction($page);
		$this->_wiki =& $page->wiki();
	}
	
	function content(&$outputManager) {
		$c = $this->_page->field("html");
		$this->_wiki->setOutputProperties($outputManager, $this->_page);
		$outputManager->setProperty("viewThisPage", null);

		return $c;
	}
}

//
// Action implementation used when a page that does not exist is requested.
// This is the base class for other actions that are invoked when a page
// cannot be displayed for some reason.
//

class NoPageAction extends PageAction {
	function NoPageAction(&$page) {
		$this->PageAction($page);
		$this->_wiki =& $page->wiki();
	}
	
	// Override this to change which page error information is pulled from.
	function errorPageToDisplay() {
		return "404";
	}
	
	// Override this to change the default message.
	function defaultMessage() {
		return "Page not found.";
	}
	
	function content(&$outputManager) {
		$c = "";
		$errorPageId = $this->errorPageToDisplay();

		$errorPage =& new Page($this->_wiki, joinSectionAndId($this->_page->section(), $errorPageId));
			
		if ($errorPage->loaded()) {
			$c .= $errorPage->field("html");
		} else {
			$errorPage =& new Page($this->_wiki, $errorPageId);
		
			if ($errorPage->loaded()) {
				$c .= $errorPage->field("html");
			}
		}
		
		if (! $errorPage->loaded()) {
			$c .= $this->defaultMessage();
		}

		$pageId = $this->_page->id();

		$this->_wiki->setOutputProperties($outputManager, $this->_page);
		$outputManager->setProperty("viewThisPage", null);
		$outputManager->setProperty("fileManager", null);
		$outputManager->setProperty("editThisPage", null);
		
		return $c;
	}
	
	function title(&$outputManager) {
		return $this->defaultMessage();
	}
}

//
// Action implementation used when a user does not have permission to view
// a page.
//

class PrivatePageAction extends NoPageAction {
	function PrivatePageAction(&$page) {
		$this->NoPageAction($page);
	}
	
	function errorPageToDisplay() {
		return "401";
	}

	function defaultMessage() {
		return "Sorry, you are not permitted to view this page.";
	}
}

//
// The init page is invoked when an "init" parameter is specified to the page.
// It initialises the databases, if they don't already exist.
//

class InitPageAction extends PageAction {
	function InitPageAction(&$page) {
		$this->PageAction($page);
		$this->_wiki =& $page->wiki();
	}
	
	function title(&$outputManager) {
		return "Initialisation";
	}
	
	function &createPagesTable() {
		$pagesTableName = $this->_wiki->pagesTableName();
		//$this->_wiki->query("DROP TABLE $pagesTableName");
		return $this->_wiki->query(
			"CREATE TABLE IF NOT EXIST $pagesTableName (" .
			"id varchar(80) not null" .
			",rawText text" .
			",html text" .
			",modified datetime not null" .
			",userId varchar(30)" .
			",allowView text" .
			",denyView text" .
			",allowEdit text" .
			",denyEdit text" .
			",allowAdmin text" .
			",title varchar(80)" .
			",template varchar(80)" .
			",primary key(id, modified)" . 
			")");
	}
	
	function &addAdminUser() {
		$db =& $this->_wiki->db();
		$usersTableName = $db->usersTableName();
		return $this->_wiki->query("INSERT INTO $usersTableName(Login,Password,Admin) VALUES('admin','admin',1)");
	}
	
	function &createUsersTable() {
		$db =& $this->_wiki->db();
		$usersTableName = $db->usersTableName();
		//$this->_wiki->query("DROP TABLE $usersTableName");
		$result =& $this->_wiki->query(
			"CREATE TABLE IF NOT EXIST $usersTableName (" .
			"UID int unsigned not null auto_increment primary key" .
			",Login varchar(30)" .
			",Name varchar(60)" .
			",Password varchar(30)" .
			",Admin tinyint unsigned" .
			",TempPassword tinyint unsigned" .
			",index(Login)" .
			")");
			
		if (! $result->error()) {
			return $this->addAdminUser();
		}
		
		return $result;
	}
	
	function reportSuccess(&$result, $doing) {
		$this->_content .= "<h3>" . htmlspecialchars($doing) . "</h3>\r\n<p>";

		$error = $result->error();
		if ($error) {
			$this->_content .= "Error: " . htmlspecialchars($error);
		} else {
			$this->_content .= "OK";
		}
		
		$this->_content .= "</p>\r\n";
	}
	
	function content(&$outputManager) {
		$this->_content = "";
		
		$this->reportSuccess($this->createPagesTable(), "Create Pages table");
		$this->reportSuccess($this->createUsersTable(), "Create users table");
		$this->_wiki->setOutputProperties($outputManager, $this->_page);
		$outputManager->setProperty("viewThisPage", null);
		$outputManager->setProperty("fileManager", null);
		$outputManager->setProperty("editThisPage", null);
		
		return $this->_content;
	}
}

//
// Rebuilds all of the pages in the wiki.
//

class RebuildPageAction extends PageAction {
	function RebuildPageAction(&$page) {
		$this->PageAction($page);
		$this->_wiki =& $page->wiki();
	}
	
	function title(&$outputManager) {
		return "Rebuild";
	}
	
	function content(&$outputManager) {
		$user = $this->_wiki->user();
		if (! $user->isAdmin()) {
			return "Access denied";
		}

		$c = "<h1>Rebuilding...</h1>";
		
		$tableName = $this->_wiki->pagesTableName();
		
		$qr =& $this->_wiki->query("SELECT DISTINCT id FROM $tableName");
		
		if ($qr->error() || ! $qr->hasRow()) {
			$c .= "<p class=\"error\">Unable to find any page IDs</p>";
		} else {
			$ids = array();
			while ($qr->hasRow()) {
				$row = $qr->nextRow();
				$id = $row["id"];
				$ids[] = $id;
			}
			
			foreach ($ids as $id) {
				$c .= "<p>" . htmlspecialchars($id) . "</p>";
				$p =& new Page($this->_wiki, $id);
				if (! $p->loaded()) {
					$c .= "<p class=\"error\">Error loading page</p>";
				}
				
				$p->fixId();				
				if ($p->id() != $id) {
					$p2 =& new Page($this->_wiki, $p->id());
					if ($p2->loaded()) {
						$c .= "<p class=\"error\">Old style ID \"" . htmlspecialchars($id) . "\" ignored (already replaced)</p>";
						continue;
					}
					
					$c .= "<p class=\"error\">" . htmlspecialchars($id) . " is now " . htmlspecialchars($p->id()) . "</p>";
				}
				
				if (! $oldStyle) {								
					$parser =& new Parser($p, $p->field("rawText"));
					$newHtml = $parser->html();
					if ($newHtml != $p->field("html")) {
						$p->setField("html", $newHtml);
						$error = $p->insertRecord();
						if ($error) {
							$c .= "<p class=\"error\">" . htmlspecialchars($error) . "</p>";
						}
					} else {
						$c .= "<p>Page not updated</p>";
					}
				}
			}
		}
		
		$this->_fields = $qr->nextRow();

		return $c;
	}
}

//
// Lists all of the pages in the wiki.
//

class ListPageAction extends PageAction {
	function ListPageAction(&$page) {
		$this->PageAction($page);
		$this->_wiki =& $page->wiki();
	}
	
	function title(&$outputManager) {
		return "List";
	}
	
	function content(&$outputManager) {
		$user = $this->_wiki->user();
		if (! $user->canCreateSections()) {
			return "Access denied";
		}

		$tableName = $this->_wiki->pagesTableName();
		
		$qr =& $this->_wiki->query("SELECT DISTINCT id FROM $tableName");
		
		if ($qr->error() || ! $qr->hasRow()) {
			$c .= "<p class=\"error\">Unable to find any page IDs</p>";
		} else {
			$ids = array();
			while ($qr->hasRow()) {
				$row = $qr->nextRow();
				$id = $row["id"];
				$ids[] = $id;
			}
			
			foreach ($ids as $id) {
				$url = redirect($id, null);
				$c .= "<p><a href=\"$url\">" . htmlspecialchars($id) . "</a></p>";
			}
		}
		
		$this->_fields = $qr->nextRow();

		return $c;
	}
}

//
// This is the file manager action.
//

class FileManagerAction extends PageAction {
	function FileManagerAction(&$page) {
		$this->PageAction($page);
		$this->_wiki =& $page->wiki();
		$this->_folder =& new Folder($page->wiki(), $page->id());
	}
	
	function doYesDelete(&$outputManager, $c) {
		$delete = stripAutoSlashes($_GET["yesdelete"]);
		
		$redirect = redirect($this->_page->id(), "files");

		if (! $this->_folder->delete($delete)) {
			$c .= "<p>Unable to delete: ";
			$c .= htmlspecialchars($delete);
			$c .= "</p>";
			$c .= "<p><a href=\"$redirect\">Back to File Manager</a></p>";
			return $c;
		}
		
		header("Location: $redirect");
		exit;
	}
	
	function doDelete(&$outputManager, $c) {
		$delete = stripAutoSlashes($_GET["delete"]);
		
	    // TODO: make this a POST, not a GET!
		$yesLink = redirect($this->_page->id(), "files&yesdelete=" . urlencode($delete));

		$noLink = redirect($this->_page->id(), "files");
		
		if (! $this->_folder->exists($delete)) {
			$fullname = $this->_folder->pathToFile($delete);
			$c .= "<p>No such file: $fullname</p>";
			$c .= "<p><a href=\"$noLink\">Back to File Manager</a></p>";
			return $c;
		}
		
		$c .= "<p>Delete: " . htmlspecialchars($delete) . "</p>";
		$c .= "<p>Are you sure?</p>";
		
		$c .= "<p><a href=\"$yesLink\" rel=\"nofollow\">Yes, delete it</a></p>";
		$c .= "<p><a href=\"$noLink\">No, don't delete it</a></p>";
		
		return $c;
	}
	
	function doUpload(&$outputManager, $c) {
		$this->_folder->create();
	
		$file = $_FILES["userfile"]["tmp_name"];
		$fileName = $_FILES["userfile"]["name"];
		$fileSize = $_FILES["userfile"]["size"];
		
		$redirect = redirect($this->_page->id(), "files");
		
		if (strstr($fileName, "/") !== false || strstr($fileName, "\\") !== false || substr($fileName, 0, 1) == ".") {
			$c .= "<p>Invalid file name.</p>";
			$c .= "<p><a href=\"$redirect\">Go back</a></p>";
			return $c;
		}

		if (! $file || $file == "none" || $fileSize == 0) {
			$c .= "<p>No file was uploaded.</p>";
			$c .= "<p><a href=\"$redirect\">Go back</a></p>";
			return $c;
		}
		
		if (! is_uploaded_file($file)) {
			$c .= "<p>File upload was denied.</p>";
			$c .= "<p><a href=\"$redirect\">Go back</a></p>";
			return $c;
		}
		
		$fullName = $this->_folder->pathToFile($fileName);
		
		if (! move_uploaded_file($file, $fullName)) {
			$c .= "<p>Unable to move the file to the destination.</p>";
			$c .= "<p><a href=\"$redirect\">Go back</a></p>";
			return $c;
		}
		
		$oldUmask = @umask(0);
		@chmod($fullName, 0777);
		@umask($oldUmask);

		header("Location: $redirect");
		exit;
	}
	
	function content(&$outputManager) {
		if (! $this->_page->userCanEdit()) {
			$c = "<p>You are not permitted to edit this page.</p>";
			$redirect = redirect($this->_page->id(), "");
			$c = "<p><a href=\"$redirect\">Go back</a></p>";
			header("Location: $redirect");
			exit;
		}
		
		$pageUrl = redirect($this->_page->id(), "");
		$htmlTitle = htmlspecialchars($this->_page->title());
		
		$c = "<h1>File Manager</h1>";
		
		$c .= "<p>Page: <a href=\"$pageUrl\">$htmlTitle</a></p>";
		
		if (isset($_GET["delete"])) {
			return $this->doDelete($outputManager, $c);
		}

		if (isset($_GET["yesdelete"])) {
			return $this->doYesDelete($outputManager, $c);
		}
		
		if (isset($_POST["doupload"])) {
			return $this->doUpload($outputManager, $c);
		}
		
		if (isset($_GET["paths"])) {
			$c .= "<p>";
			$c .= htmlspecialchars($this->_folder->pathToFile(""));
			$c .= "</p>";

			$c .= "<p>";
			$c .= htmlspecialchars($this->_folder->urlToFile(""));
			$c .= "</p>";
		}
		
		$list = $this->_folder->listFiles();
		
		if (count($list)) {		
			$c .= "<table border=\"0\" cellspacing=\"4\" cellpadding=\"0\">";
			foreach ($list as $file) {
				$htmlFiles = htmlspecialchars($file);
				$deleteLink = redirect($this->_page->id(), "files&delete=" . urlencode($file));
				$viewLink = $this->_folder->urlToFile($file);
				
				$c .= "<tr>";
				$c .= "<td>$htmlFiles</td>";
				$c .= "<td></td>";
				$c .= "<td><a href=\"$viewLink\">View</a></td>";
				$c .= "<td></td>";
				$c .= "<td><a href=\"$deleteLink\">Delete</a></td>";
				$c .= "</tr>";
			}
			$c .= "</table>";
		}
		
		$c .= "<h2>Upload file</h2>";
		$uploadUrl = redirect($this->_page->id(), "files");
		
		$c .= "<form enctype=\"multipart/form-data\" action=\"$uploadUrl\" method=\"post\">";
		$c .= "<input type=\"hidden\" name=\"MAX_FILE_SIZE\" value=\"20000000\" />";
		$c .= "<input name=\"doupload\" type=\"hidden\" value=\"yes\" />";
		$c .= "<input name=\"userfile\" type=\"file\" size=\"40\" />";
		$c .= "<p></p>";
		$c .= "<input type=\"submit\" value=\"Upload\" />";
		$c .= "</form>";
		
		$this->_wiki->setOutputProperties($outputManager, $this->_page);
		$outputManager->setProperty("fileManager", null);

		return $c;
	}
	
	function title(&$outputManager) {
		return "File Manager: " . $this->_page->id();
	}
}

//
// Base class for the user management actions.
//

class UserManagementAction extends PageAction {
	function UserManagementAction($page) {
		$this->PageAction($page);
		$this->_wiki =& $page->wiki();
	}

	function loadUserNames() {
		$db =& $this->_wiki->db();
		$tableName = $db->usersTableName();

		$qr =& $this->_wiki->query("SELECT Login FROM $tableName");
		
		if ($qr->error() || ! $qr->hasRow()) {
			$this->_logins = false;
			return false;
		} else {
			$logins = array();
			while ($qr->hasRow()) {
				$row = $qr->nextRow();
				$login = $row["Login"];
				$logins[] = $login;
			}
			
			$this->_logins = $logins;
			return true;
		}
	}
	
}

//
// Allows an admin to add a user.
//

class AddUserAction extends UserManagementAction {
	function AddUserAction(&$page) {
		$this->UserManagementAction($page);
		$this->_wiki =& $page->wiki();
	}
	
	function content(&$outputManager) {
		$user =& $this->_wiki->user();
		if (! $user->isAdmin()) {
			return "Access denied.";
		}
		
		$c = "";
		
		$posted = isset($_POST["posted"]);
		$done = false;
		$error = false;
		
		if ($posted) {
			$username = stripAutoSlashes($_POST["username"]);
			$password = stripAutoSlashes($_POST["password"]);
			$password2 = stripAutoSlashes($_POST["password2"]);
			
			if (isset($_POST["isAdmin"]) && $_POST["isAdmin"]) {
				$isAdmin = 1;
			} else {
				$isAdmin = 0;
			}
			
			$user =& new User($this->_wiki, $username);
			if ($user->isValid()) {
				$error = "User already exists.";
			} else if ($password != $password2){
				$error = "Passwords do not match.";
			} else if (! strlen($username) || ! strlen($password)) {
				$error = "You must specify a user name and password.";
			} else {
				if (! $user->setPassword($password, $isAdmin)) {
					$error = "Unable to set password.";
				} else {
					$done = true;
				}
			}
		} else {
			$username = $password = $password2 = "";
			$isAdmin = 0;
		}
			
		if ($error) {
			$outputManager->addFlash($error);
		} else if ($done) {
			$redirect = redirect($this->_page->id(), "users");
			header("Location: $redirect");
			exit;
		}
		
		$formUrl = redirect($this->_page->id(), "add_user");
		$htmlUserName = htmlspecialchars($username);
		$htmlPassword = htmlspecialchars($password);
		$htmlPassword2 = htmlspecialchars($password2);
		$isAdminChecked = $isAdmin ? "checked=\"checked\"" : "";
		
		$c .= "<h2>Add User</h2>";
		
		$c .= "<form action=\"$formUrl\" method=\"post\" name=\"f\">";
		$c .= "<table border=\"0\" cellspacing=\"0\" cellpadding=\"4\">";
		$c .= "<tr><td>User name:</td><td><input type=\"text\" name=\"username\" size=\"40\" value=\"$htmlUserName\" /></td></tr>";
		$c .= "<tr><td>Password:</td><td><input type=\"password\" name=\"password\" size=\"40\" value=\"$htmlPassword\" /></td></tr>";
		$c .= "<tr><td>Confirm:</td><td><input type=\"password\" name=\"password2\" size=\"40\" value=\"$htmlPassword2\" /></td></tr>";
		$c .= "<tr><td></td><td><input type=\"checkbox\" name=\"isAdmin\" value=\"1\" $isAdminChecked/> Administrator</td></tr>";
		$c .= "<tr><td colspan=\"2\" align=\"right\"><input type=\"submit\" value=\"Add user\" name=\"signin\" />";
		$c .= "<input type=\"hidden\" value=\"add_user\" name=\"posted\" /></td></tr>";
		$c .= "</table>";
		$c .= "</form>";
		$c .= "<script language=\"javascript\">\r\n";
		$c .= "<!--\r\n";
		$c .= "document.f.username.focus();\r\n";
		$c .= "--></script>\r\n";

		$this->_wiki->setOutputProperties($outputManager, $this->_page);
		$outputManager->setProperty("editThisPage", null);
		$outputManager->setProperty("fileManager", null);
		
		return $c;
	}
	
	function title(&$outputManager) {
		return "Add User";
	}
}

//
// Allows an admin to reset a user's password.
//

class ResetPasswordAction extends UserManagementAction {
	function ResetPasswordAction(&$page) {
		$this->UserManagementAction($page);
		$this->_wiki =& $page->wiki();
	}
	
	function content(&$outputManager) {
		$user =& $this->_wiki->user();
		if (! $user->isAdmin()) {
			return "Access denied.";
		}
		
		$userId = $user->id();
		
		$c = "";
		
		$posted = isset($_POST["posted"]);
		$done = false;
		$error = false;
		
		if ($posted) {
			$username = stripAutoSlashes($_POST["username"]);
			$password = stripAutoSlashes($_POST["password"]);
			$password2 = stripAutoSlashes($_POST["password2"]);

			if (isset($_POST["isAdmin"]) && $_POST["isAdmin"]) {
				$isAdmin = 1;
			} else {
				$isAdmin = 0;
			}
			
			$user =& new User($this->_wiki, $username);
			if (! $user->isValid()) {
				$error = "Unknown user.";
			} else if ($password != $password2){
				$error = "Passwords do not match.";
			} else if (! strlen($username) || ! strlen($password)) {
				$error = "Password cannot be blank.";
			} else if ($user->loginIs($userId) && ! $isAdmin) {
				$error = "You cannot remove your own administrator privileges.";
			} else {
				if (! $user->setPassword($password, $isAdmin)) {
					$error = "Unable to set new password.";
				} else {
					$done = true;
				}
			}
		} else {
			if (! isset($_GET["reset_pwd"])) {
				return "Error.";
			}
			
			$username = stripAutoSlashes($_GET["reset_pwd"]);
			
			$password = $password2 = "";

			$user =& new User($this->_wiki, $username);
			$isAdmin = $user->isAdmin();
		}
			
		if ($error) {
			$outputManager->addFlash($error);
		} else if ($done) {
			$redirect = redirect($this->_page->id(), "users");
			header("Location: $redirect");
			exit;
		}
		
		$formUrl = redirect($this->_page->id(), "reset_pwd");
		$htmlUserName = htmlspecialchars($username);
		$htmlPassword = htmlspecialchars($password);
		$htmlPassword2 = htmlspecialchars($password2);
		$isAdminChecked = $isAdmin ? "checked=\"checked\"" : "";
		
		$c .= "<h2>Reset Password For \"$htmlUserName\"</h2>";
		
		$c .= "<form action=\"$formUrl\" method=\"post\" name=\"f\">";
		$c .= "<input type=\"hidden\" name=\"username\" size=\"40\" value=\"$htmlUserName\" />";
		$c .= "<table border=\"0\" cellspacing=\"0\" cellpadding=\"4\">";
		$c .= "<tr><td>Password:</td><td><input type=\"password\" name=\"password\" size=\"40\" value=\"$htmlPassword\" /></td>";
		$c .= "<tr><td>Confirm:</td><td><input type=\"password\" name=\"password2\" size=\"40\" value=\"$htmlPassword2\" /></td>";
		$c .= "<tr><td></td><td><input type=\"checkbox\" name=\"isAdmin\" value=\"1\" $isAdminChecked/> Administrator</td></tr>";
		$c .= "<tr><td colspan=\"2\" align=\"right\"><input type=\"submit\" value=\"Reset password\" name=\"signin\" />";
		$c .= "<input type=\"hidden\" value=\"add_user\" name=\"posted\" /></td></tr>";
		$c .= "</table>";
		$c .= "</form>";
		$c .= "<script language=\"javascript\">\r\n";
		$c .= "<!--\r\n";
		$c .= "document.f.password.focus();\r\n";
		$c .= "--></script>\r\n";

		$this->_wiki->setOutputProperties($outputManager, $this->_page);
		$outputManager->setProperty("editThisPage", null);
		$outputManager->setProperty("fileManager", null);
		
		return $c;
	}
	
	function title(&$outputManager) {
		return "Reset Password";
	}
}

//
// Allows an admin to delete a user.
//

class DeleteUserAction extends UserManagementAction {
	function DeleteUserAction(&$page) {
		$this->UserManagementAction($page);
		$this->_wiki =& $page->wiki();
	}
	
	function content(&$outputManager) {
		$user =& $this->_wiki->user();
		if (! $user->isAdmin()) {
			return "Access denied.";
		}
		
		$userId = $user->id();
		
		$c = "";
		
		$posted = isset($_POST["posted"]);
		$done = false;
		$error = false;
		$hideForm = false;
		
		if ($posted) {
			$username = stripAutoSlashes($_POST["username"]);
			
			if (isset($_POST["confirm"])) {
				$user =& new User($this->_wiki, $username);
				if (! $user->isValid()) {
					$error = "Unknown user.";
				} else if ($user->loginIs($userId)) {
					$error = "You can't delete yourself!";
				} else {
					if ($user->deleteUser()) {
						$done = true;
					} else {
						$error = "Unable to delete user.";
					}
				}
			} else {
				$done = true;
			}
		} else {
			if (! isset($_GET["del_user"])) {
				return "Error.";
			}
			
			$username = stripAutoSlashes($_GET["del_user"]);
			
			if (strtolower($username) == strtolower($userId)) {
				$error = "You can't delete yourself!";
				$hideForm = true;
			}
		}
			
		if ($error) {
			$outputManager->addFlash($error);
		} else if ($done) {
			$redirect = redirect($this->_page->id(), "users");
			header("Location: $redirect");
			exit;
		}
		
		if (! $hideForm) {		
			$formUrl = redirect($this->_page->id(), "del_user");
			$htmlUserName = htmlspecialchars($username);
		
			$c .= "<h2>Delete User \"$htmlUserName\"?</h2>";
		
			$c .= "<form action=\"$formUrl\" method=\"post\" name=\"f\">";
			$c .= "<input type=\"hidden\" name=\"username\" size=\"40\" value=\"$htmlUserName\" />";
			$c .= "<table border=\"0\" cellspacing=\"0\" cellpadding=\"4\">";
			$c .= "<tr><td><input type=\"submit\" value=\"Yes\" name=\"confirm\" /></td><td><input type=\"submit\" value=\"No\" name=\"cancel\" /></td></tr>";
			$c .= "<input type=\"hidden\" value=\"del_user\" name=\"posted\" /></td></tr>";
			$c .= "</table>";
			$c .= "</form>";
		}

		$this->_wiki->setOutputProperties($outputManager, $this->_page);
		$outputManager->setProperty("editThisPage", null);
		$outputManager->setProperty("fileManager", null);
		
		return $c;
	}
	
	function title(&$outputManager) {
		return "Delete User";
	}
}

//
// User management page. Lists users.
//

class UsersAction extends UserManagementAction {
	function UsersAction(&$page) {
		$this->UserManagementAction($page);
		$this->_wiki =& $page->wiki();
	}
	
	function userListHtml() {
		$c = "";
		
		$c .= "<table border=\"0\" cellspacing=\"0\" cellpadding=\"3\">";
		foreach ($this->_logins as $login) {
			$c .= "<tr>";
			$htmlLogin = htmlspecialchars($login);
			$urlLogin = urlencode($login);
			$c .= "<td>$htmlLogin</td>";
			$resetPasswordUrl = redirect($this->_page->id(), "reset_pwd=$urlLogin");
			$c .= "<td>&nbsp;</td>";
			$c .= "<td><a href=\"$resetPasswordUrl\">Reset Password</a></td>";
			$deleteUrl = redirect($this->_page->id(), "del_user=$urlLogin");
			$c .= "<td>&nbsp;</td>";
			$c .= "<td><a href=\"$deleteUrl\">Delete</a></td>";
			$c .= "</tr>";
		}
		$c .= "</table>";
		
		return $c;
	}
	
	function content(&$outputManager) {
		$user =& $this->_wiki->user();
		if (! $user->isAdmin()) {
			return "Access denied.";
		}
		
		if (! $this->loadUserNames()) {
			return "Error loading user name list.";
		}
		
		$c = "";
		
		$c .= "<h2>User List</h2>";
		
		$addUserLink = redirect($this->_page->id(), "add_user");
		
		$c .= "<p><a href=\"$addUserLink\">Add New User</a></p>";
		
		$c .= $this->userListHtml();
		
		$this->_wiki->setOutputProperties($outputManager, $this->_page);
		$outputManager->setProperty("editThisPage", null);
		$outputManager->setProperty("fileManager", null);
		
		return $c;
	}
	
	function title(&$outputManager) {
		return "User List";
	}
}

//
// "main"
//

session_start();

if (isset($_GET["p"])) {
	// Old style ID. Redirect to the new URL.
	$qs = "";
	foreach ($_GET as $key => $value) {
		if ($key == "p") {
			continue;
		}

		if ($qs !== "") {
			$qs .= "&";
		}
		
		if ($value) {		
			$qs .= urlencode($key) . "=" . urlencode($value);
		} else {
			$qs .= urlencode($key);
		}
	}
	$qs = substr($qs, 0, strlen($qs) - 1);
	$redirect = redirect(fixOldStyleId(stripAutoSlashes($_GET["p"])), $qs);
	header("Location: $redirect");
	exit;
} else if (isset($_GET["rewrite"])) {
	$requestedPage = stripAutoSlashes($_GET["rewrite"]);
} else {
	$requestedPage = "";
}

if ($requestedPage == "_") {
	// TODO: write a proper way to normalise the requested page.
	$requestedPage = "";
}
$editRequested = isset($_GET["edit"]);
$initRequested = $INIT_ENABLED && isset($_GET["init"]);
$phpInfoRequested = $INIT_ENABLED && isset($_GET["phpinfo"]);
$loginRequested = isset($_GET["signin"]);
$logoutRequested = isset($_GET["signout"]);
$overrideRequested = isset($_GET["over"]);
$fileManagerRequested = isset($_GET["files"]);
$rebuildRequested = isset($_GET["rebuild"]);
$listRequested = isset($_GET["list"]);
$usersRequested = isset($_GET["users"]);
$addUserRequested = isset($_GET["add_user"]);
$resetPasswordRequested = isset($_GET["reset_pwd"]);
$deleteUserRequested = isset($_GET["del_user"]);

$wiki =& new Wiki();
$page = null;

$outputManager =& new OutputManager();

if ($overrideRequested) {
	$user = $wiki->user();
	$user->override();
}

if ($phpInfoRequested) {
	phpinfo();
	exit;
} else if ($initRequested) {
	$page =& new Page($wiki, "");
	$action =& new InitPageAction($page);
} else if ($rebuildRequested) {
	$page =& new Page($wiki, "");
	$action =& new RebuildPageAction($page);
} else if ($listRequested) {
	$page =& new Page($wiki, "");
	$action =& new ListPageAction($page);
} else if ($usersRequested) {
	$page =& new Page($wiki, "");
	$action =& new UsersAction($page);
} else if ($addUserRequested) {
	$page =& new Page($wiki, "");
	$action =& new AddUserAction($page);
} else if ($resetPasswordRequested) {
	$page =& new Page($wiki, "");
	$action =& new ResetPasswordAction($page);
} else if ($deleteUserRequested) {
	$page =& new Page($wiki, "");
	$action =& new DeleteUserAction($page);
} else {
	$page =& new Page($wiki, $requestedPage);
	$noPage = ! $page->loaded();

	if ($loginRequested) {
		$action =& new LoginAction($page);
	} else if ($logoutRequested) {
		$action =& new LogoutAction($page);
	} else if ($fileManagerRequested) {
		$action =& new FileManagerAction($page);
	} else if ($noPage || $editRequested) {
		// If there's no existing page, or if editing was requested, show the
		// editing form (if the user has editing permissions).
		if ($page->userCanEdit()) {
			$action =& new EditPageAction($page);
			$action->setIsNew(! $page->loaded());
		} else {
			// The user does not have editing permissions. If they manually
			// requested editing, emit a flash so they're aware. Otherwise, we
			// have a non existant page and should use a NoPageAction instance.
			if ($editRequested) {
				$outputManager->addFlash("You are not permited to edit this page.");
			} else {
				$action =& new NoPageAction($page);
			}
		}
	} 
}

if (isset($_GET["perms"])) {
	$user = $wiki->user();
	$outputManager->addFlash("User ID: " . $user->idForPermissionCheck() . "<br />");
	$outputManager->addFlash("User can view: " . $page->userCanView() . "<br />");
	$outputManager->addFlash("User can edit: " . $page->userCanEdit() . "<br />");
	$outputManager->addFlash("User can admin: " . $page->userCanAdmin() . "<br />");
}

if (! isset($action)) {
	$user = $wiki->user();
	if ($page->userCanView()) {
		$action =& new ViewPageAction($page);
	} else if (! $user->isValid()) {
		$outputManager->addFlash("You must sign in to view this page.");
		$action =& new LoginAction($page);
	} else {
		$action =& new PrivatePageAction($page);
	}
}

$outputManager->setAction($action);

if ($page !== null) {
	$templateName = $page->derivedField("template");
} else {
	$templateName = null;
}

if (! $templateName) {
	$templateName = "template.php";
}

$template =& new OutputTemplate($wiki->pathToFolder($templateName));
$outputManager->emit($template);

?>
