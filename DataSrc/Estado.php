<?php

namespace FacturaScripts\Plugins\DixTPV\DataSrc;

use FacturaScripts\Core\DataSrc\DataSrcInterface;
use FacturaScripts\Dinamic\Model\CodeModel;
use FacturaScripts\Dinamic\Model\Estado as EstadoModel;

final class Estado implements DataSrcInterface
{
    private static $list;
    
    public static function all(): array
    {
        if (!isset(self::$list)) {
            $model = new EstadoModel();
            self::$list = $model->all([], [], 0, 0);
        }

        return self::$list;
    }

    public static function clear(): void
    {
        self::$list = null;
    }

    public static function codeModel(bool $addEmpty = true): array
    {
        $codes = [];
        foreach (self::all() as $estado) {
            $codes[$estado->idestado] = $estado->descripcion;
        }

        return CodeModel::array2codeModel($codes, $addEmpty);
    }

    public static function get($code): EstadoModel
    {
        foreach (self::all() as $item) {
            if ($item->primaryColumnValue() === $code) {
                return $item;
            }
        }

        return new EstadoModel();
    }
}
