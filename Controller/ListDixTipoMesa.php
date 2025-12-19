<?php

namespace FacturaScripts\Plugins\DixTPV\Controller;

use FacturaScripts\Core\Lib\ExtendedController\ListController;
use FacturaScripts\Core\Tools;

class ListDixTipoMesa extends ListController {

    //Definir las vistas disponibles en el controlador.
    protected function createViews() {
        $this->createViewMesa();
    }

    public function getPageData(): array {
        $pageData = parent::getPageData();
        $pageData['menu'] = 'DixTPV';
        $pageData['title'] = 'Tipo de mesa';
        $pageData['icon'] = 'fa-solid fa-mug-hot';
        return $pageData;
    }

    // Devuelve el nombre de la clase del modelo asociado al controlador.
    public function getModelClassName(): string {
        return 'DixTipoMesa';
    }

    // Crea la vista específica para la lista de mesas, añadiendo columnas y criterios de ordenación.
    public function createViewMesa($viewName = 'ListDixTipoMesa'): void {
        $view = $this->addView($viewName, 'DixTipoMesa', Tools::trans('dixtpv-tabletype-name-plural'), 'fa-solid fa-mug-hot')
                ->addOrderBy(['idtipomesa'], Tools::trans('dixtpv-tabletype-id'))
                ->addOrderBy(['nombre'], Tools::trans('dixtpv-tabletype-name'));

        $columns = [
            'idtipomesa' => 'dixtpv-tabletype-id',
            'nombre' => 'dixtpv-tabletype-name',
        ];

        foreach ($columns as $field => $label) {
            $column = $view->columnForField($field);
            if ($column) {
                $column->title = Tools::trans($label);
            }
        }
    }
}
