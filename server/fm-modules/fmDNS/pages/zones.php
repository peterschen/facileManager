<?php

/**
 * Processes zones config page
 *
 * @author		Jon LaBass
 * @version		$Id:$
 * @copyright	2013
 *
 */

$map = (isset($_GET['map'])) ? strtolower($_GET['map']) : 'forward';

$page_name = 'Zones';
$page_name_sub = ($map == 'forward') ? 'Forward' : 'Reverse';

include(ABSPATH . 'fm-modules/fmDNS/classes/class_zones.php');

$map = (isset($_GET['map'])) ? strtolower($_GET['map']) : 'forward';
$map = (isset($_POST['createZone'][0]['domain_mapping'])) ? strtolower($_POST['createZone'][0]['domain_mapping']) : $map;

$response = isset($response) ? $response : null;

if ($allowed_to_manage_zones) {
	if (isset($_POST['action']) && $_POST['action'] == 'reload') {
		if (isset($_POST['domain_id']) && !empty($_POST['domain_id'])) {
//			$response = $fm_dns_zones->buildZoneConfig($_POST['domain_id']);
		} else header('Location: ' . $GLOBALS['basename'] . '?map=' . $map);
		unset($_POST);
	}
	
	$action = (isset($_REQUEST['action'])) ? $_REQUEST['action'] : 'create';
	switch ($action) {
	case 'create':
		if (!empty($_POST)) {
			$insert_id = $fm_dns_zones->add();
			if (!is_numeric($insert_id)) {
				$response = '<p class="error">' . $insert_id . '</p>'. "\n";
				$form_data = $_POST;
			} else header('Location: zone-records.php?map=' . $map . '&domain_id=' . $insert_id . '&record_type=SOA');
		}
		break;
	case 'delete':
		if (isset($_GET['domain_id'])) {
			$zone_delete_status = $fm_dns_zones->delete(sanitize($_GET['domain_id']));
			if ($zone_delete_status !== true) {
				$response = '<p class="error">' . $zone_delete_status . '</p>'. "\n";
				$action = 'create';
			} else header('Location: ' . $GLOBALS['basename'] . '?map=' . $map);
		}
		break;
	case 'edit':
		if (!empty($_POST)) {
			$zone_update_status = $fm_dns_zones->update();
			if ($zone_update_status !== true) {
				$response = '<p class="error">' . $zone_update_status . '</p>'. "\n";
				$form_data = $_POST;
			} else header('Location: ' . $GLOBALS['basename'] . '?map=' . $map);
		}
		if (isset($_GET['status'])) {
			if (!updateStatus('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'domains', $_GET['domain_id'], 'domain_', $_GET['status'], 'domain_id')) {
				$response = '<p class="error">This item could not be '. $_GET['status'] .'.</p>'. "\n";
			} else header('Location: ' . $GLOBALS['basename']);
		}
		break;
	case 'download':
		if (array_key_exists('domain_id', $_POST) && is_numeric($_POST['domain_id'])) {
			include(ABSPATH . 'fm-modules/facileManager/classes/class_accounts.php');
			include(ABSPATH . 'fm-modules/fmDNS/classes/class_buildconf.php');
			
			$data['SERIALNO'] = -1;
			$data['compress'] = 0;
			$data['dryrun'] = true;
			$data['domain_id'] = sanitize($_POST['domain_id']);
		
			basicGet('fm_accounts', $_SESSION['user']['account_id'], 'account_', 'account_id');
			$account_result = $fmdb->last_result;
			$data['AUTHKEY'] = $account_result[0]->account_key;
		
			$raw_data = $fm_module_buildconf->buildZoneConfig($data);
		
			if (!is_array($raw_data)) {
				$zone_contents = unserialize($raw_data);
			} else {
				$zone_contents = null;
				foreach ($raw_data['files'] as $filename => $contents) {
					$zone_contents .= $contents . "\n\n";
				}
				$tmp_file = TMP_FILE_EXPORTS . $filename . date("Ymdhis");
				if (!file_put_contents($tmp_file, $zone_contents)) {
					$response = '<p>Zone file export failed to write to temp file: ' . $tmp_file . '. Please correct and try again.</p>';
					break;
				}
				
				if (is_file($tmp_file)) {
//					exec(findProgram('zip') . ' -j ' . $tmp_file . '.zip ' . $tmp_file);
//					echo findProgram('zip') . ' -j ' . $tmp_file . '.zip ' . $tmp_file; exit;
//					$gzdata = gzencode($zone_contents, 9);
					file_put_contents($tmp_file . '.gz', gzencode($zone_contents, 9));
//					$fm = fopen($tmp_file . '.gz', 'w');
//					gzwrite($fm, $zone_contents);
//					gzclose($fm);
					header('Content-type: application/x-download');
					header('Content-Encoding: gzip');
					header('Content-Disposition: attachment; filename=' . basename($filename) . '.gz');
//					header("Content-Length: " . strlen($gzdata));
					header('Content-Transfer-Encoding: binary');
//					echo $gzdata;
					readfile($tmp_file . '.gz');
					unlink($tmp_file . '.gz');
					unlink($tmp_file);
					exit;
				}
			}
		} else header('Location: ' . $GLOBALS['basename']);
		break;
	}
}

printHeader();
@printMenu($page_name, $page_name_sub);

/** Check if any servers need their configs built first */
$reload_allowed = $fm_dns_zones->reloadAllowed();
if (!$reload_allowed && !$response) $response = '<p>You currently have no name servers hosting zones.  <a href="' . $__FM_CONFIG['menu']['Config']['Servers'] . '">Click here</a> to manage one or more servers.</p>';

if (!empty($response)) echo '<div id="response">' . $response . "</div>\n";
echo '<div id="response" style="display: none;"></div>' . "\n";
echo '<div id="body_container"';
if (!empty($response)) echo ' style="margin-top: 4em;"';
echo '>
	<h2>Zones';

if ($allowed_to_manage_zones) {
	echo '<a id="plus" name="' . $map . '" href="#" title="Add New">' . $__FM_CONFIG['icons']['add'] . '</a>';
}

echo '</h2>' . "\n";
	
$result = basicGetList('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'domains', 'domain_name', 'domain_', "AND domain_mapping='$map' AND domain_clone_domain_id='0'");
$fm_dns_zones->rows($result, $map, $reload_allowed);

printFooter();

?>
