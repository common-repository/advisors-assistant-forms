<?php

GFForms::include_feed_addon_framework();

class GFAdvisorsAssistantCRM extends GFFeedAddOn
{
	protected $_version = GF_ADVISORSASSISTANTCRM_VERSION;
	protected $_min_gravityforms_version = '1.9.12';
	protected $_slug = 'advisors-assistant-forms';
	protected $_path = 'advisors-assistant-forms/advisorsassistantcrm.php';
	protected $_full_path = __FILE__;
	protected $_url = 'http://www.gravityforms.com';
	protected $_title = 'Advisors Assistant Forms';
	protected $_short_title = 'Advisors Assistant Forms';
	//protected $_enable_rg_autoupgrade = true;
	protected $api = null;
	private static $_instance = null;

	/* Permissions */
	protected $_capabilities_settings_page = 'gravityforms_advisorsassistantcrm';
	protected $_capabilities_form_settings = 'gravityforms_advisorsassistantcrm';
	protected $_capabilities_uninstall = 'gravityforms_advisorsassistantcrm_uninstall';

	/* Members plugin integration */
	protected $_capabilities = array('gravityforms_advisorsassistantcrm', 'gravityforms_advisorsassistantcrm_uninstall');

	/* Local handling variables */
	protected $skip_init_aa_check = false;

	/**
	 * Get instance of this class.
	 *
	 * @access public
	 * @static
	 * @return $_instance
	 */
	public static function get_instance()
	{
		if ( self::$_instance == null )
			self::$_instance = new self;

		return self::$_instance;
	}

	/**
	 * Register needed plugin hooks and PayPal delayed payment support.
	 *
	 * @access public
	 * @return void
	 */
	public function init()
	{
		parent::init();
	}


	/**
	 * Register needed styles.
	 *
	 * @access public
	 * @return array $styles
	 */
	public function styles()
	{
		$styles = array(
			array(
				'handle'  => 'gform_advisorsassistantcrm_form_settings_css',
				'src'     => $this->get_base_url() . '/css/form_settings.css',
				'version' => $this->_version,
				'enqueue' => array(
					array('admin_page' => array('form_settings')),
				)
			),
			array(
				'handle'  => 'gform_advisorsassistantcrm_plugin_settings_css',
				'src'     => $this->get_base_url() . '/css/plugin_settings.css',
				'version' => $this->_version,
				'enqueue' => array(
					array('admin_page' => array('plugin_settings')),
				)
			)
		);

		return array_merge(parent::styles(), $styles);
	}

	public function scripts()
	{
		$scripts = array(
			array(
				'handle'    => 'aa_auth_js',
				'src'       => $this->get_base_url() . '/js/aa_auth.js',
				'version'   => $this->_version,
				'deps'      => array('jquery'),
				'enqueue'   => array(
					array(
						'admin_page' => array('plugin_settings')
					)
				)
			),
		);

		return array_merge(parent::scripts(), $scripts);
	}

	/**
	 * Setup plugin settings fields.
	 *
	 * @access public
	 * @return array
	 */
	public function plugin_settings_fields()
	{
		return array(
			array(
				'title'       => '',
				'description' => $this->plugin_settings_description(),
				'fields'      => array(
					array(
						'name'              => 'apiURL',
						'label'             => __('API URL', 'advisors-assistant-forms'),
						'type'              => 'text',
						'class'             => 'medium',
					),
					array(
						'name'              => 'authURL',
						'label'             => __('Auth URL', 'advisors-assistant-forms'),
						'type'              => 'text',
						'class'             => 'medium',
					),
					array(
						'name'              => 'clientID',
						'label'             => __('OAuth Client ID', 'advisors-assistant-forms'),
						'type'              => 'text',
						'class'             => 'medium',
					),
					array(
						'name'              => 'clientSecret',
						'label'             => __('OAuth Client Secret', 'advisors-assistant-forms'),
						'type'              => 'text',
						'class'             => 'medium',
					),
					array(
						'name'              => 'accessKey',
						'label'             => __('Access Token', 'advisors-assistant-forms'),
						'type'              => 'text',
						'class'             => 'medium',
					),
					array(
						'name'              => 'refreshKey',
						'label'             => __('Refresh Token', 'advisors-assistant-forms'),
						'type'              => 'text',
						'class'             => 'medium',
					),
					//array(
					//    'name'              => 'workflowID',
					//    'label'             => __('Workflow Master ID', 'advisors-assistant-forms'),
					//    'type'              => 'text',
					//    'class'             => 'medium',
					//),
					array(
						'name'              => 'adminEmail',
						'label'             => __('Admin Email', 'advisors-assistant-forms'),
						'type'              => 'text',
						'class'             => 'medium',
					),
					array(
						'name'              => 'aesKey',
						'label'             => __('AES Signing Key', 'advisors-assistant-forms'),
						'type'              => 'text',
						'class'             => 'medium',
					),
				),
			),

		);
	}

	/**
	 * Prepare plugin settings description.
	 *
	 * @access public
	 * @return string $description
	 */
	public function plugin_settings_description()
	{
		$description  = '<p>';
		$description .= sprintf(
			__( 'Advisors Assistant CRM is a contact management tool that makes it easy to track cases, contacts and deals. Use Gravity Forms to collect customer '.
				'information and automatically add them to your Advisors Assistant CRM account. If you don\'t have a Advisors Assistant CRM account, you can %1$s sign ' .
				'up for one here.%2$s', 'advisors-assistant-forms' ),
			'<a href="http://www.advisorsassistant.com/" target="_blank">', '</a>'
		);
		$description .= '</p>';

		$callback_url = $this->get_base_url() . '/callback.php';

		$description  .= '<p>';
		$description .= sprintf(
			__('To log into the authorization server and get a fresh access and refresh token, click the following link: %1$sAuthorization server%2$s', 'advisors-assistant-forms'),
			"<a style='text-decoration:underline;cursor:pointer;' onclick='go_auth_server(\"" . $callback_url . "\");'>", '</a>'
		);
		$description .= '</p>';

		if ($_SERVER['REQUEST_METHOD'] == 'POST' && $this->skip_init_aa_check == false)
		{
			$this->skip_init_aa_check = true;
			return $description;
		}

		$settings = $this->get_plugin_settings();

		//$this->log_error( __METHOD__ . '(): ' . $_SERVER['REQUEST_METHOD']);

		if (empty($settings['apiURL']) || empty($settings['accessKey']))
		{
			$description .= '<p>';
			$description .= __('Advisors Assistant CRM Integration for Gravity Forms requires both the API URL and access key, which we will provide to you.', 'advisors-assistant-forms');
			$description .= '</p>';
		} else
		{
			$result = $this->initialize_aa_api(true);
			if (is_string($result))
			{
				$description .= "<p>{$result}</p>";
			}
			elseif ($result instanceof Exception)
			{
				$desc_msg = "";
				switch ($result->getCode())
				{
					case 401:
						$desc_msg = "The entered credentials are incorrect or expired. Please contact your tech support for further help.";
						break;
					default:
						$desc_msg = "The API server sent the following error message.  Please contact your tech support for further help.";
						$desc_msg .= "<br /><br /><span style='font-weight: bold;'>" . $result->getMessage() . "</span>";
						break;
				}
				$description .= "<p style='font-weight: bold;'>{$desc_msg}</p>";
			}
		}

		return $description;
	}

	/**
	 * Setup fields for feed settings.
	 *
	 * @access public
	 * @return array
	 */
	public function feed_settings_fields()
	{
		/* Build base fields array. */
		$base_fields = array(
			'title'  => '',
			'fields' => array(
				array(
					'name'           => 'feedName',
					'label'          => __('Feed Name', 'advisors-assistant-forms'),
					'type'           => 'text',
					'class'			 => 'medium',
					'required'       => true,
					'default_value'  => $this->get_default_feed_name(),
					'tooltip'        => '<h6>'. __('Name', 'advisors-assistant-forms') .'</h6>' . __('Enter a feed name to uniquely identify this setup.', 'advisors-assistant-forms')
				),
				array(
					'name'           => 'contactStandardFields',
					'label'          => __('Map Fields', 'advisors-assistant-forms'),
					'type'           => 'field_map',
					'field_map'      => $this->standard_fields_for_feed_mapping(),
					'tooltip'        => '<h6>'. __('Map Fields', 'advisors-assistant-forms') .'</h6>' . __('Select which Gravity Form fields pair with their respective Advisors Assistant CRM fields.', 'advisors-assistant-forms')
				),
				array(
					'name'           => 'noteTopic',
					'label'          => __('Note Topic', 'advisors-assistant-forms'),
					'type'           => 'text',
					'class'			 => 'medium',
					'default_value'  => 'Notes',
					'tooltip'        => '<h6>'. __('Name Type', 'advisors-assistant-forms') .'</h6>' . __('Enter the Note Topic to file notes entered through the API under.', 'advisors-assistant-forms')
				),
				array(
					'name'           => 'nameType',
					'label'          => __('Primary Name Type', 'advisors-assistant-forms'),
					'type'           => 'text',
					'class'			 => 'medium',
					'default_value'  => 'Prospect (Website)',
					'tooltip'        => '<h6>'. __('Name Type', 'advisors-assistant-forms') .'</h6>' . __('Enter the Primary Name Type to assign to your API entries.', 'advisors-assistant-forms')
				),
				array(
					'name'           => 'workflowID',
					'label'          => __('Master Workflow GUID', 'advisors-assistant-forms'),
					'type'           => 'text',
					'class'			 => 'medium',
					'tooltip'        => '<h6>'. __('Name Type', 'advisors-assistant-forms') .'</h6>' . __('Enter a Master Workflow GUID to create a workflow for your API entries submitted from this form.', 'advisors-assistant-forms')
				),
				array(
					'name'           => 'splitName',
					'label'          => __( 'Split "Last Name" Into First and Last?', 'advisors-assistant-forms' ),
					'type'           => 'checkbox',
					//'onclick'        => "jQuery(this).parents('form').submit();",
					'choices'        => array(
						array(
							'name'       => 'splitNameYes',
							//'label'      => __( 'Assign Task to Created Contact', 'advisors-assistant-forms' ),
						),
					)
				),
			)
		);

		/* Build conditional logic fields array. */
		$conditional_fields = array(
			'title'      => __( 'Feed Conditional Logic', 'advisors-assistant-forms' ),
			'dependency' => array( $this, 'show_conditional_logic_field' ),
			'fields'     => array(
				array(
					'name'           => 'feedCondition',
					'type'           => 'feed_condition',
					'label'          => __( 'Conditional Logic', 'advisors-assistant-forms' ),
					'checkbox_label' => __( 'Enable', 'advisors-assistant-forms' ),
					'instructions'   => __( 'Export to Advisors Assistant CRM if', 'advisors-assistant-forms' ),
					'tooltip'        => '<h6>' . __( 'Conditional Logic', 'advisors-assistant-forms' ) . '</h6>' . __( 'When conditional logic is enabled, form submissions will only be exported to Advisors Assistant CRM when the condition is met. When disabled, all form submissions will be posted.', 'advisors-assistant-forms' )
				),

			)
		);

		return array($base_fields, $conditional_fields);
	}

	public function field_map_title()
	{
		return "AA Field";
	}

	/**
	 * Prepare standard fields for feed field mapping.
	 *
	 * @access public
	 * @return array
	 */
	public function standard_fields_for_feed_mapping()
	{
		return array(
			array(
				'name'          => 'LastName',
				'label'         => __('Last Name', 'advisors-assistant-forms'),
				'required'      => true,
				'field_type'    => array('name', 'text', 'hidden'),
				//'default_value' => $this->get_first_field_by_type( 'name', 6 ),
			),
			array(
				'name'          => 'FirstName',
				'label'         => __('First Name', 'advisors-assistant-forms'),
				'field_type'    => array('name', 'text', 'hidden'),
				//'default_value' => $this->get_first_field_by_type( 'name', 3 ),
			),
			array(
				'name'          => 'MiddleName',
				'label'         => __('Middle Name', 'advisors-assistant-forms'),
				'field_type'    => array('name', 'text', 'hidden'),
				//'default_value' => $this->get_first_field_by_type( 'name', 3 ),
			),
			array(
				'name'          => 'Prefix',
				'label'         => __('Prefix', 'advisors-assistant-forms'),
				'field_type'    => array('select', 'name', 'text', 'hidden'),
				//'default_value' => $this->get_first_field_by_type( 'name', 3 ),
			),
			array(
				'name'          => 'Suffix',
				'label'         => __('Suffix', 'advisors-assistant-forms'),
				'field_type'    => array('select', 'name', 'text', 'hidden'),
				//'default_value' => $this->get_first_field_by_type( 'name', 3 ),
			),
			array(
			    'name'          => 'EmailAddress',
			    'label'         => __( 'Primary Email Address', 'advisors-assistant-forms' ),
			    'field_type'    => array('email', 'text', 'hidden'),
			    //'default_value' => $this->get_first_field_by_type( 'email' ),
			),
			array(
			    'name'          => 'Business',
			    'label'         => __('Business', 'advisors-assistant-forms'),
			    'field_type'    => array('text', 'hidden'),
			    //'default_value' => $this->get_first_field_by_type( 'text' ),
			),
			array(
			    'name'          => 'AddressLine1',
			    'label'         => __('Address 1', 'advisors-assistant-forms'),
			    'field_type'    => array('text', 'hidden'),
			    //'default_value' => $this->get_first_field_by_type( 'text' ),
			),
			array(
			    'name'          => 'AddressLine2',
			    'label'         => __('Address 2', 'advisors-assistant-forms'),
			    'field_type'    => array('text', 'hidden'),
			    //'default_value' => $this->get_first_field_by_type( 'text' ),
			),
			array(
			    'name'          => 'City',
			    'label'         => __('City', 'advisors-assistant-forms'),
			    'field_type'    => array('text', 'hidden'),
			    //'default_value' => $this->get_first_field_by_type( 'text' ),
			),
			array(
			    'name'          => 'State',
			    'label'         => __('State', 'advisors-assistant-forms'),
			    'field_type'    => array('select', 'hidden'),
			    //'default_value' => $this->get_first_field_by_type( 'select' ),
			),
			array(
			    'name'          => 'Zip',
			    'label'         => __('Zip/Postal Code', 'advisors-assistant-forms'),
			    'field_type'    => array('zip', 'text', 'hidden'),
			    //'default_value' => $this->get_first_field_by_type( 'text' ),
			),
			array(
			    'name'          => 'Phone',
			    'label'         => __('Primary Phone', 'advisors-assistant-forms'),
			    'field_type'    => array('phone', 'text', 'hidden'),
			    //'default_value' => $this->get_first_field_by_type( 'phone' ),
			),
			array(
			    'name'          => 'Note',
			    'label'         => __('Note', 'advisors-assistant-forms'),
			    'field_type'    => array('textarea', 'text', 'hidden'),
			    //'default_value' => $this->get_first_field_by_type( 'text' ),
			),

		);

	}

	/**
	 * Set feed creation control.
	 *
	 * @access public
	 * @return bool
	 */
	//public function can_create_feed()
	//{
	//    return $this->initialize_api();
	//}

	/**
	 * Enable feed duplication.
	 *
	 * @access public
	 * @return bool
	 */
	public function can_duplicate_feed( $id )
	{
		return true;
	}

	/**
	 * Setup columns for feed list table.
	 *
	 * @access public
	 * @return array
	 */
	public function feed_list_columns()
	{
		return array(
			'feedName' => __('Name', 'advisors-assistant-forms'),
			'action'   => __('Action', 'advisors-assistant-forms'),
		);
	}

	/**
	 * Get value for action feed list column.
	 *
	 * @access public
	 * @param array $feed
	 * @return string $action
	 */
	public function get_column_value_action($feed)
	{
		return esc_html__('Create New Contact', 'advisors-assistant-forms');
	}

	/**
	 * Process feed.
	 *
	 * @access public
	 * @param array $feed
	 * @param array $entry
	 * @param array $form
	 * @return void
	 */
	public function process_feed($feed, $entry, $form)
	{
		$this->log_debug(__METHOD__ . '(): Processing feed.');

		/* If API instance is not initialized, exit. */
		if (!$this->initialize_aa_api())
		{
		    $error_msg = esc_html__('Feed was not processed because API was not initialized.', 'gravityformsicontact');
		    $this->add_feed_error($error_msg, $feed, $entry, $form);
		    $this->log_error(__METHOD__ . '(): ' . $error_msg);
		    $this->email_error($error_msg);
		    return;
		}


		// We just support adding names for now
		$this->create_contact($feed, $entry, $form);
	}

	/**
	 * Create contact.
	 *
	 * @access public
	 * @param array $feed
	 * @param array $entry
	 * @param array $form
	 * @return array $contact
	 */
	public function create_contact($feed, $entry, $form)
	{
		$this->log_debug(__METHOD__ . '(): Creating contact.');
		/* Setup mapped fields array. */
		$contact_standard_fields = $this->get_field_map_fields($feed, 'contactStandardFields');
		$settings = $this->get_plugin_settings();



		/* Setup base fields. */
		$FirstName    = $this->get_field_value($form, $entry, $contact_standard_fields['FirstName']);
		$LastName     = $this->get_field_value($form, $entry, $contact_standard_fields['LastName']);
		$MiddleName   = $this->get_field_value($form, $entry, $contact_standard_fields['MiddleName']);
		$Prefix   = $this->get_field_value($form, $entry, $contact_standard_fields['Prefix']);
		$Suffix   = $this->get_field_value($form, $entry, $contact_standard_fields['Suffix']);
		$EmailAddress = $this->get_field_value($form, $entry, $contact_standard_fields['EmailAddress']);
		$Business = $this->get_field_value($form, $entry, $contact_standard_fields['Business']);
		$AddressLine1 = $this->get_field_value($form, $entry, $contact_standard_fields['AddressLine1']);
		$AddressLine2 = $this->get_field_value($form, $entry, $contact_standard_fields['AddressLine2']);
		$City = $this->get_field_value($form, $entry, $contact_standard_fields['City']);
		$State = $this->get_field_value($form, $entry, $contact_standard_fields['State']);
		$Zip = $this->get_field_value($form, $entry, $contact_standard_fields['Zip']);
		$raw_phone = $this->get_field_value($form, $entry, $contact_standard_fields['Phone']);
		$phone_arr = explode(" ", $raw_phone);
		$AreaCode = trim($phone_arr[0], "()");
		$BasePhoneNumber = $phone_arr[1];
		$PrimaryNameType = $feed['meta']['nameType'];
		//$InitiateWorkflow = $settings['workflowID'];
		$InitiateWorkflow = $feed['meta']['workflowID'];
		$Note = $this->get_field_value($form, $entry, $contact_standard_fields['Note']);
		$NoteTopic = $feed['meta']['noteTopic'];

		/* If the name is empty, exit. */
		if (rgblank($LastName))
		{
			$error_msg = esc_html__('Contact could not be created as last name was not provided.', 'advisors-assistant-forms');
			$this->add_feed_error($error_msg, $feed, $entry, $form);
			$this->log_error( __METHOD__ . '(): ' . $error_msg);
			return null;
		}

		if (rgars($feed, 'meta/splitNameYes') == 1)
		{
			$parts = explode(" ", $LastName);
			switch (count($parts))
			{
				case 0:
				case 1:
					break;
				case 2:
					list($FirstName, $LastName) = $parts;
					break;
				default:
					$this->log_debug(__METHOD__ . '(): Starting name parse.');
					if (!class_exists('nameParser'))
					{
					    require_once 'includes/nameParser.php';
					}
					$np = new nameParser();
					$np->setFullName($LastName);
					$np->parse();
					if(!$np->getNotParseable())
					{
					    $LastName = $np->getLastName();
					    $FirstName = $np->getFirstName();
					    $MiddleName = $np->getMiddleName();
					    $Prefix = $np->getTitle();
					    $Suffix = $np->getSuffix();
					}
					break;
			}

		}


		/* Check for already existing contact, the hopefully more robust version :-P */
		/* This will attempt to match at least 3 data items per contact, a blank in either AA or the form won't count */
		/* But even 1 MISMATCH means it's not a matching contact */
		/* Err on the side of adding a contact, since dupes in AA can be combined */
		/* Also, if more than 1 matching name is found, add the new name, since we can't be sure in that case */

		$check_arr = array("lastname" => $LastName); // set up search criteria, start with last name
		$existing_matches = 1; // this is the minimum number of data matches that would happen with just a last name match
		if (!empty($FirstName)) // if there's also an entered first name, add it to the criteria and set $existing_matches to 2 (since there's also a first name match now)
		{
			$check_arr["firstname"] = $FirstName;
			$existing_matches = 2;
		}
		try
		{
			$raw_return = $this->api->get_contact(null, $check_arr); // get the matching contacts
		}
		catch (Exception $e)
		{
			// I have to add this to handle the new 404 thing with names
		}

		//$this->log_error( __METHOD__ . '(): ' . print_r($raw_return, true));
		if (!empty($raw_return["_embedded"])) // if it doesn't find ANY contact matches from the name then skip all this and just add the contact
		{
			$names_return = $raw_return["_embedded"]["names"]; // get names sub-array from API return
			$names_keys = array_keys($names_return); // this is so I can remove an array element while iterating through it
			// iterate through the returned contacts
			foreach ($names_keys as $key)
			{
				$this_matches = 0;
				$name = $names_return[$key];
				// first check the middle name, if they match then record it
				if (str_replace(".","",$name["MiddleName"]) == str_replace(".","",$MiddleName))
				{
					$this_matches++;
				}
				// now check for a mismatch, but only if one of them isn't blank
				// if there's a mismatch then the contact is not a valid match, so remove it from the array
				elseif (!(empty($MiddleName) || empty($name["MiddleName"])) &&
						str_replace(".","",$name["MiddleName"]) != str_replace(".","",$MiddleName))
				{
					unset($names_return[$key]);
					continue;
				}

				// now do the same thing with suffixes (suffices? lol)
				if (str_replace(".","",$name["Suffix"]) == str_replace(".","",$Suffix))
				{
					$this_matches++;
				} elseif (!(empty($Suffix) || empty($name["Suffix"])) &&
						  str_replace(".","",$name["Suffix"]) != str_replace(".","",$Suffix))
				{
					unset($names_return[$key]);
					continue;
				}

				// now we check emails; this will require a separate API call
				// if entered email is empty, leave it alone
				if (!empty($EmailAddress))
				{
					try {
						$email_return = $this->api->get_emails($name["ID"]);
					}
					catch (Exception $e) {}
					// only do this if something comes back
					if (!empty($email_return["_embedded"]))
					{
						// this is a little different than the names loop
						$email_mismatch = false; // flag for if it finds a mismatch
						foreach ($email_return["_embedded"]["emailaddresses"] as $email)
						{
							// if it finds a match, increment $this_matches, reset the flag, and break out of foreach
							if ($email["EmailAddress"] == $EmailAddress)
							{
								$this_matches++;
								$email_mismatch = false;
								break;
							}
							// if it finds a mismatch, just set the flag
							// if email on contact empty, just skip it
							elseif (!empty($email["EmailAddress"]) && $email["EmailAddress"] != $EmailAddress)
							{
								//unset($names_return[$key]);
								//continue 2;
								$email_mismatch = true;
							}
						}
						// after the foreach, if it found a mismatch, but WITHOUT finding a match, remove the contact and go to the next one
						if ($email_mismatch == true)
						{
							unset($names_return[$key]);
							continue;
						}
					}
				}

				// now check phone numbers
				// if either area code or phone number not entered, skip this
				if (!(empty($AreaCode) || empty($BasePhoneNumber)))
				{
					// this is basically just like the email address checker
					try {
						$phones_return = $this->api->get_phones($name["ID"]);
					}
					catch (Exception $e) {}
					//$this->log_error( __METHOD__ . '(): ' . print_r($phones_return, true));
				    if (!empty($phones_return["_embedded"]))
				    {
						$phones_mismatch = false;
				        foreach ($phones_return["_embedded"]["phonenumbers"] as $phone)
				        {
							// both area code and phone number have to match to be counted as a "match"
				            if ($phone["AreaCode"] == $AreaCode && $phone["BasePhoneNumber"] == $BasePhoneNumber)
				            {
				                $this_matches++;
								$phones_mismatch = false;
								break;
				            }
							// consider for mismatch only if the contact has both area code and phone number filled in (it should anyway but who knows)
							elseif (!(empty($phone["AreaCode"]) || empty($phone["BasePhoneNumber"])) &&
									 ($phone["AreaCode"] != $AreaCode || $phone["BasePhoneNumber"] != $BasePhoneNumber))
				            {
				                $phones_mismatch = true;
				            }
				        }
						if ($phones_mismatch == true)
						{
							unset($names_return[$key]);
							continue;
						}
				    }
				}

				// if any part of the entered address is empty, skip this part
				if (!(empty($AddressLine1) || empty($City) || empty($State) || empty($Zip)))
				{
					// again, this is like the emails and phones
					try {
						$address_return = $this->api->get_addresses($name["ID"]);
					}
					catch (Exception $e) {}
				    if (!empty($address_return["_embedded"]))
				    {
						$address_mismatch = false;
				        foreach ($address_return["_embedded"]["addresses"] as $address)
				        {
							// all the parts of the address have to match to be a match here
							// also we compare only the first part of the zip, if it has a +4
				            if ($address["StreetLine1"] == $AddressLine1 && $address["StreetLine2"] == $AddressLine2 &&
								$address["City"] == $City && $address["State"] == $State && substr($address["Zip"],0,5) == substr($Zip,0,5))
				            {
				                $this_matches++;
								$address_mismatch = false;
								break;
				            }
							// like phones, consider for match only if all the "required" parts are in the contact
							elseif (!(empty($address["StreetLine1"]) || empty($address["City"]) || empty($address["State"]) || empty($address["Zip"])) &&
									 ($address["StreetLine1"] != $AddressLine1 || $address["City"] != $City || $address["State"] != $State ||
									  substr($address["Zip"],0,5) != substr($Zip,0,5)))
				            {
				                $address_mismatch = true;
				            }
				        }
						if ($address_mismatch == true)
						{
							unset($names_return[$key]);
							continue;
						}
				    }
				}

				// now add the "base" number of data matches (true for any matched contact) and the matches for this contact
				// if it's less than 3, it's not enough, so remove it
				if ($existing_matches + $this_matches < 3)
				{
					unset($names_return[$key]);
				}
			}

			// after iterating, any contact left in the array is a valid-enough match
			// if there is EXACTLY ONE of these, don't send it through; otherwise, send it through
			// so instead of creating a new contact, attach an error message to the WP form entry, and return without creating
			//if (count($names_return) > 0)
			if (count($names_return) == 1)
			{
				if (!empty($NoteTopic) && !empty($Note))
				{
					$match_name = array_pop($names_return);
					$this->create_note($match_name["ID"], $NoteTopic, $Note);
				}
			    $error_msg = esc_html__('The entered contact already exists as a name in Advisors Assistant.', 'advisors-assistant-forms');
			    $this->add_feed_error($error_msg, $feed, $entry, $form);
			    $this->log_error( __METHOD__ . '(): ' . $error_msg);
			    return null;
			}

		}

		/* And that's it :) */


		/* If the email address is empty, exit. */
		/* Leave as an example */
		//if (GFCommon::is_invalid_or_empty_email($default_email))
		//{
		//    $this->add_feed_error(esc_html__('Contact could not be created as email address was not provided.', 'advisors-assistant-forms'), $feed, $entry, $form);
		//    return null;
		//}

		/* Build contact objects. */
		$ContactName = array(
			"FirstName" => $FirstName,
			"LastName" => $LastName,
			"MiddleName" => $MiddleName,
			"Prefix" => $Prefix,
			"Suffix" => $Suffix,
			//"PrimaryNameType" => "Prospect (Website)",
			"PrimaryNameType" => $PrimaryNameType,
			"Gender" => "U",
			"InitiateWorkflow" => $InitiateWorkflow
		);

		$ContactEmailAddress = null;
		if (!empty($EmailAddress))
		{
			$ContactEmailAddress = array(
				"Address" => $EmailAddress,
				"Comment" => "Added from Website Submission"
			);
		}


		$ContactAddress = null;
		if (!empty($Business) || !empty($AddressLine1) || !empty($_AddressLine2) || !empty($City) || !empty($State) || !empty($Zip))
		{
			$ContactAddress = array(
				"Addressee" => "{$FirstName} {$LastName}",
				"Greeting" => $FirstName,
				"BusinessName" => $Business,
				"StreetLine1" => $AddressLine1,
				"StreetLine2" => $AddressLine2,
				"City" => $City,
				"State" => $State,
				"Zip" => $Zip,
				"Comment" => "Added from Website Submission",
				"Location" => "Home"
			);
		}

		$ContactPhone = null;
		if (!empty($AreaCode) || !empty($BasePhoneNumber))
		{
			$ContactPhone = array(
				"AreaCode" => $AreaCode,
				"BasePhoneNumber" => $BasePhoneNumber,
				"Comment" => "Added from Website Submission",
				"Location" => "Home"
			);
		}

		$ContactNote = null;
		if (!empty($NoteTopic) && !empty($Note))
		{
			$ContactNote = array(
				"Topic" => $NoteTopic,
				"NoteText" => $Note,
				"TextFormat" => "Plain Text"
			);
		}


		$contact = array("Name" => $ContactName, "EmailAddress" => $ContactEmailAddress, "Address" => $ContactAddress, "PhoneNumber" => $ContactPhone,
						 "TopicNote" => $ContactNote);
		$this->log_debug( __METHOD__ . '(): Creating contact: ' . print_r( $contact, true ) );

		try
		{
			/* Create contact. */
			$contact = $this->api->create_contact($contact);

			/* Log that contact was created. */
			$this->log_debug( __METHOD__ . '(): Contact #' . $contact['ID'] . ' created.' );

		} catch (Exception $e)
		{
			$error_msg = sprintf(esc_html__('Contact could not be created. %s', 'advisors-assistant-forms'), $e->getMessage());
			$this->add_feed_error( $error_msg, $feed, $entry, $form );
			$this->log_error( __METHOD__ . '(): ' . $error_msg);
			return null;
		}

		return $contact;
	}

	public function create_note($id, $note_topic, $note)
	{
		$ContactNote = array(
			"NameID" => $id,
			"Topic" => $note_topic,
			"NoteText" => $note,
			"TextFormat" => "Plain Text"
		);
		try
		{
			$this->api->create_note($ContactNote);
		} catch (Exception $e)
		{
			$error_msg = sprintf(esc_html__('Note could not be created. %s', 'advisors-assistant-forms'), $e->getMessage());
			$this->add_feed_error( $error_msg, $feed, $entry, $form );
			$this->log_error( __METHOD__ . '(): ' . $error_msg);
			return null;
		}

	}

	/**
	 * Initializes AdvisorsAssistant CRM API if credentials are valid.
	 *
	 * @access public
	 * @return bool
	 */
	public function initialize_aa_api($force_check = false)
	{
		if (!is_null($this->api) && $force_check == false)
		{
			return true;
		}

		/* Load the AdvisorsAssistant CRM API library. */
		if (!class_exists('AdvisorsAssistantCRM_API'))
		{
			require_once 'includes/class-advisorsassistantcrm-api.php';
		}

		$redo = false;

		do {
			/* Get the plugin settings */
			$settings = $this->get_plugin_settings();

			/* If API URL is empty, return null. */
			if (rgblank($settings['apiURL'])) // || rgblank($settings['accessKey']))
				return null;

			$this->log_debug(__METHOD__ . "(): Validating API info for {$settings['apiURL']}.");

			//$test_url = "/v1/names";
			//$this->log_error(__METHOD__ . '(URL): ' . $test_url);
			//$this->log_error(__METHOD__ . '(Sign): ' . base64_encode(hash_hmac('sha256', $test_url, $settings['aesKey'], true)));

			$aa_api = new AdvisorsAssistantCRM_API($settings);

			try
			{
				/* Run API test. */
				//$aa_api->get_contact(null, array("firstname" => "xyz123"));
				$aa_api->init_request();

				/* Log that test passed. */
				$this->log_debug(__METHOD__ . '(): API credentials are valid.');

				/* Assign AdvisorsAssistant CRM object to the class. */
				$this->api = $aa_api;

				if ($redo == true && $force_check == true)
				{
					//return("Your Access and Refresh Keys have been automatically refreshed.");
					return $this->refresh_message($settings);
				} else
				{
					return true;
				}
			}
			catch (Exception $e)
			{
				if ($redo == false && $e->getCode() == 401)
				{
					try
					{
						$new_tokens = $aa_api->refresh_tokens();
					}
					catch (Exception $e2)
					{
						$error_msg = 'Token refresh failed: ' . $e2->getMessage();
						$this->log_error(__METHOD__ . '(): ' . $error_msg);
						if ($force_check)
						{
							return $e2;
						} else
						{
							return false;
						}
					}
					$this->log_error(__METHOD__ . '(): Refreshing token: ' . print_r($new_tokens, true));
					$settings["accessKey"] = $new_tokens["access_token"];
					$settings["refreshKey"] = $new_tokens["refresh_token"];
					$this->update_plugin_settings($settings);
					$redo = true;
				} else
				{
					/* Log that test failed. */
					$error_msg = 'API credentials are invalid: ' . $e->getMessage();
					$this->log_error(__METHOD__ . '(): ' . $error_msg);
					if ($force_check)
					{
						return $e;
					} else
					{
						return false;
					}
				}
			}
		} while ($redo == true);
	}



	public function email_error($msg)
	{
		$settings = $this->get_plugin_settings();

		if (empty($settings['adminEmail']))
		{
			return false;
		}

		$to_addrs = explode(",", $settings['adminEmail']);

		foreach ($to_addrs as $addr)
		{
			mail(trim($addr), "Wordpress Plugin API Error", $msg);
		}
		return true;
	}

	public function refresh_message($settings)
	{
		$msg = "<p style='font-weight: bold;'>Your Access and Refresh Keys have been automatically refreshed.</p>";
		if (is_array($settings))
		{
			$msg .= <<<EOD
					<script type="text/javascript">
						function update_keys() {
							document.getElementById("accessKey").value = "{$settings['accessKey']}";
							document.getElementById("refreshKey").value = "{$settings['refreshKey']}";
						}

						if (document.addEventListener) {
							window.addEventListener( "load", update_keys, false );
						} else if (document.attachEvent) {
							window.attachEvent("onload", update_keys);
						}
					</script>
EOD;
		}

		return $msg;
	}

	/**
	 * Set custom dependency for conditional logic.
	 * This is not needed right away but we're keeping it around for now
	 *
	 * @access public
	 * @return bool
	 */
	//public function show_conditional_logic_field()
	//{
	//    /* Get current feed. */
	//    $feed = $this->get_current_feed();

	//    /* Get posted settings. */
	//    $posted_settings = $this->get_posted_settings();

	//    /* Show if an action is chosen */
	//    if (rgar($posted_settings, 'createContact') == '1' || rgars($feed, 'meta/createContact') == '1' || rgar($posted_settings, 'createTask') == '1' || rgars($feed, 'meta/createTask') == '1')
	//    {
	//        return true;
	//    }

	//    return false;
	//}

	/**
	 * Validate Task Days Until Due feed settings field.
	 * Keeping this around too as an example
	 *
	 * @access public
	 * @param array $field
	 * @param string $field_setting
	 * @return void
	 */
	//public function validate_task_days_until_due( $field, $field_setting )
	//{
	//    if (!is_numeric( $field_setting))
	//    {
	//        $this->set_field_error($field, esc_html__('This field must be numeric.', 'gravityforms'));
	//    }
	//}

}