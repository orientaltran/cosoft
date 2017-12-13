<?php

namespace App\Cryosoft;

use App\Cryosoft\ValueListService;
use App\Cryosoft\UnitsConverterService;
use App\Models\Unit;
use App\Models\StudyEquipment;


class EquipmentsService
{
    public function __construct(ValueListService $valueService, UnitsConverterService $unitConverter)
    {
        $this->value = $valueService;
        $this->unit = $unitConverter;
    }

    public function getCapability($capabilities, $capMask)
    {
        if (($capabilities & $capMask) != 0) {
            return true;
        } else {
            return false;
        }
    }


    public function getSpecificEquipName($idStudyEquipment) 
    {
        $sEquipName = "";
        $studyEquipment = StudyEquipment::where("ID_STUDY_EQUIPMENTS", $idStudyEquipment)->first();
        $capabilitie = $studyEquipment->CAPABILITIES;

         if (($studyEquipment->STD == 1) && (!($this->getCapability($capabilitie , 32768))) && (!($this->getCapability($capabilitie , 1048576)))) {
            $seriesName = $studyEquipment->SERIES_NAME;
            $equipParameter = $studyEquipment->EQP_LENGTH + $studyEquipment->NB_MODUL * $studyEquipment->MODUL_LENGTH;
            $equipParameterUnit = $this->unit->unitConvert($this->value->EQUIP_DIMENSION, $equipParameter);
            $eqpWidthUnit = $this->unit->unitConvert($this->value->EQUIP_DIMENSION, $studyEquipment->EQP_WIDTH);
            $equipVestion = $studyEquipment->EQUIP_VERSION;

            $sEquipName = $seriesName . " - ". $equipParameterUnit." x ".$eqpWidthUnit." (v".$equipVestion.")";
         } else if (($this->getCapability($capabilitie , 1048576)) && ($studyEquipment->EQP_LENGTH != -1.0) && ($studyEquipment->EQP_WIDTH != -1.0)) {
            $stdEqpLength = $this->unit->unitConvert($this->value->EQUIP_DIMENSION, $studyEquipment->EQP_LENGTH);
            $stdeqpWidth = $this->unit->unitConvert($this->value->EQUIP_DIMENSION, $studyEquipment->EQP_WIDTH);
            $sEquipName = $studyEquipment->EQUIP_NAME . " - " . $stdEqpLength . "x" . $sEquipName;
        } else {
            $sEquipName = $studyEquipment->EQUIP_NAME;
        }

        
        return $sEquipName;
    }

    public function getResultsEquipName($idStudyEquipment) {
        $sEquipName = "";
        $studyEquipment = StudyEquipment::where("ID_STUDY_EQUIPMENTS", $idStudyEquipment)->first();
        $capabilitie = $studyEquipment->CAPABILITIES;

        if (($studyEquipment->STD == 1) && (!($this->getCapability($capabilitie , 32768))) && (!($this->getCapability($capabilitie , 1048576)))) {
            $seriesName = $studyEquipment->SERIES_NAME;
            $equipParameter = $studyEquipment->EQP_LENGTH + $studyEquipment->NB_MODUL * $studyEquipment->MODUL_LENGTH;
            $equipParameterUnit = $this->unit->unitConvert($this->value->EQUIP_DIMENSION, $equipParameter);
            $eqpWidthUnit = $this->unit->unitConvert($this->value->EQUIP_DIMENSION, $studyEquipment->EQP_WIDTH);
            $equipVestion = $studyEquipment->EQUIP_VERSION;

            $sEquipName = $seriesName . " - ". $equipParameterUnit." x ".$eqpWidthUnit." (v".$equipVestion.")";

        } else if (($this->getCapability($capabilitie , 1048576)) && ($studyEquipment->EQP_LENGTH != -1.0) && ($equip->EQP_WIDTH != -1.0)) {
            $sEquipName = $studyEquipment->EQUIP_NAME . " - " . $this->getSpecificEquipSize($idStudyEquipment);
        } else {
            $sEquipName = $studyEquipment->EQUIP_NAME;
        }

        return $sEquipName;
    }

    public function getSpecificEquipSize($idStudyEquipment) {
        $sEquipName = "";
        $studyEquipment = StudyEquipment::where("ID_STUDY_EQUIPMENTS", $idStudyEquipment)->first();
        $capabilitie = $studyEquipment->CAPABILITIES;

        if (($this->getCapability($capabilitie , 1048576)) && ($studyEquipment->EQP_LENGTH != -1.0) && ($studyEquipment->EQP_WIDTH != -1.0)) {

            $stdEqpLength = $this->unit->unitConvert($this->value->EQUIP_DIMENSION, $studyEquipment->EQP_LENGTH);
            $stdeqpWidth = $this->unit->unitConvert($this->value->EQUIP_DIMENSION, $studyEquipment->EQP_WIDTH);

            $sEquipName = "(" . $stdEqpLength . "x" . $stdeqpWidth + ")";
        }

        return $sEquipName;
    }
    

    
}
