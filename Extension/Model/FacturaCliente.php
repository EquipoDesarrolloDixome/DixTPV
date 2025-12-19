<?php

namespace FacturaScripts\Plugins\DixTPV\Extension\Model;

use FacturaScripts\Dinamic\Model\FacturaCliente as BaseFacturaCliente;

class FacturaCliente extends BaseFacturaCliente
{

    public $idcaja;
    public $idagente;

     
    public function __construct($idcaja = '')
    {
        // Llamada al constructor de la clase base
        parent::__construct();

        // Inicializar la propiedad clave
        $this->idcaja = $idcaja; // O un valor por defecto que prefieras
    }
}
