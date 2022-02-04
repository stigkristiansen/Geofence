<?php

class GeofenceController extends IPSModule {
    
    public function Create(){
		$count = count(IPS_GetInstanceListByModuleID('{C5271BF2-DDC9-4EA7-8467-A8C645500263}'));
		if($count==2) {
			echo 'The Geofence Controller already exists!';
			return;
		}

        parent::Create();
        
        $this->RegisterPropertyString('Username', '');
		$this->RegisterPropertyString('Password', '');
		$this->RegisterPropertyInteger('ArrivalScript1', 0);
		$this->RegisterPropertyInteger('ArrivalScript2', 0);
		$this->RegisterPropertyInteger('DepartureScript1', 0);
		$this->RegisterPropertyInteger('DepartureScript2', 0);
		$this->RegisterPropertyBoolean('ArrivalScript1Update', false);
		$this->RegisterPropertyBoolean('ArrivalScript2Update', false);
		$this->RegisterPropertyBoolean('DepartureScript1Update', false);
		$this->RegisterPropertyBoolean('DepartureScript2Update', false);

		$this->RegisterPropertyString('Users', '');

		$this->RegisterVariableBoolean('Presence', 'Presence', '~Presence');
		
	}

    public function ApplyChanges(){
        parent::ApplyChanges();
		
		$ident="geofence".$this->InstanceID;
		$name="Geofence".$this->InstanceID."Hook";
		$scriptId = $this->RegisterScript($ident, $name, "<?\n//Do not modify!\nrequire_once(IPS_GetKernelDirEx().\"scripts/__ipsmodule.inc.php\");\nrequire_once(\"../modules/Geofence/Controller/module.php\");\n(new GeofenceController(".$this->InstanceID."))->HandleWebData();\n?>");
		$this->RegisterWebHook("/hook/".$ident, $scriptId);
						
		$this->UpdateUsers();	
    }

	public  function GetConfigurationForm ( )  { 
		$this->SendDebug(__FUNCTION__, 'Creating the form...', 0);

		$form = json_decode(file_get_contents(__DIR__ . '/form.json'), true);

		$userInstanceIds = IPS_GetInstanceListByModuleID('{C4A1F68D-A34E-4A3A-A5EC-DCBC73532E2C}');

		$this->SendDebug(__FUNCTION__, 'Building users list...', 0);
		$users = [];
		foreach($userInstanceIds as $userInstanceId) {
			$users[] = ['Username' => IPS_GetName($userInstanceId), 'Enabled' => IPS_GetProperty($userInstanceId, 'Enabled'), 'InstanceId' => $userInstanceId];
		}

		$form['elements'][1]['items'][9]['values'] = $users;

		$this->SendDebug(__FUNCTION__, 'Done creating form', 0);

		return json_encode($form);
	}

	private function UpdateUsers() {
		$this->SendDebug(__FUNCTION__, 'Updating users...', 0);

		$list = $this->ReadPropertyString('Users');
		if(strlen($list)>0) {
			$userList = json_decode($list, true);
						
			foreach($userList as $user) {
				if($user['InstanceId']==0) {
					$this->SendDebug(__FUNCTION__, sprintf('Adding new user "%s"...', $user['Username']), 0);
					$newUserId = IPS_CreateInstance('{C4A1F68D-A34E-4A3A-A5EC-DCBC73532E2C}');
					IPS_SetName($newUserId, $user['Username']);
					IPS_SetParent($newUserId, $this->InstanceID);
				} else if(IPS_InstanceExists($user['InstanceId'])){
					$this->SendDebug(__FUNCTION__, sprintf('Checking existing user with id "%d"...', $user['InstanceId']), 0);
					$oldName = IPS_GetName($user['InstanceId']);
					if($oldName!=$user['Username']) {
						$this->SendDebug(__FUNCTION__, sprintf('Renaming user to "%s"', $user['Username']), 0);
						IPS_SetName($user['InstanceId'], $user['Username']);
					}
					$enabled = IPS_GetProperty($user['InstanceId'], 'Enabled');
					if($enabled!=$user['Enabled']) {
						$this->SendDebug(__FUNCTION__, sprintf('Setting "Enabled to "%s"', $user['Enabled']?'true':'false'), 0);
						IPS_SetProperty($user['InstanceId'], 'Enabled', $user['Enabled']);
						IPS_ApplyChanges($user['InstanceId']);
					}
				}
			}

			$this->SendDebug(__FUNCTION__, 'Removing unused users...', 0);

			$existingUserInstanceIds = IPS_GetInstanceListByModuleID ('{C4A1F68D-A34E-4A3A-A5EC-DCBC73532E2C}');

			$this->SendDebug(__FUNCTION__, sprintf('Existing users: %s', json_encode($existingUserInstanceIds)), 0);
			$this->SendDebug(__FUNCTION__, sprintf('Users in list: %s', json_encode($userList)), 0);

			foreach($existingUserInstanceIds as $existingUserInstanceId) {
				$username = IPS_GetName($existingUserInstanceId);
				$found = false;
				foreach($userList as $user) {
					if($user['Username'] == $username) {
						$found = true;
						break;
					}
				}

				if(!$found) {
					$this->SendDebug(__FUNCTION__, sprintf('Removing user "%s"...', $username), 0);
					
					$this->Delete($existingUserInstanceId);
					
					$this->SendDebug(__FUNCTION__, sprintf('Removed user "%s"', $username), 0);
				}
			}
		}

		$this->SendDebug(__FUNCTION__, 'Done updating users', 0);
	}

	private function Delete(int $ObjectId) {
		$children = IPS_GetChildrenIDs($ObjectId);
		foreach($children as $child) {
			Delete($child);
		}
	
		switch(IPS_GetObject($ObjectId)['ObjectType']) {
			case 0: // Category   
				IPS_DeleteCategory($ObjectId);
				break;
			case 1: // Instance
				IPS_DeleteInstance($ObjectId);
				break;
			case 2: // Variable
				IPS_DeleteVariable($ObjectId);
				break;
			case 3: // Script
				IPS_DeleteScript($ObjectId);
				break;
			case 4: // Event
				IPS_DeleteEvent($ObjectId);
				break;
			case 5: // Media
				IPS_DeleteMedia($ObjectId);
				break;
			case 6: // Link
				IPS_DeleteLink($ObjectId);
				break;
		}
	}

    public function HandleWebData() {
		$username = IPS_GetProperty($this->InstanceID, "Username");
		$password = IPS_GetProperty($this->InstanceID, "Password");
				
		if($username!="" || $password!="") {
			if(!isset($_SERVER['PHP_AUTH_USER']))
				$_SERVER['PHP_AUTH_USER'] = "";
			if(!isset($_SERVER['PHP_AUTH_PW']))
				$_SERVER['PHP_AUTH_PW'] = "";

			if(($_SERVER['PHP_AUTH_USER'] != $username) || ($_SERVER['PHP_AUTH_PW'] != $password)) {
				header('WWW-Authenticate: Basic Realm="Geofence"');
				header('HTTP/1.0 401 Unauthorized');
				echo "Authorization required to access Symcon and Geofence";
				$this->SendDebug(__FUNCTION__, 'Authentication needed or invalid username/password!', 0);
				return;
			} else
			$this->SendDebug(__FUNCTION__, 'You are authenticated!', 0);
				
		} else
		$this->SendDebug(__FUNCTION__, 'No authentication needed', 0);
		
		$username="";
		$password="";
		
		if(!$this->Lock("HandleWebData")) {
			$this->SendDebug(__FUNCTION__, 'Waiting for unlock timed out!', 0);
			return;
		}
		
		$this->SendDebug(__FUNCTION__, 'The controller is locked', 0);
		
		$cmd="";
		$userId="";
		
		$this->SendDebug(__FUNCTION__, sprintf('Received the following query parameters: %s',json_encode($_GET)), 0);
		
		if (array_key_exists('cmd', $_GET))
			$cmd=strtolower($_GET['cmd']);
				
		if (array_key_exists('uid', $_GET))
			$userId=strtolower($_GET['uid']);
		elseif (array_key_exists('id', $_GET))
			$userId=strtolower($_GET['id']);
		
		if($cmd!="" && $userId!="") {
			$msg = sprintf('Received the command "%s" for user with id "%d"', $cmd, $userId);
			$this->SendDebug(__FUNCTION__, $msg, 0);
			IPS_LogMessage('Geofence', $msg);

			$userIds=IPS_GetInstanceListByModuleID('{C4A1F68D-A34E-4A3A-A5EC-DCBC73532E2C}');
						
			$userExists = false;
			foreach($usersIds as $id) {
				if($id==$userId) {
					$userExists=true;
					break;
				}
			}
			
			if($userExists) {
				$this->SendDebug(__FUNCTION__, sprintf('User with id is "%s"', IPS_GetName($userId)), 0);
				
				switch($cmd) {
					case 'arrival1':
						$presence = true;
						$scriptProperty = 'ArrivalScript1';
						break;
					case 'arrival2':
						$presence = true;
						$scriptProperty = 'ArrivalScript2';
						break;
					case 'departure1':
						$presence = false;
						$scriptProperty = 'DepartureScript1';
						break;
					case 'departure2':
						$presence = false;
						$scriptProperty = 'DepartureScript2';
						break;
					default:
					$this->SendDebug(__FUNCTION__, 'Invalid command!', 0);
						$this->Unlock("HandleWebData");
						return;
				}
				
				$presenceId=IPS_GetVariableIDByName ('Presence', $userId);
				$commonPresenceId=$this->GetIdForIdent('Presence');
				
				$lastCommonPresence=$this->GetValue('Presence');
				
				$updatePresence=$this->ReadPropertyBoolean($scriptProperty."Update");
				
				if($updatePresence) {
					$this->SendDebug(__FUNCTION__, 'Updated Presence for user "'.IPS_GetName($userId).'" to "'.$this->GetProfileValueName(IPS_GetVariable($presenceId)['VariableProfile'], $presence).'"', 0);
					SetValue($presenceId, $presence);
				}
				
				$commonPresence = false;
				for($x=0;$x<$size;$x++){
					$presenceId=IPS_GetVariableIDByName ('Presence', $users[$x]);
					if(GetValue($presenceId)) {
						$commonPresence = true;
						break;
					}
				}
				
				if($updatePresence) {
					$this->SetValue('Presence', $commonPresence);
					$this->SendDebug(__FUNCTION__, 'Updated Common Presence to "'.$this->GetProfileValueName(IPS_GetVariable($commonPresenceId)['VariableProfile'], $commonPresence).'"', 0);
				} else
					$this->SendDebug(__FUNCTION__, 'Presence update is not enabled for this command.', 0);
				
				$scriptId = $this->ReadPropertyInteger($scriptProperty);
			
				if($scriptId>0) { 
					$this->SendDebug(__FUNCTION__, 'The script is "'.IPS_GetName($scriptId).'"', 0);
					
					$runScript = true;
					if($updatePresence && $presence==$lastCommonPresence) {
						$runScript = false;
						$message = 'Old Presence and new Presence is equal. Skipping script';
					}
									
					if($updatePresence && !$presence && $commonPresence) {
						$runScript = false;
						$message='Not all users have sent a departure command. Skipping script';
					}
					
					if($runScript) {					
						if(array_key_exists('delay', $_GET) && is_numeric($_GET['delay'])) {
							$delay = (int)$_GET['delay'];
							if($delay>0) {
								$this->SendDebug(__FUNCTION__, sprintf('Running script with %d seconds delay...', $delay), 0);
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
								$this->SendDebug(__FUNCTION__, 'Running script...', 0);
								IPS_RunScript($scriptId);
							}
						} else {
							$this->SendDebug(__FUNCTION__, 'Running script...', 0);
							IPS_RunScript($scriptId);
						}
					} else
						$this->SendDebug(__FUNCTION__, $message, 0);				
				} else 
					$this->SendDebug(__FUNCTION__, 'No script is selected for this command', 0);
				
				echo "The request is processed";
			
			} else
				$this->SendDebug(__FUNCTION__, 'Unknown user', 0);
		} else
			$this->SendDebug(__FUNCTION__, 'Invalid or missing "id" or "cmd" in URL', 0);
		
		$this->Unlock("HandleWebData");
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
		$id = IPS_GetInstanceListByModuleID('{015A6EB8-D6E5-4B93-B496-0D3F77AE9FE1}');

		if(sizeof($id)) {
			$hooks = json_decode(IPS_GetProperty($id[0], 'Hooks'), true);

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
			   $hooks[] = Array('Hook' => $Hook, 'TargetID' => $TargetId);
			   
			IPS_SetProperty($id[0], 'Hooks', json_encode($hooks));
			IPS_ApplyChanges($id[0]);
		}
    }
		
	private function Lock($Ident) {
        for ($x=0;$x<100;$x++) {
            if (IPS_SemaphoreEnter('GEO_'.(string)$this->InstanceID.(string)$Ident, 1)){
                return true;
            }
            else {
  				if($x==0)
				  	$this->SendDebug(__FUNCTION__, 'Waiting for controller to unlock...', 0);
				IPS_Sleep(mt_rand(1, 5));
            }
        }

        return false;
    }

    private function Unlock($Ident) {
        IPS_SemaphoreLeave('GEO_'.(string)$this->InstanceID.(string)$Ident);
		$this->SendDebug(__FUNCTION__, 'The controller is unlocked', 0);
    }
}

