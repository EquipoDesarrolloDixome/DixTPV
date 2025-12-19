<?php

namespace FacturaScripts\Plugins\DixTPV\Model;

use FacturaScripts\Core\Model\Base\ModelClass;
use FacturaScripts\Core\Model\Base\ModelTrait;

class DixPedidoCaja extends ModelClass
{
    use ModelTrait;

    public $idpedidocaja;
    public $idcaja;
    public $idpedido;
    public $codpago;
    public $codigo;
    public $total;

    public static function tableName(): string
    {
        return 'dix_pedidos_caja';
    }

    public static function primaryColumn(): string
    {
        return 'idpedidocaja';
    }

    public function clear(): void
    {
        parent::clear();
        $this->total = 0.0;
    }
}
