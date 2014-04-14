<?php

/*
 +--------------------------------------------------------------------+
 | CiviCRM version 3.3                                                |
 +--------------------------------------------------------------------+
 | Copyright CiviCRM LLC (c) 2004-2010                                |
 +--------------------------------------------------------------------+
 | This file is a part of CiviCRM.                                    |
 |                                                                    |
 | CiviCRM is free software; you can copy, modify, and distribute it  |
 | under the terms of the GNU Affero General Public License           |
 | Version 3, 19 November 2007 and the CiviCRM Licensing Exception.   |
 |                                                                    |
 | CiviCRM is distributed in the hope that it will be useful, but     |
 | WITHOUT ANY WARRANTY; without even the implied warranty of         |
 | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.               |
 | See the GNU Affero General Public License for more details.        |
 |                                                                    |
 | You should have received a copy of the GNU Affero General Public   |
 | License and the CiviCRM Licensing Exception along                  |
 | with this program; if not, contact CiviCRM LLC                     |
 | at info[AT]civicrm[DOT]org. If you have questions about the        |
 | GNU Affero General Public License or the licensing of CiviCRM,     |
 | see the CiviCRM license FAQ at http://civicrm.org/licensing        |
 +--------------------------------------------------------------------+
 | Kaibissa Report Kabissa Hall of Fame                               |
 | Erik Hommel, 22 August 2012                                        |
 +--------------------------------------------------------------------+
*/

/**
 *
 * @package CRM
 * @copyright CiviCRM LLC (c) 2004-2010
 * $Id$
 *
 */
ini_set( 'display_errors', '1' );

require_once 'CRM/Report/Form.php';

class CRM_Report_Form_KabHoF extends CRM_Report_Form {

    function __construct( ) {
        if ( !isset( $session ) ) {
			$session = CRM_Core_Session::singleton( );
		}
		$this->_userID = $session->get( 'userID' );
		$this->_columns = array( );
        parent::__construct( );
    }

    function postProcess( ) {

		$this->beginPostProcess( );

		$qryHallOfFame = 
"SELECT a.contact_id, b.display_name, b.first_name, b.last_name, b.employer_id,
c.organization_name, d.city, e.name, CONCAT(d.city, ', ', e.name) AS whereabouts
FROM civicrm_group_contact a LEFT JOIN civicrm_contact b ON a.contact_id = b.id 
LEFT JOIN civicrm_contact c ON b.employer_id = c.id LEFT JOIN civicrm_address d 
ON a.contact_id = d.contact_id AND d.is_primary = 1 LEFT JOIN civicrm_country e 
ON d.country_id = e.id WHERE a.group_id = 2";

        $this->_columnHeaders = array(
			'display_name' 		=> array( 'title' => 'Name' ),
            'first_name'  		=> array( 'title' => 'First name' ),
            'last_name' 		=> array( 'title' => 'Last name' ),
            'organization_name'	=> array( 'title' => 'Affiliation' ),
            'whereabouts'		=> array( 'title' => 'Whereabouts' )
                       );

        $this->buildRows ( $qryHallOfFame, $rows );
		$this->alterDisplay( $rows);
        $this->doTemplateAssignment( $rows );
        $this->endPostProcess( $rows );    
	}
    function alterDisplay( &$rows ) {

		require_once 'CRM/Utils/Date.php';
		require_once 'CRM/Utils/Array.php';

		$entryFound = false;
        foreach ( $rows as $rowNum => $row ) {
			/*
			 * make display_name function as link to contact view
			 */
            if ( array_key_exists('display_name', $row) ) {
                $url = CRM_Utils_System::url( "civicrm/contact/view",  
                                              'reset=1&cid=' . $row['contact_id'],
                                              $this->_absoluteUrl );
                $rows[$rowNum]['display_name_link' ] = $url;
                $rows[$rowNum]['display_name_hover'] = ts("Kabissa Profile");
                $entryFound = true;
            }
			/*
			 * make organization_name function as link to organization view
			 */
            if ( array_key_exists('organization_name', $row) ) {
                $url = CRM_Utils_System::url( "civicrm/contact/view",  
                                              'reset=1&cid=' . $row['employer_id'],
                                              $this->_absoluteUrl );
                $rows[$rowNum]['organization_name_link' ] = $url;
                $rows[$rowNum]['organization_name_hover'] = ts("Kabissa Affiliation Profile");
                $entryFound = true;
            }
			/*
			 * open Google Map for whereabouts
			 */
            if ( array_key_exists('whereabouts', $row) ) {
				if ( !empty( $row['whereabouts'] ) ) {
					$rows[$rowNum]['whereabouts_link' ] = "<iframe width='300' height='300' frameborder='0' scrolling='no' marginheight='0' marginwidth='0' src='https://maps.google.com/maps?q='.$whereabouts.'></iframe>";
					$rows[$rowNum]['whereabouts_hover'] = ts("Map");
					$entryFound = true;
				}
            }

            // skip looking further in rows, if first row itself doesn't 
            // have the column we need
            if ( !$entryFound ) {
                break;
            }
            
            if ( !$entryFound ) {
                break;
            }
        }
    }
    function buildRows( $sql, &$rows ) {
        $dao  = CRM_Core_DAO::executeQuery( $sql );
        if ( ! is_array($rows) ) {
            $rows = array( );
        }

        // use this method to modify $this->_columnHeaders
        $this->modifyColumnHeaders( );


        while ( $dao->fetch( ) ) {
            $row = array( );
            foreach ( $this->_columnHeaders as $key => $value ) {
                if ( property_exists( $dao, $key ) ) {
                    $row[$key] = $dao->$key;
                }
                /*
                 * add contact_id to row
                 */
                if ( isset( $dao->contact_id ) ) {
					$row['contact_id'] = $dao->contact_id;
				}
                /*
                 * add employer_id to row
                 */
                if ( isset( $dao->employer_id ) ) {
					$row['employer_id'] = $dao->employer_id;
				}
				/*
				 * fill whereabouts if country or city empty
				 */
				if ( isset( $dao->whereabouts ) && empty( $dao->whereabouts ) ) {
					$whereabouts = null;
					if ( isset( $dao->city ) && !empty( $dao->city ) ) {
						$whereabouts = $dao->city;
					}
					if ( isset( $dao->name ) && !empty( $dao->name ) ) {
						if ( empty( $whereabouts ) ) {
							$whereabouts = $dao->name;
						} else {
							$whereabouts .= ", ".$dao->name;
						}
					}
				}
            }
            $rows[] = $row;
        }

    }
}
