<?php

namespace FacturaScripts\Plugins\DixTPV\Controller;

use FacturaScripts\Core\Lib\ExtendedController\EditController;
use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Core\Lib\ExtendedController\BaseView;
use FacturaScripts\Dinamic\Model\DixMesa;
use FacturaScripts\Core\Tools;

class EditDixSalon extends EditController {

    public function getPageData(): array {
        $pageData = parent::getPageData();
        $pageData['menu'] = 'DixTPV';
        $pageData['title'] = Tools::trans('dixtpv-salon-edit');
        $pageData['icon'] = 'fa-solid fa-person-shelter';
        return $pageData;
    }

    // Devuelve el nombre de la clase del modelo asociado al controlador.
    public function getModelClassName(): string {
        return 'DixSalon';
    }

    //Definir las vistas disponibles en el controlador.
    protected function createViews() {
        parent::createViews();
        $this->setTabsPosition('bottom');

        // Crea y añade las vistas adicionales.
        $this->customizeMainView();
        $this->createTablesView();
    }

    // Crea la vista para la lista de salones y desactiva el botón de eliminar.
    protected function createViewFamilies($viewName = 'ListDixSalon'): void {
        $this->addListView($viewName, 'DixSalon', 'dix_salones', 'fas fa-sitemap');
        $this->views[$viewName]->addOrderBy(['idsalon'], 'code');
        $this->setSettings($viewName, 'btnDelete', false);
    }

    // Carga los datos correspondientes a la vista según el nombre de la vista.
    protected function loadData($viewName, $view) {
        $idsalon = $this->getViewModelValue('EditDixSalon', 'idsalon');
        $where = [new DataBaseWhere('idsalon', $idsalon)];

        switch ($viewName) {
            case 'EditDixMesa':
                $orderBy = ['idmesa' => 'ASC'];
                $view->loadData('', $where, $orderBy);
                break;

            case 'ListDixMesa-new':
                $where = [new DataBaseWhere('idsalon', null, 'IS')];
                $view->loadData('', $where);
                break;

            case 'ListDixSalon':
                $view->loadData('', $where);
                break;

            default:
                parent::loadData($viewName, $view);
                break;
        }
    }

    // Añade configuraciones comunes a las vistas de mesas.
    protected function createViewMesasCommon($viewName): void {
        $this->views[$viewName]->addOrderBy(['idmesa'], 'code');
    }

    // Ejecuta acciones previas dependiendo de la acción solicitada.
    protected function execPreviousAction($action) {
        switch ($action) {
            case 'add-mesa':
                $this->addMesaAction();
                return true;

            case 'remove-mesa':
                $this->removeMesaAction();
                return true;

            default:
                return parent::execPreviousAction($action);
        }
    }

    // Acción que añade una nueva mesa a un salón específico.
    protected function addMesaAction(): void {
        $codes = $this->request->request->get('code', []);
        if (false === is_array($codes)) {
            return;
        }

        $num = 0;
        foreach ($codes as $code) {
            $mesa = new DixMesa();
            if (false === $mesa->loadFromCode($code)) {
                continue;
            }

            $mesa->idsalon = $this->request->query->get('code');
            if ($mesa->save()) {
                $num++;
            }
        }
    }

    // Acción que elimina una mesa de un salón específico.
    protected function removeMesaAction(): void {
        $codes = $this->request->request->get('code', []);
        if (false === is_array($codes)) {
            Tools::log()->warning('No se recibieron códigos válidos para eliminar.');
            return;
        }

        $num = 0;
        foreach ($codes as $code) {
            $mesa = new DixMesa();
            if (false === $mesa->loadFromCode($code)) {
                Tools::log()->warning('No se pudo cargar la mesa con el código:', ['%code%' => $code]);
                continue;
            }

            $mesa->idsalon = $this->request->query->get('idsalon');
            if ($mesa->save()) {
                $num++;
            } else {
                Tools::log()->warning('No se pudo eliminar la mesa con el código:', ['%code%' => $code]);
            }
        }
        Tools::log()->notice('Se eliminaron con éxito %num% mesas.', ['%num%' => $num]);
    }

    protected function createTablesView($viewName = 'EditDixMesa'): void
    {
        $view = $this->addEditListView($viewName, 'DixMesa', Tools::trans('dixtpv-salon-tab-tables'), 'fas fa-folder');
        $this->setViewColumnLabels($view, [
            'nombre' => 'dixtpv-table-name',
            'tipo_mesa' => 'dixtpv-table-type',
            'comensales' => 'dixtpv-table-guests',
            'idcomanda' => 'dixtpv-table-order',
            'observaciones' => 'dixtpv-table-notes',
        ]);
    }

    protected function customizeMainView(): void
    {
        $viewName = $this->getMainViewName();
        if (!isset($this->views[$viewName])) {
            return;
        }

        $view = $this->views[$viewName];
        $this->setViewColumnLabels($view, [
            'nombre' => 'dixtpv-salon-field-name',
            'codsalon' => 'dixtpv-salon-field-code',
            'comensales' => 'dixtpv-salon-field-guests',
            'codtarifa' => 'dixtpv-salon-field-rate',
            'observaciones' => 'dixtpv-salon-field-notes',
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
