<?php

namespace FacturaScripts\Plugins\DixTPV\Controller;

use FacturaScripts\Core\Lib\ExtendedController\EditController;

class EditDixEstado extends EditController {

    public function getPageData(): array {
        $pageData = parent::getPageData();
        $pageData['menu'] = 'DixTPV';
        $pageData['title'] = 'estado';
        $pageData['icon'] = 'fa-solid fa-mug-hot';
        return $pageData;
    }

    //Definir las vistas disponibles en el controlador.
    protected function createViews() {
        parent::createViews();
    }

    // Devuelve el nombre de la clase del modelo asociado al controlador.
    public function getModelClassName(): string {
        return 'DixEstado';
    }
}
