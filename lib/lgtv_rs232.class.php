<?php
/* 
*	Класс для управления телевизорами LG через последовательный порт RS232.
*	Команды управления соответствуют модели LG 32LW575S.
*
*	Copyright (C) 2014-2015 Agaphonov Dmitri aka skysilver [mailto:skysilver.da@gmail.com]
*/

require('php_serial.class.php');
require('my.class.php');

class MyResult {

	public $value = 0;
	public $name = '';
	public $text = '';
	public $raw	= '';	
	public $status = '';	
	public $data = '';		
}

class lgTV_rs232
{
    public  $tty = '/dev/ttyUSB0';	// Адрес локального последовательного порта, к которому подключен ТВ
    public  $setId = '01';			// Идентификатор управляемого ТВ (из настроек самого ТВ)
    public 	$waitForReply = 0.7;	// Величина паузы (в сек.) после записи в порт и перед считыванием ответа
    public 	$confirm = true;		// Проверять статус выполнения команды (да/нет)
    
    public  $data = '';				// Поле данных в отправляемой ТВ команде
    public  $result = '';			// Ответ ТВ на переданную ему команду
    public  $status = '';			// Статус выполнения команды (если в ответе NG, то FALSE, если OK, то TRUE)

    private $serial = '';			// Экземпляр класса php_serial.class для обмена через порт RS232
    private $channels = array();	// Массив ТВ-каналов (Название/Номер)
    private $params = array();		// Массив полей данных команд
    private $commands = Array(
			 	'power' => "ka {sid} {data}", 
			 	'input select' => "xb {sid} {data}",
             	'aspect ratio' => "kc {sid} {data}",
				'screen mute' => "kd {sid} {data}",
				'volume mute' => "ke {sid} {data}",
                'volume control' => "kf {sid} {data}",
                'contrast' => "kg {sid} {data}",
                'brightness' => "kh {sid} {data}",
                'colour' => "ki {sid} {data}",
                'tint' => "kj {sid} {data}",
                'sharpness' => "kk {sid} {data}",
                'osd select' => "kl {sid} {data}",
                'remote control lock' => "km {sid} {data}",
                'treble' => "kr {sid} {data}",
                'bass' => "ks {sid} {data}",
                'balance' => "kt {sid} {data}",
                'colour temperature' => "xu {sid} {data}",
                'energy saving' => "jq {sid} {data}",
                'auto configuration' => "ju {sid} {data}",
                'channel select' => "ma {sid} {data}",
                'channel add delete' => "mb {sid} {data}",
                'remote control key' => "mc {sid} {data}",
                'backlight' => "mg {sid} {data}",
                'stereoscopic off' => "xt {sid} {data} 00 00 00",
                'stereoscopic on' => "xt {sid} 00 {data} 00 00");

    private $rangecmd = Array('volume control', 'contrast', 'brightness', 'colour', 'tint', 'sharpness', 'treble', 'bass', 'balance', 'colour temperature',
			      'backlight');

    private $specialcmds = Array('channel select');

    public function __construct() {

		$this->initFiles();
    }

    /*
		Инициализация и настройка последовательного порта
	*/

    public function serialInit() {

		$this->serial = new phpSerial;

		$status = $this->serial->deviceSet($this->tty);
		
		if ($status) {
			$status |= $this->serial->confBaudRate(9600);

                        $status |= $this->serial->confCharacterLength(8);
                        $status |= $this->serial->confParity('none');
                        $status |= $this->serial->confStopBits(1);

                        $status |= $this->serial->confFlowControl('none');

			$status |= $this->serial->deviceOpen();
		}
		
		return $status;
	}

	/*
		Заверешение сеанса обмена через порт
	*/

    public function serialExit() {

		$this->serial->deviceClose();                                                                                                                                          
    }

	/*
		Запись команды в порт и получение ответа
	*/

    private function sendSerial($serialData) {

		$this->result = '';
		$serialData .= "\r";

		$serialData = preg_replace("/{sid}/", $this->setId, $serialData);
        $serialData = preg_replace("/{data}/", $this->data, $serialData);
        //var_dump($serialData);
		$this->serial->sendMessage($serialData, $this->waitForReply); 
		
		$this->result = $this->serial->readPort();
    }

	/*
		Определение статуса отправленной команды
	*/

    private function isOK($res) {
		
		if (strpos($res, 'NG') === false) return true;
		 else return false;
    }

	/*
		Определение статуса отправленной команды (расширенный ответ)
	*/

    public function itemStatus($answer) {

		$results = new MyResult;

		$sp = preg_split("/ /", $answer);
		
		//echo "<br>-- sp --";
		//var_dump($sp);

		$results->raw = $answer;

		if ( isset($sp[2]) ) {
			if ( substr($sp[2], 0, 2) == 'OK' ) {
				$results->status = 'OK';
				$results->data = substr(preg_replace("/OK/", "", $sp[2]), 0, -1);
				if ( strlen($results->data) == 2 ) {
					$results->text = array_search($results->data, $this->params);
					$results->value = hexdec($results->data);
				} else if (strlen($results->data) == 6 ) {
					$results->text = $this->channels[hexdec(substr($results->data, 0, 4))].' - '. array_search(substr($results->data, 4, 2), $this->params);
				}
				return $results;
			} else {
				
				$results->status = 'NG';	
				$results->data = substr(preg_replace("/NG/", "", $sp[2]), 0, -1);
				
				return $results;
			}
		}
    }

	/*
		Формирование команды и ее отправка телевизору
	*/

    public function command($command, $data) {
	
		$command = strtolower($command);

		//var_dump($this->commands);
		//var_dump($this->params);

		if ( isset($this->commands[$command]) ) {
		
			if ( in_array($command, $this->rangecmd) ) {	
				$this->data = sprintf("%02x", $data);
				//echo "мы в rangecmd";
			} else {
				if ( in_array($command, $this->specialcmds) ) {
					$this->data = $data;
					//echo "мы в specialcmds";
				} else if ( isset($this->params[$data]) ) {
					$this->data = $this->params[$data];
					//echo "мы в основных командах";
					//var_dump($this->data);
				} else {
					return 'Неверный параметр команды ' . $data;
				}
			}
			//var_dump($this->commands[$command]);

			$this->sendSerial($this->commands[$command]);
			
			if ($this->result != '') {
				$this->status = $this->isOK($this->result);
				if ( !$this->confirm ) {
					return $this->result;
				} else {
					return $this->itemStatus($this->result);
				}	
			} else {
				return 'Нет ответа от ТВ.';
			}
		} else {
			//var_dump($command);
			return 'Неверная команда.';
		}	
    }

	/*
		Включение определенного канала по его названию с помощью
		имитации набора цифровых кнопок пульта
	*/

    public function channelSelect($channelName) {

		if ( in_array($channelName, $this->channels) ) {
			$k = array_search($channelName, $this->channels);
			$l = strlen("$k");

			if ( $l == 1 ) {
				$this->command('remote control key',"rc$k");
                $this->command('remote control key','rcok');                                                                                                  
			} else {
				$ks = "$k";
				for( $a = 0; $a < $l; $a++ ) {
					$this->command('remote control key', 'rc'.substr($ks, $a, 1));
				}
				$this->command('remote control key','rcok');
			}
		}
    }

	/*
		Запрос текущего статуса питания ТВ.
		Возвращает 01, если включен, и 00, если выключен.
	*/

    public function getPower() {
	
		$this->confirm = true;
		$this->data = "ff";
		$command = "ka {sid} {data}";
			
		$this->sendSerial($command);
		
		if ( $this->result !== '' ) {
			$this->status = $this->isOK($this->result);
			$res = $this->itemStatus($this->result);
			if ( $this->status ) return $res->data;
			 else return false;
		} else return false;
		
    }
	
	/*
		Установка статуса питания ТВ.
		Принимает '00' для выключения ТВ, '01' - для включения.
		Возвращает TRUE, если команда успешно выполнена, иначе FALSE.
	*/

    public function setPower($cmd = '00') {
	
		$this->confirm = true;
		$this->data = $cmd;
		$command = "ka {sid} {data}";
			
		$this->sendSerial($command);
		
		if ( $this->result !== '' ) {
			$this->status = $this->isOK($this->result);
			$res = $this->itemStatus($this->result);
			if ( $this->status && $res->data == $cmd ) return true;
			 else return false;
		} else return false;
		
    }

	/*
		Запрос текущего уровня громкости.
		Возвращает громкость (от 0 до 100) или FALSE, если ошибка.
	*/

    public function getVolume() {
	
		$this->confirm = true;
		$this->data = "ff";
		$command = "kf {sid} {data}";
			
		$this->sendSerial($command);
		
		if ( $this->result !== '' ) {
			$this->status = $this->isOK($this->result);
			$res = $this->itemStatus($this->result);
			if ( $this->status ) return hexdec($res->data);
			 else return false;
		} else return false;
		
    }

	/*
		Установка громкости ТВ.
		Принимает требуемую величину громкости - от 0 до 100.
		Возвращает TRUE, если команда успешно выполнена, иначе FALSE.
	*/

    public function setVolume($vol = 10) {
	
		$this->confirm = $confirm;
		$volHex = dechex($vol);
		$this->data = $volHex;
		$command = "kf {sid} {data}";
			
		$this->sendSerial($command);
		
		if ( $this->result !== '' ) {
			$this->status = $this->isOK($this->result);
			$res = $this->itemStatus($this->result);
			if ( $this->status && $res->data == $volHex ) return true;
			 else return false;
		} else return false;
		
    }
	
    /*
		Запрос текущего ТВ-канала кабельном цифровом вещании.
		Возвращает номер канала или FALSE, если ошибка.
		Диапазон каналов: от 0 до 9999.
	*/

    public function getChannelCDTV() {
	
		$this->confirm = true;
		$this->data = "ff ff 90";
		$command = "ma {sid} {data}";
			
		$this->sendSerial($command);
		//$this->result = "a 01 OK2af0x";
		if ( $this->result !== '' ) {
			$this->status = $this->isOK($this->result);
			$res = $this->itemStatus($this->result);
			if ( $this->status ) {
				$ch = substr($res->data, 0, -2);
				return hexdec($ch);
			}
			 else return false;
		} else return false;
		
    }

	/*
		Включение определенного канала для кабельного цифрового вещания
		по его названию согласно данным из channels.csv.
		Принимает название ТВ-канала.
		Возвращает TRUE, если команда успешно выполнена, иначе FALSE.
	*/

    public function setChannelCDTV($channelName) {

		if ( in_array($channelName, $this->channels) ) {
			
			$ch = array_search($channelName, $this->channels);		
			$chHex = str_pad(dechex($ch), 4, '0', STR_PAD_LEFT);
			//$chHex = strtoupper($chHex);
			
			$this->confirm = true;
			$this->data = substr($chHex, 0, 2) . ' ' . substr($chHex, 2, 3) . ' 90';
			$command = "ma {sid} {data}";
			
			$this->sendSerial($command);
			
			if ( $this->result !== '' ) {
				$this->status = $this->isOK($this->result);
				$res = $this->itemStatus($this->result);
				if ( $this->status && (substr($res->data, 0, -2) == $chHex) ) return true;
					else return false;
			} else return false;
		}
		else return false;
    }
	
	/*
		Получает номер канала по его названию согласно данным из channels.csv.
		Принимает название ТВ-канала.
		Возвращает номер ТВ-канала, если команда успешно выполнена, иначе FALSE.
	*/
	
	public function getChannelNumberByName($channelName) {
		
		if ( in_array($channelName, $this->channels) ) {
			
			$chNum = array_search($channelName, $this->channels);		
			return $chNum;
		}
		else return false;
		
	}

	/*
		Получает название канала по его номеру согласно данным из channels.csv.
		Принимает номер ТВ-канала.
		Возвращает название ТВ-канала, если команда успешно выполнена, иначе FALSE.
	*/
	
	public function getChannelNameByNumber($channelNumber) {
		
		$chName = $this->channels[$channelNumber];
		if ( isset($chName) ) return $chName;
		 else return false;
		
	}
	
	/*
		Включение 3D.
		Принимает параметр, определяющий режим 3D:
			00 - вертикальная стереопара (over under);
			01 - горизонтальная стереопара (side by side).
		Возвращает TRUE, если команда успешно выполнена, иначе FALSE.
	*/

    public function on3D($mode = '00') {
	
		$this->confirm = true;
		$this->data = '00 ' . $mode . ' 00 00';
		$command = "xt {sid} {data}";
			
		$this->sendSerial($command);
		
		if ( $this->result !== '' ) {
			$this->status = $this->isOK($this->result);
			$res = $this->itemStatus($this->result);
			if ( $this->status ) return true;
			 else return false;
		} else return false;
		
    }
	
	/*
		Выключение 3D.
		Возвращает TRUE, если команда успешно выполнена, иначе FALSE.
	*/

    public function off3D() {
	
		$this->confirm = true;
		$this->data = '01 00 00 00';
		$command = "xt {sid} {data}";
			
		$this->sendSerial($command);
		
		if ( $this->result !== '' ) {
			$this->status = $this->isOK($this->result);
			$res = $this->itemStatus($this->result);
			if ( $this->status ) return true;
			 else return false;
		} else return false;
		
    }
	
	/*
		Запрос текущего входа ТВ.
		Возвращает код входа или FALSE, если ошибка.
	*/

    public function getInput() {
	
		$this->confirm = true;
		$this->data = "ff";
		$command = "xb {sid} {data}";
			
		$this->sendSerial($command);
		
		if ( $this->result !== '' ) {
			$this->status = $this->isOK($this->result);
			$res = $this->itemStatus($this->result);
			if ( $this->status ) return $res->data;
			 else return false;
		} else return false;
		
    }

	/*
		Формирование массива команд и массива ТВ-каналов 
		на основе файлов data.csv и channels.csv
	*/

    public function initFiles() {

        $file = './templates/app_lgtvrs232/data.csv';
        $lines = file($file);

        foreach ( $lines as $linen => $line ) {

                $sp = preg_split("/,/", $line);
                $name = $sp[0];
                $hex  = $sp[1];
                $this->params[$name] = trim($hex);
        }

		$file = './templates/app_lgtvrs232/channels.csv';
		$lines = file($file);

		foreach ( $lines as $linen => $line ) {
			$sp = preg_split("/,/", $line);
			$channo = $sp[0];
			$channa = $sp[1];
			$this->channels[$channo] = trim("$channa");
		}
	}
}
?>
