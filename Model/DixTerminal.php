<?php

namespace FacturaScripts\Plugins\DixTPV\Model;

use FacturaScripts\Core\Model\Base\ModelClass;
use FacturaScripts\Core\Model\Base\ModelTrait;
use FacturaScripts\Core\Tools;
use FacturaScripts\Dinamic\Model\DixSalon as DinDixSalon;
use FacturaScripts\Dinamic\Model\DixCaja as DinDixCaja;

class DixTerminal extends ModelClass {

    use ModelTrait;

    // Valores por defecto para las propiedades de la clase
    const DEFAULT_DOC_TYPE = 'FacturaCliente';
    const DEFAULT_LIST_TYPE = 'Variant';
    const DEFAULT_TICKET_FORMAT = 'Normal';

    // Propiedades que mapean a la estructura XML
    public $active;
    public $adddiscount;
    public $adddiscount1;
    public $changeprice;
    public $codalmacen;
    public $codcliente;
    public $coddivisa;
    public $codpago;
    public $codserie;
    public $doctype;
    public $grouplines;
    public $idempresa;
    public $idtpv;
    public $idprinter;
    public $listtype;
    public $nametpv;
    public $preticket;
    public $sound;
    public $ticketformat;
    public $idcomanda;
    public $tipocomandero;
    public $mostrarcamareros;
    public $mostrarbuscador;
    public $mostrardescripcion;
    public $dineroapertura;
    public $impresion;
    public $cardsize;
    public $hosteleria;

    public function clear(): void {
        parent::clear();
        $this->active = true;
        $this->adddiscount = false;
        $this->adddiscount1 = false;
        $this->changeprice = false;
        $this->codalmacen = Tools::settings('default', 'codalmacen');
        $this->coddivisa = Tools::settings('default', 'coddivisa');
        $this->codpago = Tools::settings('default', 'codpago');
        $this->codserie = $this->getSimplifiedSerie();
        $this->doctype = self::DEFAULT_DOC_TYPE;
        $this->grouplines = true;
        $this->preticket = false;
        $this->sound = false;
        $this->ticketformat = self::DEFAULT_TICKET_FORMAT;
        $this->idcomanda = null;
        $this->mostrarcamareros = true;
        $this->mostrarbuscador = true;
        $this->mostrardescripcion = false;
        $this->dineroapertura = 0.0;
        $this->impresion = 1;
        $this->cardsize = 'medium';
        $this->hosteleria = true;
    }

    public static function primaryColumn(): string {
        return 'idtpv';
    }

    public static function tableName(): string {
        return 'dixtpv';
    }

    public function install(): string {
        return parent::install();
    }

    public function getSimplifiedSerie() {
        return '';
    }

    public function test(): bool {

        return parent::test(); // Llama al método de prueba de la clase base
    }

    public function getWarehouse() {
        return '';
    }
}
