<?php

namespace FacturaScripts\Plugins\DixTPV\Controller;

use FacturaScripts\Core\Lib\ExtendedController\ListController;

class ListDixTarifa extends ListController {

    public function getPageData(): array {
        $pageData = parent::getPageData();
        $pageData['menu'] = 'DixTPV';
        $pageData['title'] = 'tarifas';
        $pageData['icon'] = 'fa-solid fa-mug-hot';
        $pageData['showonmenu'] = false;

        return $pageData;
    }

    //Devuelve el nombre de la clase del modelo asociado al controlador
    public function getModelClassName(): string {
        return 'DixTarifa';
    }

    //Define las vistas disponibles en el controlador
    public function createViews() {
        $this->createViewsTarifa();
    }

    public function createViewsTarifa($viewName = 'ListDixTarifa'): void {
        $this->addView($viewName, 'DixTarifa', 'Tarifas', 'fa-solid fa-mug-hot')
                ->addOrderBy(['codtarifa'], 'cod-tarifa')
                ->addOrderBy(['nombre'], 'name')
                ->addOrderBy(['horaini'], 'hora-inicio')
                ->addOrderBy(['horafin'], 'hora-fin')
                ->addOrderBy(['fechainitarifa'], 'Fecha-ini')
                ->addOrderBy(['fechafintarifa'], 'fecha-fin');
    }
}
