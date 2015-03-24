<?php

chdir(dirname(__FILE__).'/../');

include_once("./config.php");
include_once("./lib/loader.php");
include_once("./lib/threads.php");

set_time_limit(0);

// connecting to database
$db = new mysql(DB_HOST, '', DB_USER, DB_PASSWORD, DB_NAME); 

include_once("./load_settings.php");
include_once(DIR_MODULES."control_modules/control_modules.class.php");

$ctl = new control_modules();

$timerClass = SQLSelectOne("SELECT * FROM classes WHERE TITLE LIKE 'timer'");
$o_qry = 1;
 
if ($timerClass['SUB_LIST']!='') 
   $o_qry.=" AND (CLASS_ID IN (".$timerClass['SUB_LIST'].") OR CLASS_ID=".$timerClass['ID'].")";
else 
   $o_qry.=" AND 0";

$old_minute = date('i');
$old_hour   = date('h');
$old_date   = date('Y-m-d');

$checked_time=0;

echo date("Y-m-d H:i:s") . " running " . basename(__FILE__) . "\n";

while(1) 
{

   if (time()-$checked_time>5) {
    $checked_time=time();
    setGlobal((str_replace('.php', '', basename(__FILE__))).'Run', time(), 1);
   }

   $m  = date('i');
   $h  = date('h');
   $dt = date('Y-m-d');
  
   if ($m!=$old_minute) 
   {
      echo date("H:i:s ") . "new minute\n";
      $objects = SQLSelect("SELECT ID, TITLE FROM objects WHERE $o_qry");
      $total   = count($objects);
   
      for($i=0;$i<$total;$i++) 
      {
         echo $objects[$i]['TITLE'] . "->onNewMinute\n";
         getObject($objects[$i]['TITLE'])->raiseEvent("onNewMinute");
         getObject($objects[$i]['TITLE'])->setProperty("time", date('Y-m-d H:i:s'));
      }
   
      $old_minute=$m;
   }
  
   if ($h!=$old_hour) 
   {
      echo date("H:i:s ") . "new hour\n";
      $old_hour = $h;
      $objects  = SQLSelect("SELECT ID, TITLE FROM objects WHERE $o_qry");
      $total    = count($objects);
      
      for($i = 0; $i < $total; $i++)
         getObject($objects[$i]['TITLE'])->raiseEvent("onNewHour");
   }
   
   if ($dt != $old_date) 
   {
      echo date("Y-m-d H:i:s") . " new day\n";
      $old_date = $dt;
   }

   if (file_exists('./reboot') || $_GET['onetime']) 
   {
      $db->Disconnect();
      echo date("Y-m-d H:i:s ") . "Stopping by command REBOOT " . basename(__FILE__) . "\n";
      exit;
   }

   sleep(1);
}

echo date("Y-m-d H:i:s ") . "Unexpected stopping " . basename(__FILE__) . "\n";
DebMes("Unexpected close of cycle: " . basename(__FILE__));
?>
