<?php
namespace FacturaScripts\Plugins\DixTPV\Model;

use FacturaScripts\Core\Model\Base\ModelClass;
use FacturaScripts\Core\Model\Base\ModelTrait;

class DixProductosResumen extends ModelClass
{
    use ModelTrait;

    public $idprodresumen;
    public $idcaja;
    public $claveprod;     // referencia o descripción normalizada
    public $referencia;    // opcional
    public $descripcion;   // opcional
    public $unidades;      // suma de cantidades
    public $total;         // suma de (cantidad * pvpunitario SIN IVA)

    public static function primaryColumn(): string { return 'idprodresumen'; }
    public static function tableName(): string     { return 'dix_productos_resumen'; }

    public function clear(): void
    {
        parent::clear();
        $this->unidades = 0.0;
        $this->total    = 0.0;
    }
}
