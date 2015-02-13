<?
	require_once(dirname(__FILE__) . '/gangplank.php');
	
	//  
	// The NewRecordGangplank Class
	//
	// A simple class that creates a new, empty record and forwards
	// the user to the editing page.
	//
	// Use setColumnValue() to specify keys.
	//
	class NewRecordPlank extends Gangplank {
	
		function NewRecordPlank($singular, $plural, $data_source='') {
			$this->singular_ws = preg_replace('/\s+/', '_', $singular);
			$this->plural_ws = preg_replace('/\s+/', '_', $plural);
			
			$this->primary_key_value = false;
			$this->edit_link_url = '';
			$this->Gangplank($singular, $plural, $data_source);
			$this->populateColumnValuesFromUrl();
		}

		// Allow you to pass in URL args like ?gp_new_random_key=123 and have
		// random_key in your table set to 123 when the New button is hit (this works
		// because URL parameters are maintained as you drift around the Gangplank
		// list/edit/new pages). This saves you from having to do a lot of work to
		// do $g->new_record->setColumnValue() all over the place. Instead, just
		// pass those values in via the URL when you direct users to that autoplank
		// page
		function populateColumnValuesFromUrl() {
			$this->loadColumns();
			foreach ($this->cols as $name => $col) {
				if (!empty($_REQUEST['gp_auto_limit_'.$name])) {
					// picking up value from url..
					$this->cols[$name]['value'] = $_REQUEST['gp_auto_limit_'.$name];
					$this->cols[$name]['set_manually'] = true;
				}
			}
		}
		
		function setColumnValue($col, $val) {
			$this->loadColumns();
			if (! isset($this->cols[$col])) gp_die("Could not set value for column '$col' because it is not defined.");
			$this->cols[$col]['value'] = $val;
			$this->cols[$col]['set_manually'] = true;
		}

		function setEditLink($url) {
			// You may use {KEY} to refer to the primary key value for this row.
			
			if (empty($url) || !is_string($url)) 
				gp_die('setEditLink(): only argument should be a string URL');
			
			$this->edit_link_url = $url;
		}
		
		function handleRequest() {

			$this->loadColumns();
			$all_cols = $this->columns();
			
			if (empty($this->edit_link_url))
				$this->edit_link_url = "{$this->singular_ws}_edit." . GANGPLANK_EXT . "?$this->primary_key={KEY}";
			
			if (empty($this->primary_key))
				gp_die('handleRequest(): could not determine primary key');
			
			// Determine extra fields that require values.
			$extra_cols = '';
			$extra_vals = '';

			foreach ($this->cols as $col) {
				if ($col['name'] == 'sort_key') {
					// go through a mission to find a proper sort key
					
					// build a where clause first in the case of pre-defined keys.
					$where = array();
					foreach ($this->cols as $sub_col) {
						if (isset($sub_col['value'])) 
							$where[] = " $sub_col[name] = '".gp_escapeSql($sub_col['value'])."' ";
					}
					if (empty($where))
						$where_sql = ' 1 = 1 ';
					else
						$where_sql = join(' and ', $where);
					
					$qs = "
						select max(sort_key) as max
						  from $this->data_source
						 where $where_sql ";
					$highest = gp_one_row($qs);
					if ($highest && isset($highest['max'])) {
						$new_sort_key = $highest['max']+1;
						$extra_cols .= ", $col[name]";
						$extra_vals .= ", $new_sort_key";
					}
				}
				if (isset($col['value'])) {
					$extra_cols .= ", $col[name]";
					$extra_vals .= ", '" . gp_escapeSql($col["value"]) . "'";
				}
			}
			
			if (! empty($all_cols['add_dt'])) {
				$extra_cols .= ', add_dt';
				$extra_vals .= ', now()';
			}

			if (! empty($all_cols['create_dt'])) {
				$extra_cols .= ', create_dt';
				$extra_vals .= ', now()';
			}

			if (! empty($all_cols['creation_dt'])) {
				$extra_cols .= ', creation_dt';
				$extra_vals .= ', now()';
			}

			if (! empty($all_cols['update_dt'])) {
				$extra_cols .= ', update_dt';
				$extra_vals .= ', now()';
			}
			
			$qs = "
				insert into
					$this->data_source
				($this->primary_key $extra_cols)
				values
				(/*autoid:$this->data_source*/ $extra_vals)";
			gp_update($qs);
			$new_pk = gp_insert_id(false);
			
			$data = gp_one_row("
				select *
				  from $this->data_source
				 where $this->primary_key = $new_pk ");
			if (!$data)
				gp_die("Could not load data for primary key $new_pk");

			$link = gp_interpUrl($this->edit_link_url, $data);
			$link = str_replace('{' . 'KEY' .'}', urlencode($new_pk), $link);
			$link = str_replace('{' . 'MYURL' . '}', urlencode(gp_my_url()), $link);
			gp_goto($link);
		}
	}
?>
