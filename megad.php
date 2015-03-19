<?
 include_once("./config.php");
 include_once("./lib/loader.php");
 $session=new session("prj");
 $db=new mysql(DB_HOST, '', DB_USER, DB_PASSWORD, DB_NAME); // connecting to database
 include_once("./load_settings.php");
 if (!headers_sent()) {
  header ("HTTP/1.0: 200 OK\n");
  header ('Content-Type: text/html; charset=utf-8');
 }

 $reclog = 2; // Писать логи отладки ( 0-нет, 1-только крит, 2-все )

 // Открыть лог если нужно
 if ($reclog) { $log = getLogger(__FILE__); }

 // Собрать все переданные параметры в строку для отправки в лог
 if ($reclog == 2 ) {
      $str = "";
      foreach ($params as $key=>$value) {
           if ($str != "") {$str.=", ";}
           $str .= $key."=".$value;
      }
      $log->trace('Got message from MegaDevice '.$str);
      $str = ""; //свободна
 }

 $objects = getObjectsByClass('MegaD'); 
 $megaD = null;
 
 // В начале ищем объект Меги по mdid
 if (isset($params['mdid'])) {
      foreach ($objects as $obj) {
           if (trim(getGlobal($obj['TITLE'].'.mdid')) == $params['mdid']) {
                $megaD = $obj;
                break;
           }
      }
 } else {
      if ($reclog) { $log->error('MegaDevice has not transmitted mdid. Try to search on IP'); }
 }
 
 // Если не нашли по mdid, ищем по IP
 if (!isset($megaD)) {
      // Получить IP адрес MegaD
      $ip = $_SERVER['REMOTE_ADDR'];
      if (isset($ip)) {
           foreach ($objects as $obj) {
                if (trim(getGlobal($obj['TITLE'].'.ipAddress')) == $ip) {
                     $megaD = $obj;
                     break;
                }
           }
      } else {
           if ($reclog) { $log->error('Cannot determinate remote IP address of megadevice!'); }
      }
 }

 // Запуск метода incomingMessage с передачей ему параметров,
 // или сообщение "Объект с нужным ID или IP не найден в классе MegaD" при неудаче.
 if (isset($megaD)) {
      callMethod($megaD['TITLE'].'.incomingMessage', $params);
 } else { 
      if ($reclog) { $log->error('Cannot find object of MegaD class with mdid = '. $params['mdid'].' or ip = '.$ip ); }
 }


 $session->save();
 $db->Disconnect(); // closing database connection
?>
