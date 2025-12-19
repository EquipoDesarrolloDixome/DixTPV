<?php

namespace FacturaScripts\Plugins\DixTPV\Controller;

use FacturaScripts\Core\Lib\ExtendedController\BaseView;
use FacturaScripts\Core\Lib\ExtendedController\EditController;
use FacturaScripts\Core\Tools;

class EditDixTerminal extends EditController {

    public function getPageData(): array {
        $pageData = parent::getPageData();
        $pageData['menu'] = 'DixTPV';
        $pageData['title'] = Tools::trans('dixtpv-terminal-edit');
        $pageData['icon'] = 'fa-solid fa-cash-register';
        return $pageData;
    }

    //Definir las vistas disponibles en el controlador.
    protected function createViews() {
        parent::createViews();
        $this->customizeMainView();
    }

    // Devuelve el nombre de la clase del modelo asociado al controlador.
    public function getModelClassName(): string {
        return 'DixTerminal';
    }

    protected function customizeMainView(): void
    {
        $viewName = $this->getMainViewName();
        if (!isset($this->views[$viewName])) {
            return;
        }

        $view = $this->views[$viewName];
        $this->setViewColumnLabels($view, [
            'nametpv' => 'dixtpv-terminal-field-name',
            'doctype' => 'dixtpv-terminal-field-doctype',
            'codcliente' => 'dixtpv-terminal-field-client',
            'adddiscount' => 'dixtpv-terminal-field-add-discount',
            'adddiscount1' => 'dixtpv-terminal-field-add-discount1',
            'changeprice' => 'dixtpv-terminal-field-change-price',
            'coddivisa' => 'dixtpv-terminal-field-currency',
            'codpago' => 'dixtpv-terminal-field-payment',
            'codserie' => 'dixtpv-terminal-field-series',
            'impresion' => 'dixtpv-terminal-field-printing',
            'idprinter' => 'dixtpv-terminal-field-printer',
            'mostrarcamareros' => 'dixtpv-terminal-field-show-waiters',
            'mostrarporpin' => 'dixtpv-terminal-field-show-pin',
            'hosteleria' => 'dixtpv-terminal-field-hosteleria',
            
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
