<?
	require_once(dirname(__FILE__) . '/gangplank.php');
	
	// The ListGangplank Class
	// 
	// A powerful, paginated data grid.
	//
	class ListPlank extends Gangplank {
	
		function ListPlank($singular, $plural, $data_source='') {
			$this->singular_ws = preg_replace('/\s+/', '_', $singular);
			$this->plural_ws = preg_replace('/\s+/', '_', $plural);
			
			$this->html_id = $this->singular_ws . '-list';
			$this->html_class = 'gangplank-list';
			$this->caption = '';
			
			$this->sort_var = 'sort_' . $this->plural_ws;
			$this->default_order_by = '';
			$this->start_var = 'start_' . $this->plural_ws;

			$this->show_header = true;
			
			$this->buttons = array();
			$url = $this->singular_ws . '_new.' . GANGPLANK_EXT;
			if (defined('GANGPLANK_USE_ICONS') && GANGPLANK_USE_ICONS)
				$label = '<i class="fa fa-plus"></i> New ' . $singular;
			else
				$label = "Create new $singular";
			$this->addButton('add', array('label' => $label, 'url' => $url));

			$this->show_move_link = false;
			$this->move_link_url_up = '';
			$this->move_link_url_down = '';
			$this->move_link_label_up = 'Move Up';
			$this->move_link_label_down = 'Move Down';
			
			$this->show_edit_link = true;
			$this->edit_link_url = '';
			if (defined('GANGPLANK_USE_ICONS') && GANGPLANK_USE_ICONS)
				$this->edit_link_label = '<i class="fa fa-edit"></i> Edit';
			else
				$this->edit_link_label = 'Edit';
			$this->edit_extra_html = '';
			
			// stuff to support break rows 
			$this->use_break_rows = 0;
			$this->break_row_columns = array();
			$this->break_row_values = array();
			$this->break_row_callbacks = array();
			
			// functionality relating to group controls - delete checked, etc..
			$this->show_group_controls = true;
			$this->group_controls = array();
			if (defined('GANGPLANK_USE_ICONS') && GANGPLANK_USE_ICONS)
				$delete = '<i class="fa fa-trash-o"></i> Delete checked items';
			else
				$delete = 'Delete checked items';
			$this->group_controls['delete'] = 
				array('title' => $delete,
						  'callback' => array(&$this, 'handleDelete'));
			$this->delete_callback = false;

			$this->search_enabled = false;
			// list of fields to search if search is turned on
			$this->search_fields = array();
			
			$this->per_page = 100;
			$this->max_col_length = 50;
			$this->show_pagination = true;
			$this->results_cnt = false;
			$this->use_ajax = true;
			$this->amend_primary_key_column_names = true;
			$this->virtual_column_types[] = 'Callback';
			$this->virtual_column_types[] = 'ForeignKey';
			$this->Gangplank($singular, $plural, $data_source);
			$this->setWhereFromUrl();
		}

		function setWhereFromUrl() {

			// use existing where clasues, simply add more
			$clauses = array();
			if (!empty($this->where)) {
				$clauses[] = '('.$this->where.')';
			}

			$this->loadColumns();
			foreach ($this->cols as $name => $col) {
				if (!empty($_REQUEST['gp_auto_limit_'.$name]))
					$clauses[] = $name . " = '" . $_REQUEST['gp_auto_limit_'.$name] . "'";
			}

			$this->setWhere(join(' and ', $clauses));
		}
		
		function setHtmlId($html_id) {
			$this->html_id = $html_id;
		}
		
		function setHtmlClass($html_class) {
			$this->html_class = $html_class;
		}
		
		function setCaption($caption) {
			$this->caption = $caption;
		}
		
		function numCols() {
			$n = count($this->visibleColumns());
			if ($this->show_group_controls)
				$n++;
			if ($this->show_move_link)
				$n += 2;
			if ($this->show_edit_link)
				$n++;
			return $n;
		}
		
		function getData($primary_key) {
			// load column data so we know the primary key
			$this->loadColumns();
			$from = $this->data_source;
			$where = $this->where;
			$pri = $this->primary_key;
			$qs = "
				select *
					from $from
				 where $pri = '$primary_key' 
				 limit 1";
			return xone_row($qs);
		}
		
		// Set a callback to be called whenever the value of $column changes from what
		// it was in the previous rows. You better add an order by on this column or
		// chaos will ensue.
		function setBreakRow($column, $callback) {
			$this->loadColumns();
			if (empty($column) || empty($this->cols[$column]))
				gp_die('setBreakRow(): Break row column \"' . $column . '\" is unknown.');
			if (!is_callable($callback))
				gp_die('setBreakRow(): Callback is not callable');
			$this->use_break_rows++;
			$this->break_row_columns[$column] = $column;
			$this->break_row_values[$column] = md5(time());
			$this->break_row_callbacks[$column] = $callback;
		}
		
		function clearBreakRow($column) {
			if (in_array($column, $this->break_row_columns)) {
				$this->use_break_rows--;
				unset($this->break_row_columns[$column]);
				unset($this->break_row_values[$column]);
				unset($this->break_row_callbacks[$column]);
			}
		}
		
		// --[ Buttons ]--
		// 
		// These are buttons that are displayed below the list; usually these affect the entire
		// collection of records rather than specific checked ones
		
		function addButton($id, $options) {
			if (!is_array($options))
				gp_die("addButton($id): No options supplied", __FILE__, __LINE__);
			$options['id'] = $id;
			if (!isset($options['visible'])) //visible by default
				$options['visible'] = true;
			// check array keys
			$valid_keys = array('id', 'url', 'callback', 'label', 'visible', 'onclick');
			foreach ($options as $k=>$v)
				if (!in_array($k, $valid_keys))
					gp_die("addButton($id): invalid option '$k'", __FILE__, __LINE__);
			if (isset($options['callback']) && !is_callable($options['callback'])) 
				gp_die("addButton($id): Invalid callback '$options[callback]', not callable", __FILE__, __LINE__);
			if (!isset($options['url']) && !isset($options['callback']))
				gp_die("addButton($id): No url and no callback", __FILE__, __LINE__);
			$this->buttons[$id] = $options;
		}
		
		// --[ Group Controls ]--
		//
		// Group controls are options that are executed on checked rows. 
		// The most common example is the ability to check off and delete
		// many items at once.
		function addGroupControl($id, $title, $callback) {
			$this->group_controls[$id] = 
				array('title' => $title, 'callback' => $callback);
		}
		
		function removeGroupControl($id) {
			if (!isset($this->group_controls[$id]))
				gp_die('removeGroupControl(): unknown id "' . $id . '"');
			unset($this->group_controls[$id]);
		}
		
		function setDefaultOrderBy($col) {
			$this->default_order_by = $col;
		}
		
		function determineFinalOrderBy() {
			if (empty($this->order_by)) {
				if (!empty($_REQUEST[$this->sort_var]))
					$this->order_by = $_REQUEST[$this->sort_var];
				else 
					if (!empty($this->default_order_by)) 
						$this->order_by = $this->default_order_by;
			}
		}
		
		// Set the behavior of the Add link at the bottom of the form. 
		function setAddLink($show, $url = false, $label = false) {
			$this->buttons['add']['visible'] = $show;
			if ($url) 
				$this->buttons['add']['url'] = $url;
			if ($label) 
				$this->buttons['add']['label'] = $label;
		}
		
		// Set the behavior of the Edit link on each row. You may use {KEY} to
		// refer to the primary key value for this row.
		function setEditLink($show, $url = false, $label = false) {
			$this->show_edit_link = $show;
			if ($url) 
				$this->edit_link_url = $url;
			if ($label) 
				$this->edit_link_label = $label;
		}
		
		// Set some extra HTML that will be shown after the edit link on each
		// row. You can use URL variable interpolation; see gp_interpUrl()
		function setEditExtraHtml($extra_html) {
			$this->edit_extra_html = $extra_html;
		}
		
		function setDeleteCallback($cb) {
			$this->delete_callback = $cb;
		}
		
		function setPagination($bool) {
			$this->show_pagination = $bool;
		}
		
		function getResultsCount() {
			if ($this->results_cnt === false) {	
				$from = $this->getFromClause();
				$this->results_cnt = gp_one_column("
					select count(*) as cnt
					  from $from
					 where ($this->where)
					 ", 'cnt');
			}
			return $this->results_cnt;
		}
		
		function startIndex() {
			return empty($_REQUEST[$this->start_var]) ? 0 : $_REQUEST[$this->start_var];
		}
		
		function setPerPage($per_page) {
			$this->per_page = $per_page;
		}
		
		function perPage() {
			return $this->per_page;
		}

		function setSearchOptions($enabled, $search_fields = array()) {
			$this->search_enabled = $enabled;
			$this->search_fields = $search_fields;
		}

		function requestedSearchTerm() {
			if ($this->search_enabled &&
					!empty($_REQUEST[$this->plural_ws.'_search']))
				return $_REQUEST[$this->plural_ws.'_saerch'];
			else
				return '';
		}
		
		function renderHeader() {

			$html  = '';
			
			if ($this->use_ajax) 
				$html .= "<div id=\"{$this->html_id}-enclosure\">";

			if ($this->search_enabled) {
				$html .= "<input type=text id='{$this->html_id}-search' name='{$this->plural_ws}-search' onchange='{$this->plural_ws}_search()'>";
			}
		
			if ($this->show_group_controls) {
				// if we are going to be using group controls (checkboxes), we'll set a hidden var.
				$url = gp_my_url();
				$html .= "<form action=\"$url\" method=\"post\" name=\"$this->singular_ws\">";
				$field_name = $this->singular_ws . '_do_group';
				$html .= "<input type=\"hidden\" name=\"$field_name\" id=\"$field_name\" value=\"\">";
				$field_name = $this->singular_ws . '_do_button';
				$html .= "<input type=\"hidden\" name=\"$field_name\" id=\"$field_name\" value=\"\">";
			}
			
			$html .= "<table id=\"$this->html_id\" class=\"$this->html_class\">\n";
			if (!empty($this->caption))
				$html .= '<caption>' . htmlspecialchars($this->caption) . '</caption>';
			
			if (! $this->show_header)
				return $html;
			
			$html .= "<thead>\n";
			$html .= "<tr>\n";
			
			// should have already happened but let's be safe.
			$this->loadColumns();
			
			if ($this->show_group_controls) {
				$html .= '<th class="group">&nbsp;</th>';
			}

			$this->determineFinalOrderBy();
			
			foreach ($this->visibleColumns() as $col) {
				
				$name = htmlspecialchars($col["name"]);
				$label = htmlspecialchars($col["label"]);
				if ($this->amend_primary_key_column_names &&
						$col["is_primary_key"])
					$label = "#";
				
				$class = $name;
				
				if ($this->order_by == $name)
					$class .= " curorderby ";
				if ($this->show_move_link === false) {
					// Virtual columns are not sortable; only short the sortable column
					// headings/links on non-virtual columns..
					if (!$col["is_virtual"] ||
						  !empty($col["order_by_clause"])) {
						// allow column sorting
						$url = gp_add_url_arg(gp_my_url(), $this->sort_var, $col["name"]);
						if ($this->use_ajax) {
							$onclick = "onclick=\"return {$this->plural_ws}_reload('$url');\"";
						} else {
							$onclick = "";
						}
						$html .= "<th class=\"$class\"><a href=\"$url\" $onclick>$label</th>\n";
					} else {
						$html .= "<th class=\"$class\">$label</th>";
					}
				} else {
					// disable column sorting; sort key move up/down enabled
					$html .= "<th class=\"$class\">$label</th>\n";
				}
			}
			
			if ($this->show_edit_link) {
				$html .= '<th class="edit">&nbsp;</th>';
			}
			
			if ($this->show_move_link) {
				$html .= '<th>&nbsp;</th>';
				$html .= '<th>&nbsp;</th>';
			}
			
			$html .= "</tr>\n";

			$paginate = $this->show_pagination && ($this->getResultsCount() > $this->perPage());
			if ($paginate) {
				$pagination = $this->renderPagination();
				
				// only show the pagination results row if there is something worth showing
				if (!empty($pagination)) {
					$html .= "<tr><!-- pagination -->\n";
					$n = $this->numCols();
					$html .= "<th colspan=\"$n\" class=\"pagination\">\n";
					$html .= $pagination;
					$html .= "</th>\n";
					$html .= "</tr><!-- /pagination -->\n";
				}
			}

			$html .= "</thead>\n";
			
			return $html;
		}
		
		function renderPaginationNoResults() {
			return '';
		}
		
		function renderPageLink($page_num, $cur_page_num, $this_idx, $link_text = false) {
			if (! $link_text)
				$link_text = $page_num;
			
			$url = gp_add_url_arg(gp_my_url(), $this->start_var, $this_idx);
			$url = gp_add_url_arg($url, 'gp_fetch', $this->plural_ws);
			
			if ($page_num == $cur_page_num)
				$html = ' ' . $link_text . ' ';
			else {
				if ($this->use_ajax) 
					$onclick = "onclick=\"return {$this->plural_ws}_reload('$url');\"";
				else
					$onclick = '';
				$html = "<a href=\"$url\" class=\"page-link\" $onclick>$link_text</a> ";
			}
			return $html;
		}
		
		function renderPagination() { 
			$cnt = $this->getResultsCount();
			$start = $this->startIndex();
			$per = $this->perPage();	
			
			if ($cnt == 0)
				return $this->renderPaginationNoResults();
				
			if ($start == 0) 
				$cur_page_n = 1;
			else
				$cur_page_n = ceil($start / $per)+1;
			$last_page_num = ceil($cnt / $per);
			$first_page_shown = 99999;
			$last_page_shown = -1;
			
			$html = '';
			$links_shown = 0;
			
			for ($page_num = $cur_page_n - 5; 
				$page_num <= $cur_page_n + 5; 
				$page_num++) {
			
				$this_idx = ($page_num - 1) * $per;
				$should_show = 1;
				
				if ($page_num <= 0) {
					$should_show = 0;
				}
				
				// If there is more than one page of results,
				// and this start index would yield an invalid
				// result, do not show.
				if ($page_num > 1 && $this_idx >= $cnt) {
					$should_show = 0;
				}
				
				if ($should_show) {
					$first_page_shown = min($page_num, $first_page_shown);
					$last_page_shown = max($page_num, $last_page_shown);
					$html .= $this->renderPageLink($page_num, $cur_page_n, $this_idx);
					$links_shown++;
				}
			}

			// Show the leading "1 .. " if necessary.
			if ($cur_page_n != 1 && $first_page_shown > 1) {
				$html = $this->renderPageLink(1, $cur_page_n, 0) . ' &#8230; ' . $html;
			}
			
			// Show the leading " .. <max>" if necessary
			if ($cur_page_n != $last_page_num && $last_page_shown < $last_page_num) {
				$this_idx = ($last_page_num - 1) * $per;
				$html = $html . ' &#8230 ' . $this->renderPageLink($last_page_num, $cur_page_n, $this_idx);
			}
			
			$a = $start + 1;
			$b = min($start + $per, $cnt);
			$label = "Showing $a to $b of $cnt";
			if ($links_shown > 1) $label .= '; jump to ' . $html;
			
			$prev = '&laquo; Prev';
			if ($cur_page_n > 1) {
				$this_idx = ($cur_page_n - 2) * $per;
				$label = $this->renderPageLink($cur_page_n - 1, $cur_page_n, $this_idx, $prev) . ' ' . $label;
			} else {
				$label = "<span style=\"color:#ccc\">$prev</span> $label";
			}
			
			$next = 'Next &raquo;';
			if ($cur_page_n < $last_page_num) {
				$this_idx = ($cur_page_n + 1 - 1) * $per;
				$label = $label . ' ' . $this->renderPageLink($last_page_num, $cur_page_n, $this_idx, $next);
			} else {
				$label = "$label <span style=\"color:#ccc\">$next</span>";
			}
			
			return $label;
		}
		
		//
		// --[ Virtual Column Handlers ]------------------------------------------
		// 
		
		// ----[ "Callback" Virtual Columns
		
		// The supplied data for a Callback virtual column should be either a 
		// function name as a string, like "frobnicate_name", or an array that 
		// refers to a method, such as array($my_obj, "frobnicate_name").
		//
		// Please note that this callback will be called as
		// func($row, $col) where $row is the data for this record and $col
		// is the data for this column (which is slightly redundant but lets you
		// reuse one callback for multiple columns).
		function validateVirtualCallbackData($data, &$error_text) {
			if (empty($data)) {
				$error_text = 'Callback name blank';
				return false;
			}
			if (! is_callable($data)) {
				$error_text = 'Callback supplied is not callable';
				return false;
			}
			return true;
		}
		
		// A callback virtual column merely calls $virtual_data (it must be a function
		// name) and returns the results in an unmolested fashion.
		function renderVirtualCallback($row, $col) {
			return call_user_func($col['virtual_data'], $row, $col);
		}
		
		// ----[ "ForeignKey" Virtual Columns
		
		// Validate virtual data used for ListGangplank ForeignKey columns. The data must
		// be non-blank, an array, and have these indices:
		// 
		// * $data["foreign_table"] -> the table we'll be joining against
		// * $data["foreign_key"] -> the primary key in that table that we'll join on
		// * $data["foreign_desc"] -> the column that we'll display as an identifier of the row.
		// (this is usually a title or description field)
		//
		// Optionally, the data may also have a link_contents_to parameter, which is a URL
		// that the contents of this column will be linked to. In that URL, you can specify
		// {foreign_key} and {local_key} which will be replaced with the equivalent values.
		// For instance, section_edit.php?section_key={foreign_key}&from_page_key={local_key}
		//
		function validateVirtualForeignKeyData($data, &$error_text) {
			if (! is_array($data)) {
				$error_text = 'Data must be an array';
				return false;
			}
			if (empty($data['foreign_table'])) {
				$error_text = 'Missing "foreign_table" element';
				return false;
			}
			if (empty($data['foreign_key'])) {
				$error_text = 'Missing "foreign_key" element';
				return false;
			}
			if (empty($data['foreign_desc'])) {
				$error_text = 'Missing "foreign_desc" element';
				return false;
			}
			return true;
		}

		// Render a virtual column that refers to another table.
		//
		// See ListGangplank::validateVirtualForeignKeyData for documentation on
		// how to use this virtual column type.
		//
		function renderVirtualForeignKey($row, $col) {
			$name = $col['name'];
			
			$local_col = $name;
			$foreign_table = $col['virtual_data']['foreign_table'];
			$foreign_key = $col['virtual_data']['foreign_key'];
			$foreign_desc = $col['virtual_data']['foreign_desc'];
			if (count($col['virtual_data']) == 5)
				$where_clause = 'and ' . $col['virtual_data']['where_clause'];
			else
				$where_clause = '';
			
			gp_assert(!empty($this->cols[$local_col]), 'No such local column: ' . $local_col);
			
			$key_value = gp_escapeSql($row[$name]);
			
			$qs = "
				select $foreign_key, $foreign_desc
				  from $foreign_table
				 where $foreign_desc <> ''
				   and $foreign_key = '$key_value'
							 $where_clause
				 limit 1
				 ";
			$foreign_row = gp_one_row($qs);
			$html = '';
			
			// If the contents of this cell should be linked (with link_contents_to), let's replace
			// {foreign_key} and {local_key} in that string with the proper data.
			$link = empty($col['virtual_data']['link_contents_to']) ? '' : $col['virtual_data']['link_contents_to'];
			if (!empty($link)) {
				$link = str_replace('{foreign_key}', 
					empty($foreign_row) || empty($foreign_row[$foreign_key]) ? 0 : $foreign_row[$foreign_key],
					$link);
				$link = str_replace('{local_key}', 
					$key_value,
					$link);
			}
			
			// build the cell contents, wrapping them in an A..
			if (!empty($link)) 
				$html .= '<a href="' . $link . '">';
			if (!$foreign_row) 
				$html .= 'None';
			else
				$html .= $foreign_row[$foreign_desc];
			if (!empty($link)) 
				$html .= '</a>';
			
			return $html;
		}
		
		
		function renderBodyNoResults() {
			$n = $this->numCols();
			return "<tr><td colspan=\"$n\" style=\"text-align:center;\">No $this->plural found</td></tr>";
		}
		
		function renderBody() {
			$html  = '';
			
			if (empty($this->move_link_url)) {
				$p_k_col = $this->primary_key;

				$mv_url = gp_my_url();
				$mv_url = gp_add_url_arg($mv_url, "{$this->singular_ws}_move", 'up');
				$mv_url = gp_add_url_arg($mv_url, $p_k_col, 'XOPENX' . $p_k_col . 'XCLOSEX');
				// what a HACK! fix.. this is because it's urlencoded by default
				$mv_url = str_replace('XOPENX', '{', $mv_url);
				$mv_url = str_replace('XCLOSEX', '}', $mv_url);
				$this->move_link_url_up = $mv_url;

				$mv_url = gp_my_url();
				$mv_url = gp_add_url_arg($mv_url, "{$this->singular_ws}_move", 'down');
				$mv_url = gp_add_url_arg($mv_url, $p_k_col, 'XOPENX' . $p_k_col . 'XCLOSEX');
				$mv_url = str_replace('XOPENX', '{', $mv_url);
				$mv_url = str_replace('XCLOSEX', '}', $mv_url);
				$this->move_link_url_down = $mv_url;
			}
			
			if (empty($this->edit_link_url)) {
				$ed_pg = "{$this->singular_ws}_edit." . GANGPLANK_EXT;
				$p_k_col = $this->primary_key;
				$this->edit_link_url = "$ed_pg?$p_k_col={KEY}&after=" . urlencode(gp_my_url());
			}
			
			$start = $this->startIndex();
			$per_page = $this->perPage();
			
			$this->determineFinalOrderBy();
						
			// If the selected order by has an order_by_clause setting, 
			// we need to order by that clause instead of the column name.
			// This is useful for virtual columns that have no real analog inside
			// the database that we could sort by.
			if (!empty($this->order_by) &&
					!empty($this->cols[$this->order_by]) &&
					!empty($this->cols[$this->order_by]['order_by_clause'])) {
				$order_by_clause = ' order by ' . $this->cols[$this->order_by]['order_by_clause'] . ' ';
			} else {
				if (!empty($this->order_by)) 
					$order_by_clause = ' order by ' . $this->order_by;
				else
					$order_by_clause = '';
			}
				
			$from = $this->getFromClause();
			$qs = "
				select *
				  from $from
				 where ($this->where)
				 $order_by_clause
				 limit $start,$per_page";
			$rows = gp_all_rows($qs);
			
			$row_n = 0;
			$n_rows = count($rows);
			$html .= '<tbody>';
			$visible_cols = $this->visibleColumns();
			
			foreach ($rows as $row) {
				$primary_key_val = $row[$this->primary_key];
				
				if ($this->use_break_rows) {
					foreach ($this->break_row_columns as $br_col) {
						if ($row[$br_col] != $this->break_row_values[$br_col]) {
							$html .= call_user_func($this->break_row_callbacks[$br_col], $row, $this->cols[$br_col]);
							$this->break_row_values[$br_col] = $row[$br_col];
						}
					}
				}
				
				// Setup some HTML classes for the row.
				if ($row_n % 2 == 0) 
					$class = 'even';
				else
					$class = 'odd';
				if ($row_n == $n_rows-1)
					$class .= ' lastrow ';
					
				$html .= "<tr class=\"$class\">\n";

				if ($this->show_group_controls) {
					$html .= '<td class="group">';
					$html .= "<input type=\"checkbox\" name=\"{$this->singular_ws}_checked_$primary_key_val\" value=\"1\">";
					$html .= "</td>\n";
				}
	
				foreach ($visible_cols as $col) {
					$name = $col['name'];
					$html .= '<td class="' . $name . '">';
					$html .= $this->getColumnRepr($col, $row);
					$html .= "</td>\n";
				}
				
				if ($this->show_edit_link) {
					$link = $this->edit_link_url;
					$link = str_replace('{KEY}', urlencode($primary_key_val), $link);
					$link = str_replace('{MYURL}', urlencode(gp_my_url()), $link);
					$link = gp_interpUrl($link, $row);
					$html .= "\t<td class=\"edit\">";
					$html .= "<a class=gangplank-btn href=\"$link\">$this->edit_link_label</a>";
					
					// display the selected edit column extra html if any
					if (! empty($this->edit_extra_html))
						$html .= ' - ' . gp_interpUrl($this->edit_extra_html, $row);
					
					$html .= "</td>\n";
				}

				if ($this->show_move_link) {
					$link = gp_interpUrl($this->move_link_url_up, $row);
					$onclick = "";
					if ($this->use_ajax)
						$onclick = "onclick=\"return {$this->plural_ws}_reload('$link');\"";
					$html .= "\t<td class=\"move\">";
					$html .= "<a href=\"$link\" ${onclick}>$this->move_link_label_up</a>";
					// $html .= "<a href=\"$link\" ${onclick}><img src=\"../up.gif\" border=\"0\" /></a>";
					$html .= "</td>\n";
					
					$link = gp_interpUrl($this->move_link_url_down, $row);
					$onclick = "";
					if ($this->use_ajax)
						$onclick = "onclick=\"return {$this->plural_ws}_reload('$link');\"";
					$html .= "\t<td class=\"move\">";
					$html .= "<a href=\"$link\" ${onclick}>$this->move_link_label_down</a>";
					// $html .= "<a href=\"$link\" ${onclick}><img src=\"../down.gif\" border=\"0\" /></a>";
					$html .= "</td>\n";
				}
				
				$html .= "</tr>\n";
				
				$row_n++;
			}
			
			if ($row_n == 0) 
				$html .= $this->renderBodyNoResults();
				
			$html .= "</tbody>\n";
			
			return $html;
		}

		function renderAjax() {
		
			$html_id = $this->html_id;
			
			$html = "
			
			<script>
			function {$this->plural_ws}_reload(url) {
				var A;
				
				document.body.style.cursor = 'wait';
				
				try {
					A = new ActiveXObject(\"Msxml2.XMLHTTP\");
				} catch (e) {
					try {
						A=new ActiveXObject(\"Microsoft.XMLHTTP\");
					} catch (oc) {
						A=null;
					}
				}
				if(!A && typeof XMLHttpRequest != \"undefined\")
					A = new XMLHttpRequest();
				if (!A) {
					document.body.style.cursor = 'auto';
					return true;
				}
				
				A.open(\"GET\", url, true);
				A.onreadystatechange = function() {
					if (A.readyState != 4) 
						return;
					
					document.getElementById(\"${html_id}-enclosure\").innerHTML = A.responseText;
					document.body.style.cursor = 'auto';
					// {$this->plural}_fix_links();
				}
				A.send(null);
				delete A;
				return false;
			}
			function {$this->plural_ws}_search() {
				var searchTerm = document.getElementById('${html_id}-search').value,
				    searchUrlTemplate = " . json_encode(gp_add_url_arg(gp_my_url(), $this->plural_ws.'_search', '_TERM_')) . ";
				searchUrlTemplate = searchUrlTemplate.replace('_TERM_', encodeURIComponent(searchTerm));
				{$this->plural_ws}_reload(searchUrlTemplate);
			}
			function {$this->plural_ws}_toggle_chex() {
				if (! document.getElementsByTagName)
					return;
				var elems = document.getElementsByTagName('input');
				for (var i = 0; i < elems.length; i++) {
					if (elems[i].type == 'checkbox' &&
							elems[i].name.match(/{$this->singular_ws}_checked.*/)) {
						elems[i].checked = !elems[i].checked;
					}
				}
				return false;
			}
			</script>
			";
			
			return $html;
		}
	
		function renderFooter() {
			$html  = '';
			$html .= "<tfoot>\n";
			
			$has_visible_button = false;
			foreach ($this->buttons as $b) {
				if ($b['visible']) {
					$has_visible_button = true;
					break;
				}
			}
			
			if ($has_visible_button || $this->show_group_controls) {
				$html .= "<tr>\n";
				$count = $this->numCols();
				$html .= '<td colspan="' . $count . '">';
				
				if ($this->show_group_controls && $has_visible_button) {
					// $html .= '<p class="gangplank-list-buttons">';
					if ($this->show_group_controls) {
						$items = array();
						
						$onclick = $this->plural_ws . '_toggle_chex();return false;';
						$toggle_label = 'Toggle all';
						if (defined('GANGPLANK_USE_ICONS') && GANGPLANK_USE_ICONS)
							$toggle_label = "<i class='fa fa-check'></i> " . $toggle_label;
						$items[] = '<a class=gangplank-btn href="#" onclick="' . htmlspecialchars($onclick) . '">' . $toggle_label . '</a>';

						foreach ($this->group_controls as $short_name => $control) {
							$onclick = "if (confirm('$control[title]?')) { document.forms.{$this->singular_ws}.{$this->singular_ws}_do_group.value = '$short_name'; document.forms.{$this->singular_ws}.submit(); } return false;";
							$items[] = '<a class=gangplank-btn href="#" onclick="' . htmlspecialchars($onclick) . '">' . $control["title"] . '</a>';
						}

						$html .= join(' &nbsp; ', $items);
					}
					if ($has_visible_button) {
						foreach ($this->buttons as $b) {
							if ($b['visible']) {
								$btn = '<button class=gangplank-btn id="' . $b['id'] . '" onclick="';
								if (!empty($b['onclick']))
									$btn .= 'if ('. $b['onclick'] .') { ';
								if (!empty($b['callback']))
									$btn .= "document.forms.{$this->singular_ws}.{$this->singular_ws}_do_button.value = '$b[id]'; document.forms.{$this->singular_ws}.submit();";
								else
									$btn .= 'location.href=\'' . $b['url'] .'\'; return false;';
								if (!empty($b['onclick']))
									$btn .= '}';
								$btn .= '">'. $b['label'] .'</button>';
								$html .= $btn;
							}
						}
					}
					// $html .= '</p>';
				}
				
				$html .= "</td></tr>\n";
			}
			
			if ($this->show_pagination &&
				  $this->getResultsCount() > $this->perPage()) {
				$pagination = $this->renderPagination();
				
				if (!empty($pagination)) {
					$html .= "<tr><!-- pagination -->\n";
					$n = $this->numCols();
					$html .= "<th colspan=\"$n\" class=\"pagination\">\n";
					$html .= $pagination;
					$html .= "</th>\n";
					$html .= "</tr><!-- /pagination -->\n";
				}
			}

			$html .= "</tfoot>\n";
			$html .= "</table><!-- end of #$this->html_id -->";
			if ($this->show_group_controls) 
				$html .= "</form><!-- end of form used for deletion checkboxes -->";
			if ($this->use_ajax) 
				$html .= "</div><!-- end ajax enclosure div -->";
			$html .= "\n";
			
			if ($this->use_ajax) 
				$html .= $this->renderAjax();
			
			return $html;
		}
		
		function render() {
			$html = '';
			$html .= $this->renderHeader();
			$html .= $this->renderBody();
			$html .= $this->renderFooter();
			return $html;
		}

		function handleMove($move_pk, $move) {
			$c = xconnect();
			
			$pk_col	= $this->primary_key;
			$table	= $this->data_source;

			$qs = "
				select *
				  from $table
				 where ${pk_col} = '${move_pk}'";
			$a = gp_one_row($qs);

			if ($a) {
				if ($move == "up") {
					$sub_qs = "
						select ${pk_col}, sort_key
						  from $table
						 where sort_key < $a[sort_key]
							 and ($this->where)
						 order by sort_key desc 
						 limit 1";
					$sub_a = gp_one_row($sub_qs);
				} else {
					$sub_qs = "
						select ${pk_col}, sort_key
						  from $table
						 where sort_key > $a[sort_key]
							 and ($this->where)
						 order by sort_key asc
						 limit 1";
					$sub_a = gp_one_row($sub_qs);
				}
				if ($sub_a && isset($sub_a["sort_key"])) {
					$qs = "
						update $table
						   set sort_key = $sub_a[sort_key]
						 where $pk_col = '" . $a["$pk_col"] . "'";
					gp_update($qs);
					$qs = "
						update $table
						   set sort_key = $a[sort_key]
						 where $pk_col = '" . $sub_a["$pk_col"] . "'";
					gp_update($qs);
				}
			}
		}
		
		function handleDelete() {
			
			// load column data so we know the primary key
			$this->loadColumns();
			
			foreach ($_REQUEST as $k=>$v) {
				if (! preg_match("/{$this->singular_ws}_checked_(.*)/", $k, $aa))
					continue;
				
				$key_to_del = $aa[1];
				$from = $this->data_source;
				$where = $this->where;
				
				// if we have to pass this row's data to a delete callback, we must
				// save a copy before deleting..
				if ($this->delete_callback) {
					$saved_row = $this->getData($key_to_del);
				}
				
				$qs = "
					delete 
					  from $from
					 where $this->primary_key = ($key_to_del) ";
				gp_update($qs);
				// echo "<p>$qs</p>";
				
				if ($this->delete_callback) {
					call_user_func($this->delete_callback, $saved_row);
				}
			}
		}

		function handleSearch($term) {
			$curWhere = $this->where;
			$clauses = array();
		
			foreach ($this->search_fields as $field) {
				$clauses[] = " ($field LIKE '%$term%') ";
			}

			$clauses_sql = join(' OR ', $clauses);
			$where = "($curWhere) AND ($clauses_sql)";

			$this->setWhere($where);
			echo $this->render();
		}
				
		function handleRequest() {

			$this->loadColumns();

			// If the URL indicates that we should be deleting some entries..
			if (! empty($_REQUEST[$this->singular_ws . '_do_group'])) {
				$clicked_group = $_REQUEST[$this->singular_ws . '_do_group'];
				foreach ($this->group_controls as $short_name => $control) {
					//echo "TESTING $short_name vs $clicked_group<p>";
					if ($clicked_group == $short_name) {
						// echo "DOING $short_name $control[callback]...";
						call_user_func($control['callback']);
					}
				}
			}
			
			// If the URL indicates that we should be reacting to a button push..
			if (! empty($_REQUEST[$this->singular_ws . '_do_button'])) {
				$clicked_btn = $_REQUEST[$this->singular_ws . '_do_button'];
				foreach ($this->buttons as $id => $btn) {
					//echo "TESTING $id vs $clicked_btn<p>";
					if ($clicked_btn == $id) {
						// echo "DOING $short_name $control[callback]...";
						call_user_func($btn['callback']);
					}
				}
			}
			
			// If the URL indicates that we should be moving these entries,
			// we'll call that callback..
			if (! empty($_REQUEST[$this->singular_ws . '_move'])) {
				$w = $_REQUEST[$this->singular_ws . '_move'];
				$this->handleMove($_REQUEST[$this->primary_key], $w);
			}
			
			if ($this->search_enabled &&
					!empty($_REQUEST[$this->plural_ws.'_search'])) {
				$this->handleSearch($_REQUEST[$this->plural_ws.'_search']);
				exit;
			}
			
			if (!empty($_REQUEST['gp_fetch']) 
					&& $_REQUEST['gp_fetch'] === $this->plural_ws) {
				echo $this->render();
				exit;
			}
		}
	}
?>
