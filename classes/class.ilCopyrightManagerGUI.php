<?php
/* Copyright (c) 2016 Institut fuer Lern-Innovation, GPLv3, see LICENSE */

/**
 * Copyright Manager GUI
 *
 * @author Fred Neumann <fred.neumann@fau.de>
 *
 * @ilCtrl_IsCalledBy ilCopyrightManagerGUI: ilUIPluginRouterGUI
 */
class ilCopyrightManagerGUI
{
	/** @var ilCtrl $ctrl */
	protected $ctrl;

	/** @var ilTemplate $tpl */
	protected $tpl;

	/** @var ilCopyrightManagerPlugin $plugin */
	public $plugin;

	/** @var  ilCopyrightManager $manager */
	public $manager;

	/** @var string  */
	protected $view = 'listOwnObjects';

	/** @var  ilCopyrightManagerTableGUI */
	protected $table;

	/**
	 * ilCopyrightManagerGUI constructor.
	 */
	public function __construct()
	{
		global $ilCtrl, $tpl;

		$this->ctrl = $ilCtrl;
		$this->tpl = $tpl;
		$this->plugin = ilPlugin::getPluginObject(IL_COMP_SERVICE, 'UIComponent', 'uihk', 'CopyrightManager');
		$this->plugin->includeClass('class.ilCopyrightManager.php');
		$this->manager = new ilCopyrightManager($this->plugin);
	}

	/**
	 * Controller
	 * @return bool
	 */
	public function &executeCommand()
	{
		/** @var ilErrorHandling $ilErr */
		global $ilErr, $lng;

		// catch hack attempts
		if ($_SESSION["AccountId"] == ANONYMOUS_USER_ID)
		{
			$ilErr->raiseError($lng->txt("msg_not_available_for_anon"), $ilErr->MESSAGE);
		}

		$cmd = $this->ctrl->getCmd('listOwnObjects');
		switch($cmd)
		{
			case 'listOwnObjects':
			case 'listAdminObjects':
			case 'listSubObjects':
				$this->determineView($cmd);
				$this->prepareOutput();
				$this->listObjects();
				break;

			case 'applyFilter':
			case 'resetFilter':
				$this->determineView($_GET['view']);
				$this->prepareOutput();
				$this->$cmd();
				break;

			case 'saveCopyrights':
			case 'applyCopyright':
				$this->determineView($_GET['view']);
				$this->$cmd();
				break;
		}
		
		return true;
	}

	/**
	 * Determine the currently viewed list
	 * The name of the view is identical to the command for calling it
	 */
	protected function determineView($view)
	{
		switch($view)
		{
			case 'listOwnObjects':
			case 'listAdminObjects':
			case 'listSubObjects':
				$this->view = $view;
				break;
			default:
				$this->view = 'listOwnObjects';
		}
		$this->ctrl->setParameter($this, 'view', $this->view);

		if ($view == 'listSubObjects')
		{
			$this->ctrl->saveParameter($this, 'root_id');
		}
	}

	/**
	 * Prepare the page header, tabs etc.
	 */
	protected function prepareOutput()
	{
		/** @var ilLocatorGUI $ilLocator */
		/** @var ilTabsGUI $ilTabs */
		global $ilLocator, $ilSetting, $tpl, $ilTabs, $lng;

		if (!$ilSetting->get('disable_personal_workspace'))
		{
			// Tab is shown in workspace, add it's locator item
			$ilLocator->addItem($lng->txt('personal_workspace'),$this->ctrl->getLinkTargetByClass('ilPersonalDesktopGUI','jumpToWorkspace'));
		}

		// load the page template
		$tpl->getStandardTemplate();
		$tpl->setLocator();
		$tpl->setTitle($this->plugin->txt('copyrights_title'));
		$tpl->setDescription($this->plugin->txt('copyrights_description'));

		// load the saved tabs of the workspace or settings
		$ilTabs->target = $_SESSION['CopyrightManager']['TabTarget'];
		$ilTabs->activateTab('copyrights');

		// addd the plugins sub tabs
		$ilTabs->addSubTab('listOwnObjects', $this->plugin->txt('list_own_objects'), $this->ctrl->getLinkTarget($this,'listOwnObjects'));
		$ilTabs->addSubTab('listAdminObjects', $this->plugin->txt('list_admin_objects'), $this->ctrl->getLinkTarget($this,'listAdminObjects'));
		if ($this->view == 'listSubObjects')
		{
			$title = ilObject::_lookupTitle(ilObject::_lookupObjId($this->getRootId()));
			$ilTabs->addSubTab('listSubObjects', sprintf($this->plugin->txt('list_contents_of'), $title), $this->ctrl->getLinkTarget($this,'listSubObjects'));
		}
		$ilTabs->activateSubTab($this->view);

		ilUtil::sendInfo($this->plugin->txt('general_note'), false);
	}

	/**
	 * Show a list of objects
	 * The list is determined by the view
	 */
	protected function listObjects()
	{
		$this->initTable();
		$this->table->prepareData($this->getObjects());
		$this->tpl->setContent($this->table->getHTML());
		$this->tpl->show();
	}

	/**
	 * Apply the selected filter to the current list
	 */
	protected function applyFilter()
	{
		$this->initTable();
		$this->table->resetOffset();
		$this->table->writeFilterToSession();
		$this->listObjects();
	}

	/**
	 * Reset the filter of the current list
	 */
	protected function resetFilter()
	{
		$this->initTable();
		$this->table->resetOffset();
		$this->table->resetFilter();
		$cmd = $this->view;
		$this->listObjects();
	}

	/**
	 * Save the selected copyrights of the listed objects
	 * (only changes will be saved)
	 */
	protected function saveCopyrights()
	{
		$this->initTable();
		$settings = $_POST['set_copyright'];
		$this->manager->saveCopyrights($this->getObjects(), $settings);

		$this->table->resetOffset();
		$this->table->writeFilterToSession();
		ilUtil::sendSuccess($this->plugin->txt('copyrights_saved'),true);
		$this->ctrl->redirect($this, $this->view);
	}

	/**
	 * Apply a common copyright to selected objects
	 */
	protected function applyCopyright()
	{
		if (!is_array($_POST['ref_id']))
		{
			ilUtil::sendFailure($this->plugin->txt('please_select_objects'),true);
			$this->ctrl->redirect($this, $this->view);
		}

		$this->initTable();
		$settings = array();
		foreach($_POST['ref_id'] as $ref_id)
		{
			$settings[(int) $ref_id] = $_POST['apply_copyright'];
		}
		$this->manager->saveCopyrights($this->getObjects(), $settings);

		$this->table->resetOffset();
		$this->table->writeFilterToSession();
		ilUtil::sendSuccess($this->plugin->txt('copyright_applied'),true);
		$this->ctrl->redirect($this, $this->view);
	}


	/**
	 * Initialize the table of objects thet should be viewed
	 */
	protected function initTable()
	{
		$this->plugin->includeClass('class.ilCopyrightManagerTableGUI.php');
		$this->table = new ilCopyrightManagerTableGUI($this, $this->view);
	}

	/**
	 * Get the objects of the current view
	 * This needs a call of initTable() before
	 * @return array ref_id => object and copyright data
	 */
	protected function getObjects()
	{
		/** @var ilAccessHandler $ilAccess */
		global $ilAccess, $lng;

		$type_filter = $this->table->getFilterItemByPostVar('type')->getValue();
		$copyright_filter = $this->table->getFilterItemByPostVar('copyright')->getValue();

		switch ($this->view)
		{
			case 'listOwnObjects':
				return $this->manager->getOwnObjects($type_filter, $copyright_filter);

			case 'listAdminObjects':
				return $this->manager->getAdminObjects($type_filter, $copyright_filter);

			case 'listSubObjects':
				$root_id = $this->getRootId();
				if (!$ilAccess->checkAccess('read', '', $root_id))
				{
					ilUtil::sendFailure($lng->txt('permission_denied'));
					$this->ctrl->redirect($this,'listAdminObjects');
				}
				return $this->manager->getSubObjects($root_id, $type_filter, $copyright_filter);

			default:
				return array();
		}
	}

	/**
	 * Get the root ref_id for listSubObjects() or display of paths
	 * @return int
	 */
	public function getRootId()
	{
		if ($this->view == 'listSubObjects')
		{
			return (int) $_GET['root_id'];
		}
		else
		{
			return $this->plugin->getDefaultRootId();
		}
	}
}
