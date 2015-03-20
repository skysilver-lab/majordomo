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

$checked_time=0;

if ($_GET['once']) {
 echo date("Y-m-d H:i:s") . " running " . basename(__FILE__) . " once ";
 $last_run=getGlobal((str_replace('.php', '', basename(__FILE__))).'Run');
 if ((time()-$last_run)>5*60) {
  setGlobal((str_replace('.php', '', basename(__FILE__))).'Run', time(), 1);
  cycleBody();
 }
 echo "OK\n";
} else {
 echo date("Y-m-d H:i:s") . " running " . basename(__FILE__) . "\n";
 while(1) {
   if (time()-$checked_time>5) {
    setGlobal((str_replace('.php', '', basename(__FILE__))).'Run', time(), 1);
    $checked_time=time();
    cycleBody();
   }
   if (file_exists('./reboot') || $_GET['onetime']) 
   {
      $db->Disconnect();
      exit;
   }
   sleep(1);
 }
 $db->Disconnect(); 

 echo date("Y-m-d H:i:s ") . "Unexpected stopping " . basename(__FILE__) . "\n";

 DebMes("Unexpected close of cycle: " . basename(__FILE__));
}

 function cycleBody() {
   // check main system states
   $objects = getObjectsByClass('systemStates');
   $total   = count($objects);
   for($i=0;$i<$total;$i++) 
   {
      $old_state = getGlobal($objects[$i]['TITLE'] . '.stateColor');
      callMethod($objects[$i]['TITLE'] . '.checkState');
      $new_state = getGlobal($objects[$i]['TITLE'] . '.stateColor');
  
      if ($new_state!=$old_state) 
      {
         echo date("Y-m-d H:i:s ") . $objects[$i]['TITLE'] . " state changed to " . $new_state . "\n";
         $params=array('STATE'=>$new_state);
         callMethod($objects[$i]['TITLE'] . '.stateChanged', $params);
      }
   }  
 }

?>
