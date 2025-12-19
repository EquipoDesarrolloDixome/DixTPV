<?php

namespace FacturaScripts\Plugins\DixTPV\Controller;

use FacturaScripts\Core\Lib\ExtendedController\EditController;

class EditDixTarifa extends EditController {

    public function getPageData(): array {
        $pageData = parent::getPageData();
        $pageData['menu'] = 'DixTPV';
        $pageData['title'] = 'tarifa';
        $pageData['icon'] = 'fa-solid fa-mug-hot';
        $pageData['showonmenu'] = false;
        return $pageData;
    }

    //definir las vistas disponibles en el controlador.
    protected function createViews() {
        parent::createViews();
    }

    //Devuelve el nombre de la clase Model asociado al controlador

    public function getModelClassName(): string {
        return 'DixTarifa';
    }
}
