<?php
/* Copyright (C) 2007-2017  Laurent Destailleur <eldy@users.sourceforge.net>
 * Copyright (C) 2014-2016  Juanjo Menent       <jmenent@2byte.es>
 * Copyright (C) 2015       Florian Henry       <florian.henry@open-concept.pro>
 * Copyright (C) 2015       Raphaël Doursenaud  <rdoursenaud@gpcsolutions.fr>
 * Copyright (C) 2024       Frédéric France             <frederic.france@free.fr>
 * Copyright (C) 2024		MDW							<mdeweerd@users.noreply.github.com>
 *
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

/**
 * \file        core/class/emailsenderprofile.class.php
 * \ingroup     core
 * \brief       This file is a CRUD class file for EmailSenderProfile (Create/Read/Update/Delete)
 */

// Put here all includes required by your class file
require_once DOL_DOCUMENT_ROOT.'/core/class/commonobject.class.php';


/**
 * Class for EmailSenderProfile
 */
class EmailSenderProfile extends CommonObject
{
	/**
	 * @var string ID to identify managed object
	 */
	public $element = 'emailsenderprofile';

	/**
	 * @var string Name of table without prefix where object is stored
	 */
	public $table_element = 'c_email_senderprofile';

	/**
	 * @var int|string  Does emailsenderprofile support multicompany module ? 0=No test on entity, 1=Test with field entity, 2=Test with link by societe
	 */
	public $ismultientitymanaged = 1;

	/**
	 * @var string String with name of icon for emailsenderprofile
	 */
	public $picto = 'emailsenderprofile';

	public $fk_user_creat;


	const STATUS_DISABLED = 0;
	const STATUS_ENABLED = 1;



	/**
	 *  'type' if the field format ('integer', 'integer:ObjectClass:PathToClass[:AddCreateButtonOrNot[:Filter]]', 'varchar(x)', 'double(24,8)', 'real', 'price', 'text', 'html', 'date', 'datetime', 'timestamp', 'duration', 'mail', 'phone', 'url', 'password')
	 *         Note: Filter can be a string like "(t.ref:like:'SO-%') or (t.date_creation:<:'20160101') or (t.nature:is:NULL)"
	 *  'label' the translation key.
	 *  'enabled' is a condition when the field must be managed.
	 *  'position' is the sort order of field.
	 *  'notnull' is set to 1 if not null in database. Set to -1 if we must set data to null if empty ('' or 0).
	 *  'visible' says if field is visible in list (Examples: 0=Not visible, 1=Visible on list and create/update/view forms, 2=Visible on list only, 3=Visible on create/update/view form only (not list), 4=Visible on list and update/view form only (not create). 5=Visible on list and view only (not create/not update). Using a negative value means field is not shown by default on list but can be selected for viewing)
	 *  'noteditable' says if field is not editable (1 or 0)
	 *  'default' is a default value for creation (can still be overwrote by the Setup of Default Values if field is editable in creation form). Note: If default is set to '(PROV)' and field is 'ref', the default value will be set to '(PROVid)' where id is rowid when a new record is created.
	 *  'index' if we want an index in database.
	 *  'foreignkey'=>'tablename.field' if the field is a foreign key (it is recommended to name the field fk_...).
	 *  'searchall' is 1 if we want to search in this field when making a search from the quick search button.
	 *  'isameasure' must be set to 1 if you want to have a total on list for this field. Field type must be summable like integer or double(24,8).
	 *  'css' is the CSS style to use on field. For example: 'maxwidth200'
	 *  'help' is a string visible as a tooltip on field
	 *  'showoncombobox' if value of the field must be visible into the label of the combobox that list record
	 *  'disabled' is 1 if we want to have the field locked by a 'disabled' attribute. In most cases, this is never set into the definition of $fields into class, but is set dynamically by some part of code.
	 *  'arrayofkeyval' to set list of value if type is a list of predefined values. For example: array("0"=>"Draft","1"=>"Active","-1"=>"Cancel")
	 *  'comment' is not used. You can store here any text of your choice. It is not used by application.
	 *
	 *  Note: To have value dynamic, you can set value to 0 in definition and edit the value on the fly into the constructor.
	 */

	// BEGIN MODULEBUILDER PROPERTIES
	/**
	 * @var array<string,array{type:string,label:string,enabled:int<0,2>|string,position:int,notnull?:int,visible:int,noteditable?:int,default?:string,index?:int,foreignkey?:string,searchall?:int,isameasure?:int,css?:string,csslist?:string,help?:string,showoncombobox?:int,disabled?:int,arrayofkeyval?:array<int,string>,comment?:string}>  Array with all fields and their property. Do not use it as a static var. It may be modified by constructor.
	 */
	public $fields = array(
		'rowid' => array('type' => 'integer', 'label' => 'TechnicalID', 'visible' => -1, 'enabled' => 1, 'position' => 1, 'notnull' => 1, 'index' => 1, 'comment' => 'Id',),
		'entity' => array('type' => 'integer', 'label' => 'Entity', 'visible' => -1, 'enabled' => 1, 'position' => 20, 'notnull' => 1, 'index' => 1,),
		'label' => array('type' => 'varchar(255)', 'label' => 'Label', 'visible' => 1, 'enabled' => 1, 'position' => 30, 'notnull' => 1),
		'email' => array('type' => 'varchar(255)', 'label' => 'Email', 'visible' => 1, 'enabled' => 1, 'position' => 40, 'notnull' => -1),
		'private' => array('type' => 'integer:User:user/class/user.class.php', 'label' => 'User', 'visible' => 1, 'enabled' => 1, 'position' => 50, 'default' => '0', 'notnull' => 1),
		'signature' => array('type' => 'html', 'label' => 'Signature', 'visible' => 3, 'enabled' => 1, 'position' => 400, 'notnull' => -1, 'index' => 1,),
		'position' => array('type' => 'integer', 'label' => 'Position', 'visible' => 1, 'enabled' => 1, 'position' => 405, 'notnull' => -1, 'index' => 1,),
		'date_creation' => array('type' => 'datetime', 'label' => 'DateCreation', 'visible' => -1, 'enabled' => 1, 'position' => 500, 'notnull' => 1,),
		'tms' => array('type' => 'timestamp', 'label' => 'DateModification', 'visible' => -1, 'enabled' => 1, 'position' => 500, 'notnull' => 1,),
		'active' => array('type' => 'integer', 'label' => 'Status', 'visible' => 1, 'enabled' => 1, 'default' => 1, 'position' => 1000, 'notnull' => 1, 'index' => 1, 'arrayofkeyval' => array(0 => 'Disabled', 1 => 'Enabled')),
	);

	/**
	 * @var int ID
	 */
	public $rowid;

	/**
	 * @var int Entity
	 */
	public $entity;

	/**
	 * @var string Email Sender Profile label
	 */
	public $label;

	public $email;

	/**
	 * @var integer|string date_creation
	 */
	public $date_creation;

	public $private;
	public $signature;
	public $position;
	public $active;
	// END MODULEBUILDER PROPERTIES


	/**
	 * Constructor
	 *
	 * @param DoliDB $db Database handler
	 */
	public function __construct(DoliDB $db)
	{
		global $conf;

		$this->db = $db;

		if (!getDolGlobalString('MAIN_SHOW_TECHNICAL_ID')) {
			$this->fields['rowid']['visible'] = 0;
		}
		if (!isModEnabled('multicompany')) {
			$this->fields['entity']['enabled'] = 0;
		}
	}

	/**
	 * Create object into database
	 *
	 * @param  User $user      User that creates
	 * @param  int 	$notrigger 0=launch triggers after, 1=disable triggers
	 * @return int             Return integer <0 if KO, Id of created object if OK
	 */
	public function create(User $user, $notrigger = 0)
	{
		return $this->createCommon($user, $notrigger);
	}

	/**
	 * Clone and object into another one
	 *
	 * @param  	User 	$user      	User that creates
	 * @param  	int 	$fromid     Id of object to clone
	 * @return 	mixed 				New object created, <0 if KO
	 */
	public function createFromClone(User $user, $fromid)
	{
		global $hookmanager, $langs;
		$error = 0;

		dol_syslog(__METHOD__, LOG_DEBUG);

		$object = new self($this->db);

		$this->db->begin();

		// Load source object
		$object->fetchCommon($fromid);
		// Reset some properties
		unset($object->id);
		unset($object->fk_user_creat);
		unset($object->import_key);

		// Clear fields
		$object->ref = "copy_of_".$object->ref;
		$object->title = $langs->trans("CopyOf")." ".$object->title;
		// ...

		// Create clone
		$object->context['createfromclone'] = 'createfromclone';
		$result = $object->createCommon($user);
		if ($result < 0) {
			$error++;
			$this->error = $object->error;
			$this->errors = $object->errors;
		}

		unset($object->context['createfromclone']);

		// End
		if (!$error) {
			$this->db->commit();
			return $object;
		} else {
			$this->db->rollback();
			return -1;
		}
	}

	/**
	 * Load object in memory from the database
	 *
	 * @param int    $id   Id object
	 * @param string $ref  Ref
	 * @return int         Return integer <0 if KO, 0 if not found, >0 if OK
	 */
	public function fetch($id, $ref = null)
	{
		$result = $this->fetchCommon($id, $ref);
		if ($result > 0 && !empty($this->table_element_line)) {
			$this->fetchLines();
		}
		return $result;
	}

	/**
	 * Load object lines in memory from the database
	 *
	 * @return int         Return integer <0 if KO, 0 if not found, >0 if OK
	 */
	public function fetchLines()
	{
		$this->lines = array();

		// Load lines with object EmailSenderProfileLine

		return count($this->lines) ? 1 : 0;
	}

	/**
	 * Update object into database
	 *
	 * @param  User $user      User that modifies
	 * @param  int 	$notrigger 0=launch triggers after, 1=disable triggers
	 * @return int             Return integer <0 if KO, >0 if OK
	 */
	public function update(User $user, $notrigger = 0)
	{
		return $this->updateCommon($user, $notrigger);
	}

	/**
	 * Delete object in database
	 *
	 * @param User 	$user       User that deletes
	 * @param int 	$notrigger  0=launch triggers after, 1=disable triggers
	 * @return int             	Return integer <0 if KO, >0 if OK
	 */
	public function delete(User $user, $notrigger = 0)
	{
		return $this->deleteCommon($user, $notrigger);
	}

	/**
	 *  Return a link to the object card (with optionally the picto)
	 *
	 *	@param	int		$withpicto					Include picto in link (0=No picto, 1=Include picto into link, 2=Only picto)
	 *	@return	string								String with URL
	 */
	public function getNomUrl($withpicto = 0)
	{
		global $db, $conf, $langs;
		global $dolibarr_main_authentication, $dolibarr_main_demo;
		global $menumanager;

		$result = '';
		$companylink = '';

		$label = $this->label;

		$url = '';
		//$url = dol_buildpath('/monmodule/emailsenderprofile_card.php',1).'?id='.$this->id;

		$linkstart = '';
		$linkend = '';

		if ($withpicto) {
			$result .= ($linkstart.img_object($label, 'label', 'class="classfortooltip"').$linkend);
			if ($withpicto != 2) {
				$result .= ' ';
			}
		}
		$result .= $linkstart.$this->label.$linkend;
		return $result;
	}

	/**
	 *  Return the label of a given status
	 *
	 *  @param  int		$mode          0=long label, 1=short label, 2=Picto + short label, 3=Picto, 4=Picto + long label, 5=Short label + Picto, 6=Long label + Picto
	 *  @return string 			       Label of status
	 */
	public function getLibStatut($mode = 0)
	{
		return $this->LibStatut($this->active, $mode);
	}

	// phpcs:disable PEAR.NamingConventions.ValidFunctionName.ScopeNotCamelCaps
	/**
	 *  Return the label of a given status
	 *
	 *  @param	int		$status        Id status
	 *  @param  int		$mode          0=long label, 1=short label, 2=Picto + short label, 3=Picto, 4=Picto + long label, 5=Short label + Picto, 6=Long label + Picto
	 *  @return string 			       Label of status
	 */
	public static function LibStatut($status, $mode = 0)
	{
		global $langs;

		if ($status == 1) {
			$label = $labelshort = $langs->transnoentitiesnoconv('Enabled');
		} else {
			$label = $labelshort = $langs->transnoentitiesnoconv('Disabled');
		}

		$statusType = 'status'.$status;
		if ($status == self::STATUS_ENABLED) {
			$statusType = 'status4';
		}

		return dolGetStatus($label, $labelshort, '', $statusType, $mode);
	}

	/**
	 *  Charge les information d'ordre info dans l'objet commande
	 *
	 *  @param  int     $id       Id of order
	 *  @return	void
	 */
	public function info($id)
	{
		$sql = "SELECT rowid, date_creation as datec, tms as datem";
		$sql .= " FROM ".$this->db->prefix().$this->table_element." as t";
		$sql .= " WHERE t.rowid = ".((int) $id);
		$result = $this->db->query($sql);
		if ($result) {
			if ($this->db->num_rows($result)) {
				$obj = $this->db->fetch_object($result);
				$this->id = $obj->rowid;

				$this->date_creation     = $this->db->jdate($obj->datec);
				$this->date_modification = $this->db->jdate($obj->datem);
			}

			$this->db->free($result);
		} else {
			dol_print_error($this->db);
		}
	}

	/**
	 * Initialise object with example values
	 * Id must be 0 if object instance is a specimen
	 *
	 * @return int
	 */
	public function initAsSpecimen()
	{
		return $this->initAsSpecimenCommon();
	}
}

/**
 * Class EmailSenderProfileLine. You can also remove this and generate a CRUD class for lines objects.
 */
/*
class EmailSenderProfileLine
{
	// @var int ID
	public $id;
	// @var mixed Sample line property 1
	public $prop1;
	// @var mixed Sample line property 2
	public $prop2;
}
*/
