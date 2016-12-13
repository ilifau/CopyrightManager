<?php
/* Copyright (c) 2016 Institut fuer Lern-Innovation, GPLv3, see LICENSE */

include_once("./Services/UIComponent/classes/class.ilUIHookPluginGUI.php");

/**
 * User interface hook class
 *
 * @author Fred Neumann <fred.neumann@fau.de>
 * @version $Id$
 */
class ilCopyrightManagerUIHookGUI extends ilUIHookPluginGUI
{
	/**
	 * Modify GUI objects, before they generate ouput
	 *
	 * @param string $a_comp component
	 * @param string $a_part string that identifies the part of the UI that is handled
	 * @param string $a_par array of parameters (depend on $a_comp and $a_part)
	 */
	function modifyGUI($a_comp, $a_part, $a_par = array())
	{
		/** @var ilCtrl $ilCtrl */
		/** @var ilTabsGUI $ilTabs */
		global $ilCtrl, $ilTabs, $ilSetting;

		switch ($a_part)
		{
			case 'tabs':

				// add the copyrights tab preferably to the personal workspace
				// add it to the personal settings, if workspace is not activated
				if ($ilCtrl->getCmdClass() == 'ilobjworkspacerootfoldergui'
					|| $ilCtrl->getCmdClass() == 'ilobjectownershipmanagementgui'
					|| ($ilCtrl->getCmdClass() == 'ilpersonalsettingsgui' && $ilSetting->get('disable_personal_workspace')))
				{

					$ilTabs->addTab('copyrights', $this->plugin_object->txt('copyrights'),
						$ilCtrl->getLinkTargetByClass(array('ilUIPluginRouterGUI','ilCopyrightManagerGUI')));

					// save the existing tabs of workspace or settings for reuse in the plugin gui
					// (links are already generated with working controller paths)
					// not nice, but effective
					$_SESSION['CopyrightManager']['TabTarget'] = $ilTabs->target;
				}
				break;

			default:
				break;
		}
	}
}

?>
