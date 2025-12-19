<?php

namespace FacturaScripts\Plugins\DixTPV\Controller;

use FacturaScripts\Core\Lib\ExtendedController\ListController;
use FacturaScripts\Core\Tools;

class ListDixTPVCaja extends ListController {

    public function getPageData(): array {
        $pageData = parent::getPageData();
        $pageData['menu'] = 'DixTPV';
        $pageData['title'] = 'Caja';
        $pageData['icon'] = 'fa-solid fa-cash-register';
        return $pageData;
    }

    //devuelve el nombre de la clase del modelo asociado al controlador
    public function getModelClassName(): string {
        return 'DixTPVCaja';
    }

    //Define las vistas disponibles en el controlador
    public function createViews() {
        $this->createViewsCajas();
    }

    public function createViewsCajas($viewName = 'ListDixTPVCaja'): void {
        $view = $this->addView($viewName, 'DixTPVCaja', Tools::trans('dixtpv-boxes'), 'fa-solid fa-cash-register')
                ->addOrderBy(['idcaja'], Tools::trans('dixtpv-box-id'))
                ->addOrderBy(['pertenenciaterminal'], Tools::trans('dixtpv-terminal'))
                ->addOrderBy(['dineroini'], Tools::trans('dixtpv-cash-start'))
                ->addOrderBy(['dinerofin'], Tools::trans('dixtpv-cash-end'))
                ->addOrderBy(['descuadre'], Tools::trans('dixtpv-difference'))
                ->addOrderBy(['fechahoraapertura'], Tools::trans('dixtpv-opening-datetime'))
                ->addOrderBy(['fechahoracierre'], Tools::trans('dixtpv-closing-datetime'))
                ->addOrderBy(['camareroini'], Tools::trans('dixtpv-opening-waiter'))
                ->addOrderBy(['camarerofin'], Tools::trans('dixtpv-closing-waiter'));

        $columns = [
            'idcaja' => 'dixtpv-box-id',
            'nombre' => 'dixtpv-box-name',
            'pertenenciaterminal' => 'dixtpv-terminal',
            'dineroini' => 'dixtpv-cash-start',
            'dinerocierre' => 'dixtpv-cash-end',
            'descuadre' => 'dixtpv-difference',
            'fechahoraapertura' => 'dixtpv-opening-datetime',
            'fechahoracierre' => 'dixtpv-closing-datetime',
            'camareroini' => 'dixtpv-opening-waiter',
            'camarerofin' => 'dixtpv-closing-waiter',
        ];

        foreach ($columns as $field => $label) {
            $column = $view->columnForField($field);
            if ($column) {
                $column->title = Tools::trans($label);
            }
        }
    }
}
