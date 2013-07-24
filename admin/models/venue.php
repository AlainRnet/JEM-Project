<?php
/**
 * @version 1.9
 * @package JEM
 * @copyright (C) 2013-2013 joomlaeventmanager.net
 * @copyright (C) 2005-2009 Christoph Lukes
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */

// No direct access.
defined('_JEXEC') or die;

jimport('joomla.application.component.modeladmin');

/**
 * Venue model.
 *
 */
class JEMModelVenue extends JModelAdmin
{
	/**
	 * Method to test whether a record can be deleted.
	 *
	 * @param	object	A record object.
	 * @return	boolean	True if allowed to delete the record. Defaults to the permission set in the component.
	 * 
	 */
	protected function canDelete($record)
	{
		if (!empty($record->id)) 
		{
				if ($record->state != -2) 
				{
					return ;
				}
				
				
				$user = JFactory::getUser();

				if (!empty($record->catid)) {
					return $user->authorise('core.delete', 'com_jem.category.'.(int) $record->catid);
				}
			
				else 
				{
					return $user->authorise('core.delete', 'com_jem');
				}
		}
		
		
	}

	/**
	 * Method to test whether a record can be deleted.
	 *
	 * @param	object	A record object.
	 * @return	boolean	True if allowed to change the state of the record. Defaults to the permission set in the component.
	 * 
	 */
	protected function canEditState($record)
	{
		$user = JFactory::getUser();

		if (!empty($record->catid)) {
			return $user->authorise('core.edit.state', 'com_jem.category.'.(int) $record->catid);
		}
		else {
			return $user->authorise('core.edit.state', 'com_jem');
		}
	}

	/**
	 * Returns a reference to the a Table object, always creating it.
	 *
	 * @param	type	The table type to instantiate
	 * @param	string	A prefix for the table class name. Optional.
	 * @param	array	Configuration array for model. Optional.
	 * @return	JTable	A database object
	 * 
	 */
	public function getTable($type = 'Venue', $prefix = 'JEMTable', $config = array())
	{
		return JTable::getInstance($type, $prefix, $config);
	}

	/**
	 * Method to get the record form.
	 *
	 * @param	array	$data		Data for the form.
	 * @param	boolean	$loadData	True if the form is to load its own data (default case), false if not.
	 * @return	mixed	A JForm object on success, false on failure
	 * 
	 */
	public function getForm($data = array(), $loadData = true)
	{
		// Get the form.
		$form = $this->loadForm('com_jem.venue', 'venue', array('control' => 'jform', 'load_data' => $loadData));
		if (empty($form)) {
			return false;
		}

		return $form;
	}

	
	
	
	/**
	 * Method to get a single record.
	 *
	 * @param	integer	The id of the primary key.
	 *
	 * @return	mixed	Object on success, false on failure.
	 */
	public function getItem($pk = null)
	{
		$jemsettings = JEMAdmin::config();
		
		
		if ($item = parent::getItem($pk)) {
			
			
		$files = JEMAttachment::getAttachments('venue'.$item->id);
		$item->attachments = $files;
		}
		$item->author_ip = $jemsettings->storeip ? getenv('REMOTE_ADDR') : 'DISABLED';
		
		if (empty($item->id))
		{ 
		$item->country = $jemsettings->defaultCountry;
		}
	
		return $item;
	}
	
	
	
	/**
	 * Method to get the data that should be injected in the form.
	 *
	 */
	protected function loadFormData()
	{
		
		// Check the session for previously entered form data.
		$data = JFactory::getApplication()->getUserState('com_jem.edit.venue.data', array());

		if (empty($data)) {
			$data = $this->getItem();
		}

		return $data;
	}

	/**
	 * Prepare and sanitise the table data prior to saving.
	 *
	 */
	protected function prepareTable(&$table)
	{
		
		//Debug
		/* var_dump($_POST);exit; */
		
		
		$app =  JFactory::getApplication();
		$date	= JFactory::getDate();
		$jemsettings = JEMAdmin::config();
		
		$user	= JFactory::getUser();
		if ($table->id) {
			// Existing item
			$table->modified	= $date->toSql();
			$table->modified_by	= $user->get('id');
		} else {
			// New venue. A venue created and created_by field can be set by the user,
			// so we don't touch either of these if they are set.
			
			if (!intval($table->created)) {
			$table->created = $date->toSql();
			}
			
			if (empty($this->created_by)) {
			$table->created_by = $user->get('id');
			}
			
			
			}
			
			//uppercase needed by mapservices
			if ($table->country) {
				$table->country = JString::strtoupper($table->country);
			}

			
			// Check if image was selected
			jimport('joomla.filesystem.file');
			$format 	= JFile::getExt(JPATH_SITE.'/images/jem/venues/'.$table->locimage);
			
			$allowable 	= array ('gif', 'jpg', 'png');
			if (in_array($format, $allowable)) {
				$table->locimage = $table->locimage;
			} else {
				$table->locimage = '';
			}	
			
		
			// attachments
			// new ones first
			$attachments = JRequest::getVar( 'attach', array(), 'files', 'array' );
			$attachments['customname'] = JRequest::getVar( 'attach-name', array(), 'post', 'array' );
			$attachments['description'] = JRequest::getVar( 'attach-desc', array(), 'post', 'array' );
			$attachments['access'] = JRequest::getVar( 'attach-access', array(), 'post', 'array' );
			JEMAttachment::postUpload($attachments, 'venue'.$table->id);
			
			// and update old ones
			$attachments = array();
			$old['id'] = JRequest::getVar( 'attached-id', array(), 'post', 'array' );
			$old['name'] = JRequest::getVar( 'attached-name', array(), 'post', 'array' );
			$old['description'] = JRequest::getVar( 'attached-desc', array(), 'post', 'array' );
			$old['access'] = JRequest::getVar( 'attached-access', array(), 'post', 'array' );
			foreach ($old['id'] as $k => $id)
			{
				$attach = array();
				$attach['id'] = $id;
				$attach['name'] = $old['name'][$k];
				$attach['description'] = $old['description'][$k];
				$attach['access'] = $old['access'][$k];
				JEMAttachment::update($attach);
			}
			
			
		
		$table->venue = htmlspecialchars_decode($table->venue, ENT_QUOTES);
		
		// Increment the content version number.
		$table->version++;
		
	}
}
