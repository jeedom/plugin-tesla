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
	/************** API TESLA **************/
	public static function recupToken(){
		// ************* DEBUT DES VARIABLES
		$grant_type="password"; // information lié à l'appel API, NE PAS MODIFIER
		$client_id="81527cff06843c8634fdc09e8ac0abefb46ac849f38fe1e431c2ef2106796384"; // information lié à l'appel API, NE PAS MODIFIER
		$client_secret="c7257eb71a564034f9419ee651c7d0e5f7aa6bfbd18bafb5c5c033b093bb2fa3"; // information lié à l'appel API, NE PAS MODIFIER
		$email = config::byKey('username', 'tesla');
		$password = config::byKey('password', 'tesla');

		$my_file=fopen("/var/www/html/plugins/tesla/data/Tesla_Token.json", 'w');
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
	
	public static function scantesla(){
		tesla::recupToken()
	}
	
	/*** ****/
	
	public static function modele($product){
		//PRODUCT $produit
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
