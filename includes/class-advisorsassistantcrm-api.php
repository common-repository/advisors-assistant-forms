<?php

class AdvisorsAssistantCRM_API
{
	function __construct($settings)
	{
		$this->api_url = $settings['apiURL'];
		$this->auth_url = $settings['authURL'];
		$this->client_id = $settings['clientID'];
		$this->client_secret = $settings['clientSecret'];
		$this->access_key = $settings['accessKey'];
		$this->refresh_key = $settings['refreshKey'];
		$this->aes_key = $settings['aesKey'];
	}

	/**
	 * Make API request.
	 *
	 * @access public
	 * @param string $action
	 * @param string $method (default: 'GET')
	 * @param int $id (default: null)
	 * @param array $options (default: array())
	 * @param int $expected_code (default: 200)
	 * @return array
	 */
	function make_request($action, $method = 'GET', $id = null, $options = array(), $expected_code = 200, $no_rest = false)
	{
		/* Build request URL. */
		/* Super simple option for init request */
		if ($no_rest == true)
		{
			$parsed_url = parse_url($this->api_url);
			$request_url = $parsed_url["scheme"] . '://' . $parsed_url["host"];
		} else /* for every other time */
		{
			$full_id = ($id != null) ? "/". $id : null;
			// for initiating workflow
			if ($action == "Names" && $method == "POST" && !empty($options['InitiateWorkflow']))
			{
				$request_options = "?InitiateWorkflow=" . $options['InitiateWorkflow'];
				unset($options['InitiateWorkflow']);
			} else
			{
				$request_options = ($method == 'GET' && $no_rest != true && $options != null) ? '?' . http_build_query($options) : null;
			}
			$request_url = "{$this->api_url}{$action}{$full_id}{$request_options}";
		}


		/* Setup request arguments. */
		$args = array(
			'headers'   => array(
				'Accept'        => 'application/hal+json',
				'Authorization' => 'Bearer ' . $this->access_key,
				'Content-Type'  => 'application/json'
			),
			'method'    => $method,
			'timeout'	=> 60,
			'sslverify' => true // SSL is ALWAYS required for our API
		);

		/* Add request options to body of POST and PUT requests. */
		if ($method == 'POST' || $method == 'PUT')
		{
			$args['body'] = json_encode($options);
		}

		/* Sign URL and body */
		if (!empty($this->aes_key))
		{
			$paq_arr = parse_url($request_url);
			$paq_url = $paq_arr['path'] . ((!empty($paq_arr['query'])) ? '?' . $paq_arr['query'] : "");
			$args['headers']['X-Signature'] = base64_encode(hash_hmac('sha256', strtolower($paq_url), $this->aes_key, true));
			if (!empty($args['body']))
			{
				$args['headers']['X-ContentSignature'] = base64_encode(hash_hmac('sha256', $args['body'], $this->aes_key, true));
			}
		}

		/* Execute request. */
		$result = wp_remote_request($request_url, $args);

		/* If WP_Error, throw exception */
		if (is_wp_error($result))
		{
			throw new Exception("{$action} {$method}: WordPress remote request error");
		}

		/* If response code does not match expected code, throw exception. */
		if ($result['response']['code'] !== $expected_code)
		{
			$prefix = "{$action} {$method} {$result['response']['code']}";
			if (!empty($result['response']['message']))
			{
				throw new Exception("{$prefix}: {$result['response']['message']}", $result['response']['code']);
			} else
			{
				throw new Exception($prefix . ': There was an error.');
			}
		}

		return json_decode($result['body'], true);
	}

	function refresh_tokens()
	{
		$request_url = "{$this->auth_url}connect/token";
		$options = array("grant_type" => "refresh_token", "refresh_token" => $this->refresh_key);

		/* Setup request arguments. */
		$args = array(
			'headers'   => array(
				'Accept'        => 'application/json',
				'Authorization' => 'Basic ' . base64_encode("{$this->client_id}:{$this->client_secret}"),
			),
			'body'		=> $options,
			'timeout'	=> 60,
			'sslverify' => true // SSL is ALWAYS required for our API
		);

		/* Execute request. */
		$result = wp_remote_post($request_url, $args);

		/* If WP_Error, throw exception */
		if (is_wp_error($result))
		{
			throw new Exception("Wordpress auth refresh error");
		}

		/* If response code does not match expected code, throw exception. */
		if (!in_array($result['response']['code'], array(200, 201)))
		{
			$prefix = "Refresh Token {$result['response']['code']}";
			if (!empty($result['response']['message']))
			{
				throw new Exception("{$prefix}: {$result['response']['message']}", $result['response']['code']);
			} else
			{
				throw new Exception($prefix . ': There was an error.');
			}
		}

		$result_obj = json_decode($result['body'], true);
		return $result_obj;
	}

	function init_request()
	{
		return $this->make_request("Ping", "GET", null, null, 200);
	}

	/**
	 * Create a name.
	 *
	 * @access public
	 * @param array $contact
	 * @return array $contact
	 */
	function create_contact($contact)
	{
		$result = $this->make_request('Names', 'POST', null, $contact['Name'], 201);
		if (empty($result['ID']))
		{
			throw new Exception('A new GUID for the created record was not returned.');
		}
		$guid = $result['ID'];

		if ($contact['EmailAddress'] != null)
		{
			$contact["EmailAddress"]["NameID"] = $guid;
			$result = $this->make_request('EmailAddresses', 'POST', null, $contact['EmailAddress'], 201);
		}

		if ($contact['Address'] != null)
		{
			$contact["Address"]["NameID"] = $guid;
			$result = $this->make_request('Addresses', 'POST', null, $contact['Address'], 201);
		}

		if ($contact['PhoneNumber'] != null)
		{
			$contact["PhoneNumber"]["NameID"] = $guid;
			$result = $this->make_request('PhoneNumbers', 'POST', null, $contact['PhoneNumber'], 201);
		}

		if ($contact['TopicNote'] != null)
		{
			$contact["TopicNote"]["NameID"] = $guid;
			$result = $this->make_request('TopicNotes', 'POST', null, $contact['TopicNote'], 201);
		}

		return $result;
	}

	function create_note($note)
	{
		$result = $this->make_request('TopicNotes', 'POST', null, $note, 201);
	}

	/**
	 * Get all names.
	 *
	 * @access public
	 * @return void
	 */
	function get_contacts()
	{
		return $this->make_request('Names');
	}

	/**
	 * Get specific contact.
	 *
	 * @access public
	 * @param int $id
	 * @param array $options
	 * @return void
	 */
	function get_contact($id = null, $options = null)
	{
		return $this->make_request("Names", "GET", $id, $options);
	}

	/**
	 * Get emails for contact.
	 *
	 * @access public
	 * @param int $id
	 * @param array $options
	 * @return void
	 */
	function get_emails($id)
	{
		return $this->make_request("EmailAddresses", "GET", null, array("NameID" => $id));
	}

	function get_phones($id)
	{
		return $this->make_request("PhoneNumbers", "GET", null, array("NameID" => $id));
	}

	function get_addresses($id)
	{
		return $this->make_request("Addresses", "GET", null, array("NameID" => $id));
	}

	/* This is all stuff we may do later, but not right now */

	///**
	// * Update a contact.
	// *
	// * @access public
	// * @param array $contact
	// * @return array $contact
	// */
	//function update_contact( $contact ) {

	//    return $this->make_request( 'contacts', json_encode( $contact ), 'PUT' );

	//}

	///**
	// * Create a note.
	// *
	// * @access public
	// * @param array $note
	// * @return array $note
	// */
	//function create_note( $note ) {

	//    return $this->make_request( 'notes', json_encode( $note ), 'POST' );

	//}

	///**
	// * Create a task.
	// *
	// * @access public
	// * @param array $task
	// * @return array $task
	// */
	//function create_task( $task ) {

	//    return $this->make_request( 'tasks', json_encode( $task ), 'POST' );

	//}

	///**
	// * Search contacts.
	// *
	// * @access public
	// * @param string $query
	// * @return array $contacts
	// */
	//function search_contacts( $query ) {

	//    return $this->make_request( 'search', array( 'q' => $query, 'type' => 'PERSON', 'page_size' => 999 ) );

	//}
}
