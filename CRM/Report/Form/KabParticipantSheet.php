<?php

/*
 +--------------------------------------------------------------------+
 | CiviCRM version 3.4                                                |
 +--------------------------------------------------------------------+
 | Copyright CiviCRM LLC (c) 2004-2011                                |
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
 | Kabissa bug #523                                                   |
 | for the purposes of registering participants as they arrive at the |
 | event and collecting money, we need to have a printout of          |
 | registered users                                                   |
 |                                                                    |
 | Author		:	Erik Hommel                                       |
 | Date			:	18 June 2012                                      |
 | (adaptation of core report CRM/Report/Form/Event/ParticipanListing)| 
 +--------------------------------------------------------------------+
*/

/**
 *
 * @package CRM
 * @copyright CiviCRM LLC (c) 2004-2011
 * $Id$
 *
 */

require_once 'CRM/Report/Form.php';
require_once 'CRM/Event/PseudoConstant.php';
require_once 'CRM/Core/OptionGroup.php';
require_once 'CRM/Event/BAO/Participant.php';
require_once 'CRM/Contact/BAO/Contact.php';

class CRM_Report_Form_KabParticipantSheet extends CRM_Report_Form {

    protected $_summary = null;

   
    function __construct( ) {

        static $_events;
        if ( !isset($_events['all']) ) {
            CRM_Core_PseudoConstant::populate( $_events['all'], 'CRM_Event_DAO_Event', false, 'title', 'is_active', "(is_template IS NULL OR is_template = 0) AND event_type_id = 7", 'end_date DESC' );
        }

        $this->_columns = 
            array( 'civicrm_participant' =>
				array( 'dao'		=> 'CRM_Event_DAO_Participant',
					   'filters'  	=>
					array( 'event_id' 
						=> array( 'name'         => 'event_id',
                                  'title'        => ts( 'Event' ),
                                  'operatorType' => CRM_Report_Form::OP_MULTISELECT,
                                  'options'      => $_events['all'] ),
                                ),
                         ),
                  );
        parent::__construct( );
    }
    
    function preProcess( ) {
        parent::preProcess( );
    }
    
    
    static function formRule( $fields, $files, $self ) {  
        $errors = $grouping = array( );
        return $errors;
    }
    
    function postProcess( ) {

		$this->beginPostProcess( );
		$eventIDs = $this->_params['event_id_value'];
		if ( is_array( $eventIDs ) ) {
			$eventString = implode( ",", $eventIDs );
		} else {
			$eventString = $eventIDs;
		}
		$partQry =
" SELECT a.contact_id AS contact_id, b.label AS status, c.first_name, c.last_name,
d.label AS role, e.affiliated_organization_33 AS organization,
e.website_32 AS website, e.blog_35 AS blog, e.skypename_36 AS skypename
FROM civicrm_participant a JOIN civicrm_participant_status_type b ON a.status_id = b.id
JOIN civicrm_contact c ON a.contact_id = c.id JOIN civicrm_option_value d ON a.role_id = value AND option_group_id = 13
JOIN civicrm_value_additional_data_for_africa_round_7 e ON a.id = e.entity_id";

		if ( !empty( $eventString ) ) {	
			$partQry .= " WHERE event_id IN ($eventString)";
		}

        $this->_columnHeaders = array(
			'contact_id'		=> array( 'title' => 'ID'),
			'first_name' 		=> array( 'title' => 'First Name' ),
            'last_name'		  	=> array( 'title' => 'Last Name' ),
            'role' 				=> array( 'title' => 'Role' ),
            'status'			=> array( 'title' => 'Status' ),
            'skypename'			=> array( 'title' => 'Skype User'),
            'organization'		=> array( 'title' => 'Organization' ),
            'website'			=> array( 'title' => 'Website' ),
            'blog'				=> array( 'title' => 'Blog')
                       );

        $this->buildRows ( $partQry, $rows );
		$this->alterDisplay( $rows);
        $this->doTemplateAssignment( $rows );
        $this->endPostProcess( $rows );    
	}
    
    function alterDisplay( &$rows ) {

		$entryFound = false;
        foreach ( $rows as $rowNum => $row ) {
            // make count columns point to detail report
            // convert display name to links
            if ( array_key_exists('contact_id', $row) ) {
                $url = CRM_Utils_System::url( "civicrm/contact/view",  
                                              'reset=1&cid=' . $row['contact_id'],
                                              $this->_absoluteUrl );
                $rows[$rowNum]['contact_id_link' ] = $url;
                $rows[$rowNum]['contact_id_hover'] = ts("View Contact details for this contact.");
                $entryFound = true;
            }

            // skip looking further in rows, if first row itself doesn't 
            // have the column we need
            if ( !$entryFound ) {
                break;
            }
        }
    }
}
