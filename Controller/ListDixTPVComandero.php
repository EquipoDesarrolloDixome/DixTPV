<?php

namespace FacturaScripts\Plugins\DixTPV\Controller;

use FacturaScripts\Core\Lib\ExtendedController\ListController;
use FacturaScripts\Core\Tools;

class ListDixTPVComandero extends ListController {

    public function getPageData(): array {
        $pageData = parent::getPageData();
        $pageData['menu'] = 'DixTPV';
        $pageData['title'] = Tools::trans('dixtpv-commander');
        $pageData['icon'] = 'fa-solid fa-mug-hot';
        $pageData['showonmenu'] = false;
        return $pageData;
    }

    //Definir las vistas disponibles en el controlador
    protected function createViews() {
        $this->createViewsComandero();
    }

    //devuelve el nombre de la clase del modelo asociado al controlador
    public function getModelClassName(): string {
        return 'DixTPVComandero';
    }

    //crea la vista especifica para la lista de comanderos
    public function createViewsComandero($viewName = 'ListDixTPVComandero'): void {
        $this->addView($viewName, 'DixTPVComandero', Tools::trans('dixtpv-commander'), 'fa-solid fa-mug-hot')
                ->addOrderBy(['idcomandero'], 'id-comandero')
                ->addOrderBy(['nombre'], 'name')
                ->addOrderBy(['tipocomandero'], 'tipo-comandero')
                ->addOrderBy(['idterminal'], 'idterminal');
    }
}
