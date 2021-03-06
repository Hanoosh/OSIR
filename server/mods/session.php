<?php
	/*
		This is a static class containing
		functions related to the admin
		session. It deals with non-
		cryptographic security and the
		initial creation of the OSIR
		database.
	*/
	
	class Session{
		//Private Constants
		const _DB_NAME="OSIR";
		const _SQL_USERNAME="root";
		const _SQL_PASSWORD="";
		const _MAX_SESSION_TIME=12*3600;
		const _ADMIN_TABLE_DEFINITION="CREATE TABLE ADMIN (
										ID int(11) AUTO_INCREMENT,
										HASH varchar(512) NOT NULL,
										ATTEMPTS int(11),
										WIN32_BINARY_LAST_UPDATED varchar(512) NOT NULL,
										LINUX_BINARY_LAST_UPDATED varchar(512) NOT NULL,
										PRIMARY KEY (ID)
										)";

		const _KEYS_TABLE_DEFINITION="CREATE TABLE RSAKEYS (
										ID int(11) AUTO_INCREMENT,
										PUBLIC varchar(4096) NOT NULL,
										PRIVATE varchar(4096) NOT NULL,
										SIZE int(11),
										PRIMARY KEY (ID)
										)";
		const _LOG_TABLE_DEFINITION="CREATE TABLE LOG(
										ID int(11) AUTO_INCREMENT,
										TIMESTAMP varchar(32) NOT NULL,
										IP varchar(32) NOT NULL,
										TYPE varchar(32) NOT NULL,
										ENTRY varchar(1024) NOT NULL,
										PRIMARY KEY (ID)
										)";
										
		//Private methods
		private static function setInitialAdminTable($link){
			include_once("../mods/security.php");
			mysqli_query($link,"INSERT INTO ADMIN (HASH,ATTEMPTS,WIN32_BINARY_LAST_UPDATED,LINUX_BINARY_LAST_UPDATED) VALUES ('".Security::makeSaltedHash(Security::DEFAULT_PASSWORD) . "',0,'Never updated','Never updated')");
		}
		private static function setInitialKeysTable($link){
			include_once("../mods/keypair.php");
			//Generate keypair and export to database
			$keypair=new KeyPair();
			$keypair->generate();
			$keypair->sqlExport($link);
		}
		
		//Create database/table if they don't exist
		public static function forceConnectDB(){
			$db_name=self::_DB_NAME;
			
			//Connect with SQL
			$link=mysqli_connect("localhost",self::_SQL_USERNAME, self::_SQL_PASSWORD);
			if(!$link){
				die("<div class='alert'>Can't connect to database</div>" );
			}
			
			//Try to connect to database
			$database=mysqli_select_db($link,"$db_name");
			
			//Create database if it doesn't exist
			if(!$database){
				mysqli_query($link,"CREATE DATABASE $db_name;");
				$database=mysqli_select_db($link,"$db_name");
				if(!$database)
					echo("<div class='alert'>Error: database creation failed</div><br>");
				
			}
			
			//Attempt to create table if it doesn't exist
			if(empty(mysqli_query($link, "SELECT ID FROM ADMIN"))){
				mysqli_query($link,self::_ADMIN_TABLE_DEFINITION);
				self::setInitialAdminTable($link);
			}
			if(empty(mysqli_query($link, "SELECT ID FROM RSAKEYS"))){
				mysqli_query($link,self::_KEYS_TABLE_DEFINITION);
				self::setInitialKeysTable($link);
			}
			if(empty(mysqli_query($link, "SELECT ID FROM LOG"))){
				mysqli_query($link,self::_LOG_TABLE_DEFINITION);
				include_once("eventlog.php");
				EventLog::addEntry($link, "Server", "Created table LOG", true);
			}
			return $link;
		}
		
		
		//If not in session, redirect to login page
		public static function checkAdminSession(){
			//Make sure using TLS
			if (!isset($_SERVER['HTTPS']) || !$_SERVER['HTTPS']) {
				header("Location: https://".$_SERVER["SERVER_NAME"].$_SERVER["REQUEST_URI"]);
			}
			
			//Check session
			session_start();
			if(!array_key_exists('admin_session',$_SESSION)){
				header("Location: ../admin/login.php");
			//Twelve hour max time
			}else if($_SESSION["timeout"]+self::_MAX_SESSION_TIME<time()){
				unset($_SESSION);
				session_destroy();
				header("Location: ../admin/login.php");
			}
			
		}
	}
?>
