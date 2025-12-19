<?php

namespace FacturaScripts\Plugins\DixTPV\Controller;

use FacturaScripts\Core\Lib\ExtendedController\ListController;

class ListDixReserva extends ListController {

    //Definir las vistas disponibles en el controlador.
    protected function createViews() {
        $this->createViewReserva();
    }

    public function getPageData(): array {
        $pageData = parent::getPageData();
        $pageData['menu'] = 'DixTPV';
        $pageData['title'] = 'reservas';
        $pageData['icon'] = 'fa-solid fa-mug-hot';
        $pageData['showonmenu'] = false;
        return $pageData;
    }

    // Devuelve el nombre de la clase del modelo asociado al controlador.
    public function getModelClassName(): string {
        return 'DixReserva';
    }

    // Crea la vista específica para la lista de reservas
    public function createViewReserva($viewName = 'ListDixReserva'): void {
        $this->addView($viewName, 'DixReserva', 'Reservas', 'fa-solid fa-mug-hot')
                ->addOrderBy(['fecha'], 'date')
                ->addOrderBy(['horaini'], 'hora-inicio')
                ->addOrderBy(['horafin'], 'hora-fin')
                ->addOrderBy(['personas'], 'comansales')
                ->addOrderBy(['nombrereserva'], 'nombre-reserva')
                ->addOrderBy(['idmesa'], 'id');
    }
}
