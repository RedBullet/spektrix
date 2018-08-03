<?php

namespace Spektrix;

class Base
{
  protected $wp_theme;
  protected $resource;
  protected $params;
  protected $path_to_cache;

  private $api_key;
  private $certificate_path;
  private $key_path;
  private $api_url;

  /**
    * Create a new object to connect to Spektrix
    */

  public function __construct()
  {
    $this->api_key = SPEKTRIX_API_KEY;
    $this->certificate_path = $this->set_if_file_exists(SPEKTRIX_CERTIFICATE_PATH);
    $this->key_path = $this->set_if_file_exists(SPEKTRIX_KEY_PATH);
    $this->api_url = SPEKTRIX_API_URL;
  }

  /**
    * Get an XML object
    *
    * @param string $resource
    * @param array $params
    * @return SimpleXMLElement(s)
    */

  protected function get_xml_object($resource, $params=array(), $method='GET', $reqbody=null)
  {
    $this->resource = $resource;
    $this->params = $params;
    $this->method = $method;
    $this->reqbody = $reqbody;
    try {
      $xml_string = $this->load_or_retrieve_data();
      if($xml_string || (!$xml_string && $method !== 'GET')){
        $xml_as_object = simplexml_load_string($xml_string);
        return $xml_as_object;
      } else {
        throw new \Exception('No XML received from Spektrix');
      }
    }
    catch (\Exception $e){
      $this->redirectAsError();
    }
  }

  /**
    * Build the URL for the API request
    *
    * @return string - the full to use with cURL
    * @see Base::request_xml()
    */

  private function build_url()
  {
    $params_string = '';
    if(!empty($this->params)){
      foreach($this->params as $k => $v){
        $params_string .= $k . '=' . $v . '&';
      }
    }
    return $this->api_url . $this->resource . "?" . $params_string . "api_key=" . $this->api_key . "&all=true";
  }

  /**
    * Make the API request
    *
    * @param string $xml_url
    * @return string - the XML received from Spektrix
    * @see Base::build_url()
    */

  private function request_xml($xml_url, $method = 'GET', $reqbody = null)
  {
    $curl = curl_init();
    $requestHeaders = array();

    $options = array(
      CURLOPT_URL => $xml_url,
      CURLOPT_RETURNTRANSFER => 1,
      CURLOPT_SSLCERT => $this->certificate_path,
      CURLOPT_SSLKEY => $this->key_path
    );

    if ($method === 'POST') {
        $options[CURLOPT_POST] = true;
    } elseif ($method !== 'GET') {
        $options[CURLOPT_CUSTOMREQUEST] = $method;
    }

    if ($method === 'POST' || $method === 'PATCH') {
        $requestHeaders[] = 'Content-Type: application/xml';
        $options[CURLOPT_POSTFIELDS] = $reqbody;
    }

    $options[CURLOPT_HTTPHEADER] = $requestHeaders;

    curl_setopt_array($curl, $options);
    $string = curl_exec($curl);
    return $string;
  }

  private function load_or_retrieve_data()
  {
    $file = new CachedFile($this->resource, $this->params);
    if($file->is_cached_and_fresh() && 1 === 2){
      $xml_string = $file->retrieve();
    } else {
      $xml_string = $this->request_xml($this->build_url(), $this->method, $this->reqbody);
      $file->store($xml_string);
    }
    return $xml_string;
  }

  /**
    * Redirect on Exception / Error
    *
    * @return void
    */

  private function redirectAsError()
  {
    echo "<div class='alert alert-warning'>Sorry, we seem to be having a few problems with the connection to our booking system at the moment. Please give our box office a call to book your tickets</div>";
    die();
  }

  /**
    * Set if Exists
    *
    * @return string - path to file
    */

  private function set_if_file_exists($path)
  {
    try {
      if(file_exists($path)){
        return $path;
      } else {
        throw new \Exception('Cannot find file: ' . $path);
      }
    } catch (\Exception $e){
      echo $this->print_error($e);
    }
  }

  private function print_error($error)
  {
    echo $error->getMessage();
    die();
  }
}
