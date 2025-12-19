<?php

namespace FacturaScripts\Plugins\DixTPV\Model;

use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Core\Model\Base\ModelClass;
use FacturaScripts\Core\Model\Base\ModelTrait;
use FacturaScripts\Core\Session;
use FacturaScripts\Core\Tools;

class DixMovimiento extends ModelClass {

    use ModelTrait;

    // Propiedades que mapean a la estructura XML
    public $amount;
    public $creationdate;
    public $id;
    public $idasiento;
    public $idcaja;
    public $idtpv;
    public $lastnick;
    public $lastupdate;
    public $motive;
    public $nick;

    public function clear(): void {
        parent::clear();
        $this->amount = 0.0;
        $this->creationdate = Tools::dateTime();
        $this->nick = empty(Session::get('user')) ? null : Session::get('user')->nick;
    }

    public static function primaryColumn(): string {
        return "id";
    }

    public static function tableName(): string {
        return "dixtpv_movimientos";
    }

    public function test(): bool {
        $this->motive = Tools::noHtml($this->motive);
        return parent::test();
    }

    public function install(): string {

        return parent::install();
    }
}
