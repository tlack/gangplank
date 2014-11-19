<?
	//
	// Miscellaneous functions used by Gangplank
	// 
	
	if (! function_exists("any_of")) {
		//
		// Return the first argument that isn't NULL or false.
		//
		// Use like this:
		//
		// $width = @any_of($options["default_width"], 250);
		// 
		// If $options has a default_width parameter, it will be used.
		// Otherwise, you'll get 250 back. Returns NULL in the case of
		// failure. 
		//
		function any_of(/* val1, val2, val3, val4.. */) {
			$args = func_get_args();
			for ($i = 0; $i < count($args); $i++) {
				if ($args[$i] !== NULL &&
						$args[$i] !== false) 
					return $args[$i];
			}
			return NULL;
		}
	}
	
	//
	// --[ URL Handling ]--
	//
	
	function gp_add_url_arg($url, $name, $value)
	{	
		if (preg_match("/(\?|&)+(" . preg_quote($name) . "=([^?&]*))/", $url, $regs)) {
			$url = str_replace($regs[2], "$name=".urlencode($value), $url);
		}
		else {
			if (strstr($url, "?")) {
				$url .= "&$name=". urlencode($value);
			}
			else {
				$url .= "?$name=". urlencode($value);
			}
		}
		$url = str_replace("?&", "?", $url);
		return $url;
	}

	// Same as gp_add_url_arg(), but no urlencoding of the value..	
	function gp_add_url_raw_arg($url, $name, $value)
	{	
		if (preg_match("/(\?|&)+(" . preg_quote($name) . "=([^?&]*))/", $url, $regs)) {
			$url = str_replace($regs[2], "$name=".$value, $url);
		}
		else {
			if (strstr($url, "?")) {
				$url .= "&$name=". $value;
			}
			else {
				$url .= "?$name=". $value;
			}
		}
		$url = str_replace("?&", "?", $url);
		return $url;
	}
	
	function gp_my_url()
	{
		if (!empty($_SERVER["HTTPS"]))
			$protocol = "https";
		else
			$protocol = "http";
		return '//' . $_SERVER["HTTP_HOST"] . $_SERVER["REQUEST_URI"];
	}
	
	
	function gp_remove_url_arg($url, $name)
	{
		$url = eregi_replace("(\?|&)+$name=([^?&]*)", "", $url);
		
		// This is great, but we get the case of..
		// http://whatever/blah.phtml?a=1&b=2
		// becoming..
		// http://whatever/blah.phtml&b=2
		// The solution:
		$quest_pos = strpos($url, "?");
		$amp_pos = strpos($url, "&");
		if (!is_integer($quest_pos) && is_integer($amp_pos)) {
			$url[$amp_pos] = "?";
		}
		return $url;
	}
	
	function gp_names_to_table_name($singular, $plural) {
		if (GANGPLANK_TABLE_NAME_FORMAT == "camelcase") {
			$thing = ucwords($plural);
			$thing = str_replace(" ", "", $thing);
			$thing[0] = strtolower($thing[0]);
			return GANGPLANK_BASE_TABLE_PREFIX . $thing;
		}
		if (GANGPLANK_TABLE_NAME_FORMAT == "underscores") {
			$thing = str_replace(" ", "_", $plural);
			return GANGPLANK_BASE_TABLE_PREFIX . $thing;
		}
		gp_die("Invalid GANGPLANK_TABLE_NAME_FORMAT; must be 'underscores' or 'camelcase'");
	}
	
	function gp_valid_col_name($col) {
		if (is_string($col) && 
			  preg_match("/[A-Za-z0-9_]+/", $col))
		  return true;
		else
			return false;
	}
	
	//
	// Assertions, debugging, errors.
	//
	
	function gp_assert($cond, $text) {
		if (!$cond)
			gp_die("Assertion failed: $text");
	}
	
	function gp_debug($text) {
		if (GANGPLANK_DEBUG)
			echo "DEBUG: $text<br/>\n";
	}

	function gp_die($text, $file = false, $line = false) {
		die($text . ($file ? ", in $file" : "") . ($line ? ", line $line" : ""));
		return false;
	}
	
	//
	// Misc.
	// 

	function gp_format_date($date) {
		return date("m/d/y", strtotime($date));
	}
	
	function gp_format_datetime($datetime) {
		return date("m/d/y h:ia", strtotime($datetime));
	}
	
	// Format $money, including a dollar sign
	function gp_format_money($money) {
		return "\$" . sprintf("%0.2f", $money);
	}
	
	// Format $money, but don't add anything but a decimal; basically sprintf("%0.2f", $money);
	function gp_format_money_int($money) {
		return sprintf("%0.2f", $money);
	}
	
	// Redirect to a URL
	function gp_goto($url) {
		header("Location: $url");
		exit;
	}
	
	function gp_interpUrl($url, $row) {
		foreach ($row as $k=>$v) {
			$url = str_replace("{" . $k . "}", $v, $url);
		}
		return $url;
	}
	
	// Parse a size spec into its component parts
	// Size specs look like:
	//   WxH
	// or, with options
	//   WxH opt=1 opt2=b opt3=c
	// or, for no scaling
	//   noscale 
	// In the geometry part, the H value is optional, the "x" is not.
	//
	// Returns array($size, $options_array)
	// $size may == "noscale"
	function gp_parse_size_spec($spec) {
		$re = '#^
			(?P<geom>
				(?P<size>
					(?P<w>[0-9]+x)+(?P<h>[0-9]+)?
				)
				|
				(?P<noscale>
					noscale
				)
			)+
			(?P<opts>
				\s+.+=.+
			)?
			$#x';
		if (preg_match($re, $spec, $parts)) {
			$geom = $parts["geom"];
			$opts = array();
			if (!empty($parts["opts"])) {
				// split opts array into components
				$opts_l = explode(' ', trim($parts["opts"]));
				$opts = array();
				foreach ($opts_l as $opt) {
					$parts_l = explode('=', $opt);
					if (count($parts_l) != 2) //sanity check
						return false;
					$opts[$parts_l[0]] = $parts_l[1];
				}
			}
			if (empty($parts['h'])) $parts['h'] = 0;
			$a = array($geom, (int)$parts["w"], (int)$parts["h"], $opts);
			return $a;
		} else {
			return false;
		}
	}
	
	// Image resizers
	function gp_resize_gd($in_filename, $out_filename, $size) {	 	
		gp_assert(file_exists($in_filename), "gp_resize_gd(): input file '$in_filename' doesnt exist");
		
		// get dimensions of the image
		list($width, $height, $type, $attr) = getimagesize($in_filename);
		
		if (! preg_match("/^([0-9]+(?:x)?)((?:x)?[0-9]+)?$/", $size, $parts)) 
			die("gp_resize_gd(): invalid size spec \"$size\"");
		
		// extract only x value
		$size = $parts[1];
		$new_width = $size;
		$new_height = $height*($size/$width);

		// var_dump($type);
		$in_p = false;
		if ($type == 1) {
			// create from .gif
			$in_p = imagecreatefromgif($in_filename);
		} elseif ($type == 2) {
			// create from .jpg 
			$in_p = imagecreatefromjpeg($in_filename);
		} elseif ($type == 3){
			// create from .png 
			$in_p = imagecreatefrompng($in_filename);
		}
		if (!$in_filename) {
			$in_p = imagecreatetruecolor($new_width, $new_height);
		}
		// var_dump($out_filename);
		$out_p = imagecreatetruecolor($new_width, $new_height);
		imagecopyresampled($out_p, $in_p, 0, 0, 0, 0, $new_width, $new_height, $width, $height);
		imagejpeg($out_p, $out_filename, 90);
	}

	function gp_resize_imagemagick($in_filename, $out_filename, $size) {
			if (defined('GANGPLANK_IMAGEMAGICK_BINARY')) {
				$image_magick_path = GANGPLANK_IMAGEMAGICK_BINARY;
			} else {
				global $image_magick_path;
				if (empty($image_magick_path)) gp_die("gp_resize_imagemagick(): No ImageMagick path; set global \$image_magick_path or GANGPLANK_IMAGEMAGICK_BINARY");
			}
		$cmd = "$image_magick_path -geometry $size -quality 75 \"$in_filename\" \"$out_filename\" 2>&1";
		// echo "$cmd<p>";
		$out = `$cmd`;
		if (! empty($out)) gp_die("gp_resize_imagemagick(): ImageMagick: Command failed: '$cmd' '$out'");
		return true;
	}
	
	function gp_strip_ws($thing) {
		return preg_replace("/[A-Z0-9_]+/", "", $thing);
	}
		
	// Escape JavaScript code $js for use in an XHTML attribute such as
	// <a href="#" onclick="...">
	function gp_escapeJs($js) {
		return htmlspecialchars($js);
	}
	
	// htmlspecialchars
	function gp_hsc($v) {
		return htmlspecialchars($v);
	}

	function gp_trimStr($str, $max_col_len = false) {
		if (!$max_col_len)
			$max_col_len = 50;
		if (strlen($str) > $max_col_len)
			return substr($str, 0, $max_col_len-3) . "&#8230;";
		else
			return $str;
	}
			
	function gp_f_text($name, $value = false, $width = 35) {
		global $$name;
		global $gp_f_js;
		
		if ($value == false) {
			if (isset($$name)) {
				$value = $$name;
			}
			else {
				$value = "";
			}
		}
		
		?>
		<input class="w w-text" type=text name="<? echo $name; ?>"
			   value="<? echo htmlspecialchars($value); ?>"
			   size=<? echo $width; ?> maxlength=200
			   <?= $gp_f_js; ?>>
		<?
		$gp_f_js = "";
	}

	function gp_f_hidden($name, $value = false) {
		global $$name;
		global $gp_f_js;
		
		if ($value == false) {
			if (isset($$name)) {
				$value = $$name;
			}
			else {
				$value = "";
			}
		}
		
		?>
		<input type=hidden name="<? echo $name; ?>"
			   value="<? echo htmlspecialchars($value); ?>"
			   <?= $gp_f_js; ?>>
		<?
		$gp_f_js = "";
	}

	function gp_f_textarea($name, $value = false) {
		global $$name;
		global $gp_f_js;
		
		if ($value == false) {
			if (isset($$name)) {
				$value = $$name;
			}
			else {
				$value = "";
			}
		}
		
		?>

		<textarea class="w w-textarea" name="<? echo $name; ?>" rows=7 cols=60
			<?= $gp_f_js; ?>><?
			echo htmlspecialchars($value);
		?></textarea>

		<?
		$gp_f_js = "";
	}

	$g_sel_name = "";
	$g_sel_value = "";
	
	function gp_f_sel_start($name, $value = false, $extra = "") {
		global $g_sel_name;
		global $g_sel_value;
		global $gp_f_js;
		
		if ($value == false) {	
			global $$name;
			
			if (isset($$name)) {
				$value = $$name;
			}
			else {
				$value = "";
			}
		}
		
		$g_sel_name = $name;
		$g_sel_value = $value;
		
		?>
		
		<select class="w w-select" name=<? echo $name; ?> default_value="<? echo $g_sel_value; ?>"
			<?= $extra; ?> <?= $gp_f_js; ?>>
		
		<?
		$gp_f_js = "";
	}
	
	function gp_f_sel_item($description, $value = "--") {
		global $g_sel_name;
		global $g_sel_value;
		
		if ($value == "--") {
			$value = $description;
		}

		if ($g_sel_value == $value) {
			$s = " selected ";
		}
		else {
			$s = "";
		}
		
		echo "<option $s value=\"$value\">$description</option>\n";
	}
	
	function gp_f_sel_end() {
		echo "</select>";
	}
	
	function gp_f_checkbox($name, $value, $cur_value = false) {
		global $$name;
		global $gp_f_js;
		
		if ($cur_value == false) {
			if (isset($$name)) {
				$cur_value = $$name;
			}
			else {
				$cur_value = "";
			}
		}
		
		if ($value == $cur_value) {
			$checked = " checked ";
		}
		else {
			$checked = "";
		}
		?>
		<input class="w w-checkbox" type=checkbox name="<?=$name;?>"
			value="<?=htmlspecialchars($value);?>" <?= $gp_f_js; ?>
			<?=$checked;?>>
		<?
		$gp_f_js = "";
	}

	function gp_f_radio($name, $value, $cur_value = false) {
		global $$name;
		global $gp_f_js;
		
		if ($cur_value == false) {
			if (isset($$name)) {
				$cur_value = $$name;
			}
			else {
				$cur_value = "";
			}
		}
		
		if ($value == $cur_value) {
			$checked = " checked ";
		}
		else {
			$checked = "";
		}
		?>
		<input class="w w-radio" type=radio name="<?=$name;?>"
			value="<?=htmlspecialchars($value);?>" <?= $gp_f_js; ?>
			<?=$checked;?>>
		<?
		$gp_f_js = "";
	}
	
	// Display a series of date dropdown boxes to enter a date.
	// $value must be in MySQL YYYY-MM-DD format.
	// Returns the components of the date in $name_y, $name_m, $name_d.
	function gp_f_date($name, $value)
	{
		global $gp_f_js;
		$x = $gp_f_js;
		
		if (empty($value)) {
			$mon = "0";
			$day = "0";
			$year = "0";
		}
		else {
			$parts = explode(" ", $value);
			if (count($parts) == 1) {
				$parts = explode("-", $value);
				$year = $parts[0];
				$mon = $parts[1];
				$day = $parts[2];
			}
			else {
				$date = $parts[0];
				$time = $parts[1];
				$parts = explode("-", $date);
				$year = $parts[0];
				$mon = $parts[1];
				$day = $parts[2];
			}
		}
		
		gp_f_sel_start($name . "_m", $mon);
		gp_f_sel_item("--", "0");
		for ($i = 1; $i <= 12; $i++) {
			$tmp = date("Y")."-".sprintf("%02d", $i)."-01";
			gp_f_sel_item(date("M", strtotime($tmp)), $i);
		}
		gp_f_sel_end();

		$gp_f_js = $x;
		gp_f_sel_start($name . "_d", $day);
		gp_f_sel_item("--", "0");
		for ($i = 1; $i <= 31; $i++) 
			gp_f_sel_item($i);
		gp_f_sel_end();

		$gp_f_js = $x;
		gp_f_sel_start($name . "_y", $year);
		gp_f_sel_item("--", "0");
		for ($i = 1900; $i <= date("Y")+3; $i++) 
			gp_f_sel_item($i);
		gp_f_sel_end();
	}
	
	function gp_f_date_reassemble($name)
	{
		$d = $_REQUEST["${name}_y"] . "-" .
			$_REQUEST["${name}_m"] . "-" .
			$_REQUEST["${name}_d"];
		$_REQUEST["$name"] = $d;
		$_GET[$name] = $d;
		return $d;
	}
	
	// Display a series of date/time dropdown boxes to enter a date.
	// $value must be in MySQL YYYY-MM-DD HH:MM:SS format.
	// Returns the components of the date in $name_y, $name_m, $name_d
	// and "F_DATE_TIME" in $name
	function gp_f_date_time($name, $value)
	{
		global $gp_f_js;
		
		$x = $gp_f_js;
		?>
		<input type="hidden" name="<?= $name; ?>"
			value="F_DATE_TIME">
		<?
		
		
		if (empty($value)) {
			$mon = "0";
			$day = "0";
			$year = "0";
			$hour = "0";
			$min = "0";
			$sec = "0";
		}
		else {
			$parts = explode(" ", $value);
			if (count($parts) == 1) {
				$parts = explode("-", $value);
				$year = $parts[0];
				$mon = $parts[1];
				$day = $parts[2];
				$hour = 0;
				$min = 0;
				$sec = 0;
			}
			else {
				$date = $parts[0];
				$time = $parts[1];
				$parts = explode("-", $date);
				$year = $parts[0];
				$mon = $parts[1];
				$day = $parts[2];
				$parts = explode(":", $time);
				$hour = $parts[0];
				$min = $parts[1];
				$sec = $parts[2];
			}
		}
		
		gp_f_sel_start($name . "_m", $mon);
		gp_f_sel_item("--", "0");
		for ($i = 1; $i <= 12; $i++) {
			$tmp = date("Y")."-".sprintf("%02d", $i)."-01";
			gp_f_sel_item(date("M", strtotime($tmp)), $i);
		}
		gp_f_sel_end();

		$gp_f_js = $x;
		gp_f_sel_start($name . "_d", $day);
		gp_f_sel_item("--", "0");
		for ($i = 1; $i <= 31; $i++) 
			gp_f_sel_item($i);
		gp_f_sel_end();

		$gp_f_js = $x;
		gp_f_sel_start($name . "_y", $year);
		gp_f_sel_item("--", "0");
		for ($i = 1900; $i <= date("Y")+3; $i++) 
			gp_f_sel_item($i);
		gp_f_sel_end();

		$gp_f_js = $x;
		gp_f_sel_start($name . "_h", $hour);
		gp_f_sel_item("--", "0");
		for ($i = 0; $i <= 24; $i++) 
			gp_f_sel_item($i);
		gp_f_sel_end();
		
		$gp_f_js = $x;
		gp_f_sel_start($name . "_i", $min);
		gp_f_sel_item("--", "0");
		for ($i = 0; $i <= 60; $i++) 
			gp_f_sel_item($i);
		gp_f_sel_end();
	}

	function gp_f_date_time_reassemble($name)
	{
		$d = $_REQUEST["${name}_y"] . "-" .
			$_REQUEST["${name}_m"] . "-" .
			$_REQUEST["${name}_d"] . " " .
			$_REQUEST["${name}_h"] . ":" .
			$_REQUEST["${name}_i"];
		$_REQUEST["$name"] = $d;
		$_GET[$name] = $d;
		$_POST[$name] = $d;
		return $d;
	}
	
?>
