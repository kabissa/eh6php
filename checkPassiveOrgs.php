<?php
ini_set( 'display_startup_errors', '0' );
ini_set( 'display_errors', '1' );
/*
 * bootstrap CiviCRM
 */
require_once($_SERVER['DOCUMENT_ROOT'].
	'/speel6/sites/default/civicrm.settings.php');
require_once 'CRM/Core/Config.php';
require_once('api/v2/GroupContact.php');
require_once('api/v2/Contact.php');
$config =& CRM_Core_Config::singleton( );

/*
 * check all active organizations that 
 * 1) have been created longer than 6 months ago
 * 2) have not been updated in the last 6 months
 * 3) no relations have been updated in the last 6 months
 * 4) do not have a corresponding Drupal user that has blogged
 *    in the last 6 months
 * 5) have not clicked a newsletter in the last 6 months
 * 
 * set each of those organizations to 'passive' 
 * 
 */
$updatedOrgs = 0;
$selectOrg = "SELECT a.id FROM civicrm_contact a LEFT JOIN civicrm_value_kabissa b ON a.id = b.entity_id
  WHERE contact_type = 'Organization' AND is_deleted = 0
  AND status_id NOT IN (5, 6) AND signup_date < DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
  AND last_updated_date < DATE_SUB(CURDATE(), INTERVAL 6 MONTH) LIMIT 10";
$daoOrg = CRM_Core_DAO::executeQuery($selectOrg);
while ($daoOrg->fetch()) {
  $setToPassive = TRUE;
  /*
   * check if organization has any relations of type Employer/Employee
   *
   */
  $selectEmployee = "SELECT * FROM civicrm_relationship 
    WHERE relationship_type_id = 4 
    AND contact_id_b = ".$daoOrg->id;
  $daoEmployee = CRM_Core_DAO::executeQuery($selectEmployee);
  while ($daoEmployee->fetch()) {
    /*
     * if start or end date of relationship is in the last 6 months,
     * organization is seen as active
     */
    $testDate = new DateTime("-6 months");
    $sqlTestDate = $testDate->format('Ymd');
    if ($daoEmployee->start_date >= $sqlTestDate || $daoEmployee->end_date >= $sqlTestDate) {
      $setToPassive = FALSE;
    }
    if ($setToPassive == TRUE) {
      /*
       * if contact still set to passive, check if there were any
       * clicks on Kabissa mailings in the last six months
       */
      $selectClicks = "SELECT COUNT( * ) AS contactClicked
          FROM civicrm_mailing_event_queue a
          JOIN civicrm_mailing_event_opened b ON a.id = b.event_queue_id
          JOIN civicrm_mailing_job c ON a.job_id = c.id
          JOIN civicrm_mailing d ON c.mailing_id = d.id
          WHERE d.from_name IN('Kabissa Newsletter Editors', 'Africa Roundtable', 'Kabissa Gong Gong', 'Afrika Kabissa - Absolutely Africa')
          AND b.time_stamp > '". $sqlTestDate."' AND contact_id = ". $daoEmployee->contact_id_a;
      $daoClicks = CRM_Core_DAO::executeQuery($selectClicks);
      if ($daoClicks->fetch()) {
        if ($daoClicks->contactClicked > 0) {
          $setToPassive = FALSE;
        }
      }
      /*
       * if contact still to be set passive, check if org has a Drupal user
       * and has blogged in the last 6 months
       */
      if ($setToPassive == TRUE) {
        $selectDrupalUser = "SELECT uf_id FROM civicrm_uf_match WHERE contact_id = ".$daoEmployee->contact_id_a;
        $daoDrupalUser = CRM_Core_DAO::executeQuery($selectDrupalUser);
        if ($daoDrupalUser->fetch()) {
          /*
           * bootstrap Drupal
           */
          $drupal_path = $_SERVER['DOCUMENT_ROOT'].'/speel6';
          chdir($drupal_path);
          define('DRUPAL_ROOT', $drupal_path);
          require_once DRUPAL_ROOT . '/includes/bootstrap.inc';
          drupal_bootstrap(DRUPAL_BOOTSTRAP_FULL);
          $selectDrupalBlog = "SELECT uid, type, created, changed FROM {node}"
          . " WHERE type = 'blog' AND uid = ".$daoDrupalUser->uf_id;
          $userBlogs = db_query($selectDrupalBlog);
          while ( $userBlog = db_fetch_object($userBlogs)) {
            $createDate = format_date($userBlog->created, 'created', 'Ymd');
            $changeDate = format_date($userBlog->changed, 'changed', 'Ymd');
            if ($createdDate > $testDate || $changeDate > $testDate) {
              $setToPassive == FALSE;
            }
          }              
        }
      }
    }
  }
  /*
   * if still set to passive, set organization to passive
   */
  if ($setToPassive == TRUE) {
    $updateOrg = "UPDATE civicrm_value_kabissa SET status_id = 6 WHERE entity_id = ".$daoOrg->id;
    CRM_Core_DAO::executeQuery($updateOrg);
    $updatedOrgs++;
  }
}
