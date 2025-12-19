<?php

namespace FacturaScripts\Plugins\DixTPV\Controller;

use FacturaScripts\Core\Lib\ExtendedController\EditController;

class EditDixTPVComandero extends EditController {

    public function getPageData(): array {
        $pageData = parent::getPageData();
        $pageData['menu'] = 'DixTPV';
        $pageData['title'] = 'comandero';
        $pageData['icon'] = 'fa-solid fa-mug-hot';
        return $pageData;
    }

    //definir las vistas disponibles en el controlador
    public function createViews() {
        parent::createViews();
    }

    //devuelve el nombre de la clase del modelo asociado al controlador
    public function getModelClassName(): string {
        return 'DixTPVComandero';
    }
}
