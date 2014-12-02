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
define('EH_CONTACTLIMIT', 2500);
define('EH_SPECIALGROUPS', '3:9:22:48:51:102:119');
define('EH_TAG_ID', 35);
define('EH_SUSPECT_EMAIL', 'gmail:yahoo:hotmail:aol');
define('EH_EMAIL_EXTENSIONS', '.com:.co.uk');
define('EH_PROCESSED_GROUP', 138);

// to be changed for local/server runs
require_once($_SERVER['DOCUMENT_ROOT'].'/sites/kabissa.org/civicrm.settings.php');
//require_once($_SERVER['DOCUMENT_ROOT'].'/speel6/sites/default/civicrm.settings.php');

require_once 'CRM/Core/Config.php';
$config = CRM_Core_Config::singleton( );

$suspects = array();
$group_members = array();
$log_flagged = array();
$log_trashed = array();

$query = 'SELECT 
  civicontact.id AS civicrm_contact_id, 
  civicontact.display_name AS civicrm_contact_display_name, 
  civiemail.email AS civicrm_email_email 
  FROM civicrm_contact civicontact
  LEFT JOIN civicrm_email civiemail ON civicontact.id = civiemail.contact_id AND is_primary = %1
  WHERE civicontact.contact_type = %2 AND 
  civicontact.id NOT IN (SELECT contact_id FROM civicrm_group_contact WHERE group_id = %3) LIMIT %4';
$params = array(
  1 => array(1, 'Positive'),
  2 => array('Individual', 'String'),
  3 => array(EH_PROCESSED_GROUP, 'Positive'),
  4 => array(EH_CONTACTLIMIT, 'Positive'));
$dao = CRM_Core_DAO::executeQuery($query, $params);

while ($dao->fetch()) {
  flag_contact_as_processed($dao->civicrm_contact_id);
  if (is_participant($dao->civicrm_contact_id) == FALSE && is_linked_to_org($dao->civicrm_contact_id) == FALSE) {
    if (is_in_special_groups($dao->civicrm_contact_id) == TRUE) {
      $group_members[] = $dao->civicrm_contact_id;
    }
    if (is_contact_suspect($dao) == TRUE) {
      if (in_array($dao->civicrm_contact_id, $group_members)) {
        process_flag($dao->civicrm_contact_id);
        $log_flagged[] = 'Contact ID '.$dao->civicrm_contact_id.' with name '.$dao->civicrm_contact_display_name;
      } else {
        process_trash($dao->civicrm_contact_id);
        $log_trashed[] = 'Contact ID '.$dao->civicrm_contact_id.' with name '.$dao->civicrm_contact_display_name;
      }
      $suspects[] = $dao->civicrm_contact_id;
    } 
  }
}
echo '<h3>Log of spamcheck run, running for '.EH_CONTACTLIMIT,' contacts.</h3>';
echo '<p>Contacts tagged as ToBeSpamChecked : </p><ul>';
foreach ($log_flagged as $flagged) {
  echo '<li>'.$flagged.'</li>';
}
echo '</ul><p>Contacts trashed : </p><ul>';
foreach ($log_trashed as $trashed) {
  echo '<li>'.$trashed.'</li>';
}
echo '</ul><p>Click <a href="'.CRM_Utils_System::url('civicrm', 'reset=1').'">here</a> to return to the CiviCRM main page</p>';

/**
 * Function to add contact to group of processed contacts
 * 
 * @param int $contact_id
 */

function flag_contact_as_processed($contact_id) {
  $params = array(
    'group_id' => EH_PROCESSED_GROUP,
    'contact_id' => $contact_id,
    'status' => 'Added',
    'version' => 3
  );
  civicrm_api('GroupContact', 'Create', $params);
}
/**
 * Function to check if contact is linked to an organization
 * 
 * @param int $contact_id
 * @return boolean
 */
function is_linked_to_org($contact_id) {
  $query = 'SELECT COUNT(*) AS count_relationship FROM civicrm_relationship WHERE '
    . 'relationship_type_id = %1 AND contact_id_a = %2';
  $params = array(
    1 => array(4, 'Positive'),
    2 => array($contact_id, 'Positive')
  );
  $dao = CRM_Core_DAO::executeQuery($query, $params);
  if ($dao->fetch()) {
    if ($dao->count_relationship > 0) {
      return TRUE;
    }
  }
  return FALSE;
}
/**
 * Function to check contact
 * 
 * @param object $dao
 * @return boolean $is_contact_suspect
 */
function is_contact_suspect($dao) {
  $is_contact_suspect = is_suspect_name($dao->civicrm_contact_display_name);
  if ($is_contact_suspect == FALSE) {
    $is_contact_suspect = is_suspect_email($dao->civicrm_email_email);
  }
  return $is_contact_suspect;
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
    if (!empty($email_parts) && isset($email_parts[1])) {
      $email_second_part = split_email_second_part($email_parts[1]);
      if (!empty($email_second_part) && in_array($email_second_part['ext'], $suspect_extensions) 
          && in_array($email_second_part['org'], $suspect_email_orgs)) {
          return is_suspect_name($email_parts[0]);
      }
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