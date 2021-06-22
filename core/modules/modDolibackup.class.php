<?php

/**
 * @author   Simafri  https://simafri.com
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */

include_once DOL_DOCUMENT_ROOT .'/core/modules/DolibarrModules.class.php';


/**
 *  Description and activation class for module dolibackup
 */
class modDolibackup extends DolibarrModules
{
	/**
	 * Constructor. Define names, constants, directories, boxes, permissions
	 *
	 * @param DoliDB $db Database handler
	 */
	public function __construct($db)
	{
        global $langs,$conf;

        $this->db = $db;


		$this->numero = 3370000;
		$this->rights_class = 'dolibackup';
		$this->family = "other";
		$this->module_position = '90';
		$this->name = preg_replace('/^mod/i','',get_class($this));
		$this->description = "dolibackup";
		$this->descriptionlong = "dolibackup";
		$this->version = '1.0';
		$this->const_name = 'MAIN_MODULE_'.strtoupper($this->name);
		$this->picto='backup@dolibackup';

		$this->cronjobs = array(
			// Cron Job to lauch the backup, by default executed every 24 hours
			0=>array( 'label'=>'DOLIBACKUP', 'jobtype'=>'method', 'class'=>'/dolibackup/class/dolibackup.class.php', 'objectname'=>'Dolibackup', 'method'=>'lancer_dolibackup', 'parameters'=>'', 'comment'=>'', 'frequency'=>1, 'unitfrequency'=>86400, 'status'=>0, 'test'=>'$conf->dolibackup->enabled', 'priority'=>50),
			//Cron Job to control the backups, check if there is error and relaunch the backup
			1=>array('label'=>'DOLIBACKUP Control', 'jobtype'=>'method', 'class'=>'/dolibackup/class/dolibackup.class.php', 'objectname'=>'Dolibackup', 'method'=>'controle_dolibackup', 'parameters'=>'', 'comment'=>'', 'frequency'=>30, 'unitfrequency'=>60, 'status'=>0, 'test'=>'$conf->dolibackup->enabled', 'priority'=>50)
		);
		$this->config_page_url = array("admin.php@dolibackup");

		$this->hidden = false;			
		$this->depends = array("modCron");		
		$this->requiredby = array();	
		$this->conflictwith = array();
		$this->langfiles = array("dolibackup@dolibackup");
		$this->need_dolibarr_version = array(9,0);
		$this->warnings_activation = array();			
		$this->warnings_activation_ext = array();	
		$this->editor_name = 'Simafri';
		$this->editor_url = 'https://simafri.com';	

		if (! isset($conf->dolibackup) || ! isset($conf->dolibackup->enabled))
		{
			$conf->dolibackup=new stdClass();
			$conf->dolibackup->enabled=0;
		}

        $this->tabs = array();
		$this->dictionaries=array();
		$this->rights = array();
	}

	/**
	 *	Function called when module is enabled.
	 *	The init function add constants, boxes, permissions and menus (defined in constructor) into Dolibarr database.
	 *	It also creates data directories
	 *
     *	@param      string	$options    Options when enabling module ('', 'noboxes')
	 *	@return     int             	1 if OK, 0 if KO
	 */
	public function init($options='')
	{
		$sql = array();
		return $this->_init($sql, $options);
	}

	/**
	 *	Function called when module is disabled.
	 *	Remove from database constants, boxes and permissions from Dolibarr database.
	 *	Data directories are not deleted
	 *
	 *	@param      string	$options    Options when enabling module ('', 'noboxes')
	 *	@return     int             	1 if OK, 0 if KO
	 */
	public function remove($options = '')
	{
		$sql = array();
		return $this->_remove($sql, $options);
	}
}
