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

include_once(DIR_MODULES.'scripts/scripts.class.php');

$ctl = new control_modules();
$sc=new scripts();
$checked_time=0;

echo date("Y-m-d H:i:s") . " running " . basename(__FILE__) . "\n"; 

while(1) 
{
   if (time()-$checked_time>5) {
    $checked_time=time();
    setGlobal((str_replace('.php', '', basename(__FILE__))).'Run', time(), 1);
   }
   runScheduledJobs();
   $sc->checkScheduledScripts();

   if (file_exists('./reboot') || $_GET['onetime']) 
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
