<?php

namespace FacturaScripts\Plugins\DixTPV\Controller;

use FacturaScripts\Core\Lib\ExtendedController\BaseView;
use FacturaScripts\Core\Lib\ExtendedController\EditController;
use FacturaScripts\Core\Tools;

class EditDixTipoMesa extends EditController {

    public function getPageData(): array {
        $pageData = parent::getPageData();
        $pageData['menu'] = 'DixTPV';
        $pageData['title'] = Tools::trans('dixtpv-tabletype-edit');
        $pageData['icon'] = 'fa-solid fa-mug-hot';
        return $pageData;
    }

    //Definir las vistas disponibles en el controlador.
    protected function createViews() {
        parent::createViews();
        $this->customizeMainView();
    }

    // Devuelve el nombre de la clase del modelo asociado al controlador.
    public function getModelClassName(): string {
        return 'DixTipoMesa';
    }

    protected function customizeMainView(): void
    {
        $viewName = $this->getMainViewName();
        if (!isset($this->views[$viewName])) {
            return;
        }

        $view = $this->views[$viewName];
        $this->setViewColumnLabels($view, [
            'nombre' => 'dixtpv-tabletype-field-name',
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
