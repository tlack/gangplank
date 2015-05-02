<?
	//
	// GANGPLANK BASE CLASS
	//
	// Defines fundamental functionality for dealing with the underlying
	// data structure.
	//
	class Gangplank {

		// Constructor. $singular and $plural tell us how to refer to the
		// data. For example, "gallery" as a singular and "galleries" as
		// the plural. We use these terms to base certain names and labels
		// on.
		function Gangplank($singular, $plural, $data_source = false) {
			$this->singular = $singular;
			$this->plural = $plural;
			
			$this->singular_ws = preg_replace("/\s+/", "_", $singular);
			$this->plural_ws = preg_replace("/\s+/", "_", $plural);
			
			if (! $data_source)
				$this->data_source = gp_names_to_table_name($singular, $plural);
			else
				$this->data_source = $data_source;

			$this->joins = array();
			$this->where = "1=1";
			$this->is_simple_source = true;
			
			if (!isset($this->virtual_column_types))
				$this->virtual_column_types = array();
			
			$this->order_by = "";
			
			$this->primary_key = false;
			
			$this->cols = false;
		}
		
		// --[ Miscellaneous Helper Functions ]--
		
		// Convert a SQL column name into something similar to
		// pretty english. 
		//
		// Example:
		// last_update_dt -> Last Update Date
		// image_url -> Image Web URL
		// section_key -> Section #
		function sanitizeColName($name) {
			$name = str_replace("_", " ", $name);
			$name = ucwords($name);
			$name = str_replace("Dt", "Date", $name);
			$name = str_replace(" Key", " #", $name);
			$name = str_replace("Url", "Web URL", $name);
			return $name;
		}
		
		// --[ Setting up the database relationship ]--
		
		// Set the table that we'll pull data from.
		function setDataSource($table) {
			$this->data_source = $table;
		}
		
		function setSelect($select) {
			$this->select = $select;
			$this->hideAllColumns();
			$select = str_replace(" ", "", $select);
			$aa = explode(",", $select);
			$this->showColumns($aa);
		}

		function addJoin($table, $join_clause, $join_type = "inner", $foreign_cols_to_import = false) {
			if (empty($join_type)) $join_type = "inner";
			$join_type = strtolower($join_type);
			if ($join_type != "inner" &&
				$join_type != "left" &&
				$join_type != "right")
				return gp_die("addJoin(): Invalid join type \"$join_type\"");
				
			if ($foreign_cols_to_import !== false) {
				if (is_string($foreign_cols_to_import) &&
						gp_valid_col_name($foreign_cols_to_import))
					$foreign_cols_to_import = array($foreign_cols_to_import);
				
				if (!is_array($foreign_cols_to_import)) 
					return gp_die("addJoin(): Invalid foreign cols \"$foreign_cols_to_import\"");
			}
			
			$this->joins[] = array($table, $join_clause, $join_type, $foreign_cols_to_import);
			
			// Invalidate the columns cache ($this->cols) so that future calls to 
			// $this->loadColumns() will pull up info about our new join table
			$this->cols = false;
		}
		
		function setWhere($where) {
			$this->where = $where;
		}
		
		function setOrderBy($order_by) {
			$this->order_by = $order_by;
		}
		
 		function getFromClause() {
			$qs = "";
			$qs = "$this->data_source";
			foreach ($this->joins as $j) {
				list($table, $clause, $type) = $j;
				$qs .= " $type join $table on ($clause) ";
			}
			return $qs;
		}
		
		function getValueList($col) {
			if (preg_match("/^enum\((.*)\)$/", $col["Type"], $a)) {
				$terms = explode(",", $a[1]);
				$a = array();
				foreach ($terms as $t) {
					if (substr($t, 0, 1) == "'") 
						$t = substr($t, 1);
					if (substr($t, -1) == "'") 
						$t = substr($t, 0, -1);
					$t = str_replace("''", "'", $t);
					$a[] = $t;
				}
				return $a;
			} else {
				return false;
			}
		}
		
		function getNativeType($col) {
			if (preg_match("/^is_.*/", $col["Field"]) || 
					preg_match("/^has_.*/", $col["Field"]) ||
					preg_match("/^use_.*/", $col["Field"]) ||
					$col["Type"] == "bit" ||
					$col["Type"] == "tinyint(1)" ||
					$col["Type"] == "tinyint(4)") {
				return "boolean";
			} else if ($col["Type"] == "date") {
				return "date";
			} else if ($col["Type"] == "datetime") {
				return "datetime";
			} else if ($col["Type"] == "text" ||
				$col["Type"] == "mediumtext" ||
				$col["Type"] == "blob") {
				return "longstring";
			} else if (preg_match("/^enum\((.*)\)$/", $col["Type"], $a)) {
				return "enum";
			} else if (preg_match("/.*price$/", $col["Field"], $a)) {
				return "money";
			} else {			
				return "string";
			}			
		}

		function getColumnRepr($col, $row, $allow_virtual_columns = 1) {
			// handle virtual columns by returning the result of the call directly
			if ($allow_virtual_columns && $col["is_virtual"]) {
				return call_user_func(array(&$this, "renderVirtual" . $col["type"]), $row, $col);
			}
			
			$type = $col["native_type"];
			$col_name = $col["name"];
			
			if ($type == "boolean") {
				return $row[$col_name] ? "Yes" : "No";
			} 
			
			else if ($type == "date") {
				return gp_format_date($row[$col_name]);
			}

			else if ($type == "datetime") {
				return gp_format_datetime($row[$col_name]);
			}
			
			else if ($type == "money") {
				return gp_format_money($row[$col_name]);
			}
			
			else if ($col_name == "password") {
				return str_pad("", strlen($row[$col_name]), "*");
			}
			
			else if ($type == "string" && 
					preg_match("/.*_url/", $col_name) &&
					preg_match("/.*(jpg|gif|png)/i", $row[$col_name])) {
				return "<img src=\"$row[$col_name]\">";
			}

			else if ($type == "string" && 
					(preg_match("/^email$/i", $col_name) ||
					 preg_match("/^email_address$/i", $col_name))) {
				return "<a href=\"mailto:$row[$col_name]\">$row[$col_name]</a>";
			}
			
			// Everything else
			else {
				return gp_trimStr(htmlentities($row[$col_name]));
			}
		}
		
		function loadColumns() {
			if (empty($this->data_source)) gp_die("loadColumns(): no data source");
			if (! $this->is_simple_source) gp_die("loadColumns(): only simple sources");
			
			if ($this->cols !== false) 
				return $this->cols;
			
			$sources = array();
			$sources[] = array($this->data_source, false);
			
			foreach ($this->joins as $join) {
				$sources[] = array($join[0], $join[3]);
			}
			
			foreach ($sources as $source) {
				$table = $source[0];
				$desired = $source[1];
				$cols = gp_all_rows("describe $table");
				foreach ($cols as $col) {
					$name = $col["Field"];
					
					// if we've selected to only import certain columns,
					// and this column is not one of them, continue..
					if (is_array($desired) && !in_array($name, $desired))
						continue;
					
					if ($col["Key"] == "PRI") 
						$this->primary_key = $name;
					
					$this->cols["$name"] = array(
						"name" => $name, 
						"is_primary_key" => $col["Key"] == "PRI" ? true : false,
						"type" => $col["Type"],
						"native_type" => $this->getNativeType($col),
						"value_list" => $this->getValueList($col),
						"extra" => $col["Extra"], 
						"label" => $this->sanitizeColName($name),
						"visible" => true,
						"is_virtual" => false,
						"virtual_data" => false,
						"sort_key" => count($this->cols) + 1,
					);
				}
			}
		}
		
		function columns() {
			$this->loadColumns();
			$a = $this->cols;
			$f = create_function('$a,$b', '$as = $a["sort_key"]; $bs = $b["sort_key"]; return $as == $bs ? 0 : $as < $bs ? -1 : 1;');
			uasort($a, $f);
			return $a;
		}
		
		function visibleColumns() {
			$this->loadColumns();
			$find_vis = create_function('$a', 'return $a["visible"];');
			$a = array_filter($this->cols, $find_vis);
			$f = create_function('$a,$b', '$as = $a["sort_key"]; $bs = $b["sort_key"]; return $as == $bs ? 0 : $as < $bs ? -1 : 1;');
			usort($a, $f);
			return $a;			
		}	
		
		//
		// Create a "virtual column." These have their own custom made behavior
		// that works outside of the usual type-guessing mechanism. $type refers
		// to any of the predefined virtual column types. $data is a set of configurable
		// parameters that controls how the virtual column will work - they are 
		// type-specific. $order_by_clause allows you to specify an alternate order by
		// clause to the database when the user sorts by the virtual column
		//
		function addVirtualColumn($name, $type, $data = array(), $order_by_clause = false) {
			if (empty($name) || empty($type)) 
				gp_die("addVirtualColumn(): first arg (name) or second arg (type) are blank");
				
			if (! in_array($type, $this->virtual_column_types)) 
				gp_die("addVirtualColumn($name): '$type' is not a defined virtual column type.");
			
			$error_text = "";
			if (method_exists($this, "validateVirtual${type}Data"))
				if (! call_user_func_array(array(&$this, "validateVirtual${type}Data"), array(&$data, &$error_text)))
					gp_die("addVirtualColumn($name): invalid virtual data " . $error_text);
			
			$this->loadColumns();
			
			$cols = $this->visibleColumns();
			
			$this->cols[$name] = array(
				"name" => $name, 
				"type" => $type,
				"native_type" => $type,
				"value_list" => false,
				"extra" => false,
				"label" => $this->sanitizeColName($name),
				"visible" => true,
				"is_virtual" => true,
				"virtual_data" => $data,
				"order_by_clause" => $order_by_clause,
				"sort_key" => count($cols) + 1
			);
			
		}

		// Hide a column from display or editing; call as 
		// $x->hideColumns("col_a", "col_b") or pass an array.		
		function hideColumns() {
			// load the data at the outset
			$this->loadColumns();
			
			$args = func_get_args();

			// if one arg is specified and it looks like a comma-separated
			// list of values, we'll split and call.
			//
			if (count($args) == 1 &&
				is_string($args[0]) &&
				preg_match("/,/", $args[0]) ) {
				$select = $args[0];
				$select = str_replace(" ", "", $select);
				$aa = explode(",", $select);
				$this->hideColumns($aa);
			}
			
			foreach ($args as $arg) {
				if (is_array($arg))
					foreach ($arg as $a)
						$this->hideColumns($a);
				else
					if (isset($this->cols[$arg]))
						$this->cols[$arg]["visible"] = false;
			}
		}
		
		function hideColumn() {
			$x = func_get_args();
			call_user_func_array(array(&$this, "hideColumns"), $x);
		}	

		// Hides all columns.  Easily display only a few columns by 
		// calling this, then showColumns.  Do not pass anything.
		function hideAllColumns() {
			$this->loadColumns();

			$my_cols = $this->cols;
			foreach ($my_cols as $my_col) {
				$this->cols[$my_col["name"]]["visible"] = false;
			}
		}

		// Show a column during display or editing; call as 
		// $x->hideColumns("col_a", "col_b") or pass an array.		
		function showColumns() {
			// load the data at the outset
			$this->loadColumns();
			
			$args = func_get_args();

			// if one arg is specified and it looks like a comma-separated
			// list of values, we'll split and call.
			//
			if (count($args) == 1 &&
				is_string($args[0]) &&
				preg_match("/,/", $args[0]) ) {
				$select = str_replace(" ", "", $args[0]);
				$aa = explode(",", $select);
				$this->showColumns($aa);
			}
			
			foreach ($args as $arg) {
				if (is_array($arg))
					foreach ($arg as $a)
						$this->showColumns($a);
				else
					if (isset($this->cols[$arg])) 
						$this->cols[$arg]["visible"] = true;
			}
		}

		function showColumn() {
			$x = func_get_args();
			call_user_func(array(&$this, "showColumns"), $x);
		}	

		// Show all columns.  Easily display only a few columns by 
		// calling this, then showColumns.  Do not pass anything.
		function showAllColumns() {
			$this->loadColumns();

			$my_cols = $this->cols;
			foreach ($my_cols as $my_col) {
				$this->cols[$my_col["name"]]["visible"] = true;
			}
		}
		
		// Set one or more column properties by way of an array.
		function setColumnProperties($name, $properties) {
			$this->loadColumns();
			
			if (! isset($this->cols[$name])) 
				return gp_die("setColumnProperties($name): unknown column '$name'");
			
			if (! is_array($properties))
				return gp_die("setColumnProperties($name): properties must be an array such as array('prop' => 'value', 'prop2' => 'value2')");
			
			$aliases = array(
				"helptext" => "help_text",
				"onchange" => "on_change",
				"onclick" => "on_click" ,
				"onkeydown" => "on_key_down",
				"on_keydown" => "on_key_down",
				"onkeyup" => "on_key_up",
				"on_keyup" => "on_key_up"
			);
			
			foreach ($properties as $k=>$v) {
				if (is_numeric($k))
					return gp_die("setColumnProperties($name): invalid property name '$k'");
				if (isset($aliases[$k]))
					$k = $aliases[$k];
				$this->cols[$name][$k] = $v;
			}
		}
		
		// Set the labels for an individual column.
		// Call as $x->setColumnLabels("tname", "Tag Name", "xref", "Cross Reference");
		// or $x->setColumnLabels(array("tname" => "Tag Name") .. );
		function setColumnLabels() {
			$args = func_get_args();
			for ($i = 0; $i < count($args); $i+=2) {
				$arg = $args[$i];
				$arg2 = $args[$i + 1];
				if (is_array($arg))
					foreach ($arg as $col => $name)
						$this->setColumnLabels($col, $name);
				else
					$this->setColumnLabel($arg, $arg2);
			}
		}
		
		function setColumnLabel($col, $val) {
			if (isset($this->cols[$col])) 
				$this->cols[$col]["label"] = $val;
		}
		
		// Call like $x->setColumnPositions("user_key,user_name,age") or $x->setColumnPositions("user_key", 1);
		function setColumnOrder() {
			$this->loadColumns();
			
			$args = func_get_args();
			
			// an even number of arguments: iterate over each and reposition.
			if (count($args) == 2) {
				$col = $args[0];
				$pos = $args[1];
				$start_changing = false;
				
				$a = $this->columns();
				$keys = array_keys($a);
				for ($i = 0; $i < count($keys); $i++) {
					$k = $keys[$i];
					if (!$start_changing && $a[$k]["sort_key"] == $pos) {
						$this->cols[$col]["sort_key"] = $pos;
						$this->cols[$k]["sort_key"] = $pos++;
						$start_changing = true;
					} 
					else 
						if ($start_changing) {
							$this->cols[$k]["sort_key"] = $pos++;
						}
							
				}
			} 
			// one argument: a comma-separated list of columns, we'll sort in that order.
			else {
				$seen = array();
				$str = $args[0];
				$str = str_replace(" ", "", $str);
				$parts = explode(",", $str);
				$n = 0;
				foreach ($parts as $name) {
					if (! isset($this->cols[$name])) 
						gp_die("setColumnPositions(): unknown column '$name'");
					
					$this->cols[$name]["sort_key"] = $n++;
					$seen[$name] = $name;
				}
				
				// if we didn't set the position for any column, let's assign them incrementally.
				foreach ($this->cols as $name => $info) {
					if (! isset($seen[$name])) {
						$this->cols[$name]["sort_key"] = $n++;
						$seen[$name] = $name;
					}
				}
			}
		}

		function moveColumnBefore($col_to_move, $move_to_before_col) {
			$sort_key = 0;
			
			$cols = $this->columns();
			foreach ($cols as $col_name => $col_data) {
				// if we won't be explicitly repositioning the column
				// we're moving at this stage; we'll swap its position
				// with the others, after this.
				if ($col_name == $col_to_move) 
					continue;
				if ($col_name == $move_to_before_col) {
					$this->cols[$col_to_move]["sort_key"] = $sort_key;
					$sort_key++;
					$this->cols[$move_to_before_col]["sort_key"] = $sort_key;
				} else {
					$this->cols[$col_name]["sort_key"] = $sort_key;
				}
				
				$sort_key++;
			}
		}

		function moveColumnAfter($col_to_move, $move_to_after_col) {
			$sort_key = 0;
			
			$cols = $this->columns();
			foreach ($cols as $col_name => $col_data) {
				// if we won't be explicitly repositioning the column
				// we're moving at this stage; we'll swap its position
				// with the others, after this.
				if ($col_name == $col_to_move) 
					continue;
				if ($col_name == $move_to_after_col) {
					$this->cols[$move_to_after_col]["sort_key"] = $sort_key;
					$sort_key++;
					$this->cols[$col_to_move]["sort_key"] = $sort_key;
				} else {
					$this->cols[$col_name]["sort_key"] = $sort_key;
				}
				
				$sort_key++;
			}
		}

		// Move column named $col_to_move to the very top of the form/list
		function moveColumnToTop($col_to_move) {
			$lowest_sort_key = 999;
			$cols = $this->columns();
			// how i long for a really smooth functional min() function in PHP
			foreach ($cols as $col_name => $col_data) {
				if ($col_data['sort_key'] < $lowest_sort_key)
					$lowest_sort_key = $col_data['sort_key'];
			}
			// taking advantage of the fact that sort keys can be negative:
			$this->cols[$col_to_move]['sort_key'] = $lowest_sort_key-1;
		}
		
	}
?>
