<?
	//
	// GANGPLANK CONFIGURABLE PARAMETERS
	//
	// Edit these to configure Gangplank.
	// 
	
	// --[ Database Settings ]--
	define("GANGPLANK_DB_HOST", $db_host);
	define("GANGPLANK_DB_USER", $db_user);
	define("GANGPLANK_DB_PASS", $db_pw);
	define("GANGPLANK_DB_DB",   $db_db);
	
	// --[ Misc Settings ]--
	define("GANGPLANK_BASE_TABLE_PREFIX", $db_table_prefix);
	define("GANGPLANK_DEBUG", false);
	define("GANGPLANK_EXT", "phtml");
	// "underscores" or "camelcase"
	define("GANGPLANK_TABLE_NAME_FORMAT", "underscores");
	
	// --[ Icon and Appearance Settings ]--
	define("GANGPLANK_USE_ICONS", true);
	// Full or relative URL to the icon images folder:

	// --[ Image and Asset Upload Settings ]--
	// These URLs and paths should end with /
	define("GANGPLANK_SCALER", "gd");
	define("GANGPLANK_UL_PATH", "/home/socialtray/app/dev/ul/");
	define("GANGPLANK_UL_URL", "/ul/");
	define("GANGPLANK_IMAGEMAGICK_BINARY", "/usr/local/bin/convert");
	
	// End of configurable parameters
?>
