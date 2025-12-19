<?php

namespace FacturaScripts\Plugins\DixTPV\Model;

use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Core\Model\Base\ModelClass;
use FacturaScripts\Core\Model\Base\ModelTrait;
use FacturaScripts\Plugins\DixTPV\Model\DixCaja as DinDixCaja;
use FacturaScripts\Core\Model\FacturaCliente as DFacturaCliente;

class DixListaFin extends ModelClass {

    use ModelTrait;

    //propiedades que mapean a la estructura del XML
    public $idlista;
    public $codpago;
    public $idcaja;
    public $total;
    public $idfactura;
    public $importeentregado;
    public $codigofactura;

    public function clear(): void {
        parent::clear();
    }

    public static function primaryColumn(): string {
        return 'idlista';
    }

    public static function tableName(): string {
        return 'dix_listafins';
    }

    public function test(): bool {
        return parent::test();
    }

    public function save(): bool {
        $factura = new DFacturaCliente();
        $factura->loadFromCode($this->idfactura);

        $this->codigofactura = $factura->codigo;

        return parent::save();
    }

    public function install(): string {
        return parent::install();
    }

    public function url(string $type = 'auto', string $list = 'List'): string {
        if (($type === 'auto' || $type === 'edit') && !empty($this->codigofactura)) {
            return 'DixTPVInvoicePdf?code=' . rawurlencode($this->codigofactura);
        }

        return parent::url($type, $list);
    }
}
