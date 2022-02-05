<?php


class GeofenceUser extends IPSModule {
    
    public function Create(){
        parent::Create();
                
		$this->RegisterPropertyBoolean ("Enabled", true);

		$this->RegisterVariableBoolean('Presence', 'Presence', '~Presence');
		//$this->EnableAction('Presence');
	}

    public function ApplyChanges(){
        parent::ApplyChanges();
    }

	public function GetURLs() {
		$instances = IPS_GetInstanceListByModuleID("{C5271BF2-DDC9-4EA7-8467-A8C645500263}");
		
		if(count($instances)>0) {
			$controller = $instances[0];

			$message = "/hook/geofence".$controller."?cmd=arrival1&id=".$this->InstanceID."\n";
			$message .= "/hook/geofence".$controller."?cmd=arrival2&id=".$this->InstanceID."\n";
			$message .= "/hook/geofence".$controller."?cmd=departure1&id=".$this->InstanceID."\n";
			$message .= "/hook/geofence".$controller."?cmd=departure2&id=".$this->InstanceID."\n\n";
			$message .= "The parameter \"delay\" (in seconds) is optional for all commands.";
		} else {
			$message = "The Geofence Controller is missing!";
		}
		
		return $message;
	}

	
}


