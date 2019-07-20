.. include:: ../Includes.txt

.. _userManual:

===========
User Manual
===========

Installation
============

#. Download the extension from the TYPO3 Extension Repository and install
   it.
   
#. Include the extension static templates

#. Insert the plugin in your page.

#. Edit the configuration flexform and save.

Configuration flexform
======================

#. Select the filter type in the General folder.

	.. figure:: ../Images/FilterTypeInBackEnd.png
		:alt: Filter type in the configuration flexform
  
#. Extensions built with the SAV Library Kickstarter may have a specific WHERE clause for the list view.
   Use the second selector to keep (default) or remove this WHERE clause. 

	- If you choose the Keep option, the filter will add, with a AND operator, its own WHERE clause to the extension WHERE clause.
	- If you chosse the Remove option, the WHERE clause of the filter will replace the extension WHERE clause.

#. Use the third field to provide, if required, a specific CSS file for the filter. 

#. Fill the record storage page to rpvode the pages in which the filter will get the information.

#. Then fill the folder associated with the selected type.

.. toctree::
	:maxdepth: 5
	:titlesonly:
	:glob:
	
	AlphabeticFilter/Index
	MinicalendarFilter/Index
	MonthsFilter/Index
	SearchFilter/Index
	SelectorsFilter/Index
	PageAccessFilter/Index
	DebugFilter/Index

  