<?php

/*
 * cardDAV Class
 *
 * simple carddav query
 * --------------------
 * $carddav = new carddav('https://davical.example.com/user/contacts/');
 * $carddav->set_auth('username', 'password');
 * $carddav->set_filter('NICKNAME', 'equals', 'EdMolf');
 * $carddav->set_fields(array('FN','EMAIL'));
 * echo $carddav->get();
 * 
 * 
 * @author Christian Putzke <cputzke@graviox.de>
 * @copyright Graviox Studios
 * @since 20.07.2011
 * @version 0.1
 * @license http://gnu.org/copyleft/gpl.html GNU GPL v2 or later
 * 
 */
class carddav
{
	protected $url;
	protected $auth = NULL;
	protected $context = array();
	protected $fields = array();
	protected $filter = array();
	
	
	/*
	 * set the cardDAV server url
	 * 
	 * @param string $url cardDAV server url
	 */
	public function __construct($url)
	{
		$this->url = $url; 
	}
	
	
	/*
	 * set authentification information and base64 encode them
	 * 
	 * @param string $username cardDAV username
	 * @param string $password cardDAV password
	 */
	public function set_auth($username, $password)
	{
		$this->auth = base64_encode($username.':'.$password);
	}
	
	
	/*
	 * set fields that will be returned by $this->get()
	 * 
	 * common fields
	 * - FN (full name without any semicolon)
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
	 * these are just a few fields but I think the mostly common ones
	 * 
	 * @param array $fields array with fields that will be displayed in the vcards
	 */
	public function set_fields(array $fields)
	{
		$this->fields = $fields;
	}
	
	
	/*
	 * set search filter that will be considered by $this->get()
	 * 
	 * supported match types
	 * - equals (an exact match to the target string)
     * - contains (a substring match, matching anywhere within the target string)
     * - starts-with (a substring match, matching only at the start of the target string)
     * - ends-with (a substring match, matching only at the end of the target string)
	 * 
	 * @param string $fieldname fieldnames like in $this->set_fields() described
	 * @param string $match_type match types
	 * @param string $text searched text
	 */
	public function set_filter($fieldname, $match_type, $text)
	{
		$this->filter[$fieldname]['match_type'] = $match_type;
		$this->filter[$fieldname]['text'] = $text;
	}
	
	
	/*
	* set http request context
	*
	* @param string $method HTTP-Method like (OPTIONS, GET, HEAD, POST, PUT, DELETE, TRACE, COPY, MOVE)
	* @param string $content content for cardDAV queries
	* @param string $content_type set content-type
	*/
	private function set_context($method, $content = NULL, $content_type = NULL)
	{
		$context['http']['method'] = $method;
	
		if ($content !== NULL)
			$context['http']['content'] = $content;
	
		if ($content_type !== NULL)
			$context['http']['header'][] = 'Content-type: '.$content_type;
	
		if ($this->auth !== NULL)
			$context['http']['header'][] = 'Authorization: Basic '.$this->auth;
	
		$this->context = stream_context_create($context);
	}
	
	
	/*
	* get xml-response from the cardDAV server
	*/
	public function get()
	{
		$xml = '<?xml version="1.0" encoding="utf-8" ?>';
			$xml .= '<C:addressbook-query xmlns:D="DAV:" xmlns:C="urn:ietf:params:xml:ns:carddav">';
				$xml .= '<D:prop>';
					$xml .= '<D:getetag/>';
					$xml .= '<C:address-data>';
						$xml .= '<C:prop name="Version"/>';
						$xml .= $this->get_xml_fields();
					$xml .= '</C:address-data>';
				$xml .= '</D:prop>';
				$xml .= $this->get_xml_filter();
			$xml .= '</C:addressbook-query>';
			
		$this->set_context('REPORT', $xml, 'text/xml');
	
		return $this->query($this->url);
	}
	
	
	/*
	 * get fields xml formatted
	 */
	private function get_xml_fields()
	{
		if (!empty($this->fields))
		{
			$xml_fields = NULL;
			foreach ($this->fields as $fieldname)
			{
				$xml_fields .= '<C:prop name="'.$fieldname.'"/>';
			}
			
			return $xml_fields;
		}
		
		return null;
	}
	
	
	/*
	 * get filter xml formatted
	 */
	private function get_xml_filter()
	{
		if (!empty($this->filter))
		{
			$xml_filter = '<C:filter>';
			foreach ($this->filter as $fieldname => $filter)
			{
				$xml_filter .= '<C:prop-filter name="'.$fieldname.'">';
					$xml_filter .= '<C:text-match collation="i;unicode-casemap" match-type="'.$filter['match_type'].'">';
						$xml_filter .= $filter['text'];
					$xml_filter .= '</C:text-match>';
				$xml_filter .= '</C:prop-filter>';
			}
			$xml_filter .= '</C:filter>';
			
			return $xml_filter;
		}
		
		return null;
	}
	
	
	/*
	 * get the response from the cardDAV server
	 * 
	 * @param resource $stream cardDAV stream resource
	 */
	private function get_response($stream)
	{
		$response_header = stream_get_meta_data($stream);
		
		foreach ($response_header['wrapper_data'] as $header)
		{
			if (preg_match('/Content-Length/',$header))
				$content_length = (int) str_replace('Content-Length: ',null, $header);
		}
		
		return stream_get_contents($stream, $content_length);
	}
	
	
	/*
	* quries the cardDAV server and returns the response
	* 
	* @param string $url cardDAV server url
	*/
	private function query($url)
	{
		if ($stream = @fopen($url, 'r', false, $this->context))
			return $this->get_response($stream);
		
		return false;
	}
}