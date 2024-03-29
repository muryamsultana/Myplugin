<?php

class XverifyClientAPI {

    var $api_host = 'http://www.xverify.com/services';
	var $api_key;
    var $services = array('phone','email','address','phoneconfirm','lead_scorecard','phoneconfirm_code');
    var $response;
	var $options;

    function __construct($api_key, $options = array()) {
        $this->api_key = $api_key;
        if (empty($this->api_key)){
            throw new InvalidArgumentException("api_key required");
        }
        $this->options = $options;
    }

    function verify($serviceName,$data) {
		if (empty($serviceName) or !in_array($serviceName,$this->services)){ 
            throw new InvalidArgumentException("Invalid service name");
        }
        $requests = array();
        $requestUrl = $this->$serviceName($data);
	    $extraParameters = http_build_query($this->options);
		if(trim($extraParameters) != '')
			$requestUrl .= '&'.$extraParameters;

        return $this->request($requestUrl);
    }
	
	public function getReponseAsObject()
	{
		$returnObj = new stdClass();
		if ($this->response == null) return $returnObj;
		$returnObj = json_decode($this->response);
		foreach ($returnObj as $res) {
			return $res;
		}
		return $returnObj;
	}

    private function request($requests) {
		$curl_connection = curl_init();
		curl_setopt($curl_connection,CURLOPT_URL,$requests);
		curl_setopt($curl_connection, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($curl_connection, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($curl_connection, CURLOPT_HEADER, 0);
		curl_setopt($curl_connection, CURLOPT_FOLLOWLOCATION, 1);
		$result = curl_exec($curl_connection);
		curl_close($curl_connection);
		$this->response = $result;
		return $result;
    }

    private function email($data) {
        $url = $this->api_host . '/emails/verify/';
		$value = '';
		if(is_array($data) && isset($data['email']))
		{
			$value = $data['email'];
		}
        $params = array('email' => $value, 'apikey' => $this->api_key);
        return $url . '?' . http_build_query($params);
    }
	
	private function phone($data) {
        $url = $this->api_host . '/phone/verify/';
		$value = '';
		if(is_array($data) && isset($data['phone']))
		{
			$value = $data['phone'];
		}
        $params = array('phone' => $value, 'apikey' => $this->api_key);
        return $url . '?' . http_build_query($params);
    }
	
	private function address($data) {
        $url = $this->api_host . '/address/verify/';
		$street = '';
		$zip = '';
		if(is_array($data))
		{
			if(isset($data['street'])) $street = $data['street'];
			if(isset($data['zip'])) $zip = $data['zip'];
		}
        $params = array('street' => $street,'zip' => $zip, 'apikey' => $this->api_key);
        return $url . '?' . http_build_query($params);
    }
    public function is_valid() {
        if ($this->response == null) return null;
		$responseObj = json_decode($this->response);

        $valid = true;
        foreach ($responseObj as $res) {
            if (isset($res->status) && $res->status != 'valid') {
                $valid = false;
            }
        }
        return $valid;
    }
    public function status() {
        if ($this->response == null) return null;
		$responseObj = json_decode($this->response);
        $status = 'unknown';
        foreach ($responseObj as $res) {
            if (isset($res->status)) {
				$status = $res->status;
            }
        }
        return $status;
    }
	 private function phoneconfirm($data) {
        $url = $this->api_host . '/phoneconfirm/placecall/';
		$phone = '';
		$country_code = 1;
		$code = '';
		$redial_count = 3;
		$redial_interval = 10;
		$call_place_time = 30;
		
		if(is_array($data))
		{
			if(isset($data['phone'])) $phone = $data['phone'];
			if(isset($data['country_code'])) $country_code = $data['country_code'];
			if(isset($data['code'])) $code = $data['code'];
			if(isset($data['redial_count'])) $redial_count = $data['redial_count'];
			if(isset($data['call_place_time'])) $call_place_time = $data['call_place_time'];
			if(isset($data['street'])) $street = $data['street'];
		}
         $params = array(
		 	'apikey' => $this->api_key,'phone' => $phone,'country_code' => $country_code, 
			'code' => $code,'redial_count'=>$redial_count,'redial_interval'=>$redial_interval,'call_place_time'=>$call_place_time);
        return $url . '?' . http_build_query($params);
    }
	
	private function phoneconfirm_code($data) {
        $url = $this->api_host . '/phoneconfirm/verifycode/';
		$transaction_number = '0';
		$code = 0;
		
		if(is_array($data))
		{
			if(isset($data['transaction_number'])) $phone = $data['transaction_number'];
			if(isset($data['code'])) $street = $data['code'];
		}
         $params = array(
		 	'apikey' => $this->api_key,'transaction_number' => $transaction_number,'code' => $code);
        return $url . '?' . http_build_query($params);
    }
	 private function lead_scorecard($data) {
        $url = $this->api_host . '/scoring/verify/';
		$phone = '';
		$ip = '';
		$firstname = '';
		$lastname = '';
		$address = '';
		$city = '';
		$state = '';
		$zip = '';
		$email = '';
		$start_time = '';	
		
		if(is_array($data))
		{
			if(isset($data['phone'])) $phone = $data['phone'];
			if(isset($data['ip'])) $ip = $data['ip'];
			if(isset($data['firstname'])) $firstname = $data['firstname'];
			if(isset($data['lastname'])) $lastname = $data['lastname'];
			if(isset($data['address'])) $address = $data['address'];
			if(isset($data['city'])) $city = $data['city'];
			if(isset($data['state'])) $state = $data['state'];
			if(isset($data['zip'])) $zip = $data['zip'];
			if(isset($data['email'])) $email = $data['email'];
			if(isset($data['start_time'])) $start_time = $data['start_time'];
		}
         $params = array(
		 	'apikey' => $this->api_key,'phone' => $phone,'ip' => $ip, 
			'firstname' => $firstname,'lastname'=>$lastname,'address'=>$address,'city'=>$city,
			'state' => $state,'zip'=>$zip,'email'=>$email,'start_time'=>$start_time);
        return $url . '?' . http_build_query($params);
    }
}