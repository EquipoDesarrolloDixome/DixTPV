<?php

namespace FacturaScripts\Plugins\DixTPV\Extension\Model;

use FacturaScripts\Dinamic\Model\Familia as BaseFamilia;

class Familia extends BaseFamilia
{
    public $color_fondo;
    public $color_texto;
    public $imagen_tpv;

    public function __construct()
    {
        parent::__construct();
        // si aún no los registraste:
        $this->addField('color_fondo', 'string', 7, true, null);
        $this->addField('color_texto', 'string', 7, true, null);
        $this->addField('imagen_tpv', 'string', 255, true, null);
    }

    public function clear(): void
    {
        parent::clear();
        // valores por defecto para NUEVAS familias
        $this->color_fondo = $this->color_fondo ?: '#F2F2F2';
        $this->color_texto = $this->color_texto ?: '#333333';
        $this->imagen_tpv = $this->imagen_tpv ?: '';
    }

    public function getImagenUrl(): string
    {
        if (empty($this->imagen_tpv)) {
            return '';
        }

        return FS_ROUTE . '/MyFiles/' . ltrim($this->imagen_tpv, '/');
    }
}
