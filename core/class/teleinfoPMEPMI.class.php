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

require_once __DIR__  . '/../../../../core/php/core.inc.php';

class teleinfoPMEPMI extends eqLogic {

  public static function deamon_info() {
    $return = array();
    $return['log'] = __CLASS__;
    $return['state'] = 'nok';
    $pid_file = jeedom::getTmpFolder(__CLASS__) . '/teleinfoPMEPMId.pid';
    if (file_exists($pid_file)) {
      if (@posix_getsid(trim(file_get_contents($pid_file)))) {
        $return['state'] = 'ok';
      } else {
        shell_exec(system::getCmdSudo() . 'rm -rf ' . $pid_file . ' 2>&1 > /dev/null');
      }
    }
    $return['launchable'] = 'ok';
    $port = config::byKey('port', __CLASS__);
    if ($port != 'auto') {
      $port = jeedom::getUsbMapping($port);
      if (@!file_exists($port)) {
        $return['launchable'] = 'nok';
        $return['launchable_message'] = __("Le port n'est pas configuré", __FILE__);
      }
      exec(system::getCmdSudo() . 'chmod 777 ' . $port . ' > /dev/null 2>&1');
    }
    return $return;
  }

  public static function deamon_start() {
    self::deamon_stop();
    $deamon_info = self::deamon_info();
    if ($deamon_info['launchable'] != 'ok') {
      throw new Exception(__('Veuillez vérifier la configuration', __FILE__));
    }
    $port = jeedom::getUsbMapping(config::byKey('port', __CLASS__));
    $serialrate = (strpos($port, 'ttyACM') !== false) ? 115200 : 1200;

    $cmd = '/usr/bin/python3 ' . realpath(__DIR__ . '/../../resources/teleinfoPMEPMId') . '/teleinfoPMEPMId.py';
    $cmd .= ' --device ' . $port;
    $cmd .= ' --serialrate ' . $serialrate;
    $cmd .= ' --loglevel ' . log::convertLogLevel(log::getLogLevel(__CLASS__));
    $cmd .= ' --callback ' . network::getNetworkAccess('internal', 'proto:127.0.0.1:port:comp') . '/plugins/teleinfoPMEPMI/core/php/teleinfoPMEPMI.php';
    $cmd .= ' --apikey ' . jeedom::getApiKey(__CLASS__);
    $cmd .= ' --pid ' . jeedom::getTmpFolder(__CLASS__) . '/teleinfoPMEPMId.pid';
    log::add(__CLASS__, 'info', __('Démarrage du démon', __FILE__) . ' : ' . $cmd);
    exec($cmd . ' >> ' . log::getPathToLog(__CLASS__) . ' 2>&1 &');

    $i = 0;
    while ($i < 30) {
      $deamon_info = self::deamon_info();
      if ($deamon_info['state'] == 'ok') {
        break;
      }
      sleep(1);
      $i++;
    }
    if ($i >= 30) {
      log::add(__CLASS__, 'error', 'Impossible de lancer le démon, vérifiez le port', 'unableStartDeamon');
      return false;
    }
    message::removeAll(__CLASS__, 'unableStartDeamon');
    return true;
  }

  public static function deamon_stop() {
    $pid_file = jeedom::getTmpFolder(__CLASS__) . '/teleinfoPMEPMId.pid';
    if (file_exists($pid_file)) {
      $pid = intval(trim(file_get_contents($pid_file)));
      system::kill($pid);
    }
    system::kill('teleinfoPMEPMId.py');
    $port = config::byKey('port', __CLASS__);
    if ($port != 'auto') {
      system::fuserk(jeedom::getUsbMapping($port));
    }
    sleep(1);
  }

  public function preInsert() {
    $this->setIsEnable(1)->setIsVisible(1);
  }

  public function postInsert() {
    $this->createCmds();
  }

  public function createCmds($type = 'default') {
    if ($type === 'default') {
      $cmds = array(
        'PTCOUR1' => array('name' => __('Période tarifaire en cours', __FILE__), 'subtype' => 'string', 'unite' => '', 'order' => 0),
        'EA_s_delta' => array('name' => __('Energie active soutirée', __FILE__), 'subtype' => 'numeric', 'unite' => 'Wh', 'order' => 1),
        'EAPP_s_delta' => array('name' => __('Energie apparente soutirée', __FILE__), 'subtype' => 'numeric', 'unite' => 'VAh', 'order' => 2),
        'PA1MN' => array('name' => __('Puissance active 1 minute', __FILE__), 'subtype' => 'numeric', 'unite' => 'kW', 'order' => 3),
        'CONSO_TOTALE_s' => array('name' => __('Consommation totale tous tarifs', __FILE__), 'subtype' => 'numeric', 'unite' => 'kWh', 'order' => 4)
      );
    } else if ($type === 'injection') {
      $cmds = array(
        'EA_i_delta' => array('name' => __('Energie active injectée', __FILE__), 'subtype' => 'numeric', 'unite' => 'Wh', 'order' => 5),
        'EAPP_i_delta' => array('name' => __('Energie apparente injectée', __FILE__), 'subtype' => 'numeric', 'unite' => 'VAh', 'order' => 6),
        'CONSO_TOTALE_i' => array('name' => __('Injection totale tous tarifs', __FILE__), 'subtype' => 'numeric', 'unite' => 'kWh', 'order' => 7)
      );
    } else {
      $cmds = array(
        'EAP_s' . $type => array('name' => __('Consommation totale', __FILE__) . ' ' . $type, 'subtype' => 'numeric', 'unite' => 'kWh', 'order' => 8),
        'PMAX_s' . $type => array('name' => __('Puissance maximale soutirage', __FILE__) . ' ' . $type, 'subtype' => 'numeric', 'unite' => 'kVA', 'order' => 9)
      );
      if (is_object($this->getCmd('info', 'CONSO_TOTALE_i'))) {
        $add = array(
          'EAP_i' . $type => array('name' => __('Injection totale', __FILE__) . ' ' . $type, 'subtype' => 'numeric', 'unite' => 'kWh', 'order' => 10),
          'PMAX_i' . $type => array('name' => __('Puissance maximale injection', __FILE__) . ' ' . $type, 'subtype' => 'numeric', 'unite' => 'kVA', 'order' => 11)
        );
        array_push($cmds, $add);
      }
    }

    foreach (array_keys($cmds) as $logical) {
      if (!is_object($this->getCmd('info', $logical))) {
        (new teleinfoPMEPMICmd)
          ->setName($cmds[$logical]['name'])
          ->setEqLogic_id($this->getId())
          ->setType('info')
          ->setSubType($cmds[$logical]['subtype'])
          ->setLogicalId($logical)
          ->setUnite($cmds[$logical]['unite'])
          ->setOrder($cmds[$logical]['order'])
          ->setTemplate('dashboard', 'line')
          ->setTemplate('mobile', 'line')
          ->save();
      }
    }
  }
}

class teleinfoPMEPMICmd extends cmd {

  public function dontRemoveCmd() {
    return true;
  }

  public function execute($_options = array()) {
  }
}
