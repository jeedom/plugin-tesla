<?php

/* This file is part of Jeedom.
 *
 * Jeedom is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Jeedom is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Jeedom. If not, see <http://www.gnu.org/licenses/>.
 */

/* * ***************************Includes********************************* */
require_once dirname(__FILE__) . '/../../../../core/php/core.inc.php';

class tesla extends eqLogic {
    /*     * *************************Attributs****************************** */
   public static function lienToken(){
    	return  dirname(__FILE__) . '/../../data/Tesla_Token.json';
   }
	/************** API TESLA **************/
	public static function recupToken(){
		// ************* DEBUT DES VARIABLES
		$grant_type="password"; // information lié à l'appel API, NE PAS MODIFIER
		$client_id="81527cff06843c8634fdc09e8ac0abefb46ac849f38fe1e431c2ef2106796384"; // information lié à l'appel API, NE PAS MODIFIER
		$client_secret="c7257eb71a564034f9419ee651c7d0e5f7aa6bfbd18bafb5c5c033b093bb2fa3"; // information lié à l'appel API, NE PAS MODIFIER
		$email = config::byKey('username', 'tesla');
		$password = config::byKey('password', 'tesla');

		$my_file=fopen(tesla::lienToken(), 'w');
		$data_url = "grant_type=$grant_type&client_id=$client_id&client_secret=$client_secret&email=$email&password=$password";

		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, "https://owner-api.teslamotors.com/oauth/token");
		curl_setopt($ch, CURLOPT_HEADER, FALSE);
		curl_setopt($ch, CURLOPT_POST, TRUE);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $data_url);
		curl_setopt($ch, CURLOPT_FILE, $my_file);
		$response = curl_exec($ch);
		curl_close($ch);
	}
  
    public static function readToken(){
        $linkToken = tesla::lienToken();
        $token_json = fopen($linkToken, "r");
      	$contents = fread($token_json, filesize($linkToken));
		fclose($token_json);
        $token_json = json_decode($contents,true);
        $expire = ($token_json['created_at'] + $token_json['expires_in']);
        if(time() < $expire){
        	log::add('tesla', 'debug', 'fichier token : '.$contents);
      		return $token_json['access_token'];
        }else{
        	log::add('tesla', 'debug', 'fichier token : '.$contents);
          	log::add('tesla', 'error', 'TOKEN EXPIRER');
          return 'nok';
        }
    }
  
  	public static function discoveryVehicule(){
         log::add('tesla', 'debug', 'Discovery Vehicule');
      	 $token = tesla::readToken();
      	if($token == 'nok'){
          tesla::recupToken();
          $token = tesla::readToken();
        }
          $ch = curl_init();
           curl_setopt($ch, CURLOPT_URL, "https://owner-api.teslamotors.com/api/1/vehicles");
           curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
           curl_setopt($ch, CURLOPT_HEADER, FALSE);
           curl_setopt($ch, CURLOPT_HTTPHEADER, array("Authorization: Bearer ".$token));
          //execute la requête
          $response = curl_exec($ch);
          curl_close($ch);
          log::add('tesla', 'debug', 'Voiture : '.$response);
          return $response;
    }
	
  	public static function addVehicule($Tesla_Vehicles){
        log::add('tesla', 'debug', 'validation Vehicule');
      	$Tesla_Vehicles = json_decode($Tesla_Vehicles,true);
      	$Tesla_Vehicles = $Tesla_Vehicles['response'];
      	foreach ($Tesla_Vehicles as &$Tesla_Vehicle) {
    		log::add('tesla', 'debug', 'id vehicles : '.$Tesla_Vehicle['id']);
          	$eqExiste = eqlogic::byLogicalId($Tesla_Vehicle['vehicle_id'], 'tesla');
          	if(!is_object($eqExiste)){
              	log::add('tesla', 'info','Création du vehicule '.$Tesla_Vehicle['display_name']);
            	$tesla = new eqLogic;
                $tesla->setEqType_name('tesla');
                $tesla->setName($Tesla_Vehicle['display_name']);
                $tesla->setConfiguration('vehicle_id',$Tesla_Vehicle['vehicle_id']);
                $tesla->setConfiguration('state',$Tesla_Vehicle['state']);
              	$tesla->setConfiguration('vin',$Tesla_Vehicle['vin']);
                $tesla->setConfiguration('option_codes',$Tesla_Vehicle['option_codes']);
                $tesla->setConfiguration('color',$Tesla_Vehicle['color']);
                $tesla->setConfiguration('in_service',$Tesla_Vehicle['in_service']);
                $tesla->setConfiguration('id_s',$Tesla_Vehicle['id_s']);
                $tesla->setConfiguration('remote_start_enabled',$Tesla_Vehicle['remote_start_enabled']);
                $tesla->setConfiguration('calendar_enabled',$Tesla_Vehicle['calendar_enabled']);
                $tesla->setConfiguration('notifications_enabled',$Tesla_Vehicle['notifications_enabled']);
                $tesla->setConfiguration('backseat_token',$Tesla_Vehicle['backseat_token']);
                $tesla->setConfiguration('backseat_token_updated_at',$Tesla_Vehicle['backseat_token_updated_at']);
              	$tesla->setConfiguration('model',tesla::modele($Tesla_Vehicle['option_codes']));
                $tesla->setIsEnable(1);
                $tesla->setLogicalId($Tesla_Vehicle['vehicle_id']);
                $tesla->save();
            }
	  	}  
    }
  
	public static function scantesla(){
      	log::add('tesla', 'info','Lancement du scan des vehicules Tesla');
      	$discoveryVehicule = tesla::discoveryVehicule();
        tesla::addVehicule($discoveryVehicule);
      	tesla::maj_tesla();
	}
	/*** ****/
	
	public static function modele($option_codes){
		$model = substr($option_codes, 0, 4);
        if($model == 'MDLX'){
        	return 'X';  
        }else{
         	return 'S'; 
        }
	}
  
  /***** MAJ TESLA *****/
  public static function maj_tesla(){
    	$vehicles = eqlogic::byType('tesla');
      	foreach ($vehicles as &$vehicle) {
          	  log::add('tesla', 'info','Mise à jours des commandes du vehicule '.$vehicle->getConfiguration('display_name'));
          	  $vehicle_id = $vehicle->getConfiguration('id_s');
              log::add('tesla', 'debug', 'recup State Vehicule : '.$vehicle_id);
          	  $vehicule_state = tesla::recup_json($vehicle_id,'vehicle_state');
              tesla::read_json($vehicule_state,$vehicle->getId());
              $charge_state = tesla::recup_json($vehicle_id,'charge_state');
          	  tesla::read_json($charge_state,$vehicle->getId());
              $drive_state = tesla::recup_json($vehicle_id,'drive_state');
          	  tesla::read_json($drive_state,$vehicle->getId());
              $climate_state = tesla::recup_json($vehicle_id,'climate_state');
          	  tesla::read_json($climate_state,$vehicle->getId());
        }
  }
  
  /*********** API TESLA UPDATE ****************/
    public static function recup_json($vehicle,$type){
        $url = "https://owner-api.teslamotors.com/api/1/vehicles/".$vehicle."/data_request/$type";
    	$token = tesla::readToken();
    	if($token == 'nok'){
        	$reponse = 'nok';
          	log::add('tesla', 'debug', 'charge_state : '.$response);
        }else{
          $ch = curl_init();
          curl_setopt($ch, CURLOPT_URL, $url);
          curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
          curl_setopt($ch, CURLOPT_HEADER, FALSE);
          curl_setopt($ch, CURLOPT_HTTPHEADER, array("Authorization: Bearer ".$token));
          $response = curl_exec($ch);
          curl_close($ch);
          log::add('tesla', 'debug', $type.' : '.$response);
        }
    	return $response;
  	}
  
  	public static function read_json($json,$eqlogicId){
      $array_json = json_decode($json,true);
      if(isset($array_json['error'])){
      		 log::add('tesla', 'error',' ERROR lecture JSON : '.$array_json['error']);
      }else{
       		$data = $array_json['response'];
        	$keys = array_keys($data);
        	foreach ($keys as &$key) {
              	$cmd = cmd::byEqLogicIdAndLogicalId($eqlogicId,$key);
              	log::add('tesla', 'debug',$key.' => '.$data[$key]);
              	if(is_object($cmd)){
                	$value_cmd = $cmd->execCmd();
                  	if($value_cmd !== $data[$key]){
                    	$cmd->event($data[$key]);
                      	log::add('tesla', 'debug','different enregistrement de '.$key);
                    }
                }
            }
      }
    }

    /*     * ***********************Methode static*************************** */

    /*
     * Fonction exécutée automatiquement toutes les minutes par Jeedom
      public static function cron() {

      }
     */


    /*
     * Fonction exécutée automatiquement toutes les heures par Jeedom
      public static function cronHourly() {

      }
     */

    /*
     * Fonction exécutée automatiquement tous les jours par Jeedom
      public static function cronDayly() {

      }
     */



    /*     * *********************Méthodes d'instance************************* */
	/*
    public function preInsert() {
        
    }

    public function postInsert() {
    }

    public function preSave() {
    }
	*/
    public function postSave() {
    	$this->crea_cmd();
    }
	/*
    public function preUpdate() {
        
    }

    public function postUpdate() {
        
    }

    public function preRemove() {
        
    }

    public function postRemove() {
        
    }
    */
    function crea_cmd() {
	    	$cmd = $this->getCmd(null, 'luminosity_state');
			if (!is_object($cmd)) {
				$cmd = new teslaCmd();
				$cmd->setLogicalId('luminosity_state');
				$cmd->setName(__('Etat Luminosité', __FILE__));
				$cmd->setIsVisible(0);
			}
		$cmd->setType('info');
		$cmd->setSubType('numeric');
		$cmd->setEqLogic_id($this->getId());
		$cmd->setOrder(1);
		$cmd->setConfiguration('minValue', '0');
		$cmd->setConfiguration('maxValue', '100');
		$cmd->setDisplay('generic_type', 'LIGHT_STATE');
		$cmd->save();
		$luminosity_id = $cmd->getId();
    }

    /*
     * Non obligatoire mais permet de modifier l'affichage du widget si vous en avez besoin
      public function toHtml($_version = 'dashboard') {

      }
     */

    /*     * **********************Getteur Setteur*************************** */
}

class teslaCmd extends cmd {
    /*     * *************************Attributs****************************** */


    /*     * ***********************Methode static*************************** */


    /*     * *********************Methode d'instance************************* */

    /*
     * Non obligatoire permet de demander de ne pas supprimer les commandes même si elles ne sont pas dans la nouvelle configuration de l'équipement envoyé en JS
      public function dontRemoveCmd() {
      return true;
      }
     */

    /*     * **********************Getteur Setteur*************************** */
}

?>


