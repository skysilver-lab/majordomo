<?php
/* 
*	Цикл контроля подсистемы электропитания Cubietruck.
*
*	Примечание:
*		Чтобы дать пользователю www-data возможность читать данные из регистров i2c-устройств,
*		необходимо www-data включить в группу i2c (файл /etc/group).
*
*	Copyright (C) 2014-2015 Agaphonov Dmitri aka skysilver [mailto:skysilver.da@gmail.com]
*/

chdir(dirname(__FILE__).'/../');

include_once("./config.php");
include_once("./lib/loader.php");
include_once("./lib/my.class.php");

set_time_limit(0);

// Параметры из datasheet на AXP209
const BAT_U_SCALE = 1.1;			// Battery Voltage 0mV 1.1mV 4.5045V
const TEMP_SCALE = 0.1;				// Internal temperature -144.7 C 0.1C 264.8C
const APS_SCALE = 1.4;				// APS voltage 0mV 1.4mV 5.733V
const VLSB = 1.1;					// voltage LSB is 1.1mV
const CLSB = 0.5;					// Current LSB is 0.5mA
const ADCSR = 100; 					// Refer to REG84H setting for ADC sample rate (100Hz)
$Pbat_k = 2*VLSB*CLSB/1000;			// Коэффициент для вычисления мощности АКБ

// Команда на чтение регистров AXP209
const CMD = "/usr/sbin/i2cget -y -f 0 0x34 ";

$db = new mysql(DB_HOST, '', DB_USER, DB_PASSWORD, DB_NAME); 
 
include_once("./load_settings.php");

$checked_time = 0;

echo date("Y-m-d H:i:s ") . " running " . basename(__FILE__) . "\n";

while(1) 
{
   // Если прошло 10 сек., то запрашиваем данные и отправляем их в метод объекта.
   if (time() - $checked_time > 10) 
    {
		$checked_time = time();
	    setGlobal((str_replace('.php', '', basename(__FILE__))).'Run', time(), 1);

		// Контроль параметров батареи
		$presentBAT = mb_substr(file_get_contents('/sys/class/power_supply/battery/present'), 0, -1);						// наличие батареи [1 = подключена/ 0 = отключена]
		$onlineBAT = mb_substr(file_get_contents('/sys/class/power_supply/battery/online'), 0, -1);							// питание от батареи или нет [1/0]
		$statusBAT = mb_substr(file_get_contents('/sys/class/power_supply/battery/status'), 0, -1);							// текущий статус батареи [Full/Charge/Discharge]
		$uBAT = (int)TwoRegsToDec(shell_exec(CMD.'0x78'),shell_exec(CMD.'0x79'))*BAT_U_SCALE;
		$uBAT = number_format($uBAT/1000,3,'.','');																			// напряжение на батарее [В]
		$iBAT = number_format(file_get_contents('/sys/class/power_supply/battery/current_now')/1000,1,'.',''); 				// ток при работе от батареи [мА]
		
		$capBATperc = base_convert(shell_exec(CMD.'0xb9'), 16, 10);															// текущая емкость батареи [0-100%]		
		$pBAT = $Pbat_k*(int)ThreeRegsToDec(shell_exec(CMD.'0x70'),shell_exec(CMD.'0x71'),shell_exec(CMD.'0x72'));			// мощность, потребляемая при работе от АКБ [мВт]
		$CCCV = FourRegsToDec(shell_exec(CMD.'0xb0'),shell_exec(CMD.'0xb1'),shell_exec(CMD.'0xb2'),shell_exec(CMD.'0xb3'));	// вспомогательный коэффициент (счетчик энергии заряда)
		$DCCV = FourRegsToDec(shell_exec(CMD.'0xb4'),shell_exec(CMD.'0xb5'),shell_exec(CMD.'0xb6'),shell_exec(CMD.'0xb7'));	// вспомогательный коэффициент (счетчик энергии разряда)
		$capBATmah = 65536*CLSB*((int)$CCCV-(int)$DCCV)/3600/ADCSR;
		$capBATmah = number_format($capBATmah,2,'.','');																	// емкость батареи [мАч]

		// Контроль параметров сетевого источиника питания (СИП)
		$presentAC = mb_substr(file_get_contents('/sys/class/power_supply/ac/present'), 0, -1);					// наличие СИП [1/0]
		$onlineAC = mb_substr(file_get_contents('/sys/class/power_supply/ac/online'), 0, -1);					// питание от СИП или нет [1/0]
		$uAC = number_format(file_get_contents('/sys/class/power_supply/ac/voltage_now')/1000000,3,'.','');		// напряжение СИП [В]
		$iAC = number_format(file_get_contents('/sys/class/power_supply/ac/current_now')/1000,1,'.','');		// ток при работе от СИП [мА]
		
		// Дополнительные параметры	
		$tempAXP = ((int)TwoRegsToDec(shell_exec(CMD.'0x5e'),shell_exec(CMD.'0x5f'))-1447)*TEMP_SCALE;		
		$tempAXP = number_format($tempAXP,1,'.','');															// температура контроллера заряда AXP209
	
		// Запускаем метод и передаем ему все собранные параметры для дальнейшей обработки
		callMethod('Cubietruck.getPowerStatus',array("uAC"=>$uAC,"iAC"=>$iAC,"tempAXP"=>$tempAXP,"uBAT"=>$uBAT,"iBAT"=>$iBAT,"capBATmah"=>$capBATmah,"capBATperc"=>$capBATperc,"onlineAC"=>$onlineAC,"pBAT"=>$pBAT,"onlineBAT"=>$onlineBAT,"presentAC"=>$presentAC,"presentBAT"=>$presentBAT,"statusBAT"=>$statusBAT));
	}

   if (file_exists('./reboot')) 
	{
		$db->Disconnect();
		echo date("Y-m-d H:i:s ") . "Stopping by command REBOOT " . basename(__FILE__) . "\n";
		exit;
	}
   sleep(1);
}

$db->Disconnect();

echo date("Y-m-d H:i:s ") . "Unexpected stopping " . basename(__FILE__) . "\n";

DebMes("Unexpected close of cycle: " . basename(__FILE__));

?>
