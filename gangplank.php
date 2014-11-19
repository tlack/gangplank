<?
	//
	// The Gangplank Library
	// (C) Copyright 2005 ModernMethod, Inc.
	//
	
	// Include the rest of the library
	define('GANGPLANK_BASE', dirname(__FILE__) . '/');
	require_once(GANGPLANK_BASE . 'GangplankConfig.php');

	require_once(GANGPLANK_BASE . 'GangplankAuto.php');
	require_once(GANGPLANK_BASE . 'GangplankBase.php');
	require_once(GANGPLANK_BASE . 'GangplankDbMysql.php');
	require_once(GANGPLANK_BASE . 'GangplankEdit.php');
	require_once(GANGPLANK_BASE . 'GangplankList.php');
	require_once(GANGPLANK_BASE . 'GangplankListSimple.php');
	require_once(GANGPLANK_BASE . 'GangplankMisc.php');
	require_once(GANGPLANK_BASE . 'GangplankNew.php');
?>