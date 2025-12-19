<?php

namespace FacturaScripts\Plugins\DixTPV\Controller;

use FacturaScripts\Core\Lib\ExtendedController\ListController;
use FacturaScripts\Core\Tools;

class ListDixSalon extends ListController {

    public function getPageData(): array {
        $pageData = parent::getPageData();
        $pageData['menu'] = 'DixTPV';
        $pageData['title'] = 'Salones';
        $pageData['icon'] = 'fa-solid fa-person-shelter';
        return $pageData;
    }

    // Devuelve el nombre de la clase del modelo asociado al controlador.
    public function getModelClassName(): string {
        return 'DixSalon';
    }

    //Definir las vistas disponibles en el controlador.
    protected function createViews() {
        $this->createViewSalones();
    }

    // Crea la vista específica para la lista de salones.
    public function createViewSalones($viewName = 'ListDixSalon'): void {
        $view = $this->addView($viewName, 'DixSalon', Tools::trans('dixtpv-salon'), 'fa-solid fa-person-shelter')
                ->addOrderBy(['idsalon'], Tools::trans('dixtpv-salon-id'))
                ->addOrderBy(['codsalon'], Tools::trans('dixtpv-salon-code'));

        $columns = [
            'idsalon' => 'dixtpv-salon-id',
            'codsalon' => 'dixtpv-salon-code',
            'nombre' => 'dixtpv-salon-name',
            'observaciones' => 'dixtpv-salon-notes',
        ];

        foreach ($columns as $field => $label) {
            $column = $view->columnForField($field);
            if ($column) {
                $column->title = Tools::trans($label);
            }
        }
    }
}
