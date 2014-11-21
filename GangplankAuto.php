<?
	require_once(dirname(__FILE__) . "/gangplank.php");

	//
	// Gangplank on full autopilot.
	// 
	class AutoPlank {
		function AutoPlank($singular, $plural = '', $data_source = false) {
			$this->singular = $singular;
			$plural = empty($plural) ? $singular . 's' : $plural;
			$this->plural = $plural;
			$this->singular_ws = preg_replace('/\s+/', '_', $singular);
			$this->plural_ws = preg_replace('/\s+/', '_', $plural);
			$this->list = new ListPlank($singular, $plural, $data_source);
			$this->new_record = new NewRecordPlank($singular, $plural, $data_source);
			$this->edit = new EditPlank($singular, $plural, $data_source);

			$this->show_msgs = true;
			
			if ($data_source)
				$this->setDataSource($data_source);
			
			// Determine the mode that we should be functioning in..
			$gp_mode_var_name = $this->singular_ws . '_gp_mode';
			$mode = @any_of(
				$_POST[$gp_mode_var_name], 
				$_GET[$gp_mode_var_name],
				'list');
			$this->setMode($mode);
			$gp_mode_var_name = $this->singular_ws . '_gp_mode';
			$this->list->loadColumns();			
			$my_url = gp_my_url();
			$this->edit_page = gp_add_url_arg($my_url, $gp_mode_var_name, 'edit');
			$this->list_page = gp_add_url_arg($my_url, $gp_mode_var_name, 'list');
			$this->new_record_page = gp_add_url_arg($my_url, $gp_mode_var_name, 'new_record');
			$primary = $this->list->primary_key;
			$this->list->setEditLink(true, gp_add_url_raw_arg($this->edit_page, $primary, '{KEY}'));
			$this->list->setAddLink(true, $this->new_record_page);
			$this->edit->addFormValue($gp_mode_var_name, 'edit');
			$this->edit->setSaveButton(true, gp_add_url_arg($this->list_page, 'msgs[]', "Changes to {$this->singular} saved"));
			$this->edit->setDeleteButton(true, gp_add_url_arg($this->list_page, 'msgs[]', $this->singular . ' deleted'));
			$this->new_record->setEditLink(gp_add_url_raw_arg($this->edit_page, $primary, '{KEY}'));
		}
		
		function setMode($mode) {
			$possible_modes = array('list', 'new_record', 'edit');
			if (!in_array($mode, $possible_modes))
				gp_die('invalid AutoPlank mode ' . $mode, __FILE__, __LINE__);
			$this->mode = $mode;
		}
		
		function getMode() {
			return $this->mode;
		}
		
		function handleRequest() {
			if ($this->mode == 'list') 
				return $this->list->handleRequest();
			if ($this->mode == 'new_record') 
				return $this->new_record->handleRequest();
			if ($this->mode == 'edit') 
				return $this->edit->handleRequest();
		}
		
		function render() {
			if ($this->show_msgs) {
				if (!empty($_REQUEST['msgs'])) {
					foreach ($_REQUEST['msgs'] as $msg) {
						echo htmlspecialchars($msg) . "<br/>";
					}
				}
			}
			if ($this->mode == 'list') 
				return $this->list->render();
			if ($this->mode == 'new_record') 
				return $this->new_record->render();
			if ($this->mode == 'edit') 
				return $this->edit->render();			
		}

		//
		// The rest of these functions simply call the underlying objects' corresponding
		// methods.
		// 

		function addVirtualColumn() {
			$args = func_get_args();
			call_user_func_array(array(&$this->list, 'addVirtualColumn'), $args);
			call_user_func_array(array(&$this->edit, 'addVirtualColumn'), $args);
		}
		
		function hideColumns() {
			$args = func_get_args();
			call_user_func_array(array(&$this->list, 'hideColumns'), $args);
			call_user_func_array(array(&$this->edit, 'hideColumns'), $args);
		}
		
		function hideColumn() {
			$args = func_get_args();
			call_user_func_array(array(&$this->list, 'hideColumn'), $args);
			call_user_func_array(array(&$this->edit, 'hideColumn'), $args);
		}
		
		function hideAllColumns() {
			$args = func_get_args();
			call_user_func_array(array(&$this->list, 'hideAllColumns'), $args);
			call_user_func_array(array(&$this->edit, 'hideAllColumns'), $args);
		}

		function setColumnLabel() {
			$args = func_get_args();
			call_user_func_array(array(&$this->list, 'setColumnLabel'), $args);
			call_user_func_array(array(&$this->edit, 'setColumnLabel'), $args);
		}
		
		function setDataSource() {
			$args = func_get_args();
			call_user_func_array(array(&$this->list, 'setDataSource'), $args);
			call_user_func_array(array(&$this->new_record, 'setDataSource'), $args);
			call_user_func_array(array(&$this->edit, 'setDataSource'), $args);
		}
		
		function showColumns() {
			$args = func_get_args();
			call_user_func_array(array(&$this->list, 'showColumns'), $args);
			call_user_func_array(array(&$this->edit, 'showColumns'), $args);
		}
		
	}
?>
