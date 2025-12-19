<?php

namespace FacturaScripts\Plugins\DixTPV\Extension\Model;

use FacturaScripts\Dinamic\Model\FormaPago as FormaPagoCore;

class FormaPago extends FormaPagoCore {

    public $efectivo;

     
    public function __construct($efectivo = '') {
        // Llamada al constructor de la clase base
        parent::__construct();

        // Inicializar la propiedad clave
        $this->efectivo = $efectivo; // O un valor por defecto que prefieras
    }
}
