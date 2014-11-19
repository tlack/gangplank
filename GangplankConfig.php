<?
	//
	// GANGPLANK CONFIGURABLE PARAMETERS
	//
	// Edit these to configure Gangplank.
	// 

	$DB = Config::get('database');
	
	// --[ Database Settings ]--
	define("GANGPLANK_DB_HOST", $DB['host']);
	define("GANGPLANK_DB_USER", $DB['user']);
	define("GANGPLANK_DB_PASS", $DB['pass']);
	define("GANGPLANK_DB_DB",   $DB['db']);
	
	// --[ Misc Settings ]--
	define("GANGPLANK_BASE_TABLE_PREFIX", "");
	define("GANGPLANK_DEBUG", false);
	define("GANGPLANK_EXT", "php");
	// "underscores" or "camelcase"
	define("GANGPLANK_TABLE_NAME_FORMAT", "underscores");
	
	// --[ Icon and Appearance Settings ]--
	define("GANGPLANK_USE_ICONS", true);
	// Full or relative URL to the icon images folder:
	define("GANGPLANK_ICON_URL", "http://www.somewhere.com/gangplank/gangplankimages/");

	// --[ Image and Asset Upload Settings ]--
	// These URLs and paths should end with /
	define("GANGPLANK_SCALER", "gd");
	define("GANGPLANK_UL_PATH", "/home/somewhere/public_html/ul/");
	define("GANGPLANK_UL_URL", "/ul/");
	define("GANGPLANK_IMAGEMAGICK_BINARY", "/usr/local/bin/convert");
	
	// End of configurable parameters
?>
