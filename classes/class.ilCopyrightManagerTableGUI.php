<?php
/* Copyright (c) 2016 Institut fuer Lern-Innovation, GPLv3, see LICENSE */

include_once ('./Services/Table/classes/class.ilTable2GUI.php');

/**
 * Copyright Manager Table GUI
 *
 * @author Fred Neumann <fred.neumann@fau.de>
 */
class ilCopyrightManagerTableGUI extends ilTable2GUI
{
	/** @var  ilCopyrightManagerGUI */
	public $parent_obj;

	/** @var  string */
	public $parent_cmd;

	/** @var ilCopyrightManagerPlugin $plugin */
	public $plugin;

	/** @var  ilCopyrightManager $manager */
	public $manager;

	/** @var ilPathGUI */
	public $pathGUI;

	/**
	 * ilCopyrightManagerTableGUI constructor.
	 * @param ilCopyrightManagerGUI $a_parent_obj
	 * @param string 				$a_parent_cmd
	 */
	public function __construct($a_parent_obj, $a_parent_cmd)
	{
		global $ilCtrl, $lng;

		$this->plugin = $a_parent_obj->plugin;
		$this->manager = $a_parent_obj->manager;

		require_once("Services/Link/classes/class.ilLink.php");
		require_once("Services/Tree/classes/class.ilPathGUI.php");
		$this->pathGUI = new ilPathGUI();
		$this->pathGUI->enableTextOnly(false);

		$this->setId('coriman'.$a_parent_cmd);
		parent::__construct($a_parent_obj,$a_parent_cmd);

		// columns
		$this->addColumn('','','1',true);
		$this->addColumn($lng->txt("title"), "title");
		foreach ($this->getSelectableColumns() as $colid => $settings)
		{
			if ($this->isColumnSelected($colid))
			{
				$this->addColumn(
					$settings['txt'],
					$settings['sort'],
					'',
					false,
					'',
					$settings['tooltip']
				);
			}
		}
		$this->addColumn($this->plugin->txt('copyright'), "copyright");
		$this->addColumn($lng->txt('actions'));

		$this->setFormAction($ilCtrl->getFormAction($a_parent_obj, $a_parent_cmd));
		$this->setRowTemplate("tpl.obj_copyright_row.html", $this->plugin->getDirectory());
		$this->setDisableFilterHiding(true);
		$this->setDefaultOrderField("title");
		$this->setDefaultOrderDirection("asc");
		$this->setSelectAllCheckbox('ref_id');
		$this->addMultiItemSelectionButton('apply_copyright',
			$this->manager->getSelectableCopyrights(true), 'applyCopyright',
			$this->plugin->txt('apply_copyright'));

		$this->initFilter();

		$this->addCommandButton('saveCopyrights', $this->plugin->txt('save_copyrights'));
	}

	/**
	 * Get the selectable columns with basic object data
	 * @return array
	 */
	public function getSelectableColumns()
	{
		global $lng;

		return array(
			'owner_login' => array(
				'txt' => $lng->txt('owner'),
				'sort' => 'owner_login',
				'tooltip' => '',
				'default' => true
			),

			'create_date' => array(
				'txt' => $lng->txt('create_date'),
				'sort' => 'create_date',
				'tooltip' => '',
				'default' => true
			),
			'last_update' => array(
				'txt' => $lng->txt('last_update'),
				'sort' => 'last_update',
				'tooltip' => '',
				'default' => false
			),
		);
	}

	/**
	 * Initialize the filter controls
	 */
	public function initFilter()
	{
		$options = array();
		$options["all"] = $this->plugin->txt("all_types");
		$types = ($this->parent_cmd == 'listAdminObjects') ? array('crs','grp') : array();
		foreach($this->manager->getSelectableTypes($types) as $type => $name)
		{
			$options[$type] = $name;
		}

		include_once("./Services/Form/classes/class.ilSelectInputGUI.php");
		$si = new ilSelectInputGUI($this->plugin->txt('types'), "type");
		$si->setParent($this->parent_obj);
		$si->setOptions($options);
		$this->addFilterItem($si);
		$si->readFromSession();
		if (!$si->getValue())
		{
			$si->setValue('all');
		}

		include_once("./Services/Form/classes/class.ilSelectInputGUI.php");
		$si = new ilSelectInputGUI($this->plugin->txt('Copyright'), "copyright");
		$options = array(
			'all' => $this->plugin->txt('any_copyright'),
			'with' =>  $this->plugin->txt('with_copyright'),
			'without' =>  $this->plugin->txt('without_copyright'),
		);
		$si->setParent($this->parent_obj);
		$si->setOptions($options);
		$this->addFilterItem($si);
		$si->readFromSession();
		if (!$si->getValue())
		{
			$si->setValue('all');
		}
	}

	/**
	 * Prepare the data to be shown
	 * @param array	$a_data	ref_id => object and copyright data
	 */
	public function prepareData($a_data)
	{
		$this->setData(array_values($a_data));
	}

	/**
	 * Fill a row in the table
	 * @param array $row
	 */
	public function fillRow($row)
	{
		/** @var ilAccessHandler $ilAccess */
		/** @var ilObjectDefinition $objDefinition */
		global $ilUser, $ilAccess, $ilCtrl, $lng, $objDefinition;

		//access checks (visible is already checked, owner has all rights)
		if ($row['owner'] == $ilUser->getId())
		{
			$read_access = true;
			$write_access = true;
		}
		elseif ($ilAccess->checkAccess('read','',$row['ref_id'], $row['type'], $row['obj_id']))
		{
			$read_access = true;
			$write_access = $ilAccess->checkAccess('write', '', $row['ref_id'], $row['type'], $row['obj_id']);
		}
		else
		{
			$read_access = false;
			$write_access = false;
		}

		// get missing data
		if(!$objDefinition->isPlugin($row["type"]))
		{	
			$txt_type = $lng->txt("obj_".$row["type"]);
		}
		else
		{
			include_once("./Services/Component/classes/class.ilPlugin.php");
			$txt_type = ilPlugin::lookupTxt("rep_robj", $row["type"], "obj_".$row["type"]);						
		}
		if (empty($row['title']) && $row['type'] == 'sess')
		{
			include_once "Modules/Session/classes/class.ilObjSession.php";
			$sess = new ilObjSession($row['obj_id'], false);
			$row['title'] = $sess->getFirstAppointment()->appointmentToString();
		}
		// checkbox if object can be changed
		if ($write_access)
		{
			$this->tpl->setVariable("REF_ID", $row["ref_id"]);
		}
		$this->tpl->setVariable("ALT_ICON", $txt_type);
		$this->tpl->setVariable("SRC_ICON", ilObject::_getIcon("", "tiny", $row["type"]));

		// linked or unlinked title with parents
		if ($read_access)
		{
			$this->tpl->setVariable("TITLE", $row["title"] ? $row["title"] : $txt_type);
			$this->tpl->setVariable("URL", ilLink::_getStaticLink($row['ref_id'], $row['type']));
		}
		else
		{
			$this->tpl->setVariable("UNLINKED_TITLE", $row["title"] ? $row["title"] : $txt_type);
		}
		$this->tpl->setVariable("PATH", $this->buildPath($row["ref_id"]));

		// copyright selection
		$this->tpl->setVariable("COPYRIGHT_SELECTION", $this->buildCopyrightSelectionHtml(
			'set_copyright['.$row['ref_id'].']', $row['copyright'], !$write_access));

		// inherited copyright
		if ($row['copyright'] == 'il_copyright_undefined')
		{
			if ($inherited = $this->manager->getInheritedCopyright($row['ref_id']))
			{
				$this->tpl->setVariable("INHERIT_TITLE", $inherited["title"]);
				$this->tpl->setVariable("INHERIT_COPYRIGHT", $this->manager->getCopyrightTitle($inherited['copyright']));
			}
		}

		// actions
		if (in_array($row['type'], array('crs','grp','fold','cat')) && $read_access)
		{
			$ilCtrl->setParameter($this->parent_obj, 'root_id', $row['ref_id']);
			$this->tpl->setVariable('ACTION_URL', $ilCtrl->getLinkTarget($this->parent_obj, 'listSubObjects'));
			$this->tpl->setVariable('ACTION_TITLE', $this->plugin->txt('list_contents'));
		}

		// optional columns
		foreach ($this->getSelectedColumns() as $colid)
		{
			$this->tpl->setCurrentBlock('column');
			$this->tpl->setVariable('CONTENT',  $row[$colid]);
			$this->tpl->parseCurrentBlock();
		}
	}

	/**
	 * Get the HTML of a repository path
	 * @param $a_ref_id
	 * @return string
	 */
	protected function buildPath($a_ref_id)
	{
		return $this->pathGUI->getPath($this->parent_obj->getRootId(), $a_ref_id);
	}

	/**
	 * Build the form selection for the copyright
	 * @param string 	$a_postvar
	 * @param string 	$a_selected
	 * @param bool 		$a_disabled
	 * @return string
	 */
	protected function buildCopyrightSelectionHtml($a_postvar, $a_selected='', $a_disabled = false)
	{
		$tpl = $this->plugin->getTemplate('tpl.nested_select.html');

		foreach ($this->manager->getSelectableCopyrights() as $group => $options)
		{
			foreach ($options as $key => $title)
			{
				$tpl->setCurrentBlock('option');
				$tpl->setVariable('KEY', $key);
				$tpl->setVariable('TITLE', $title);
				if ($key == $a_selected) {
					$tpl->setVariable('SELECTED', 'selected="selected"');
				}
				$tpl->parseCurrentBlock();
			}
			$tpl->setCurrentBlock('optgroup');
			$tpl->setVariable('GROUP', $group);
			$tpl->parseCurrentBlock();
		}

		if($a_disabled)
		{
			$tpl->setVariable('DISABLED', 'disabled="disabled"');
		}
		$tpl->setVariable('POSTVAR', $a_postvar);

		return $tpl->get();
	}
}
