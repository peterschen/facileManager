<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2013 The facileManager Team                               |
 |                                                                         |
 | This program is free software; you can redistribute it and/or           |
 | modify it under the terms of the GNU General Public License             |
 | as published by the Free Software Foundation; either version 2          |
 | of the License, or (at your option) any later version.                  |
 |                                                                         |
 | This program is distributed in the hope that it will be useful,         |
 | but WITHOUT ANY WARRANTY; without even the implied warranty of          |
 | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the           |
 | GNU General Public License for more details.                            |
 +-------------------------------------------------------------------------+
 | facileManager: Easy System Administration                               |
 | fmFirewall: Easily manage one or more software firewalls                |
 +-------------------------------------------------------------------------+
 | http://www.facilemanager.com/modules/fmfirewall/                        |
 +-------------------------------------------------------------------------+
*/

class fm_module_time {
	
	/**
	 * Displays the time list
	 */
	function rows($result) {
		global $fmdb;
		
		if (!$result) {
			printf('<p id="table_edits" class="noresult" name="time">%s</p>', __('There are no time restrictions defined.'));
		} else {
			$num_rows = $fmdb->num_rows;
			$results = $fmdb->last_result;
			
			$table_info = array(
							'class' => 'display_results',
							'id' => 'table_edits',
							'name' => 'time'
						);

			$title_array = array(__('Restriction Name'), __('Date Range'), __('Time'), __('Weekdays'), array('title' => __('Comment'), 'style' => 'width: 30%;'));
			if (currentUserCan('manage_time', $_SESSION['module'])) $title_array[] = array('title' => __('Actions'), 'class' => 'header-actions');

			echo displayTableHeader($table_info, $title_array);
			
			for ($x=0; $x<$num_rows; $x++) {
				$this->displayRow($results[$x]);
			}
			
			echo "</tbody>\n</table>\n";
		}
	}

	/**
	 * Adds the new time
	 */
	function add($post) {
		global $fmdb, $__FM_CONFIG;
		
		/** Validate entries */
		$post = $this->validatePost($post);
		if (!is_array($post)) return $post;
		
		$sql_insert = "INSERT INTO `fm_{$__FM_CONFIG[$_SESSION['module']]['prefix']}time`";
		$sql_fields = '(';
		$sql_values = null;
		
		$post['account_id'] = $_SESSION['user']['account_id'];
		
		$exclude = array('submit', 'action', 'time_id', 'compress', 'AUTHKEY', 'module_name', 'module_type', 'config',
						'time_start_time_hour', 'time_start_time_min', 'time_end_time_hour', 'time_end_time_min');

		foreach ($post as $key => $data) {
			$clean_data = sanitize($data);
			if (($key == 'time_name') && empty($clean_data)) return __('No time name defined.');
			if (!in_array($key, $exclude)) {
				$sql_fields .= $key . ',';
				$sql_values .= "'$clean_data',";
			}
		}
		$sql_fields = rtrim($sql_fields, ',') . ')';
		$sql_values = rtrim($sql_values, ',');
		
		$query = "$sql_insert $sql_fields VALUES ($sql_values)";
		$result = $fmdb->query($query);
		
		if (!$fmdb->result) return __('Could not add the time because a database error occurred.');

		/** Format weekdays */
		$weekdays = $this->formatDays($post['time_weekdays']);
		
		addLogEntry("Added time restriction:\nName: {$post['time_name']}\nDates: " . $this->formatDates($post['time_start_date'], $post['time_end_date']) . "\n" .
					"Time: {$post['time_start_time']} &rarr; {$post['time_end_time']}\nWeekdays: " . $this->formatDays($post['time_weekdays']) .
					"\nComment: {$post['time_comment']}");
		return true;
	}

	/**
	 * Updates the selected time
	 */
	function update($post) {
		global $fmdb, $__FM_CONFIG;
		
		/** Validate entries */
		$post = $this->validatePost($post);
		if (!is_array($post)) return $post;
		
		$exclude = array('submit', 'action', 'time_id', 'compress', 'AUTHKEY', 'module_name', 'module_type', 'config',
						'time_start_time_hour', 'time_start_time_min', 'time_end_time_hour', 'time_end_time_min');

		$sql_edit = null;
		
		foreach ($post as $key => $data) {
			if (!in_array($key, $exclude)) {
				$sql_edit .= $key . "='" . sanitize($data) . "',";
			}
		}
		$sql = rtrim($sql_edit, ',');
		
		// Update the time
		$old_name = getNameFromID($post['time_id'], 'fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'time', 'time_', 'time_id', 'time_name');
		$query = "UPDATE `fm_{$__FM_CONFIG[$_SESSION['module']]['prefix']}time` SET $sql WHERE `time_id`={$post['time_id']} AND `account_id`='{$_SESSION['user']['account_id']}'";
		$result = $fmdb->query($query);
		
		if (!$fmdb->result) return __('Could not update the time because a database error occurred.');
		
		/** Return if there are no changes */
		if (!$fmdb->rows_affected) return true;

//		setBuildUpdateConfigFlag(getServerSerial($post['time_id'], $_SESSION['module']), 'yes', 'build');
		
		addLogEntry("Updated time restriction '$old_name' to:\nName: {$post['time_name']}\nDates: " . $this->formatDates($post['time_start_date'], $post['time_end_date']) . "\n" .
					"Time: {$post['time_start_time']} &rarr; {$post['time_end_time']}\nWeekdays: " . $this->formatDays($post['time_weekdays']) .
					"\nComment: {$post['time_comment']}");
		return true;
	}
	
	/**
	 * Deletes the selected time
	 */
	function delete($time_id) {
		global $fmdb, $__FM_CONFIG;
		
		/** Does the time_id exist for this account? */
		basicGet('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'time', $time_id, 'time_', 'time_id');
		if ($fmdb->num_rows) {
			/** Is the time_id present in a policy? */
			if (isItemInPolicy($time_id, 'time')) return __('This schedule could not be deleted because it is associated with one or more policies.');
			
			/** Delete time */
			$tmp_name = getNameFromID($time_id, 'fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'time', 'time_', 'time_id', 'time_name');
			if (updateStatus('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'time', $time_id, 'time_', 'deleted', 'time_id')) {
				addLogEntry(sprintf(__('Time restriction (%s) was deleted.'), $tmp_name));
				return true;
			}
		}
		
		return __('This time restriction could not be deleted.');
	}


	function displayRow($row) {
		global $__FM_CONFIG;
		
		$disabled_class = ($row->time_status == 'disabled') ? ' class="disabled"' : null;
		
		$edit_status = null;
		
		if (currentUserCan('manage_time', $_SESSION['module'])) {
			$edit_status = '<a class="edit_form_link" href="#">' . $__FM_CONFIG['icons']['edit'] . '</a>';
			$edit_status .= '<a class="status_form_link" href="#" rel="';
			$edit_status .= ($row->time_status == 'active') ? 'disabled' : 'active';
			$edit_status .= '">';
			$edit_status .= ($row->time_status == 'active') ? $__FM_CONFIG['icons']['disable'] : $__FM_CONFIG['icons']['enable'];
			$edit_status .= '</a>';
			if (!isItemInPolicy($row->time_id, 'time')) $edit_status .= '<a href="#" class="delete">' . $__FM_CONFIG['icons']['delete'] . '</a>';
			$edit_status = '<td id="edit_delete_img">' . $edit_status . '</td>';
		}
		
		/** Format date range */
		$date_range = $this->formatDates($row->time_start_date, $row->time_end_date);
		
		/** Format weekdays */
		$weekdays = $this->formatDays($row->time_weekdays);

		$comments = nl2br($row->time_comment);
		
		echo <<<HTML
			<tr id="$row->time_id"$disabled_class>
				<td>$row->time_name</td>
				<td>$date_range</td>
				<td>$row->time_start_time &rarr; $row->time_end_time</td>
				<td>$weekdays</td>
				<td>$comments</td>
				$edit_status
			</tr>

HTML;
	}

	/**
	 * Displays the form to add new time
	 */
	function printForm($data = '', $action = 'add') {
		global $__FM_CONFIG;
		
		$time_weekdays = $time_id = 0;
		$time_name = $time_comment = null;
		$time_start_date = $time_start_time = $time_end_date = $time_end_time = null;
		$ucaction = ucfirst($action);
		
		if (!empty($_POST) && !array_key_exists('is_ajax', $_POST)) {
			if (is_array($_POST))
				extract($_POST);
		} elseif (@is_object($data[0])) {
			extract(get_object_vars($data[0]));
		}

		/* Time options */
		for ($x = 0; $x < 24; $x++) {
			$houropt[$x][] = sprintf("%02d", $x);
			$houropt[$x][] = sprintf("%02d", $x);
		}
		for ($x = 0; $x < 60; $x++) {
			$minopt[$x][] = sprintf("%02d", $x);
			$minopt[$x][] = sprintf("%02d", $x);
		}
		@list($start_hour, $start_min) = explode(':', $time_start_time);
		@list($end_hour, $end_min) = explode(':', $time_end_time);
		
		$time_name_length = getColumnLength('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'time', 'time_name');
		
		$time_start_hour = BuildSelect('time_start_time_hour', 1, $houropt, $start_hour, 1);
		$time_start_min = BuildSelect('time_start_time_min', 1, $minopt, $start_min, 1);

		$time_end_hour = BuildSelect('time_end_time_hour', 1, $houropt, $end_hour, 1);
		$time_end_min = BuildSelect('time_end_time_min', 1, $minopt, $end_min, 1);

		/** Weekdays */
		$weekdays_form = null;
		foreach ($__FM_CONFIG['weekdays'] as $day => $bit) {
			$weekdays_form .= '<label><input type="checkbox" name="time_weekdays[' . $bit . ']" ';
			if ($bit & $time_weekdays) $weekdays_form .= 'checked';
			$weekdays_form .= '/>' . $day . "</label>\n";
		}

		$popup_title = $action == 'add' ? __('Add Restriction') : __('Edit Restriction');
		$popup_header = buildPopup('header', $popup_title);
		$popup_footer = buildPopup('footer');
		
		$return_form = sprintf('<form name="manage" id="manage" method="post" action="">
		%s
			<input type="hidden" name="action" value="%s" />
			<input type="hidden" name="time_id" value="%d" />
			<table class="form-table">
				<tr>
					<th width="33&#37;" scope="row"><label for="time_name">%s</label></th>
					<td width="67&#37;"><input name="time_name" id="time_name" type="text" value="%s" size="40" maxlength="%d" /></td>
				</tr>
				<tr>
					<th width="33&#37;" scope="row"><label for="time_start_date">%s</label></th>
					<td width="67&#37;"><input name="time_start_date" id="time_start_date" type="date" value="%s" size="40" class="datepicker" /></td>
				</tr>
				<tr>
					<th width="33&#37;" scope="row"><label for="time_start_time">%s</label></th>
					<td width="67&#37;">%s</td>
				</tr>
				<tr>
					<th width="33&#37;" scope="row"><label for="time_end_date">%s</label></th>
					<td width="67&#37;"><input name="time_end_date" id="time_end_date" type="date" value="%s" size="40" class="datepicker" /></td>
				</tr>
				<tr>
					<th width="33&#37;" scope="row"><label for="time_end_time">%s</label></th>
					<td width="67&#37;">%s</td>
				</tr>
				<tr>
					<th width="33&#37;" scope="row">%s</th>
					<td width="67&#37;" style="white-space: nowrap;">%s</td>
				</tr>
				<tr>
					<th width="33&#37;" scope="row"><label for="time_comment">%s</label></th>
					<td width="67&#37;"><textarea id="time_comment" name="time_comment" rows="4" cols="30">%s</textarea></td>
				</tr>
			</table>
		%s
		</form>
		<script>
			$(document).ready(function() {
				$("#manage select").select2({
					width: "70px",
					minimumResultsForSearch: 10
				});
			});
		</script>',
				$popup_header,
				$action, $time_id,
				__('Name'), $time_name, $time_name_length,
				__('Start Date'), $time_start_date,
				__('Start Time'), "$time_start_hour : $time_start_min",
				__('End Date'), $time_end_date,
				__('End Time'), "$time_end_hour : $time_end_min",
				__('Weekdays'), $weekdays_form,
				__('Comment'), $time_comment,
				$popup_footer
			);

		return $return_form;
	}
	
	
	function validatePost($post) {
		global $fmdb, $__FM_CONFIG;
		
		if (empty($post['time_name'])) return __('No name defined.');
		
		/** Check name field length */
		$field_length = getColumnLength('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'time', 'time_name');
		if ($field_length !== false && strlen($post['time_name']) > $field_length) return sprintf(dngettext($_SESSION['module'], 'Name is too long (maximum %d character).', 'Name is too long (maximum %d characters).', $field_length), $field_length);
		
		/** Does the record already exist for this account? */
		basicGet('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'time', $post['time_name'], 'time_', 'time_name', "AND time_id!={$post['time_id']}");
		if ($fmdb->num_rows) return __('This name already exists.');
		
		/** Process time */
		$post['time_start_time'] = $post['time_start_time_hour'] . ':' . $post['time_start_time_min'];
		$post['time_end_time'] = $post['time_end_time_hour'] . ':' . $post['time_end_time_min'];
		
		/** Process weekdays */
		if (@is_array($post['time_weekdays'])) {
			$decimals = 0;
			foreach ($post['time_weekdays'] as $dec => $checked) {
				$decimals += $dec;
			}
			$post['time_weekdays'] = $decimals;
		} else $post['time_weekdays'] = 0;
		
		/** Process dates */
		if (empty($post['time_start_date'])) unset($post['time_start_date']);
		if (empty($post['time_end_date'])) unset($post['time_end_date']);
		
		return $post;
	}
	
	
	function formatDates($start, $end) {
		/** Format date range */
		if (!$start && !$end) {
			return __('Everyday');
		} elseif (!$start) {
			return __('Creation') . ' &rarr; ' . date(getOption('date_format', $_SESSION['user']['account_id']), strtotime($end));
		} elseif (!$end) {
			return date(getOption('date_format', $_SESSION['user']['account_id']), strtotime($start)) . ' &rarr; ' . __('Infinity');
		} else {
			return date(getOption('date_format', $_SESSION['user']['account_id']), strtotime($start)) . ' &rarr; ' . date(getOption('date_format', $_SESSION['user']['account_id']), strtotime($end));
		}
		
	}
	
	
	function formatDays($weekdays_bits) {
		global $__FM_CONFIG;
		
		/** Format weekdays */
		if (!$weekdays_bits || $weekdays_bits == array_sum($__FM_CONFIG['weekdays'])) {
			return __('Everyday');
		} else {
			$weekdays = null;
			foreach ($__FM_CONFIG['weekdays'] as $day => $bit) {
				if ($weekdays_bits & $bit) $weekdays .= $day . ', ';
			}
			return rtrim($weekdays, ', ');
		}
	}
	
}

if (!isset($fm_module_time))
	$fm_module_time = new fm_module_time();

?>
