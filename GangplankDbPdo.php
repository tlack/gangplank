<?
	if(!function_exists('gp_die')) require(dirname(__FILE__).'/GangplankMisc.php');

	$gp_db_con = false;
	
	function gp_connect() {
		global $gp_db_con;
		
		if ($gp_db_con) {
			return $gp_db_con;
		}
		
		// echo "Connecting " . GANGPLANK_DB_HOST ."/". GANGPLANK_DB_USER ."/" . GANGPLANK_DB_PASS;
		try {
			$constr='mysql:host='.GANGPLANK_DB_HOST.';dbname='.GANGPLANK_DB_DB.';charset=utf8';
			$c = new PDO($constr,GANGPLANK_DB_USER,GANGPLANK_DB_PASS);
			$c->query("set @sqlmode='mysql40'");
		} catch (PDOException $e) { 
			gp_die("gp_connect(): Error connecting: #$code: ".$e);
			return false;
		}
		$gp_db_con = $c;
		return $c;
	}
	
	function gp_close($con) {
		/* no-op on PDO? */
	}
	
	function gp_dberror($qs) {
		global $gp_db_con;
		
		$code = $gp_db_con->errorCode();
		$error = $gp_db_con->errorInfo();
		if ($error == '') {
			gp_die("<b>No error message available.</b> (source: $qs)");
		} else {
			gp_die("<b>Error in query:</b> #$code: ".json_encode($error)." (source: $qs)");
		}
	}
	
	function gp_escapeSql($data) {
		global $gp_db_con;
		return substr($gp_db_con->quote($data),1,-1);
	}

	function gp_escapeSqlMaybe($data) {
		return !get_magic_quotes_gpc() ? gp_escapeSql($data) : $data;
	}
	
	function gp_query($qs, $c)
	{
		$qs = preg_replace("/\/\*autoid:([A-Za-z0-9_]+)\*\//", "null", $qs);
		$qs = preg_replace("'/\/\*now\*\//'", "now()", $qs);
		$qs = preg_replace("/\/\*now\*\//", "now()", $qs);
    $qs = preg_replace('/\binterval\b/', '`interval`', $qs);

		gp_debug("<b>gp_query('$qs', \$c)</b><p>");
		$res = $c->query($qs);
		if (!$res) {
			gp_dberror($qs, $c);
		}
		else {
			return $res;
		}
	}
	
	function gp_num_rows($res)
	{
		return $res->rowCount();
	}

	function gp_affected_rows($stmt)
	{
		global $gp_db_con;
	
		return $res->rowCount();
	}
	
	function gp_fetch($res)
	{
		return $res->fetch(PDO::FETCH_ASSOC);
	}
		
	function gp_insert_id($res = false, $pass = false)
	{
		global $gp_db_con;
		
		return $gp_db_con->lastInsertId();
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
		if ($res) {
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
		if ($res) {
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
		if ($res) {
			return gp_fetch($res);
		}
		else {
			return false;
		}
	}

	define('GP_RAW_VALUE_START', "__{{");
	define('GP_RAW_VALUE_END', "}}__");

	function gp_insert_row($table, $values) {
		$c = gp_connect();
    $keys = array();
    $vals = array();

    // filter values we want; too complex for array_map/array_filter
    foreach ($values as $k=>$v) {
      if (is_string($k)) {
        $keys[] = gp_escapeSql($k);
        $vals[] = gp_escapeSql((string)$v);
      }
    }
    $keys_sql = join(",", $keys);
    $vals_sql = join("','", $vals);
    $qs = "
      insert into $table
        ($keys_sql)
      values
        ('$vals_sql')
      ";

		// allow for literal non-quoted values:
    $qs = str_replace("'" . GP_RAW_VALUE_START, "", $qs);
    $qs = str_replace(GP_RAW_VALUE_END . "'", "", $qs);

		gp_update($qs);
		return gp_insert_id();
  }

?>
