<?
	require_once(dirname(__FILE__) . '/gangplank.php');
	
	// 
	// GANGPLANK EDIT CLASS
	//
	// Builds a flexible form based on the data in the row. Smart about field types.
	//
	class EditPlank extends Gangplank {
	
		function EditPlank($singular, $plural = '', $data_source = false) {
			$this->singular_ws = preg_replace('/\s+/', '_', $singular);
			$plural = empty($plural) ? $singular . 's' : $plural;
			$this->plural = $plural;
			$this->plural_ws = preg_replace('/\s+/', '_', $plural);
			$this->primary_key = false;
			$this->primary_key_value = false;
			
			$this->html_id = $singular . '-list';
			$this->html_class = 'gangplank-edit';
			
			$this->buttons = array();
			
			// save button configuration 
			$save_button_label = 'Save changes';
			$save_post_url = $this->plural_ws . GANGPLANK_EXT . '?msgs[]=' . urlencode("Changes to $singular saved.");
			$this->addButton('save', true, $save_button_label, $save_post_url, '');

			$clone_button_label = ucwords('Clone');
			$clone_button_onclick = "return confirm('Are you sure you want to make a copy of this $singular?');";
			$this->addButton('clone', true, $clone_button_label, false, $clone_button_onclick, array($this, 'cloneRecord'));
			
			// delete button configuration
			$delete_post_url = $this->plural_ws . GANGPLANK_EXT . '?msgs[]=' . urlencode("Ok, that $singular was deleted.");
			$delete_button_label = ucwords('Delete ' . $singular);
			$delete_button_onclick = "return confirm('Are you sure you want to delete this $singular?');";
			$this->addButton('delete', true, $delete_button_label, $delete_post_url, $delete_button_onclick);

			// passed form values
			$this->passed_form_values = array();
			
			// various callbacks to enable expandability
			$this->validation_callback = false;
			$this->save_callback = false;
			$this->delete_callback = false;
			$this->preform_callback = false;
			$this->postform_callback = false;
			// render callbacks work in place of rendering a column's edit pair
			$this->render_callbacks = false;
			
			$this->photo_prefix = '';
			$this->photo_preview = false;
			$this->use_dynamic_textarea_sizing = true;
			$this->virtual_column_types[] = 'Callback';
			$this->virtual_column_types[] = 'Asset';
			$this->virtual_column_types[] = 'ScaledPhoto';
			$this->virtual_column_types[] = 'ForeignKey';
			$this->virtual_column_types[] = 'MappedKey';
			$this->virtual_column_types[] = 'LocalPath';

			$this->Gangplank($singular, $plural, $data_source);

			$this->hideFieldsFromUrl();
		}

		// Look for URL fields in the format gp_auto_limit_COL and automatically hide
		// those. This is so that we can pass in URL vars to limit the data edited to
		// only those keys we specify. They are also auto-populated when a new record
		// is set up and automatically limited to matching keys when listed.
		function hideFieldsFromUrl() {
			$this->loadColumns();
			foreach ($this->cols as $name => $col) {
				if (!empty($_REQUEST['gp_auto_limit_'.$name])) 
					$this->hideColumn($name);
			}
		}
		
		function addFormValue($name, $value) {
			$this->passed_form_values[$name] = $value;
		}
		
		function removeFormValue($name) {
			unset($this->passed_form_values[$name]);
		}

		// 
		// ----[ BUTTONS - Top and bottom of form ]----------------------------
		//
		
		function addButton($name, $show, $label, $post_url, $onclick = false, $callback = false, $id = '') {
			if ($id == '')
				$id = $this->singular_ws . '_' . $name;
			if ($callback && !is_callable($callback)) 
				gp_die("addButton($name): Invalid callback '$callback', not callable", __FILE__, __LINE__);
			$this->buttons[$name] = 
				array('name' => $name, 'show' => $show, 
							'label' => $label, 'id' => $id,
						  'post_url' => $post_url, 
						  'onclick' => $onclick,
						  'callback' => $callback);
		}
				
		// Private convenience function. Set the behavior of $button
		function _setButtonBehavior($button, $show, $label, $post_url, $onclick) {
			$b = $this->buttons[$button];
			if ($b) {
				$b['show'] = $show;
				if ($label != false)
					$b['label'] = $label;
				if ($post_url != false)
					$b['post_url'] = $post_url;
				if ($onclick != false)
					$b['onclick'] = $onclick;
				$this->buttons[$button] = $b;
			}
		}
		
		// Set the behavior of the Save button. $show (boolean) controls whether
		// or not it appears at all; $post_url controls where we go after
		// saving; $label controls the text label; $onclick is arbitrary
		// JavaScript JavaScript to be called on button click. Specify false for
		// post_url/label/onclick to leave default.
		//
		// The post URL follows the rules of URL interpolation; see Gangplank::interpUrl 
		// for more information.
		function setSaveButton($show, $post_url = false, $label = false, $onclick = false) {
			$this->_setButtonBehavior('save', $show, $label, $post_url, $onclick);
		}
		
		function deleteButtonName() {
			$button_name = $this->singular_ws . '_delete';
			return $button_name;
		}
		
		function saveButtonName() {
			$button_name = $this->singular_ws . '_save';
			return $button_name;
		}
		
		// After a button's behavior is carried out, we redirect to the post URL. This function
		// gets that post URL.
		function getButtonPostURL($button_name) {
			if (!isset($this->buttons[$button_name])) {
				gp_die("getButtonPostURL($button_name): Unknown button");
			}
			
			return $this->buttons[$button_name]['post_url'];
		}

		// 
		// ----[ CALLBACKS - For buttons and column rendering ]----------------
		//
		
		// The validation callback is called before we begin saving
		// changes to the database. It has two uses:
		//
		// 1. You can check if the values in $_POST are valid. If
		// not, return false; we will abort the save.
		//
		// 2. You can modify the values in $_POST so that we save an
		// adjusted value instead of what was really submitted. In
		// this case, be sure you return true from your callback
		// function.
		//
		// $callback_func will be called with no arguments.
		//
		// To clear the validation callback, pass false as $callback_func.
		function setValidationCallback($callback_func) {
			$this->validation_callback = $callback_func;
		}
		
		// The callback you specify in $callback_func will be called
		// after we are done saving changes.  This is useful if you
		// want to update other tables as a result of the primary
		// table being updated.
		//
		// Your callback will be called with one argument: the value
		// of the row, after the update. You can access $_POST for
		// other values.
		//
		// Call with $callback_func == false to cancel the callback process.
		function setSaveCallback($callback_func) {
			$this->save_callback = $callback_func;
		}
		
		// Set the behavior of the Delete button. $show (boolean) controls whether
		// or not it appears at all; $post_url controls where we go after
		// saving; $label controls the text label; $onclick is arbitrary
		// JavaScript JavaScript to be called on button click. Specify false for
		// post_url/label/onclick to leave default.
		//
		// The post URL follows the rules of URL interpolation; see Gangplank::interpUrl 
		// for more information.
		function setDeleteButton($show, $post_url = false, $label = false, $onclick = false) {
			$this->_setButtonBehavior('delete', $show, $label, $post_url, $onclick);
		}

		// After we delete the record, call $callback_func. Call with $callback_func == false
		// to cancel.
		function setDeleteCallback($callback_func) {
			$this->delete_callback = $callback_func;
		}
		
		// After we output the top buttons, but before we start showing the
		// actual editable form elements, we'll call $callbakc_func. Specify
		// false (which is the default) to turn off this behavior.
		function setPreformCallback($callback_func) {
			$this->preform_callback = $callback_func;
		}
		
		// After we output the editable form items, but before we show the
		// bottom buttons, we'll call $callback_func. Specify false (which is
		// the default) to turn off this behavior.
		// 
		// The return value from this call will be embedded in the form HTML.
		// If you attempt to use PHP's echoing facilities here, the output
		// may not appear where you expect.
		function setPostformCallback($callback_func) {
			$this->postform_callback = $callback_func;
		}
		
		// Set a function to be called when it comes time to rendering the edit
		// pair for $col_name. Set $callback to false to ignore this behavior.
		function setRenderCallback($col_name, $callback) {
			$this->loadColumns(); // load column data in case it hasnt been done yet
			if (empty($col_name) || empty($this->cols[$col_name]))
				gp_die("setRenderCallback('$col_name','$callback'): Invalid column name");
			$this->render_callbacks[$col_name] = $callback;
		}
				
		// Set the id of this editing widget; the widget will also be in $id-enclosure.
		function setHtmlId($html_id) {
			$this->html_id = $html_id;
		}

		// Set a class to apply to the editing widget's div.
		function setHtmlClass($html_class) {
			$this->html_class = $html_class;
		}
		
		function setHelpText($col_name, $help_text) {
			if (empty($col_name))
				gp_die("setHelpText('$col_name', '$help_text'): Invalid empty column name");
			$this->loadColumns();
			if (empty($this->cols[$col_name]))
				gp_die("setHelpText('$col_name', '$help_text'): Unknown column");
			$this->setColumnProperties($col_name, array('help_text' => $help_text));
		}
		
		function setOnHandler($col_name, $event_type, $javascript) {
			if (substr($event_type, 0, 2) != 'on')
				$event_type = 'on' . $event_type;
			$this->setColumnProperties($col_name, array($event_type => $javascript));
		}
				
		// Return a count of the number of columns current defined.
		function numCols() {
			$n = count($this->visibleColumns());
			return $n;
		}
		
		// Set the value of the primary key that we're looking for. This can be used to override
		// the value that we pick from the environment.
		function setPrimaryKeyValue($key) {
			$this->primary_key_value = $key;
		}
		
		// Return the data for the selected row.
		function getData() {
			$this->loadColumns();
			if (empty($this->data_source))
				gp_die('getData(): Could not determine data source');
			if (empty($this->where) || $this->where == '1=1') 
				$this->buildWhereClause();
			
			$qs = "
				select *
				  from $this->data_source
				 where ($this->where) 
				 limit 1
				 ";
			$data = gp_one_row($qs);
			assert(is_array($data));
			return $data;
		}
		
		function renderButtons($which = 'top') {
			$html = "<p class=\"$which-buttons\">";
			
			foreach ($this->buttons as $button) {
				$id = $button['id'];
				$onclick = gp_escapeJs($button['onclick']);
				$lbl = gp_escapeJs($button['label']);
				$name = $button['name'];
				$html .= "
					<input type=\"submit\" name=\"$id\" id=\"$id\" 
						value=\"$lbl\" onclick=\"$onclick\"/>
					";
			}
				
			$html .= '
				</p>';
			return $html;
		}
		
		function renderPrimaryKey($row, $col) {
			$name = $col['name'];
			$val = htmlspecialchars($row[$name]);
			$html = "
				<input type=\"hidden\" name=\"$name\" id=\"$name\"
					class=\"w w-text\"
					value=\"$val\" />";
			return $html;
		}
		
		// Internal use. Go through the column settings and build a string of
		// all the JavaScript behaviors we need to apply to an INPUT.
		function buildJsBehaviors($col) {
			$js = '';
			if (! empty($col['on_change']))
				$js .= ' onChange="' . gp_escapeJs($col['on_change']) . '" ';
			if (! empty($col['on_click']))
				$js .= ' onClick="' . gp_escapeJs($col["on_click"]) . '" ';
			if (! empty($col["on_key_down"]))
				$js .= ' onKeyUp="' . gp_escapeJs($col["on_key_down"]) . '\" ';
			return $js;
		}
		
		function renderText($row, $col) {
			$name = $col['name'];
			
			$js = $this->buildJsBehaviors($col);
			if (!empty($col['help_text'])) 
				$help = "<span class=\"help-text\">$col[help_text]</span>";
			else
				$help = '';
			$val = htmlspecialchars($row[$name]);

			$html = "
				<label for=\"$name\">
					$col[label]
				</label>
				<input type=\"text\" name=\"x_$name\" id=\"$name\"
					class=\"w w-text\"
					value=\"$val\" $js />
				$help";
			return $html;
		}

		function renderMoney($row, $col) {
			$name = $col['name'];
			
			$js = $this->buildJsBehaviors($col);
			if (!empty($col['help_text'])) 
				$help = "<span class=\"help-text\">$col[help_text]</span>";
			else
				$help = '';
			$val = htmlspecialchars(gp_format_money_int($row[$name]));

			$html = "
				<label for=\"$name\">
					$col[label]
				</label>
				$help
				$<input type=\"text\" name=\"x_$name\" id=\"$name\"
					class=\"w w-text\"
					value=\"$val\" $js />";
			return $html;
		}

		function renderTextArea($row, $col) {
			$name = $col['name'];

			$js = $this->buildJsBehaviors($col);
			$val = htmlspecialchars($row[$name]);
			if (!empty($col['help_text'])) 
				$help = "<span class=\"help-text\">$col[help_text]</span>";
			else
				$help = '';
			$html = '';
			
			// If dynamic textarea sizing is on, we'll measure the 
			// text as it stands and expand the area suitably. 
			if ($this->use_dynamic_textarea_sizing) {
				$min_rows = 4;
				$max_rows = 12;
				
				$physical_rows = substr_count($val, "\n");
				$visual_rows = strlen($val) / 60;
				$rows = ceil(max($physical_rows, $visual_rows));
				if ($rows < $min_rows)
					$rows = $min_rows;
				if ($rows > $max_rows)
					$rows = $max_rows;
				$html .= "<style>
				#$name { height: ${rows}em; }
				</style>";
			}
			$html .= "
				<label for=\"$name\">
					$col[label]
				</label>
				$help
				<textarea name=\"x_$name\" id=\"$name\"
					class=\"w w-textarea\" $js>$val</textarea>";
			if ($this->use_dynamic_textarea_sizing) {
				$html .= "<script>
				
				var min_rows_$name = $min_rows;
				var max_rows_$name = $max_rows;
				var t_$name = document.getElementById(\"$name\"); 
				t_$name.onkeydown = function() {
					var val = t_$name.value;
					var physical_rows = val.split(/\\n/).length;
					var visual_rows = parseInt(val.length / 60);
					var rows = physical_rows > visual_rows ? physical_rows : visual_rows;
					if (rows < min_rows_$name) rows = min_rows_$name;
					if (rows > max_rows_$name) rows = max_rows_$name;
					t_$name.style.height = parseInt(rows) + \"em\";
				}
				t_$name.onkeydown();
				
				</script>";
			}
			return $html;
		}

		// Render an enumarated list as a select box
		function renderEnum($row, $col) {
			$name = $col['name'];
			$js = $this->buildJsBehaviors($col);
			$val = htmlspecialchars($row[$name]);
			if (!empty($col['help_text'])) 
				$help = "<span class=\"help-text\">$col[help_text]</span>";
			else
				$help = '';
			$html = "
				<label for=\"$name\">
					$col[label]
				</label>
				$help
				<select name=\"x_$name\" id=\"$name\"
					class=\"w w-enum\" $js>";
			
			foreach ($col['value_list'] as $v) {
				$v = htmlspecialchars($v);
				if ($v == $val)
					$sel = 'selected';
				else
					$sel = '';
				
				$html .= "<option value=\"$v\" $sel>$v</option>\n";
			}
			
			$html .= '</select>';
			
			return $html;
		}

		function renderCheckbox($row, $col) {
			$name = $col['name'];
			$js = $this->buildJsBehaviors($col);
			if (!empty($col['help_text'])) 
				$help = "<span class=\"help-text\">$col[help_text]</span>";
			else
				$help = '';
			$val = 1; // checkboxes never have a useful value attached.
			$chk = !empty($row[$name]) ? 'checked' : '';
			$html = "
				<label for=\"$name\">
					<input type=\"checkbox\" name=\"x_$name\" id=\"$name\"
						class=\"w w-checkbox\" $chk
						value=\"$val\" $js />
					$col[label]
					$help
				</label> ";
			return $html;
		}
		
		function renderDate($row, $col) {
			$name = $col['name'];
			$val = htmlspecialchars($row[$name]);
			if (!empty($col['help_text'])) 
				$help = "<span class=\"help-text\">$col[help_text]</span>";
			else
				$help = '';
			if (empty($val) || $val == '0000-00-00')
				$val = date('Y-m-d');
			$chk = $row[$name] == 1 ? 'checked' : '';
			$html = "
				<label for=\"$name\">
					$col[label]
				</label>
				$help
				";
			ob_start();
			gp_f_date('x_' . $name, $val);
			$html .= ob_get_contents();
			ob_end_clean();
			$html .= "
				<input type=\"hidden\" name=\"reassemble_fields[]\" value=\"x_$name\" />";
			
			return $html;
		}
		
		function renderDateTime($row, $col) {
			$name = $col['name'];
			$val = htmlspecialchars($row[$name]);
			if (!empty($col['help_text'])) 
				$help = "<span class=\"help-text\">$col[help_text]</span>";
			else
				$help = '';
			if (empty($val) || $val == '0000-00-00 00:00:00')
				$val = date('Y-m-d h:i:s');
			$chk = $row[$name] == 1 ? 'checked' : '';
			$html = "
				<label for=\"$name\">
					$col[label]
				</label>
				$help
				";
			ob_start();
			gp_f_date_time('x_' . $name, $val);
			$html .= ob_get_contents();
			ob_end_clean();
			$html .= "
				<input type=\"hidden\" name=\"reassemble_fields[]\" value=\"x_$name\" />";
			
			return $html;
		}

		function renderColumn($row, $col) {
			$name = $col['name'];
			
			// Special case for primary keys: They don't get the <p> decoration around them.
			// Does this mean that primary keys should be handled outside of renderColumn()?
			// Hard to say. renderColumn() is called on the result of visibleColumns(), but
			// primary keys aren't really visible, are they? Hmm..
			if ($name == $this->primary_key) {
				return $this->renderPrimaryKey($row, $col);
			}
			
			// Everything else gets wrapped in an edit-pair..
			$html = "
				<p class=\"edit-pair\" id=\"$col[name]-container\">
					";
			
			if ($col['is_virtual']) {
				$html .= call_user_func(array(&$this, 'renderVirtual' . $col['type']), $row, $col);
			} elseif (isset($this->render_callbacks[$name]) && $this->render_callbacks[$name]) {
				$html .= call_user_func($this->render_callbacks[$name], $row, $col);
			} else {
				switch ($col['native_type']) {
					case 'string':
						$html .= $this->renderText($row, $col);
						break;
					case 'longstring':
						$html .= $this->renderTextArea($row, $col);
						break;
					case 'money':
						$html .= $this->renderMoney($row, $col);
						break;
					case 'boolean':
						$html .= $this->renderCheckbox($row, $col);
						break;
					case 'date':
						$html .= $this->renderDate($row, $col);
						break;
					case 'datetime':
						$html .= $this->renderDateTime($row, $col);
						break;
					case 'enum':
						$html .= $this->renderEnum($row, $col);
						break;
					default:
						$html .= $this->renderText($row, $col);
						break;
				}
			}
			$html .= "</p><!-- /$name -->\n";
			return $html;
		}
				
		// 
		// ----[ Virtual Column Handlers ]----
		//

		// --[ The "Callback" Virtual Column Type ]--
		//		
		// This callback does almost nothing; it merely calls your callbacks.
		//
		// Options:
		//
		// render_callback -> Required. Called as $render_callback($data); should return HTML.
		// save_callback -> Required. Called as $save_callback($data); should save on its own and not return anything.
		function validateCallbackData($data, &$error_text) {
		
			if (empty($data['render_callback'])) {
				$error_text = 'render_callback not specified';
				return false;
			}
			
			if (! is_callback($data['render_callback'])) {
				$error_text = 'render_callback not callable';
				return false;
			}

			if (empty($data['save_callback'])) {
				$error_text = 'save_callback not specified';
				return false;
			}
			
			if (! is_callback($data['save_callback'])) {
				$error_text = 'save_callback not callable';
				return false;
			}
			
			return true;
		}
		
		function renderVirtualCallback($row, $col) {
			global $uploaded_img_url;
			
			$name = $col['name'];
			
			$html = "
				<label for=\"$name\">
					$col[label]
				</label>
				";
			
			$html .= call_user_func($col['virtual_data']['render_callback'], $row);
			return $html;
		}

		function saveVirtualCallback($row, $col) {
			call_user_func($col['virtual_data']['save_callback'], $row);
			return '';
		}
		
		// --[ The "Asset" Virtual Column Type ]--
		//		
		// This is basically an uploadable piece of data -- file, photo,
		// document, etc. It is shown as a downloadable
		//
		// Options:
		//
		// disallow_downloads
		// upload_path -> where the files go in the filesystem
		// upload_url -> corresponding URL
		// path_column -> if you want to save the output path in the DB, here's the column (optional)
		// optionally_scale_to -> an optional array("col_name" => "size") list
		
		function validateVirtualAssetData($data, &$error_text) {
			if (empty($data))
				return true;
			$valid_opts = array('disallow_downloads', 'upload_path', 'upload_url', 'path_column', 'optionally_scale_to');
			foreach ($data as $k=>$v) {
				if (!in_array($k, $valid_opts)) {
					$opts = join(', ', $valid_opts);
					$error_text = "Invalid \"$k\" option given -- must be one of ($opts)";
					return false;
				}
			}
			if (!empty($data["upload_path"]) && !file_exists($data["upload_path"])) {
				$error_text = "Invalid path \"$data[upload_path]\"";
				return false;
			}
			if (!empty($data["path_column"]) && !$this->cols[$data["path_column"]]) {
				$error_text = "Invalid path column specified";
				return false;
			}
			return true;
		}
		
		function renderVirtualAsset($row, $col) {
			global $uploaded_img_url;
			
			$name = $col['name'];
			
			$html = "
				<label for=\"$name\">
					$col[label]
				</label>
				";
			
			if (empty($row[$name])) {
				$html .= 'Nothing has been uploaded yet.<br/>';
			} else {
				if (!isset($col['virtual_data']['disallow_downloads']) ||
						$col['virtual_data']['disallow_downloads'] == 0) {
					$html .= "<a href=\"$row[$name]\" target=\"_blank\">Open/View uploaded file</a> &nbsp;&nbsp; Delete this file: <input type=\"checkbox\" name=\"x_${name}_del\" value=\"yes\" />";
				} else {
					$html .= 'Asset has been uploaded.<br/>';
				}
			}
			
			$html .= "
				<br /><input type=\"file\" name=\"$name\" />
			";
			
			if (isset($col["virtual_data"]["optionally_scale_to"])) {
				foreach ($col["virtual_data"]["optionally_scale_to"] as $col => $sz) {
					$field = "x_asset_${name}_scale_$col";
					$html .= "<br/>
						<input type=\"checkbox\" name=\"$field\" id=\"$field\" value=\"1\">
						<label for=\"$field\" style=\"display:inline\">Also create thumbnail at $sz?</label>";
				}
			}
			
			return $html;
		}

		// Save an Asset column - basically processes and moves an upload.
		function saveVirtualAsset($row, $col) {
			global $uploaded_img_path;
			global $uploaded_img_url;
			
			$options = $col['virtual_data'];
			
			if (!empty($options['upload_path']))
				$uploaded_img_path = $col['virtual_data']['upload_path'];
			if (!empty($options['upload_url']))
				$uploaded_img_url = $col['virtual_data']['upload_url'];

			// copy the settings from the config file if set, falling back on $options or globals
			if (defined('GANGPLANK_UL_PATH') && empty($uploaded_img_path))
				$uploaded_img_path = GANGPLANK_UL_PATH;
			if (defined('GANGPLANK_UL_URL') && empty($uploaded_img_url))
				$uploaded_img_url = GANGPLANK_UL_URL;
				
			// still nothing? die.
			if (empty($uploaded_img_path)) gp_die('saveVirtualAsset(): No uploaded image path');
			if (empty($uploaded_img_url)) gp_die('saveVirutalAsset(): No uploaded image URL');
			
			$pk = $this->primary_key;
			$field = $col['name'];
			
			// If the user clicked the Delete This Asset checkbox,
			// let's go ahead and clear the relevant fields. Also unlink the file.
			if (isset($_POST["x_${field}_del"])) {
				$sql = "$field = '', ";
				if (!empty($options['path_column'])) {
					$sql .= "$options[path_column] = '', ";
					if (!empty($row[$options['path_column']])) {
						@unlink($row[$options['path_column']]);
					}
				}
				return $sql;
			}
			
			if (empty($_FILES[$field]) || empty($_FILES[$field]['name'])) 
				return;
			$tmp = $_FILES[$field]['tmp_name'];
			if (! is_uploaded_file($tmp)) gp_die('saveVirtualAsset(): Bad upload!');
			
			$sql = '';
			
			// build the output filename.
			$fn = '';
			if (! empty($this->photo_prefix))
				$fn .= $this->photo_prefix . '-';
			
			if (! empty($row[$pk])) 
				$fn .= $row[$pk] . '-';
			if (! empty($_FILES[$field]['name']))
				$fn .= basename($_FILES[$field]['name']);
			$out_fn = $uploaded_img_path . escapeshellcmd($fn);
			$out_url = $uploaded_img_url . $fn;
			
			// build command string
			if (! move_uploaded_file($tmp, $out_fn)) {
				gp_die("saveVirtualAsset(): Command failed: 'move_uploaded_file($tmp, $out_fn)'");
			}
			
			chmod($out_fn, 0755);
			
			if (empty($options['skip_url_column']))
				$sql .= "$field = '" . gp_escapeSql($out_url) ."', ";
			
			if (!empty($options['path_column'])) 
				$sql .= "$options[path_column] = '" . gp_escapeSql($out_fn) . "', ";

			if (isset($col["virtual_data"]["optionally_scale_to"])) {
				foreach ($col["virtual_data"]["optionally_scale_to"] as $col => $sz) {
					$field_name = "x_asset_${field}_scale_$col";
					if (isset($_POST[$field_name])) {
						// scale a copy; put in $col
						$save_as = $out_fn . "-$sz.jpg";
						$save_url = $out_url . "-$sz.jpg";
						gp_resize_imagemagick($out_fn, $save_as, $sz);
						$sql .= "$col = '" . gp_escapeSql($save_url). "', ";
					}
				}
			}
						
			if (file_exists($tmp))
				unlink($tmp);
			return $sql;
		}
		
		// --[ The "ScaledPhoto" Virtual Column Type ]--
		//
		// Use like this:
		//
		// $options = array(
		//		'photo_small_url' => '160x',
		//		'photo_large_url' => '500x' );
		//
		
		// A virtual scaled photo is scaled after saving.

		function validateVirtualScaledPhotoData($data, &$error_text) {
			if (! is_array($data)) {
				$error_text = 'Data must be an array';
				return false;
			}
			$n = 1;
			foreach ($data as $k=>$v) {
				if (!gp_parse_size_spec($v)) {
					$error_text = "Column $k: invalid geometry spec '$v'; must be Xx[Y..] or noscale";
					return false;
				}
			}
			return true;
		}
		
		function renderVirtualScaledPhoto($row, $col) {
			global $uploaded_img_url;
			
			$name = $col['name'];
			
			$x = array();
			foreach ($col['virtual_data'] as $k=>$v) {
				$x[] = array($k, $v);
			}
			$first_col = $x[0][0];
			$first_size = $x[0][1];
			
			$html = "
				<label for=\"$name\">
					$col[label]
				</label>
				";
			
			if (empty($row[$first_col])) {
				$html .= 'No pic has been uploaded yet.<br/>';
			} else {
				$html .= "<img src=\"$row[$first_col]\" alt=\"$first_col $first_size pic: $row[$first_col]\" /><br/>";
			}
			
			$html .= "
				<input type=\"file\" name=\"$name\" /><br/>
				<input type=\"checkbox\" name=\"clear_$name\" value=\"1\"> Delete photo
			";
			
			return $html;
		}
		
		function _generateIMCropSpec($in_width, $in_height, $out_width, $ratio) {
			gp_assert(is_numeric($out_width), "_generateCropSpec(): bad out width '$out_width'");
			gp_assert($in_width && $in_height && $out_width, 
				"_generateCropSpec(): bad values $in_width / $in_height / $out_width");
			gp_assert($in_width >= $out_width, 
				"_generateCropSpec(): $in_width < $out_width!");
				
			// if ratio is a string like 1:2, convert to float 1/2
			if (is_string($ratio)) {
				$ratio_parts = preg_split("/\/:/i", $ratio);
				gp_assert(count($ratio_parts) == 2, "_generateCropSpec: invalid ratio '$ratio'");
				$ratio = (float)$ratio_parts[0]/(float)$ratio_parts[1];
			}
			
			$out_height = $out_width * $ratio;
			$in_ratio = $in_width / $in_height;
			if ($in_ratio >= 1) { // w > h
				// landscape
				$final_height = $in_height;
				$final_width = $in_height * $ratio;
			} else {
				// portrait
				$final_width = $in_width;
				$final_height = $in_width * (1/$ratio);
			}
			
			// too big? shrink. this is a hack.
			if ($final_height > $in_height) {
				// too big, scale down w/ same ratio
			  $ratio = ($in_height / $final_height);
			  $final_width = $final_width * $ratio;
			  $final_height = $final_height * $ratio;
			}
			if ($final_width > $in_width) {
				// too big, scale down w/ same ratio
			  $ratio = ($in_width / $final_width);
			  $final_width = $final_width * $ratio;
			  $final_height = $final_height * $ratio;
			}
			$final_width = round($final_width);
			$final_height = round($final_height);
			$ofs_x = round(($in_width - $final_width) / 2);
			$ofs_y = round(($in_height - $final_height) / 2);
			return "${final_width}x${final_height}+${ofs_x}+${ofs_y}";
		}
		
		function _generateIMCmds($ul_width, $ul_height, $w, $opts)
		{
			$cmd = "";
			if ($opts["cropratio"])
				$cmd .= "-crop " . $this->_generateIMCropSpec($ul_width, $ul_height, $w, $opts["cropratio"]) . " ";
			if ($opts["quality"])
				$cmd .= "-quality $opts[quality] ";
			return $cmd;
		}

		// Save a virtual scaled photo.
		function saveVirtualScaledPhoto($row, $col) {
			
			if (defined('GANGPLANK_UL_PATH')) {
				$uploaded_img_path = GANGPLANK_UL_PATH;
				$uploaded_img_url = GANGPLANK_UL_URL;
				$image_magick_path = GANGPLANK_IMAGEMAGICK_BINARY;
			} else {
				global $uploaded_img_path;
				global $uploaded_img_url;
				global $image_magick_path;
				if (empty($uploaded_img_path)) gp_die("saveVirtualScaledPhoto(): No uploaded image path; set global \$uploaded_img_path");
				if (empty($uploaded_img_url)) gp_die("saveVirutalScaledPhoto(): No uploaded image URL; set global \$uploaded_img_url");
				if (empty($image_magick_path)) gp_die("saveVirtualScaledPhoto(): No ImageMagick path; set global \$image_magick_path");
			}
			
			$pk = $this->primary_key;
			
			$field = $col['name'];
			
			if (!empty($_POST['clear_' . $field])) {
				$sql = '';
				foreach ($col['virtual_data'] as $name => $size) {
					$cur_val = gp_escapeSql($row[$name]);
					$sql .= "$name = '', ";
				}
				return $sql;
			}
			
			if (empty($_FILES[$field]) || empty($_FILES[$field]['tmp_name'])) 
				return;
			gp_assert($_FILES[$field]['error'] == UPLOAD_ERR_OK, "saveVirtualScaledPhoto(): Bad upload for $field");
			$tmp = $_FILES[$field]['tmp_name'];
			if (! is_uploaded_file($tmp)) {
				$sql = '';
				foreach ($col['virtual_data'] as $name => $size) {
					$cur_val = gp_escapeSql($row[$name]);
					$sql .= "$name = '$cur_val', ";
				}
				return $sql;
			}

			$info = getimagesize($tmp);
			gp_assert($info, "saveVirtualScaledPhoto(): uploaded image is not valid image format");
			// get dimensions of the image
			list($ul_width, $ul_height, $type, $attr) = $info;
			
			$sql = '';
			foreach ($col['virtual_data'] as $name => $size_spec) {
					
				$spec_info = gp_parse_size_spec($size_spec);
				gp_assert($spec_info, "saveVirtualScaledPhoto(): could not parse size spec '$size_spec'");
				$size = $spec_info[0];
				$w = $spec_info[1];
				$h = $spec_info[2];
				$opts = $spec_info[3];
				
				// build the output filename.
				$fn = '';
				if (! empty($this->photo_prefix))
					$fn .= $this->photo_prefix . '-';
				
				if (! empty($row[$pk])) 
					$fn .= $row[$pk] . '-';
				$fn .= $size . '-';
				if (! empty($_FILES[$field]['name']))
					$fn .= str_replace(".jpg", "", basename($_FILES[$field]['name'])) . ".jpg";
				$out_fn = escapeshellcmd($uploaded_img_path . $fn);
				$out_url = $uploaded_img_url . $fn;
				//$out_url = $fn;
				
				// a special size of "noscale" will cause the system
				// to just copy the file to that path/URL instead of 
				// doing any scaling
				if ($size != 'noscale') {
					if (GANGPLANK_SCALER == 'imagemagick') {
					
						// build command string
						$opt_cmds = $this->_generateIMCmds($ul_width, $ul_height, $w, $opts);
						$cmd = "$image_magick_path \"$tmp\" $opt_cmds -resize $size \"$out_fn\" 2>&1";
						$out = `$cmd`;
						if (! empty($out)) gp_die("saveVirtualScaledPhoto(): ImageMagick: Command failed: '$cmd' '$out'");
					} elseif (GANGPLANK_SCALER == 'gd') {
						gp_resize_gd($tmp, $out_fn, $size);
						if (! @filesize($out_fn)) gp_die("saveVirtualScaledPhoto(): gd: Scaling failed");
					} else {
						gp_die("saveVirtualScaledPhoto(): no scaler defined");
					}
				} else {
					copy($tmp, $out_fn);
				}	
							
				$sql .= "$name = '$out_url', ";
			}

			unlink($tmp);
			return $sql;
		}
		
		// --[ The "ForeignKey" Virtual Column Type ]--
		
		// Validate virtual data used for ListGangplank ForeignKey columns. The data must
		// be non-blank, an array, and have these indices:
		// 
		// * $data["foreign_table"] -> the table we'll be joining against
		// * $data["foreign_key"] -> the primary key in that table that we'll
		//   join on
		// * $data["foreign_desc"] -> the column that we'll display as an
		//   identifier of the row.  (this is usually a title or description
		//   field)
		//
		// OPTIONAL:
		//
		// * $data["where_clause"] -> a where clause to use when joining to
		//   other table
		// * $data["desc_format"]  -> a gp_interpUrl()-style string used as a
		//   label for the select button
		//
		// The link_contents_to parameter of ListGangplank::validateVirtualForeignKeyData does
		// not work here.
		function validateVirtualForeignKeyData($data, &$error_text) {
			if (! is_array($data)) {
				$error_text = 'Data must be an array';
				return false;
			}
			if (empty($data['foreign_table'])) {
				$error_text = 'Missing "foreign_table" element';
				return false;
			}
			if (empty($data["foreign_key"])) {
				$error_text = 'Missing "foreign_key" element';
				return false;
			}
			if (empty($data["foreign_desc"])) {
				$error_text = 'Missing "foreign_desc" element';
				return false;
			}
			return true;
		}
		
		// Render a virtual column that refers to another table. 
		function renderVirtualForeignKey($row, $col) {

			$js = $this->buildJsBehaviors($col);
			$name = $col['name'];

			if (!empty($col['help_text'])) 
				$help = "<span class=\"help-text\">$col[help_text]</span>";
			else
				$help = '';
			
			$label = $col['label'];
			if (! empty($col['virtual_data']['link_contents_to'])) {
				$link = $col['virtual_data']['link_contents_to'];
				$label = "<a href=\"$link\">$col[label]</a>";
			}
			
			$local_col = $name;
			$foreign_table = $col['virtual_data']['foreign_table'];
			$foreign_key = $col['virtual_data']['foreign_key'];
			$foreign_desc = $col['virtual_data']['foreign_desc'];
			if (!empty($col['virtual_data']['where_clause'])) 
				$where_clause = 'and ' . gp_interpUrl($col['virtual_data']['where_clause'], $row);
			else
				$where_clause = '';
			
			assert(!empty($this->cols[$local_col]));
			
			$key_value = gp_escapeSql($row[$name]);
			
			$qs = "
				select *
				  from $foreign_table
				 where $foreign_desc <> ''
							 $where_clause
				 order by $foreign_desc
				 ";
			// echo "<p>$qs</p>";
			$foreign_rows = gp_all_rows($qs);

			$html = "
				<label for=\"$name\">
					$label
				</label>
				$help
				<select name=\"$local_col\" id=\"$local_col\" current_value=\"$key_value\" $js>";
			$html .= '<option value="0">(None)</option>';
			foreach ($foreign_rows as $option) {
				if (empty($col['virtual_data']['desc_format']))
					$d = gp_hsc($option[$foreign_desc]);
				else
					$d = gp_interpUrl($col['virtual_data']['desc_format'], $option);
				$k = gp_hsc($option[$foreign_key]);
				$sel = $k == $key_value ? 'selected' : '';
				$html .= "<option value=\"$k\" $sel>$d</option>\n";
			}
			$html .= '</select>';

			return $html;
		}

		function saveVirtualForeignKey($row, $col) {
			$name = $col['name'];
			if(!isset($_POST[$name]))
				$val = 0;
			else 
				$val = $_POST[$name];
			
			$sql = "
				$name = '$val', ";
			return $sql;
		}
				
		// --[ The "MappedKey" Virtual Column Type ]--
		
		// Validate virtual data used for ListGangplank MappedKey columns. The data must
		// be non-blank, an array, and may have these indices:
		// 
		// * $data["local_key"] -> the joining key in our local table (optional; default = pri key)
		// * $data["map_table"] -> the table that holds the mapping
		// * $data["map_local_key"] -> the local key column's name in the map table (optional)
		// * $data["map_foreign_key"] -> the foreign key column's name in the map table (optional)
		// * $data["map_where"] -> a WHERE clause used when doing the mapping (optional)
		// * $data["foreign_table"] -> the foreign table
		// * $data["foreign_key"] -> the primary key in the remote table
		// * $data["foreign_desc"] -> the column that we'll display as an identifier of the row.
		// * $data["foreign_where"] -> a WHERE clause used on the foreign table
		// (this is usually a title or description field)
		//
		function validateVirtualMappedKeyData($data, &$error_text) {
			if (! is_array($data)) {
				$error_text = 'Data must be an array';
				return false;
			}
			/* validate required fields */
			if (empty($data['map_table'])) {
				$error_text = "\"map_table\" (name of table containing mapped relationship) not found.";
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
			/* optional fields */
			if (empty($data['local_key'])) {
				if (empty($this->primary_key)) {
					$error_text = "\"local_key\" not specified and not primary key found.";
					return false;
				}
				$data['local_key'] = $this->primary_key;
			}
			if (empty($data['map_local_key'])) {
				$data['map_local_key'] = $data['local_key'];
			}
			if (empty($data['map_foreign_key'])) {
				$map['map_foreign_key'] = $data['foreign_key'];
			}
			return true;
		}
		
		// Render a virtual column that refers to another table. 
		function renderVirtualMappedKey($row, $col) {
			$name = $col['name'];
			$data = $col['virtual_data'];

			if (!empty($col['help_text'])) 
				$help = "<span class=\"help-text\">$col[help_text]</span>";
			else
				$help = '';
			
			$label = $col['label'];
			if (! empty($data['link_contents_to'])) {
				$link = $data['link_contents_to'];
				$label = "<a href=\"$link\">$col[label]</a>";
			}
			
			$local_key = $data['local_key'];
			$local_key_val = gp_escapeSql($row[$local_key]);
			$map_table = $data['map_table'];
			$map_local_key = $data['map_local_key'];
			$map_foreign_key = $data['map_foreign_key'];
			if (!empty($data['map_where']))
				$map_where = 'and ' . $data['map_where'];
			else
				$map_where = '';
			$foreign_table = $data['foreign_table'];
			$foreign_key = $data['foreign_key'];
			$foreign_desc = $data['foreign_desc'];
			if (!empty($data['foreign_where'])) 
				$foreign_where = 'and ' . $data['foreign_where'];
			else
				$foreign_where = '';
			
			$qs = "
				select $map_foreign_key
				  from $map_table
				 where $map_local_key = '$local_key_val' 
				 			 $map_where ";
			$map = gp_all_rows_as_array($qs, $map_foreign_key);

			$qs = "
				select $foreign_key, $foreign_desc
				  from $foreign_table
				 where $foreign_desc <> ''
							 $foreign_where
				 order by $foreign_desc
				 ";
			$foreign_rows = gp_all_rows($qs);
			
			$html = "
				<label for=\"$name\">
					$label
				</label>
				$help";
			foreach ($foreign_rows as $option) {
				$field_name = $name . '[]';
				$desc = gp_hsc($option[$foreign_desc]);
				$f_key = $option[$foreign_key];
				$check = isset($map[$f_key]) ? 'checked' : '';
				$html .= "<input type=\"checkbox\" name=\"$field_name\" value=\"$f_key\" $check> $desc<br/>\n";
			}
			return $html;
		}

		function saveVirtualMappedKey($row, $col) {
			$data = $col['virtual_data'];
			$name = $col['name'];
			$vals = $_POST[$name];

			$local_key = $data['local_key'];
			$local_key_val = gp_escapeSql($row[$local_key]);
			$map_table = $data['map_table'];
			$map_local_key = $data['map_local_key'];
			$map_foreign_key = $data['map_foreign_key'];
			if (!empty($data['map_where']))
				$map_where = 'and ' . $data['map_where'];
			else
				$map_where = '';
			$foreign_table = $data['foreign_table'];
			$foreign_key = $data['foreign_key'];
			$foreign_desc = $data['foreign_desc'];
			if (!empty($data['foreign_where'])) 
				$foreign_where = 'and ' . $data['foreign_where'];
			else
				$foreign_where = '';
			
			gp_update("
				delete from $map_table
				 where $map_local_key = '$local_key_val' 
				       $map_where ");
			
			if (empty($vals))
				return;
			
			foreach ($vals as $key_to_ins) {
				gp_update("
					insert into $map_table
					($map_local_key, $map_foreign_key)
					values
					('$local_key_val', '$key_to_ins') ");
			}
			
			// don't do anything extra to the main query.
			return '';
		}

		// --[ The "LocalPath" Virtual Column Type ]--
		//
		// Displays a dropdown box where you can select a path in the local file system.
		//
		
		// Validate virtual data used for LocalPath columns. The data must be
		// non-blank, an array, and have these indices:
		// 
		// * $data["local_base_path"] -> the path in which you will be able to select files
		//
		// OPTIONAL:
		// * $data["match_pattern"] -> a regular expression that the filenames must match
		// * $data["link_contents_to"] -> link the label text to this URL
		//
		function validateVirtualLocalPathData($data, &$error_text) {
			if (! is_array($data)) {
				$error_text = 'Data must be an array';
				return false;
			}
			if (empty($data['local_base_path'])) {
				$error_text = "Missing \"local_base_path\" element";
				return false;
			}
			if (!file_exists($data['local_base_path'])) {
				$error_text = "local_base_path ($data[local_base_path]) does not exist";
				return false;
			}
			return true;
		}
		
		// Render a virtual column that refers to another table. 
		function renderVirtualLocalPath($row, $col) {
			$name = $col['name'];
			$val = $row[$name];
			$val_hsc = htmlspecialchars($val);

			if (!empty($col['help_text'])) 
				$help = "<span class=\"help-text\">$col[help_text]</span>";
			else
				$help = '';
			
			$label = $col['label'];
			if (! empty($col['virtual_data']['link_contents_to'])) {
				$link = $col['virtual_data']['link_contents_to'];
				$label = "<a href=\"$link\">$col[label]</a>";
			}
			
			$html = "
				<label for=\"$name\">
					$label
				</label>
				$help
				<select name=\"$name\" id=\"$name\" current_value=\"$val_hsc\">";
			$html .= "<option value=\"\">(None)</option>";
			
			$dir_name = $col['virtual_data']['local_base_path'];
			if (substr($dir_name, -1) != '/')
				$dir_name .= '/';
			$dir = opendir($dir_name);
			assert($dir !== false);
			while (($file = readdir($dir)) !== false) {

				$this_path = $dir_name . $file;

				// skip directories			
				// skip files that start with dot
				if (substr($file, 0, 1) == '.' ||
					  is_dir($this_path)) 
					continue;
					
				if (!empty($col['virtual_data']['match_pattern']) &&
					  !preg_match($col['virtual_data']['match_pattern'], $file))
					continue;
				
				$file_hsc = htmlspecialchars($file);
				$sel = $this_path == $val ? 'selected' : '';
				
				// in $_POST, we only transmit the basename (filename/last part) of the selected
				// file, and then we rejoin it into the full path name in the save function.
				//
				// this is done for security to make it easier to scan for hacks.
				$html .= "<option value=\"$file_hsc\" $sel>$file</option>\n";
			}
			$html .= '</select>';

			return $html;
		}

		function saveVirtualLocalPath($row, $col) {
			$name = $col['name'];
			if(!isset($_POST[$name]))
				$val = '';
			else 
				$val = $_POST[$name];
			
			if (!empty($val)) {
				// check to make sure it doesnt violate securty
				// check for leading periods
				// check for slashes
				// check for back slashes
				if (preg_match("/^\.|.*\/.*|.*\\\.*/", $val))
					gp_die("Bad selected path: $val");
				
				if (!empty($col['virtual_data']['match_pattern']) &&
						!preg_match($col['virtual_data']['match_pattern'], $val))
					gp_die("Bad selected path: $val, does not match filter");
				
				$path = $col['virtual_data']['local_base_path'];
				if (substr($path, -1) != '/')
					$path .= '/';
				$path .= $val;
			} else {
				$path = '';
			}
			
			$sql = "
				$name = '$path', ";
			return $sql;
		}
				
		function buildWhereClause() {
		
			if ($this->primary_key && ! $this->primary_key_value)
				if (isset($_REQUEST[$this->primary_key]))
					$this->primary_key_value = $_REQUEST[$this->primary_key];
				else
					$this->primary_key_value = $GLOBALS[$this->primary_key];
			
			if (empty($this->where) || $this->where == '1=1') {
				if (empty($this->primary_key_value))
					gp_die("buildWhereClause(): Could not determine primary key \"$this->primary_key\" value");
				$this->where = "$this->primary_key = '$this->primary_key_value' ";
			}
		}
		
		function renderHeader() {
			$html  = "<form method=\"post\" enctype=\"multipart/form-data\" id=\"$this->html_id\" class=\"$this->html_class\">";
			$html .= $this->renderButtons('top');
			if ($this->preform_callback)
				$html .= call_user_func($this->preform_callback);
			return $html;
		}
		
		function renderBody() {
			// should have already happened but let's be safe.
			$this->loadColumns();
			// Build the where clause that we'll use to isolate this record.
			// Usually this is where primary_key = '$primary_key' but you can
			// override it (for no reason)			
			$this->buildWhereClause();
			$row = $this->getData();

			$html = '';
			
			foreach ($this->passed_form_values as $k=>$v) {
				$html .= "<input type=\"hidden\" name=\"$k\" id=\"$k\" value=\"" . htmlspecialchars($v) . "\">\n";
			}
			
			$visible_cols = $this->visibleColumns();
			foreach ($visible_cols as $col) {
				$html .= $this->renderColumn($row, $col);
			}
			return $html;
		}
		
		function renderFooter() {
			$html = '';
			if ($this->postform_callback)
				$html .= call_user_func($this->postform_callback);
			$html .= $this->renderButtons();
			$html .= "</form>\n";
			return $html;
		}

		function render() {
			
			$html  = '';
			$html .= $this->renderHeader();
			$html .= $this->renderBody();
			$html .= $this->renderFooter();			
			
			return $html;
		}
		
		function saveChanges() {
			$row = $this->getData();
			$all_cols = $this->columns();
			$vis_cols = $this->visibleColumns();
			
			// first, reassemble any date fields
			if (!empty($_POST['reassemble_fields'])) 
				foreach ($_POST['reassemble_fields'] as $f) {
					$nt = $all_cols[substr($f, 2)]['native_type'];
					if (!$nt)
						gp_die("handleRequest(): cannot reassemble '$f' -> unknown column");
					if ($nt == 'datetime') {
						$_POST[$f] = gp_f_date_time_reassemble($f);
						continue;
					} 
					if ($nt == 'date') {
						$_POST[$f] = gp_f_date_reassemble($f);
						continue;
					} 
					// fallthrough: shouldnt happen
					gp_die("handleRequest(): don't know how to reassemble '$f' -> unknown type '$nt'");
				}
			
			if ($this->validation_callback) 
				if (!call_user_func($this->validation_callback))
					return false;
			
			$sql = "
				update $this->data_source
					 set\n";
			
			foreach ($vis_cols as $col) {
				$tmp = 'x_' . $col['name'];
			
				if ($col['is_virtual']) {
					// call a virtual save method (saveVirtual$name) to process the field. this should return some SQL
					// to add to our query. 
					if (! method_exists($this, "saveVirtual$col[type]")) gp_die("handleRequest(): Handler for $col[type] (\$this->saveVirtual$col[type]) not defined.");
					$sql .= call_user_func(array(&$this, "saveVirtual$col[type]"), $row, $col);
					
				} else {
					// non-virtual regular column; try to pull a value from POST
					// to save..
					
					if ($col['native_type'] == 'boolean' &&
						!isset($_POST[$tmp]))
						$_POST[$tmp] = 0;
					
					if (isset($_POST[$tmp])) {
						$v = gp_escapeSqlMaybe($_POST[$tmp]);	// we're assuming addSlashes() or unfck() is active
						$sql .= "$col[name] = '$v',\n";
					} 
				}
			}
			
			$this->buildWhereClause();
			$sql = substr($sql, 0, -2);
			$sql .= "
				where
					($this->where) 
					";
			// echo $sql;
			gp_update($sql);

			$row = $this->getData();
			
			if ($this->save_callback) 
				call_user_func($this->save_callback, $row);
			
			// global $msgs;
			// $msgs[] = "Changes to $this->singular saved.";
		}

		function cloneRecord($row) {
			$pk_col = '';
			foreach ($this->cols as $col=>$attrs) {
				if ($attrs['is_primary_key']) {
					unset($row[$col]);
					$pk_col = $col;
				}
			}
			$new_pk = gp_insert_row($this->data_source, $row);
			$url = gp_my_url();
			if ($pk_col) {
				$url = gp_remove_url_arg($url, $pk_col);
				$url = gp_add_url_arg($url, $pk_col, $new_pk);
				$url = gp_add_url_arg($url, 'msgs[]', "You are editing the {$this->singular} clone.");
			} else {
				$url = $this->plural_ws . GANGPLANK_EXT . '?msgs[]=' . urlencode("{$this->singular} cloned.");
			}
			gp_goto($url);
			exit;
		}

		// Handle an incoming request; this could mean to save or delete the record.
		function handleRequest($force = false) {
			// should have already happened but let's be safe.
			$this->loadColumns();
			$this->buildWhereClause();

			$delete_button_name = $this->deleteButtonName();
			if (! empty($_POST[$delete_button_name])) {
				if (empty($this->where) || $this->where == '1=1') 
					gp_die('handleRequest(): Could not determine where clause');
				
				$row = $this->getData();
				
				$sql = "
					delete from $this->data_source
				 	 where ($this->where)";
				gp_one_row($sql);
				
				if ($this->delete_callback) 
					call_user_func($this->delete_callback, $row);
				
				gp_goto(gp_interpUrl($this->getButtonPostUrl('delete'), $row));

			}
			
			$save_button_name = $this->saveButtonName();
			if ($force || !empty($_POST[$save_button_name])) {
				$this->saveChanges();
				$row = $this->getData();
				gp_goto(gp_interpUrl($this->getButtonPostURL('save'), $row));
			}
			
			$row = $this->getData();
			foreach ($this->buttons as $button) {
				$id = $button['id'];
				if (!empty($_POST[$id])) {
					$this->saveChanges();
					if (!empty($button['callback']))
						call_user_func($button['callback'], $row);
					else
						gp_goto(gp_interpUrl($button['post_url'], $row));
				}
			}
		}
	}
?>
