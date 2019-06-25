<?php
/*
 * ACTIONS
 */
class Actions {
	private $allowed_domain = '';
	private $db = '';
	private $cookie_token;
	private $account_id;
	private $action;
	private $body_data;
	private $forms;
	private $mkto_settings;
	private $api_token;


	public function __construct($allowed_domain, $db, $forms, $mkto_settings)  {
		$this->allowed_domain = $allowed_domain;
		$this->db = $db;
		$this->forms = $forms;
		$this->mkto_settings = $mkto_settings;
		$this->body_data = (isset($this->body_data) && substr($this->body_data, 0, 1)=='{')?json_decode($this->body_data):$this->body_data;
		$this->cookie_token = (isset($_GET['c']))?$_GET['c']:'';
		$this->account_id = (isset($_GET['aid']))?$_GET['aid']:'';
		$this->action = (isset($_GET['a']))?$_GET['a']:'';
		$this->body_data = file_get_contents("php://input");
		if($this->isJson($this->body_data)) $this->body_data = json_decode($this->body_data);
	}



	public function check_action(){
		if($this->action=='prefill') $this->action_prefill();
		else if($this->action=='save_prefill') $this->action_save_prefill();
		else if($this->action=='sync') $this->action_sync();
		else {
			$this->end_script(array(
				'status' => 'success',
				'data' => array(
					'fields' => ''
				)
			));
		}
	}




	// PREFILL FORM
	private function action_prefill(){		
		if($this->check_mrkto_fetch()){

			// Check Latest
			$this->check_latest_prefills();

			// Reset timer for checking prefill again
			$this->db->update($this->db_prefix.'prefill_settings', array(			
				'api_calls' => 1,
				'last_check_timestamp'=>time()
			), array(
				'account_id' => $this->account_id
			));
		}

		$form = $this->db->query('SELECT	fields
														FROM '.$this->db_prefix.'prefill_info
														WHERE account_id = ? AND tokens LIKE ?
														LIMIT 1', array($this->account_id, '%'.$cookie_token.'%'), true);

		if(isset($form->fields)){
			$this->end_script(array(
				'status' => 'success',
				'data' => array(
					'fields' => $form->fields
				)
			));
		} else {
			$this->end_script(array(
				'status' => 'success',
				'data' => array(
					'fields' => ''
				)
			));
		}
	}




	// SAVE PREFILL INFO
	private function action_save_prefill(){
		if(!isset($_POST) && !isset($_POST['mkto_token'])) $this->end_script(array('status'=>'failed', 'data'=>array('reason'=>'no fields sent')));

		// Get previous
		$prev_fields = array();
		$form = $this->db->query('SELECT	pfi.id, 
																			pfi.fields,
																			pfi.tokens
																	FROM '.$this->db_prefix.'prefill_info as pfi
																	WHERE pfi.tokens LIKE ?
																	LIMIT 1', array('%'.$_POST['mkto_token'].'%'), true);
		if(isset($form->fields)){
			$temp_fields = json_decode($form->fields);
			foreach($temp_fields as $k=>$v){
				$prev_fields[$k] = $v;
			}
		}

		// Prefill fields
		$settings = $this->db->query('SELECT prefill_fields
																		FROM '.$this->db_prefix.'prefill_settings
																		WHERE account_id = ?
																		LIMIT 1', array($this->account_id), true);
		$prefill_fields = json_decode($settings->prefill_fields);

		// Loop through allowed fields
		
		$new_fields = array();
		foreach($prefill_fields as $field){
			
			// Loop through posts
			if(isset($prev_field[$field])) $new_fields[$field] = $prev_field[$field];
			if(isset($_POST[$field])) $new_fields[$field] = $_POST[$field];
		}

		// Update Prefill in db
		if(isset($form->id)){
			$tokens = $form->tokens;
			if(strpos($tokens, $_POST['mkto_token'])===FALSE){
				if($tokens>'') $tokens.= ',';
				$tokens.= $_POST['mkto_token'];
			}

			$result = $this->db->update('prefill_info', array(
				'fields' => json_encode($new_fields)
			), array('id' => $form->id));
			
		} else {
			$email = (isset($_POST['Email']))?$_POST['Email']:'';
			if(count($new_fields)>0){
				$result = $this->db->insert('prefill_info', array(
					'account_id' => $this->account_id,
					'tokens' => $_POST['mkto_token'],
					'email' => $email,
					'fields' => json_encode($new_fields),
					'created' => date('Y-m-d H:i:s', time())
				));
			}
		}


		$this->end_script(array(
			'status' => 'success'
		));
	}



	// SYNC FIELDS FROM FormFuse.com
	private function action_sync(){
		$this->db->update($this->db_prefix.'prefill_settings', array(
			'prefill_fields' => json_encode($this->body_data->fields)
		), array(
			'account_id' => $this->account_id
		));

		$this->end_script(array(
			'status' => 'success'
		));
	}










	public function setup_headers(){
		// Only allow from 
		header("Access-Control-Allow-Headers: authorization,content-type");
		header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
		header("Access-Control-Allow-Origin: *");
		header('Access-Control-Allow-Credentials: true');
		header('Access-Control-Max-Age: 86400');
		header("Content-type:application/json");


		// Show error if origin doesn't exist
		if($this->allowed_domain==''){
			$this->end_script(array('status' => 'error', 'reason' => 'ERROR: Please set the domain from which your forms reside in the \'$prefill_settings\' variable'));
		}

		// Check if Origin script is the same as the domain allowed
		$origin = $this->prefill_settings = ($_SERVER['HTTP_ORIGIN'])?$_SERVER['HTTP_ORIGIN']:'';
		if($origin!=$this->prefill_settings && $origin!='https://client.formfuse.com'){
			$this->end_script(array('status' => 'error', 'reason' => 'ERROR: Domain not allowed to access script'));
		}
	}




	private function check_latest_prefills(){
		// Get marketo api token
		$this->api_token = $forms->getToken($mkto_settings);

		// First Page
		if($form->last_prefill_mkto_check>'') $this->forms->last_date = $form->last_prefill_mkto_check;
		$pageToken = $this->forms->getPageToken($mkto_settings, $this->api_token);		
		$page_token = $pageToken->pageToken;


		// adjust fields in obj
		$fields = array();
		foreach($this->mkto_settings->fields as $field) $fields[] = $field->field;
		$this->mkto_settings->fields = $fields;
		
		// Add email if not in
		if(!in_array('Email', $this->mkto_settings->fields)) $this->mkto_settings->fields[] = 'Email';

		// Get activities
		$this->getActivities($page_token, 1);
	}




	private function getActivities($page_token, $pageNum){		
		// Only get the first two pages of activities....don't want to hold up the script.
		if($pageNum<3){
			$activities = $this->forms->getActivityUpdates($this->mkto_settings, $this->api_token, $page_token);
			$updates = array();
			
			// Loop through Activities
			if(isset($activities->activities)){
				foreach($activities->activities as $activity){
					if( strtotime($this->forms->last_date) < strtotime($activity->activityDate) ) $this->forms->last_date = date('Y-m-d\TH:i:s\Z', strtotime($activity->activityDate));

					// Figure out the 'Form Fields' Attribute
					if(isset($activity->attributes)){
						foreach($activity->attributes as $att){
							if(isset($att->name) && $att->name=='Form Fields'){
								$fields = unserialize($att->value);

								// Get tracking
								if(isset($fields['_mkt_trk'])){
									$temp = explode('&token:', $fields['_mkt_trk']);

									// If tracking found then put it together
									if(isset($temp[1])){
										foreach($fields as $field=>$value){
											if(in_array($field, $this->mkto_settings->fields)) $updates[$temp[1]][$field] = $value;
										}
									}
								}
							}
						}
					}
				}
			}

			
			// Update database
			foreach($updates as $token=>$item){
				$this->forms->total_update = $this->forms->total_updates+1;
				if(!isset($item['Email'])) $item['Email'] = '';

				$row = $this->db->query('SELECT * FROM prefill_info WHERE tokens LIKE ? OR email = ? LIMIT 1', array('%'.$token.'%', $item['Email']), true);
				if(isset($row->id)){
					$fields = (array)json_decode($row->fields);
					$new_fields = array_unique (array_merge ($fields, $item));
					
					// Get all tokens and keep only the last 6
					$tokens = explode('|', $row->tokens);
					while(count($tokens)>5){
						array_shift($tokens);
					}
					if(!in_array($token, $tokens)) $tokens[] = $token;

					// Update
					$this->db->update('prefill_info', array(
						'tokens' => implode('|', $tokens),
						'email' => $item['Email'],
						'fields' => json_encode($new_fields)
					), array('id' => $row->id));

				} else {
					// Insert
					$this->db->insert('prefill_info', array(
						'tokens' => $token,
						'email' => $item['Email'],
						'fields' => json_encode($item),
						'created' => date('Y-m-d H:i:s')
					));
				}
			}

			if(isset($activities->moreResult) && $activities->moreResult==1){
				$pageNum++;
				$this->getActivities($this->forms, $this->mkto_settings, $this->api_token, $activities->nextPageToken, $pageNum);
			}
		}
	}




	private function isJson($string) {
		json_decode($string);
		return (json_last_error() == JSON_ERROR_NONE);
	}

	private function end_script($response){
		die(json_encode($response));
	}



	private function check_mrkto_fetch(){
		// GET PREFILL
		$form = $this->db->query('SELECT	last_check_timestamp
																	FROM '.$this->db_prefix.'prefill_settings
																	WHERE '.$this->db_prefix.'prefill_settings.account_id = ?
																	ORDER BY last_check_timestamp DESC
																	LIMIT 1', array($this->account_id), true);
		if(!isset($form->last_check_timestamp)){
			// Reset timer for checking prefill again
			$this->db->insert($this->db_prefix.'prefill_settings', array(
				'account_id' => $this->account_id,
				'api_calls' => 1,
				'last_check_timestamp'=>time()
			));

			$last_check_date = time()-3600;

		} else $last_check_date = $form->last_check_timestamp;


		// Time Check
		$can_check_time = time() - $prefill_settings_x_min; // check every X min or more
		return ($last_check_date < $can_check_time);
	}
}