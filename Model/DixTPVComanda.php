<?php

namespace FacturaScripts\Plugins\DixTPV\Model;

use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Core\Model\Base\ModelClass;
use FacturaScripts\Core\Model\Base\ModelTrait;
use FacturaScripts\Plugins\DixTPV\Model\DixEstado as DinDixEstado;
use FacturaScripts\Dinamic\Model\DixMesa as DinDixMesa;
use FacturaScripts\Dinamic\Model\DixSalon;
use FacturaScripts\Dinamic\Model\Cliente;

class DixTPVComanda extends ModelClass {

    use ModelTrait;

    // Propiedades que mapean a la estructura XML
    public $idcomanda;
    public $fecha;
    public $idcliente;
    public $idmesa;
    public $idsalon;
    public $idestado;
    public $total;
    public $observaciones;
    public $nombrecliente;
    public $nombremesa;
    public $nombresalon;

    public function clear(): void {
        parent::clear();
        $this->fecha = date(self::DATETIME_STYLE);
        $this->total = 0.0;
    }

    public static function primaryColumn(): string {
        return 'idcomanda';
    }

    public static function tableName(): string {
        return 'dix_comanda';
    }

    public function test(): bool {
        return parent::test();
    }

    public function install(): string {
        return parent::install();
    }

    public function save(): bool {
        if (!empty($this->idmesa)) {
            $mesa = new DixMesa();
            if ($mesa->loadFromCode($this->idmesa)) {
                $this->nombremesa = $mesa->nombre;
                if (empty($this->idsalon)) {
                    $this->idsalon = $mesa->idsalon;
                }
            }
        } elseif (empty($this->nombremesa)) {
            $this->nombremesa = 'Sin mesa';
        }

        if (!empty($this->idsalon)) {
            $salon = new DixSalon();
            if ($salon->loadFromCode($this->idsalon)) {
                $this->nombresalon = $salon->nombre;
            }
        } elseif (empty($this->nombresalon)) {
            $this->nombresalon = 'Aparcados';
        }

        if (!empty($this->idcliente)) {
            $cliente = new Cliente();
            if ($cliente->loadFromCode($this->idcliente)) {
                $this->nombrecliente = $cliente->nombre;
            }
        }

        return parent::save();
    }
}
