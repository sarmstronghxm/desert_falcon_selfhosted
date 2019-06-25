<?php
class DBConn  {
	public $db = null;

	private $db_host;
	private $db_user;
	private $db_pass;
	private $db_name;

	private $show_errors = true;
	private $last_query;


	/* Constructor */
	public function __construct($host, $user, $pass, $db_name)  {
		$this->db_host = $host;
		$this->db_user = $user;
		$this->db_pass = $pass;
		$this->db_name = $db_name;
		if (!$this->db ) $this->db = mysqli_connect($this->db_host, $this->db_user, $this->db_pass, $this->db_name);

		mysqli_set_charset($this->db, "utf8");
	}

	public function __destruct() {
		if($this->db) mysqli_close($this->db);
	}



	// QUERY
	public function query($sql, $vars = array(), $expect_one = false){
		$sql = str_replace("\r\n", '', $sql);
		$pos = 0;
		while(strpos($sql, '?')!==FALSE){
			$sql = preg_replace('/\?/', $this->escape($vars[$pos]), $sql, 1);
			$pos++;
		}

		$this->last_query = $sql;
		$result = mysqli_query($this->db, $sql);
		if ( !$result && $this->show_errors) echo("MySQL error: ".mysqli_error($this->db));

		if(in_array(substr(strtolower($sql), 0, 6), array('update', 'delete'))) return true;
		else {
			$rows = array();
			if (!$result) return $rows; // empty results
			else if ($expect_one) {
				while ($row = mysqli_fetch_object($result)) $one_row = $row;
				if (isset($one_row)) return $one_row;
				else return new stdClass();

			} else {
				while ($row = mysqli_fetch_object($result)) $rows[] = $row;
				return $rows;
			}
		}
	}




	// UPDATE RECORD(s)
	public function update($table, $updateItems, $whereItems){
		$u_items = array();
		foreach($updateItems as $k=>$v) $u_items[] = '`'.$k.'` = '.$this->escape($v);

		$w_items = array();
		foreach($whereItems as $k=>$v) $w_items[] = '`'.$k.'` = '.$this->escape($v);

		$sql = "UPDATE ".$table." SET ".implode(",", $u_items);
		if(count($w_items)>0) $sql.= ' WHERE '.implode(" AND ", $w_items);

		$this->last_query = $sql;
		$result = mysqli_query($this->db, $sql);
		if ( !$result && $this->show_errors) echo("MySQL error: ".mysqli_error($this->db));

		if(!$result) return false;
		return true;
	}




	// INSERT RECORD
	public function insert($table, $insertItems){
		$i_fields = $i_values = array();
		foreach($insertItems as $k=>$v){
			$i_fields[] = '`'.$k.'`';

			// SPECIAL ITEMS
			if(gettype($v) == 'string' && $v == '{cur_date}') die('testing');//$i_values[] = $this->escape(gmdate("Y-m-d", time()));
			else if(gettype($v) == 'string' && $v=="{cur_datetime}") $i_values[] = $this->escape(gmdate("Y-m-d H:i:s", time()));
			else $i_values[] = $this->escape($v);
		}

		$sql = "INSERT INTO ".$table." (".implode(",", $i_fields).") VALUES (".implode(",", $i_values).")";

		$this->last_query = $sql;
		$result = mysqli_query($this->db, $sql);
		if ( !$result && $this->show_errors) echo("MySQL error: ".mysqli_error($this->db)."<br /><br />".$sql);

		if(!$result) return -1;
		else return mysqli_insert_id($this->db);
	}



	// FOR ESCAPING QUOTES / STOP SQL INJECTION
	public function escape($value){
		$value = strip_tags(trim($value));
		if(is_numeric($value)) return mysqli_real_escape_string($this->db, $value);
		else return "'".mysqli_real_escape_string($this->db, $value)."'";
	}


	public function last_query(){
		return $this->last_query;
	}
}






class GetForms {
	var $last_date = '';
	var $total_updates = 0;

	function __construct(){
		$last_week = date('Y-m-d', time() - (86400 * 7));
		$this->last_date = $last_week.'T00:00:00-00:00Z';
	}

	public function __get($property) {
    if (property_exists($this, $property)) {
      return $this->$property;
    }
  }

  public function __set($property, $value) {
    if (property_exists($this, $property)) {
      $this->$property = $value;
    }
    return $this;
  }
	

	public function getForms($settings){
		$api_token = $this->getToken($settings);
		$full_data = new stdClass();
		$done = false;
		$offset = 0;
		while(!$done){
			$response = $this->getFormSet($settings, $api_token, $offset);

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


	private function getFormSet($settings, $api_token, $offset = 0){
		$extra_offset = ($offset>0)?'&offset='.$offset:'';
		$url = $settings->endpoint . "/asset/v1/forms.json?access_token=" . $api_token."&maxReturn=200".$extra_offset;

		$ch = curl_init($url);
		curl_setopt($ch,  CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_HTTPHEADER, array('accept: application/json',));
		$response = curl_exec($ch);
		return json_decode($response);
	}
	

	public function getToken($settings){
		$host = str_replace('/rest', '', $settings->endpoint);
		$url = $host . "/identity/oauth/token?grant_type=client_credentials&client_id=" . $settings->client_id . "&client_secret=" . $settings->client_secret;
		$ch = curl_init($url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_HTTPHEADER, array('accept: application/json',));
		$response = json_decode(curl_exec($ch));
		curl_close($ch);
		
		$token = $response->access_token;
		return $token;
	}




	public function getPageToken($settings, $api_token){
		$url = $settings->endpoint . "/v1/activities/pagingtoken.json?sinceDatetime=".$this->last_date."&access_token=" . $api_token;

		$ch = curl_init($url);
		curl_setopt($ch,  CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_HTTPHEADER, array('accept: application/json',));
		$response = curl_exec($ch);
		$response = json_decode($response);

		$data = new stdClass();
		if(isset($response->success)){
			if($response->success) {
				$data->status = 'success';
				$data->pageToken = $response->nextPageToken;


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



	public function getActivityUpdates($settings, $api_token, $pageToken){
		$url = $settings->endpoint . "/v1/activities.json?activityTypeIds=2&nextPageToken=".$pageToken."&access_token=" . $api_token;

		$ch = curl_init($url);
		curl_setopt($ch,  CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_HTTPHEADER, array('accept: application/json',));
		$response = curl_exec($ch);
		$response = json_decode($response);

		$data = new stdClass();
		if(isset($response->success)){
			if($response->success) {
				$data->status = 'success';
				$data->activities = (isset($response->result))?$response->result:array();
				$data->moreResult = $response->moreResult;
				$data->nextPageToken = $response->nextPageToken;

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



	public function getAllFields($settings){
		$api_token = $this->getToken($settings);
		
		$url = $settings->endpoint . "/asset/v1/form/fields.json?maxReturn=200&access_token=" . $api_token;

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