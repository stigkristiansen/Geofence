<?

require_once(__DIR__ . "/../logging.php");

class GeofenceUser extends IPSModule {
    
    public function Create(){
        parent::Create();
        
        $this->RegisterPropertyBoolean ("log", false );

    
	}

    public function ApplyChanges(){
        parent::ApplyChanges();
		
		
		$this->RegisterVariableBoolean( "Presence", "Presence", "~Presence", false );
        
    }

	public GetURLs() {
		$message = "This is a test\nThis is line two...";
		return $message;
	}

	
}

?>
