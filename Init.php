<?php

namespace FacturaScripts\Plugins\DixTPV;

use FacturaScripts\Core\Template\InitClass;
use FacturaScripts\Plugins\DixTPV\Model\DixTPVComanda as dinDixTPVComanda;
use FacturaScripts\Plugins\DixTPV\Model\DixBillete as dinDixBillete;
use FacturaScripts\Plugins\DixTPV\Model\DixDetailComanda as dinDixDetailComanda;
use FacturaScripts\Plugins\DixTPV\Model\DixEstado as dinDixEstado;
use FacturaScripts\Plugins\DixTPV\Model\DixMesa as dinDixMesa;
use FacturaScripts\Plugins\DixTPV\Model\DixMovimiento as dinDixMovimiento;
use FacturaScripts\Plugins\DixTPV\Model\DixReserva as dinDixReserva;
use FacturaScripts\Plugins\DixTPV\Model\DixSalon as dinDixSalon;
use FacturaScripts\Plugins\DixTPV\Model\DixTPVCaja as dinDixTPVCaja;
use FacturaScripts\Plugins\DixTPV\Model\DixTerminal as dinDixTerminal;
use FacturaScripts\Plugins\DixTPV\Model\DixTPVComandero as dinDixTPVComandero;
use FacturaScripts\Plugins\DixTPV\Model\DixTarifa as dinDixTarifa;
use FacturaScripts\Plugins\DixTPV\Model\DixTipoMesa as dinDixTipoMesa;
use FacturaScripts\Plugins\DixTPV\Model\DixListaFin as dinListaFin;
use FacturaScripts\Plugins\DixTPV\Model\DixFormasPagoResumen as dinDixFormasPagoResumen;
use FacturaScripts\Plugins\DixTPV\Model\DixTipoIVAResumen as dinDixTipoIVAResumen;
use FacturaScripts\Plugins\DixTPV\Model\DixProductosResumen as dinDixProductosResumen;
use FacturaScripts\Plugins\DixTPV\Model\DixAlbaranCaja as dinDixAlbaranCaja;
use FacturaScripts\Plugins\DixTPV\Model\DixPedidoCaja as dinDixPedidoCaja;
use FacturaScripts\Plugins\Tickets\Controller\SendTicket;
use FacturaScripts\Plugins\DixTPV\Lib\Tickets\CashClosingTicket as DixCashClosingTicket;

use FacturaScripts\Core\Base\DataBase;
use FacturaScripts\Core\Kernel;
use FacturaScripts\Core\Controller\ApiRoot;
use FacturaScripts\Core\Tools;

/**
 * Clase de arranque del plugin DixTPV que registra modelos y gestiona ciclo de vida.
 */
class Init extends InitClass {

    private static $detailTaxColumnChecked = false;

    /**
     * Registra los modelos personalizados para que esten disponibles en FacturaScripts.
     */
    public function init():void {
        new dinDixSalon();
        new dinDixEstado();
        new dinDixTipoMesa();
        new dinDixTarifa();

        new dinDixMesa();
        new dinDixTPVComanda();
        new dinDixDetailComanda();
        new dinDixReserva();
        new dinDixTerminal();
        new dinDixTPVCaja();
        new dinDixMovimiento();
        new dinDixProductosResumen();
        new dinDixTipoIVAResumen();
        new dinDixFormasPagoResumen();
        new dinDixTPVComandero();
        new dinListaFin();
        $albaranCaja = new dinDixAlbaranCaja();
        $albaranCaja->install();
        $pedidoCaja = new dinDixPedidoCaja();
        $pedidoCaja->install();

        $this->ensureDetailTaxColumn();

        if (class_exists(SendTicket::class)) {
            SendTicket::addFormat(DixCashClosingTicket::class, 'DixTPVCaja', 'dix-cashclosing');
        }

        $this->createPublicFolders();
    }

    /**
     * Punto reservado para ejecutar migraciones cuando se actualiza el plugin.
     */
    public function update():void {
        
    }

    /**
     * Limpia recursos al desinstalar el plugin (actualmente sin logica adicional).
     */
    public function uninstall():void {
        // se ejecuta cada vez que se desinstale el plugin. Primero desinstala y luego ejecuta el uninstall.
    }

    private function createPublicFolders(): void
    {
        $publicFolder = Tools::folder('MyFiles', 'Public');
        $certificateFolder = Tools::folder('MyFiles', 'Public', 'Certificate');
        $certificatesFolder = Tools::folder('MyFiles', 'Certificate');
        Tools::folderCheckOrCreate($publicFolder);
        Tools::folderCheckOrCreate($certificateFolder);
        Tools::folderCheckOrCreate($certificatesFolder);

        $sourceFolder = Tools::folder('Plugins', 'DixTPV', 'Resources', 'Public', 'Certificate');
        if (false === file_exists($sourceFolder)) {
            return;
        }

        $files = [
            'digital-certificate.txt',
            'private-key.pem',
            'private-key.pass',
            'public-key.txt',
            'sign-message.php'
        ];

        foreach ($files as $file) {
            $sourceFile = $sourceFolder . DIRECTORY_SEPARATOR . $file;
            if (false === file_exists($sourceFile)) {
                continue;
            }

            $publicTarget = $certificateFolder . DIRECTORY_SEPARATOR . $file;
            if (!file_exists($publicTarget)) {
                copy($sourceFile, $publicTarget);
            }

            $privateTarget = $certificatesFolder . DIRECTORY_SEPARATOR . $file;
            if (!file_exists($privateTarget)) {
                copy($sourceFile, $privateTarget);
            }
        }
    }

    private function ensureDetailTaxColumn(): void
    {
        if (self::$detailTaxColumnChecked) {
            return;
        }

        self::$detailTaxColumnChecked = true;

        $dataBase = new DataBase();
        if (false === $dataBase->connect()) {
            return;
        }

        $columns = $dataBase->getColumns('dix_details_comanda');
        foreach ($columns as $column) {
            if (($column['name'] ?? '') === 'codimpuesto') {
                return;
            }
        }

        $dataBase->exec('ALTER TABLE dix_details_comanda ADD COLUMN codimpuesto character varying(50)');
    }
}
