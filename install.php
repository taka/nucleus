<?php
/*
 * Nucleus: PHP/MySQL Weblog CMS (http://nucleuscms.org/)
 * Copyright (C) 2002-2007 The Nucleus Group
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2
 * of the License, or (at your option) any later version.
 * (see nucleus/documentation/index.html#license for more info)
 * This script will install the Nucleus tables in your SQL-database, and initialize the data in
 * those tables.
 *
 * Below is a friendly way of letting users on non-php systems know that Nucleus won't run there.
 * ?><div style="font-size: xx-large;">If you see this text in your browser when you open <i>install.php</i>, your web server is not able to run PHP-scripts, and therefor Nucleus will not be able to run there. </div><div style="display: none"><?php
 */

/**
 * @license http://nucleuscms.org/license.txt GNU General Public License
 * @copyright Copyright (C) 2002-2007 The Nucleus Group
 * @version $Id: install.php 1184 2007-07-15 22:43:46Z kaigreve $
 */

/*
	This part of the install.php code allows for customization of the install process.
	When distributing plugins or skins together with a Nucleus installation, the
	configuration below will instruct to install them

	-- Start Of Configurable Part --
*/

// array with names of plugins to install. Plugin files must be present in the nucleus/plugin/
// directory.
//
// example:
//     array('NP_TrackBack', 'NP_MemberGoodies')
$aConfPlugsToInstall = array('NP_SkinFiles');


// array with skins to install. skins must be present under the skins/ directory with
// a subdirectory having the same name that contains a skinbackup.xml file
//
// example:
//     array('base','rsd')
$aConfSkinsToImport = array('default');

/*
	-- End Of Configurable Part --
*/

// don't give warnings for uninitialized vars
error_reporting(E_ERROR | E_WARNING | E_PARSE);

// make sure there's no unnecessary escaping:
set_magic_quotes_runtime(0);

// if there are some plugins or skins to import, do not include vars
// in globalfunctions.php again... so set a flag
if ((count($aConfPlugsToInstall) > 0) || (count($aConfSkinsToImport) > 0) ) {
	global $CONF;
	$CONF['installscript'] = 1;
}

if (phpversion() >= '4.1.0') {
	include_once('nucleus/libs/vars4.1.0.php');
} else {
	include_once('nucleus/libs/vars4.0.6.php');
}

include_once('nucleus/libs/mysql.php');

// check if mysql support is installed
	if (!function_exists('mysql_query') ) {
		_doError('Your PHP version does not have support for MySQL :(');
	}

	if (postVar('action') == 'go') {
		doInstall();
	} else {
		showInstallForm();
	}

	exit;

function showInstallForm() {
	// 0. pre check if all necessary files exist
	doCheckFiles();

	?>
	<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
	<html xmlns="http://www.w3.org/1999/xhtml">
	<head>
		<title>Nucleus Install</title>
		<style type="text/css"><!--
			@import url('nucleus/documentation/styles/manual.css');
		--></style>
		<script type="text/javascript"><!--
			var submitcount = 0;

			// function to make sure the submit button only gets pressed once
			function checkSubmit() {
				if (submitcount == 0) {
					submitcount++;
					return true;
				} else {
					return false;
				}
			}
		--></script>
	</head>
	<body>
		<div style="text-align:center"><img src="./nucleus/styles/logo.gif" /></div> <!-- Nucleus logo -->
		<form method="post" action="install.php">

		<h1>Install Nucleus</h1>

		<p>This script will help you to install Nucleus. It will set up your MySQL database tables and provide you with the information you need to enter in <i>config.php</i>. In order to do all this, you need to enter some information.</p>

		<p>All fields are mandatory. Optional information can be set from the Nucleus admin-area when installation is completed.</p>

		<h1>PHP &amp; MySQL Versions</h1>

		<p>Below are the version numbers of the PHP interpreter and the MySQL server on your webhost. When reporting problems on the Nucleus Support Forum, please include this information.</p>

		<ul>
			<li>PHP:

<?php
	echo phpversion();
	$minVersion = '4.0.6';

	if (phpversion() < $minVersion) {
		echo ' <span class="warning">WARNING: Nucleus requires at least version ', $minVersion, '</span>';
	}
?>

			</li>
			<li>MySQL:

<?php
	// note: this piece of code is taken from phpMyAdmin
	$result = @mysql_query('SELECT VERSION() AS version');

	if ($result != FALSE && @mysql_num_rows($result) > 0) {
		$row = mysql_fetch_array($result);
		$match = explode('.', $row['version']);
	} else {
		$result = @mysql_query('SHOW VARIABLES LIKE \'version\'');

		if ($result != FALSE && @mysql_num_rows($result) > 0) {
			$row = mysql_fetch_row($result);
			$match = explode('.', $row[1]);
		} else {
			$match[0] = '?';
			$match[1] = '?';
			$match[2] = '?';
		}
	}

	if (!isset($match) || !isset($match[0]) ) {
		$match[0] = 3;
	}

	if (!isset($match[1]) ) {
		$match[1] = 21;
	}

	if (!isset($match[2]) ) {
		$match[2] = 0;
	}

	if ($match[0] != '?') {
		$match[0] = intval($match[0]);
		$match[1] = intval($match[1]);
	}

	$mysqlVersion = implode($match, '.');
	$minVersion = '3.23';

	echo $mysqlVersion;

	if ($mysqlVersion < $minVersion) {
		echo ' <span class="warning">WARNING: Nucleus requires at least version ', $minVersion, '</span>';
	}
?>

			</li>
		</ul>

<?php
	// tell people how they can have their config file filled out automatically
	if (@file_exists('config.php') && @!is_writable('config.php') ) {
?>

		<h1>Automatic <i>config.php</i> Update</h1>

		<p>If you want Nucleus to automatically update the <em>config.php</em> file, you'll need to make it writable. You can do this by changing the file permissions to <strong>666</strong>. After Nucleus is successfully installed, you can change the permissions back to <strong>444</strong> (<a href="nucleus/documentation/tips.html#filepermissions">Quick guide on how to change file permissions</a>).</p>

		<p>If you choose not to make your file writable (or are unable to do so): don't worry. The installation process will provide you with the contents of the <em>config.php</em> file so you can upload it yourself.</p>

<?php } ?>

		<h1>MySQL login data</h1>

		<p>Enter your MySQL data below. This install script needs it to be able to create and fill your database tables. Afterwards, you'll also need to fill it out in <i>config.php</i>.</p>

		<p>If you don't know this information, contact your system administrator for more info. Often, the hostname will be 'localhost'. If Nucleus found a 'default MySQL host' in the PHP settings of your server, this host is already listed in the 'hostname' field. There's no guarantee that this information is correct, though.</p>

		<fieldset>
			<legend>General Database Settings</legend>
			<table>
				<tr>
					<td>Hostname:</td>
					<td><input name="mySQL_host" value="<?php echo htmlspecialchars(@ini_get('mysql.default_host') )?>" /></td>
				</tr>
				<tr>
					<td>Username:</td>
					<td><input name="mySQL_user" /></td>
				</tr>
				<tr>
					<td>Password:</td>
					<td><input name="mySQL_password" type="password" /></td>
				</tr>
				<tr>
					<td>Database:</td>
					<td><input name="mySQL_database" /> (<input name="mySQL_create" value="1" type="checkbox" id="mySQL_create"><label for="mySQL_create" />needs to be created</label>)</td>
				</tr>
			</table>
		</fieldset>

		<fieldset>
			<legend>Advanced Database Settings</legend>
			<table>
				<tr>
					<td><input name="mySQL_usePrefix" value="1" type="checkbox" id="mySQL_usePrefix"><label for="mySQL_usePrefix" />Use table prefix:</label></td>
					<td><input name="mySQL_tablePrefix" value="" /></td>
				</tr>
			</table>

			<p>Unless you're installing multiple Nucleus installations in one single database and know what you're doing, <strong>you really shouldn't change this</strong>.</p>
			<p>All database tables generated by Nucleus will start with this prefix.</p>
		</fieldset>

		<h1>Directories and URLs</h1>

		<p>This install script has attempted to find out the directories and URLs in which Nucleus is installed. Please check the values below and correct if necessary. The URLs and file paths should end with a slash.</p>

<?php

	// no need to this all! dirname(__FILE__) is all we need -- moraes
	/*
	// discover full path
	$fullPath = serverVar('PATH_TRANSLATED');

	if ($fullPath == '') {
		$fullPath = serverVar('SCRIPT_FILENAME');
	}

	$basePath = str_replace('install.php', '', $fullPath);
	$basePath = replaceDoubleBackslash($basePath);
	$basePath = replaceDoubleBackslash($basePath);

	// add slash at end if necessary
	if (!endsWithSlash($basePath) ) {
		$basePath .= '/';
	}
	*/

	$basePath = dirname(__FILE__) . '/';
?>

		<fieldset>
			<legend>URLs and directories</legend>
			<table>
				<tr>
					<td>Site <strong>URL</strong>:</td>
					<td><input name="IndexURL" size="60" value="<?php
						$url = 'http://' . serverVar('HTTP_HOST') . serverVar('PHP_SELF');
						$url = str_replace('install.php', '', $url);
						$url = replaceDoubleBackslash($url);

						// add slash at end if necessary
						if (!endsWithSlash($url) ) {
							$url .= '/';
						}

						echo $url; ?>" /></td>
				</tr>
				<tr>
					<td>Admin-area <strong>URL</strong>:</td>
					<td><input name="AdminURL" size="60" value="<?php
						if ($url) {
							echo $url, 'nucleus/';
						} ?>" /></td>
				</tr>
				<tr>
					<td>Admin-area <strong>path</strong>:</td>
					<td><input name="AdminPath" size="60" value="<?php
						if($basePath) {
							echo $basePath, 'nucleus/';
						} ?>" /></td>
				</tr>
				<tr>
					<td>Media files <strong>URL</strong>:</td>
					<td><input name="MediaURL" size="60" value="<?php
						if ($url) {
							echo $url, 'media/';
						} ?>" /></td>
				</tr>
				<tr>
					<td>Media directory <strong>path</strong>:</td>
					<td><input name="MediaPath" size="60" value="<?php
						if ($basePath) {
							echo $basePath, 'media/';
						} ?>" /></td>
				</tr>
				<tr>
					<td>Extra skin files <strong>URL</strong>:</td>
					<td><input name="SkinsURL" size="60" value="<?php
						if ($url) {
							echo $url, 'skins/';
						} ?>" />
						<br />(used by imported skins)
					</td>
				</tr>
				<tr>
					<td>Extra skin files directory <strong>path</strong>:</td>
					<td><input name="SkinsPath" size="60" value="<?php
						if ($basePath) {
							echo $basePath, 'skins/';
						} ?>" />
						<br />(this is where imported skins can place their extra files)
					</td>
				</tr>
				<tr>
					<td>Plugin files <strong>URL</strong>:</td>
					<td><input name="PluginURL" size="60" value="<?php
						if ($url) {
							echo $url, 'nucleus/plugins/';
						} ?>" /></td>
				</tr>
				<tr>
					<td>Action <strong>URL</strong>:</td>
					<td><input name="ActionURL" size="60" value="<?php
						if ($url) {
							echo $url, 'action.php';
						} ?>" />
						<br />(absolute location of the <tt>action.php</tt> file)
					</td>
				</tr>
			</table>
		</fieldset>

		<p class="note"><strong>Note: Use absolute paths</strong> instead of relative paths. Usually, an absolute path will start with something like <tt>/home/username/public_html/</tt>. On Unix systems (most servers), paths should start with a slash. If you have trouble filling out this information, you should ask your administrator what to fill out.</p>

		<h1>Administrator User</h1>

		<p>Below, you need to enter some information to create the first user of your site.</p>

		<fieldset>
			<legend>Administrator User</legend>
			<table>
				<tr>
					<td>Display Name:</td>
					<td><input name="User_name" value="" /> <small>(allowed characters: a-z and 0-9, spaces allowed inside)</small></td>
				</tr>
				<tr>
					<td>Real Name:</td>
					<td><input name="User_realname" value="" /></td>
				</tr>
				<tr>
					<td>Password:</td>
					<td><input name="User_password" type="password" value="" /></td>
				</tr>
				<tr>
					<td>Password Again:</td>
					<td><input name="User_password2" type="password" value="" /></td>
				</tr>
				<tr>
					<td>E-mail Address:</td>
					<td><input name="User_email" value="" /> <small>(needs to be a valid e-mail address)</small></td>
				</tr>
			</table>
		</fieldset>

		<h1>Weblog data</h1>

		<p>Below, you need to enter some information to create a default weblog. The name of this weblog will also be used as name for your site</p>

		<fieldset>
			<legend>Weblog Data</legend>
			<table>
				<tr>
					<td>Blog Name:</td>
					<td><input name="Blog_name" size="60" value="My Nucleus CMS" /></td>
				</tr>
				<tr>
					<td>Blog Short Name:</td>
					<td><input name="Blog_shortname" value="mynucleuscms" /> <small>(allowed characters: a-z and 0-9, no spaces allowed)</small></td>
				</tr>
			</table>
		</fieldset>

		<h1>Submit</h1>

		<p>Verify the data above, and click the button below to set up your database tables and initial data. This can take a while, so have patience. <strong>ONLY CLICK THE BUTTON ONCE !</strong></p>

		<p><input name="action" value="go" type="hidden" /> <input type="submit" value="Install Nucleus" onclick="return checkSubmit();" /></p>

		</form>
	</body>
</html>

<?php }

function tableName($unPrefixed) {
	global $mysql_usePrefix, $mysql_prefix;

	if ($mysql_usePrefix == 1) {
		return $mysql_prefix . $unPrefixed;
	} else {
		return $unPrefixed;
	}
}

function doInstall() {
	global $mysql_usePrefix, $mysql_prefix;

	// 0. put all POST-vars into vars
	$mysql_host = postVar('mySQL_host');
	$mysql_user = postVar('mySQL_user');
	$mysql_password = postVar('mySQL_password');
	$mysql_database = postVar('mySQL_database');
	$mysql_create = postVar('mySQL_create');
	$mysql_usePrefix = postVar('mySQL_usePrefix');
	$mysql_prefix = postVar('mySQL_tablePrefix');
	$config_indexurl = postVar('IndexURL');
	$config_adminurl = postVar('AdminURL');
	$config_adminpath = postVar('AdminPath');
	$config_mediaurl = postVar('MediaURL');
	$config_skinsurl = postVar('SkinsURL');
	$config_pluginurl = postVar('PluginURL');
	$config_actionurl = postVar('ActionURL');
	$config_mediapath = postVar('MediaPath');
	$config_skinspath = postVar('SkinsPath');
	$user_name = postVar('User_name');
	$user_realname = postVar('User_realname');
	$user_password = postVar('User_password');
	$user_password2 = postVar('User_password2');
	$user_email = postVar('User_email');
	$blog_name = postVar('Blog_name');
	$blog_shortname = postVar('Blog_shortname');
	$config_adminemail = $user_email;
	$config_sitename = $blog_name;

	$config_indexurl = replaceDoubleBackslash($config_indexurl);
	$config_adminurl = replaceDoubleBackslash($config_adminurl);
	$config_mediaurl = replaceDoubleBackslash($config_mediaurl);
	$config_skinsurl = replaceDoubleBackslash($config_skinsurl);
	$config_pluginurl = replaceDoubleBackslash($config_pluginurl);
	$config_actionurl = replaceDoubleBackslash($config_actionurl);
	$config_adminpath = replaceDoubleBackslash($config_adminpath);
	$config_skinspath = replaceDoubleBackslash($config_skinspath);

	// 1. check all the data
	$errors = array();

	if (!$mysql_database) {
		array_push($errors, 'mySQL database name missing');
	}

	if (($mysql_usePrefix == 1) && (strlen($mysql_prefix) == 0) ) {
		array_push($errors, 'mySQL prefix was selected, but prefix is empty');
	}

	if (($mysql_usePrefix == 1) && (!eregi('^[a-zA-Z0-9_]+$', $mysql_prefix) ) ) {
		array_push($errors, 'mySQL prefix should only contain characters from the ranges A-Z, a-z, 0-9 or underscores');
	}

	// TODO: add action.php check
	if (!endsWithSlash($config_indexurl) || !endsWithSlash($config_adminurl) || !endsWithSlash($config_mediaurl) || !endsWithSlash($config_pluginurl) || !endsWithSlash($config_skinsurl) ) {
		array_push($errors, 'One of the URLs does not end with a slash, or action url does not end with \'action.php\'');
	}

	if (!endsWithSlash($config_adminpath) ) {
		array_push($errors, 'The path of the administration area does not end with a slash');
	}

	if (!endsWithSlash($config_mediapath) ) {
		array_push($errors, 'The media path does not end with a slash');
	}

	if (!endsWithSlash($config_skinspath) ) {
		array_push($errors, 'The skins path does not end with a slash');
	}

	if (!is_dir($config_adminpath) ) {
		array_push($errors, 'The path of the administration area does not exist on your server');
	}

	if (!_isValidMailAddress($user_email) ) {
		array_push($errors, 'Invalid e-mail address given for user');
	}

	if (!_isValidDisplayName($user_name) ) {
		array_push($errors, 'User name is not a valid display name (allowed chars: a-zA-Z0-9 and spaces)');
	}

	if (!$user_password || !$user_password2) {
		array_push($errors, 'User password is empty');
	}

	if ($user_password != $user_password2) {
		array_push($errors, 'User password do not match');
	}

	if (!_isValidShortName($blog_shortname) ) {
		array_push($errors, 'Invalid short name given for blog (allowed chars: a-z0-9, no spaces)');
	}

	if (sizeof($errors) > 0) {
		showErrorMessages($errors);
	}

	// 2. try to log in to mySQL
	global $MYSQL_CONN;
	$MYSQL_CONN = @mysql_connect($mysql_host, $mysql_user, $mysql_password);

	if ($MYSQL_CONN == false) {
		_doError('Could not connect to mySQL server: ' . mysql_error() );
	}

	// 3. try to create database (if needed)
	if ($mysql_create == 1) {
		mysql_query('CREATE DATABASE ' . $mysql_database) or _doError('Could not create database. Make sure you have the rights to do so. SQL error was: ' . mysql_error() );
	}

	// 4. try to select database
	mysql_select_db($mysql_database) or _doError('Could not select database. Make sure it exists');

	// 5. execute queries
	$filename = 'install.sql';
	$fd = fopen($filename, 'r');
	$queries = fread($fd, filesize($filename) );
	fclose($fd);

	$queries = split("(;\n|;\r)", $queries);

	$aTableNames = array(
		'nucleus_actionlog',
		'nucleus_ban',
		'nucleus_blog',
		'nucleus_category',
		'nucleus_comment',
		'nucleus_config',
		'nucleus_item',
		'nucleus_karma',
		'nucleus_member',
		'nucleus_plugin',
		'nucleus_skin',
		'nucleus_template',
		'nucleus_team',
		'nucleus_activation',
		'nucleus_tickets'
		);
// these are unneeded (one of the replacements above takes care of them)
//			'nucleus_plugin_event',
//			'nucleus_plugin_option',
//			'nucleus_plugin_option_desc',
//			'nucleus_skin_desc',
//			'nucleus_template_desc',

	$aTableNamesPrefixed = array(
		$mysql_prefix . 'nucleus_actionlog',
		$mysql_prefix . 'nucleus_ban',
		$mysql_prefix . 'nucleus_blog',
		$mysql_prefix . 'nucleus_category',
		$mysql_prefix . 'nucleus_comment',
		$mysql_prefix . 'nucleus_config',
		$mysql_prefix . 'nucleus_item',
		$mysql_prefix . 'nucleus_karma',
		$mysql_prefix . 'nucleus_member',
		$mysql_prefix . 'nucleus_plugin',
		$mysql_prefix . 'nucleus_skin',
		$mysql_prefix . 'nucleus_template',
		$mysql_prefix . 'nucleus_team',
		$mysql_prefix . 'nucleus_activation',
		$mysql_prefix . 'nucleus_tickets'
		);
// these are unneeded (one of the replacements above takes care of them)
//			$mysql_prefix . 'nucleus_plugin_event',
//			$mysql_prefix . 'nucleus_plugin_option',
//			$mysql_prefix . 'nucleus_plugin_option_desc',
//			$mysql_prefix . 'nucleus_skin_desc',
//			$mysql_prefix . 'nucleus_template_desc',

	$count = count($queries);

	for ($idx = 0; $idx < $count; $idx++) {
		$query = trim($queries[$idx]);
		// echo "QUERY = <small>" . htmlspecialchars($query) . "</small><p>";

		if ($query) {

			if ($mysql_usePrefix == 1) {
					$query = str_replace($aTableNames, $aTableNamesPrefixed, $query);
			}

			mysql_query($query) or _doError('Error while executing query (<small>' . htmlspecialchars($query) . '</small>): ' . mysql_error() );
		}
	}

	// 6. update global settings
	updateConfig('IndexURL', $config_indexurl);
	updateConfig('AdminURL', $config_adminurl);
	updateConfig('MediaURL', $config_mediaurl);
	updateConfig('SkinsURL', $config_skinsurl);
	updateConfig('PluginURL', $config_pluginurl);
	updateConfig('ActionURL', $config_actionurl);
	updateConfig('AdminEmail', $config_adminemail);
	updateConfig('SiteName', $config_sitename);

	// 7. update GOD member
	$query = 'UPDATE ' . tableName('nucleus_member')
			. " SET mname='" . addslashes($user_name) . "',"
			. " mrealname='" . addslashes($user_realname) . "',"
			. " mpassword='" . md5(addslashes($user_password) ) . "',"
			. " murl='" . addslashes($config_indexurl) . "',"
			. " memail='" . addslashes($user_email) . "',"
			. " madmin=1, mcanlogin=1"
			. " WHERE mnumber=1";

	mysql_query($query) or _doError('Error while setting member settings: ' . mysql_error() );

	// 8. update weblog settings
	$query = 'UPDATE ' . tableName('nucleus_blog')
			. " SET bname='" . addslashes($blog_name) . "',"
			. " bshortname='" . addslashes($blog_shortname) . "',"
			. " burl='" . addslashes($config_indexurl) . "'"
			. " WHERE bnumber=1";

	mysql_query($query) or _doError('Error while setting weblog settings: ' . mysql_error() );

	// 9. update item date
	$query = 'UPDATE ' . tableName('nucleus_item')
			. " SET itime='" . date('Y-m-d H:i:s', time() ) ."'"
			. " WHERE inumber=1";

	mysql_query($query) or _doError('Error with query: ' . mysql_error() );

	global $aConfPlugsToInstall, $aConfSkinsToImport;
	$aSkinErrors = array();
	$aPlugErrors = array();

	if ((count($aConfPlugsToInstall) > 0) || (count($aConfSkinsToImport) > 0) ) {
		// 10. set global variables
		global $MYSQL_HOST, $MYSQL_USER, $MYSQL_PASSWORD, $MYSQL_DATABASE, $MYSQL_PREFIX;

		$MYSQL_HOST = $mysql_host;
		$MYSQL_USER = $mysql_user;
		$MYSQL_PASSWORD = $mysql_password;
		$MYSQL_DATABASE = $mysql_database;
		$MYSQL_PREFIX = ($mysql_usePrefix == 1)?$mysql_prefix:'';

		global $DIR_NUCLEUS, $DIR_MEDIA, $DIR_SKINS, $DIR_PLUGINS, $DIR_LANG, $DIR_LIBS;

		$DIR_NUCLEUS = $config_adminpath;
		$DIR_MEDIA = $config_mediapath;
		$DIR_SKINS = $config_skinspath;
		$DIR_PLUGINS = $DIR_NUCLEUS . 'plugins/';
		$DIR_LANG = $DIR_NUCLEUS . 'language/';
		$DIR_LIBS = $DIR_NUCLEUS . 'libs/';

		// close database connection (needs to be closed if we want to include globalfunctions.php)
		mysql_close();

		$manager = '';
		include_once($DIR_LIBS . 'globalfunctions.php');

		// 11. install custom skins
		$aSkinErrors = installCustomSkins($manager);

		// 12. install custom plugins
		$aPlugErrors = installCustomPlugs($manager);
	}

	// 12. Write config file ourselves (if possible)
	$bConfigWritten = 0;

	if (@file_exists('config.php') && is_writable('config.php') && $fp = @fopen('config.php', 'w') ) {
		$config_data = '<' . '?php' . "\n\n";
		//$config_data .= "\n"; (extraneous, just added extra \n to previous line
		$config_data .= "	// mySQL connection information\n";
		$config_data .= "	\$MYSQL_HOST = '" . $mysql_host . "';\n";
		$config_data .= "	\$MYSQL_USER = '" . $mysql_user . "';\n";
		$config_data .= "	\$MYSQL_PASSWORD = '" . $mysql_password . "';\n";
		$config_data .= "	\$MYSQL_DATABASE = '" . $mysql_database . "';\n";
		$config_data .= "	\$MYSQL_PREFIX = '" . (($mysql_usePrefix == 1)?$mysql_prefix:'') . "';\n";
		$config_data .= "\n";
		$config_data .= "	// main nucleus directory\n";
		$config_data .= "	\$DIR_NUCLEUS = '" . $config_adminpath . "';\n";
		$config_data .= "\n";
		$config_data .= "	// path to media dir\n";
		$config_data .= "	\$DIR_MEDIA = '" . $config_mediapath . "';\n";
		$config_data .= "\n";
		$config_data .= "	// extra skin files for imported skins\n";
		$config_data .= "	\$DIR_SKINS = '" . $config_skinspath . "';\n";
		$config_data .= "\n";
		$config_data .= "	// these dirs are normally sub dirs of the nucleus dir, but \n";
		$config_data .= "	// you can redefine them if you wish\n";
		$config_data .= "	\$DIR_PLUGINS = \$DIR_NUCLEUS . 'plugins/';\n";
		$config_data .= "	\$DIR_LANG = \$DIR_NUCLEUS . 'language/';\n";
		$config_data .= "	\$DIR_LIBS = \$DIR_NUCLEUS . 'libs/';\n";
		$config_data .= "\n";
		$config_data .= "	// include libs\n";
		$config_data .= "	include(\$DIR_LIBS.'globalfunctions.php');\n";
		$config_data .= "?" . ">";

		$result = @fputs($fp, $config_data, strlen($config_data) );
		fclose($fp);

		if ($result) {
			$bConfigWritten = 1;
		}
	}

?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
	<title>Nucleus Install</title>
	<style>@import url('nucleus/styles/manual.css');</style>
</head>
<body>
	<div style='text-align:center'><img src='./nucleus/styles/logo.gif' /></div> <!-- Nucleus logo -->

<?php
	$aAllErrors = array_merge($aSkinErrors, $aPlugErrors);

	if (count($aAllErrors) > 0) {
		echo '<h1>Skin/Plugin Install errors</h1>';
		echo '<ul><li>' . implode('</li><li>', $aAllErrors) . '</li></ul>';
	}

	if (!$bConfigWritten) { ?>
		<h1>Installation Almost Complete!</h1>

		<p>The database tables have been initialized successfully. What still needs to be done is to change the contents of <i>config.php</i>. Below is how it should look like (the mysql password is masked, so you'll have to fill that out yourself)</p>

		<pre><code>&lt;?php
	// mySQL connection information
	$MYSQL_HOST = '<b><?php echo $mysql_host?></b>';
	$MYSQL_USER = '<b><?php echo $mysql_user?></b>';
	$MYSQL_PASSWORD = '<i><b>xxxxxxxxxxx</b></i>';
	$MYSQL_DATABASE = '<b><?php echo $mysql_database?></b>';
	$MYSQL_PREFIX = '<b><?php echo ($mysql_usePrefix == 1)?$mysql_prefix:''?></b>';

	// main nucleus directory
	$DIR_NUCLEUS = '<b><?php echo $config_adminpath?></b>';

	// path to media dir
	$DIR_MEDIA = '<b><?php echo $config_mediapath?></b>';

	// extra skin files for imported skins
	$DIR_SKINS = '<b><?php echo $config_skinspath?></b>';

	// these dirs are normally sub dirs of the nucleus dir, but
	// you can redefine them if you wish
	$DIR_PLUGINS = $DIR_NUCLEUS . 'plugins/';
	$DIR_LANG = $DIR_NUCLEUS . 'language/';
	$DIR_LIBS = $DIR_NUCLEUS . 'libs/';

	// include libs
	include($DIR_LIBS.'globalfunctions.php');
?&gt;</code></pre>

	<p>After you changed the file on your computer, upload it to your web server using FTP. Make sure you use ASCII mode to send over the files.</p>

	<div class="note">
		<b>Note:</b> Make sure that you have no spaces at the beginning or end of the <i>config.php</i> file. These would cause errors to happen when performing certain actions.<br />
		Thus, the first character of config.php should be "&lt;", and the last character should be "&gt;".
	</div>

<?php } else { ?>

	<h1>Installation complete!</h1>

	<p>Nucleus has been installed, and your <code>config.php</code> has been updated for you.</p>

	<p>Don't forget to change the permissions on <code>config.php</code> back to 444 for security (<a href="nucleus/documentation/tips.html#filepermissions">Quick guide on how to change file permissions</a>).</p>

<?php } ?>

	<h1>Fight against Spam</h1>
	
	<p>Nucleus CMS allows every visitor to write comments in blogs. So there is a high risk that spammers abuse this function. We recommend that you protect your blog with one of the following methods:</p>

	<ul>
		<li>If you don't want comments you can disable them individually for each blog: Go to the hompage of the Admin area and choose <b>Your Weblog > Settings > Comments enabled > No</b>.</li>
		<li>Install one of serveral plugins that help to avoid spam comments: <a href="http://faq.nucleuscms.org/item/45">How can I stop comment and trackback spam?</a> (you could bookmark this page to read it later).</li>
	</ul>
	
	<h1>Delete your install files</h1>

	<p>Files you should delete from your web server:</p>

	<ul>
		<li><b>install.sql</b>: file containing table structures</li>
		<li><b>install.php</b>: this file</li>
	</ul>

	<p>If you don't delete these files, you won't be able to open the admin area</p>

	<h1>Visit your web site</h1>

	<p>Your web site is now ready to use.
		<ul>
			<li><a href="<?php echo $config_adminurl?>">Login to the admin area to configure your site</a></li>
			<li><a href="<?php echo $config_indexurl?>">Visit your site now</a></li>
		</ul>
	</p>

</body>
</html>

<?php
}

function installCustomPlugs(&$manager) {
	global $aConfPlugsToInstall, $DIR_LIBS;

	$aErrors = array();

	if (count($aConfPlugsToInstall) == 0) {
		return $aErrors;
	}

	$res = sql_query('SELECT * FROM ' . sql_table('plugin') );
	$numCurrent = mysql_num_rows($res);

	foreach ($aConfPlugsToInstall as $plugName) {
		// do this before calling getPlugin (in case the plugin id is used there)
		$query = 'INSERT INTO ' . sql_table('plugin') . ' (porder, pfile) VALUES (' . (++$numCurrent) . ', "' . addslashes($plugName) . '")';
		sql_query($query);

		// get and install the plugin
		$plugin =& $manager->getPlugin($plugName);

		if (!$plugin) {
			sql_query('DELETE FROM ' . sql_table('plugin') . ' WHERE pfile=\'' . addslashes($plugName) . '\'');
			$numCurrent--;
			array_push($aErrors, 'Unable to install plugin ' . $plugName);
			continue;
		}

		$plugin->install();
	}

	// SYNC PLUGIN EVENT LIST
	sql_query('DELETE FROM ' . sql_table('plugin_event') );

	// loop over all installed plugins
	$res = sql_query('SELECT pid, pfile FROM ' . sql_table('plugin') );

	while($o = mysql_fetch_object($res) ) {
		$pid = $o->pid;
		$plug =& $manager->getPlugin($o->pfile);

		if ($plug) {
			$eventList = $plug->getEventList();

			foreach ($eventList as $eventName) {
				sql_query('INSERT INTO ' . sql_table('plugin_event') . ' (pid, event) VALUES (' . $pid . ', \'' . $eventName . '\')');
			}
		}
	}

	return $aErrors;
}

function installCustomSkins(&$manager) {
	global $aConfSkinsToImport, $DIR_LIBS, $DIR_SKINS;

	$aErrors = array();

	if (count($aConfSkinsToImport) == 0) {
		return $aErrors;
	}

	// load skinie class
	include_once($DIR_LIBS . 'skinie.php');

	$importer = new SKINIMPORT();

	foreach ($aConfSkinsToImport as $skinName) {
		$importer->reset();
		$skinFile = $DIR_SKINS . $skinName . '/skinbackup.xml';

		if (!@file_exists($skinFile) ) {
			array_push($aErrors, 'Unable to import ' . $skinFile . ' : file does not exist');
			continue;
		}

		$error = $importer->readFile($skinFile);

		if ($error) {
			array_push($aErrors, 'Unable to import ' . $skinName . ' : ' . $error);
			continue;
		}

		$error = $importer->writeToDatabase(1);

		if ($error) {
			array_push($aErrors, 'Unable to import ' . $skinName . ' : ' . $error);
			continue;
		}
	}

	return $aErrors;
}

// give an error if one or more nucleus are not accessible
function doCheckFiles() {
	$missingfiles = array();
	$files = array(
		'install.sql',
		'index.php',
		'action.php',
		'nucleus/index.php',
		'nucleus/libs/globalfunctions.php',
		'nucleus/libs/ADMIN.php',
		'nucleus/libs/BLOG.php',
		'nucleus/libs/COMMENT.php',
		'nucleus/libs/COMMENTS.php',
		'nucleus/libs/ITEM.php',
		'nucleus/libs/MEMBER.php',
		'nucleus/libs/SKIN.php',
		'nucleus/libs/TEMPLATE.php',
		'nucleus/libs/MEDIA.php',
		'nucleus/libs/ACTIONLOG.php',
		'nucleus/media.php'
		);

	$count = count($files);

	for ($i = 0; $i < $count; $i++) {
		if (!is_readable($files[$i]) ) {
			array_push($missingfiles, 'File <b>' . $files[$i] . '</b> is missing or not readable.');
		}
	}

// The above code replaces several if statements of the form:

//	if (!is_readable('install.sql') ) {
//		array_push($missingfiles, 'File <b>install.sql</b> is missing or not readable');
//	}

	if (count($missingfiles) > 0) {
		showErrorMessages($missingfiles);
	}
}

function updateConfig($name, $val) {
	$name = addslashes($name);
	$val = trim(addslashes($val) );

	$query = 'UPDATE ' . tableName('nucleus_config')
			. " SET value='$val'"
			. " WHERE name='$name'";

	mysql_query($query) or _doError('Query error while trying to update config: ' . mysql_error() );
	return mysql_insert_id();
}

function replaceDoubleBackslash($input) {
	return str_replace('\\', '/', $input);
}

function endsWithSlash($s) {
	return (strrpos($s, '/') == strlen($s) - 1);
}

/**
 * Checks if email address is valid
 */
function _isValidMailAddress($address) {
	if (preg_match("/^[a-zA-Z0-9\._-]+@+[A-Za-z0-9\._-]+\.+[A-Za-z]{2,4}$/", $address) ) {
		return 1;
	} else {
		return 0;
	}
}

// returns true if the given string is a valid shortname
// (to check short blog names and nicknames)
// logic: starts and ends with a non space, can contain spaces in between
//        min 2 chars
function _isValidShortName($name) {
	if (eregi("^[a-z0-9]+$", $name) ) {
		return 1;
	} else {
		return 0;
	}
}



// returns true if the given string is a valid display name
// (to check nicknames)
function _isValidDisplayName($name) {
	if (eregi("^[a-z0-9]+[a-z0-9 ]*[a-z0-9]+$", $name) ) {
		return 1;
	} else {
		return 0;
	}
}

function _doError($msg) {
	?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
	<title>Nucleus Install</title>
	<style>@import url('nucleus/styles/manual.css');</style>
</head>
<body>
	<div style='text-align:center'><img src='./nucleus/styles/logo.gif' /></div> <!-- Nucleus logo -->
	<h1>Error!</h1>

	<p>Error message was: "<?php echo $msg?>";</p>

	<p><a href="install.php" onclick="history.back();return false;">Go Back</a></p>
</body>
</html>

<?php
	exit;
}

function showErrorMessages($errors) {
	?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
	<title>Nucleus Install</title>
	<style>@import url('nucleus/styles/manual.css');</style>
</head>
<body>
	<div style='text-align:center'><img src='./nucleus/styles/logo.gif' /></div> <!-- Nucleus logo -->
	<h1>Errors!</h1>

	<p>Errors were found:</p>

	<ul>

<?php
	while($msg = array_shift($errors) ) {
		echo '<li>', $msg, '</li>';
	}
?>

	</ul>

	<p><a href="install.php" onclick="history.back();return false;">Go Back</a></p>
</body>
</html>

<?php
	exit;
}

/* for the non-php systems that decide to show the contents:
?></div><?php	*/

?>
