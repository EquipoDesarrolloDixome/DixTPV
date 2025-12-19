<?php

namespace FacturaScripts\Plugins\DixTPV\Controller;

use FacturaScripts\Core\Lib\ExtendedController\ListController;

class ListDixEstado extends ListController {

    //Definir las vistas disponibles en el controlador.
    protected function createViews() {
        $this->createViewEstado();
    }

    public function getPageData(): array {
        $pageData = parent::getPageData();
        $pageData['menu'] = 'DixTPV';
        $pageData['title'] = 'estados';
        $pageData['icon'] = 'fa-solid fa-mug-hot';
        $pageData['showonmenu'] = false;
        return $pageData;
    }

    // Devuelve el nombre de la clase del modelo asociado al controlador.
    public function getModelClassName(): string {
        return 'DixEstado';
    }

    // Crea la vista específica para la lista de salones.
    public function createViewEstado($viewName = 'ListDixEstado'): void {
        $this->addView($viewName, 'DixEstado', 'dix_estado', 'fa-solid fa-mug-hot')
                ->addOrderBy(['idestado'], 'id')
                ->addOrderBy(['nombre'], 'name')
                ->addOrderBy(['descripcion'], 'descripcion');
    }
}
