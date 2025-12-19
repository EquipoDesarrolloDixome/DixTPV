<?php

namespace FacturaScripts\Plugins\DixTPV\Model;

use FacturaScripts\Core\Template\ModelClass;
use FacturaScripts\Core\Template\ModelTrait;
use FacturaScripts\Core\Tools;
use FacturaScripts\Dinamic\Model\Empresa;

class DixTPVCaja extends ModelClass {

    use ModelTrait;

    public $idcaja;
    public $nombre;
    public $pertenenciaterminal;
    public $dineroini;
    public $dinerofin;
    public $descuadre;
    public $fechahoraapertura;
    public $fechahoracierre;
    public $camareroini;
    public $camarerofin;
    public $dinerocierre;

    public function clear(): void {
        parent::clear();
    }

    public static function primaryColumn(): string {
        return 'idcaja';
    }

    public static function tableName(): string {
        return 'dixtpv_cajas';
    }

    public function test(): bool {
        return parent::test();
    }

    public function install(): string {

        return parent::install();
    }

    public function getCompany(): Empresa
    {
        $company = new Empresa();
        $companyId = Tools::settings('default', 'idempresa');
        if (!empty($companyId)) {
            $company->loadFromCode($companyId);
        }

        return $company;
    }
}
