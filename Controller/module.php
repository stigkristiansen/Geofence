<?

require_once(__DIR__ . "/../logging.php");

class GeofenceController extends IPSModule {
    
    public function Create(){
		$instances = IPS_GetInstanceListByModuleID('{C5271BF2-DDC9-4EA7-8467-A8C645500263}');
		$count = count($instances);
		if($count>0 && $instances[0]!=$this->InstanceID) {
			throw new Exception('The Geofence Controller already exists!');
		}

        parent::Create();
        
        $this->RegisterPropertyBoolean ("Log", false );

		$this->RegisterPropertyString("Username", "");
		$this->RegisterPropertyString("Password", "");
		$this->RegisterPropertyInteger("ArrivalScript1", 0);
		$this->RegisterPropertyInteger("ArrivalScript2", 0);
		$this->RegisterPropertyInteger("DepartureScript1", 0);
		$this->RegisterPropertyInteger("DepartureScript2", 0);
		$this->RegisterPropertyBoolean("ArrivalScript1Update", false);
		$this->RegisterPropertyBoolean("ArrivalScript2Update", false);
		$this->RegisterPropertyBoolean("DepartureScript1Update", false);
		$this->RegisterPropertyBoolean("DepartureScript2Update", false);
	}

    public function ApplyChanges(){
        parent::ApplyChanges();
		
		$ident="geofence".$this->InstanceID;
		$name="Geofence".$this->InstanceID."Hook";
		$criptId = $this->RegisterScript($ident, $name, "<?\n//Do not modify!\nrequire_once(IPS_GetKernelDirEx().\"scripts/__ipsmodule.inc.php\");\nrequire_once(\"../modules/Geofence/Controller/module.php\");\n(new GeofenceController(".$this->InstanceID."))->HandleWebData();\n?>");
		$this->RegisterWebHook("/hook/".$ident, $scriptId);
		
		$this->CreateVariable($this->InstanceID, "Presence", "Presence", 0, "~Presence");
		$this->EnableAction("Presence");
		
    }

	public  function GetConfigurationForm ( )  { 
		$form = json_decode(file_get_contents(__DIR__ . '/form.json'), true);

		$userInstanceIds = IPS_GetInstanceListByModuleID ('{C4A1F68D-A34E-4A3A-A5EC-DCBC73532E2C}');

		$users = [];
		foreach($userInstanceIds as $userInstanceId) {
			$users[] = ['Username' => IPS_GetName($userInstanceId), 'Enabled' => IPS_GetProperty($userInstanceId, 'Enabled'), 'InstanceId' => $userInstanceId];
		}

		$form['elements'][1]['items'][9]['values'] = $users;

		return json_encode($form);
	}

    public function HandleWebData() {
		//IPS_LogMessage("Debug", "Inside HandleWebData");
		
		$log = new Logging($this->ReadPropertyBoolean("Log"), IPS_Getname($this->InstanceID));
		
		$username = IPS_GetProperty($this->InstanceID, "Username");
		$password = IPS_GetProperty($this->InstanceID, "Password");
		
		//IPS_LogMessage("User is ".$username);
		
		if($username!="" || $password!="") {
			if(!isset($_SERVER['PHP_AUTH_USER']))
				$_SERVER['PHP_AUTH_USER'] = "";
			if(!isset($_SERVER['PHP_AUTH_PW']))
				$_SERVER['PHP_AUTH_PW'] = "";

			if(($_SERVER['PHP_AUTH_USER'] != $username) || ($_SERVER['PHP_AUTH_PW'] != $password)) {
				header('WWW-Authenticate: Basic Realm="Geofence"');
				header('HTTP/1.0 401 Unauthorized');
				echo "Authorization required to access Symcon and Geofence";
				$log->LogMessage("Authentication needed or invalid username/password!");
				return;
			} else
				$log->LogMessage("You are authenticated!");
		} else
			$log->LogMessage("No authentication needed");
		
		$username="";
		$password="";
		
		if(!$this->Lock("HandleWebData")) {
			$log->LogMessage("Waiting for unlock timed out!");
			return;
		}
		
		$log->LogMessage("The controller is locked");
		
		$cmd="";
		$userId="";
		
		$log->LogMessage(print_r($_GET, true));
		
		if (array_key_exists('cmd', $_GET))
			$cmd=strtolower($_GET['cmd']);
				
		if (array_key_exists('uid', $_GET))
			$userId=strtolower($_GET['uid']);
		elseif (array_key_exists('id', $_GET))
			$userId=strtolower($_GET['id']);
		
		if($cmd!="" && $userId!="") {
			$log->LogMessage("Received the command \"".$cmd."\" for user \"".IPS_GetName($userId)."\"");
			
			$children = IPS_GetChildrenIDs($this->InstanceID);
			
			$size = sizeof($children);
			$userExists = false;
			for($x=0;$x<$size;$x++) {
				if($children[$x]==$userId) {
					$userExists=true;
					break;
				}
			}
			
			if($userExists) {
				switch($cmd) {
					case "arrival1":
						$presence = true;
						$scriptProperty = "ArrivalScript1";
						break;
					case "arrival2":
						$presence = true;
						$scriptProperty = "ArrivalScript2";
						break;
					case "departure1":
						$presence = false;
						$scriptProperty = "DepartureScript1";
						break;
					case "departure2":
						$presence = false;
						$scriptProperty = "DepartureScript2";
						break;
					default:
						$log->LogMessage("Invalid command!");
						$this->Unlock("HandleWebData");
						return;
				}
				
				$presenceId = $this->CreateVariable($userId, "Presence", "Presence", 0, "~Presence");
				$commonPresenceId = $this->CreateVariable($this->InstanceID, "Presence", "Presence", 0, "~Presence");
				$lastCommonPresence=GetValue($commonPresenceId);
				$updatePresence = $this->ReadPropertyBoolean($scriptProperty."Update");
				
				if($updatePresence) {
					$log->LogMessage("Updated Presence for user ".IPS_GetName($userId)." to \"".$this->GetProfileValueName(IPS_GetVariable($presenceId)['VariableCustomProfile'], $presence)."\"");
					SetValue($presenceId, $presence);
				}
				
				$commonPresence = false;
				$users=IPS_GetInstanceListByModuleID("{C4A1F68D-A34E-4A3A-A5EC-DCBC73532E2C}");
				$size=sizeof($users);
				for($x=0;$x<$size;$x++){
					if(IPS_GetParent($users[$x])==$this->InstanceID) {
						$presenceId=$this->CreateVariable($users[$x], "Presence", "Presence", 0, "~Presence");
						//$log->LogMessage("Checking ".IPS_GetName($users[$x]));
						if(GetValue($presenceId)) {
							$commonPresence = true;
							break;
						}
					}
				}
				
				if($updatePresence) {
					SetValue($commonPresenceId, $commonPresence);
					$log->LogMessage("Updated Common Presence to \"".$this->GetProfileValueName(IPS_GetVariable($commonPresenceId)['VariableCustomProfile'], $commonPresence)."\"");
				} else
					$log->LogMessage("Presence update is not enabled for this command.");
				
				$scriptId = $this->ReadPropertyInteger($scriptProperty);
				if($scriptId>0) { 
					$log->LogMessage("The script is ".IPS_GetName($scriptId));
					
					$runScript = true;
					if($updatePresence && $presence==$lastCommonPresence) {
						$runScript = false;
						$message = "Old Presence and new Presence is equal. Skipping script";
					}
									
					if($updatePresence && !$presence && $commonPresence) {
						$runScript = false;
						$message="Not all users have sent a departure command. Skipping script";
					}
					
					if($runScript) {					
						if(array_key_exists('delay', $_GET) && is_numeric($_GET['delay'])) {
							$delay = (int)$_GET['delay'];
							if($delay>0) {
								$log->LogMessage("Running script with ".$delay." seconds delay...");
								$scriptContent = IPS_GetScriptContent($scriptId);
								$scriptModification =  "//Do not modify this line or the line below\nIPS_SetScriptTimer(\$_IPS['SELF'],0);\n//Do not modify this line or the line above\n";
								if(strripos($scriptContent, $scriptModification)===false) {
									$splitPos = strpos($scriptContent, "?>");
									$scriptPart1 = substr($scriptContent, 0, $splitPos);
									$scriptPart2 = substr($scriptContent, $splitPos);
									$scriptContent = $scriptPart1.$scriptModification.$scriptPart2;
									IPS_SetScriptContent($scriptId, $scriptContent);
								}
								IPS_SetScriptTimer($scriptId, $delay);
							} else {
								$log->LogMessage("Running script...");
								IPS_RunScript($scriptId);
							}
						} else {
							$log->LogMessage("Running script...");
							IPS_RunScript($scriptId);
						}
					} else
						$log->LogMessage($message);				
				} else 
					$log->LogMessage("No script is selected for this command");
				
				echo "OK";
			
			} else
				$log->LogMessage("Unknown user");
			
		} else
			$log->LogMessage("Invalid or missing \"id\" or \"cmd\" in URL");
		
		$this->Unlock("HandleWebData");
    }
	
	public function UnregisterUser(string $Username) {
		$ident = strtolower(preg_replace("/[^a-zA-Z0-9]+/", "", $Username));
		$id = IPS_GetObjectIDByIdent($ident, $this->InstanceID);
		if($id!==false) {
			$vId = IPS_GetObjectIDByIdent("Presence", $id);
			if($vId!==false)
				IPS_DeleteVariable($vId);
			return IPS_DeleteInstance($id);
		}
		
		$id = IPS_GetInstanceIDByName($Username, $this->InstanceID);
		if($id!==false) {
			$vId = IPS_GetObjectIDByName("Presence", $id);
			if($vId!==false)
				IPS_DeleteVariable($vId);
			return IPS_DeleteInstance($id);
		}
		
		return false;
	}

	public function RegisterUser(string $Username) {
		$ident = strtolower(preg_replace("/[^a-zA-Z0-9]+/", "", $Username));
		$id = IPS_GetObjectIDByIdent($ident, $this->InstanceID);
		if($id===false) {
			$id = IPS_GetInstanceIDByName($Username, $this->InstanceID);
			if($id===false) {
				$id = IPS_CreateInstance("{C4A1F68D-A34E-4A3A-A5EC-DCBC73532E2C}");
				IPS_SetName($id,$Username);
				IPS_SetParent($id,$this->InstanceID);
				IPS_SetIdent($id, $ident);
				return true;
			} else {
				IPS_SetIdent($id, $ident);
				
				return true;
			}
		} 
						
		return false;
	}
	
	private function GetProfileValueName($Profile, $Value) {
		$associations = IPS_GetVariableProfile($Profile)['Associations'];
		
		$size=sizeof($associations);
		for($x=0;$x<$size;$x++) {
		   if($associations[$x]['Value']==$Value) {
				return $associations[$x]['Name'];
			}
		}

		return "Invalid";
	}

    private function RegisterWebHook($Hook, $TargetId) {
		$id = IPS_GetInstanceListByModuleID("{015A6EB8-D6E5-4B93-B496-0D3F77AE9FE1}");

		if(sizeof($id)) {
			$hooks = json_decode(IPS_GetProperty($id[0], "Hooks"), true);

			$hookExists = false;
			$numHooks = sizeof($hooks);
			for($x=0;$x<$numHooks;$x++) {
				if($hooks[$x]['Hook']==$Hook) {
					if($hooks[$x]['TargetID']==$TargetId)
						return;
				$hookExists = true;
				$hooks[$x]['TargetID']= $TargetId;
					break;
				}
			}
				
			if(!$hookExists)
			   $hooks[] = Array("Hook" => $Hook, "TargetID" => $TargetId);
			   
			IPS_SetProperty($id[0], "Hooks", json_encode($hooks));
			IPS_ApplyChanges($id[0]);
		}
    }
		
	private function CreateVariable($Parent, $Ident, $Name, $Type, $Profile = "") {
		$id = @IPS_GetObjectIDByIdent($Ident, $Parent);
		if($id === false) {
			$id = IPS_CreateVariable($Type);
			IPS_SetParent($id, $Parent);
			IPS_SetName($id, $Name);
			IPS_SetIdent($id, $Ident);
			if($Profile != "")
				IPS_SetVariableCustomProfile($id, $Profile);
		}
		
		return $id;
	}
	
	private function Lock($Ident) {
        $log = new Logging($this->ReadPropertyBoolean("Log"), IPS_Getname($this->InstanceID));
		for ($x=0;$x<100;$x++)
        {
            if (IPS_SemaphoreEnter("GEO_".(string)$this->InstanceID.(string)$Ident, 1)){
                return true;
            }
            else {
  				if($x==0)
					$log->LogMessage("Waiting for controller to unlock...");
				IPS_Sleep(mt_rand(1, 5));
            }
        }
        return false;
    }

    private function Unlock($Ident) {
        IPS_SemaphoreLeave("GEO_".(string)$this->InstanceID.(string)$Ident);
		$log = new Logging($this->ReadPropertyBoolean("Log"), IPS_Getname($this->InstanceID));
		$log->LogMessage("The controller is unlocked");
    }
		
}

?>
