<?php
/*
* Свои дополнительные функции
*/

// Возвращает оставшееся время в секундах работы таймера по его имени. function timeOutResidue($title) {
// Если таймера нет, вернет 0
function timeOutResidue($title) {
  $timerId=timeOutExists($title);
  if ($timerId) {
   $timer_job=SQLSelectOne("SELECT UNIX_TIMESTAMP(RUNTIME) as TM FROM jobs WHERE ID='".$timerId."'");
   $diff=(int)$timer_job['TM']-time(); // получаем время в секундах, оставшееся до запланированного срабатывания таймера
    return $diff;
   } else {
    return 0;
   }
 }

// Получить имя класса по имени объекта
function getClassNameByObject($title) {
 $obj=getObject($title);
 $class=SQLSelectOne("SELECT * FROM classes WHERE ID='".(int)$obj->class_id."'"); 
 if (is_array ($class )) {
  return $class['TITLE'];
 } else {
  return 'error';
 }
}

// Получить ID свойства объекта по имени этого объекта и имени свойства (только для свежих версий MajorDoMo)
function getPropertyID ($prop_name) {
        $arr_s = SQLSelectOne("SELECT * FROM pvalues WHERE PROPERTY_NAME='".$prop_name."'");
        if (is_array ($arr_s)) {
                $value_id = $arr_s['ID'];
                return $value_id;
        } else {
                return 'error';
        }
}

// Получить ID свойства объекта по имени этого объекта и имени свойства
function getPropertyIDByObject($obj_title, $prop_title) {
 $arr_s = SQLSelectOne("SELECT * FROM objects WHERE TITLE='".$obj_title."'");
 if (is_array ($arr_s)) {
	$obj_id = $arr_s['ID'];
	$class_id = $arr_s['CLASS_ID'];
	$arr_s = SQLSelectOne("SELECT * FROM properties WHERE TITLE='".$prop_title."' AND CLASS_ID='".$class_id."'");
	if (is_array ($arr_s)) {
		$prop_id = $arr_s['ID'];
		$arr_s = SQLSelectOne("SELECT * FROM pvalues WHERE OBJECT_ID='".$obj_id."' AND PROPERTY_ID='".$prop_id."'");
		if (is_array ($arr_s)) {
			$value_id = $arr_s['ID'];
			return $value_id;
		} else {
			return 'error';
		}
	} else {
			return 'error';
		}
 } else {
			return 'error';
		}
}
// Получить детали события по его имени
function registeredEventDetails($eventName) {
 $even=SQLSelectOne("SELECT * FROM events WHERE EVENT_NAME='".$eventName."'"); 
 if (is_array ($even )) {
  return $even['DETAILS'];
 } else {
  return false;
 }
}

// Преобразование 2-х двоичных чисел (регистров i2c) в одно десятичное число
function TwoRegsToDec($reg_1, $reg_2)
{

	$reg_1bin = base_convert($reg_1, 16, 2);
	$reg_2bin = base_convert($reg_2, 16, 2);
	$reg_2binLow = substr($reg_2bin, -4);
	$valBin = number_pad($reg_1bin,8).number_pad($reg_2binLow,4);
	$valDec = base_convert($valBin, 2, 10);
    return $valDec;
}

// Преобразование 3-х двоичных чисел (регистров i2c) в одно десятичное число
function ThreeRegsToDec($reg_1, $reg_2, $reg_3)
{
	$reg_1bin = base_convert($reg_1, 16, 2);
	$reg_2bin = base_convert($reg_2, 16, 2);
	$reg_3bin = base_convert($reg_3, 16, 2);
	$valBin = number_pad($reg_1bin,8).number_pad($reg_2bin,8).number_pad($reg_3bin,8);
	$valDec = base_convert($valBin, 2, 10);
    return $valDec;
}

// Преобразование 4-х двоичных чисел (регистров i2c) в одно десятичное число
function FourRegsToDec($reg_1, $reg_2, $reg_3, $reg_4)
{
	$reg_1bin = base_convert($reg_1, 16, 2);
	$reg_2bin = base_convert($reg_2, 16, 2);
	$reg_3bin = base_convert($reg_3, 16, 2);
	$reg_4bin = base_convert($reg_4, 16, 2);
	$valBin = number_pad($reg_1bin,8).number_pad($reg_2bin,8).number_pad($reg_3bin,8).number_pad($reg_4bin,8);
	$valDec = base_convert($valBin, 2, 10);
    return $valDec;
}

// Функция дополняет нолями двоичное число (например, из 1101 делает 00001101, если n = 8) 
function number_pad($number,$n)
{
	return str_pad((int) $number,$n,"0",STR_PAD_LEFT);
}

// Склонение числительных
// string - само число
// ch1 - час, день, год, месяц
// ch2 - часа, дня, года, месяца
// ch3 - часов, дней, лет, месяцев
function chti($string, $ch1, $ch2, $ch3)
{
	$ff=Array('0','1','2','3','4','5','6','7','8','9');
	if(substr($string,-2, 1)==1 AND strlen($string)>1) $ry=array("0 $ch3","1 $ch3","2 $ch3","3 $ch3" ,"4 $ch3","5 $ch3","6 $ch3","7 $ch3","8 $ch3","9 $ch3");
	else $ry=array("0 $ch3","1 $ch1","2 $ch2","3 $ch2","4 $ch2","5 $ch3"," 6 $ch3","7 $ch3","8 $ch3"," 9 $ch3");
	$string1=substr($string,0,-1).str_replace($ff, $ry, substr($string,-1,1));
	
	return $string1;
}

// Преобразование десятичного числа (цены) в строку вида "ХХ руб. УУ коп."
// $format = {mini, medium} {р. к., руб. коп.}
function priceToRublesAndKopeck($price, $format='medium') {
	
	if ($format == 'mini') {
			$rub_u = " р."; $kop_u = " к.";
		}
	if ($format == 'medium') {
			$rub_u = " руб."; $kop_u = " коп.";
		}
	
	if ($price != 0) {
		// Округляем до сотых
		$price = number_format($price, 2, '.', '');
		$point = strpos($price, '.');
		// Отделяем рубли от копеек
		if ( !empty($point) ) {
			$rub = substr($price, 0, $point);
			$kop = substr($price, $point + 1);
		}
		// Формируем строку
		if ($rub == 0) $result = $kop.$kop_u;
			else $result = $rub.$rub_u." ".$kop.$kop_u;
		// Возвращаем результат
		return $result;
	}
	else {
		$result = "0".$rub_u." 0".$kop_u;
		return $result;
	}
}

// Возвращает последний день (число) текущего месяца
function getLastDayOfMonth() {
 	return date('t', time());
}

// Сортирует массив $data по полю $sortpath по возрастанию
// arraySort($list, 'created');
function arraySort(&$data, $sortpath) {
    
	$path = '[\''.implode('\'][\'', explode('/', $sortpath)).'\']';
    
	usort($data, function (&$lv, &$rv) use($path) {
        eval('$lp = $lv'.$path.';');
        eval('$rp = $rv'.$path.';');
        return ($lp < $rp) ? -1 : ($lp === $rp) ? 0 : 1;
    });
}

/*
	Удаляет каталог (с содержимым) с диска
*/
 
function removeDirectory($dir) {

	if ($objs = glob($dir."/*")) {
		foreach($objs as $obj) {
			is_dir($obj) ? removeDirectory($obj) : unlink($obj);
		}
    }
    rmdir($dir);
}
 
?>
