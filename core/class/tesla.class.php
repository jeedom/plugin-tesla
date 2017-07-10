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
        	log::add('tesla', 'debug', 'access token : '.$token_json['access_token']);
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
          	$eqExiste = eqlogic::byLogicalId($Tesla_Vehicle['id'], 'tesla');
          	if(!is_object($eqExiste)){
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
      	$discoveryVehicule = tesla::discoveryVehicule();
        tesla::addVehicule($discoveryVehicule);
      	$vehicles = eqlogic::byType('tesla');
      	foreach ($vehicles as &$vehicle) {
          	  $vehicle_id = $vehicle->getConfiguration('id_s');
              log::add('tesla', 'debug', 'recup State Vehicule : '.$vehicle_id);
          	  tesla::charge_state($vehicle_id);
        }
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
  
  /*********** API TESLA UPDATE ****************/
    public static function charge_state($vehicle){
    	$token = tesla::readToken();
    	if($token == 'nok'){
        	$reponse = 'nok';
          	log::add('tesla', 'debug', 'charge_state : '.$response);
        }else{
          $ch = curl_init();
          curl_setopt($ch, CURLOPT_URL, "https://owner-api.teslamotors.com/api/1/vehicles/".$vehicle."/data_request/charge_state");
          curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
          curl_setopt($ch, CURLOPT_HEADER, FALSE);
          curl_setopt($ch, CURLOPT_HTTPHEADER, array("Authorization: Bearer ".$token));
          $response = curl_exec($ch);
          curl_close($ch);
          log::add('tesla', 'debug', 'charge_state : '.$response);
        }
    	return $reponse;
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
	    $cmd = $this->getCmd(null, 'inside_temp');
     if (!is_object($cmd)) {
      $cmd = new teslaCmd();
      $cmd->setLogicalId('inside_temp');
      $cmd->setName(__('Température intérieure', __FILE__));
      $cmd->setIsVisible(1);
       $cmd->setIsHistorized(1);
     }
      $cmd->setType('info');
      $cmd->setSubType('numeric');
      $cmd->setEqLogic_id($this->getId());
      $cmd->setOrder(1);
      $cmd->save();

  $cmd = $this->getCmd(null, 'outside_temp');
     if (!is_object($cmd)) {
      $cmd = new teslaCmd();
      $cmd->setLogicalId('outside_temp');
      $cmd->setName(__('outside_temp', __FILE__));
      $cmd->setIsVisible(1);
       $cmd->setIsHistorized(1);
     }
      $cmd->setType('info');
      $cmd->setSubType('numeric');
      $cmd->setEqLogic_id($this->getId());
      $cmd->setOrder(2);
      $cmd->save();

  $cmd = $this->getCmd(null, 'driver_temp_setting');
     if (!is_object($cmd)) {
      $cmd = new teslaCmd();
      $cmd->setLogicalId('driver_temp_setting');
      $cmd->setName(__('driver_temp_setting', __FILE__));
      $cmd->setIsVisible(1);
       $cmd->setIsHistorized(1);
     }
      $cmd->setType('info');
      $cmd->setSubType('numeric');
      $cmd->setEqLogic_id($this->getId());
      $cmd->setOrder(3);
      $cmd->save();

  $cmd = $this->getCmd(null, 'passenger_temp_setting');
     if (!is_object($cmd)) {
      $cmd = new teslaCmd();
      $cmd->setLogicalId('passenger_temp_setting');
      $cmd->setName(__('passenger_temp_setting', __FILE__));
      $cmd->setIsVisible(1);
       $cmd->setIsHistorized(1);
     }
      $cmd->setType('info');
      $cmd->setSubType('numeric');
      $cmd->setEqLogic_id($this->getId());
      $cmd->setOrder(4);
      $cmd->save();

  $cmd = $this->getCmd(null, 'left_temp_direction');
     if (!is_object($cmd)) {
      $cmd = new teslaCmd();
      $cmd->setLogicalId('left_temp_direction');
      $cmd->setName(__('left_temp_direction', __FILE__));
      $cmd->setIsVisible(0);
       $cmd->setIsHistorized(0);
     }
      $cmd->setType('info');
      $cmd->setSubType('string');
      $cmd->setEqLogic_id($this->getId());
      $cmd->setOrder(5);
      $cmd->save();

  $cmd = $this->getCmd(null, 'right_temp_direction');
     if (!is_object($cmd)) {
      $cmd = new teslaCmd();
      $cmd->setLogicalId('right_temp_direction');
      $cmd->setName(__('right_temp_direction', __FILE__));
      $cmd->setIsVisible(0);
       $cmd->setIsHistorized(0);
     }
      $cmd->setType('info');
      $cmd->setSubType('string');
      $cmd->setEqLogic_id($this->getId());
      $cmd->setOrder(6);
      $cmd->save();

  $cmd = $this->getCmd(null, 'is_auto_conditioning_on');
     if (!is_object($cmd)) {
      $cmd = new teslaCmd();
      $cmd->setLogicalId('is_auto_conditioning_on');
      $cmd->setName(__('is_auto_conditioning_on', __FILE__));
      $cmd->setIsVisible(1);
       $cmd->setIsHistorized(1);
     }
      $cmd->setType('info');
      $cmd->setSubType('binary');
      $cmd->setEqLogic_id($this->getId());
      $cmd->setOrder(7);
      $cmd->save();

  $cmd = $this->getCmd(null, 'is_front_defroster_on');
     if (!is_object($cmd)) {
      $cmd = new teslaCmd();
      $cmd->setLogicalId('is_front_defroster_on');
      $cmd->setName(__('is_front_defroster_on', __FILE__));
      $cmd->setIsVisible(1);
       $cmd->setIsHistorized(0);
     }
      $cmd->setType('info');
      $cmd->setSubType('binary');
      $cmd->setEqLogic_id($this->getId());
      $cmd->setOrder(8);
      $cmd->save();

  $cmd = $this->getCmd(null, 'is_rear_defroster_on');
     if (!is_object($cmd)) {
      $cmd = new teslaCmd();
      $cmd->setLogicalId('is_rear_defroster_on');
      $cmd->setName(__('is_rear_defroster_on', __FILE__));
      $cmd->setIsVisible(0);
       $cmd->setIsHistorized(0);
     }
      $cmd->setType('info');
      $cmd->setSubType('binary');
      $cmd->setEqLogic_id($this->getId());
      $cmd->setOrder(9);
      $cmd->save();

  $cmd = $this->getCmd(null, 'fan_status');
     if (!is_object($cmd)) {
      $cmd = new teslaCmd();
      $cmd->setLogicalId('fan_status');
      $cmd->setName(__('fan_status', __FILE__));
      $cmd->setIsVisible(1);
       $cmd->setIsHistorized(1);
     }
      $cmd->setType('info');
      $cmd->setSubType('numeric');
      $cmd->setEqLogic_id($this->getId());
      $cmd->setOrder(10);
      $cmd->save();

  $cmd = $this->getCmd(null, 'is_climate_on');
     if (!is_object($cmd)) {
      $cmd = new teslaCmd();
      $cmd->setLogicalId('is_climate_on');
      $cmd->setName(__('is_climate_on', __FILE__));
      $cmd->setIsVisible(1);
       $cmd->setIsHistorized(0);
     }
      $cmd->setType('info');
      $cmd->setSubType('binary');
      $cmd->setEqLogic_id($this->getId());
      $cmd->setOrder(11);
      $cmd->save();

  $cmd = $this->getCmd(null, 'min_avail_temp');
     if (!is_object($cmd)) {
      $cmd = new teslaCmd();
      $cmd->setLogicalId('min_avail_temp');
      $cmd->setName(__('min_avail_temp', __FILE__));
      $cmd->setIsVisible(0);
       $cmd->setIsHistorized(0);
     }
      $cmd->setType('info');
      $cmd->setSubType('numeric');
      $cmd->setEqLogic_id($this->getId());
      $cmd->setOrder(12);
      $cmd->save();

  $cmd = $this->getCmd(null, 'max_avail_temp');
     if (!is_object($cmd)) {
      $cmd = new teslaCmd();
      $cmd->setLogicalId('max_avail_temp');
      $cmd->setName(__('max_avail_temp', __FILE__));
      $cmd->setIsVisible(0);
       $cmd->setIsHistorized(0);
     }
      $cmd->setType('info');
      $cmd->setSubType('numeric');
      $cmd->setEqLogic_id($this->getId());
      $cmd->setOrder(13);
      $cmd->save();

  $cmd = $this->getCmd(null, 'seat_heater_left');
     if (!is_object($cmd)) {
      $cmd = new teslaCmd();
      $cmd->setLogicalId('seat_heater_left');
      $cmd->setName(__('seat_heater_left', __FILE__));
      $cmd->setIsVisible(1);
       $cmd->setIsHistorized(0);
     }
      $cmd->setType('info');
      $cmd->setSubType('numeric');
      $cmd->setEqLogic_id($this->getId());
      $cmd->setOrder(14);
      $cmd->save();

  $cmd = $this->getCmd(null, 'seat_heater_right');
     if (!is_object($cmd)) {
      $cmd = new teslaCmd();
      $cmd->setLogicalId('seat_heater_right');
      $cmd->setName(__('seat_heater_right', __FILE__));
      $cmd->setIsVisible(1);
       $cmd->setIsHistorized(0);
     }
      $cmd->setType('info');
      $cmd->setSubType('numeric');
      $cmd->setEqLogic_id($this->getId());
      $cmd->setOrder(15);
      $cmd->save();

  $cmd = $this->getCmd(null, 'seat_heater_rear_left');
     if (!is_object($cmd)) {
      $cmd = new teslaCmd();
      $cmd->setLogicalId('seat_heater_rear_left');
      $cmd->setName(__('seat_heater_rear_left', __FILE__));
      $cmd->setIsVisible(1);
       $cmd->setIsHistorized(0);
     }
      $cmd->setType('info');
      $cmd->setSubType('numeric');
      $cmd->setEqLogic_id($this->getId());
      $cmd->setOrder(16);
      $cmd->save();

  $cmd = $this->getCmd(null, 'seat_heater_rear_right');
     if (!is_object($cmd)) {
      $cmd = new teslaCmd();
      $cmd->setLogicalId('seat_heater_rear_right');
      $cmd->setName(__('seat_heater_rear_right', __FILE__));
      $cmd->setIsVisible(1);
       $cmd->setIsHistorized(0);
     }
      $cmd->setType('info');
      $cmd->setSubType('numeric');
      $cmd->setEqLogic_id($this->getId());
      $cmd->setOrder(17);
      $cmd->save();

  $cmd = $this->getCmd(null, 'seat_heater_rear_center');
     if (!is_object($cmd)) {
      $cmd = new teslaCmd();
      $cmd->setLogicalId('seat_heater_rear_center');
      $cmd->setName(__('seat_heater_rear_center', __FILE__));
      $cmd->setIsVisible(1);
       $cmd->setIsHistorized(0);
     }
      $cmd->setType('info');
      $cmd->setSubType('numeric');
      $cmd->setEqLogic_id($this->getId());
      $cmd->setOrder(18);
      $cmd->save();

  $cmd = $this->getCmd(null, 'seat_heater_rear_right_back');
     if (!is_object($cmd)) {
      $cmd = new teslaCmd();
      $cmd->setLogicalId('seat_heater_rear_right_back');
      $cmd->setName(__('seat_heater_rear_right_back', __FILE__));
      $cmd->setIsVisible(1);
       $cmd->setIsHistorized(0);
     }
      $cmd->setType('info');
      $cmd->setSubType('numeric');
      $cmd->setEqLogic_id($this->getId());
      $cmd->setOrder(19);
      $cmd->save();

  $cmd = $this->getCmd(null, 'seat_heater_rear_left_back');
     if (!is_object($cmd)) {
      $cmd = new teslaCmd();
      $cmd->setLogicalId('seat_heater_rear_left_back');
      $cmd->setName(__('seat_heater_rear_left_back', __FILE__));
      $cmd->setIsVisible(1);
       $cmd->setIsHistorized(0);
     }
      $cmd->setType('info');
      $cmd->setSubType('numeric');
      $cmd->setEqLogic_id($this->getId());
      $cmd->setOrder(20);
      $cmd->save();
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

