<?php

/**
 * CardDAV-PHP
 *
 * simple CardDAV query
 * --------------------
 * $carddav = new carddav_backend('https://davical.example.com/user/contacts/');
 * $carddav->set_auth('username', 'password');
 * echo $carddav->get();
 *
 *
 * simple vCard query
 * ------------------
 * $carddav = new carddav_backend('https://davical.example.com/user/contacts/');
 * $carddav->set_auth('username', 'password');
 * echo $carddav->get_vcard('0126FFB4-2EB74D0A-302EA17F');
 *
 *
 * xml vCard query
 * ------------------
 * $carddav = new carddav_backend('https://davical.example.com/user/contacts/');
 * $carddav->set_auth('username', 'password');
 * echo $carddav->get_xml_vcard('0126FFB4-2EB74D0A-302EA17F');
 *
 *
 * check CardDAV-Server connection
 * -------------------------------
 * $carddav = new carddav_backend('https://davical.example.com/user/contacts/');
 * $carddav->set_auth('username', 'password');
 * var_dump($carddav->check_connection());
 *
 *
 * CardDAV delete query
 * --------------------
 * $carddav = new carddav_backend('https://davical.example.com/user/contacts/');
 * $carddav->set_auth('username', 'password');
 * $carddav->delete('0126FFB4-2EB74D0A-302EA17F');
 *
 *
 * CardDAV add query
 * --------------------
 * $vcard = 'BEGIN:VCARD
 * VERSION:3.0
 * UID:1f5ea45f-b28a-4b96-25as-ed4f10edf57b
 * FN:Christian Putzke
 * N:Christian;Putzke;;;
 * EMAIL;TYPE=OTHER:christian.putzke@graviox.de
 * END:VCARD';
 *
 * $carddav = new carddav_backend('https://davical.example.com/user/contacts/');
 * $carddav->set_auth('username', 'password');
 * $carddav->add($vcard);
 *
 *
 * CardDAV update query
 * --------------------
 * $vcard = 'BEGIN:VCARD
 * VERSION:3.0
 * UID:1f5ea45f-b28a-4b96-25as-ed4f10edf57b
 * FN:Christian Putzke
 * N:Christian;Putzke;;;
 * EMAIL;TYPE=OTHER:christian.putzke@graviox.de
 * END:VCARD';
 *
 * $carddav = new carddav_backend('https://davical.example.com/user/contacts/');
 * $carddav->set_auth('username', 'password');
 * $carddav->update($vcard, '0126FFB4-2EB74D0A-302EA17F');
 *
 *
 * URL-Schema list
 * ---------------
 * DAViCal: https://example.com/{resource|principal}/{collection}/
 * Apple Addressbook Server: https://example.com/addressbooks/users/{resource|principal}/{collection}/
 * memotoo: https://sync.memotoo.com/cardDAV/
 * SabreDAV: https://example.com/addressbooks/{resource|principal}/{collection}/
 * ownCloud: https://example.com/apps/contacts/carddav.php/addressbooks/{username}/{resource|principal}/
 *
 *
 * @author Christian Putzke <christian.putzke@graviox.de>
 * @copyright Graviox Studios
 * @link http://www.graviox.de
 * @since 20.07.2011
 * @version 0.5
 * @license http://www.gnu.org/licenses/agpl.html GNU AGPL v3 or later
 *
 */

class carddav_backend
{
	/**
	 * CardDAV-PHP Version
	 *
	 * @constant string
	 */
	const VERSION = '0.5';

	/**
	 * user agent displayed in http requests
	 *
	 * @constant string
	 */
	const USERAGENT = 'CardDAV-PHP/';

	/**
	 * CardDAV-Server url
	 *
	 * @var string
	 */
	private $url = null;

	/**
	 * CardDAV-Server url_parts
	 *
	 * @var string
	 */
	private $url_parts = null;

	/**
	 * authentication information
	 *
	 * @var string
	 */
	private $auth = null;

	/**
	 * last used vCard id
	 *
	 * @var string
	 */
	private $vcard_id = null;

	/**
	* authentication: username
	*
	* @var string
	*/
	private $username = null;

	/**
	* authentication: password
	*
	* @var string
	*/
	private $password = null;

	/**
	 * characters used for vCard id generation
	 *
	 * @var array
	 */
	private $vcard_id_chars = array(0, 1, 2, 3, 4, 5, 6, 7, 8, 9, 'A', 'B', 'C', 'D', 'E', 'F');

	/**
	 * CardDAV-Server connection (curl handle)
	 *
	 * @var resource
	 */
	private $curl;

	/**
	 * constructor
	 * set the CardDAV-Server url
	 *
	 * @param string $url CardDAV-Server url
	 * @return void
	 */
	public function __construct($url = null)
	{
		if ($url !== null)
		{
			$this->set_url($url);
		}
	}

	/**
	* set the CardDAV-Server url
	*
	* @param string $url CardDAV-Server url
	* @return void
	*/
	public function set_url($url)
	{
		$this->url = $url;

		if (substr($this->url, -1, 1) !== '/')
		{
			$this->url = $this->url . '/';
		}

		$this->url_parts = parse_url($this->url);
	}

	/**
	 * set authentication information
	 *
	 * @param string $username CardDAV-Server username
	 * @param string $password CardDAV-Server password
	 * @return void
	 */
	public function set_auth($username, $password)
	{
		$this->username = $username;
		$this->password = $password;
		$this->auth = $username . ':' . $password;
	}

	/**
	 * get propfind xml-response from the CardDAV-Server
	 *
	 * @param boolean $include_vcards include vCards in the response (simplified only)
	 * @param boolean $raw get response raw or simplified
	 * @return string raw or simplified xml response
	 */
	public function get($include_vcards = true, $raw = false)
	{
		$response = $this->query($this->url, 'PROPFIND');

		if ($response === false || $raw === true)
		{
			return $response;
		}
		else
		{
			return $this->simplify($response, $include_vcards);
		}
	}

	/**
	* get a clean vCard from the CardDAV-Server
	*
	* @param string $id vCard id on the CardDAV-Server
	* @return string vCard (text/vcard)
	*/
	public function get_vcard($vcard_id)
	{
		$vcard_id = str_replace('.vcf', null, $vcard_id);
		return $this->query($this->url . $vcard_id . '.vcf', 'GET');
	}

	/**
	 * get a vCard + XML from the CardDAV-Server
	 *
	 * @param string $id vCard id on the CardDAV-Server
	 * @return string vCard (text/xml)
	 */
	public function get_xml_vcard($vcard_id)
	{
		$vcard_id = str_replace('.vcf', null, $vcard_id);

		$xml = new XMLWriter();
		$xml->openMemory();
		$xml->setIndent(4);
		$xml->startDocument('1.0', 'utf-8');
			$xml->startElement('C:addressbook-multiget');
				$xml->writeAttribute('xmlns:D', 'DAV:');
				$xml->writeAttribute('xmlns:C', 'urn:ietf:params:xml:ns:carddav');
				$xml->startElement('D:prop');
					$xml->writeElement('D:getetag');
					$xml->writeElement('D:getlastmodified');
				$xml->endElement();
				$xml->writeElement('D:href', $this->url_parts['path'] . $vcard_id . '.vcf');
			$xml->endElement();
		$xml->endDocument();

		$response = $this->query($this->url, 'REPORT', $xml->outputMemory(), 'text/xml');

		if ($response === false)
		{
			return $response;
		}
		else
		{
			return $this->simplify($response, true);
		}
	}

	/**
	 * get the last used vCard id
	 *
	 * @return string $vcard_id last vCard id
	 */
	public function get_last_vcard_id()
	{
		return $this->vcard_id;
	}

	/**
	* checks if the CardDAV-Server is reachable
	*
	* @return boolean
	*/
	public function check_connection()
	{
		return $this->query($this->url, 'OPTIONS', null, null, true);
	}

	/**
	 * cleans the vCard
	 *
	 * @param string $vcard vCard
	 * @return string $vcard vCard
	 */
	private function clean_vcard($vcard)
	{
		$vcard = str_replace("\t", null, $vcard);

		return $vcard;
	}

	/**
	 * deletes an entry from the CardDAV-Server
	 *
	 * @param string $id vCard id on the CardDAV-Server
	 * @return string CardDAV xml-response
	 */
	public function delete($vcard_id)
	{
		$this->vcard_id = $vcard_id;
		return $this->query($this->url . $vcard_id . '.vcf', 'DELETE', null, null, true);
	}

	/**
	 * adds an entry to the CardDAV-Server
	 *
	 * @param string $vcard vCard
	 * @return string CardDAV xml-response
	 */
	public function add($vcard)
	{
		$vcard_id = $this->generate_vcard_id();
		$this->vcard_id = $vcard_id;
		$vcard = $this->clean_vcard($vcard);

		return $this->query($this->url . $vcard_id . '.vcf', 'PUT', $vcard, 'text/vcard', true);
	}

	/**
	 * updates an entry to the CardDAV-Server
	 *
	 * @param string $vcard vCard
	 * @param string $id vCard id on the CardDAV-Server
	 * @return string CardDAV xml-response
	 */
	public function update($vcard, $vcard_id)
	{
		$vcard_id = str_replace('.vcf', null, $vcard_id);
		$this->vcard_id = $vcard_id;
		$vcard = $this->clean_vcard($vcard);

		return $this->query($this->url . $vcard_id . '.vcf', 'PUT', $vcard, 'text/vcard', true);
	}

	/**
	 * simplify CardDAV xml-response
	 *
	 * @param string $response CardDAV xml-response
	 * @return string simplified CardDAV xml-response
	 */
	private function simplify($response, $include_vcards = true)
	{
		$response = $this->clean_response($response);
		$xml = new SimpleXMLElement($response);

		$simplified_xml = new XMLWriter();
		$simplified_xml->openMemory();
		$simplified_xml->setIndent(4);

		$simplified_xml->startDocument('1.0', 'utf-8');
			$simplified_xml->startElement('response');

				foreach ($xml->response as $response)
				{
					if (preg_match('/vcard/', $response->propstat->prop->getcontenttype) || preg_match('/vcf/', $response->href))
					{
						$id = basename($response->href);
						$id = str_replace('.vcf', null, $id);

						if (!empty($id))
						{
							$simplified_xml->startElement('element');
								$simplified_xml->writeElement('id', $id);
								$simplified_xml->writeElement('etag', str_replace('"', null, $response->propstat->prop->getetag));
								$simplified_xml->writeElement('last_modified', $response->propstat->prop->getlastmodified);

								if ($include_vcards === true)
								{
									$simplified_xml->writeElement('vcard', $this->get_vcard($id));
								}
							$simplified_xml->endElement();
						}
					}
					else if (preg_match('/unix-directory/', $response->propstat->prop->getcontenttype))
					{
						if (isset($response->propstat->prop->href))
						{
							$href = $response->propstat->prop->href;
						}
						else if (isset($response->href))
						{
							$href = $response->href;
						}
						else
						{
							$href = null;
						}


						$url = str_replace($this->url_parts['path'], null, $this->url) . $href;
						$simplified_xml->startElement('addressbook_element');
							$simplified_xml->writeElement('display_name', $response->propstat->prop->displayname);
							$simplified_xml->writeElement('url', $url);
							$simplified_xml->writeElement('last_modified', $response->propstat->prop->getlastmodified);
						$simplified_xml->endElement();
					}
				}

			$simplified_xml->endElement();
		$simplified_xml->endDocument();

		return $simplified_xml->outputMemory();
	}

	/**
	 * cleans CardDAV xml-response
	 *
	 * @param string $response CardDAV xml-response
	 * @return string $response cleaned CardDAV xml-response
	 */
	private function clean_response($response)
	{
		$response = utf8_encode($response);
		$response = str_replace('D:', null, $response);
		$response = str_replace('d:', null, $response);
		$response = str_replace('C:', null, $response);
		$response = str_replace('c:', null, $response);

		return $response;
	}

	/**
	 * curl initialization
	 *
	 * @return void
	 */
	public function curl_init()
	{
		if (empty($this->curl))
		{
			$this->curl = curl_init();
			curl_setopt($this->curl, CURLOPT_HEADER, false);
			curl_setopt($this->curl, CURLOPT_SSL_VERIFYHOST, false);
			curl_setopt($this->curl, CURLOPT_SSL_VERIFYPEER, false);
			curl_setopt($this->curl, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($this->curl, CURLOPT_USERAGENT, self::USERAGENT.self::VERSION);

			if ($this->auth !== null)
			{
				curl_setopt($this->curl, CURLOPT_HTTPAUTH, CURLAUTH_ANY);
				curl_setopt($this->curl, CURLOPT_USERPWD, $this->auth);
			}
		}
	}

	/**
	 * quries the CardDAV-Server via curl and returns the response
	 *
	 * @param string $url CardDAV-Server URL
	 * @param string $method HTTP-Method like (OPTIONS, GET, HEAD, POST, PUT, DELETE, TRACE, COPY, MOVE)
	 * @param string $content content for CardDAV-Queries
	 * @param string $content_type set content-type
	 * @param boolean $return_boolean returns just a boolean
	 * @return string CardDAV xml-response
	 */
	private function query($url, $method, $content = null, $content_type = null, $return_boolean = false)
	{
		$this->curl_init();

		curl_setopt($this->curl, CURLOPT_URL, $url);
		curl_setopt($this->curl, CURLOPT_CUSTOMREQUEST, $method);

		if ($content !== null)
		{
			curl_setopt($this->curl, CURLOPT_POST, true);
			curl_setopt($this->curl, CURLOPT_POSTFIELDS, $content);
		}
		else
		{
			curl_setopt($this->curl, CURLOPT_POST, false);
			curl_setopt($this->curl, CURLOPT_POSTFIELDS, null);
		}

		if ($content_type !== null)
		{
			curl_setopt($this->curl, CURLOPT_HTTPHEADER, array('Content-type: '.$content_type));
		}
		else
		{
			curl_setopt($this->curl, CURLOPT_HTTPHEADER, array());
		}

		$response = curl_exec($this->curl);
		$http_code = curl_getinfo($this->curl, CURLINFO_HTTP_CODE);

		if (in_array($http_code, array(200, 207)))
		{
			return ($return_boolean === true ? true : $response);
		}
		else if ($return_boolean === true && in_array($http_code, array(201, 204)))
		{
			return true;
		}
		else
		{
			return false;
		}
	}

	/**
	 * returns a valid and unused vCard id
	 *
	 * @return string valid vCard id
	 */
	private function generate_vcard_id()
	{
		$id = null;

		for ($number = 0; $number <= 25; $number ++)
		{
			if ($number == 8 || $number == 17)
			{
				$id .= '-';
			}
			else
			{
				$id .= $this->vcard_id_chars[mt_rand(0, (count($this->vcard_id_chars) - 1))];
			}
		}

		$carddav = new carddav_backend($this->url);
		$carddav->set_auth($this->username, $this->password);

		if ($carddav->query($this->url . $id . '.vcf', 'GET', null, null, true))
		{
			return $this->generate_vcard_id();
		}
		else
		{
			return $id;
		}
	}

	/**
	 * destructor
	 * close curl connection if it's open
	 *
	 * @return void
	 */
	public function __destruct()
	{
		if (!empty($this->curl))
		{
			curl_close($this->curl);
		}
	}
}
