<?php

/**
 * cardDAV-PHP
 *
 * simple carddav query
 * --------------------
 * $carddav = new carddav('https://davical.example.com/user/contacts/');
 * $carddav->set_auth('username', 'password');
 * $carddav->set_vcard_properties(array('EMAIL'));
 * echo $carddav->get();
 *
 *
 * carddav query with filters
 * --------------------
 * $carddav = new carddav('https://davical.example.com/user/contacts/');
 * $carddav->set_auth('username', 'password');
 * $carddav->set_filter_type('OR');
 * $carddav->set_filter('NICKNAME', 'equals', 'EdMolf');
 * $carddav->set_filter('EMAIL', 'ends-with', 'example.com');
 * $carddav->set_vcard_properties(array('EMAIL'));
 * echo $carddav->get();
 * 
 * 
 * carddav delete query
 * --------------------
 * $carddav = new carddav('https://davical.example.com/user/contacts/');
 * $carddav->set_auth('username', 'password');
 * $carddav->delete('0126FFB4-2EB74D0A-302EA17F');
 * 
 * 
 *  * carddav add query
 * --------------------
 * $vcard = 'BEGIN:VCARD
 * VERSION:3.0
 * FN:Christian Putzke
 * N:Christian;Putzke;;;
 * EMAIL;TYPE=OTHER:cputzke@graviox.de
 * END:VCARD';
 * 
 * $carddav = new carddav('https://davical.example.com/user/contacts/');
 * $carddav->set_auth('username', 'password');
 * $carddav->add($vcard);
 * 
 * 
 *  carddav update query
 * --------------------
 * $vcard = 'BEGIN:VCARD
 * VERSION:3.0
 * FN:Christian Putzke
 * N:Christian;Putzke;;;
 * EMAIL;TYPE=OTHER:cputzke@graviox.de
 * END:VCARD';
 * 
 * $carddav = new carddav('https://davical.example.com/user/contacts/');
 * $carddav->set_auth('username', 'password');
 * $carddav->update($vcard, '0126FFB4-2EB74D0A-302EA17F');
 * 
 * 
 * 
 * @author Christian Putzke <cputzke@graviox.de>
 * @copyright Graviox Studios
 * @since 20.07.2011
 * @version 0.22
 * @license http://gnu.org/copyleft/gpl.html GNU GPL v2 or later
 * 
 */

class carddav
{
	protected $url;
	protected $auth = null;
	protected $filter_type = 'anyof';
	protected $id_chars = array(0, 1, 2, 3, 4, 5, 6, 7, 8, 9, 'A', 'B', 'C', 'D', 'E', 'F');
	protected $match_types = array('equals', 'contains', 'stats-with', 'ends-with');
	protected $context = array();
	protected $vcard_properties = array();
	protected $filter = array();
	
	
	/**
	 * set the cardDAV server url
	 * 
	 * @param string $url cardDAV server url
	 * @access public
	 */
	public function __construct($url)
	{
		$this->url = $url;
	}
	
	
	/**
	 * set authentification information and base64 encode them
	 * 
	 * @param string $username cardDAV username
	 * @param string $password cardDAV password
	 * @access public
	 */
	public function set_auth($username, $password)
	{
		$this->auth = base64_encode($username.':'.$password);
	}
	
	
	/**
	 * set vcard properties that will be returned by $this->get()
	 * 
	 * common vcard properties
	 * - FN (formatted name without any semicolon)
	 * - N (name semicolon separated)
	 * - EMAIL (email adresses work, private, etc.)
	 * - NICKNAME
	 * - TEL (tel and fax numbers like cell, work, private, etc.)
	 * - ADR (adresses like work, private, etc.)
	 * - BDAY (birthday YYYY-MM-DD)
	 * - PHOTO (base64 encoded image)
	 * - URL (webite url)
	 * - CALURI (calendar url)
	 * - NOTE
	 * - ORG (organisation)
	 * - ROLE (organisation role)
	 * - TITLE (organisation title)
	 * 
	 * These are just a few vcard properties but I think the mostly common ones
	 * For further information visit http://en.wikipedia.org/wiki/VCard#Properties
	 * 
	 * @param array $vcard_properties array with vcard properties
	 * @access public
	 */
	public function set_vcard_properties(array $vcard_properties)
	{
		$this->vcard_properties = $vcard_properties;
	}
	
	
	/**
	 * set search filter that will be considered by $this->get()
	 * 
	 * supported match types
	 * - equals (an exact match to the target string)
	 * - contains (a substring match, matching anywhere within the target string)
	 * - starts-with (a substring match, matching only at the start of the target string)
	 * - ends-with (a substring match, matching only at the end of the target string)
	 * 
	 * @param string $property vcard property like in $this->set_vcard_properties() described
	 * @param string $match_type match types
	 * @param string $text searched text
	 * @access public
	 * @throws Exception wrong match type given
	 */
	public function set_filter($property, $match_type, $text)
	{
		if (!in_array($match_type, $this->match_types))
			throw new Exception('wrong match type given: '.$match_type);
		
		$this->filter[$property]['match_type'] = $match_type;
		$this->filter[$property]['text'] = $text;
	}
	
	
	/**
	 * set the logical filter type for the match filter
	 *
	 * @param string $filter_type filter type OR or AND
	 * @access public
	 */
	public function set_filter_type($filter_type)
	{
		switch ($filter_type)
		{
			case 'OR':
				$this->filter_type = 'allof';
			break;
			
			case 'AND':
				$this->filter_type = 'anyof';
			break;
			
			default:
				throw new Exception('wrong filter type given: '.$filter_type);
			break;
		}
	}
	
	
	/**
	 * set http request context
	 *
	 * @param string $method HTTP-Method like (OPTIONS, GET, HEAD, POST, PUT, DELETE, TRACE, COPY, MOVE)
	 * @param string $content content for cardDAV queries
	 * @param string $content_type set content-type
	 * @access private
	 */
	private function set_context($method, $content = null, $content_type = null)
	{
		$context['http']['method'] = $method;
		$context['http']['header'][] = 'User-Agent: cardDAV-PHP/0.22';
		
		if ($content !== null)
			$context['http']['content'] = $content;
	
		if ($content_type !== null)
			$context['http']['header'][] = 'Content-type: '.$content_type;
	
		if ($this->auth !== null)
			$context['http']['header'][] = 'Authorization: Basic '.$this->auth;
	
		$this->context = stream_context_create($context);
	}
	
	
	/**
	 * get xml-response from the cardDAV server
	 * 
	 * @param boolean $raw get response raw or simplified
	 * @access public
	 * @return string raw or simplified xml response
	 */
	public function get($raw = false)
	{
		$xml = new XMLWriter();
		$xml->openMemory();
		$xml->setIndent(4);
		$xml->startDocument('1.0', 'utf-8');
			$xml->startElement('C:addressbook-query');
				$xml->writeAttribute('xmlns:D', 'DAV:');
				$xml->writeAttribute('xmlns:C', 'urn:ietf:params:xml:ns:carddav');
				$xml->startElement('D:prop');
					$xml->writeElement('D:getetag');
					$xml->startElement('C:address-data');
						$xml->startElement('C:prop');
							$xml->writeAttribute('name', 'Version');
						$xml->endElement();
						$xml->startElement('C:prop');
							$xml->writeAttribute('name', 'FN');
						$xml->endElement();
						$xml->startElement('C:prop');
							$xml->writeAttribute('name', 'N');
						$xml->endElement();
						$this->xml_write_vcard_properties($xml);
					$xml->endElement();
				$xml->endElement();
				$this->xml_write_filter($xml);
			$xml->endElement();
		$xml->endDocument();
		
		$this->set_context('REPORT', $xml->outputMemory(), 'text/xml');
		$response = $this->query($this->url);
		
		if ($response === false OR $raw === true)
		{
			return $response;
		}
		else
		{
			return $this->simplify($response);
		}
	}
	
	
	/**
	* get the response from the cardDAV server
	*
	* @param resource $stream cardDAV stream resource
	* @access private
	* @return string cardDAV xml-response
	*/
	private function get_response($stream)
	{
		$response_header = stream_get_meta_data($stream);
	
		foreach ($response_header['wrapper_data'] as $header)
		{
			if (preg_match('/Content-Length/', $header))
				$content_length = (int) str_replace('Content-Length: ', null, $header);
		}
	
		return stream_get_contents($stream, $content_length);
	}
	

	/**
	 * get a vcard from the cardDAV server
	 * 
	 * @param string $id vcard id on the cardDAV server
	 * @access public
	 * @return string cardDAV xml-response
	 */
	public function get_vcard($vcard_id)
	{
		$this->set_context('GET');
		return $this->query($this->url.$vcard_id.'.vcf');
	}
	
	
	/**
	 * deletes an entry from the cardDAV server
	 * 
	 * @param string $id vcard id on the cardDAV server
	 * @access public
	 * @return string cardDAV xml-response
	 */
	public function delete($vcard_id)
	{
		$this->set_context('DELETE');
		return $this->query($this->url.$vcard_id.'.vcf');
	}
	
	
	/**
	 * adds an entry to the cardDAV server
	 *
	 * @param string $vcard vcard
	 * @param string $vcard_id vcard id on the cardDAV server
	 * @access public
	 * @return string cardDAV xml-response
	 */
	public function add($vcard, $vcard_id = null)
	{
		if ($vcard_id === null)
			$vcard_id = $this->generate_id(); 
		
		$vcard = str_replace("\t", null, $vcard);
		$this->set_context('PUT', $vcard, 'text/vcard');
		return $this->query($this->url.$vcard_id.'.vcf');
	}
	
	
	/**
	 * updates an entry to the cardDAV server
	 *
	 * @param string $vcard vcard
	 * @param string $id vcard id on the cardDAV server
	 * @access public
	 * @return string cardDAV xml-response
	 */
	public function update($vcard, $vcard_id)
	{
		return $this->add($vcard, $vcard_id);
	}
	
	
	/**
	 * simplify cardDAV xml-response
	 *
	 * @param string $response cardDAV xml-response
	 * @access private
	 * @return string simplified cardDAV xml-response
	 */
	private function simplify($response)
	{
		$response = str_replace('VC:address-data', 'vcard', $response);
		$xml = new SimpleXMLElement($response);
		
		$simplified_xml = new XMLWriter();
		$simplified_xml->openMemory();
		$simplified_xml->setIndent(4);
		
		$simplified_xml->startDocument('1.0', 'utf-8');
			$simplified_xml->startElement('response');
			
				foreach ($xml->response as $response)
				{
					preg_match('/[A-F0-9]{8}-[A-F0-9]{8}-[A-F0-9]{8}/', $response->href, $id);
					
					$simplified_xml->startElement('element');
						$simplified_xml->writeElement('id', (isset($id[0]) ? $id[0] : null));
						$simplified_xml->writeElement('etag', str_replace('"', null, $response->propstat->prop->getetag));
						$simplified_xml->writeElement('vcard', $response->propstat->prop->vcard);
					$simplified_xml->endElement();
				}
			
			$simplified_xml->endElement();
		$simplified_xml->endDocument();
		
		return $simplified_xml->outputMemory();
	}
	
	/**
	 * write vcard properties xml formatted
	 * 
	 * @param XMLWriter $xml
	 * @access private
	 */
	private function xml_write_vcard_properties(XMLWriter $xml)
	{
		foreach ($this->vcard_properties as $property)
		{
			$xml->startElement('C:prop');
				$xml->writeAttribute('name', $property);
			$xml->endElement();
		}
	}
	
	
	/**
	 * write carddav filter xml formatted
	 * 
	 * @param XMLWriter $xml
	 * @access private
	 */
	private function xml_write_filter(XMLWriter $xml)
	{
		if (!empty($this->filter))
		{
			$xml->startElement('C:filter');
				$xml->writeAttribute('test', $this->filter_type);
				foreach ($this->filter as $property => $filter)
				{
					$xml->startElement('C:prop-filter');
						$xml->writeAttribute('name', $property);
						$xml->startElement('C:text-match');
							$xml->writeAttribute('collation', 'i;unicode-casemap');
							$xml->writeAttribute('match-type', $filter['match_type']);
							$xml->text($filter['text']);
						$xml->endElement();
					$xml->endElement();
				}
			$xml->endElement();
		}
	}
	
	
	/**
	 * quries the cardDAV server and returns the response
	 * 
	 * @param string $url cardDAV server url
	 * @access private
	 * @throws Exception can't access url
	 * @return string cardDAV xml-response
	 */
	private function query($url)
	{
		if ($stream = fopen($url, 'r', false, $this->context))
		{
			return $this->get_response($stream);
		}
		else
		{
			throw new Exception('can\'t access url: '.$url);
		}
		
	}
	
	
	/**
	 * returns a valid and unused vcard id
	 * 
	 * @access private
	 * @return string valid vcard id
	 */
	private function generate_id()
	{
		$id = null;
		
		for ($number = 0; $number <= 25; $number ++)
		{
			if ($number == 8 OR $number == 17)
			{
				$id .= '-';
			}
			else
			{
				$id .= $this->id_chars[mt_rand(0, (count($this->id_chars) - 1))];
			}
		}

		$cardDAV = new carddav($this->url);
		$cardDAV->auth = $this->auth;
		$cardDAV->set_context('GET');
		
		if ($cardDAV->query($this->url.$id.'.vcf') === false)
		{
			return $id;
		}
		else
		{
			return $this->generate_id();
		}
	}
}