<?php
/**
 * @version 1.9.1
 * @package JEM
 * @copyright (C) 2013-2013 joomlaeventmanager.net
 * @copyright (C) 2005-2009 Christoph Lukes
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */

defined('_JEXEC') or die;

jimport('joomla.application.component.controller');

/**
 * JEM Component Import Controller
 *
 * @package JEM
 *
 */
class JEMControllerImport extends JControllerLegacy {
	/**
	 * Constructor
	 *
	 *
	 */
	function __construct() {
		parent::__construct();
	}

	function csveventimport() {
		$this->CsvImport('events', 'events');
	}

	function csvcategoriesimport() {
		$this->CsvImport('categories', 'categories');
	}

	function csvvenuesimport() {
		$this->CsvImport('venues', 'venues');
	}

	function csvcateventsimport() {
		$this->CsvImport('catevents', 'cats_event_relations');
	}

	private function CsvImport($type, $dbname) {
		$replace = JRequest::getVar('replace_'.$type, 0, 'post', 'int');
		$object = JTable::getInstance('jem_'.$dbname, '');
		$object_fields = get_object_vars($object);

		if($type == 'events') {
			// add additional fields
			$object_fields['categories'] = '';
		}

		$msg = '';
		if ($file = JRequest::getVar('File'.$type, null, 'files', 'array')) {
			$fc = iconv('windows-1252', 'utf-8', file_get_contents($file['tmp_name']));
			file_put_contents($file['tmp_name'], $fc);
			$handle = fopen($file['tmp_name'], 'r');
			if(!$handle) {
				$msg = JText::_('COM_JEM_IMPORT_OPEN_FILE_ERROR');
				$this->setRedirect('index.php?option=com_jem&view=import', $msg, 'error');
				return;
			}

			// get fields, on first row of the file
			$fields = array();
			if(($data = fgetcsv($handle, 1000, ';')) !== FALSE) {
				$numfields = count($data);
				for($c=0; $c < $numfields; $c++) {
					// here, we make sure that the field match one of the fields of jem_venues table or special fields,
					// otherwise, we don't add it
					if(array_key_exists($data[$c], $object_fields)) {
						$fields[$c] = $data[$c];
					}
				}
			}

			// If there is no validated fields, there is a problem...
			if(!count($fields)) {
				$msg .= "<p>".JText::_('COM_JEM_IMPORT_PARSE_ERROR')."</p>\n";
				$msg .= "<p>".JText::_('COM_JEM_IMPORT_PARSE_ERROR_INFOTEXT')."</p>\n";

				$this->setRedirect('index.php?option=com_jem&view=import', $msg, 'error');
				return;
			} else {
				$msg .= "<p>".JText::sprintf('COM_JEM_IMPORT_NUMBER_OF_FIELDS', $numfields)."</p>\n";
				$msg .= "<p>".JText::sprintf('COM_JEM_IMPORT_NUMBER_OF_FIELDS_USEABLE', count($fields))."</p>\n";
			}

			// Now get the records, meaning the rest of the rows.
			$records = array();
			$row = 1;
			while(($data = fgetcsv($handle, 10000, ';')) !== FALSE) {
				$num = count($data);

				if($numfields != $num) {
					$msg .= "<p>".JText::sprintf('COM_JEM_IMPORT_NUMBER_OF_FIELDS_COUNT_ERROR', $num, $row)."</p>\n";
				} else {
					$r = array();
					// only extract columns with validated header, from previous step.
					foreach($fields as $k => $v) {
						$r[$k] = $this->_formatcsvfield($v, $data[$k]);
					}
					$records[] = $r;
				}
				$row++;
			}
			fclose($handle);
			$msg .= "<p>".JText::sprintf('COM_JEM_IMPORT_NUMBER_OF_ROWS_FOUND', count($records))."</p>\n";

			// database update
			if(count($records)) {
				$model = $this->getModel('import');
				$result = $model->{$type.'import'}($fields, $records, $replace);
				$msg .= "<p>".JText::sprintf('COM_JEM_IMPORT_NUMBER_OF_ROWS_ADDED', $result['added'])."</p>\n";
				$msg .= "<p>".JText::sprintf('COM_JEM_IMPORT_NUMBER_OF_ROWS_UPDATED', $result['updated'])."</p>\n";
			}
			$this->setRedirect('index.php?option=com_jem&view=import', $msg);
		} else {
			parent::display();
		}
	}

	/**
	 * handle specific fields conversion if needed
	 *
	 * @param string column name
	 * @param string $value
	 * @return string
	 */
	function _formatcsvfield($type, $value) {
		switch($type) {
			case 'times':
			case 'endtimes':
				if($value != '' && strtoupper($value) != 'NULL') {
					$time = strtotime($value);
					$field = strftime('%H:%M', $time);
				} else {
					$field = null;
				}
				// var_dump($field);exit;
			break;
			case 'dates':
			case 'enddates':
			case 'recurrence_limit_date':
				if($value != '' && strtoupper($value) != 'NULL') {
					$date = strtotime($value);
					$field = strftime('%Y-%m-%d', $date);
				} else {
					$field = null;
				}
				break;
			default:
				$field = $value;
				break;
		}
		return $field;
	}

	/**
	 * Imports data from an old Eventlist installation
	 */
	public function eventlistImport() {
		$model = $this->getModel('import');
		$size = 500;

		// Handling the different names for all classes and db table names (possibly substrings)
		$tables = new stdClass();
		$tables->eltables = array("categories", "events", "events", "groupmembers", "groups", "register", "venues");
		$tables->jemtables = array("categories", "events", "cats_event_relations", "groupmembers", "groups", "register", "venues");

		$jinput = JFactory::getApplication()->input;
		$step = $jinput->get('step', 0, 'INT');
		$current = $jinput->get->get('current', 0, 'INT');
		$total = $jinput->get->get('total', 0, 'INT');
		$table = $jinput->get->get('table', 0, 'INT');
		$copyImages = $jinput->get('copyImages', 0, 'INT');

		$msg = JText::_('COM_JEM_IMPORT_EL_IMPORT_WORK_IN_PROGRESS')." ";

		if($step == 0 || !$model->getEventlistVersion()) {
			parent::display();
		} elseif($step == 1) {
			// Get number of rows if it is still 0 or we have moved to the next table
			if($total == 0 || $current == 0) {
				$total = $model->getTableCount("#__eventlist_".$tables->eltables[$table]);
			}

			// The real work is done here:
			// Loading from EL tables, changing data, storing in JEM tables
			$data = $model->getEventlistData("#__eventlist_".$tables->eltables[$table], $current, $size);
			$data = $model->transformEventlistData($tables->jemtables[$table], $data);
			$model->storeJemData("jem_".$tables->jemtables[$table], $data);

			// Proceed with next bunch of data
			$current += $size;

			// Current table is imported completely, proceed with next table
			if($current > $total) {
				$table++;
				$current = 0;
			}

			// Check if table import is complete
			if($current < $total && $table < count($tables->eltables)) {
				$link = 'index.php?option=com_jem&view=import&step=1&table='.$table.'&current='.$current.'&total='.$total.'&copyImages='.$copyImages;
				$msg .= JText::sprintf('COM_JEM_IMPORT_EL_IMPORT_WORKING_STEP1', $tables->jemtables[$table-1], $current, $total);
			} else {
				$step++;
				$link = 'index.php?option=com_jem&view=import&step='.$step.'&copyImages='.$copyImages;
			}
		} elseif($step == 2) {
			// Copy EL images to JEM image destination?
			if($copyImages) {
				$model->copyImages();
				$msg .= JText::_('COM_JEM_IMPORT_EL_IMPORT_WORKING_STEP2');
			} else {
				$msg .= JText::_('COM_JEM_IMPORT_EL_IMPORT_WORKING_STEP2_SKIPPED');
			}
			$step++;
			$link = 'index.php?option=com_jem&view=import&step='.$step;
		} else {
			$link = 'index.php?option=com_jem&view=import';
			$msg = JText::_('COM_JEM_IMPORT_EL_IMPORT_FINISHED');
		}

		$this->setRedirect($link, $msg);
	}
}
?>