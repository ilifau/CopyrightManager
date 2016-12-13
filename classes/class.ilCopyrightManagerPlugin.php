<?php
/* Copyright (c) 2016 Institut fuer Lern-Innovation, GPLv3, see LICENSE */

include_once("./Services/UIComponent/classes/class.ilUserInterfaceHookPlugin.php");
 
/**
 * Copyright Manager Plugin
 *
 * @author Fred Neumann <fred.neumann@fau.de>
 */
class ilCopyrightManagerPlugin extends ilUserInterfaceHookPlugin
{

	function getPluginName()
	{
		return "CopyrightManager";
	}

	/**
	 * Get a list of types that are not relevant for copyright settings
	 * @return array
	 */
	function getTypeBlacklist()
	{
		return array('chtr','feed','cat','catr','crsr','itgr','poll','prg','prtt','webr',
			'root','rolf','role','usr',
			'rcat','rcrs','rwik','rlm','rglo','rfil','rgrp','rtst',
			'xema','xxco','xlvo','xpdl','xcos');
	}

	/**
	 * Get the default root id for path display
	 * @return int
	 */
	function getDefaultRootId()
	{
		global $ilCust;

		if (is_object($ilCust))
		{
			return $ilCust->getSetting('ilias_repository_cat_id');
		}
		else
		{
			return 1;
		}
	}
}
