<?php

namespace FacturaScripts\Plugins\DixTPV\Model;

use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Core\Base\Utils;
use FacturaScripts\Core\Model\Base\ModelClass;
use FacturaScripts\Core\Model\Base\ModelTrait;
use FacturaScripts\Dinamic\Model\DixTerminal as DinDixTerminal;
use FacturaScripts\Dinamic\Model\DixBillete as DinDixBillete;

class DixCaja extends ModelClass {

    use ModelTrait;

    // Propiedades que mapean a la estructura XML
    public $diferencia;
    public $dinerofin;
    public $dineroini;
    public $dinerocredito;
    public $fechafin;
    public $fechaini;
    public $idcaja;
    public $idtpv;
    public $ingresos;
    public $nick;
    public $numtickets;
    public $observaciones;
    public $totalcaja;
    public $totalmovi;
    public $totaltickets;

    public function clear(): void {
        parent::clear();
        $this->diferencia = 0.0;
        $this->dinerofin = 0.0;
        $this->dineroini = 0.0;
        $this->fechaini = date(self::DATETIME_STYLE);
        $this->ingresos = 0.0;
        $this->numtickets = 0;
        $this->totalcaja = 0.0;
        $this->totalmovi = 0.0;
        $this->totaltickets = 0.0;
    }

    public static function primaryColumn(): string {
        return 'idcaja';
    }

    public static function tableName(): string {
        return 'dixtpv_cajas';
    }

    public function test(): bool {
        // Escapamos el HTML de observaciones
        $this->observaciones = Utils::noHtml($this->observaciones);
        return parent::test();
    }

    public function install(): string {
        return parent::install();
    }
}
