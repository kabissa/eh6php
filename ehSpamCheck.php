<?php
/*
 * Erik Hommel <hommel@ee-atwork.nl> 30 Oct 2014
 */

ini_set( 'display_errors', '1' );
set_time_limit(0);

/*
 * Define constants to be used in the rest of the script:
 * EH_CONTACTLIMIT - the limit of the number of contacts to process in one run
 * EH_SPECIALGROUPS - the groups that if the contact belongs to one of those
 *                    the contact will not be deleted but flagged with a
 *                    special tag (see next one)
 * EH_TAG_ID - the special tag that members of the groups above will be flagged
 *             with if they are suspect
 */
define('EH_CONTACTLIMIT', 50);
define('EH_SPECIALGROUPS', '9:3:102');
define('EH_TAG_ID', 35);

// to be changed for local/server runs
require_once($_SERVER['DOCUMENT_ROOT'].
	'/speel6/sites/default/civicrm.settings.php');

require_once 'CRM/Core/Config.php';
$config = CRM_Core_Config::singleton( );

$suspects = array();
$group_members = array();
$count_flagged = 0;
$count_trashed = 0;

$contacts = get_contacts_to_be_checked();
foreach ($contacts as $contact) {
  if (is_participant($contact['contact_id']) == FALSE) {
    if (is_in_special_groups($contact['contact_id']) == TRUE) {
      $group_members[] = $contact['contact_id'];
    } 
    if (is_suspect_name($contact['display_name']) == TRUE || 
      is_suspect_email($contact['email']) == TRUE) {
      $suspects[] = $contact['contact_id'];
    }
  }
}
unset($contacts);
foreach ($suspects as $suspect_contact_id) {
  if (in_array($group_members)) {
    process_flag($suspect_contactId);
    $count_flagged++;
  } else {
    process_trash($suspect_contact_id);
    $count_trashed++;
  }
}
echo "<p>".$count_trashed." contacts trashed, ".$count_flagged.
  " flagged as ToBeSpamChecked</p>";
/**
 * Function to get contacts to be checked
 */
function get_contacts_to_be_checked() {
  $params = array(
    'version' => 3,
    'is_deleted' => 0,
    'contact_type' => 'Individual',
    'options' => array('limit' => EH_CONTACTLIMIT));
  $contacts = civicrm_api('Contact', 'Get', $params);
  if (!civicrm_error($contacts)) {
    return $contacts['values'];
  } else {
    return array();
  }
}
/**
 * Function to check if the contact has been participant at any event
 */
function is_participant($contact_id) {
  $query = 'SELECT COUNT(*) as count_participant FROM civicrm_participant WHERE '
    . 'contact_id = %1';
  $params = array(1 => array($contact_id, 'Positive'));
  $dao = CRM_Core_DAO::executeQuery($query, $params);
  if ($dao->fetch()) {
    if ($dao->count_participant > 0) {
      return TRUE;
    }
  }
  return FALSE;
}
/**
 * Function to check if the contact is member of one of the special groups
 */
function is_in_special_groups($contact_id) {
  $special_groups = explode(':', EH_SPECIALGROUPS);
  $params = array(
    'version' => 3,
    'contact_id' => $contact_id,
    'options' => array('limit' => 99999));
  $contact_groups = civicrm_api('GroupContact', 'Get', $params);
  foreach ($contact_groups['values'] as $contact_group) {
    if (in_array($contact_group['group_id'], $special_groups)) {
      return TRUE;
    }
  }
  return FALSE;
}
/**
 * Function to check if the name has a digit in it and is therefore suspect
 */
function is_suspect_name($name) {
  $name_length = strlen($name);
  for ($start = 0; $start < $name_length; $start++) {
    if (is_numeric(substr($name, $start, 1))) {
      return TRUE;
    }
  }
  return FALSE;
}
/**
 * Function to check if the email address is suspect
 */
function is_suspect_email($email) {
  return FALSE;
}
/**
 * Function to flag contacts as ToBeSpamChecked
 */
function process_flag($contact_id) {
  
}
/**
 * Function to trash contacts
 */
function process_trash($contact_id) {
  
}