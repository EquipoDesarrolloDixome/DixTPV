<?php

namespace FacturaScripts\Plugins\DixTPV\Controller;

use FacturaScripts\Core\Lib\ExtendedController\ListController;
use FacturaScripts\Core\Tools;

class ListDixMesa extends ListController {

    //Definir las vistas disponibles en el controlador.
    protected function createViews() {
        $this->createViewMesa();
    }

    public function getPageData(): array {
        $pageData = parent::getPageData();
        $pageData['menu'] = 'DixTPV';
        $pageData['title'] = 'mesas';
        $pageData['icon'] = 'fa-solid fa-mug-hot';
        $pageData['showonmenu'] = false;
        return $pageData;
    }

    // Devuelve el nombre de la clase del modelo asociado al controlador.
    public function getModelClassName(): string {
        return 'DixMesa';
    }

    // Crea la vista específica para la lista de mesas, añadiendo columnas y criterios de ordenación.
    public function createViewMesa($viewName = 'ListDixMesa'): void {
        $view = $this->addView($viewName, 'DixMesa', 'mesas', 'fa-solid fa-mug-hot')
                ->addOrderBy(['idmesa'], Tools::trans('dixtpv-table-id'))
                ->addOrderBy(['nombre'], Tools::trans('dixtpv-table-name'));

        $columns = [
            'idmesa' => 'dixtpv-table-id',
            'nombre' => 'dixtpv-table-name',
        ];

        foreach ($columns as $field => $label) {
            $column = $view->columnForField($field);
            if ($column) {
                $column->title = Tools::trans($label);
            }
        }
    }
}
