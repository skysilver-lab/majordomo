<?php
/* 
*	Класс для работы с Яндекс.Диск через REST API и OAuth-авторизацию.
*
*	Copyright (C) 2014-2015 Agaphonov Dmitri aka skysilver [mailto:skysilver.da@gmail.com]
*/

class Yandex_Disk {

	private $appId;
  	private $appSecret;
	private $login;
  	private $password;

  	private	$tokenCreateTime;
  	private	$ttl;
	private $oauth_token;

	public 	$error = NULL;

	public function __construct($conf) {
		
		$this->appId = $conf['app_id'];
		$this->appSecret = $conf['app_secret'];
    	$this->login = $conf['login'];
    	$this->password = $conf['password'];
		$this->oauth_token = $conf['token'];
		
	}

	/*
		Получить метаинформацию о диске пользователя
	*/

	public function get() {
		
		return $this->_get_content('disk', 'GET');

	}

	/*
		Получить метаинформацию о файле или каталоге
	*/

	public function resources_get($path, $fields = false, $limit = false, $offset = false, $sort = false) {

		$url = 'disk/resources/?path='.urlencode($path);

		if($fields) $url .= '&fields='.$fields;
		if($limit) $url .= '&limit='.abs((int)$limit);
		if($offset) $url .= '&offset='.abs((int)$offset);
		if($sort) $url .= '&sort='.$sort;

		return $this->_get_content($url, 'GET');

	}

	/*
		Создать папку
	*/

	public function resources_put($path, $fields = false) {

		$url = 'disk/resources/?path='.urlencode($path);

		if($fields) $url .= '&fields='.$fields;

		return $this->_get_content($url, 'PUT');

	}

	/*
		Удалить файл или папку
	*/

	public function resources_delete($path, $fields = false, $permanently = false) {

		$url = 'disk/resources/?path='.urlencode($path);

		if($fields) $url .= '&fields='.$fields;
		if($permanently) $url .= '&permanently=true';

		return $this->_get_content($url, 'DELETE');

	}

	/*
		Создать копию файла или папки
	*/

	public function resources_copy($from, $path, $fields = false, $overwrite = false) {

		$url = 'disk/resources/copy/?from='.urlencode($from).'&path='.urlencode($path);

		if($fields) $url .= '&fields='.$fields;
		if($overwrite) $url .= '&permanently=true';

		return $this->_get_content($url, 'POST');

	}

	/*
		Получить ссылку на скачивание файла
	*/

	public function resources_download($path, $fields = false) {

		$url = 'disk/resources/download/?path='.urlencode($path);

		if($fields) $url .= '&fields='.$fields;

		return $this->_get_content($url, 'GET');

	}

	/*
		Получить список файлов упорядоченный по имени
	*/

	public function resources_files($fields = false, $limit = false, $media_type = false, $offset = false) {

		$url = 'disk/resources/files/?';

		if($fields) $url .= '&fields='.$fields;
		if($limit) $url .= '&limit='.abs((int)$limit);
		if($media_type) $url .= '&media_type='.$media_type;
		if($offset) $url .= '&offset='.abs((int)$offset);

		return $this->_get_content($url, 'GET');

	}

	/*
		Получить список файлов упорядоченный по дате загрузки
	*/

	public function resources_last_uploaded($fields = false, $limit = false, $media_type = false) {

		$url = 'disk/resources/last-uploaded/?';

		if($fields) $url .= '&fields='.$fields;
		if($limit) $url .= '&limit='.abs((int)$limit);
		if($media_type) $url .= '&media_type='.$media_type;

		return $this->_get_content($url, 'GET');

	}

	/*
		Переместить файл или папку
	*/

	public function resources_move($from, $path, $fields = false, $overwrite = false) {

		$url = 'disk/resources/move/?from='.urlencode($from).'&path='.urlencode($path);

		if($fields) $url .= '&fields='.$fields;
		if($overwrite) $url .= '&permanently=true';

		return $this->_get_content($url, 'POST');

	}

	/*
		Опубликовать ресурс
	*/

	public function resources_publish($path, $fields = false) {

		$url = 'disk/resources/publish/?path='.urlencode($path);

		if($fields) $url .= '&fields='.$fields;

		return $this->_get_content($url, 'PUT');

	}

	/*
		Отменить публикацию ресурса
	*/

	public function resources_unpublish($path, $fields = false) {

		$url = 'disk/resources/unpublish/?path='.urlencode($path);

		if($fields) $url .= '&fields='.$fields;

		return $this->_get_content($url, 'PUT');

	}

	/*
		Получить ссылку для загрузки файла
		https://cloud-api.yandex.net/v1/disk/resources/upload ?
		   path=<путь, по которому следует загрузить файл>
			[& overwrite=<признак перезаписи>]
			[& fields=<нужные ключи ответа>]
	*/

	public function get_upload_url($path, $fields = false, $overwrite = false) {

		$url = 'disk/resources/upload/?path='.urlencode($path);

		if($fields) $url .= '&fields='.$fields;
		if($overwrite) $url .= '&overwrite='.$overwrite;

		return $this->_get_content($url, 'GET');

	}

	/*
		Загрузить файл в облако
	*/

	public function file_upload($fileCloudPath, $fileLocalPath) {

		$req = $this->get_upload_url($fileCloudPath, false, true);
		
		$fp = fopen($fileLocalPath, 'r');
		
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $req['href']);
		curl_setopt($ch, CURLOPT_HEADER, false);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_USERAGENT, 'YandexDisk PHP Client');

		$header = array('Authorization: OAuth '.$this->oauth_token);

		curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
		curl_setopt($ch, CURLOPT_PUT, true);
		curl_setopt($ch, CURLOPT_INFILE, $fp);
		curl_setopt($ch, CURLOPT_INFILESIZE, filesize($fileLocalPath));

		$data = curl_exec($ch);
		curl_close($ch);

		fclose($fp);

		return json_decode($data, true);

	}

	/*
		Получить метаинформацию о публичном файле или каталоге
	*/

	public function public_resources_get($public_key, $fields = false, $limit = false, $offset = false, $sort = false) {

		$url = 'disk/public/resources/?public_key='.urlencode($public_key);

		if($fields) $url .= '&fields='.$fields;
		if($limit) $url .= '&limit='.abs((int)$limit);
		if($offset) $url .= '&offset='.abs((int)$offset);
		if($sort) $url .= '&sort='.$sort;

		return $this->_get_content($url, 'GET');

	}

	/*
		Получить ссылку на скачивание публичного ресурса
	*/

	public function public_resources_download($public_key, $fields = false, $path = false) {

		$url = 'disk/public/resources/download/?public_key='.urlencode($public_key);

		if($fields) $url .= '&fields='.$fields;
		if($path) $url .= '&path='.urlencode($path);

		return $this->_get_content($url, 'GET');

	}

	/*
		Сохранить публичный ресурс в папку Загрузки
	*/

	public function public_resources_save_to_disk($public_key, $fields = false, $name = false, $path = false) {

		$url = 'disk/public/resources/save-to-disk/?public_key='.urlencode($public_key);

		if($fields) $url .= '&fields='.$fields;
		if($name) $url .= '&name='.urlencode($name);
		if($path) $url .= '&path='.urlencode($path);

		return $this->_get_content($url, 'POST');

	}

	/*
		Получить содержимое Корзины
	*/

	public function trash_resources_get($path, $fields = false, $limit = false, $offset = false, $sort = false) {

		$url = 'disk/trash/resources/?path='.urlencode($path);

		if($fields) $url .= '&fields='.$fields;
		if($limit) $url .= '&limit='.abs((int)$limit);
		if($offset) $url .= '&offset='.abs((int)$offset);
		if($sort) $url .= '&sort='.$sort;

		return $this->_get_content($url, 'GET');

	}

	/*
		Очистить корзину
	*/

	public function trash_resources_delete($fields = false, $path = false) {

		$url = 'disk/trash/resources/?';

		if($fields) $url .= '&fields='.$fields;
		if($path) $url .= '&path='.urlencode($path);

		return $this->_get_content($url, 'DELETE');

	}

	/*
		Восстановить ресурс из корзины
	*/

	public function trash_resources_restore($path, $fields = false, $name = false, $overwrite = false) {

		$url = 'disk/trash/resources/restore/?path='.urlencode($path);

		if($fields) $url .= '&fields='.$fields;
		if($name) $url .= '&name='.urlencode($name);
		if($overwrite) $url .= '&overwrite=true';

		return $this->_get_content($url, 'PUT');

	}

	/*
		Получить статус асинхронной операции
	*/

	public function operations($operation_id, $fields = false) {

		$url = 'disk/operations/'.$operation_id.'/?';

		if($fields) $url .= '&fields='.$fields;

		return $this->_get_content($url, 'GET');

	}

	/*
		Отправить запрос на cloud-api
	*/

	function _get_content($url, $request) {

		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, 'https://cloud-api.yandex.net:443/v1/'.$url);
		curl_setopt($ch, CURLOPT_HEADER, false);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);
		curl_setopt($ch, CURLOPT_USERAGENT, 'YandexDisk PHP Client');
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

		$header = array('Authorization: OAuth '.$this->oauth_token);

		curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $request);

		$data = curl_exec($ch);
		curl_close($ch);

		return json_decode($data, true);

	}

	/*
		Получить OAuth-токен (или обновить сведения о нем, если был получен ранее)
	*/

	public function getToken() {
		
			$ch = curl_init();
    		curl_setopt($ch, CURLOPT_HEADER, 0);
    		curl_setopt($ch, CURLOPT_URL, 'https://oauth.yandex.ru/token');
    		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 1);
    		curl_setopt($ch, CURLOPT_FRESH_CONNECT, 1);
    		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    		curl_setopt($ch, CURLOPT_FORBID_REUSE, 1);
    		curl_setopt($ch, CURLOPT_TIMEOUT, 4);
    		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

    		$post = 'grant_type=password&client_id='.$this->appId
      			. '&client_secret='.$this->appSecret
      			. '&username='.$this->login
      			. '&password='.$this->password;
	  
    		curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
    		curl_setopt($ch, CURLOPT_POST, 1);

    		$header = [
      			'Content-type: application/x-www-form-urlencoded',
      			'Content-Length: '.strlen($post),
    		];

    		curl_setopt($ch, CURLOPT_HEADER, $header);

    		$curlResponse = curl_exec($ch);

    		if (!$curlResponse || curl_errno($ch)) {
      			$this->error = curl_error($ch);
      			curl_close($ch);
      			return false;
		}

    		$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    		if (($httpCode !== 200) && ($httpCode !== 400)) {
      			$this->error = "Request Status is " . $httpCode;
      			curl_close($ch);
      			return false;
    		}

    		$curlResponseBody = $this->getResponseBody($ch, $curlResponse);
    		$result = json_decode($curlResponseBody, true);

    		curl_close($ch);

    		if (isset($result['error']) && ($result['error'] != '')) {
      			$this->error = $result['error'];
      			return false;
    		}

    		$this->oauth_token = $result['access_token'];
    		$this->ttl = intval($result['expires_in']);
    		$this->tokenCreateTime = intval(time());
			  		
			return $this->oauth_token;
  	}

  	private function getResponseBody($ch, $curlResponse) {
    		
		return substr($curlResponse, curl_getinfo($ch, CURLINFO_HEADER_SIZE));
	
	}

}

?>
