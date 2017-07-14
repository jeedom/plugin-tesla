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
   public static function imageTesla(){
   		return dirname(__FILE__) . '/../../data';
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
          	tesla::photoTesla($Tesla_Vehicle['option_codes'],$Tesla_Vehicle['vehicle_id'],tesla::modele($Tesla_Vehicle['option_codes']));
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
    public static function photoTesla($options,$vehicle_id,$model){
      	  if($model == 'X'){
          	$url = "https://www.tesla.com/configurator/compositor/?model=mx&view=STUD_SIDE&size=1028&options=".$options."&bkba_opt=1";
          }else if($model == 'S'){
            $url = "https://www.tesla.com/configurator/compositor/?model=ms&view=STUD_SIDE&size=1028&options=".$options."&bkba_opt=1";
          }
      	   log::add('tesla', 'debug','Recup image : '.$url);
      	   $my_file=fopen(tesla::imageTesla().'/'.$vehicle_id.'.png', 'w');
      	   /*curl_setopt($ch, CURLOPT_URL, $url);
      	   curl_setopt($ch, CURLOPT_FILE, $my_file);
           //execute la requête
           $response = curl_exec($ch);
           curl_close($ch);*/
      		$ch = curl_init($url);
            curl_setopt($ch, CURLOPT_FILE, $my_file);
            $data = curl_exec($ch);
            $curl_errno = curl_errno($ch);
            $curl_error = curl_error($ch);
            curl_close($ch);
			fclose($fp);
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
    $cmd->setOrder(101);
    $cmd->save();

$cmd = $this->getCmd(null, 'outside_temp');
   if (!is_object($cmd)) {
    $cmd = new teslaCmd();
    $cmd->setLogicalId('outside_temp');
    $cmd->setName(__('Température extérieure', __FILE__));
    $cmd->setIsVisible(1);
     $cmd->setIsHistorized(1);
   }
    $cmd->setType('info');
    $cmd->setSubType('numeric');
    $cmd->setEqLogic_id($this->getId());
    $cmd->setOrder(102);
    $cmd->save();

$cmd = $this->getCmd(null, 'driver_temp_setting');
   if (!is_object($cmd)) {
    $cmd = new teslaCmd();
    $cmd->setLogicalId('driver_temp_setting');
    $cmd->setName(__('Température conducteur', __FILE__));
    $cmd->setIsVisible(1);
     $cmd->setIsHistorized(1);
   }
    $cmd->setType('info');
    $cmd->setSubType('numeric');
    $cmd->setEqLogic_id($this->getId());
    $cmd->setOrder(103);
    $cmd->save();

$cmd = $this->getCmd(null, 'passenger_temp_setting');
   if (!is_object($cmd)) {
    $cmd = new teslaCmd();
    $cmd->setLogicalId('passenger_temp_setting');
    $cmd->setName(__('Température passager', __FILE__));
    $cmd->setIsVisible(1);
     $cmd->setIsHistorized(1);
   }
    $cmd->setType('info');
    $cmd->setSubType('numeric');
    $cmd->setEqLogic_id($this->getId());
    $cmd->setOrder(104);
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
    $cmd->setOrder(105);
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
    $cmd->setOrder(106);
    $cmd->save();

$cmd = $this->getCmd(null, 'is_auto_conditioning_on');
   if (!is_object($cmd)) {
    $cmd = new teslaCmd();
    $cmd->setLogicalId('is_auto_conditioning_on');
    $cmd->setName(__('AC Auto', __FILE__));
    $cmd->setIsVisible(1);
     $cmd->setIsHistorized(1);
   }
    $cmd->setType('info');
    $cmd->setSubType('binary');
    $cmd->setEqLogic_id($this->getId());
    $cmd->setOrder(107);
    $cmd->save();

$cmd = $this->getCmd(null, 'is_front_defroster_on');
   if (!is_object($cmd)) {
    $cmd = new teslaCmd();
    $cmd->setLogicalId('is_front_defroster_on');
    $cmd->setName(__('Dégivrage Avant', __FILE__));
    $cmd->setIsVisible(1);
     $cmd->setIsHistorized(0);
   }
    $cmd->setType('info');
    $cmd->setSubType('binary');
    $cmd->setEqLogic_id($this->getId());
    $cmd->setOrder(108);
    $cmd->save();

$cmd = $this->getCmd(null, 'is_rear_defroster_on');
   if (!is_object($cmd)) {
    $cmd = new teslaCmd();
    $cmd->setLogicalId('is_rear_defroster_on');
    $cmd->setName(__('Dégivrage Arrière', __FILE__));
    $cmd->setIsVisible(0);
     $cmd->setIsHistorized(0);
   }
    $cmd->setType('info');
    $cmd->setSubType('binary');
    $cmd->setEqLogic_id($this->getId());
    $cmd->setOrder(109);
    $cmd->save();

$cmd = $this->getCmd(null, 'fan_status');
   if (!is_object($cmd)) {
    $cmd = new teslaCmd();
    $cmd->setLogicalId('fan_status');
    $cmd->setName(__('Vitesse de ventilation', __FILE__));
    $cmd->setIsVisible(1);
     $cmd->setIsHistorized(1);
   }
    $cmd->setType('info');
    $cmd->setSubType('numeric');
    $cmd->setEqLogic_id($this->getId());
    $cmd->setOrder(110);
    $cmd->save();

$cmd = $this->getCmd(null, 'is_climate_on');
   if (!is_object($cmd)) {
    $cmd = new teslaCmd();
    $cmd->setLogicalId('is_climate_on');
    $cmd->setName(__('AC', __FILE__));
    $cmd->setIsVisible(1);
     $cmd->setIsHistorized(0);
   }
    $cmd->setType('info');
    $cmd->setSubType('binary');
    $cmd->setEqLogic_id($this->getId());
    $cmd->setOrder(111);
    $cmd->save();

$cmd = $this->getCmd(null, 'min_avail_temp');
   if (!is_object($cmd)) {
    $cmd = new teslaCmd();
    $cmd->setLogicalId('min_avail_temp');
    $cmd->setName(__('Température minimale', __FILE__));
    $cmd->setIsVisible(0);
     $cmd->setIsHistorized(0);
   }
    $cmd->setType('info');
    $cmd->setSubType('numeric');
    $cmd->setEqLogic_id($this->getId());
    $cmd->setOrder(112);
    $cmd->save();

$cmd = $this->getCmd(null, 'max_avail_temp');
   if (!is_object($cmd)) {
    $cmd = new teslaCmd();
    $cmd->setLogicalId('max_avail_temp');
    $cmd->setName(__('Température maximale', __FILE__));
    $cmd->setIsVisible(0);
     $cmd->setIsHistorized(0);
   }
    $cmd->setType('info');
    $cmd->setSubType('numeric');
    $cmd->setEqLogic_id($this->getId());
    $cmd->setOrder(113);
    $cmd->save();

$cmd = $this->getCmd(null, 'seat_heater_left');
   if (!is_object($cmd)) {
    $cmd = new teslaCmd();
    $cmd->setLogicalId('seat_heater_left');
    $cmd->setName(__('Chauffage du siège Av Gauche', __FILE__));
    $cmd->setIsVisible(1);
     $cmd->setIsHistorized(0);
   }
    $cmd->setType('info');
    $cmd->setSubType('numeric');
    $cmd->setEqLogic_id($this->getId());
    $cmd->setOrder(114);
    $cmd->save();

$cmd = $this->getCmd(null, 'seat_heater_right');
   if (!is_object($cmd)) {
    $cmd = new teslaCmd();
    $cmd->setLogicalId('seat_heater_right');
    $cmd->setName(__('Chauffage du siège Av Droit', __FILE__));
    $cmd->setIsVisible(1);
     $cmd->setIsHistorized(0);
   }
    $cmd->setType('info');
    $cmd->setSubType('numeric');
    $cmd->setEqLogic_id($this->getId());
    $cmd->setOrder(115);
    $cmd->save();

$cmd = $this->getCmd(null, 'seat_heater_rear_left');
   if (!is_object($cmd)) {
    $cmd = new teslaCmd();
    $cmd->setLogicalId('seat_heater_rear_left');
    $cmd->setName(__('Chauffage du siège Ar Gauche', __FILE__));
    $cmd->setIsVisible(1);
     $cmd->setIsHistorized(0);
   }
    $cmd->setType('info');
    $cmd->setSubType('numeric');
    $cmd->setEqLogic_id($this->getId());
    $cmd->setOrder(116);
    $cmd->save();

$cmd = $this->getCmd(null, 'seat_heater_rear_right');
   if (!is_object($cmd)) {
    $cmd = new teslaCmd();
    $cmd->setLogicalId('seat_heater_rear_right');
    $cmd->setName(__('Chauffage du siège Ar Droit', __FILE__));
    $cmd->setIsVisible(1);
     $cmd->setIsHistorized(0);
   }
    $cmd->setType('info');
    $cmd->setSubType('numeric');
    $cmd->setEqLogic_id($this->getId());
    $cmd->setOrder(117);
    $cmd->save();

$cmd = $this->getCmd(null, 'seat_heater_rear_center');
   if (!is_object($cmd)) {
    $cmd = new teslaCmd();
    $cmd->setLogicalId('seat_heater_rear_center');
    $cmd->setName(__('Chauffage du siège Ar Centre', __FILE__));
    $cmd->setIsVisible(1);
     $cmd->setIsHistorized(0);
   }
    $cmd->setType('info');
    $cmd->setSubType('numeric');
    $cmd->setEqLogic_id($this->getId());
    $cmd->setOrder(118);
    $cmd->save();

$cmd = $this->getCmd(null, 'seat_heater_rear_right_back');
   if (!is_object($cmd)) {
    $cmd = new teslaCmd();
    $cmd->setLogicalId('seat_heater_rear_right_back');
    $cmd->setName(__('Chauffage du siège 3e Droit', __FILE__));
    $cmd->setIsVisible(1);
     $cmd->setIsHistorized(0);
   }
    $cmd->setType('info');
    $cmd->setSubType('numeric');
    $cmd->setEqLogic_id($this->getId());
    $cmd->setOrder(119);
    $cmd->save();

$cmd = $this->getCmd(null, 'seat_heater_rear_left_back');
   if (!is_object($cmd)) {
    $cmd = new teslaCmd();
    $cmd->setLogicalId('seat_heater_rear_left_back');
    $cmd->setName(__('Chauffage du siège 3e Gauche', __FILE__));
    $cmd->setIsVisible(1);
     $cmd->setIsHistorized(0);
   }
    $cmd->setType('info');
    $cmd->setSubType('numeric');
    $cmd->setEqLogic_id($this->getId());
    $cmd->setOrder(120);
    $cmd->save();

$cmd = $this->getCmd(null, 'smart_preconditioning');
   if (!is_object($cmd)) {
    $cmd = new teslaCmd();
    $cmd->setLogicalId('smart_preconditioning');
    $cmd->setName(__('Préclimatisation intelligente', __FILE__));
    $cmd->setIsVisible(1);
     $cmd->setIsHistorized(1);
   }
    $cmd->setType('info');
    $cmd->setSubType('binary');
    $cmd->setEqLogic_id($this->getId());
    $cmd->setOrder(121);
    $cmd->save();

$cmd = $this->getCmd(null, 'charging_state');
   if (!is_object($cmd)) {
    $cmd = new teslaCmd();
    $cmd->setLogicalId('charging_state');
    $cmd->setName(__('Etat de charge', __FILE__));
    $cmd->setIsVisible(1);
     $cmd->setIsHistorized(1);
   }
    $cmd->setType('info');
    $cmd->setSubType('string');
    $cmd->setEqLogic_id($this->getId());
    $cmd->setOrder(201);
    $cmd->save();

$cmd = $this->getCmd(null, 'charge_limit_soc');
   if (!is_object($cmd)) {
    $cmd = new teslaCmd();
    $cmd->setLogicalId('charge_limit_soc');
    $cmd->setName(__('Limite de charge', __FILE__));
    $cmd->setIsVisible(1);
     $cmd->setIsHistorized(1);
   }
    $cmd->setType('info');
    $cmd->setSubType('numeric');
    $cmd->setEqLogic_id($this->getId());
    $cmd->setOrder(202);
    $cmd->save();

$cmd = $this->getCmd(null, 'charge_limit_soc_std');
   if (!is_object($cmd)) {
    $cmd = new teslaCmd();
    $cmd->setLogicalId('charge_limit_soc_std');
    $cmd->setName(__('Limite de charge standard', __FILE__));
    $cmd->setIsVisible(0);
     $cmd->setIsHistorized(0);
   }
    $cmd->setType('info');
    $cmd->setSubType('numeric');
    $cmd->setEqLogic_id($this->getId());
    $cmd->setOrder(203);
    $cmd->save();

$cmd = $this->getCmd(null, 'charge_limit_soc_min');
   if (!is_object($cmd)) {
    $cmd = new teslaCmd();
    $cmd->setLogicalId('charge_limit_soc_min');
    $cmd->setName(__('Limite de charge min', __FILE__));
    $cmd->setIsVisible(0);
     $cmd->setIsHistorized(0);
   }
    $cmd->setType('info');
    $cmd->setSubType('numeric');
    $cmd->setEqLogic_id($this->getId());
    $cmd->setOrder(204);
    $cmd->save();

$cmd = $this->getCmd(null, 'charge_limit_soc_max');
   if (!is_object($cmd)) {
    $cmd = new teslaCmd();
    $cmd->setLogicalId('charge_limit_soc_max');
    $cmd->setName(__('Limite de charge max', __FILE__));
    $cmd->setIsVisible(0);
     $cmd->setIsHistorized(0);
   }
    $cmd->setType('info');
    $cmd->setSubType('numeric');
    $cmd->setEqLogic_id($this->getId());
    $cmd->setOrder(205);
    $cmd->save();

$cmd = $this->getCmd(null, 'charge_to_max_range');
   if (!is_object($cmd)) {
    $cmd = new teslaCmd();
    $cmd->setLogicalId('charge_to_max_range');
    $cmd->setName(__('Charge maximale', __FILE__));
    $cmd->setIsVisible(0);
     $cmd->setIsHistorized(0);
   }
    $cmd->setType('info');
    $cmd->setSubType('binary');
    $cmd->setEqLogic_id($this->getId());
    $cmd->setOrder(206);
    $cmd->save();

$cmd = $this->getCmd(null, 'battery_heater_on');
   if (!is_object($cmd)) {
    $cmd = new teslaCmd();
    $cmd->setLogicalId('battery_heater_on');
    $cmd->setName(__('Préchauffage batterie', __FILE__));
    $cmd->setIsVisible(0);
     $cmd->setIsHistorized(0);
   }
    $cmd->setType('info');
    $cmd->setSubType('string');
    $cmd->setEqLogic_id($this->getId());
    $cmd->setOrder(207);
    $cmd->save();

$cmd = $this->getCmd(null, 'not_enough_power_to_heat');
   if (!is_object($cmd)) {
    $cmd = new teslaCmd();
    $cmd->setLogicalId('not_enough_power_to_heat');
    $cmd->setName(__('Puissance insuffisante pour préchauffage', __FILE__));
    $cmd->setIsVisible(0);
     $cmd->setIsHistorized(0);
   }
    $cmd->setType('info');
    $cmd->setSubType('binary');
    $cmd->setEqLogic_id($this->getId());
    $cmd->setOrder(208);
    $cmd->save();

$cmd = $this->getCmd(null, 'max_range_charge_counter');
   if (!is_object($cmd)) {
    $cmd = new teslaCmd();
    $cmd->setLogicalId('max_range_charge_counter');
    $cmd->setName(__('Compteur charge maximale', __FILE__));
    $cmd->setIsVisible(0);
     $cmd->setIsHistorized(0);
   }
    $cmd->setType('info');
    $cmd->setSubType('numeric');
    $cmd->setEqLogic_id($this->getId());
    $cmd->setOrder(209);
    $cmd->save();

$cmd = $this->getCmd(null, 'fast_charger_present');
   if (!is_object($cmd)) {
    $cmd = new teslaCmd();
    $cmd->setLogicalId('fast_charger_present');
    $cmd->setName(__('Chargeur rapide', __FILE__));
    $cmd->setIsVisible(0);
     $cmd->setIsHistorized(0);
   }
    $cmd->setType('info');
    $cmd->setSubType('binary');
    $cmd->setEqLogic_id($this->getId());
    $cmd->setOrder(210);
    $cmd->save();

$cmd = $this->getCmd(null, 'fast_charger_type');
   if (!is_object($cmd)) {
    $cmd = new teslaCmd();
    $cmd->setLogicalId('fast_charger_type');
    $cmd->setName(__('Type de charge', __FILE__));
    $cmd->setIsVisible(0);
     $cmd->setIsHistorized(0);
   }
    $cmd->setType('info');
    $cmd->setSubType('string');
    $cmd->setEqLogic_id($this->getId());
    $cmd->setOrder(211);
    $cmd->save();

$cmd = $this->getCmd(null, 'battery_range');
   if (!is_object($cmd)) {
    $cmd = new teslaCmd();
    $cmd->setLogicalId('battery_range');
    $cmd->setName(__('Autonomie Nominale', __FILE__));
    $cmd->setIsVisible(1);
     $cmd->setIsHistorized(1);
   }
    $cmd->setType('info');
    $cmd->setSubType('numeric');
    $cmd->setEqLogic_id($this->getId());
    $cmd->setOrder(212);
    $cmd->save();

$cmd = $this->getCmd(null, 'est_battery_range');
   if (!is_object($cmd)) {
    $cmd = new teslaCmd();
    $cmd->setLogicalId('est_battery_range');
    $cmd->setName(__('Autonomie Estimée', __FILE__));
    $cmd->setIsVisible(1);
     $cmd->setIsHistorized(1);
   }
    $cmd->setType('info');
    $cmd->setSubType('numeric');
    $cmd->setEqLogic_id($this->getId());
    $cmd->setOrder(213);
    $cmd->save();

$cmd = $this->getCmd(null, 'ideal_battery_range');
   if (!is_object($cmd)) {
    $cmd = new teslaCmd();
    $cmd->setLogicalId('ideal_battery_range');
    $cmd->setName(__('Autonomie Typique', __FILE__));
    $cmd->setIsVisible(1);
     $cmd->setIsHistorized(1);
   }
    $cmd->setType('info');
    $cmd->setSubType('numeric');
    $cmd->setEqLogic_id($this->getId());
    $cmd->setOrder(214);
    $cmd->save();

$cmd = $this->getCmd(null, 'battery_level');
   if (!is_object($cmd)) {
    $cmd = new teslaCmd();
    $cmd->setLogicalId('battery_level');
    $cmd->setName(__('Niveau de batterie', __FILE__));
    $cmd->setIsVisible(1);
     $cmd->setIsHistorized(1);
   }
    $cmd->setType('info');
    $cmd->setSubType('numeric');
    $cmd->setEqLogic_id($this->getId());
    $cmd->setOrder(215);
    $cmd->save();

$cmd = $this->getCmd(null, 'usable_battery_level');
   if (!is_object($cmd)) {
    $cmd = new teslaCmd();
    $cmd->setLogicalId('usable_battery_level');
    $cmd->setName(__('Niveau de batterie utilisable', __FILE__));
    $cmd->setIsVisible(1);
     $cmd->setIsHistorized(1);
   }
    $cmd->setType('info');
    $cmd->setSubType('numeric');
    $cmd->setEqLogic_id($this->getId());
    $cmd->setOrder(216);
    $cmd->save();

$cmd = $this->getCmd(null, 'battery_current');
   if (!is_object($cmd)) {
    $cmd = new teslaCmd();
    $cmd->setLogicalId('battery_current');
    $cmd->setName(__('battery_current', __FILE__));
    $cmd->setIsVisible(0);
     $cmd->setIsHistorized(0);
   }
    $cmd->setType('info');
    $cmd->setSubType('string');
    $cmd->setEqLogic_id($this->getId());
    $cmd->setOrder(217);
    $cmd->save();

$cmd = $this->getCmd(null, 'charge_energy_added');
   if (!is_object($cmd)) {
    $cmd = new teslaCmd();
    $cmd->setLogicalId('charge_energy_added');
    $cmd->setName(__('Puissance ajoutée', __FILE__));
    $cmd->setIsVisible(0);
     $cmd->setIsHistorized(0);
   }
    $cmd->setType('info');
    $cmd->setSubType('numeric');
    $cmd->setEqLogic_id($this->getId());
    $cmd->setOrder(218);
    $cmd->save();

$cmd = $this->getCmd(null, 'charge_miles_added_rated');
   if (!is_object($cmd)) {
    $cmd = new teslaCmd();
    $cmd->setLogicalId('charge_miles_added_rated');
    $cmd->setName(__('Autonomie Nominale ajoutée', __FILE__));
    $cmd->setIsVisible(0);
     $cmd->setIsHistorized(0);
   }
    $cmd->setType('info');
    $cmd->setSubType('numeric');
    $cmd->setEqLogic_id($this->getId());
    $cmd->setOrder(219);
    $cmd->save();

$cmd = $this->getCmd(null, 'charge_miles_added_ideal');
   if (!is_object($cmd)) {
    $cmd = new teslaCmd();
    $cmd->setLogicalId('charge_miles_added_ideal');
    $cmd->setName(__('Autonomie Typique ajoutée', __FILE__));
    $cmd->setIsVisible(0);
     $cmd->setIsHistorized(0);
   }
    $cmd->setType('info');
    $cmd->setSubType('numeric');
    $cmd->setEqLogic_id($this->getId());
    $cmd->setOrder(220);
    $cmd->save();

$cmd = $this->getCmd(null, 'charger_voltage');
   if (!is_object($cmd)) {
    $cmd = new teslaCmd();
    $cmd->setLogicalId('charger_voltage');
    $cmd->setName(__('Voltage du chargeur', __FILE__));
    $cmd->setIsVisible(0);
     $cmd->setIsHistorized(0);
   }
    $cmd->setType('info');
    $cmd->setSubType('numeric');
    $cmd->setEqLogic_id($this->getId());
    $cmd->setOrder(221);
    $cmd->save();

$cmd = $this->getCmd(null, 'charger_pilot_current');
   if (!is_object($cmd)) {
    $cmd = new teslaCmd();
    $cmd->setLogicalId('charger_pilot_current');
    $cmd->setName(__('charger_pilot_current', __FILE__));
    $cmd->setIsVisible(0);
     $cmd->setIsHistorized(0);
   }
    $cmd->setType('info');
    $cmd->setSubType('numeric');
    $cmd->setEqLogic_id($this->getId());
    $cmd->setOrder(222);
    $cmd->save();

$cmd = $this->getCmd(null, 'charger_actual_current');
   if (!is_object($cmd)) {
    $cmd = new teslaCmd();
    $cmd->setLogicalId('charger_actual_current');
    $cmd->setName(__('charger_actual_current', __FILE__));
    $cmd->setIsVisible(0);
     $cmd->setIsHistorized(0);
   }
    $cmd->setType('info');
    $cmd->setSubType('numeric');
    $cmd->setEqLogic_id($this->getId());
    $cmd->setOrder(223);
    $cmd->save();

$cmd = $this->getCmd(null, 'charger_power');
   if (!is_object($cmd)) {
    $cmd = new teslaCmd();
    $cmd->setLogicalId('charger_power');
    $cmd->setName(__('Puissance du chargeur', __FILE__));
    $cmd->setIsVisible(0);
     $cmd->setIsHistorized(0);
   }
    $cmd->setType('info');
    $cmd->setSubType('numeric');
    $cmd->setEqLogic_id($this->getId());
    $cmd->setOrder(224);
    $cmd->save();

$cmd = $this->getCmd(null, 'time_to_full_charge');
   if (!is_object($cmd)) {
    $cmd = new teslaCmd();
    $cmd->setLogicalId('time_to_full_charge');
    $cmd->setName(__('Temps jusqu’à lacharge complète', __FILE__));
    $cmd->setIsVisible(0);
     $cmd->setIsHistorized(0);
   }
    $cmd->setType('info');
    $cmd->setSubType('numeric');
    $cmd->setEqLogic_id($this->getId());
    $cmd->setOrder(225);
    $cmd->save();

$cmd = $this->getCmd(null, 'trip_charging');
   if (!is_object($cmd)) {
    $cmd = new teslaCmd();
    $cmd->setLogicalId('trip_charging');
    $cmd->setName(__('trip_charging', __FILE__));
    $cmd->setIsVisible(0);
     $cmd->setIsHistorized(0);
   }
    $cmd->setType('info');
    $cmd->setSubType('binary');
    $cmd->setEqLogic_id($this->getId());
    $cmd->setOrder(226);
    $cmd->save();

$cmd = $this->getCmd(null, 'charge_rate');
   if (!is_object($cmd)) {
    $cmd = new teslaCmd();
    $cmd->setLogicalId('charge_rate');
    $cmd->setName(__('charge_rate', __FILE__));
    $cmd->setIsVisible(0);
     $cmd->setIsHistorized(0);
   }
    $cmd->setType('info');
    $cmd->setSubType('numeric');
    $cmd->setEqLogic_id($this->getId());
    $cmd->setOrder(227);
    $cmd->save();

$cmd = $this->getCmd(null, 'charge_port_door_open');
   if (!is_object($cmd)) {
    $cmd = new teslaCmd();
    $cmd->setLogicalId('charge_port_door_open');
    $cmd->setName(__('Trappe ouverte', __FILE__));
    $cmd->setIsVisible(1);
     $cmd->setIsHistorized(1);
   }
    $cmd->setType('info');
    $cmd->setSubType('binary');
    $cmd->setEqLogic_id($this->getId());
    $cmd->setOrder(228);
    $cmd->save();

$cmd = $this->getCmd(null, 'scheduled_charging_start_time');
   if (!is_object($cmd)) {
    $cmd = new teslaCmd();
    $cmd->setLogicalId('scheduled_charging_start_time');
    $cmd->setName(__('Heure de charge planifiée', __FILE__));
    $cmd->setIsVisible(1);
     $cmd->setIsHistorized(0);
   }
    $cmd->setType('info');
    $cmd->setSubType('numeric');
    $cmd->setEqLogic_id($this->getId());
    $cmd->setOrder(229);
    $cmd->save();

$cmd = $this->getCmd(null, 'scheduled_charging_pending');
   if (!is_object($cmd)) {
    $cmd = new teslaCmd();
    $cmd->setLogicalId('scheduled_charging_pending');
    $cmd->setName(__('Charge planifiée', __FILE__));
    $cmd->setIsVisible(1);
     $cmd->setIsHistorized(0);
   }
    $cmd->setType('info');
    $cmd->setSubType('binary');
    $cmd->setEqLogic_id($this->getId());
    $cmd->setOrder(230);
    $cmd->save();

$cmd = $this->getCmd(null, 'user_charge_enable_request');
   if (!is_object($cmd)) {
    $cmd = new teslaCmd();
    $cmd->setLogicalId('user_charge_enable_request');
    $cmd->setName(__('user_charge_enable_request', __FILE__));
    $cmd->setIsVisible(0);
     $cmd->setIsHistorized(0);
   }
    $cmd->setType('info');
    $cmd->setSubType('string');
    $cmd->setEqLogic_id($this->getId());
    $cmd->setOrder(231);
    $cmd->save();

$cmd = $this->getCmd(null, 'charge_enable_request');
   if (!is_object($cmd)) {
    $cmd = new teslaCmd();
    $cmd->setLogicalId('charge_enable_request');
    $cmd->setName(__('charge_enable_request', __FILE__));
    $cmd->setIsVisible(0);
     $cmd->setIsHistorized(0);
   }
    $cmd->setType('info');
    $cmd->setSubType('binary');
    $cmd->setEqLogic_id($this->getId());
    $cmd->setOrder(232);
    $cmd->save();

$cmd = $this->getCmd(null, 'charger_phases');
   if (!is_object($cmd)) {
    $cmd = new teslaCmd();
    $cmd->setLogicalId('charger_phases');
    $cmd->setName(__('Phase du chargeur', __FILE__));
    $cmd->setIsVisible(0);
     $cmd->setIsHistorized(0);
   }
    $cmd->setType('info');
    $cmd->setSubType('string');
    $cmd->setEqLogic_id($this->getId());
    $cmd->setOrder(233);
    $cmd->save();

$cmd = $this->getCmd(null, 'charge_port_latch');
   if (!is_object($cmd)) {
    $cmd = new teslaCmd();
    $cmd->setLogicalId('charge_port_latch');
    $cmd->setName(__('Etat de la prise de charge', __FILE__));
    $cmd->setIsVisible(0);
     $cmd->setIsHistorized(0);
   }
    $cmd->setType('info');
    $cmd->setSubType('string');
    $cmd->setEqLogic_id($this->getId());
    $cmd->setOrder(234);
    $cmd->save();

$cmd = $this->getCmd(null, 'charge_current_request');
   if (!is_object($cmd)) {
    $cmd = new teslaCmd();
    $cmd->setLogicalId('charge_current_request');
    $cmd->setName(__('charge_current_request', __FILE__));
    $cmd->setIsVisible(0);
     $cmd->setIsHistorized(0);
   }
    $cmd->setType('info');
    $cmd->setSubType('numeric');
    $cmd->setEqLogic_id($this->getId());
    $cmd->setOrder(235);
    $cmd->save();

$cmd = $this->getCmd(null, 'charge_current_request_max');
   if (!is_object($cmd)) {
    $cmd = new teslaCmd();
    $cmd->setLogicalId('charge_current_request_max');
    $cmd->setName(__('charge_current_request_max', __FILE__));
    $cmd->setIsVisible(0);
     $cmd->setIsHistorized(0);
   }
    $cmd->setType('info');
    $cmd->setSubType('numeric');
    $cmd->setEqLogic_id($this->getId());
    $cmd->setOrder(236);
    $cmd->save();

$cmd = $this->getCmd(null, 'managed_charging_active');
   if (!is_object($cmd)) {
    $cmd = new teslaCmd();
    $cmd->setLogicalId('managed_charging_active');
    $cmd->setName(__('managed_charging_active', __FILE__));
    $cmd->setIsVisible(0);
     $cmd->setIsHistorized(0);
   }
    $cmd->setType('info');
    $cmd->setSubType('binary');
    $cmd->setEqLogic_id($this->getId());
    $cmd->setOrder(237);
    $cmd->save();

$cmd = $this->getCmd(null, 'managed_charging_user_canceled');
   if (!is_object($cmd)) {
    $cmd = new teslaCmd();
    $cmd->setLogicalId('managed_charging_user_canceled');
    $cmd->setName(__('managed_charging_user_canceled', __FILE__));
    $cmd->setIsVisible(0);
     $cmd->setIsHistorized(0);
   }
    $cmd->setType('info');
    $cmd->setSubType('binary');
    $cmd->setEqLogic_id($this->getId());
    $cmd->setOrder(238);
    $cmd->save();

$cmd = $this->getCmd(null, 'managed_charging_start_time');
   if (!is_object($cmd)) {
    $cmd = new teslaCmd();
    $cmd->setLogicalId('managed_charging_start_time');
    $cmd->setName(__('managed_charging_start_time', __FILE__));
    $cmd->setIsVisible(0);
     $cmd->setIsHistorized(0);
   }
    $cmd->setType('info');
    $cmd->setSubType('string');
    $cmd->setEqLogic_id($this->getId());
    $cmd->setOrder(239);
    $cmd->save();

$cmd = $this->getCmd(null, 'motorized_charge_port');
   if (!is_object($cmd)) {
    $cmd = new teslaCmd();
    $cmd->setLogicalId('motorized_charge_port');
    $cmd->setName(__('Trappe motorisée', __FILE__));
    $cmd->setIsVisible(0);
     $cmd->setIsHistorized(0);
   }
    $cmd->setType('info');
    $cmd->setSubType('binary');
    $cmd->setEqLogic_id($this->getId());
    $cmd->setOrder(240);
    $cmd->save();

$cmd = $this->getCmd(null, 'eu_vehicle');
   if (!is_object($cmd)) {
    $cmd = new teslaCmd();
    $cmd->setLogicalId('eu_vehicle');
    $cmd->setName(__('Véhicule européen', __FILE__));
    $cmd->setIsVisible(0);
     $cmd->setIsHistorized(0);
   }
    $cmd->setType('info');
    $cmd->setSubType('binary');
    $cmd->setEqLogic_id($this->getId());
    $cmd->setOrder(241);
    $cmd->save();

$cmd = $this->getCmd(null, 'api_version');
   if (!is_object($cmd)) {
    $cmd = new teslaCmd();
    $cmd->setLogicalId('api_version');
    $cmd->setName(__('Version API', __FILE__));
    $cmd->setIsVisible(0);
     $cmd->setIsHistorized(0);
   }
    $cmd->setType('info');
    $cmd->setSubType('numeric');
    $cmd->setEqLogic_id($this->getId());
    $cmd->setOrder(301);
    $cmd->save();

$cmd = $this->getCmd(null, 'autopark_state');
   if (!is_object($cmd)) {
    $cmd = new teslaCmd();
    $cmd->setLogicalId('autopark_state');
    $cmd->setName(__('Etat de l autopark v1', __FILE__));
    $cmd->setIsVisible(1);
     $cmd->setIsHistorized(1);
   }
    $cmd->setType('info');
    $cmd->setSubType('numeric');
    $cmd->setEqLogic_id($this->getId());
    $cmd->setOrder(302);
    $cmd->save();

$cmd = $this->getCmd(null, 'autopark_state_v2');
   if (!is_object($cmd)) {
    $cmd = new teslaCmd();
    $cmd->setLogicalId('autopark_state_v2');
    $cmd->setName(__('Etat de l autopark', __FILE__));
    $cmd->setIsVisible(1);
     $cmd->setIsHistorized(1);
   }
    $cmd->setType('info');
    $cmd->setSubType('numeric');
    $cmd->setEqLogic_id($this->getId());
    $cmd->setOrder(303);
    $cmd->save();

$cmd = $this->getCmd(null, 'autopark_style');
   if (!is_object($cmd)) {
    $cmd = new teslaCmd();
    $cmd->setLogicalId('autopark_style');
    $cmd->setName(__('Style de l autopark', __FILE__));
    $cmd->setIsVisible(1);
     $cmd->setIsHistorized(1);
   }
    $cmd->setType('info');
    $cmd->setSubType('numeric');
    $cmd->setEqLogic_id($this->getId());
    $cmd->setOrder(304);
    $cmd->save();

$cmd = $this->getCmd(null, 'calendar_supported');
   if (!is_object($cmd)) {
    $cmd = new teslaCmd();
    $cmd->setLogicalId('calendar_supported');
    $cmd->setName(__('Support calendrier', __FILE__));
    $cmd->setIsVisible(1);
     $cmd->setIsHistorized(1);
   }
    $cmd->setType('info');
    $cmd->setSubType('numeric');
    $cmd->setEqLogic_id($this->getId());
    $cmd->setOrder(305);
    $cmd->save();

$cmd = $this->getCmd(null, 'car_type');
   if (!is_object($cmd)) {
    $cmd = new teslaCmd();
    $cmd->setLogicalId('car_type');
    $cmd->setName(__('Type de voiture', __FILE__));
    $cmd->setIsVisible(1);
     $cmd->setIsHistorized(1);
   }
    $cmd->setType('info');
    $cmd->setSubType('numeric');
    $cmd->setEqLogic_id($this->getId());
    $cmd->setOrder(306);
    $cmd->save();

$cmd = $this->getCmd(null, 'car_version');
   if (!is_object($cmd)) {
    $cmd = new teslaCmd();
    $cmd->setLogicalId('car_version');
    $cmd->setName(__('Version de FW', __FILE__));
    $cmd->setIsVisible(1);
     $cmd->setIsHistorized(1);
   }
    $cmd->setType('info');
    $cmd->setSubType('numeric');
    $cmd->setEqLogic_id($this->getId());
    $cmd->setOrder(307);
    $cmd->save();

$cmd = $this->getCmd(null, 'center_display_state');
   if (!is_object($cmd)) {
    $cmd = new teslaCmd();
    $cmd->setLogicalId('center_display_state');
    $cmd->setName(__('center_display_state', __FILE__));
    $cmd->setIsVisible(1);
     $cmd->setIsHistorized(1);
   }
    $cmd->setType('info');
    $cmd->setSubType('numeric');
    $cmd->setEqLogic_id($this->getId());
    $cmd->setOrder(308);
    $cmd->save();

$cmd = $this->getCmd(null, 'dark_rims');
   if (!is_object($cmd)) {
    $cmd = new teslaCmd();
    $cmd->setLogicalId('dark_rims');
    $cmd->setName(__('dark_rims', __FILE__));
    $cmd->setIsVisible(1);
     $cmd->setIsHistorized(1);
   }
    $cmd->setType('info');
    $cmd->setSubType('numeric');
    $cmd->setEqLogic_id($this->getId());
    $cmd->setOrder(309);
    $cmd->save();

$cmd = $this->getCmd(null, 'df');
   if (!is_object($cmd)) {
    $cmd = new teslaCmd();
    $cmd->setLogicalId('df');
    $cmd->setName(__('Porte conducteur', __FILE__));
    $cmd->setIsVisible(1);
     $cmd->setIsHistorized(1);
   }
    $cmd->setType('info');
    $cmd->setSubType('numeric');
    $cmd->setEqLogic_id($this->getId());
    $cmd->setOrder(310);
    $cmd->save();

$cmd = $this->getCmd(null, 'dr');
   if (!is_object($cmd)) {
    $cmd = new teslaCmd();
    $cmd->setLogicalId('dr');
    $cmd->setName(__('Porte arrière conducteur', __FILE__));
    $cmd->setIsVisible(1);
     $cmd->setIsHistorized(1);
   }
    $cmd->setType('info');
    $cmd->setSubType('numeric');
    $cmd->setEqLogic_id($this->getId());
    $cmd->setOrder(311);
    $cmd->save();

$cmd = $this->getCmd(null, 'exterior_color');
   if (!is_object($cmd)) {
    $cmd = new teslaCmd();
    $cmd->setLogicalId('exterior_color');
    $cmd->setName(__('Couleur extérieure', __FILE__));
    $cmd->setIsVisible(0);
     $cmd->setIsHistorized(0);
   }
    $cmd->setType('info');
    $cmd->setSubType('string');
    $cmd->setEqLogic_id($this->getId());
    $cmd->setOrder(312);
    $cmd->save();

$cmd = $this->getCmd(null, 'ft');
   if (!is_object($cmd)) {
    $cmd = new teslaCmd();
    $cmd->setLogicalId('ft');
    $cmd->setName(__('Coffre avant', __FILE__));
    $cmd->setIsVisible(0);
     $cmd->setIsHistorized(0);
   }
    $cmd->setType('info');
    $cmd->setSubType('numeric');
    $cmd->setEqLogic_id($this->getId());
    $cmd->setOrder(313);
    $cmd->save();

$cmd = $this->getCmd(null, 'has_spoiler');
   if (!is_object($cmd)) {
    $cmd = new teslaCmd();
    $cmd->setLogicalId('has_spoiler');
    $cmd->setName(__('Possède un spoiler', __FILE__));
    $cmd->setIsVisible(0);
     $cmd->setIsHistorized(0);
   }
    $cmd->setType('info');
    $cmd->setSubType('binary');
    $cmd->setEqLogic_id($this->getId());
    $cmd->setOrder(314);
    $cmd->save();

$cmd = $this->getCmd(null, 'homelink_nearby');
   if (!is_object($cmd)) {
    $cmd = new teslaCmd();
    $cmd->setLogicalId('homelink_nearby');
    $cmd->setName(__('Homelink à proximité', __FILE__));
    $cmd->setIsVisible(0);
     $cmd->setIsHistorized(0);
   }
    $cmd->setType('info');
    $cmd->setSubType('binary');
    $cmd->setEqLogic_id($this->getId());
    $cmd->setOrder(315);
    $cmd->save();

$cmd = $this->getCmd(null, 'last_autopark_error');
   if (!is_object($cmd)) {
    $cmd = new teslaCmd();
    $cmd->setLogicalId('last_autopark_error');
    $cmd->setName(__('Dernière erreur de l autopark', __FILE__));
    $cmd->setIsVisible(0);
     $cmd->setIsHistorized(0);
   }
    $cmd->setType('info');
    $cmd->setSubType('string');
    $cmd->setEqLogic_id($this->getId());
    $cmd->setOrder(316);
    $cmd->save();

$cmd = $this->getCmd(null, 'locked');
   if (!is_object($cmd)) {
    $cmd = new teslaCmd();
    $cmd->setLogicalId('locked');
    $cmd->setName(__('Vérouillée', __FILE__));
    $cmd->setIsVisible(1);
     $cmd->setIsHistorized(1);
   }
    $cmd->setType('info');
    $cmd->setSubType('binary');
    $cmd->setEqLogic_id($this->getId());
    $cmd->setOrder(317);
    $cmd->save();

$cmd = $this->getCmd(null, 'notifications_supported');
   if (!is_object($cmd)) {
    $cmd = new teslaCmd();
    $cmd->setLogicalId('notifications_supported');
    $cmd->setName(__('Notifications activées', __FILE__));
    $cmd->setIsVisible(0);
     $cmd->setIsHistorized(0);
   }
    $cmd->setType('info');
    $cmd->setSubType('binary');
    $cmd->setEqLogic_id($this->getId());
    $cmd->setOrder(318);
    $cmd->save();

$cmd = $this->getCmd(null, 'odometer');
   if (!is_object($cmd)) {
    $cmd = new teslaCmd();
    $cmd->setLogicalId('odometer');
    $cmd->setName(__('Kilométrage', __FILE__));
    $cmd->setIsVisible(1);
     $cmd->setIsHistorized(1);
   }
    $cmd->setType('info');
    $cmd->setSubType('numeric');
    $cmd->setEqLogic_id($this->getId());
    $cmd->setOrder(319);
    $cmd->save();

$cmd = $this->getCmd(null, 'parsed_calendar_supported');
   if (!is_object($cmd)) {
    $cmd = new teslaCmd();
    $cmd->setLogicalId('parsed_calendar_supported');
    $cmd->setName(__('parsed_calendar_supported', __FILE__));
    $cmd->setIsVisible(0);
     $cmd->setIsHistorized(0);
   }
    $cmd->setType('info');
    $cmd->setSubType('binary');
    $cmd->setEqLogic_id($this->getId());
    $cmd->setOrder(320);
    $cmd->save();

$cmd = $this->getCmd(null, 'perf_config');
   if (!is_object($cmd)) {
    $cmd = new teslaCmd();
    $cmd->setLogicalId('perf_config');
    $cmd->setName(__('Config Performance', __FILE__));
    $cmd->setIsVisible(0);
     $cmd->setIsHistorized(0);
   }
    $cmd->setType('info');
    $cmd->setSubType('string');
    $cmd->setEqLogic_id($this->getId());
    $cmd->setOrder(321);
    $cmd->save();

$cmd = $this->getCmd(null, 'pf');
   if (!is_object($cmd)) {
    $cmd = new teslaCmd();
    $cmd->setLogicalId('pf');
    $cmd->setName(__('Porte passager', __FILE__));
    $cmd->setIsVisible(0);
     $cmd->setIsHistorized(0);
   }
    $cmd->setType('info');
    $cmd->setSubType('numeric');
    $cmd->setEqLogic_id($this->getId());
    $cmd->setOrder(322);
    $cmd->save();

$cmd = $this->getCmd(null, 'pr');
   if (!is_object($cmd)) {
    $cmd = new teslaCmd();
    $cmd->setLogicalId('pr');
    $cmd->setName(__('Porte arrière passager', __FILE__));
    $cmd->setIsVisible(0);
     $cmd->setIsHistorized(0);
   }
    $cmd->setType('info');
    $cmd->setSubType('numeric');
    $cmd->setEqLogic_id($this->getId());
    $cmd->setOrder(323);
    $cmd->save();

$cmd = $this->getCmd(null, 'rear_seat_heaters');
   if (!is_object($cmd)) {
    $cmd = new teslaCmd();
    $cmd->setLogicalId('rear_seat_heaters');
    $cmd->setName(__('rear_seat_heaters', __FILE__));
    $cmd->setIsVisible(0);
     $cmd->setIsHistorized(0);
   }
    $cmd->setType('info');
    $cmd->setSubType('numeric');
    $cmd->setEqLogic_id($this->getId());
    $cmd->setOrder(324);
    $cmd->save();

$cmd = $this->getCmd(null, 'rear_seat_type');
   if (!is_object($cmd)) {
    $cmd = new teslaCmd();
    $cmd->setLogicalId('rear_seat_type');
    $cmd->setName(__('rear_seat_type', __FILE__));
    $cmd->setIsVisible(0);
     $cmd->setIsHistorized(0);
   }
    $cmd->setType('info');
    $cmd->setSubType('numeric');
    $cmd->setEqLogic_id($this->getId());
    $cmd->setOrder(325);
    $cmd->save();

$cmd = $this->getCmd(null, 'remote_start');
   if (!is_object($cmd)) {
    $cmd = new teslaCmd();
    $cmd->setLogicalId('remote_start');
    $cmd->setName(__('Démarrage à distance', __FILE__));
    $cmd->setIsVisible(0);
     $cmd->setIsHistorized(0);
   }
    $cmd->setType('info');
    $cmd->setSubType('binary');
    $cmd->setEqLogic_id($this->getId());
    $cmd->setOrder(326);
    $cmd->save();

$cmd = $this->getCmd(null, 'remote_start_supported');
   if (!is_object($cmd)) {
    $cmd = new teslaCmd();
    $cmd->setLogicalId('remote_start_supported');
    $cmd->setName(__('Démarrage à distance possible', __FILE__));
    $cmd->setIsVisible(0);
     $cmd->setIsHistorized(0);
   }
    $cmd->setType('info');
    $cmd->setSubType('binary');
    $cmd->setEqLogic_id($this->getId());
    $cmd->setOrder(327);
    $cmd->save();

$cmd = $this->getCmd(null, 'rhd');
   if (!is_object($cmd)) {
    $cmd = new teslaCmd();
    $cmd->setLogicalId('rhd');
    $cmd->setName(__('Volant à droite', __FILE__));
    $cmd->setIsVisible(0);
     $cmd->setIsHistorized(0);
   }
    $cmd->setType('info');
    $cmd->setSubType('binary');
    $cmd->setEqLogic_id($this->getId());
    $cmd->setOrder(328);
    $cmd->save();

$cmd = $this->getCmd(null, 'roof_color');
   if (!is_object($cmd)) {
    $cmd = new teslaCmd();
    $cmd->setLogicalId('roof_color');
    $cmd->setName(__('Couleur du toit', __FILE__));
    $cmd->setIsVisible(0);
     $cmd->setIsHistorized(0);
   }
    $cmd->setType('info');
    $cmd->setSubType('string');
    $cmd->setEqLogic_id($this->getId());
    $cmd->setOrder(329);
    $cmd->save();

$cmd = $this->getCmd(null, 'rt');
   if (!is_object($cmd)) {
    $cmd = new teslaCmd();
    $cmd->setLogicalId('rt');
    $cmd->setName(__('Coffre arrière', __FILE__));
    $cmd->setIsVisible(0);
     $cmd->setIsHistorized(0);
   }
    $cmd->setType('info');
    $cmd->setSubType('numeric');
    $cmd->setEqLogic_id($this->getId());
    $cmd->setOrder(330);
    $cmd->save();

$cmd = $this->getCmd(null, 'seat_type');
   if (!is_object($cmd)) {
    $cmd = new teslaCmd();
    $cmd->setLogicalId('seat_type');
    $cmd->setName(__('type de siège', __FILE__));
    $cmd->setIsVisible(0);
     $cmd->setIsHistorized(0);
   }
    $cmd->setType('info');
    $cmd->setSubType('numeric');
    $cmd->setEqLogic_id($this->getId());
    $cmd->setOrder(331);
    $cmd->save();

$cmd = $this->getCmd(null, 'spoiler_type');
   if (!is_object($cmd)) {
    $cmd = new teslaCmd();
    $cmd->setLogicalId('spoiler_type');
    $cmd->setName(__('Type de spoiler', __FILE__));
    $cmd->setIsVisible(0);
     $cmd->setIsHistorized(0);
   }
    $cmd->setType('info');
    $cmd->setSubType('string');
    $cmd->setEqLogic_id($this->getId());
    $cmd->setOrder(332);
    $cmd->save();

$cmd = $this->getCmd(null, 'sun_roof_installed');
   if (!is_object($cmd)) {
    $cmd = new teslaCmd();
    $cmd->setLogicalId('sun_roof_installed');
    $cmd->setName(__('Toit panoramique', __FILE__));
    $cmd->setIsVisible(0);
     $cmd->setIsHistorized(0);
   }
    $cmd->setType('info');
    $cmd->setSubType('numeric');
    $cmd->setEqLogic_id($this->getId());
    $cmd->setOrder(333);
    $cmd->save();

$cmd = $this->getCmd(null, 'sun_roof_percent_open');
   if (!is_object($cmd)) {
    $cmd = new teslaCmd();
    $cmd->setLogicalId('sun_roof_percent_open');
    $cmd->setName(__('Toit panoramique ouvert', __FILE__));
    $cmd->setIsVisible(0);
     $cmd->setIsHistorized(0);
   }
    $cmd->setType('info');
    $cmd->setSubType('string');
    $cmd->setEqLogic_id($this->getId());
    $cmd->setOrder(334);
    $cmd->save();

$cmd = $this->getCmd(null, 'sun_roof_state');
   if (!is_object($cmd)) {
    $cmd = new teslaCmd();
    $cmd->setLogicalId('sun_roof_state');
    $cmd->setName(__('Etat du toit panoramique', __FILE__));
    $cmd->setIsVisible(0);
     $cmd->setIsHistorized(0);
   }
    $cmd->setType('info');
    $cmd->setSubType('string');
    $cmd->setEqLogic_id($this->getId());
    $cmd->setOrder(335);
    $cmd->save();

$cmd = $this->getCmd(null, 'third_row_seats');
   if (!is_object($cmd)) {
    $cmd = new teslaCmd();
    $cmd->setLogicalId('third_row_seats');
    $cmd->setName(__('3eme rangée de siège', __FILE__));
    $cmd->setIsVisible(0);
     $cmd->setIsHistorized(0);
   }
    $cmd->setType('info');
    $cmd->setSubType('string');
    $cmd->setEqLogic_id($this->getId());
    $cmd->setOrder(336);
    $cmd->save();

$cmd = $this->getCmd(null, 'valet_mode');
   if (!is_object($cmd)) {
    $cmd = new teslaCmd();
    $cmd->setLogicalId('valet_mode');
    $cmd->setName(__('Mode voiturier', __FILE__));
    $cmd->setIsVisible(0);
     $cmd->setIsHistorized(0);
   }
    $cmd->setType('info');
    $cmd->setSubType('binary');
    $cmd->setEqLogic_id($this->getId());
    $cmd->setOrder(338);
    $cmd->save();

$cmd = $this->getCmd(null, 'valet_pin_needed');
   if (!is_object($cmd)) {
    $cmd = new teslaCmd();
    $cmd->setLogicalId('valet_pin_needed');
    $cmd->setName(__('Code voiturier obligatoire', __FILE__));
    $cmd->setIsVisible(0);
     $cmd->setIsHistorized(0);
   }
    $cmd->setType('info');
    $cmd->setSubType('binary');
    $cmd->setEqLogic_id($this->getId());
    $cmd->setOrder(339);
    $cmd->save();

$cmd = $this->getCmd(null, 'vehicle_name');
   if (!is_object($cmd)) {
    $cmd = new teslaCmd();
    $cmd->setLogicalId('vehicle_name');
    $cmd->setName(__('Nom du véhicule', __FILE__));
    $cmd->setIsVisible(0);
     $cmd->setIsHistorized(0);
   }
    $cmd->setType('info');
    $cmd->setSubType('string');
    $cmd->setEqLogic_id($this->getId());
    $cmd->setOrder(340);
    $cmd->save();

$cmd = $this->getCmd(null, 'wheel_type');
   if (!is_object($cmd)) {
    $cmd = new teslaCmd();
    $cmd->setLogicalId('wheel_type');
    $cmd->setName(__('Type de jante', __FILE__));
    $cmd->setIsVisible(0);
     $cmd->setIsHistorized(0);
   }
    $cmd->setType('info');
    $cmd->setSubType('string');
    $cmd->setEqLogic_id($this->getId());
    $cmd->setOrder(341);
    $cmd->save();

$cmd = $this->getCmd(null, 'shift_state');
   if (!is_object($cmd)) {
    $cmd = new teslaCmd();
    $cmd->setLogicalId('shift_state');
    $cmd->setName(__('Vitesse actuellement sélectionnée', __FILE__));
    $cmd->setIsVisible(0);
     $cmd->setIsHistorized(0);
   }
    $cmd->setType('info');
    $cmd->setSubType('string');
    $cmd->setEqLogic_id($this->getId());
    $cmd->setOrder(401);
    $cmd->save();

$cmd = $this->getCmd(null, 'speed');
   if (!is_object($cmd)) {
    $cmd = new teslaCmd();
    $cmd->setLogicalId('speed');
    $cmd->setName(__('Vitesse', __FILE__));
    $cmd->setIsVisible(0);
     $cmd->setIsHistorized(0);
   }
    $cmd->setType('info');
    $cmd->setSubType('string');
    $cmd->setEqLogic_id($this->getId());
    $cmd->setOrder(402);
    $cmd->save();

$cmd = $this->getCmd(null, 'power');
   if (!is_object($cmd)) {
    $cmd = new teslaCmd();
    $cmd->setLogicalId('power');
    $cmd->setName(__('Puissance', __FILE__));
    $cmd->setIsVisible(0);
     $cmd->setIsHistorized(0);
   }
    $cmd->setType('info');
    $cmd->setSubType('numeric');
    $cmd->setEqLogic_id($this->getId());
    $cmd->setOrder(403);
    $cmd->save();

$cmd = $this->getCmd(null, 'latitude');
   if (!is_object($cmd)) {
    $cmd = new teslaCmd();
    $cmd->setLogicalId('latitude');
    $cmd->setName(__('Latitude', __FILE__));
    $cmd->setIsVisible(0);
     $cmd->setIsHistorized(0);
   }
    $cmd->setType('info');
    $cmd->setSubType('numeric');
    $cmd->setEqLogic_id($this->getId());
    $cmd->setOrder(404);
    $cmd->save();

$cmd = $this->getCmd(null, 'longitude');
   if (!is_object($cmd)) {
    $cmd = new teslaCmd();
    $cmd->setLogicalId('longitude');
    $cmd->setName(__('Longitude', __FILE__));
    $cmd->setIsVisible(1);
     $cmd->setIsHistorized(1);
   }
    $cmd->setType('info');
    $cmd->setSubType('numeric');
    $cmd->setEqLogic_id($this->getId());
    $cmd->setOrder(405);
    $cmd->save();

$cmd = $this->getCmd(null, 'heading');
   if (!is_object($cmd)) {
    $cmd = new teslaCmd();
    $cmd->setLogicalId('heading');
    $cmd->setName(__('Direction', __FILE__));
    $cmd->setIsVisible(1);
     $cmd->setIsHistorized(1);
   }
    $cmd->setType('info');
    $cmd->setSubType('numeric');
    $cmd->setEqLogic_id($this->getId());
    $cmd->setOrder(402);
    $cmd->save();

$cmd = $this->getCmd(null, 'gps_as_of');
   if (!is_object($cmd)) {
    $cmd = new teslaCmd();
    $cmd->setLogicalId('gps_as_of');
    $cmd->setName(__('gps_as_of', __FILE__));
    $cmd->setIsVisible(1);
     $cmd->setIsHistorized(1);
   }
    $cmd->setType('info');
    $cmd->setSubType('numeric');
    $cmd->setEqLogic_id($this->getId());
    $cmd->setOrder(403);
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


