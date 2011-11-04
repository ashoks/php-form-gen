<?php

/**
 * @package 	jquery.Formbuilder
 * @author 		Michael Botsko
 * @copyright 	2009 Trellis Development, LLC
 *
 * This PHP object is the server-side component of the jquery formbuilder
 * plugin. The Formbuilder allows you to provide users with a way of
 * creating a formand saving that structure to the database.
 *
 * Using this class you can easily prepare the structure for storage,
 * rendering the xml file needed for the builder, or render the html of the form.
 *
 * This package is licensed using the Mozilla Public License 1.1
 *
 * We encourage comments and suggestion to be sent to mbotsko@trellisdev.com.
 * Please feel free to file issues at http://github.com/botskonet/jquery.formbuilder/issues
 * Please feel free to fork the project and provide patches back.
 */

 // Uncomment these for debug
//error_reporting(E_ALL);
//ini_set('display_errors', true);


/**
 * @abstract This class is the server-side component that handles interaction with
 * the jquery formbuilder plugin.
 * @package jquery.Formbuilder
 */
class Formbuilder {

	/**
	 * @var array Contains the form_hash and serialized form_structure from an external source (db)
	 * @access protected
	 */
	protected $_container;

	/**
	 * @var array Holds the form source in raw array form
	 * @access protected
	 */
	protected $_structure;

	/**
	 * @var array Holds the form source in serialized form
	 * @access protected
	 */
	protected $_structure_ser;

	/**
	 * @var array Holds the hash of the serialized form
	 * @access protected
	 */
	protected $_hash;


	 /**
	  * Constructor, loads either a pre-serialized form structure or an incoming POST form
	  * @param array $containing_form_array
	  * @access public
	  */
	public function __construct($form = false){

		$form = is_array($form) ? $form : array();

		// Set the serialized structure if it's provided
		// otherwise, store the source
		if(array_key_exists('form_structure', $form)){

			$this->_container = $form; // set the form as the container
			$this->_structure_ser = $form['form_structure']; // pull the serialized form
			$this->_hash = $this->hash(); // hash the current structure
			$this->_structure = $this->retrieve(); // unserialize the form as the raw structure
			
		} else {

			$this->_structure = $form; // since the form is from POST, set it as the raw array
			$this->_structure_ser = $this->store(); // serialize it
			$this->rebuild_container(); // rebuild a new container
			
		}
		return true;
	}


	/**
	 * Wipes and re-saves the structure and hash to the containing array.
	 *
	 * @access protected
	 * @return boolean
	 */
	protected function rebuild_container(){
		$this->_container = array();
		$this->_container['form_hash'] = $this->_hash;
		$this->_container['form_structure'] = $this->_structure_ser;
		return true;
	}


	/**
	 * Takes an array containing the form admin information
	 * and serializes it for storage in the database. Provides a hash
	 * that can will be used later during rendering.
	 *
	 * The array provided is typically from $_POST generated by the jquery
	 * plugin.
	 *
	 * @access public
	 * @return array
	 */
	public function store(){
		$this->_structure_ser = serialize($this->_structure);
		$this->_hash = $this->hash($this->_structure_ser);
		return array('form_structure'=>$this->_structure_ser,'form_hash'=>$this->_hash);
	}


	/**
	 * Creates a hash that's used to check the contents
	 * have not changed from what was saved.
	 * 
	 * @access public
	 * @return string
	 */
	public function hash(){
		return sha1($this->_structure_ser);
	}


	/**
	 * Returns a serialized form back into it's original array, for use
	 * with rendering.
	 *
	 * @param string $form_array
	 * @access public
	 * @return boolean
	 */
	public function retrieve(){
		if(is_array($this->_container) && array_key_exists('form_hash', $this->_container)){
		 	if($this->_container['form_hash'] == $this->hash($this->_container['form_structure'])){
				return unserialize($this->_container['form_structure']);
		  }
		}
		return false;
	}


	/**
	 * Prints out the generated xml file with a content-type of text/xml
	 *
	 * @access public
	 * @uses generate_xml
	 */
	public function render_xml(){
		header("Content-Type: text/xml");
		print $this->generate_xml();
	}

	/**
	 * Builds an xml structure that the jquery plugin will parse for form admin
	 * structure. Right now we're just building the xml the old fashioned way
	 * so that we're not dependant on DOMDocument or something.
	 *
	 * @access public
	 */
	public function generate_xml(){

		// begin forming the xml
		$xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'."\n";
		$xml .= '<form>'."\n";

		if(is_array($this->_structure)){
			foreach($this->_structure as $field){

				// input type="text"
				if($field['cssClass'] == "input_text"){
					$xml .= sprintf('<field type="input_text" required="%s">%s</field>'."\n", $field['required'], $this->encode_for_xml($field['values']));
				}

				// textarea
				if($field['cssClass'] == "textarea"){
					$xml .= sprintf('<field type="textarea" required="%s">%s</field>'."\n", $field['required'], $this->encode_for_xml($field['values']));
				}

				// input type="checkbox"
				if($field['cssClass'] == "checkbox"){
					$xml .= sprintf('<field type="checkbox" required="%s" title="%s">'."\n", $field['required'], (isset($field['title']) ? $this->encode_for_xml($field['title']) : ''));
					if(is_array($field['values'])){
						foreach($field['values'] as $input){
							$xml .= sprintf('<checkbox checked="%s">%s</checkbox>'."\n", $input['default'], $this->encode_for_xml($input['value']));
						}
					}
					$xml .= '</field>'."\n";
				}

				// input type="radio"
				if($field['cssClass'] == "radio"){
					$xml .= sprintf('<field type="radio" required="%s" title="%s">'."\n", $field['required'], (isset($field['title']) ? $this->encode_for_xml($field['title']) : ''));
					if(is_array($field['values'])){
						foreach($field['values'] as $input){
							$xml .= sprintf('<radio checked="%s">%s</radio>'."\n", $input['default'], $this->encode_for_xml($input['value']));
						}
					}
					$xml .= '</field>'."\n";
				}

				// select
				if($field['cssClass'] == "select"){
					$xml .= sprintf('<field type="select" required="%s" multiple="%s" title="%s">'."\n", $field['required'], $field['multiple'], (isset($field['title']) ? $this->encode_for_xml($field['title']) : ''));
					if(is_array($field['values'])){
						foreach($field['values'] as $input){
							$xml .= sprintf('<option checked="%s">%s</option>'."\n", $input['default'], $this->encode_for_xml($input['value']));
						}
					}
					$xml .= '</field>'."\n";
				}
			}
		}

		$xml .= '</form>'."\n";

		return $xml;

	}

	/**
	 * Prints out the generated json file with a content-type of application/json
	 *
	 * @access public
	 * @uses generate_json
	 */
	public function render_json(){
		header("Content-Type: application/json");
		print $this->generate_json();
	}

	/**
	 * Builds a json object that the jquery plugin will parse
	 *
	 * @access public
	 */
	public function generate_json(){
		return json_encode( $this->_structure );
	}

	/**
	 * @abstract Encodes strings for xml. 
	 * @param string $string
	 * @access private
	 * @return string
	 */
	protected function encode_for_xml($string){

		$string = html_entity_decode($string, ENT_NOQUOTES, 'UTF-8');
		$string = htmlentities($string, ENT_NOQUOTES, 'UTF-8');

		//	manually add back in html
		$string = str_replace("&lt;", "<", $string);
		$string = str_replace("&gt;", ">", $string);

		return $string;

	}


	/**
	 * Renders the generated html of the form.
	 *
	 * @param string $form_action Action attribute of the form element.
	 * @access public
	 * @uses generate_html
	 */
	public function render_html($form_action = false){
		print $this->generate_html($form_action);
	}


	/**
	 * Generates the form structure in html.
	 * 
	 * @param string $form_action Action attribute of the form element.
	 * @return string
	 * @access public
	 */
	public function generate_html($form_action = false){

		$html = '';

		$form_action = $form_action ? $form_action : $_SERVER['PHP_SELF'];

		if(is_array($this->_structure)){
	
			$html .= '<form class="frm-bldr" method="post" action="'.$form_action.'">' . "\n";
			$html .= '<ol>'."\n";

			foreach($this->_structure as $field){
				$html .= $this->loadField($field);
			}
			
			$html .= '<li class="btn-submit"><input type="submit" name="submit" value="Submit" /></li>' . "\n";
			$html .=  '</ol>' . "\n";
			$html .=  '</form>' . "\n";
			
		}

		return $html;

	}


	/**
	 * Parses the POST data for the results of the speific form values. Checks
	 * for required fields and returns an array of any errors.
	 *
	 * @access public
	 * @returns array
	 */
	public function process(){

		$error		= '';
		$results 	= array();

		// Put together an array of all expected indices
		if(is_array($this->_structure)){
			foreach($this->_structure as $field){

				$field['required'] = $field['required'] == 'true' ? true : false;

				if($field['cssClass'] == 'input_text' || $field['cssClass'] == 'textarea'){

					$val = $this->getPostValue( $this->elemId($field['values']));

					if($field['required'] && empty($val)){
						$error .= '<li>Please complete the ' . $field['values'] . ' field.</li>' . "\n";
					} else {
						$results[ $this->elemId($field['values']) ] = $val;
					}
				}
				elseif($field['cssClass'] == 'radio' || $field['cssClass'] == 'select'){

					$val = $this->getPostValue( $this->elemId($field['title']));

					if($field['required'] && empty($val)){
						$error .= '<li>Please complete the ' . $field['title'] . ' field.</li>' . "\n";
					} else {
						$results[ $this->elemId($field['title']) ] = $val;
					}
				}
				elseif($field['cssClass'] == 'checkbox'){
					if(is_array($field['values'])){

						$at_least_one_checked = false;

						foreach($field['values'] as $item){

							$elem_id = $this->elemId($item['value'], $field['title']);

							$val = $this->getPostValue( $elem_id );

							if(!empty($val)){
								$at_least_one_checked = true;
							}

							$results[ $this->elemId($item['value']) ] = $this->getPostValue( $elem_id );
						}

						if(!$at_least_one_checked && $field['required']){
							$error .= '<li>Please check at least one ' . $field['title'] . ' choice.</li>' . "\n";
						}
					}
				} else { }
			}
		}

		$success = empty($error);

		// if results is array, send email
		return array('success'=>$success,'results'=>$results,'errors'=>$error);
		
	}


	//+++++++++++++++++++++++++++++++++++++++++++++++++
	// NON-PUBLIC FUNCTIONS
	//+++++++++++++++++++++++++++++++++++++++++++++++++


	/**
	 * Loads a new field based on its type
	 *
	 * @param array $field
	 * @access protected
	 * @return string
	 */
	protected function loadField($field){

		if(is_array($field) && isset($field['cssClass'])){

			switch($field['cssClass']){

				case 'input_text':
					return $this->loadInputText($field);
					break;
				case 'textarea':
					return $this->loadTextarea($field);
					break;
				case 'checkbox':
					return $this->loadCheckboxGroup($field);
					break;
				case 'radio':
					return $this->loadRadioGroup($field);
					break;
				case 'select':
					return $this->loadSelectBox($field);
					break;
			}
		}

		return false;

	}


	/**
	 * Returns html for an input type="text"
	 * 
	 * @param array $field Field values from database
	 * @access protected
	 * @return string
	 */
	protected function loadInputText($field){

		$field['required'] = $field['required'] == 'true' ? ' required' : false;

		$html = '';
		$html .= sprintf('<li class="%s%s" id="fld-%s">' . "\n", $this->elemId($field['cssClass']), $field['required'], $this->elemId($field['values']));
		$html .= sprintf('<label for="%s">%s</label>' . "\n", $this->elemId($field['values']), $field['values']);
		$html .= sprintf('<input type="text" id="%s" name="%s" value="%s" />' . "\n",
								$this->elemId($field['values']),
								$this->elemId($field['values']),
								$this->getPostValue($this->elemId($field['values'])));
		$html .= '</li>' . "\n";

		return $html;

	}


	/**
	 * Returns html for a <textarea>
	 *
	 * @param array $field Field values from database
	 * @access protected
	 * @return string
	 */
	protected function loadTextarea($field){

		$field['required'] = $field['required'] == 'true' ? ' required' : false;

		$html = '';
		$html .= sprintf('<li class="%s%s" id="fld-%s">' . "\n", $this->elemId($field['cssClass']), $field['required'], $this->elemId($field['values']));
		$html .= sprintf('<label for="%s">%s</label>' . "\n", $this->elemId($field['values']), $field['values']);
		$html .= sprintf('<textarea id="%s" name="%s" rows="5" cols="50">%s</textarea>' . "\n",
								$this->elemId($field['values']),
								$this->elemId($field['values']),
								$this->getPostValue($this->elemId($field['values'])));
		$html .= '</li>' . "\n";

		return $html;

	}


	/**
	 * Returns html for an <input type="checkbox"
	 *
	 * @param array $field Field values from database
	 * @access protected
	 * @return string
	 */
	protected function loadCheckboxGroup($field){

		$field['required'] = $field['required'] == 'true' ? ' required' : false;

		$html = '';
		$html .= sprintf('<li class="%s%s" id="fld-%s">' . "\n", $this->elemId($field['cssClass']), $field['required'], $this->elemId($field['title']));

		if(isset($field['title']) && !empty($field['title'])){
			$html .= sprintf('<span class="false_label">%s</span>' . "\n", $field['title']);
		}

		if(isset($field['values']) && is_array($field['values'])){
			$html .= sprintf('<span class="multi-row clearfix">') . "\n";
			foreach($field['values'] as $item){

				// set the default checked value
				$checked = $item['default'] == 'true' ? true : false;

				// load post value
				$val = $this->getPostValue($this->elemId($item['value']));
				$checked = !empty($val);

				// if checked, set html
				$checked = $checked ? ' checked="checked"' : '';

				$checkbox 	= '<span class="row clearfix"><input type="checkbox" id="%s-%s" name="%s-%s" value="%s"%s /><label for="%s-%s">%s</label></span>' . "\n";
				$html .= sprintf($checkbox, $this->elemId($field['title']), $this->elemId($item['value']), $this->elemId($field['title']), $this->elemId($item['value']), $item['value'], $checked, $this->elemId($field['title']), $this->elemId($item['value']), $item['value']);
			}
			$html .= sprintf('</span>') . "\n";
		}

		$html .= '</li>' . "\n";

		return $html;

	}


	/**
	 * Returns html for an <input type="radio"
	 * @param array $field Field values from database
	 * @access protected
	 * @return string
	 */
	protected function loadRadioGroup($field){

		$field['required'] = $field['required'] == 'true' ? ' required' : false;

		$html = '';

		$html .= sprintf('<li class="%s%s" id="fld-%s">' . "\n", $this->elemId($field['cssClass']), $field['required'], $this->elemId($field['title']));

		if(isset($field['title']) && !empty($field['title'])){
			$html .= sprintf('<span class="false_label">%s</span>' . "\n", $field['title']);
		}

		if(isset($field['values']) && is_array($field['values'])){
			$html .= sprintf('<span class="multi-row">') . "\n";
			foreach($field['values'] as $item){

				// set the default checked value
				$checked = $item['default'] == 'true' ? true : false;

				// load post value
				$val = $this->getPostValue($this->elemId($field['title']));
				$checked = !empty($val);

				// if checked, set html
				$checked = $checked ? ' checked="checked"' : '';

				$radio 		= '<span class="row clearfix"><input type="radio" id="%s-%s" name="%1$s" value="%s"%s /><label for="%1$s-%2$s">%3$s</label></span>' . "\n";
				$html .= sprintf($radio,
										$this->elemId($field['title']),
										$this->elemId($item['value']),
										$item['value'],
										$checked);
			}
			$html .= sprintf('</span>') . "\n";
		}

		$html .= '</li>' . "\n";

		return $html;

	}


	/**
	 * Returns html for a <select>
	 * 
	 * @param array $field Field values from database
	 * @access protected
	 * @return string
	 */
	protected function loadSelectBox($field){

		$field['required'] = $field['required'] == 'true' ? ' required' : false;

		$html = '';

		$html .= sprintf('<li class="%s%s" id="fld-%s">' . "\n", $this->elemId($field['cssClass']), $field['required'], $this->elemId($field['title']));

		if(isset($field['title']) && !empty($field['title'])){
			$html .= sprintf('<label for="%s">%s</label>' . "\n", $this->elemId($field['title']), $field['title']);
		}

		if(isset($field['values']) && is_array($field['values'])){
			$multiple = $field['multiple'] == "true" ? ' multiple="multiple"' : '';
			$html .= sprintf('<select name="%s" id="%s"%s>' . "\n", $this->elemId($field['title']), $this->elemId($field['title']), $multiple);

			foreach($field['values'] as $item){

				// set the default checked value
				$checked = $item['default'] == 'true' ? true : false;

				// load post value
				$val = $this->getPostValue($this->elemId($field['title']));
				$checked = !empty($val);

				// if checked, set html
				$checked = $checked ? ' checked="checked"' : '';

				$option 	= '<option value="%s"%s>%s</option>' . "\n";
				$html .= sprintf($option, $item['value'], $checked, $item['value']);
			}

			$html .= '</select>' . "\n";
			$html .= '</li>' . "\n";

		}

		return $html;

	}


	/**
	 * Generates an html-safe element id using it's label
	 * 
	 * @param string $label
	 * @return string
	 * @access protected
	 */
	private function elemId($label, $prepend = false){
		if(is_string($label)){
			$prepend = is_string($prepend) ? $this->elemId($prepend).'-' : false;
			return $prepend.strtolower( preg_replace("/[^A-Za-z0-9_]/", "", str_replace(" ", "_", $label) ) );
		}
		return false;
	}

	/**
	 * Attempts to load the POST value into the field if it's set (errors)
	 *
	 * @param string $key
	 * @return mixed
	 */
	protected function getPostValue($key){
		return array_key_exists($key, $_POST) ? $_POST[$key] : false;
	}
}
?>