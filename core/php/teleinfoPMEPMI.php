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
require_once dirname(__FILE__) . "/../../../../core/php/core.inc.php";

if (!jeedom::apiAccess(init('apikey'), 'teleinfoPMEPMI')) {
	echo __("Vous n'êtes pas autorisé à effectuer cette action", __FILE__);
	die();
}
if (init('test') != '') {
	echo 'OK';
	die();
}
$result = json_decode(file_get_contents("php://input"), true);
if (!is_array($result)) {
	die();
}

if (!is_object($eqLogic = eqLogic::byLogicalId($result['INDEP_TARIF']['ID_COMPTEUR'][0], 'teleinfoPMEPMI'))) {
	$eqLogic = (new teleinfoPMEPMI)
		->setEqType_name('teleinfoPMEPMI')
		->setLogicalId($result['INDEP_TARIF']['ID_COMPTEUR'][0])
		->setName(__('Compteur', __FILE__) . ' ' . $result['INDEP_TARIF']['ID_COMPTEUR'][0])
		->setCategory('energy', 1)
		->setComment('ADS = ' . $result['INDEP_TARIF']['ID_COMPTEUR'][0] . "\n" . __('CONTRAT', __FILE__) . ' = ' . $result['INDEP_TARIF']['CONTRAT'][0])
		->save();
}
if (isset($result['INDEP_TARIF']['EA_i']) && !is_object($eqLogic->getCmd('info', 'CONSO_TOTALE_i'))) {
	$eqLogic->createCmds('injection');
}
if (!is_object($eqLogic->getCmd('info', 'EA_s' . $result['INDEP_TARIF']['PTCOUR1'][0]))) {
	$eqLogic->createCmds($result['INDEP_TARIF']['PTCOUR1'][0]);
}


foreach ($result['INDEP_TARIF'] as $logical => $arrayValues) {
	$eqLogic->checkAndUpdateCmd($logical, $arrayValues[0]);
}

foreach ($result[$result['INDEP_TARIF']['PTCOUR1'][0]] as $logical => $arrayValues) {
	$eqLogic->checkAndUpdateCmd($logical . $result['INDEP_TARIF']['PTCOUR1'][0], $arrayValues[0]);
}
