<?
	$gp_db_con = false;
	
	function gp_connect() {
		global $gp_db_con;
		
		if ($gp_db_con) {
			return $gp_db_con;
		}
		
		// echo "Connecting " . GANGPLANK_DB_HOST ."/". GANGPLANK_DB_USER ."/" . GANGPLANK_DB_PASS;
		$c = mysql_pconnect(GANGPLANK_DB_HOST, GANGPLANK_DB_USER, GANGPLANK_DB_PASS);
		if ($c == false) {
			$code = mysql_errno();
			$error = mysql_error();
			gp_die("gp_connect(): Error connecting: #$code: $error");
			return false;
		}
		mysql_select_db(GANGPLANK_DB_DB, $c);
		mysql_query("SET NAMES UTF8");
		$gp_db_con = $c;
		return $c;
	}
	
	function gp_close($con) {
		mysql_close($con);
	}
	
	function gp_dberror($qs) {
		global $gp_db_con;
		
		$code = mysql_errno();
		$error = mysql_error($gp_db_con);
		if ($error == '') {
			gp_die("<b>No error message available.</b> (source: $qs)");
		} else {
			gp_die("<b>Error in query:</b> #$code: $error (source: $qs)");
		}
	}
	
	function gp_escapeSql($data) {
		return mysql_escape_string($data);
	}

	function gp_escapeSqlMaybe($data) {
		return !get_magic_quotes_gpc() ? mysql_escape_string($data) : $data;
	}
	
	function gp_query($qs, $c)
	{
		$qs = preg_replace("/\/\*autoid:([A-Za-z0-9_]+)\*\//", "null", $qs);
		$qs = preg_replace("'/\/\*now\*\//'", "now()", $qs);
		$qs = preg_replace("/\/\*now\*\//", "now()", $qs);
        $qs = preg_replace('/\binterval\b/', '`interval`', $qs);

		gp_debug("<b>gp_query('$qs', \$c)</b><p>");

		$res = @mysql_query($qs, $c);
		if (!$res) {
			gp_dberror($qs, $c);
		}
		else {
			return $res;
		}
	}
	
	function gp_num_rows($res)
	{
		return mysql_num_rows($res);
	}

	function gp_affected_rows($stmt)
	{
		global $gp_db_con;
	
		return mysql_affected_rows($gp_db_con);		
	}
	
	function gp_fetch($res)
	{
		return mysql_fetch_array($res);
	}
		
	function gp_insert_id($res = false, $pass = false)
	{
		global $gp_db_con;
		
		return mysql_insert_id($gp_db_con);
	}
	
	function gp_dbdate()
	{
		return "now()";
	}

	//
	// Higher-level query functions
	//

	function gp_all_rows($qs)
	{
		$c = gp_connect();
		$res = gp_query($qs, $c);
		$rows = array();
		while ($a = gp_fetch($res)) {
			$rows[] = $a;
		}
		return $rows;
	}

	function gp_all_rows_map($qs, $func)
	{	
		$n = 0;
		$ret = array();
		$c = gp_connect();
		$res = gp_query($qs, $c);
		$rows = array();
		while ($a = gp_fetch($res)) {
			$val = call_user_func($func, $a, $n);
			$ret[] = $val;
		}
		return $ret;
	}
	
	function gp_all_rows_as_array($qs, $key_col, $val_col = "")
	{
		$c = gp_connect();
		$res = gp_query($qs, $c);
		$rows = array();
		while ($a = gp_fetch($res)) {
			if (empty($val_col)) {
				$rows[$a[$key_col]] = $a;
			}
			else {
				$rows[$a[$key_col]] = $a[$val_col];
			}
		}
		return $rows;
	}

	function gp_one_column($qs, $col)
	{
		$c = gp_connect();
		$res = gp_query($qs, $c);
		if (gettype($res) == "resource") {
			$a = gp_fetch($res);
			if (isset($a[$col])) {
				return $a[$col];
			}
			else {
				return false;
			}
		}
		else {
			return false;
		}
	}

	// Return one row of records from a query
	function gp_one_row($qs)
	{
		$c = gp_connect();
		$res = gp_query($qs, $c);
		if (gettype($res) == "resource") {
			return gp_fetch($res);
		}
		else {
			return false;
		}
	}

	//
	// Perform a database update: INSERT, UPDATE, REPLACE, DELETE, ALTER..
	//
	// Eventually these will go to another server automatically
	// for replication purposes.
	//
	function gp_update($qs)
	{
		$c = gp_connect();
		$res = gp_query($qs, $c);
		if (gettype($res) == "resource") {
			return gp_fetch($res);
		}
		else {
			return false;
		}
	}

?>
