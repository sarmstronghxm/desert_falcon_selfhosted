<?php
/*
   GetFolders.php

   Marketo REST API Sample Code
   Copyright (C) 2016 Marketo, Inc.

   This software may be modified and distributed under the terms
   of the MIT license.  See the LICENSE file for details.
*/

class GetForms{
	private $settings;
	private $api_token;



	public function __construct($settings){
		$this->settings = $settings;
	}

	
	public function getForms(){
		$this->api_token = $this->getToken();
		$full_data = new stdClass();
		$done = false;
		$offset = 0;
		while(!$done){
			$response = $this->getFormSet($offset);

			if(isset($response->success) && isset($response->result) && $response->success) {
				$full_data = (object) array_merge((array) $response->result, (array) $full_data);
				if(count($response->result)<200) $done = true;
				else $offset+=200;
				
			} else {
				$done = true;
			}
		}

		$data = new stdClass();
		if(count((array)$full_data)>0) {
			$data->status = 'success';
			$data->forms_list = $full_data;

		} else {
			$data->status = 'failed';
			$data->reason = 'nothing returned from marketo';
		}	

		return $data;
	}


	private function getFormSet($offset = 0){
		$extra_offset = ($offset>0)?'&offset='.$offset:'';
		$url = $this->settings->endpoint . "/asset/v1/forms.json?access_token=" . $this->api_token."&maxReturn=200".$extra_offset;

		$ch = curl_init($url);
		curl_setopt($ch,  CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_HTTPHEADER, array('accept: application/json',));
		$response = curl_exec($ch);
		return json_decode($response);
	}
	

	private function getToken(){
		$host = str_replace('/rest', '', $this->settings->endpoint);
		$url = $host . "/identity/oauth/token?grant_type=client_credentials&client_id=" . $this->settings->client_id . "&client_secret=" . $this->settings->client_secret;
		$ch = curl_init($url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_HTTPHEADER, array('accept: application/json',));
		$response = json_decode(curl_exec($ch));
		curl_close($ch);
		
		$token = $response->access_token;
		return $token;
	}




	public function getAllFields(){
		$this->api_token = $this->getToken();
		
		$url = $this->settings->endpoint . "/asset/v1/form/fields.json?maxReturn=200&access_token=" . $this->api_token;

		$ch = curl_init($url);
		curl_setopt($ch,  CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_HTTPHEADER, array('accept: application/json',));
		$response = curl_exec($ch);
		$response = json_decode($response);

		$data = new stdClass();
		if(isset($response->success)){
			if($response->success) {
				$data->status = 'success';
				$forms_list = $response->result;

				$data->forms_list = array();
				foreach($forms_list as $item) $data->forms_list[] = $item->id;

			} else {
				$data->status = 'failed';
				$data->reason = 'nothing returned from marketo';
				$data->details = print_r($response, true);
			}
		} else {
			$data->status = 'failed';
			$data->reason = 'nothing returned from marketo';
		}	

		return $data;
	}
	
}