<?php

/**
 * Copyright Manager
 * Class for data access and manipulation
 *
 * @author Fred Neumann <fred.neumann@fau.de>
 */
class ilCopyrightManager
{
	/** @var ilCopyrightManagerPlugin $plugin */
	public $plugin;

	/** @var  array|null selectable copyrights */
	private $grouped_copyrights = null;

	/** @var  array|null selectable copyrights */
	private $flat_copyrights = null;

	/** @var array	ref_id => [ref_id => int, obj_id => int, type => string, title => string, copyright => string] */
	private $collected_rights = array();

	/**
	 * ilCopyrightManager constructor.
	 * @param $a_plugin
	 */
	public function __construct($a_plugin)
	{
		$this->plugin = $a_plugin;
	}

	/**
	 * Get a list of owned objects
	 *
	 * @param string $a_type		type filter
	 * @param string $a_copyright	copright filter
	 * @return array ref_id => object and copyright data
	 */
	public function getOwnObjects($a_type = '', $a_copyright = '')
	{
		global $ilUser, $ilDB;

		$login = $ilDB->quote($ilUser->getLogin(), 'text');

		$sql = "
			SELECT ref.ref_id, od.*, mr.meta_rights_id copy_id, mr.cpr_and_or copy_set, mr.description copy_desc, $login owner_login
			FROM object_data od
			INNER JOIN object_reference ref ON od.obj_id = ref.obj_id
			INNER JOIN tree ON (ref.ref_id = tree.child AND tree.tree = 1)
			LEFT JOIN il_meta_rights mr ON (od.obj_id = mr.rbac_id AND od.obj_id = mr.obj_id)
			WHERE od.owner = ".$ilDB->quote($ilUser->getId(), "integer");

		if (!empty($a_type) && $a_type != 'all')
		{
			$sql .= " AND od.type = " . $ilDB->quote($a_type, 'text');
		}
		else
		{
			$sql .= " AND ". $ilDB->in('od.type',$this->plugin->getTypeBlacklist(), true, 'text');
		}

		return $this->queryObjects($sql, $a_type, $a_copyright);
	}

	/**
	 * Get a list of administrated objects
	 *
	 * @param string $a_type		type filter
	 * @param string $a_copyright	copright filter
	 * @return array ref_id => object and copyright data
	 */
	public function getAdminObjects($a_type = '', $a_copyright = '')
	{
		global $ilUser, $ilDB;

		$sql = "
			SELECT ref.ref_id, od.*, mr.meta_rights_id copy_id, mr.cpr_and_or copy_set, mr.description copy_desc, ud.login owner_login
			FROM rbac_ua ua
			INNER JOIN rbac_fa fa ON (fa.rol_id = ua.rol_id AND fa.assign = 'y')
			INNER JOIN object_data role ON (role.obj_id = fa.rol_id AND (role.title LIKE 'il_crs_admin_%' OR role.title LIKE 'il_grp_admin_%'))
			INNER JOIN tree ON (tree.child = fa.parent AND tree.tree = 1)
			INNER JOIN object_reference ref ON ref.ref_id = tree.child
			INNER JOIN object_data od ON od.obj_id = ref.obj_id
			INNER JOIN usr_data ud ON ud.usr_id = od.owner
			LEFT JOIN il_meta_rights mr ON (mr.rbac_id = od.obj_id AND  mr.obj_id = od.obj_id )
			WHERE ua.usr_id = ". $ilDB->quote($ilUser->getId(), 'integer');

		if (!empty($a_type) && $a_type != 'all')
		{
			$sql .= " AND od.type = " . $ilDB->quote($a_type, 'text');
		}

		return $this->queryObjects($sql, $a_type, $a_copyright);
	}

	/**
	 * Get a list of administrated objects
	 *
	 * @param int	$a_root_id		root node
	 * @param string $a_type		type filter
	 * @param string $a_copyright	copright filter
	 * @return array ref_id => object and copyright data
	 */
	public function getSubObjects($a_root_id, $a_type = '', $a_copyright = '')
	{
		global $ilDB, $tree;

		$ref_ids = $tree->getSubTreeIds($a_root_id);

		$sql = "
			SELECT ref.ref_id, od.*, mr.meta_rights_id copy_id, mr.cpr_and_or copy_set, mr.description copy_desc, ud.login owner_login
			FROM object_reference ref
			INNER JOIN object_data od ON od.obj_id = ref.obj_id
			INNER JOIN usr_data ud ON ud.usr_id = od.owner
			LEFT JOIN il_meta_rights mr ON (od.obj_id = mr.rbac_id AND od.obj_id = mr.obj_id)
			WHERE ".$ilDB->in('ref.ref_id', $ref_ids, false, 'integer');

		if (!empty($a_type) && $a_type != 'all')
		{
			$sql .= " AND od.type = " . $ilDB->quote($a_type, 'text');
		}
		else
		{
			$sql .= " AND ". $ilDB->in('od.type',$this->plugin->getTypeBlacklist(), true, 'text');
		}

		return $this->queryObjects($sql, $a_type, $a_copyright);
	}

	/**
	 * Query for objects and filter the result
	 *
	 * @param string $a_sql			basic database query
	 * @param string $a_type		type filter
	 * @param string $a_copyright	copyright filter
	 * @return array ref_id => object and copyright data
	 */
	protected function queryObjects($a_sql, $a_type = '', $a_copyright = '')
	{
		/** @var ilAccessHandler $ilAccess */
		global $ilDB, $ilAccess;

		$objects = array();
		$res = $ilDB->query($a_sql);
		while($row = $ilDB->fetchAssoc($res))
		{
			// get the copyright identifier
			$row['copyright'] = $this->extractCopyright($row);

			// remember the rights for child access
			$this->collected_rights[$row['ref_id']] = array(
				'ref_id' => $row['ref_id'],
				'obj_id' => $row['obj_id'],
				'type' => $row['type'],
				'title' => $row['title'],
				'copyright' => $row['copyright']);

			// add to result if copyright filter matches and object is accessible
			if (!($row['copyright'] == 'il_copyright_undefined' && $a_copyright == 'with')
				&& !($row['copyright'] != 'il_copyright_undefined' && $a_copyright == 'without')
				&& $ilAccess->checkAccess('visible', '', $row['ref_id'], $row['type'], $row['obj_id']))
			{
				$objects[$row['ref_id']] = $row;
			}
		}
		return $objects;
	}

	/**
	 * Extract the copyright setting from a row of il_meta_rights
	 *
	 * - pre-defined copyrights are delivered like il_copyright_entry__0__7
	 * - undefined copyrights are delivered as il_copyright_undefined
	 * - custom copyrights are delivered as il_copyright_custom
	 *
	 * @param array		$row
	 * @return string
	 */
	protected function extractCopyright($row)
	{
		if (empty($row['copy_set']) || empty($row['copy_desc']) || $row['copy_set'] == 'No')
		{
			return 'il_copyright_undefined';
		}
		elseif (substr($row['copy_desc'],0, 12) == 'il_copyright')
		{
			return  $row['copy_desc'];
		}
		else
		{
			return 'il_copyright_custom';
		}
	}


	/**
	 * Save a list of copyright settings
	 * @param array 	$a_objects		ref_id => object and copyright data
	 * @param array		$a_settings		ref_id => copyright key
	 */
	public function saveCopyrights($a_objects, $a_settings)
	{
		/** @var ilDB	$ilDB */
		/** @var ilAccessHandler $ilAccess */
		global $ilDB, $ilAccess;

		foreach ($a_settings as $ref_id => $copyright)
		{
			$object = $a_objects[$ref_id];

			// save only changed copyrights for listed objects with write access
			if (!empty($object)
				&& $object['copyright'] != $copyright
				&& $ilAccess->checkAccess('write', '', $ref_id, $object['type'], $object['obj_id']))
			{
				if ($copyright == 'il_copyright_undefined' && !empty($object['copy_id']))
				{
					// delete copyright if it is set to undefined
					$ilDB->manipulate("DELETE FROM il_meta_rights WHERE meta_rights_id ="
						.$ilDB->quote($object['copy_id'], 'integer'));
				}
				elseif ($copyright != 'il_copyright_undefined' && empty($object['copy_id']))
				{
					// insert a new copyright setting
					$id = $ilDB->nextId('il_meta_rights');
					$ilDB->insert('il_meta_rights', array(
						'meta_rights_id' => array('integer', $id),
						'rbac_id' => array('integer', $object['obj_id']),
						'obj_id' => array('integer', $object['obj_id']),
						'cpr_and_or' => array('text', 'Yes'),
						'description' => array('text', $copyright)
					));
				}
				else
				{
					// update a changed copyright setting
					$ilDB->update('il_meta_rights', array(
						'cpr_and_or' => array('text', 'Yes'),
						'description' => array('text', $copyright)
					), array(
						'meta_rights_id' => array('integer', $object['copy_id']),
					));
				}

				// set the change data
				$ilDB->manipulate("UPDATE object_data SET last_update = now() WHERE obj_id ="
					.$ilDB->quote($object['obj_id'], 'integer'));

			}
		}
	}

	/**
	 * Get a list of selectable types (for the type filter)
	 * @param array $a_types	list of object type keys, e.g. ['crs', 'cat']
	 * @return array			key => type name
	 */
	public function getSelectableTypes($a_types = array())
	{
		/** @var ilObjectDefinition  $objDefinition */
		global $objDefinition, $lng;

		if (empty($a_types))
		{
			$a_types = array_keys($objDefinition->getSubObjectsRecursively("root"));
		}

		$types = array();
		foreach($a_types as $type)
		{
			if(!$objDefinition->isPlugin($type))
			{
				$types[$type] = $lng->txt("obj_".$type);
			}
			else
			{
				include_once("./Services/Component/classes/class.ilPlugin.php");
				$types[$type] = ilPlugin::lookupTxt("rep_robj", $type, "obj_".$type);
			}
		}

		foreach($this->plugin->getTypeBlacklist() as $type)
		{
			unset($types[$type]);
		}

		asort($types);
		return $types;
	}

	/**
	 * Get a cached list of copyrights
	 *
	 * @param 	bool	$a_flat		produce a list without option groups
	 * @return  array|null			group => [key => title] (grouped) or key => title (flat)
	 */
	public function getSelectableCopyrights($a_flat = false)
	{
		global $ilDB;

		if (!isset($this->grouped_copyrights))
		{
			$query = "SELECT * FROM il_md_cpr_selections";
			$res = $ilDB->query($query);

			$options = array();
			while($row = $ilDB->fetchAssoc($res))
			{
				$group = empty($row['description']) ?  $this->plugin->txt('other_copyrights') : $row['description'];
				$title = $row['title'];
				$key = 'il_copyright_entry__'.IL_INST_ID.'__'.$row['entry_id'];
				$options[$group][$key] = $title;
			}
			$options[$this->plugin->txt('other_copyrights')]['il_copyright_undefined'] = $this->plugin->txt('undefined_copyright');
			$options[$this->plugin->txt('other_copyrights')]['il_copyright_custom'] = $this->plugin->txt('custom_copyright');

			// sort the options by group and title
			// create the flat variant
			ksort($options);
			$this->flat_copyrights = array();
			foreach (array_keys($options) as $group)
			{
				asort($options[$group]);
				$this->flat_copyrights = array_merge($this->flat_copyrights, $options[$group]);

			}
			$this->grouped_copyrights = $options;
		}

		return ($a_flat ? $this->flat_copyrights : $this->grouped_copyrights);
	}

	/**
	 * Get the title of a copyright setting
	 * @param string $a_copyright
	 * @return string
	 */
	public function getCopyrightTitle($a_copyright)
	{
		$copyrights = $this->getSelectableCopyrights(true);
		return isset($copyrights[$a_copyright]) ? $copyrights[$a_copyright] : $this->plugin->txt('custom_copyright');
	}

	/**
	 * Get an inherited copyright
	 *
	 * @param $a_ref_id
	 * @return	array|false	[ref_id => int, obj_id => int, type => string, title => string, copyright => string]
	 */
	public function getInheritedCopyright($a_ref_id)
	{
		/** @var ilTree */
		global $tree, $ilDB;

		$path = array_reverse($tree->getNodePath($a_ref_id));
		// remove the current node
		array_shift($path);

		while ($node = array_shift($path))
		{
			$ref_id = $node['child'];
			if (!isset($this->collected_rights[$ref_id]))
			{
				$this->collected_rights[$ref_id] = array (
					'ref_id' => $node['child'],
					'obj_id' => $node['obj_id'],
					'type' => $node['type'],
					'title' => $node['title'],
					'copyright' => $this->lookupCopyright($node['obj_id'])
				);
			}
			if ($this->collected_rights[$ref_id]['copyright'] != 'il_copyright_undefined')
			{
				return $this->collected_rights[$ref_id];
			}
		}
		return false;
	}


	public function lookupCopyright($a_obj_id)
	{
		global $ilDB;

		$sql = "
			SELECT mr.meta_rights_id copy_id, mr.cpr_and_or copy_set, mr.description copy_desc
			FROM il_meta_rights mr
			WHERE mr.rbac_id = " .$ilDB->quote($a_obj_id, "integer"). "
			AND mr.obj_id = ".$ilDB->quote($a_obj_id, "integer");

		$res = $ilDB->query($sql);
		if ($row = $ilDB->fetchAssoc($res))
		{
			return $this->extractCopyright($row);
		}
		else
		{
			return 'il_copyright_undefined';
		}
	}
}