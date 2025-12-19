<?php

namespace FacturaScripts\Plugins\DixTPV\Model;

use FacturaScripts\Core\Model\Base\ModelClass;
use FacturaScripts\Core\Model\Base\ModelTrait;

class DixAlbaranCaja extends ModelClass
{
    use ModelTrait;

    public $idalbarancaja;
    public $idcaja;
    public $idalbaran;
    public $codpago;
    public $codigo;
    public $total;

    public static function tableName(): string
    {
        return 'dix_albaranes_caja';
    }

    public static function primaryColumn(): string
    {
        return 'idalbarancaja';
    }

    public function clear(): void
    {
        parent::clear();
        $this->total = 0.0;
    }
}
