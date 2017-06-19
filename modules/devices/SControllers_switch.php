<?php 

$currentStatus=$this->getProperty('status');
if ($currentStatus) {
 //$this->callmethodSafe('turnOff');
 $this->callmethod('turnOff');
} else {
 //$this->callmethodSafe('turnOn');
 $this->callmethod('turnOn');
}
