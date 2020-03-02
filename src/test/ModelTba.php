<?php

include_once(DIR_PATRONS . "TraduccioMapper.php");
include_once(DIR_PATRONS . "EscapadaMapper.php");

class CtrlEscapada {


    public static function mostrar(){
        global $smarty ,$param1;

        $escapada = PartnerMapper::obte($param1);

        if ($escapada->logo > 0) {
            $imatge = ImatgeMapper::obte($escapada->logo);
        } else {
            $imatge = "";
        }

        if ($escapada->dataIni <= date("Y-m-d") && $escapada->dataFi >= date("Y-m-d")) {
            include_once(DIR_PATRONS . "EstablimentMapper.php");
            $establiments = EstablimentMapper::llistarPartner($escapada->id);
        } else {
            $establiments = "";
        }

        $smarty->assign("partner", $escapada);
        $smarty->assign("imatge", $imatge);
        $smarty->assign("establiments", $establiments);
        $smarty->assign("cosPagina", "partner");
    }

}
