<?php

namespace FacturaScripts\Plugins\DixTPV\Model;

use FacturaScripts\Core\Model\Base\ModelClass;
use FacturaScripts\Core\Model\Base\ModelTrait;

class DixTipoIVAResumen extends ModelClass
{
    use ModelTrait;

    public $iddixtipoiva;
    public $idcaja;
    public $codimpuesto;
    public $total;

    public function clear(): void
    {
        parent::clear();
        $this->iddixtipoiva = null;
        $this->idcaja = null;
        $this->codimpuesto = '';
        $this->total = 0.0;
    }

    public static function primaryColumn(): string
    {
        return 'iddixtipoiva';
    }

    public static function tableName(): string
    {
        return 'dix_tipo_iva_resumen';
    }

    public function test(): bool
    {
        return parent::test();
    }
}
