.. include:: ../Includes.txt

.. _configuration:

=============
Configuration
=============

Configuration of the extension can be done by editing the plugin
flexform.

Special configurations are also possible in TypoScript. 

Configuration of the Templates, Partials, Layouts Root Paths
============================================================

Each filter is defined by means of a default Fluid template in the 
extension folder ``Resources/Private/Default``. 
The default root paths can be changed by modifying the following 
``constants`` in TypoScript.

::

   plugin.tx_savfilters.view.templateRootPath = yourTemplateRootPath/
   plugin.tx_savfilters.view.layoutRootPath = yourLayoutRootPath/	 
   plugin.tx_savfilters.view.partialRootPath = yourpartialRootPath/	

Configuration of the Icons
==========================

Use the following syntax to change the mini calendar icons.

::

   plugin.tx_savfilters.settings.miniCalendarFilter.leftArrowIcon = yourLeftArrowIconPath
   plugin.tx_savfilters.settings.miniCalendarFilter.rightArrowIcon = yourRightArrowIconPath	  	
	
Configuration of the CSS File
=============================	
	
Use the following syntax to change the default CSS file.	
	
::

   plugin.tx_savfilters.settings.alphabeticFilter.includeCSS = yourCSSFilePath
   plugin.tx_savfilters.settings.minicalendarFilter.includeCSS = yourCSSFilePath
   plugin.tx_savfilters.settings.monthsFilter.includeCSS = yourSearchIconPath	
   plugin.tx_savfilters.settings.searchFilter.includeCSS = mySearchIconPath	
   plugin.tx_savfilters.settings.selectorsFilter.includeCSS = mySearchIconPath		