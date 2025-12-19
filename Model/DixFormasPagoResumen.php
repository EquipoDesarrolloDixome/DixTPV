<?php

namespace FacturaScripts\Plugins\DixTPV\Model;

use FacturaScripts\Core\Model\Base\ModelClass;
use FacturaScripts\Core\Model\Base\ModelTrait;

class DixFormasPagoResumen extends ModelClass
{
    use ModelTrait;

    public $iddixformaspago;
    public $idcaja;
    public $codpago;
    public $total;

    public function clear(): void
    {
        parent::clear();
        $this->iddixformaspago = null;
        $this->idcaja = null;
        $this->codpago = '';
        $this->total = 0.0;
    }

    public static function primaryColumn(): string
    {
        return 'iddixformaspago';
    }

    public static function tableName(): string
    {
        return 'dix_formas_pago_resumen';
    }

    public function test(): bool
    {
        return parent::test();
    }

    public function save(): bool
    {
        return parent::save();
    }

    public function install(): string
    {
        return parent::install();
    }
}
