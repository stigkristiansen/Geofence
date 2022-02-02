<?

require_once(__DIR__ . "/../logging.php");

class GeofenceUser extends IPSModule {
    
    public function Create(){
        parent::Create();
        
        $this->RegisterPropertyBoolean ("log", false );
		$this->RegisterPropertyBoolean ("Enabled", true);
    
	}

    public function ApplyChanges(){
        parent::ApplyChanges();
		
		
		$this->RegisterVariableBoolean( "Presence", "Presence", "~Presence", false );
		$this->EnableAction("Presence");
        
    }

	public function GetURLs() {
		$parent = IPS_GetParent($this->InstanceID);
		$allParents = IPS_GetInstanceListByModuleID("{C5271BF2-DDC9-4EA7-8467-A8C645500263}");
		
		$size=sizeof($allParents);
		$foundParent = false;
		for($x=0;$x<$size;$x++) {
			if($allParents[$x]==$parent) {
				$foundParent = true;
				break;
			}
		}
		
		if($foundParent) {
			$message = "/hook/geofence".$parent."?cmd=arrival1&id=".$this->InstanceID."\n";
			$message .= "/hook/geofence".$parent."?cmd=arrival2&id=".$this->InstanceID."\n";
			$message .= "/hook/geofence".$parent."?cmd=departure1&id=".$this->InstanceID."\n";
			$message .= "/hook/geofence".$parent."?cmd=departure2&id=".$this->InstanceID."\n\n";
			$message .= "The parameter \"delay\" (in seconds) is optional for all commands.";
		} else
			$message = "The user does not have a Geofence Controller as parent!";
		
		return $message;
	}

	
}

?>
