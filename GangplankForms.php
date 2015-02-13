<?
	//
	// Stuff to render forms
	//

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

