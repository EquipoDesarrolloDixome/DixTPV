<?php

namespace FacturaScripts\Plugins\DixTPV\Controller;

use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Core\Lib\ExtendedController\BaseView;
use FacturaScripts\Core\Lib\ExtendedController\EditController;
use FacturaScripts\Core\Tools;
use FacturaScripts\Dinamic\Lib\AssetManager;
use const FS_ROUTE;

class EditDixTPVCaja extends EditController {

    public function getPageData(): array {
        $pageData = parent::getPageData();
        $pageData['menu'] = 'DixTPV';
        $pageData['title'] = Tools::trans('dixtpv-box-tab');
        $pageData['icon'] = 'fa-solid fa-cash-register';
        return $pageData;
    }

    //definir las vistas disponibles en el controlador
    protected function createViews() {
        parent::createViews();
        $this->customizeMainView();
        $this->createListaFinView();
        $this->createFormasPagoResumenView();
        $this->createTiposIvaView();
        $this->createResumenProductosView();
    }

    //Devuelve el nombre de la clase Model asociado al controlador
    public function getModelClassName(): string {
        return 'DixTPVCaja';
    }
    public function createListaFinView($viewName = 'ListDixListaFin'): void {
        $view = $this->addListView($viewName, 'DixListaFin', Tools::trans('dixtpv-tab-history'), 'fas fa-sticky-note');
        $this->setSettings($viewName, 'clickable', true);
        $view->addOrderBy(['idlista'], Tools::trans('dixtpv-closings-history-id'), 2);
        $this->setViewColumnLabels($view, [
            'idlista' => 'dixtpv-closings-history-id',
            'codpago' => 'dixtpv-closings-payment',
            'codigofactura' => 'dixtpv-closings-invoice-code',
            'idagente' => 'dixtpv-closings-waiter',
            'total' => 'dixtpv-closings-total',
            'importeentregado' => 'dixtpv-closings-paid',
        ]);
        $invoiceColumn = $view->columnForField('codigofactura');
        if ($invoiceColumn) {
            $invoiceColumn->widget->onclick = 'DixTPVInvoicePdf';
            $invoiceColumn->class = trim(($invoiceColumn->class ?? '') . ' dixtpv-invoice-cell');
        }
        if ($view->getRow('status')) {
            $view->getRow('status')->class = trim(($view->getRow('status')->class ?? '') . ' dix-invoice-row');
        }
    }
    public function createFormasPagoResumenView($viewName = 'ListDixFormasPagoResumen'): void {
        $view = $this->addListView($viewName, 'DixFormasPagoResumen', Tools::trans('dixtpv-tab-payments'), 'fas fa-sticky-note');
        $this->setSettings($viewName, 'clickable', false);
        $view->addOrderBy(['iddixformaspago'], Tools::trans('dixtpv-payment-summary-id'), 2);
        $this->setViewColumnLabels($view, [
            'iddixformaspago' => 'dixtpv-payment-summary-id',
            'codpago' => 'dixtpv-payment-summary-code',
            'total' => 'dixtpv-payment-summary-total',
        ]);
    }
    public function createTiposIvaView($viewName = 'ListDixTipoIVAResumen'): void {
        $view = $this->addListView($viewName, 'DixTipoIVAResumen', Tools::trans('dixtpv-tab-taxes'), 'fas fa-sticky-note');
        $this->setSettings($viewName, 'clickable', false);
        $view->addOrderBy(['iddixtipoiva'], Tools::trans('dixtpv-tax-summary-id'), 2);
        $this->setViewColumnLabels($view, [
            'iddixtipoiva' => 'dixtpv-tax-summary-id',
            'codimpuesto' => 'dixtpv-tax-summary-code',
            'base_total' => 'dixtpv-tax-summary-base',
            'tax_total' => 'dixtpv-tax-summary-tax',
            'total' => 'dixtpv-tax-summary-total',
        ]);
    }
    public function createResumenProductosView($viewName = 'ListDixProductosResumen'): void {
        $view = $this->addListView($viewName, 'DixProductosResumen', Tools::trans('dixtpv-tab-products'), 'fas fa-sticky-note');
        $this->setSettings($viewName, 'clickable', false);
        $view->addOrderBy(['idprodresumen'], Tools::trans('dixtpv-products-summary-id'), 2);
        $this->setViewColumnLabels($view, [
            'idprodresumen' => 'dixtpv-products-summary-id',
            'referencia' => 'dixtpv-products-summary-reference',
            'descripcion' => 'dixtpv-products-summary-description',
            'unidades' => 'dixtpv-products-summary-units',
            'total' => 'dixtpv-products-summary-total',
        ]);
    }
    protected function loadData($viewName, $view) {
        $mainViewName = $this->getMainViewName();
        $codcliente = $this->getViewModelValue($mainViewName, 'idcaja');
        $where = [new DataBaseWhere('idcaja', $codcliente)];

        switch ($viewName) {
            case 'ListDixProductosResumen':
                $view->loadData('', $where, ['idcaja' => 'DESC']);
                break;

            case 'ListDixTipoIVAResumen':
                $view->loadData('', $where, ['idcaja' => 'DESC']);
                break;

            case 'ListDixListaFin':
                $view->loadData('', $where, ['idcaja' => 'DESC']);
                break;

            case 'ListDixFormasPagoResumen':
                $view->loadData('', $where, ['idcaja' => 'DESC']);
                break;

            default:
                parent::loadData($viewName, $view);
                break;
        }
    }
    private function customizeMainView(): void
    {
        $viewName = $this->getMainViewName();
        if (!isset($this->views[$viewName])) {
            return;
        }

        $view = $this->views[$viewName];
        $this->setViewColumnLabels($view, [
            'nombre' => 'dixtpv-box-field-name',
            'pertenenciaterminal' => 'dixtpv-box-field-terminal',
            'dineroini' => 'dixtpv-box-field-cash-start',
            'dinerocierre' => 'dixtpv-box-field-cash-end',
            'descuadre' => 'dixtpv-box-field-difference',
            'fechahoraapertura' => 'dixtpv-box-field-opening-datetime',
            'fechahoracierre' => 'dixtpv-box-field-closing-datetime',
            'camareroini' => 'dixtpv-box-field-opening-waiter',
            'camarerofin' => 'dixtpv-box-field-closing-waiter',
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
