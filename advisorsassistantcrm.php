<?php

/*
Plugin Name: Advisors Assistant Forms
Plugin URI: http://www.advisorsassistant.com
Description: Transfer form data directly into Advisors Assistant CRM. Developer can use multiple conditions for controlling data transfers.
Version: 1.0.3
Author: Trent E. Di Renna
License: GPL2
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Text Domain: advisors-assistant-forms
Domain Path: /languages

------------------------------------------------------------------------
Copyright 2016 Client Marketing Systems Inc.
last updated: October 26, 2016

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA 02111-1307 USA
*/

define('GF_ADVISORSASSISTANTCRM_VERSION', '1.0.3');

add_action('gform_loaded', array('GF_AdvisorsAssistantCRM_Bootstrap', 'load'), 5);

class GF_AdvisorsAssistantCRM_Bootstrap
{
	public static function load()
	{
		require_once('class-gf-advisorsassistantcrm.php');
		GFAddOn::register('GFAdvisorsAssistantCRM');
	}
}

function gf_advisorsassistant()
{
	return GFAdvisorsAssistantCRM::get_instance();
}