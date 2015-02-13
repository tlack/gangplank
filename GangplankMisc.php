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

	// Return all values of $col_name from each item in $list as a new array
	function gp_pluck($list, $col_name) {
		$out = array();
		foreach ($list as $v) {
			$out[] = $v[$col_name];
		}
		return $out;
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

	function gp_is_ajax_request() {
		if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']))
			return true;
		else
			return false;
	}

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
			
?>
