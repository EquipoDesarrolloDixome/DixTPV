<?php

namespace FacturaScripts\Plugins\DixTPV\Controller;

use FacturaScripts\Core\Lib\ExtendedController\ListController;
use FacturaScripts\Core\Tools;

class ListDixTPVComanda extends ListController {

    //Definir las vistas disponibles en el controlador.
    protected function createViews() {
        $this->createViewComanda();
    }

    public function getPageData(): array {
        $pageData = parent::getPageData();
        $pageData['menu'] = 'DixTPV';
        $pageData['title'] = 'Comandas';
        $pageData['icon'] = 'fa-solid fa-print';
        return $pageData;
    }

    // Devuelve el nombre de la clase del modelo asociado al controlador.
    public function getModelClassName(): string {
        return 'DixTPVComanda';
    }

    // Crea la vista específica para la lista de comandas.
    public function createViewComanda($viewName = 'ListDixTPVComanda') {
        $view = $this->addView($viewName, 'DixTPVComanda', Tools::trans('dixtpv-orders'), 'fa-solid fa-print')
                ->addOrderBy(['idcomanda'], Tools::trans('dixtpv-order-id'))
                ->addOrderBy(['idmesa'], Tools::trans('dixtpv-order-table'))
                ->addOrderBy(['idestado'], Tools::trans('dixtpv-order-status'));

        $columns = [
            'idcomanda' => 'dixtpv-order-id',
            'fecha' => 'dixtpv-order-date',
            'idcliente' => 'dixtpv-order-client',
            'idmesa' => 'dixtpv-order-table',
            'idestado' => 'dixtpv-order-status',
            'total' => 'dixtpv-order-total',
            'observaciones' => 'dixtpv-order-notes',
        ];

        foreach ($columns as $field => $label) {
            $column = $view->columnForField($field);
            if ($column) {
                $column->title = Tools::trans($label);
            }
        }
    }
}
