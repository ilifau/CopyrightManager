ILIAS Copyright Manager
=======================

Copyright (c) 2016 Institut fuer Lern-Innovation, Friedrich-Alexander-Universitaet Erlangen-Nuernberg, GPLv3, see LICENSE

- Author:   Fred Neumann <fred.neumann@ili.fau.de>


Installation
------------

When you download the Plugin as ZIP file from GitHub, please rename the extracted directory to *CopyrightManager*
(remove the branch suffix, e.g. -master).

1. Copy the CopyrightManager directory to your ILIAS installation at the followin path
(create subdirectories, if neccessary): Customizing/global/plugins/Services/UIComponent/UserInterfaceHook
2. Go to Administration > Plugins
3. Choose action  "Update" for the CopyrightManager plugin
4. Choose action  "Activate" for the CopyrightManager plugin

Usage
-----
This plugin supports ILIAS users for looking through their own contents and the identification of material under copyright.

It adds a new tab "Copyrights" either in "My Workspace" or (if not available) in "Settings" of the Desltop.
It works similar to "My Repository Objects" but with the intention to quickly set the copyright settings of repository objects.

* It can list objects where the user is owner.
* It can list courses or groups where the user is administrator.
* it can list sub objects of those.

Only object types thar are relevant for copyright settings are listed. 
The lists can be filtered by copyright and whether a copyright is defined or not.
Copyrights can be directly set for single objects or commonly for a set of selected objects.

Copyrights are taken from the list defined in "Administration / Metadata" and they are stored in the standard ILIAS records for
copyright settings. Therefore the Settings done in the copyright manager will also be shown in the meta data of an object.

The selection lists for single copyrights are grouped by the descriptions of the pre-defined copyright to get a better overview.
If a copyright is not defined for an object, the "inherited" copyright of an ancestor is schown below the selection.


Version History
===============

Version 0.0.1 (2016-12-13)
-------------------------
First version. 