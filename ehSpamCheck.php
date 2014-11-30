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
define('EH_SPECIALGROUPS', '3:9:22:48:51:102:119');
define('EH_TAG_ID', 35);
define('EH_SUSPECT_EMAIL', 'gmail:yahoo:hotmail:aol');
define('EH_EMAIL_EXTENSIONS', '.com:.co.uk');

// to be changed for local/server runs
require_once($_SERVER['DOCUMENT_ROOT'].'/sites/kabissa.org/civicrm.settings.php');

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
    if (is_contact_suspect($contact) == TRUE) {
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
CRM_Utils_System::redirect(CRM_Utils_System::url('civicrm/dashboard', 'reset=1'));
/**
 * Function to check contact
 * 
 * @param array $contact
 * @return boolean $is_contact_suspect
 */
function is_contact_suspect($contact) {
  $is_contact_suspect = FALSE;
  if (isset($contact['display_name'])) {
    $is_contact_suspect = is_suspect_name($contact['display_name']);
  }
  if (isset($contact['email']) && $is_contact_suspect == FALSE) {
    $is_contact_suspect = is_suspect_email($contact['email']);
  }
  return $is_contact_suspect;
}
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
  $suspect_extensions = explode(':', EH_EMAIL_EXTENSIONS);
  $suspect_email_orgs = explode(':', EH_SUSPECT_EMAIL);
  $email_parts = explode('@', $email);
  if (count($email_parts) > 2) {
    return TRUE;
  } else {
    $email_second_part = split_email_second_part($email_parts[1]);
    if (!empty($email_second_part) && in_array($email_second_part['ext'], $suspect_extensions) 
        && in_array($email_second_part['org'], $suspect_email_orgs)) {
        return is_suspect_name($email_parts[0]);
    }
  }
  return FALSE;
}
/**
 * Function to split second part of emailadress e.g. gmail.com becomes
 * ['org'] = gmail and ['ext'] = .com
 */
function split_email_second_part($email_part) {
  $email_second_part = array();
  $split = strpos($email_part, '.');
  if ($split != FALSE) {
    $email_second_part['org'] = substr($email_part, 0, $split);
    $email_second_part['ext'] = substr($email_part, $split);
  }
  return $email_second_part;
}
/**
 * Function to flag contacts as ToBeSpamChecked
 */
function process_flag($contact_id) {
  if (!empty($contact_id)) {
    $params = array(
      'version' => 3,
      'entity_id' => $contact_id,
      'tag_id' => EH_TAG_ID);
    civicrm_api('EntityTag', 'Create', $params);
  }
}
/**
 * Function to trash contacts
 */
function process_trash($contact_id) {
  if (!empty($contact_id)) {
    $params = array(
      'version' => 3,
      'id' => $contact_id,
      'is_deleted' => 1);
    civicrm_api('Contact', 'Update', $params);
  }
}