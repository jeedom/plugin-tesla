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
	public static function addDevice($mac,$ip,$name,$vendor,$product,$version,$port,$group) {
		log::add('tesla', 'info', 'Produit déterminé via ID : '.tesla::modele($product), 'config');
		$eqLogic = new eqLogic();
		$eqLogic->setEqType_name('tesla');
		$eqLogic->setCategory('light', '1');
		if($name == "" || $name == null){
			$eqLogic->setName('tesla '.rand(1, 20000));
		}else{
			$eqLogic->setName('tesla '.$name);
		}
		$eqLogic->setLogicalId($mac);
		$eqLogic->setConfiguration('mac', $mac);
		$eqLogic->setConfiguration('ip', $ip);
		$eqLogic->setConfiguration('vendor', $vendor);
		$eqLogic->setConfiguration('product', $product);
		$eqLogic->setConfiguration('version', $version);
		$eqLogic->setConfiguration('port', $port);
		$eqLogic->setConfiguration('modele',tesla::modele($product));
		$eqLogic->setIsVisible(1);
		$eqLogic->setIsEnable(0);
		$eqLogic->save();
		$tesla_path = realpath(dirname(__FILE__).'/../../resources');
		$command = '/usr/bin/python '. $tesla_path .'/action_tesla.py --mac='.$mac.' --ip='.$ip;
		$command .= " --action=blink";
		shell_exec($command);
	}
	
	public static function majDevice($id,$mac,$ip,$name,$vendor,$product,$version,$port,$group){
		log::add('tesla', 'info', 'Produit déterminé via ID : '.tesla::modele($product), 'config');
		$eqLogic = eqLogic::byId($id);
		if($name == "" || $name == null){
		}else{
			$eqLogic->setName('tesla '.$name);
		}
		$eqLogic->setConfiguration('ip', $ip);
		$eqLogic->setConfiguration('vendor', $vendor);
		$eqLogic->setConfiguration('product', $product);
		$eqLogic->setConfiguration('version', $version);
		$eqLogic->setConfiguration('port', $port);
		$eqLogic->setConfiguration('modele',tesla::modele($product));
		$eqLogic->save();
	}
	
	public static function scantesla(){
		//SCANNER TESLA
	}
	
	public static function modele($product){
		//PRODUCT $produit
	}

	public static function colorrvb($hue,$saturation,$kelvin){
		$tesla_path = realpath(dirname(__FILE__).'/../../3rdparty');
		include($tesla_path.'/rgb_hsl_converter.inc.php');
		
		$hue = $hue/65535;
		$saturation = $saturation/65535;
		$kelvin = ($kelvin/6500)-2500;
		
		$color_HSL = array($hue,$saturation,$kelvin);
		
		$color_RVB = hsl2hex($color_HSL);
		
		return $color_RVB;
	}
	
	public static function create_cmd($id,$hue,$saturation,$luminosity,$kelvin){
		$eqLogic = eqLogic::byId($id);
		$new_name = 'Couleur '.tesla::colorrvb($hue,$saturation,$kelvin);
		$cmd = new teslaCmd();
		$cmd->setType('action');
		$cmd->setSubType('other');
		$cmd->setDisplay('generic_type', 'LIGHT_MODE');
		$cmd->setName(__($new_name, __FILE__));
		$cmd->setConfiguration('mode','set');
		$cmd->setConfiguration('hue',$hue);
		$cmd->setConfiguration('saturation',$saturation);
		$cmd->setConfiguration('luminosity',$luminosity);
		$cmd->setConfiguration('kelvin',$kelvin);
		$cmd->setIsVisible(1);
		$cmd->setEqLogic_id($eqLogic->getId());
		$cmd->save();
		
		
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
		
	    $cmd = $this->getCmd(null, 'on');
        if (!is_object($cmd)) {
			$cmd = new teslaCmd();
			$cmd->setLogicalId('on');
			$cmd->setName(__('On', __FILE__));
			$cmd->setIsVisible(0);
		}
		$cmd->setOrder(2);
		$cmd->setType('action');
		$cmd->setSubType('other');
		$cmd->setDisplay('generic_type', 'LIGHT_ON');
		$cmd->setEqLogic_id($this->getId());
		$cmd->setValue($luminosity_id);
		$cmd->save();

		$cmd = $this->getCmd(null, 'off');
		if (!is_object($cmd)) {
			$cmd = new teslaCmd();
			$cmd->setLogicalId('off');
			$cmd->setName(__('Off', __FILE__));
			$cmd->setIsVisible(0);
		}
		$cmd->setOrder(3);
		$cmd->setType('action');
		$cmd->setSubType('other');
		$cmd->setDisplay('generic_type', 'LIGHT_OFF');
		$cmd->setEqLogic_id($this->getId());
		$cmd->setValue($luminosity_id);
		$cmd->save();
		
		$cmd = $this->getCmd(null, 'luminosity');
		if (!is_object($cmd)) {
			$cmd = new teslaCmd();
			$cmd->setLogicalId('luminosity');
			$cmd->setIsVisible(1);
			$cmd->setName(__('Luminosité', __FILE__));
			$cmd->setTemplate('dashboard', 'light');
			$cmd->setTemplate('mobile', 'light');
		}
		$cmd->setOrder(4);
		$cmd->setType('action');
		$cmd->setSubType('slider');
		$cmd->setConfiguration('minValue', '0');
		$cmd->setConfiguration('maxValue', '100');
		$cmd->setEqLogic_id($this->getId());
		$cmd->setDisplay('generic_type', 'LIGHT_SLIDER');
		$cmd->setValue($luminosity_id);
		$cmd->save();
		
		if(tesla::ColorHi($this->getConfiguration('product')) == true){
			$cmd = $this->getCmd(null, 'color_state');
			if (!is_object($cmd)) {
				$cmd = new teslaCmd();
				$cmd->setLogicalId('color_state');
				$cmd->setName(__('Etat Couleur', __FILE__));
				$cmd->setIsVisible(0);
			}
			$cmd->setOrder(5);
			$cmd->setType('info');
			$cmd->setSubType('string');
			$cmd->setDisplay('generic_type', 'LIGHT_COLOR');
			$cmd->setEqLogic_id($this->getId());
			$cmd->save();
			$color_id = $cmd->getId();
	
			$cmd = $this->getCmd(null, 'color');
			if (!is_object($cmd)) {
				$cmd = new teslaCmd();
				$cmd->setLogicalId('color');
				$cmd->setName(__('Couleur', __FILE__));
			}
			$cmd->setOrder(6);
			$cmd->setType('action');
			$cmd->setSubType('color');
			$cmd->setDisplay('generic_type', 'LIGHT_SET_COLOR');
			$cmd->setEqLogic_id($this->getId());
			$cmd->setValue($color_id);
			$cmd->save();
			
			// MODE
			$cmd = $this->getCmd(null, 'Intense');
			if (!is_object($cmd)) {
				$cmd = new teslaCmd();
				$cmd->setLogicalId('intense');
				$cmd->setName(__('Intense', __FILE__));
				$cmd->setIsVisible(1);
				$cmd->setOrder(10);
			}
			$cmd->setType('action');
			$cmd->setSubType('other');
			$cmd->setDisplay('generic_type', 'LIGHT_MODE');
			$cmd->setConfiguration('mode','intense');
			$cmd->setEqLogic_id($this->getId());
			$cmd->save();
			$cmd = $this->getCmd(null, 'soothing');
			if (!is_object($cmd)) {
				$cmd = new teslaCmd();
				$cmd->setLogicalId('soothing');
				$cmd->setName(__('Soothing', __FILE__));
				$cmd->setIsVisible(1);
				$cmd->setOrder(11);
			}
			$cmd->setType('action');
			$cmd->setSubType('other');
			$cmd->setDisplay('generic_type', 'LIGHT_MODE');
			$cmd->setConfiguration('mode','soothing');
			$cmd->setEqLogic_id($this->getId());
			$cmd->save();
			$cmd = $this->getCmd(null, 'tranquil');
			if (!is_object($cmd)) {
				$cmd = new teslaCmd();
				$cmd->setLogicalId('tranquil');
				$cmd->setName(__('Tranquil', __FILE__));
				$cmd->setIsVisible(1);
				$cmd->setOrder(12);
			}
			$cmd->setType('action');
			$cmd->setSubType('other');
			$cmd->setDisplay('generic_type', 'LIGHT_MODE');
			$cmd->setConfiguration('mode','tranquil');
			$cmd->setEqLogic_id($this->getId());
			$cmd->save();
			$cmd = $this->getCmd(null, 'exciting');
			if (!is_object($cmd)) {
				$cmd = new teslaCmd();
				$cmd->setLogicalId('exciting');
				$cmd->setName(__('Exciting', __FILE__));
				$cmd->setIsVisible(1);
				$cmd->setOrder(13);
			}
			$cmd->setType('action');
			$cmd->setSubType('other');
			$cmd->setDisplay('generic_type', 'LIGHT_MODE');
			$cmd->setConfiguration('mode','exciting');
			$cmd->setEqLogic_id($this->getId());
			$cmd->save();
			$cmd = $this->getCmd(null, 'cheerful');
			if (!is_object($cmd)) {
				$cmd = new teslaCmd();
				$cmd->setLogicalId('cheerful');
				$cmd->setName(__('Cheerful', __FILE__));
				$cmd->setIsVisible(1);
				$cmd->setOrder(14);
			}
			$cmd->setType('action');
			$cmd->setSubType('other');
			$cmd->setDisplay('generic_type', 'LIGHT_MODE');
			$cmd->setConfiguration('mode','cheerful');
			$cmd->setEqLogic_id($this->getId());
			$cmd->save();
			$cmd = $this->getCmd(null, 'focusing');
			if (!is_object($cmd)) {
				$cmd = new teslaCmd();
				$cmd->setLogicalId('focusing');
				$cmd->setName(__('Focusing', __FILE__));
				$cmd->setIsVisible(1);
				$cmd->setOrder(15);
			}
			$cmd->setType('action');
			$cmd->setSubType('other');
			$cmd->setDisplay('generic_type', 'LIGHT_MODE');
			$cmd->setConfiguration('mode','focusing');
			$cmd->setEqLogic_id($this->getId());
			$cmd->save();
			$cmd = $this->getCmd(null, 'blissful');
			if (!is_object($cmd)) {
				$cmd = new teslaCmd();
				$cmd->setLogicalId('blissful');
				$cmd->setName(__('Blissful', __FILE__));
				$cmd->setIsVisible(1);
				$cmd->setOrder(16);
			}
			$cmd->setType('action');
			$cmd->setSubType('other');
			$cmd->setDisplay('generic_type', 'LIGHT_MODE');
			$cmd->setConfiguration('mode','blissful');
			$cmd->setEqLogic_id($this->getId());
			$cmd->save();
			$cmd = $this->getCmd(null, 'powerful');
			if (!is_object($cmd)) {
				$cmd = new teslaCmd();
				$cmd->setLogicalId('powerful');
				$cmd->setName(__('Powerful', __FILE__));
				$cmd->setIsVisible(1);
				$cmd->setOrder(17);
			}
			$cmd->setType('action');
			$cmd->setSubType('other');
			$cmd->setDisplay('generic_type', 'LIGHT_MODE');
			$cmd->setConfiguration('mode','powerful');
			$cmd->setEqLogic_id($this->getId());
			$cmd->save();
			$cmd = $this->getCmd(null, 'warning');
			if (!is_object($cmd)) {
				$cmd = new teslaCmd();
				$cmd->setLogicalId('warning');
				$cmd->setName(__('Warning', __FILE__));
				$cmd->setIsVisible(1);
				$cmd->setOrder(18);
			}
			$cmd->setType('action');
			$cmd->setSubType('other');
			$cmd->setDisplay('generic_type', 'LIGHT_MODE');
			$cmd->setConfiguration('mode','warning');
			$cmd->setEqLogic_id($this->getId());
			$cmd->save();
			$cmd = $this->getCmd(null, 'dream');
			if (!is_object($cmd)) {
				$cmd = new teslaCmd();
				$cmd->setLogicalId('dream');
				$cmd->setName(__('Dream', __FILE__));
				$cmd->setIsVisible(1);
				$cmd->setOrder(19);
			}
			$cmd->setType('action');
			$cmd->setSubType('other');
			$cmd->setDisplay('generic_type', 'LIGHT_MODE');
			$cmd->setConfiguration('mode','dream');
			$cmd->setEqLogic_id($this->getId());
			$cmd->save();
			$cmd = $this->getCmd(null, 'serene');
			if (!is_object($cmd)) {
				$cmd = new teslaCmd();
				$cmd->setLogicalId('serene');
				$cmd->setName(__('Serene', __FILE__));
				$cmd->setIsVisible(1);
				$cmd->setOrder(20);
			}
			$cmd->setType('action');
			$cmd->setSubType('other');
			$cmd->setDisplay('generic_type', 'LIGHT_MODE');
			$cmd->setConfiguration('mode','serene');
			$cmd->setEqLogic_id($this->getId());
			$cmd->save();
			$cmd = $this->getCmd(null, 'halloween');
			if (!is_object($cmd)) {
				$cmd = new teslaCmd();
				$cmd->setLogicalId('halloween');
				$cmd->setName(__('Halloween', __FILE__));
				$cmd->setIsVisible(1);
				$cmd->setOrder(21);
			}
			$cmd->setType('action');
			$cmd->setSubType('other');
			$cmd->setDisplay('generic_type', 'LIGHT_MODE');
			$cmd->setConfiguration('mode','halloween');
			$cmd->setEqLogic_id($this->getId());
			$cmd->save();
			$cmd = $this->getCmd(null, 'relaxing');
			if (!is_object($cmd)) {
				$cmd = new teslaCmd();
				$cmd->setLogicalId('relaxing');
				$cmd->setName(__('Relaxing', __FILE__));
				$cmd->setIsVisible(1);
				$cmd->setOrder(22);
			}
			$cmd->setType('action');
			$cmd->setSubType('other');
			$cmd->setDisplay('generic_type', 'LIGHT_MODE');
			$cmd->setConfiguration('mode','relaxing');
			$cmd->setEqLogic_id($this->getId());
			$cmd->save();
			$cmd = $this->getCmd(null, 'peaceful');
			if (!is_object($cmd)) {
				$cmd = new teslaCmd();
				$cmd->setLogicalId('peaceful');
				$cmd->setName(__('Peaceful', __FILE__));
				$cmd->setIsVisible(1);
				$cmd->setOrder(23);
			}
			$cmd->setType('action');
			$cmd->setSubType('other');
			$cmd->setDisplay('generic_type', 'LIGHT_MODE');
			$cmd->setConfiguration('mode','peaceful');
			$cmd->setEqLogic_id($this->getId());
			$cmd->save();
			$cmd = $this->getCmd(null, 'energizing');
			if (!is_object($cmd)) {
				$cmd = new teslaCmd();
				$cmd->setLogicalId('energizing');
				$cmd->setName(__('Energizing', __FILE__));
				$cmd->setIsVisible(1);
				$cmd->setOrder(24);
			}
			$cmd->setType('action');
			$cmd->setSubType('other');
			$cmd->setDisplay('generic_type', 'LIGHT_MODE');
			$cmd->setConfiguration('mode','energizing');
			$cmd->setEqLogic_id($this->getId());
			$cmd->save();
		}else{
			$cmd = $this->getCmd(null, 'color_state');
			if (is_object($cmd)) {
				$cmd->remove();
			}
			$cmd = $this->getCmd(null, 'color');
			if (is_object($cmd)) {
				$cmd->remove();
			}
		}
		$cmd = $this->getCmd(null, 'create_light_color');
			if (!is_object($cmd)) {
				$cmd = new teslaCmd();
				$cmd->setLogicalId('create_light_color');
				$cmd->setName(__('Nouvelle couleur', __FILE__));
				$cmd->setIsVisible(0);
				$cmd->setOrder(99);
			}
			$cmd->setType('action');
			$cmd->setSubType('other');
			$cmd->setDisplay('generic_type', 'LIGHT_MODE');
			$cmd->setEqLogic_id($this->getId());
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
    
    public function execute($_options = array()){
    	$eqLogic = $this->getEqLogic();
        if ($this->getType() != 'action') {
			return;
		}
		
		log::add('tesla', 'debug', 'Action :'.$eqLogic->getConfiguration('mac').' // '.$eqLogic->getConfiguration('ip').' pour > '.$this->getLogicalId(), 'config');
		
		$tesla_path = realpath(dirname(__FILE__).'/../../resources');
		$command = '/usr/bin/python '. $tesla_path .'/action_tesla.py --mac='.$eqLogic->getConfiguration('mac').' --ip='.$eqLogic->getConfiguration('ip');
		switch ($this->getLogicalId()) {
			case 'on':
				$command .= ' --action=power';
				$command .= ' --power=on';
				log::add('tesla', 'debug', 'Action : ON transmis au python ', 'config');
			break;
			case 'off':
				$command .= ' --action=power';
				$command .= ' --power=off';
				log::add('tesla', 'debug', 'Action : OFF transmis au python ', 'config');
			break;
			case 'luminosity':
				if($this->getsubType() == 'slider'){
					log::add('tesla', 'debug', $_options['slider'], 'config');
					if($_options['slider'] == 0){
						$command .= ' --action=power';
						$command .= ' --power=off';
						log::add('tesla', 'debug', 'Action : OFF transmis au python ', 'config');
					}else{
						$command .= ' --action=luminosity';
						$command .= ' --luminosity='.tesla::luminosity_index($_options['slider']);
					}
				}
			break;
			case 'color':
				if($_options['color'] == "#000000"){
					$command .= ' --action=power';
					$command .= ' --power=off';
					log::add('tesla', 'debug', 'Action : OFF transmis au python ', 'config');
				}else{
					log::add('tesla', 'debug', 'Color : '.$_options['color'], 'config');
					$color_HSL = tesla::colorhsl($_options['color']);
					log::add('tesla', 'debug', 'Color HSL : '.$color_HSL[0].','.$color_HSL[1].','.$color_HSL[2], 'config');
					$command .= " --action=color";
					$command .= $color_HSL;
				}
			break;
			case 'create_light_color':
				$command .= " --action=create";
			break;
		}
		
		if($this->getConfiguration('mode') !== ''){
			$mode = $this->getConfiguration('mode');
			log::add('tesla', 'debug', 'Mode : '.$this->getName(), 'config');
			$command .= " --action=mode";
				if($mode == 'set'){
					log::add('tesla', 'debug', 'Mode : '.$color, 'config');
					$command .= " --luminosity=".$this->getConfiguration('luminosity');
					$command .= " --hue=".$this->getConfiguration('hue');
					$command .= " --saturation=".$this->getConfiguration('saturation');
					$command .= " --kelvin=".$this->getConfiguration('kelvin');
					$command .= " --mode=".$mode;
				}else{
					log::add('tesla', 'debug', 'Mode : '.$mode, 'config');
					$command .= " --mode=".$mode;
				}
		}
		
		log::add('tesla', 'debug', $command, 'config');
		shell_exec($command);
		
    }

    /*     * **********************Getteur Setteur*************************** */
}

?>
