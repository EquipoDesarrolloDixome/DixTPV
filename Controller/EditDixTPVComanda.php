<?php

namespace FacturaScripts\Plugins\DixTPV\Controller;

use FacturaScripts\Core\Lib\ExtendedController\EditController;
use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Core\Lib\ExtendedController\BaseView;
use FacturaScripts\Core\Tools;

class EditDixTPVComanda extends EditController {

    public function getPageData(): array {
        $pageData = parent::getPageData();
        $pageData['menu'] = 'DixTPV';
        $pageData['title'] = Tools::trans('dixtpv-order-edit');
        $pageData['icon'] = 'fa-solid fa-print';
        return $pageData;
    }

    // definir las vistas disponibles en el controlador.
    protected function createViews() {
        parent::createViews();
        $this->createViewsDetalles();
    }

    // Devuelve el nombre de la clase del modelo asociado al controlador.
    public function getModelClassName(): string {
        return 'DixTPVComanda';
    }

    // Crea la vista para editar la lista de detalles de la comanda.
    protected function createViewsDetalles($viewName = 'EditDixTPVComandaDetails'): void {
        $view = $this->addEditListView($viewName, 'DixDetailComanda', Tools::trans('dixtpv-order-details'), 'fas fa-project-diagram');
        $this->setViewColumnLabels($view, [
            'referencia' => 'dixtpv-order-detail-reference',
            'descripcion' => 'dixtpv-order-detail-description',
            'cantidad' => 'dixtpv-order-detail-quantity',
            'importe' => 'dixtpv-order-detail-amount',
        ]);
        $this->customizeMainView();
    }

    // Carga los datos correspondientes a la vista según el nombre de la vista.
    protected function loadData($viewName, $view) {
        $id = $this->getViewModelValue('EditDixTPVComanda', 'idcomanda');
        $where = [new DataBaseWhere('idcomanda', $id)];

        switch ($viewName) {
            case 'EditDixTPVComandaDetails':
                $view->loadData('', $where, ['iddetalle' => 'DESC']);
                break;

            default:
                parent::loadData($viewName, $view);
                break;
        }
    }
    protected function customizeMainView(): void
    {
        $viewName = $this->getMainViewName();
        if (!isset($this->views[$viewName])) {
            return;
        }

        $view = $this->views[$viewName];
        $this->setViewColumnLabels($view, [
            'fecha' => 'dixtpv-order-date',
            'idcliente' => 'dixtpv-order-client',
            'idmesa' => 'dixtpv-order-table',
            'idestado' => 'dixtpv-order-status',
            'observaciones' => 'dixtpv-order-notes',
        ]);
    }

    private function setViewColumnLabels(BaseView $view, array $map): void
    {
        foreach ($map as $field => $label) {
            $column = $view->columnForField($field);
            if ($column) {
                $column->title = Tools::trans($label);
            }
        }
    }
}
