<?php

namespace FacturaScripts\Plugins\DixTPV\Controller;

use FacturaScripts\Core\Lib\ExtendedController\ListController;
use FacturaScripts\Core\Tools;
use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Dinamic\Model\FormaPago;

class ListDixTerminal extends ListController {

    public function getPageData(): array {
        $pageData = parent::getPageData();
        $pageData['menu'] = 'DixTPV';
        $pageData['title'] = 'Terminales';
        $pageData['icon'] = 'fa-solid fa-cash-register';
        return $pageData;
    }

    //Definir las vistas disponibles en el controlador.
    protected function createViews() {
        $this->createViewTerminal();
    }

    // Crea la vista específica para la lista de terminales, con sus filtros de busqueda.
    protected function createViewTerminal($viewName = 'ListDixTPVTerminal'): void {
        $view = $this->addView($viewName, 'DixTerminal', Tools::trans('dixtpv-terminals'), 'fa-solid fa-cash-register')
                ->addOrderBy(['idtpv'], Tools::trans('dixtpv-terminal-id'))
                ->addOrderBy(['nametpv'], Tools::trans('dixtpv-terminal-name'))
                ->addOrderBy(['ticketformat'], Tools::trans('dixtpv-terminal-ticketformat'))
                ->addOrderBy(['doctype'], Tools::trans('dixtpv-terminal-doctype'));

        $columns = [
            'idtpv' => 'dixtpv-terminal-id',
            'nametpv' => 'dixtpv-terminal-name',
            'ticketformat' => 'dixtpv-terminal-ticketformat',
            'doctype' => 'dixtpv-terminal-doctype',
        ];

        foreach ($columns as $field => $label) {
            $column = $view->columnForField($field);
            if ($column) {
                $column->title = Tools::trans($label);
            }
        }

        // Aquí pasamos el $viewName como el primer argumento
        $this->addSearchFields($viewName, ['idtpv', 'nametpv', 'ticketformat', 'doctype']);

        // Si tienes filtros adicionales que quieras añadir
        $this->addFilterSelectWhere($viewName, 'active', [
            ['label' => Tools::lang()->trans('only-active'), 'where' => [new DataBaseWhere('active', true)]],
            ['label' => Tools::lang()->trans('only-inactive'), 'where' => [new DataBaseWhere('active', false)]],
            ['label' => Tools::lang()->trans('all'), 'where' => []]
        ]);

        // Añade más filtros según sea necesario
        $this->addFilterSelect($viewName, 'codalmacen', 'store', 'codalmacen', $this->codeModel->all('almacenes', 'codalmacen', 'nombre'));
        $this->addFilterSelect($viewName, 'codcliente', 'customer', 'codcliente', $this->codeModel->all('clientes', 'codcliente', 'nombre'));
        $this->addFilterSelect($viewName, 'codpago', 'payment-method', 'codpago');
        $this->addFilterSelect($viewName, 'codserie', 'series', 'codserie');
    }
}
