<?php

namespace FacturaScripts\Plugins\DixTPV\Controller;

use FacturaScripts\Core\Base\Controller;
use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Dinamic\Model\FormaPago;
use FacturaScripts\Dinamic\Model\FormatoDocumento;
use FacturaScripts\Dinamic\Model\Agente;
use FacturaScripts\Dinamic\Model\Familia;
use FacturaScripts\Dinamic\Model\Cliente;
use FacturaScripts\Dinamic\Model\Producto;
use FacturaScripts\Dinamic\Model\Variante;
use FacturaScripts\Dinamic\Model\DixMesa;
use FacturaScripts\Dinamic\Model\DixTPVCaja;
use FacturaScripts\Dinamic\Model\DixSalon;
use FacturaScripts\Dinamic\Model\DixDetailComanda;
use FacturaScripts\Dinamic\Model\FacturaCliente;
use FacturaScripts\Dinamic\Model\LineaFacturaCliente;
use FacturaScripts\Dinamic\Model\AlbaranCliente;
use FacturaScripts\Dinamic\Model\LineaAlbaranCliente;
use FacturaScripts\Dinamic\Model\PedidoCliente;
use FacturaScripts\Dinamic\Model\LineaPedidoCliente;
use FacturaScripts\Dinamic\Model\PresupuestoCliente;
use FacturaScripts\Dinamic\Model\LineaPresupuestoCliente;
use FacturaScripts\Plugins\DixTPV\Model\DixTPVComanda;
use FacturaScripts\Plugins\DixTPV\Model\DixTerminal;
use FacturaScripts\Plugins\DixTPV\Model\DixAlbaranCaja;
use FacturaScripts\Plugins\DixTPV\Model\DixPedidoCaja;
use FacturaScripts\Core\Html;
use FacturaScripts\Core\Plugins;
use FacturaScripts\Dinamic\Lib\AssetManager;
use FacturaScripts\Dinamic\Lib\BusinessDocumentGenerator;
use FacturaScripts\Core\Tools;
use FacturaScripts\Plugins\DixTPV\Funciones\Funciones;
use FacturaScripts\Core\Base\Calculator;
use FacturaScripts\Plugins\DixTPV\Lib\UtilsTPV;
use FacturaScripts\Plugins\DixTPV\Lib\QzSecurity;
use FacturaScripts\Plugins\PrintTicket\Lib\PrintingService;
use FacturaScripts\Dinamic\Model\FormatoTicket;
use FacturaScripts\Core\Model\Impuesto;
use FacturaScripts\Dinamic\Model\Contacto;
use FacturaScripts\Dinamic\Model\Pais;
use FacturaScripts\Dinamic\Model\IdentificadorFiscal;
use FacturaScripts\Dinamic\Model\Serie;
use FacturaScripts\Dinamic\Model\DocTransformation;
use FacturaScripts\Dinamic\Model\TicketPrinter;
use FacturaScripts\Plugins\Tickets\Controller\SendTicket;
use FacturaScripts\Dinamic\Model\AttachedFile;
use FacturaScripts\Dinamic\Model\ReciboCliente;
use FacturaScripts\Core\Session;
use FacturaScripts\Dinamic\Model\Tarifa;

/**
 * Controlador de las ventas POS para Dixome
 *
 *  
 * @author Alexis Cambeiro <alexis@dixome.com>
 */
class DixTPV extends Controller {
    /*     * * Creamos propiedades públicas, estas propiedades son accesibles desde twig  a través de 
     * fsc  (fs.camareros)
     */

    public $camareros;
    public $familias;
    public $theme;
    public $mesas;
    public $salones;
    public $aparcados;
    public $terminales;
    public $cajas;
    public $cajaActivaFecha;
    public $formaPago;
    public $cestaDeCompra;
    public $idCaja;
    public $FormatoDocumento;
    public $anteriores;
    public $anterioresFacturas = [];
    public $anterioresAlbaranes = [];
    public $anterioresPedidos = [];
    public $clientes;
    public $defaultClient;
    public $defaultPaymentMethod;
    public $selectedTerminal;
    public $mostrarCamarero;
    public $mostrarCamareroPorPin = false;
    public $mostrarBuscador = true;
    public $mostrarDescripcionCartas = false;
    public $cardSizeClass = 'medium';
    public $printPolicy;
    private $taxPercentCache = [];
    public $paises;
    public $tipoIdFiscales;
    public $series;
    public $printers;
    public $qzConfig;
    public $rectifySeries = [];
    public $defaultRectifySerie = '';
    private $seriesMap = [];
    public $modoHosteleria = true;
    public $advancedTariffsEnabled = false;
    public $printTicketAvailable = false;
    private $clientCache = [];
    private $variantCache = [];
    private $tariffPriceCache = [];
    private $productTaxCache = [];

    public function getPageData(): array {
        $pageData = parent::getPageData();
        $pageData["title"] = "DixPOS";
        $pageData["menu"] = "DixTPV";
        $pageData["icon"] = "fa-solid fa-desktop";
        return $pageData;
    }

// En el método privateCore o donde se maneja la solicitud principal
    public function privateCore(&$response, $user, $permissions) {
        parent::privateCore($response, $user, $permissions);
        $this->theme = 'DixTPV/Hosteleria/Ventas/';

        AssetManager::add('css', FS_ROUTE . '/node_modules/jquery-ui-dist/jquery-ui.min.css');

        $this->loadBasicData();

        $action = $this->request->get('action', '');
        if ($action) {
            $this->execAfterAction($action);
        } else {
            $this->createView();
        }
    }

    protected function loadBasicData(): void {
        $this->camareros = $this->loadModel(Agente::class);
        $this->familias = $this->loadModel(Familia::class);
        $this->advancedTariffsEnabled = Plugins::isEnabled('TarifasAvanzadas');
        $this->printTicketAvailable = Plugins::isEnabled('PrintTicket');
        foreach ($this->familias as $family) {
            if (false === is_object($family)) {
                continue;
            }

            $fileName = '';
            if (isset($family->imagen_tpv)) {
                $fileName = trim((string) $family->imagen_tpv);
            }

            if (empty($fileName) && method_exists($family, 'getImagenUrl')) {
                $fileName = trim((string) $family->getImagenUrl());
            }

            if (!empty($fileName) && ctype_digit($fileName)) {
                $attachment = new AttachedFile();
                if ($attachment->load($fileName)) {
                    $fileName = $attachment->url('download-permanent');
                }
            }

            $family->imagen_url = $this->buildPublicUrl($fileName);
        }

        // ✅ Añadir "Sin familia" SOLO si hay productos sin codfamilia
        $productoModel = new Producto();
        $haySinFamilia = $productoModel->all([
            new DataBaseWhere('codfamilia', null, 'IS'),
            new DataBaseWhere('codfamilia', '', '=', 'OR'),
            new DataBaseWhere('sevende', true)
                ], [], 0, 1); // con 1 basta para saber si existe

        if (!empty($haySinFamilia)) {
            $this->familias[] = (object) [
                        'codfamilia' => 'sin_familia', // ← clave interna
                        'descripcion' => 'Sin familia', // ← lo que se muestra en el botón
                        'color_fondo' => '#F2F2F2', // ⬅ por defecto
                        'color_texto' => '#333333',
                        'imagen_tpv' => '',
                        'imagen_url' => ''
            ];
        }

        $this->mesas = $this->loadModel(DixMesa::class);
        $this->salones = $this->loadModel(DixSalon::class);
        $this->aparcados = $this->loadModel(DixTPVComanda::class);
        $this->terminales = $this->loadModel(DixTerminal::class);
        $this->cajas = $this->loadModel(DixTPVCaja::class);
        $this->formaPago = $this->loadModel(FormaPago::class);
        $this->FormatoDocumento = $this->loadModel(FormatoDocumento::class);
        $this->anterioresFacturas = $this->loadModel(FacturaCliente::class);
        $this->anterioresAlbaranes = $this->loadModel(AlbaranCliente::class);
        $this->anterioresPedidos = $this->loadModel(PedidoCliente::class);
        $this->anteriores = $this->anterioresFacturas;
        $this->decoratePreviousDocuments();
        $this->clientes = $this->loadModel(Cliente::class);
        if (false === $this->advancedTariffsEnabled && $this->anyClientHasTariff()) {
            $this->advancedTariffsEnabled = true;
        }
        $this->series = $this->loadModel(Serie::class);
        $this->rectifySeries = [];
        $this->seriesMap = [];
        foreach ($this->series as $serie) {
            $code = (string) ($serie->codserie ?? '');
            if ($code === '') {
                continue;
            }
            $this->seriesMap[$code] = $serie;
            if ('R' === ($serie->tipo ?? '')) {
                $this->rectifySeries[] = [
                    'codserie' => $code,
                    'descripcion' => $serie->descripcion,
                    'tipo' => $serie->tipo
                ];
            }
        }
        $this->defaultRectifySerie = $this->rectifySeries[0]['codserie'] ?? ($this->series[0]->codserie ?? '');
        $this->printers = $this->loadModel(TicketPrinter::class);
        $this->qzConfig = QzSecurity::getFrontendConfig();
        $this->defaultClient = new Cliente();
        $this->paises = $this->loadModel(Pais::class);
        $this->tipoIdFiscales = $this->loadModel(IdentificadorFiscal::class);
        $terminal = new DixTerminal();

        // Intentamos cargar la terminal desde la caja activa
        $caja = new DixTPVCaja();
        $cajaAbierta = $caja->all([new DataBaseWhere('fechahoracierre', null)]);

        if (!empty($cajaAbierta) && $terminal->loadFromCode($cajaAbierta[0]->pertenenciaterminal)) {
            $this->defaultClient->loadFromCode($terminal->codcliente ?: '1');
        } else {
            $this->defaultClient->loadFromCode('1');
            if (!empty($this->terminales)) {
                $firstTerminal = reset($this->terminales);
                if ($firstTerminal instanceof DixTerminal) {
                    $terminal = clone $firstTerminal;
                }
            }
        }
        $this->selectedTerminal = $terminal;
        $this->mostrarCamarero = (bool) $this->selectedTerminal->mostrarcamareros;
        $this->mostrarCamareroPorPin = (bool) ($this->selectedTerminal->mostrarporpin ?? false);
        $this->mostrarBuscador = (bool) ($this->selectedTerminal->mostrarbuscador ?? true);
        $this->mostrarDescripcionCartas = (bool) ($this->selectedTerminal->mostrardescripcion ?? false);
        $this->cardSizeClass = $this->sanitizeCardSize($this->selectedTerminal->cardsize ?? 'medium');
        $this->printPolicy = (int)($this->selectedTerminal->impresion ?? 1);
        $this->modoHosteleria = (bool) ($this->selectedTerminal->hosteleria ?? true);
        $this->prepareAparcadosForDisplay();

        $this->cestaDeCompra = [];
    }

    private function startSession(): void {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    private function loadModel(string $modelClass): array {
        $model = new $modelClass();
        return $model->all([], [], 0, 0);
    }

    private function decoratePreviousDocuments(): void
    {
        $this->markConvertedAlbaranes();
        $this->markPedidoConversions();
    }

    private function markConvertedAlbaranes(): void
    {
        if (empty($this->anterioresAlbaranes)) {
            return;
        }

        $ids = [];
        foreach ($this->anterioresAlbaranes as $albaran) {
            if ($albaran instanceof AlbaranCliente && !empty($albaran->idalbaran)) {
                $ids[] = (int) $albaran->idalbaran;
            }
        }

        $ids = array_unique(array_filter($ids));
        if (empty($ids)) {
            return;
        }

        $map = $this->fetchAlbaranInvoiceMap($ids);
        foreach ($this->anterioresAlbaranes as $albaran) {
            $id = (int) ($albaran->idalbaran ?? 0);
            if (isset($map[$id])) {
                $albaran->facturado = true;
                $albaran->facturaId = $map[$id]['id'];
                $albaran->facturaCodigo = $map[$id]['codigo'];
            } else {
                $albaran->facturado = false;
                $albaran->facturaId = null;
                $albaran->facturaCodigo = null;
            }
        }
    }

    private function fetchAlbaranInvoiceMap(array $albaranIds): array
    {
        if (empty($albaranIds)) {
            return [];
        }

        $docTransModel = new DocTransformation();
        $where = [
            new DataBaseWhere('model1', 'AlbaranCliente'),
            new DataBaseWhere('model2', 'FacturaCliente'),
            new DataBaseWhere('iddoc1', $albaranIds, 'IN')
        ];
        $relations = $docTransModel->all($where, [], 0, 0);
        if (empty($relations)) {
            return [];
        }

        $invoiceIds = [];
        foreach ($relations as $relation) {
            $invoiceIds[] = (int) ($relation->iddoc2 ?? 0);
        }
        $invoiceIds = array_unique(array_filter($invoiceIds));

        $invoiceMap = [];
        if (!empty($invoiceIds)) {
            $invoiceModel = new FacturaCliente();
            $invoices = $invoiceModel->all([new DataBaseWhere('idfactura', $invoiceIds, 'IN')], [], 0, 0);
            foreach ($invoices as $invoice) {
                $invoiceMap[(int) $invoice->idfactura] = $invoice;
            }
        }

        $result = [];
        foreach ($relations as $relation) {
            $albaranId = (int) ($relation->iddoc1 ?? 0);
            $invoiceId = (int) ($relation->iddoc2 ?? 0);
            if ($albaranId <= 0 || $invoiceId <= 0) {
                continue;
            }

            $invoice = $invoiceMap[$invoiceId] ?? null;
            $result[$albaranId] = [
                'id' => $invoiceId,
                'codigo' => $invoice ? (string) $invoice->codigo : '',
                'pagada' => $invoice ? (bool) $invoice->pagada : false,
                'fecha' => $invoice ? (string) ($invoice->fecha ?? '') : '',
                'hora' => $invoice ? (string) ($invoice->hora ?? '') : ''
            ];
        }

        return $result;
    }

    private function resolveAlbaranInvoiceData(int $albaranId): array
    {
        if ($albaranId <= 0) {
            return [];
        }

        $map = $this->fetchAlbaranInvoiceMap([$albaranId]);
        return $map[$albaranId] ?? [];
    }

    private function resolvePedidoConversionData(int $pedidoId): array
    {
        if ($pedidoId <= 0) {
            return [];
        }

        $map = $this->fetchPedidoConversionMap([$pedidoId]);
        return $map[$pedidoId] ?? [];
    }

    private function markPedidoConversions(): void
    {
        if (empty($this->anterioresPedidos)) {
            return;
        }

        $ids = [];
        foreach ($this->anterioresPedidos as $pedido) {
            if ($pedido instanceof PedidoCliente && !empty($pedido->idpedido)) {
                $ids[] = (int)$pedido->idpedido;
            }
        }

        $ids = array_unique(array_filter($ids));
        if (empty($ids)) {
            return;
        }

        $map = $this->fetchPedidoConversionMap($ids);
        foreach ($this->anterioresPedidos as $pedido) {
            $id = (int)($pedido->idpedido ?? 0);
            if (isset($map[$id])) {
                $pedido->albaranDesdePedido = $map[$id]['albaran'] ?? [];
                $pedido->facturaDesdePedido = $map[$id]['factura'] ?? [];
            } else {
                $pedido->albaranDesdePedido = [];
                $pedido->facturaDesdePedido = [];
            }
        }
    }

    private function fetchPedidoConversionMap(array $pedidoIds): array
    {
        if (empty($pedidoIds)) {
            return [];
        }

        $docTransModel = new DocTransformation();
        $where = [
            new DataBaseWhere('model1', 'PedidoCliente'),
            new DataBaseWhere('iddoc1', $pedidoIds, 'IN'),
            new DataBaseWhere('model2', ['AlbaranCliente', 'FacturaCliente'], 'IN')
        ];
        $relations = $docTransModel->all($where, [], 0, 0);
        if (empty($relations)) {
            return [];
        }

        $albaranIds = [];
        $facturaIds = [];
        foreach ($relations as $relation) {
            if ('AlbaranCliente' === ($relation->model2 ?? '')) {
                $albaranIds[] = (int)($relation->iddoc2 ?? 0);
            } elseif ('FacturaCliente' === ($relation->model2 ?? '')) {
                $facturaIds[] = (int)($relation->iddoc2 ?? 0);
            }
        }

        $albaranMap = [];
        if (!empty($albaranIds)) {
            $albaranModel = new AlbaranCliente();
            $albaranes = $albaranModel->all([new DataBaseWhere('idalbaran', array_unique(array_filter($albaranIds)), 'IN')], [], 0, 0);
            foreach ($albaranes as $albaran) {
                $albaranMap[(int)$albaran->idalbaran] = $albaran;
            }
        }

        $facturaMap = [];
        if (!empty($facturaIds)) {
            $facturaModel = new FacturaCliente();
            $facturas = $facturaModel->all([new DataBaseWhere('idfactura', array_unique(array_filter($facturaIds)), 'IN')], [], 0, 0);
            foreach ($facturas as $factura) {
                $facturaMap[(int)$factura->idfactura] = $factura;
            }
        }

        $result = [];
        foreach ($relations as $relation) {
            $pedidoId = (int)($relation->iddoc1 ?? 0);
            $targetId = (int)($relation->iddoc2 ?? 0);
            if ($pedidoId <= 0 || $targetId <= 0) {
                continue;
            }

            if (!isset($result[$pedidoId])) {
                $result[$pedidoId] = [];
            }

            if ('AlbaranCliente' === ($relation->model2 ?? '')) {
                $albaran = $albaranMap[$targetId] ?? null;
                $result[$pedidoId]['albaran'] = [
                    'id' => $targetId,
                    'codigo' => $albaran ? (string)$albaran->codigo : '',
                    'fecha' => $albaran ? (string)($albaran->fecha ?? '') : '',
                    'hora' => $albaran ? (string)($albaran->hora ?? '') : ''
                ];
            } elseif ('FacturaCliente' === ($relation->model2 ?? '')) {
                $factura = $facturaMap[$targetId] ?? null;
                $result[$pedidoId]['factura'] = [
                    'id' => $targetId,
                    'codigo' => $factura ? (string)$factura->codigo : '',
                    'fecha' => $factura ? (string)($factura->fecha ?? '') : '',
                    'hora' => $factura ? (string)($factura->hora ?? '') : ''
                ];
            }
        }

        return $result;
    }

    /** Un turno estará abierto si tenemos en la sesión (o en la cookie) asignado un camarero y una caja abierta 
     * 
     * @return bool
     */
    protected function turnoAbierto(): bool {
        $caja = new DixTPVCaja();
        $cajaAbierta = $caja->all([new DataBaseWhere('fechahoracierre', null)]);
        if (!empty($cajaAbierta)) {
            $this->cajaActivaFecha = $cajaAbierta[0]->fechahoraapertura;
            $this->idCaja = $cajaAbierta[0]->idcaja;
            $this->selectedTerminal = new DixTerminal();
            $this->selectedTerminal->loadFromCode($cajaAbierta[0]->pertenenciaterminal);
            $this->defaultPaymentMethod = new FormaPago();
            $this->defaultPaymentMethod->loadFromCode($this->selectedTerminal->codpago);
            $this->mostrarCamarero = (bool) $this->selectedTerminal->mostrarcamareros;
            $this->mostrarCamareroPorPin = (bool) ($this->selectedTerminal->mostrarporpin ?? false);
            $this->mostrarBuscador = (bool) ($this->selectedTerminal->mostrarbuscador ?? true);
            $this->mostrarDescripcionCartas = (bool) ($this->selectedTerminal->mostrardescripcion ?? false);
            $this->cardSizeClass = $this->sanitizeCardSize($this->selectedTerminal->cardsize ?? 'medium');
            $this->printPolicy = (int)($this->selectedTerminal->impresion ?? 1);
            $this->modoHosteleria = (bool) ($this->selectedTerminal->hosteleria ?? true);
            $this->prepareAparcadosForDisplay();

            return true;
        }
        return false;
    }

    private function sanitizeCardSize(?string $rawSize): string
    {
        $allowed = ['small', 'medium', 'large'];
        $value = strtolower((string)$rawSize);
        return in_array($value, $allowed, true) ? $value : 'medium';
    }

    public function cerrarCajaAuto()
    {
        $camarero    = floatval($this->request->get('camarero'));
        $dineroFinal = floatval($this->request->get('dineroFinal'));

        $caja = new DixTPVCaja();
        $cajaAbierta = $caja->all([new DataBaseWhere('fechahoracierre', null)]);

        if (empty($cajaAbierta)) {
            echo json_encode(['status' => 'error', 'message' => 'No hay ninguna caja abierta.']);
            exit;
        }

        $caja = $cajaAbierta[0];

        $esperado = (float)($caja->dinerofin ?? 0.0) + (float)($caja->dinerocredito ?? 0.0) + (float)($caja->dineroini ?? 0.0);
        $caja->dinerocierre    = $esperado;
        $caja->fechahoracierre = date('Y-m-d H:i:s');
        $diferencia = 0.0;
        $caja->descuadre       = 0.0;
        $caja->camarerofin     = $camarero;
        $caja->save();

        $docInfo = $this->generarDocCierreCaja($caja);

        $terminal = new DixTerminal();
        $terminalLoaded = $terminal->loadFromCode($caja->pertenenciaterminal);
        if ($terminalLoaded) {
            if (!empty($terminal->idprinter)) {
                $docInfo['printerId'] = (int)$terminal->idprinter;

                $printer = new TicketPrinter();
                if ($printer->loadFromCode($terminal->idprinter)) {
                    $docInfo['paperWidth'] = $printer->linelen <= 32 ? '58' : '80';
                }
            }

            if (!empty($terminal->ticketformat) && false !== strpos($terminal->ticketformat, '\\')) {
                $docInfo['formatClass'] = $terminal->ticketformat;
            }
        } else {
            $terminal = null;
        }

        $docInfo['formatClass'] = $docInfo['formatClass'] ?? $this->resolveClosingTicketClass($terminal);

        $response = [
            'status'  => 'success',
            'message' => 'Caja cerrada correctamente.',
            'docInfo' => $docInfo
        ];

        header('Content-Type: application/json');
        echo json_encode($response, JSON_UNESCAPED_UNICODE);
        exit;
    }


    function cerrarCaja() {
        $aceptaDescuadre = $this->request->get('aceptaDescuadre');
        $dineroFinal = floatval($this->request->get('dineroFinal'));
        $camarero = floatval($this->request->get('camarero'));

        // Obtener la caja abierta
        $caja = new DixTPVCaja();
        $cajaAbierta = $caja->all([new DataBaseWhere('fechahoracierre', null)]);

        if (empty($cajaAbierta)) {
            echo json_encode(['status' => 'error', 'message' => 'No hay ninguna caja abierta.']);
            exit;
        }

        $caja = $cajaAbierta[0];

        $dineroParaCerrar = $caja->dinerofin + $caja->dinerocredito + $caja->dineroini;

        $difference = $dineroFinal - $dineroParaCerrar;

        if (!empty($aceptaDescuadre)) {
            $caja->descuadre = abs($difference) < 0.005 ? 0.0 : $difference;
            $caja->dinerocierre = $dineroFinal;
            $caja->camarerofin = $camarero;
            $caja->fechahoracierre = date('Y-m-d H:i:s');
            $caja->save();

            echo json_encode(['status' => 'success', 'docInfo' => $this->buildCajaDocInfo($caja)], JSON_UNESCAPED_UNICODE);
            exit;
        } elseif (abs($difference) < 0.005) {
            $caja->descuadre = 0;
            $caja->dinerocierre = $dineroFinal;
            $caja->camarerofin = $camarero;
            $caja->fechahoracierre = date('Y-m-d H:i:s');
            $caja->save();

            echo json_encode(['status' => 'success', 'docInfo' => $this->buildCajaDocInfo($caja)], JSON_UNESCAPED_UNICODE);
            exit;
        }

        echo json_encode(['status' => 'error', 'message' => 'No se pudo cerrar la caja.']);
    }

    protected function createView() {
        if (empty($this->camareros)) {
            $this->redirect('ListAgente');
        } else if (empty($this->terminales)) {
            $this->redirect('ListDixTerminal');
        } else if (empty($this->clientes)) {
            $this->redirect('ListCliente');
        } else {
            $this->setTemplate($this->turnoAbierto() ? 'DixTPV/Hosteleria/Ventas/DixTPV' : 'DixTPV/Hosteleria/Ventas/nuevoTurnoSlid');
        }
    }

    protected function execAfterAction($action) {

        switch ($action) {
            case 'searchclient':
                $this->setTemplate(false);
                $result = $this->searchClient();
                $this->response->setContent(json_encode($result, 1));
                break;
            case 'getFamilyProducts':
                $htmlCode = $this->renderFamilyProducts();
                $this->setTemplate(false);
                $this->response->setContent(json_encode([
                    'htmlFamily' => $htmlCode,
                    'subamilias' => 'ok',
                    'resultado' => 'OK'], 1));
                break;
            case 'searchProducts':
                $this->setTemplate(false);
                $searchResponse = $this->searchProductsAjax();
                $this->response->setContent(json_encode($searchResponse, JSON_UNESCAPED_UNICODE));
                break;
            case 'quoteTariffPrice':
                $this->setTemplate(false);
                $this->response->setContent(json_encode($this->quoteTariffPrice(), JSON_UNESCAPED_UNICODE));
                break;

            case 'aparcarCuenta':
                $this->setTemplate(false);
                $this->aparcarCuenta(); // ✅ La función ya devuelve JSON, no necesitas otro json_encode()
                break;

            case 'recuperarAparcado':
                $this->recuperarAparcado();
                break;
            case 'get-aparcados':
                $this->setTemplate(false);

                $this->response->setContent(json_encode($this->aparcados, 1));
                break;
            case 'deleteAparcado':
                $this->setTemplate(false);
                $deleteResult = $this->borrarAparcado();
                $this->response->setContent(json_encode($deleteResult, JSON_UNESCAPED_UNICODE));
                break;
            case 'printAparcado':
                $this->setTemplate(false);
                $printResult = $this->printAparcadoTicket();
                $this->response->setContent(json_encode($printResult, JSON_UNESCAPED_UNICODE));
                break;
            case 'modificarProducto':
                $this->modificarProducto();
                break;

            case 'loadInvoiceLines':
                $this->setTemplate(false);
                $this->response->setContent(json_encode($this->loadInvoiceLines(), JSON_UNESCAPED_UNICODE));
                break;

            case 'createRectificativa':
                $this->setTemplate(false);
                $rectResponse = $this->createRectificativaFromInvoice();
                $rectResponse['resultado'] = $rectResponse['success'] ? 'OK' : 'ERROR';
                $this->response->setContent(json_encode($rectResponse, JSON_UNESCAPED_UNICODE));
                break;

            case 'abrirCaja':
                $this->abrirCaja();
                $this->setTemplate(false);
                break;

            case 'cobrarCuenta':
                $resultadoCobro = $this->cobrarCuenta();
                $this->setTemplate(false);
                $payload = $resultadoCobro;
                $payload['resultado'] = $resultadoCobro['success'] ? 'OK' : 'ERROR';
                $this->response->setContent(json_encode($payload, JSON_UNESCAPED_UNICODE));
                break;

            case 'vaciarCesta':
                $this->vaciarCesta();
                $this->setTemplate(false);
                $this->response->setContent(json_encode([
                    'success' => true, // Cambiado a booleano
                    'resultado' => 'OK'
                ]));
                break;

            case 'cobrarCuentaDividida':
                $resultadoCobroDiv = $this->cobrarCuentaDividida();
                $this->setTemplate(false);
                $payload = $resultadoCobroDiv;
                $payload['resultado'] = $resultadoCobroDiv['success'] ? 'OK' : 'ERROR';
                $this->response->setContent(json_encode($payload, JSON_UNESCAPED_UNICODE));
                break;

            case 'cerrarCaja':
                $this->cerrarCaja();
                $this->setTemplate(false);
                $this->response->setContent(json_encode([
                    'success' => 'ok',
                    'resultado' => 'OK'], 1));
                break;

            case 'cerrarCajaAuto':
                $this->cerrarCajaAuto();
                $this->setTemplate(false);
                $this->response->setContent(json_encode([
                    'success' => 'ok',
                    'resultado' => 'OK'], 1));
                break;

            case 'seleccionTerminal':
                $this->seleccionTerminal();
                $this->setTemplate(false);
                $this->response->setContent(json_encode([
                    'success' => 'ok',
                    'resultado' => 'OK'], 1));
                break;

            case 'obtenerMesasDisponibles':
                $this->obtenerMesasDisponibles();
                $this->setTemplate(false);
                break;

            case 'guardarPosicionMesa':
                $this->guardarPosicionMesaAction();
                $this->setTemplate(false);
                break;

            case 'resetearPosicionesSalon':
                $this->resetearPosicionesSalonAction();
                $this->setTemplate(false);
                break;

            case 'export':
                $option = $this->request->get('option', '');
                if ($option === 'Ticket') {
                    $response = $this->printTicket();
                    $action = '';
                    $this->setTemplate(false);
                    $this->createView();
                }
                break;
                
            case 'printVoucher':
                $this->setTemplate(false);

                try {
                    $result = $this->printVoucher();
                    $json = json_encode($result, JSON_UNESCAPED_UNICODE);
                    $this->response->setContent($json);
                } catch (\Throwable $e) {
                    $this->response->setContent(json_encode([
                        'resultCode' => 500,
                        'resultMsg'  => $e->getMessage(),
                        'file'       => $e->getFile(),
                        'line'       => $e->getLine()
                    ], JSON_UNESCAPED_UNICODE));
                }
                break;



            case 'get-public-cert':
                $this->servePublicCertificate();
                $this->setTemplate(false);
                break;
            case 'sign-qz-message':
                $this->signQzMessage();
                $this->setTemplate(false);
                break;
            case 'open-drawer':
                $result = $this->openCashDrawer();
                $this->setTemplate(false);
                $this->response->setContent(json_encode($result, JSON_UNESCAPED_UNICODE));
                break;

            case 'anhadirNuevoCliente':
                $this->anhadirCliente();
                $this->setTemplate(false);
                $this->response->setContent(json_encode([
                    'success' => 'ok',
                    'resultado' => 'OK'], 1));
                break;

            case 'pagarFacturaAnterior':
                $this->setTemplate(false);
                $payResult = $this->pagarFacturaAnterior();
                $this->response->setContent(json_encode($payResult, JSON_UNESCAPED_UNICODE));
                break;

            case 'facturarAlbaranAnterior':
                $this->setTemplate(false);
                $convertResult = $this->facturarAlbaranAnterior();
                $this->response->setContent(json_encode($convertResult, JSON_UNESCAPED_UNICODE));
                break;
            case 'pedidoToAlbaran':
                $this->setTemplate(false);
                $pedidoResult = $this->convertPedidoAnterior('AlbaranCliente');
                $this->response->setContent(json_encode($pedidoResult, JSON_UNESCAPED_UNICODE));
                break;
            case 'pedidoToFactura':
                $this->setTemplate(false);
                $pedidoResult = $this->convertPedidoAnterior('FacturaCliente');
                $this->response->setContent(json_encode($pedidoResult, JSON_UNESCAPED_UNICODE));
                break;
        }
    }

    private function servePublicCertificate(): void
    {
        $certificate = QzSecurity::readPublicCertificate();
        if (null === $certificate) {
            $this->response->headers->set('Content-Type', 'text/plain; charset=UTF-8');
            $this->response->setContent('');
            return;
        }

        $this->response->headers->set('Content-Type', 'text/plain; charset=UTF-8');
        $this->response->setContent($certificate);
    }

    private function signQzMessage(): void
    {
        $payload = $this->request->get('payload', '');
        $signature = QzSecurity::signMessage($payload);

        $this->response->headers->set('Content-Type', 'text/plain; charset=UTF-8');
        $this->response->setContent($signature ?? '');
    }

    public function printTicket() {

        $document = new \FacturaScripts\Dinamic\Model\FacturaCliente;
        $template = $this->request->get('template');
        $template = 'FacturaScripts\Dinamic\Lib\Ticket\Builder\\' . $template;
        $id = $this->request->get('code', '');
        if ($id == '') {

            $document->loadFromCode('', [new DataBaseWhere('idfactura', 0, '>')], ['idfactura' => 'DESC']);
        }

        $ticketWidth = Tools::settings('ticket', 'linelength', 50);
        $format = new FormatoTicket();
        $format->loadFromCode('', [], ['id' => 'ASC']);
        $ticketBuilder = new $template($document, $format);

        $response = [
            'print_job_id' => PrintingService::newPrintJob($ticketBuilder)
        ];

        return json_encode($response, 1);
    }

    private function guardarPosicionMesaAction(): void
    {
        $mesaId = (int) $this->request->get('mesaId');
        $posXRaw = $this->request->get('posX');
        $posYRaw = $this->request->get('posY');

        if (empty($mesaId)) {
            $this->response->setContent(json_encode([
                'success' => false,
                'message' => 'Mesa no especificada.'
            ], JSON_UNESCAPED_UNICODE));
            return;
        }

        $mesa = new DixMesa();
        if (false === $mesa->loadFromCode($mesaId)) {
            $this->response->setContent(json_encode([
                'success' => false,
                'message' => 'Mesa no encontrada.'
            ], JSON_UNESCAPED_UNICODE));
            return;
        }

        $mesa->posx = is_numeric($posXRaw) ? (float) $posXRaw : null;
        $mesa->posy = is_numeric($posYRaw) ? (float) $posYRaw : null;

        if (false === $mesa->save()) {
            $this->response->setContent(json_encode([
                'success' => false,
                'message' => 'No se pudo guardar la posición de la mesa.'
            ], JSON_UNESCAPED_UNICODE));
            return;
        }

        $this->response->setContent(json_encode([
            'success' => true,
            'mesaId' => $mesa->idmesa,
            'posicion' => [
                'x' => $mesa->posx,
                'y' => $mesa->posy
            ]
        ], JSON_UNESCAPED_UNICODE));
    }

    private function resetearPosicionesSalonAction(): void
    {
        $salonId = (int) $this->request->get('salonId');

        $where = [];
        if (!empty($salonId)) {
            $where[] = new DataBaseWhere('idsalon', $salonId);
        }

        $mesaModel = new DixMesa();
        $mesas = $mesaModel->all($where, [], 0, 0);

        $actualizadas = 0;
        foreach ($mesas as $mesa) {
            $mesa->posx = null;
            $mesa->posy = null;
            if ($mesa->save()) {
                ++$actualizadas;
            }
        }

        $this->response->setContent(json_encode([
            'success' => true,
            'actualizadas' => $actualizadas
        ], JSON_UNESCAPED_UNICODE));
    }

    protected function printVoucher()
    {
        try {
            $rawCarrito = $this->request->get('carrito', null);
            $carrito = is_string($rawCarrito) ? json_decode($rawCarrito, true) : $rawCarrito;

            if (!is_array($carrito) || empty($carrito)) {
                return [
                    'printed' => false,
                    'msg' => 'Carrito vacío'
                ];
            }

            return $this->printLinesAsVoucher($carrito);
        } catch (\Throwable $e) {
            Tools::log()->error('voucher-print-error', [
                'message' => $e->getMessage()
            ]);

            return [
                'printed' => false,
                'msg' => 'Error imprimiendo comprobante.',
                'error' => $e->getMessage()
            ];
        }
    }

    private function printLinesAsVoucher(array $carrito): array
    {
        $model = new \stdClass();
        $model->lines = [];

        foreach ($carrito as $item) {
            $line = new \stdClass();
            $line->referencia  = $item['referencia'] ?? '';
            $line->descripcion = $item['descripcion'] ?? '';
            $line->cantidad    = (float)($item['cantidad'] ?? 1);
            $line->pvpunitario = (float)($item['pvp'] ?? 0);
            $line->pvptotal    = $line->cantidad * $line->pvpunitario;
            $line->iva         = (float)($item['codimpuesto'] ?? 0);
            $line->recargo     = (float)($item['recargo'] ?? 0);
            $model->lines[]    = $line;
        }

        $printerId = $this->resolvePrinterIdForVoucher();
        $printer = new TicketPrinter();
        if (empty($printerId) || false === $printer->loadFromCode($printerId)) {
            return [
                'printed' => false,
                'msg' => 'No se ha podido determinar la impresora para el comprobante.'
            ];
        }

        $printed = \FacturaScripts\Plugins\DixTPV\Lib\Ticket\Builder\VoucherTicket::print(
            $model,
            $printer,
            $this->user
        );
        $escposBase64 = \FacturaScripts\Plugins\DixTPV\Lib\Ticket\Builder\VoucherTicket::getLastTicketBody();

        return [
            'printed' => (bool) $printed,
            'printerId' => $printerId,
            'lines' => count($model->lines),
            'escpos' => $escposBase64,
            'escposEncoding' => 'base64'
        ];
    }

    private function resolvePrinterIdForVoucher(): int
    {
        $requestPrinter = (int) $this->request->get('printerId', 0);
        if ($requestPrinter > 0) {
            return $requestPrinter;
        }

        if (isset($this->selectedTerminal) && $this->selectedTerminal instanceof DixTerminal) {
            $terminalPrinter = (int) ($this->selectedTerminal->idprinter ?? 0);
            if ($terminalPrinter > 0) {
                return $terminalPrinter;
            }
        }

        $terminalId = (int) $this->request->get('terminalId', 0);
        if ($terminalId > 0) {
            $terminal = new DixTerminal();
            if ($terminal->loadFromCode($terminalId) && !empty($terminal->idprinter)) {
                return (int) $terminal->idprinter;
            }
        }

        $cajaModel = new DixTPVCaja();
        $cajaAbierta = $cajaModel->all([new DataBaseWhere('fechahoracierre', null)], [], 0, 1);
        if (!empty($cajaAbierta)) {
            $terminal = new DixTerminal();
            if ($terminal->loadFromCode($cajaAbierta[0]->pertenenciaterminal) && !empty($terminal->idprinter)) {
                return (int) $terminal->idprinter;
            }
        }

        return (int) ($this->user->idprinter ?? 0);
    }











    public function openCashDrawer(array $documentData = null)
    {
        $templateClass = 'FacturaScripts\\Dinamic\\Lib\\Ticket\\Builder\\CashDrawer';
        $documentData = $documentData ?? $this->request->get('carrito', []);
        if (!is_array($documentData)) {
            $documentData = (array) $documentData;
        }
        $printerId = $this->resolvePrinterIdForVoucher();

        $printServiceAvailable = class_exists('FacturaScripts\\Plugins\\PrintTicket\\Lib\\PrintingService')
            && class_exists('FacturaScripts\\Plugins\\PrintTicket\\Lib\\Ticket\\Builder\\AbstractTicketBuilder');

        if ($printServiceAvailable && class_exists($templateClass)) {
            $formatClass = 'FacturaScripts\\Dinamic\\Model\\FormatoTicket';
            $format = null;

            if (class_exists($formatClass)) {
                $format = new $formatClass();
                if (false === $format->loadFromCode('', [], ['id' => 'ASC'])) {
                    $format = null;
                }
            } else {
                Tools::log()->warning('cashdrawer:format-missing', ['class' => $formatClass]);
            }

            if (null !== $format) {
                $ticketBuilder = new $templateClass($documentData, $format);
                return [
                    'print_job_id' => PrintingService::newPrintJob($ticketBuilder),
                    'printerId' => $printerId,
                    'method' => 'print-service'
                ];
            }
        }

        $escposCommand = "\x1B\x70\x00\x40\x50\x1B\x70\x01\x40\x50";
        return [
            'printerId' => $printerId,
            'escpos' => base64_encode($escposCommand),
            'escposEncoding' => 'base64',
            'method' => 'qz-tray'
        ];
    }

    private function abrirCaja() {
        $this->startSession();

        $dineroManejado = floatval($this->request->get('dineroManejado'));
        $tipoapertura = $this->request->get('tipoapertura');
        $caja = $this->loadActiveCaja();
        $dinero = $caja->dinerofin;

        $caja->dinerofin += ($tipoapertura === "1") ? $dineroManejado : -$dineroManejado;

        if ($caja->dinerofin < 0) {
            $this->response->setContent(json_encode([
                'success' => false,
                'resultado' => 'No puedes retirar más dinero del que hay en la caja.'
                            ], JSON_UNESCAPED_UNICODE));
        } else {
            $caja->save();
            $this->response->setContent(json_encode([
                'success' => true,
                'resultado' => 'Caja actualizada correctamente.'
                            ], JSON_UNESCAPED_UNICODE));
        }
    }

    private function loadActiveCaja(): DixTPVCaja {
        $caja = new DixTPVCaja();
        $cajaAbierta = $caja->all([new DataBaseWhere('fechahoracierre', null)]);
        if (!empty($cajaAbierta)) {
            $this->idCaja = $cajaAbierta[0]->idcaja;
            $this->ensureCajaSession($this->idCaja);
        }

        $caja1 = new DixTPVCaja();
        $caja1->loadFromCode($this->idCaja);
        return $caja1;
    }

    public function seleccionTerminal() {
        $dineroInicial = $this->request->get('dinero_inicial');
        $selectedTerminalId = $this->request->get('selectedTerminalId');

        // Si no se ha seleccionado una terminal, seleccionamos la de menor ID
        if (empty($selectedTerminalId)) {
            $terminal = new DixTerminal();
            $terminales = $terminal->all([], ['idtpv' => 'ASC'], 0, 1); // Obtener la primera terminal por ID

            if (!empty($terminales)) {
                $selectedTerminalId = $terminales[0]->idtpv;
            } else {
                // Si no hay terminales, lanzamos error
                $this->response->setContent(json_encode([
                    'status' => 'error',
                    'message' => 'No hay terminales disponibles.'
                ]));
                return;
            }
        }
        $fechaHoraIni = date('Y-m-d H:i:s');

        $nuevaCaja = new DixTPVCaja();
        $nuevaCaja->dineroini = $dineroInicial;
        $nuevaCaja->fechahoraapertura = $fechaHoraIni;
        $nuevaCaja->pertenenciaterminal = $selectedTerminalId;
        if ($nuevaCaja->save()) {
            $nuevaCaja->nombre = $nuevaCaja->id . $nuevaCaja->pertenenciaterminal;
            $nuevaCaja->save();
        }

        $_SESSION['cajaActivaFecha'] = $fechaHoraIni;
        $_SESSION['idcaja'] = $nuevaCaja->idcaja;
        $this->idCaja = $nuevaCaja->idcaja;
        $this->cajaActivaFecha = $fechaHoraIni;
    }

    public function modificarProducto() {
        $referencia = $this->request->get('referencia');
        $precio = $this->request->get('pvp');
        $inputCant = $this->request->get('inputCant');
        $descripcion = $this->request->get('descripcion');

        $variante = new Variante();
        $variante->loadFromCode('', [new DataBaseWhere('referencia', $referencia)]);
        $variante->precio = floatval($precio);
        $variante->save();

        $this->setTemplate(false);
        $htmlCode = $this->renderFamilyProducts();

        $this->response->setContent(json_encode([
            'htmlFamily' => $htmlCode,
            'subamilias' => 'ok',
            'success' => 'ok',
            'resultado' => 'OK'], 1));
    }

    public function recuperarAparcado() {
        $idcomanda = (int) $this->request->get('idcomanda');
        $productos = $this->buildProductosDeComanda($idcomanda);

        $this->setTemplate(false);
        $this->response->setContent(json_encode([
            'productos' => $productos,
            'success' => 'ok',
            'resultado' => 'OK'
        ], JSON_UNESCAPED_UNICODE));
    }

    private function buildProductosDeComanda(int $idcomanda): array
    {
        if ($idcomanda <= 0) {
            return [];
        }

        $whereDetails = [new DataBaseWhere('idcomanda', $idcomanda)];
        $detallesModel = new DixDetailComanda();
        $detalles = $detallesModel->all($whereDetails, [], 0, 0);

        $productos = [];
        foreach ($detalles as $detalle) {
            if (empty($detalle->idproducto)) {
                $taxPct = $this->resolveDetailTaxPercent($detalle);
                $productos[] = [
                    'descripcion' => $detalle->descripcion,
                    'pvp' => $this->normalizeStoredUnitPrice((float) $detalle->precio_unitario, $taxPct),
                    'cantidad' => $detalle->cantidad,
                    'codimpuesto' => $taxPct,
                    'referencia' => 'Personalizado-' . uniqid()
                ];
                continue;
            }

            $whereVariant = [new DataBaseWhere('idproducto', $detalle->idproducto)];
            $variantModel = new Variante();
            $variant = $variantModel->all($whereVariant, [], 0, 0);
            $nombreprod = $detalle->descripcion;
            $referencia = 'Desconocido';

            if (!empty($variant)) {
                $varian = $variant[0];
                $whereProduct = [new DataBaseWhere('idproducto', $varian->idproducto)];
                $productoModel = new Producto();
                $producto = $productoModel->all($whereProduct, [], 0, 0);
                if (!empty($producto)) {
                    $prod = $producto[0];
                    $nombreprod = !empty($prod->descripcion) ? $prod->descripcion : $detalle->descripcion;
                    $referencia = $varian->referencia;
                }
            }

            $taxPct = $this->resolveDetailTaxPercent($detalle);
            $productos[] = [
                'descripcion' => $nombreprod,
                'pvp' => $this->normalizeStoredUnitPrice((float) $detalle->precio_unitario, $taxPct),
                'cantidad' => $detalle->cantidad,
                'codimpuesto' => $taxPct,
                'referencia' => $referencia
            ];
        }

        return $productos;
    }

    private function sortAparcados(): void
    {
        if (empty($this->aparcados) || false === is_array($this->aparcados)) {
            return;
        }

        usort($this->aparcados, function ($a, $b) {
            $timeA = strtotime($a->fecha ?? '') ?: 0;
            $timeB = strtotime($b->fecha ?? '') ?: 0;
            if ($timeA === $timeB) {
                return ($b->idcomanda ?? 0) <=> ($a->idcomanda ?? 0);
            }
            return $timeB <=> $timeA;
        });
    }

    private function refreshAparcadoTotals(): void
    {
        if (empty($this->aparcados) || false === is_array($this->aparcados)) {
            return;
        }

        foreach ($this->aparcados as $aparcado) {
            $id = (int) ($aparcado->idcomanda ?? 0);
            if ($id <= 0) {
                continue;
            }

            $productos = $this->buildProductosDeComanda($id);
            if (empty($productos)) {
                continue;
            }

            $suma = 0.0;
            foreach ($productos as $producto) {
                $pvpNeto = (float) ($producto['pvp'] ?? 0);
                $cantidad = (float) ($producto['cantidad'] ?? 0);
                $impuesto = (float) ($producto['codimpuesto'] ?? 0);
                $pvpBruto = $pvpNeto * (1 + $impuesto / 100);
                $suma += $pvpBruto * $cantidad;
            }

            $suma = round($suma, 2);
            if ($suma <= 0) {
                continue;
            }

            if (abs($suma - (float) ($aparcado->total ?? 0)) > 0.009) {
                $aparcado->total = $suma;
                $aparcado->save();
            }
        }
    }

    private function extractTaxPercent($rawValue): float
    {
        if (is_numeric($rawValue)) {
            return (float) $rawValue;
        }

        $text = (string) $rawValue;
        if (preg_match('/(-?\d+(?:[.,]\d+)?)/', $text, $matches)) {
            return (float) str_replace(',', '.', $matches[1]);
        }

        return 0.0;
    }

    private function resolveTaxPercent($rawValue): float
    {
        if (is_numeric($rawValue)) {
            return (float) $rawValue;
        }

        $code = trim((string) $rawValue);
        if ($code === '') {
            return 0.0;
        }

        if (isset($this->taxPercentCache[$code])) {
            return $this->taxPercentCache[$code];
        }

        $taxModel = new Impuesto();
        if ($taxModel->loadFromCode($code)) {
            $this->taxPercentCache[$code] = (float) ($taxModel->iva ?? 0.0);
            return $this->taxPercentCache[$code];
        }

        $percent = $this->extractTaxPercent($code);
        $this->taxPercentCache[$code] = $percent;
        return $percent;
    }

    private function getProductTaxPercent(int $productId): float
    {
        if ($productId <= 0) {
            return 0.0;
        }

        if (array_key_exists($productId, $this->productTaxCache)) {
            return $this->productTaxCache[$productId];
        }

        $producto = new Producto();
        if (false === $producto->loadFromCode($productId)) {
            $this->productTaxCache[$productId] = 0.0;
            return 0.0;
        }

        $percent = $this->resolveTaxPercent($producto->codimpuesto ?? null);
        $this->productTaxCache[$productId] = $percent;
        return $percent;
    }

    private function resolveDetailTaxPercent(DixDetailComanda $detalle): float
    {
        $percent = $this->resolveTaxPercent($detalle->codimpuesto ?? null);
        if ($percent > 0.0) {
            return $percent;
        }

        $productId = (int) ($detalle->idproducto ?? 0);
        return $this->getProductTaxPercent($productId);
    }

    private function normalizeStoredUnitPrice(float $unitPriceWithTax, float $taxPercent): float
    {
        if ($taxPercent <= -100.0) {
            return $unitPriceWithTax;
        }

        $divider = 1.0 + ($taxPercent / 100.0);
        if (abs($divider) < 0.00001) {
            return $unitPriceWithTax;
        }

        return round($unitPriceWithTax / $divider, 6);
    }

    private function prepareAparcadosForDisplay(): void
    {
        if (empty($this->aparcados) || false === is_array($this->aparcados)) {
            $this->aparcados = [];
            return;
        }

        $this->refreshAparcadoTotals();

        if (false === $this->modoHosteleria) {
            $this->aparcados = array_values(array_filter($this->aparcados, function ($aparcado) {
                $mesa = trim((string) ($aparcado->nombremesa ?? ''));
                return '' !== $mesa && 0 === strcasecmp($mesa, 'Listado');
            }));
        }

        $this->sortAparcados();
    }

    private function markComandaAsCobrada(DixTPVComanda $comanda): void
    {
        $comanda->nombremesa = 'Cobrado';
        $comanda->nombresalon = 'Cobrados';
        if (false === $comanda->save()) {
            Tools::log()->warning('No se pudo marcar la comanda como cobrada.', ['idcomanda' => $comanda->idcomanda]);
        }
    }

    private function borrarAparcado(): array
    {
        $idcomanda = (int) $this->request->get('idcomanda');
        if ($idcomanda <= 0) {
            return ['success' => false, 'message' => 'Comanda no válida.'];
        }

        $deleted = $this->deleteComandaById($idcomanda);

        return [
            'success' => $deleted,
            'idcomanda' => $idcomanda,
            'message' => $deleted ? 'Aparcado eliminado.' : 'No se pudo eliminar el aparcado.'
        ];
    }

    private function printAparcadoTicket(): array
    {
        $idcomanda = (int) $this->request->get('idcomanda', 0);
        if ($idcomanda <= 0) {
            return [
                'printed' => false,
                'msg' => 'Comanda no válida.'
            ];
        }

        $productos = $this->buildProductosDeComanda($idcomanda);
        if (empty($productos)) {
            return [
                'printed' => false,
                'msg' => 'El aparcado no tiene líneas.'
            ];
        }

        return $this->printLinesAsVoucher($productos);
    }

    private function aparcarCuenta() {
        $cesta = $this->request->request->getArray('cesta');
        if (empty($cesta)) {
            $cesta = $this->request->query->getArray('cesta');
        }
        if (empty($cesta)) {
            Tools::log()->warning('No se recibió contenido de cesta para aparcar.');
            echo json_encode(['success' => false, 'message' => 'No se recibió ninguna cesta.'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $idComanda = (int) $this->request->get('idcomanda', 0);
        $codCliente = (string) ($this->request->request->get('codcliente', $this->request->get('codcliente', '')));
        $totalConIva = (float) ($this->request->request->get('totalconiva', $this->request->get('totalconiva', 0)));
        $forceGeneralParking = filter_var(
            $this->request->get('aparcarlistado', $this->request->request->get('aparcarlistado', false)),
            FILTER_VALIDATE_BOOLEAN
        );
        $aparcadoOrigen = (int) ($this->request->get('aparcado_origen', $this->request->request->get('aparcado_origen', 0)));
        $appendMode = filter_var(
            $this->request->get('append_mode', $this->request->request->get('append_mode', false)),
            FILTER_VALIDATE_BOOLEAN
        );
        $clienteParaPrecios = $codCliente !== '' ? $codCliente : ($this->defaultClient->codcliente ?? '');
        $cesta = $this->normalizeCartPrices($cesta, $clienteParaPrecios);
        if ($forceGeneralParking) {
            $result = $this->aparcarCuentaSinMesa($cesta, $idComanda, $codCliente, $totalConIva);
        } else {
            $result = $this->modoHosteleria
                ? $this->aparcarCuentaConMesa($cesta, $codCliente, $totalConIva, $aparcadoOrigen, $idComanda, $appendMode)
                : $this->aparcarCuentaSinMesa($cesta, $idComanda, $codCliente, $totalConIva);
        }

        echo json_encode($result, JSON_UNESCAPED_UNICODE);
        exit;
    }

    private function aparcarCuentaConMesa(
        array $cesta,
        string $codCliente = '',
        float $totalConIva = 0.0,
        int $aparcadoOrigen = 0,
        int $idComandaActual = 0,
        bool $appendMode = false
    ): array
    {
        $salon = $this->request->get('salon');
        $mesaNombre = $this->request->get('mesa');

        if (empty($mesaNombre)) {
            return ['success' => false, 'message' => 'Debe seleccionar una mesa.'];
        }

        $mesaModel = new DixMesa();
        $mesas = $mesaModel->all([new DataBaseWhere('nombre', $mesaNombre)], [], 0, 1);
        if (empty($mesas)) {
            return ['success' => false, 'message' => 'No se encontró la mesa seleccionada.'];
        }

        $mesa = $mesas[0];
        $comanda = null;
        $idComandaActual = max(0, $idComandaActual);
        if ($idComandaActual > 0) {
            $tmp = new DixTPVComanda();
            if ($tmp->loadFromCode($idComandaActual) && (int) $tmp->idmesa === (int) $mesa->idmesa) {
                $comanda = $tmp;
            }
        }
        if (null === $comanda) {
            $comanda = $this->loadOrCreateComandaForMesa((int) $mesa->idmesa);
        }
        $isEditingSameComanda = $idComandaActual > 0 && $comanda->idcomanda === $idComandaActual;

        $comanda->idsalon = $mesa->idsalon;
        $comanda->idmesa = $mesa->idmesa;
        $comanda->fecha = date(DixTPVComanda::DATETIME_STYLE);
        if (!empty($codCliente)) {
            $comanda->idcliente = $codCliente;
        }
        if (empty($comanda->idcliente)) {
            $comanda->idcliente = $this->defaultClient->codcliente ?? '1';
        }

        if (false === $comanda->save()) {
            Tools::log()->error('aparcado-save-error', ['mesa' => $mesaNombre]);
            return ['success' => false, 'message' => 'No se pudo guardar la comanda.'];
        }

        [$hasExistingDetails, $existingTotal] = $this->getComandaDetailsSummary($comanda);
        $appendExisting = false;

        if ($isEditingSameComanda) {
            if ($appendMode && $hasExistingDetails) {
                $appendExisting = true;
            } else {
                $this->deleteComandaDetails($comanda->idcomanda);
                $existingTotal = 0.0;
            }
        } else {
            if ($hasExistingDetails) {
                $appendExisting = true;
            } else {
                $this->deleteComandaDetails($comanda->idcomanda);
            }
        }

        $this->saveComandaLines(
            $comanda,
            $cesta,
            $totalConIva,
            $appendExisting,
            $appendExisting ? $existingTotal : 0.0
        );
        $this->guardarSeleccionMesa($salon, $mesaNombre);

        if ($aparcadoOrigen > 0 && $aparcadoOrigen !== (int) ($comanda->idcomanda ?? 0)) {
            $this->deleteComandaById($aparcadoOrigen);
        }

        return [
            'success' => true,
            'message' => 'Cesta aparcada correctamente.',
            'idcomanda' => $comanda->idcomanda
        ];
    }

    private function aparcarCuentaSinMesa(array $cesta, int $idComanda, string $codCliente = '', float $totalConIva = 0.0): array
    {
        $comanda = new DixTPVComanda();
        $existe = $idComanda > 0 && $comanda->loadFromCode($idComanda);
        if (false === $existe) {
            $comanda->clear();
        }

        $comanda->idsalon = null;
        $comanda->idmesa = null;
        $comanda->nombresalon = 'Aparcados';
        $comanda->nombremesa = 'Listado';
        $comanda->fecha = date(DixTPVComanda::DATETIME_STYLE);
        if (!empty($codCliente)) {
            $comanda->idcliente = $codCliente;
        }
        if (empty($comanda->idcliente)) {
            $comanda->idcliente = $this->defaultClient->codcliente ?? '1';
        }

        if (false === $comanda->save()) {
            Tools::log()->error('aparcado-save-error', ['mesa' => null]);
            return ['success' => false, 'message' => 'No se pudo guardar la comanda.'];
        }

        $this->deleteComandaDetails($comanda->idcomanda);
        $this->saveComandaLines($comanda, $cesta, $totalConIva);

        return [
            'success' => true,
            'message' => 'Cesta aparcada correctamente.',
            'idcomanda' => $comanda->idcomanda
        ];
    }

    private function guardarSeleccionMesa($salon, $mesa): void
    {
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }

        $_SESSION['salonSeleccionado'] = $salon;
        $_SESSION['mesaSeleccionada'] = $mesa;
    }

    private function loadOrCreateComandaForMesa(int $idMesa): DixTPVComanda
    {
        $comandaModel = new DixTPVComanda();
        $comandas = $comandaModel->all([new DataBaseWhere('idmesa', $idMesa)], [], 0, 1);
        if (!empty($comandas)) {
            return $comandas[0];
        }

        return new DixTPVComanda();
    }

    private function deleteComandaDetails(int $idcomanda): void
    {
        if ($idcomanda <= 0) {
            return;
        }

        $detalleModel = new DixDetailComanda();
        $detalles = $detalleModel->all([new DataBaseWhere('idcomanda', $idcomanda)], [], 0, 0);
        foreach ($detalles as $detalle) {
            $detalle->delete();
        }
    }

    /**
     * @return array{0: bool, 1: float}
     */
    private function getComandaDetailsSummary(DixTPVComanda $comanda): array
    {
        if (false === ($comanda instanceof DixTPVComanda) || $comanda->idcomanda <= 0) {
            return [false, 0.0];
        }

        $detalleModel = new DixDetailComanda();
        $detalles = $detalleModel->all([new DataBaseWhere('idcomanda', $comanda->idcomanda)], [], 0, 0);
        if (empty($detalles)) {
            return [false, 0.0];
        }

        $total = 0.0;
        foreach ($detalles as $detalle) {
            $total += (float) ($detalle->precio_total ?? 0.0);
        }

        return [true, $total];
    }

    private function deleteComandaById(int $idcomanda): bool
    {
        if ($idcomanda <= 0) {
            return false;
        }

        $comanda = new DixTPVComanda();
        if (false === $comanda->loadFromCode($idcomanda)) {
            return false;
        }

        $this->deleteComandaDetails($idcomanda);
        return (bool) $comanda->delete();
    }

    private function saveComandaLines(DixTPVComanda $comanda, array $cesta, float $totalConIvaOverride = 0.0, bool $append = false, float $existingTotal = 0.0): void
    {
        $total = 0.0;
        $mergeIndex = $append ? $this->getExistingDetailIndex($comanda->idcomanda) : [];

        foreach ($cesta as $producto) {
            $lineDescription = $producto['descripcion'] ?? '';
            $lineQuantity = (float) ($producto['cantidad'] ?? 1);
            $rawTax = $producto['codimpuesto'] ?? null;
            $netPrice = (float) ($producto['pvp'] ?? 0);
            $lineTaxPct = $this->resolveTaxPercent($rawTax);
            $unitPriceWithTax = $netPrice * (1 + $lineTaxPct / 100);
            $lineTotalWithTax = $unitPriceWithTax * $lineQuantity;
            $total += $lineTotalWithTax;

            $mergeKey = $this->buildDetailMergeKey($lineDescription, $unitPriceWithTax);
            if (isset($mergeIndex[$mergeKey])) {
                $detalleExistente = $mergeIndex[$mergeKey];
                $detalleExistente->cantidad = (float) ($detalleExistente->cantidad ?? 0) + $lineQuantity;
                $detalleExistente->precio_total = $unitPriceWithTax * $detalleExistente->cantidad;
                if (null !== $rawTax && $rawTax !== '') {
                    $detalleExistente->codimpuesto = $rawTax;
                }
                if (false === $detalleExistente->save()) {
                    Tools::log()->warning('No se pudo actualizar la línea combinada de comanda.', [
                        'idcomanda' => $comanda->idcomanda,
                        'descripcion' => $lineDescription,
                        'precio_unitario' => $unitPriceWithTax
                    ]);
                }
                continue;
            }

            $detalle = new DixDetailComanda();
            $detalle->idcomanda = $comanda->idcomanda;
            $detalle->descripcion = $lineDescription;
            $detalle->cantidad = $lineQuantity;
            $detalle->codimpuesto = $rawTax;
            $detalle->precio_unitario = $unitPriceWithTax;
            $detalle->precio_total = $lineTotalWithTax;

            $referencia = $producto['referencia'] ?? '';
            $variant = $this->findVariantByReference($referencia);
            if ($variant) {
                $detalle->idproducto = $variant->idproducto;
            } else {
                $detalle->idproducto = $this->extractProductIdFromReference($referencia);
            }

            if (false === $detalle->save()) {
                Tools::log()->warning('No se pudo guardar la línea de comanda aparcada.', $producto);
            } else {
                $mergeIndex[$mergeKey] = $detalle;
            }
        }

        $nuevoTotal = $totalConIvaOverride > 0 ? $totalConIvaOverride : $total;
        if ($append) {
            $nuevoTotal += max(0.0, $existingTotal);
        }

        $comanda->total = $nuevoTotal;
        if (false === $comanda->save()) {
            Tools::log()->warning('No se pudo actualizar el total del aparcado.', [
                'idcomanda' => $comanda->idcomanda,
                'total' => $nuevoTotal
            ]);
        }
    }

    private function findVariantByReference(?string $referencia): ?Variante
    {
        $referencia = trim((string) $referencia);
        if ($referencia === '') {
            return null;
        }

        if (array_key_exists($referencia, $this->variantCache)) {
            return $this->variantCache[$referencia];
        }

        $variantModel = new Variante();
        $variants = $variantModel->all([new DataBaseWhere('referencia', $referencia)], [], 0, 1);
        $variant = empty($variants) ? null : $variants[0];
        $this->variantCache[$referencia] = $variant;
        return $variant;
    }

    private function extractProductIdFromReference(?string $referencia): ?int
    {
        if (empty($referencia)) {
            return null;
        }

        $numeric = preg_replace('/\D/', '', (string) $referencia);
        $productId = (int) $numeric;
        if ($productId <= 0) {
            return null;
        }

        $producto = new Producto();
        if ($producto->loadFromCode($productId)) {
            return $productId;
        }

        return null;
    }

    private function buildDetailMergeKey(string $descripcion, float $precioUnitario): string
    {
        $cleanDesc = trim(mb_strtolower($descripcion ?? '', 'UTF-8'));
        return $cleanDesc . '|' . number_format($precioUnitario, 4, '.', '');
    }

    /**
     * @return array<string, DixDetailComanda>
     */
    private function getExistingDetailIndex(int $idcomanda): array
    {
        if ($idcomanda <= 0) {
            return [];
        }

        $detalleModel = new DixDetailComanda();
        $detalles = $detalleModel->all([new DataBaseWhere('idcomanda', $idcomanda)], [], 0, 0);
        $index = [];
        foreach ($detalles as $detalle) {
            $key = $this->buildDetailMergeKey($detalle->descripcion ?? '', (float) ($detalle->precio_unitario ?? 0));
            $index[$key] = $detalle;
        }

        return $index;
    }

    private function shouldApplyClientTariff(?Cliente $cliente): bool
    {
        if (false === ($cliente instanceof Cliente)) {
            return false;
        }

        if ($this->advancedTariffsEnabled) {
            return true;
        }

        return trim((string)($cliente->codtarifa ?? '')) !== '';
    }

    private function anyClientHasTariff(): bool
    {
        if (empty($this->clientes)) {
            return false;
        }

        foreach ($this->clientes as $cliente) {
            if (false === ($cliente instanceof Cliente)) {
                continue;
            }
            if (trim((string)($cliente->codtarifa ?? '')) !== '') {
                return true;
            }
        }

        return false;
    }

    private function getClientByCode(?string $code): ?Cliente
    {
        $code = trim((string)$code);
        if ($code === '' && $this->defaultClient instanceof Cliente) {
            return $this->defaultClient;
        }

        if ($code !== '' && isset($this->clientCache[$code])) {
            return $this->clientCache[$code];
        }

        if ($code === '') {
            return $this->defaultClient instanceof Cliente ? $this->defaultClient : null;
        }

        $cliente = new Cliente();
        if ($cliente->loadFromCode($code)) {
            $this->clientCache[$code] = $cliente;
            return $cliente;
        }

        return $this->defaultClient instanceof Cliente ? $this->defaultClient : null;
    }

    private function calculateVariantTariffPrice(Variante $variant, ?Cliente $cliente = null): float
    {
        $basePrice = (float)($variant->precio ?? 0.0);
        $cliente = $cliente ?? ($this->defaultClient instanceof Cliente ? $this->defaultClient : null);
        if (false === $this->shouldApplyClientTariff($cliente)) {
            return $basePrice;
        }

        $rateCode = trim((string)($cliente->codtarifa ?? ''));
        if ($rateCode === '') {
            return $basePrice;
        }

        $clientKey = (string)($cliente->codcliente ?? '');
        $reference = (string)($variant->referencia ?? '');
        if ($clientKey !== '' && $reference !== '' && isset($this->tariffPriceCache[$clientKey][$reference])) {
            return $this->tariffPriceCache[$clientKey][$reference];
        }

        $tarifa = new Tarifa();
        if (false === $tarifa->loadFromCode($rateCode)) {
            return $basePrice;
        }

        $product = $variant->getProducto();
        $price = $product ? $tarifa->applyTo($variant, $product) : $tarifa->apply((float)$variant->coste, $basePrice);

        if ($clientKey !== '' && $reference !== '') {
            $this->tariffPriceCache[$clientKey][$reference] = $price;
        }

        return $price > 0 ? $price : $basePrice;
    }

    private function normalizeCartPrices(array $cesta, ?string $codCliente): array
    {
        if (empty($cesta)) {
            return $cesta;
        }

        $cliente = $this->getClientByCode($codCliente);
        if (false === $this->shouldApplyClientTariff($cliente)) {
            return $cesta;
        }

        foreach ($cesta as $index => $producto) {
            if ($this->isManualPriceLocked($producto)) {
                continue;
            }
            $referencia = (string)($producto['referencia'] ?? '');
            if ($referencia === '') {
                continue;
            }
            $variant = $this->findVariantByReference($referencia);
            if (false === ($variant instanceof Variante)) {
                continue;
            }
            $cesta[$index]['pvp'] = $this->calculateVariantTariffPrice($variant, $cliente);
        }

        return $cesta;
    }

    /**
     * Indica si el precio de la línea debe conservarse porque se fijó manualmente en el TPV.
     */
    private function isManualPriceLocked($producto): bool
    {
        if (false === is_array($producto)) {
            return false;
        }
        $flag = $producto['pvpManual'] ?? null;
        if (null === $flag) {
            return false;
        }
        if (is_bool($flag)) {
            return $flag;
        }
        if (is_numeric($flag)) {
            return (bool) $flag;
        }
        if (is_string($flag)) {
            $normalized = strtolower($flag);
            return in_array($normalized, ['1', 'true', 'yes', 'on', 'si'], true);
        }

        return false;
    }

    private function quoteTariffPrice(): array
    {
        $codCliente = (string)$this->request->get('codcliente', '');
        $references = $this->request->request->getArray('referencias');
        if (empty($references)) {
            $single = trim((string)$this->request->get('referencia', ''));
            if ($single !== '') {
                $references = [$single];
            }
        }

        if (empty($references)) {
            return [
                'success' => false,
                'message' => 'No se indicó referencia.'
            ];
        }

        $cliente = $this->getClientByCode($codCliente);
        $prices = [];
        foreach ($references as $reference) {
            $reference = trim((string)$reference);
            if ($reference === '') {
                continue;
            }
            $variant = $this->findVariantByReference($reference);
            if (false === ($variant instanceof Variante)) {
                continue;
            }
            $prices[$reference] = $this->calculateVariantTariffPrice($variant, $cliente);
        }

        $response = [
            'success' => !empty($prices),
            'prices' => $prices
        ];

        if (count($prices) === 1) {
            $response['pvp'] = reset($prices);
        }

        if ($cliente instanceof Cliente) {
            $response['codcliente'] = $cliente->codcliente;
        }

        return $response;
    }

    private function getFamilyProducts(): array
    {
        $codfamilia = $this->request->get('codfamilia');
        return $this->fetchFamilyProducts($codfamilia);
    }

    private function renderFamilyProducts() {
        $products = $this->getFamilyProducts();
        $dataForTwig = [
            'products' => $products,
            'showDescription' => $this->mostrarDescripcionCartas
        ];
        $htmlCode = Html::render('DixTPV/Hosteleria/Ventas/Vistas/familyProducts.html.twig', $dataForTwig);

        return $htmlCode;
    }

    private function fetchFamilyProducts($codfamilia = null, callable $variantFilter = null, int $limit = 0): array
    {
        $productModel = new Producto();
        $where = $this->buildFamilyWhere($codfamilia);
        $where[] = new DataBaseWhere('sevende', true);
        $productos = $productModel->all($where, [], 0, 0);
        $dataProducts = [];
        $taxResolver = new Impuesto();

        foreach ($productos as $producto) {
            $variants = $producto->getVariants();
            foreach ($variants as $variant) {
                if ($variantFilter && false === $variantFilter($producto, $variant)) {
                    continue;
                }
                $dataProducts[] = $this->buildVariantData($producto, $variant, $taxResolver);
                if ($limit > 0 && count($dataProducts) >= $limit) {
                    break 2;
                }
            }
        }

        return $dataProducts;
    }

    private function buildVariantData(Producto $producto, Variante $variant, Impuesto $taxResolver = null): array
    {
        $taxResolver = $taxResolver ?? new Impuesto();
        $codImpuesto = $taxResolver->loadFromCode($producto->codimpuesto) ? $taxResolver->iva : 0;

        return [
            'product' => $producto,
            'variant' => $variant,
            'pvp' => $variant->priceWithTax(),
            'images' => $variant->getImages(),
            'description' => $variant->description(),
            'codimpuesto' => $codImpuesto,
            'ventasinstock' => (int)($producto->ventasinstock ?? 0),
            'imageUrl' => $this->getVariantImageUrl($variant),
        ];
    }

    private function buildFamilyWhere($codfamilia = null): array
    {
        if (empty($codfamilia)) {
            return [];
        }

        if (in_array($codfamilia, ['sin_familia', 'varios'], true)) {
            return [
                new DataBaseWhere('codfamilia', null, 'IS'),
                new DataBaseWhere('codfamilia', '', '=', 'OR')
            ];
        }

        return [new DataBaseWhere('codfamilia', $codfamilia)];
    }

    private function searchProductsAjax(): array
    {
        $mode = strtolower((string)$this->request->get('mode', 'text'));
        $term = trim((string)$this->request->get('term', ''));

        if ($term === '') {
            return [
                'resultado' => 'OK',
                'htmlFamily' => '',
                'items' => 0
            ];
        }

        if ($mode === 'barcode') {
            return $this->searchProductByBarcode($term);
        }

        $familyCode = (string)$this->request->get('family', '');

        $cleanTerm = Tools::noHtml($term);
        $needle = mb_strtolower($cleanTerm, 'UTF-8');
        $filter = function (Producto $producto, Variante $variant) use ($needle): bool {
            $normalize = static function ($value) {
                $value = Tools::noHtml((string)$value);
                return mb_strtolower($value, 'UTF-8');
            };
            $byDescription = '';
            if (method_exists($variant, 'description')) {
                $byDescription = $normalize($variant->description());
            }
            $byReference = $normalize($variant->referencia ?? '');
            $byProduct = $normalize($producto->descripcion ?? '');

            return ('' !== $byDescription && false !== strpos($byDescription, $needle)) ||
                ('' !== $byReference && false !== strpos($byReference, $needle)) ||
                ('' !== $byProduct && false !== strpos($byProduct, $needle));
        };

        $products = $this->fetchFamilyProducts(
            $familyCode !== '' ? $familyCode : null,
            $filter,
            60
        );

        $htmlCode = Html::render('DixTPV/Hosteleria/Ventas/Vistas/familyProducts.html.twig', [
            'products' => $products,
            'showDescription' => $this->mostrarDescripcionCartas
        ]);

        return [
            'resultado' => 'OK',
            'htmlFamily' => $htmlCode,
            'items' => count($products)
        ];
    }

    private function searchProductByBarcode(string $barcode): array
    {
        $barcode = trim($barcode);
        if ($barcode === '') {
            return [
                'resultado' => 'ERROR',
                'message' => 'Código de barras vacío.'
            ];
        }

        $variantModel = new Variante();
        $cleanBarcode = Tools::noHtml($barcode);
        $barcodeLower = mb_strtolower($cleanBarcode, 'UTF-8');
        $where = [new DataBaseWhere('LOWER(codbarras)', $barcodeLower)];
        $variants = $variantModel->all($where, [], 0, 1);
        if (empty($variants)) {
            return [
                'resultado' => 'ERROR',
                'message' => 'Producto no encontrado.'
            ];
        }

        $variant = $variants[0];
        $product = $variant->getProducto();
        if (false === $product) {
            return [
                'resultado' => 'ERROR',
                'message' => 'Producto no encontrado.'
            ];
        }

        if (false === (bool)($product->sevende ?? true)) {
            return [
                'resultado' => 'ERROR',
                'message' => 'Producto no disponible para la venta.'
            ];
        }

        $variantData = $this->buildVariantData($product, $variant);

        return [
            'resultado' => 'OK',
            'product' => [
                'referencia' => $variant->referencia,
                'descripcion' => $variant->description(),
                'pvp' => $variant->precio,
                'codimpuesto' => $variantData['codimpuesto'],
                'stock' => $variant->stockfis,
                'ventasinstock' => $variantData['ventasinstock']
            ]
        ];
    }

    private function vaciarCesta() {
        $this->startSession();

        if (false === $this->modoHosteleria) {
            $idComanda = (int) $this->request->get('idcomanda', 0);
            if ($idComanda > 0) {
                $this->deleteComandaById($idComanda);
            }
            return;
        }

        $salon = $this->request->get('salon'); // Recuperar el salón
        $mesa = $this->request->get('mesa'); // Recuperar la mesa

        $whereMesa = [new DataBaseWhere('nombre', $mesa)];
        $mesasSeleccionada = new DixMesa();
        $mesasSeleccionada = $mesasSeleccionada->all($whereMesa, [], 0, 0);

        if (!empty($mesasSeleccionada)) {
            $mess = $mesasSeleccionada[0];

            $comanda = new DixTPVComanda();
            //Tenemos que vaciar todos los detalles de comanda por las restricciones en las tablas.
            if ($comanda->loadFromCode('', [new DataBaseWhere('idmesa', $mess->idmesa)])) {
                $modelDetallesComanda = new DixDetailComanda();
                $whereDetalles = [new DataBaseWhere('idcomanda', $comanda->idcomanda)];
                $detallesComandas = $modelDetallesComanda->all($whereDetalles);
                foreach ($detallesComandas as $detalleComanda) {
                    $detalleComanda->delete();
                }
                $comanda->delete(); // Eliminar la comanda de la base de datos
            }
        }
    }

    private function getVariantImageUrl(Variante $variant): string
    {
        $images = $variant->getImages();
        if (empty($images)) {
            return '';
        }

        $image = $images[0];
        if (method_exists($image, 'getThumbnail')) {
            $thumb = $image->getThumbnail(200, 200, true, true);
            if (!empty($thumb)) {
                return $thumb;
            }
        }

        if (method_exists($image, 'url')) {
            return $this->buildPublicUrl($image->url('download-permanent'));
        }

        return '';
    }

    private function buildPublicUrl(string $path): string
    {
        $path = trim($path);
        if ('' === $path) {
            return '';
        }

        if (preg_match('#^https?://#i', $path)) {
            return $path;
        }

        if ($path[0] !== '/') {
            $path = '/' . ltrim($path, '/');
        }

        if (false === strpos($path, '/MyFiles/')) {
            $path = '/MyFiles' . $path;
        }

        return rtrim(FS_ROUTE, '/') . $path;
    }
    

    private function cobrarCuenta(): array {

        $this->startSession();

        $response = [
            'success' => false,
            'message' => '',
            'document' => null
        ];

        $salon = $this->request->get('salon');
        $mesa = $this->request->get('mesa');
        $idComandaOriginal = (int) $this->request->get('idcomanda', 0);
        $cesta = $this->request->request->getArray('cesta');
        if (empty($cesta)) {
            $cesta = $this->request->query->getArray('cesta');
        }
        if (empty($cesta)) {
            $response['message'] = 'No se recibió contenido de la cesta para cobrar.';
            return $response;
        }

        $camarero = $this->request->get('camarero');
        $formapago = $this->request->get('formapago');
        $idcliente = $this->request->get('idcliente');
        $precioacobrar = (float)$this->request->get('precioacobrar', 0);
        $importePagado = (float)$this->request->get('importeentregado', $precioacobrar);
        if ($importePagado < 0) {
            $importePagado = 0.0;
        }
        $serie = $this->request->get('serie');
        $requestedDocType = $this->request->get('doctype');
        $bonoCodigo = $this->request->get('bonoCodigo', '');
        $bonoImporte = (float)$this->request->get('bonoImporte', 0);
        $bonoTotal = (float)$this->request->get('bonoTotal', 0);
        $bonoContext = null;
        if (!empty($bonoCodigo) && $bonoImporte > 0) {
            $bonoContext = [
                'codigo' => $bonoCodigo,
                'importe' => $bonoImporte,
                'cliente' => $idcliente,
            ];
            if ($bonoTotal > 0) {
                $bonoContext['total'] = $bonoTotal;
                $precioacobrar = max(0.0, $bonoTotal - $bonoImporte);
            }
        }

        if (empty($formapago) && isset($this->selectedTerminal->codpago)) {
            $formapago = $this->selectedTerminal->codpago;
        }
        if (empty($serie)) {
            $serie = $this->selectedTerminal->codserie ?? ($this->series[0]->codserie ?? null);
        }
        if (empty($idcliente) && isset($this->defaultClient->codcliente)) {
            $idcliente = $this->defaultClient->codcliente;
        }

        $cesta = $this->normalizeCartPrices($cesta, $idcliente);

        $pago = new FormaPago();
        if (false === $pago->loadFromCode($formapago)) {
            $response['message'] = 'La forma de pago seleccionada no existe.';
            return $response;
        }

        $mess = null;
        if (!empty($mesa)) {
            $mesasSeleccionada = (new DixMesa())->all([new DataBaseWhere('nombre', $mesa)], [], 0, 0);
            $mess = $mesasSeleccionada[0] ?? null;
        }

        $comanda = new DixTPVComanda();
        $comanda->idcliente = $idcliente;
        $createDetails = true;

        if ($mess && $comanda->loadFromCode('', [new DataBaseWhere('idmesa', $mess->idmesa)])) {
            $comanda->idcliente = $idcliente;
            $comanda->idsalon = null;
            $comanda->idmesa = null;
            $comanda->save();
            $createDetails = false; // ya existen los detalles de la comanda
        } elseif (false === $comanda->exists() && false === $comanda->save()) {
            $response['message'] = 'No se pudo crear la comanda para registrar la venta.';
            return $response;
        }

        if (false === $this->modoHosteleria) {
            $this->markComandaAsCobrada($comanda);
        }

        if ($createDetails) {
            foreach ($cesta as $producto) {
                $referencia = $producto['referencia'] ?? '';
                $where = [new DataBaseWhere('referencia', $referencia)];
                $variant = (new Variante())->all($where, [], 0, 0);
                $varian = $variant[0] ?? null;

                $comandaProducto = new DixDetailComanda();
                $comandaProducto->idcomanda = $comanda->idcomanda;
                $comandaProducto->idproducto = $varian ? $varian->idproducto : null;
                $comandaProducto->descripcion = $producto['descripcion'] ?? '';
                $comandaProducto->cantidad = $producto['cantidad'] ?? 1;
                $comandaProducto->precio_unitario = $producto['pvp'] ?? 0;
                $comandaProducto->precio_total = ($producto['pvp'] ?? 0) * ($producto['cantidad'] ?? 1);

                $codImpuestoRecibido = $producto['codimpuesto'] ?? null;
                if (is_numeric($codImpuestoRecibido)) {
                    $comandaProducto->codimpuesto = 'IVA' . intval($codImpuestoRecibido);
                } else {
                    $comandaProducto->codimpuesto = $codImpuestoRecibido;
                }

                if (false === $comandaProducto->save()) {
                    Tools::log()->warning('No se pudo guardar la línea de comanda.', $producto);
                }
            }
        }

        $caja = new DixTPVCaja();
        $cajaAbierta = $caja->all([new DataBaseWhere('fechahoracierre', null)]);
        if (!empty($cajaAbierta)) {
            $this->idCaja = $cajaAbierta[0]->idcaja;
            $this->ensureCajaSession($this->idCaja);
        }

        if (empty($this->idCaja)) {
            $response['message'] = 'No hay una caja abierta para registrar el cobro.';
            return $response;
        }

        $caja1 = new DixTPVCaja();
        if (false === $caja1->loadFromCode($this->idCaja)) {
            $response['message'] = 'No se pudo cargar la caja activa.';
            return $response;
        }

        $terminal = new DixTerminal();
        $terminal->loadFromCode($caja1->pertenenciaterminal);
        $documentType = $terminal->doctype ?? DixTerminal::DEFAULT_DOC_TYPE;
        if (false === $this->modoHosteleria && !empty($requestedDocType)) {
            $documentType = $requestedDocType;
        }
        $documentType = $this->normalizeDocType($documentType);
        $excludeFromTotals = $this->shouldExcludeFromCajaTotals($documentType);

        $documentData = $this->generarDoc($documentType, $comanda->idcomanda, $formapago, $idcliente, $camarero, $serie, $bonoContext);
        if ($documentData) {
            $documentData = $this->appendTicketMetadata($documentData, $terminal);
            if ($bonoContext) {
                $documentData['bono'] = [
                    'codigo' => $bonoContext['codigo'],
                    'importe' => $bonoContext['importe'],
                ];
            }

            $documentTotal = (float)($documentData['total'] ?? $precioacobrar);
            $netPaid = $this->resolveNetPayment($importePagado, $documentTotal);

            if (false === $excludeFromTotals || in_array($documentType, ['AlbaranCliente', 'PedidoCliente'], true)) {
                $this->applyCajaPayment($caja1, $pago, $netPaid);
            }

            if (false === $caja1->save()) {
                Tools::log()->warning('No se pudo guardar el estado de la caja tras cobrar.');
            }

            if ('FacturaCliente' === $documentType) {
                $this->rebuildInvoiceReceipts((int)$documentData['modelCode'], $formapago, $netPaid, $documentTotal);
            } elseif ('AlbaranCliente' === $documentType) {
                $this->registrarResumenAlbaranCaja((int)($caja1->idcaja ?? 0), $documentData, $formapago);
                $this->registrarAnticipoDesdeCobro((int)$documentData['modelCode'], $formapago, $netPaid, $documentTotal);
                UtilsTPV::registrarPagoAlbaran((int)($caja1->idcaja ?? 0), $formapago, $netPaid);
            } elseif ('PedidoCliente' === $documentType) {
                $this->registrarResumenPedidoCaja((int)($caja1->idcaja ?? 0), $documentData, $formapago);
                UtilsTPV::registrarPagoPedido((int)($caja1->idcaja ?? 0), $formapago, $netPaid);
                $this->registrarAnticipoPedidoDesdeCobro((int)$documentData['modelCode'], $formapago, $netPaid, $documentTotal);
            }
        } else {
            Tools::log()->warning('No se pudo generar el documento de venta durante el cobro.');
        }

        $response['success'] = (bool)$documentData;
        $response['document'] = $documentData;
        $response['message'] = $documentData ? 'Cobro realizado correctamente.' : 'No se pudo generar el documento de venta.';
        if ($response['success']) {
            $response['cashDrawer'] = $this->openCashDrawer([]);
            $this->deleteComandaById((int) ($comanda->idcomanda ?? 0));
            if ($idComandaOriginal > 0 && $idComandaOriginal !== (int) ($comanda->idcomanda ?? 0)) {
                $this->deleteComandaById($idComandaOriginal);
            }
        }

        return $response;
    }

    private function cobrarCuentaDividida(): array {
        $salon = $this->request->get('salon');
        $mesa = $this->request->get('mesa');
        $idComandaOriginal = (int) $this->request->get('idcomanda', 0);
        $cesta = $this->request->request->getArray('cesta');
        if (empty($cesta)) {
            $cesta = $this->request->query->getArray('cesta');
        }

        $response = [
            'success' => false,
            'message' => '',
            'document' => null
        ];

        if (empty($cesta)) {
            $response['message'] = 'No se recibió contenido de la cesta para cobrar.';
            return $response;
        }

        $formapago = $this->request->get('formapago');
        $precioacobrar = (float)$this->request->get('precioacobrar', 0);
        $idcliente = $this->request->get('codcliente');
        $serie = $this->request->get('serie');
        $requestedDocType = $this->request->get('doctype');

        if (empty($formapago) && isset($this->selectedTerminal->codpago)) {
            $formapago = $this->selectedTerminal->codpago;
        }
        if (empty($serie)) {
            $serie = $this->selectedTerminal->codserie ?? ($this->series[0]->codserie ?? null);
        }
        if (empty($idcliente) && isset($this->defaultClient->codcliente)) {
            $idcliente = $this->defaultClient->codcliente;
        }

        $cesta = $this->normalizeCartPrices($cesta, $idcliente);

        $whereMesa = [new DataBaseWhere('nombre', $mesa)];
        $mesasSeleccionada = new DixMesa();
        $mesasSeleccionada->loadFromCode('', $whereMesa);
        $mess = $mesasSeleccionada;

        $pago = new FormaPago();
        if (false === $pago->loadFromCode($formapago)) {
            $response['message'] = 'La forma de pago seleccionada no existe.';
            return $response;
        }

        $comanda = new DixTPVComanda();
        $comanda->idcliente = $idcliente;
        $createDetails = true;

        if ($comanda->loadFromCode('', [new DataBaseWhere('idmesa', $mess->idmesa)])) {
            $comanda->idcliente = $idcliente;
            $comanda->idsalon = null;
            $comanda->idmesa = null;
            $comanda->save();
            $createDetails = false;
        } elseif (false === $comanda->exists() && false === $comanda->save()) {
            $response['message'] = 'No se pudo crear la comanda para registrar el cobro dividido.';
            return $response;
        }

        if (false === $this->modoHosteleria) {
            $this->markComandaAsCobrada($comanda);
        }

        if ($createDetails) {
            foreach ($cesta as $producto) {
                $referencia = $producto['referencia'] ?? '';
                $variant = (new Variante())->all([new DataBaseWhere('referencia', $referencia)], [], 0, 0);
                $varian = $variant[0] ?? null;

                $comandaProducto = new DixDetailComanda();
                $comandaProducto->idcomanda = $comanda->idcomanda;
                $comandaProducto->idproducto = $varian ? $varian->idproducto : null;
                $comandaProducto->descripcion = $producto['descripcion'] ?? '';
                $comandaProducto->cantidad = $producto['cantidad'] ?? 1;
                $comandaProducto->precio_unitario = $producto['pvp'] ?? 0;
                $comandaProducto->precio_total = ($producto['pvp'] ?? 0) * ($producto['cantidad'] ?? 1);

                if (!$comandaProducto->save()) {
                    Tools::log()->warning('No se pudo guardar la línea de comanda en cobro dividido.', $producto);
                }
            }
        }

        $caja = new DixTPVCaja();
        $cajaAbierta = $caja->all([new DataBaseWhere('fechahoracierre', null)]);
        if (!empty($cajaAbierta)) {
            $this->idCaja = $cajaAbierta[0]->idcaja;
            $this->ensureCajaSession($this->idCaja);
        }

        if (empty($this->idCaja)) {
            $response['message'] = 'No hay una caja abierta para registrar el cobro dividido.';
            return $response;
        }

        $caja1 = new DixTPVCaja();
        if (!$caja1->loadFromCode($this->idCaja)) {
            $response['message'] = 'No se pudo cargar la caja activa.';
            return $response;
        }

        $terminal = new DixTerminal();
        $terminal->loadFromCode($caja1->pertenenciaterminal);
        $documentType = $terminal->doctype ?? DixTerminal::DEFAULT_DOC_TYPE;
        if (false === $this->modoHosteleria && !empty($requestedDocType)) {
            $documentType = $requestedDocType;
        }
        $documentType = $this->normalizeDocType($documentType);
        $excludeFromTotals = $this->shouldExcludeFromCajaTotals($documentType);

        $documentData = $this->generarDocDiv($documentType, $cesta, $formapago, $idcliente, $serie, $bonoContext);
        if ($documentData) {
            $documentData = $this->appendTicketMetadata($documentData, $terminal);
            if ($bonoContext) {
                $documentData['bono'] = [
                    'codigo' => $bonoContext['codigo'],
                    'importe' => $bonoContext['importe'],
                ];
            }

            $documentTotal = (float)($documentData['total'] ?? $precioacobrar);
            $netPaid = $this->resolveNetPayment($importePagado, $documentTotal);

            if (false === $excludeFromTotals || in_array($documentType, ['AlbaranCliente', 'PedidoCliente'], true)) {
                $this->applyCajaPayment($caja1, $pago, $netPaid);
            }

            if (false === $caja1->save()) {
                Tools::log()->warning('No se pudo guardar el estado de la caja tras el cobro dividido.');
            }

            if ('FacturaCliente' === $documentType) {
                $this->rebuildInvoiceReceipts((int)$documentData['modelCode'], $formapago, $netPaid, $documentTotal);
            } elseif ('AlbaranCliente' === $documentType) {
                $this->registrarResumenAlbaranCaja((int)($caja1->idcaja ?? 0), $documentData, $formapago);
                $this->registrarAnticipoDesdeCobro((int)$documentData['modelCode'], $formapago, $netPaid, $documentTotal);
                UtilsTPV::registrarPagoAlbaran((int)($caja1->idcaja ?? 0), $formapago, $netPaid);
            } elseif ('PedidoCliente' === $documentType) {
                $this->registrarResumenPedidoCaja((int)($caja1->idcaja ?? 0), $documentData, $formapago);
                UtilsTPV::registrarPagoPedido((int)($caja1->idcaja ?? 0), $formapago, $netPaid);
                $this->registrarAnticipoPedidoDesdeCobro((int)$documentData['modelCode'], $formapago, $netPaid, $documentTotal);
            }
        } else {
            Tools::log()->warning('No se pudo generar el documento de venta durante el cobro dividido.');
        }

        $response['success'] = (bool)$documentData;
        $response['document'] = $documentData;
        $response['message'] = $documentData ? 'Cobro dividido realizado correctamente.' : 'No se pudo generar el documento de la venta dividida.';
        if ($response['success']) {
            $response['cashDrawer'] = $this->openCashDrawer([]);
            $this->deleteComandaById((int) ($comanda->idcomanda ?? 0));
            if ($idComandaOriginal > 0 && $idComandaOriginal !== (int) ($comanda->idcomanda ?? 0)) {
                $this->deleteComandaById($idComandaOriginal);
            }
        }

        return $response;
    }

    private function generarDocCierreCaja($caja)
{
    if (!$caja || empty($caja->idcaja)) {
        return false;
    }

    return [
        'modelClassName' => 'DixTPVCaja',
        'modelCode'      => $caja->idcaja,
        'documentCode'   => 'CIERRE-' . $caja->idcaja,
        'total'          => $caja->dinerocierre ?? 0,
        'serie'          => null
    ];
}


    private function normalizeDocType(?string $doctype): string
    {
        $validTypes = [
            'FacturaCliente',
            'AlbaranCliente',
            'PedidoCliente',
            'PresupuestoCliente'
        ];

        if (!empty($doctype)) {
            foreach ($validTypes as $validType) {
                if (0 === strcasecmp($doctype, $validType)) {
                    return $validType;
                }
            }
        }

        return DixTerminal::DEFAULT_DOC_TYPE;
    }

    private function shouldExcludeFromCajaTotals(string $documentType): bool
    {
        if (true === $this->modoHosteleria) {
            return false;
        }

        return in_array($documentType, ['AlbaranCliente', 'PedidoCliente'], true);
    }

    private function registrarResumenAlbaranCaja(int $idCaja, array $documentData, string $codPago): void
    {
        if ($idCaja <= 0 || empty($documentData) || empty($documentData['modelCode'])) {
            return;
        }

        if (('AlbaranCliente' !== ($documentData['modelClassName'] ?? ''))) {
            return;
        }

        UtilsTPV::ensureAlbaranCajaTable();
        $registro = new DixAlbaranCaja();
        $exists = $registro->all([
            new DataBaseWhere('idalbaran', (int)$documentData['modelCode'])
        ], [], 0, 1);
        if (!empty($exists)) {
            $registro = $exists[0];
        }

        $registro->idcaja = $idCaja;
        $registro->idalbaran = (int)$documentData['modelCode'];
        $registro->codpago = $codPago;
        $registro->codigo = $documentData['documentCode'] ?? '';
        $registro->total = (float)($documentData['total'] ?? 0.0);
        $registro->save();
    }

    private function registrarResumenPedidoCaja(int $idCaja, array $documentData, string $codPago): void
    {
        if ($idCaja <= 0 || empty($documentData) || empty($documentData['modelCode'])) {
            return;
        }

        if (('PedidoCliente' !== ($documentData['modelClassName'] ?? ''))) {
            return;
        }

        UtilsTPV::ensurePedidoCajaTable();
        $registro = new DixPedidoCaja();
        $exists = $registro->all([
            new DataBaseWhere('idpedido', (int)$documentData['modelCode'])
        ], [], 0, 1);
        if (!empty($exists)) {
            $registro = $exists[0];
        }

        $registro->idcaja = $idCaja;
        $registro->idpedido = (int)$documentData['modelCode'];
        $registro->codpago = $codPago;
        $registro->codigo = $documentData['documentCode'] ?? '';
        $registro->total = (float)($documentData['total'] ?? 0.0);
        $registro->save();
    }

    private function resolveNetPayment(float $amountPaid, float $documentTotal): float
    {
        if ($amountPaid <= 0) {
            return 0.0;
        }

        if ($documentTotal <= 0) {
            return max(0.0, $amountPaid);
        }

        return max(0.0, min($amountPaid, $documentTotal));
    }

    private function applyCajaPayment(DixTPVCaja $caja, FormaPago $formaPago, float $amount): void
    {
        if ($amount <= 0) {
            return;
        }

        if ($formaPago->efectivo) {
            $caja->dinerofin = (float)($caja->dinerofin ?? 0.0) + $amount;
            return;
        }

        $caja->dinerocredito = (float)($caja->dinerocredito ?? 0.0) + $amount;
    }

    private function rebuildInvoiceReceipts(int $invoiceId, string $codPago, float $amountPaid, float $documentTotal): void
    {
        if ($invoiceId <= 0) {
            return;
        }

        $invoice = new FacturaCliente();
        if (false === $invoice->loadFromCode($invoiceId)) {
            return;
        }

        $netPaid = $this->resolveNetPayment($amountPaid, (float)($invoice->total ?? $documentTotal));
        foreach ($invoice->getReceipts() as $receipt) {
            $receipt->delete();
        }

        $numero = 1;
        if ($netPaid > 0) {
            $this->createInvoiceReceipt($invoice, $codPago, $netPaid, $numero++, true);
        }

        $pending = max(0.0, (float)($invoice->total ?? $documentTotal) - $netPaid);
        if ($pending > 0) {
            $this->createInvoiceReceipt($invoice, $codPago, $pending, $numero++, false);
        }

        $this->ajustarResumenFormasPagoFactura($invoice, $netPaid, $codPago);
    }

    private function ajustarResumenFormasPagoFactura(FacturaCliente $invoice, float $netPaid, string $codPago): void
    {
        if ($netPaid < 0) {
            $netPaid = 0.0;
        }

        if (!empty($codPago) && $codPago !== $invoice->codpago) {
            $invoice->codpago = $codPago;
            $invoice->save();
        }

        UtilsTPV::ajustarResumenFormaPagoFactura($invoice, $netPaid);
    }

    private function createInvoiceReceipt(FacturaCliente $invoice, string $codPago, float $importe, int $numero, bool $pagado): void
    {
        if ($importe <= 0) {
            return;
        }

        $recibo = new ReciboCliente();
        $recibo->codcliente = $invoice->codcliente;
        $recibo->coddivisa = $invoice->coddivisa;
        $recibo->codigofactura = $invoice->codigo;
        $recibo->codpago = $codPago ?: $invoice->codpago;
        $recibo->fecha = date('Y-m-d');
        $recibo->idempresa = $invoice->idempresa;
        $recibo->idfactura = $invoice->idfactura;
        $recibo->importe = $importe;
        $recibo->liquidado = $pagado ? $importe : 0.0;
        $recibo->nick = $invoice->nick ?? (Session::user()->nick ?? null);
        $recibo->numero = $numero;
        $recibo->observaciones = 'Cobro desde DixTPV';
        $recibo->pagado = $pagado;
        $recibo->vencimiento = $pagado ? $recibo->fecha : $invoice->fecha;
        if ($pagado) {
            $recibo->fechapago = $recibo->fecha;
        }

        $recibo->save();
    }

    private function registrarAnticipoDesdeCobro(int $albaranId, string $codPago, float $amountPaid, float $documentTotal): void
    {
        if ($albaranId <= 0 || $amountPaid <= 0) {
            return;
        }

        if (false === class_exists('FacturaScripts\\Plugins\\Anticipos\\Model\\Anticipo')) {
            return;
        }

        $albaran = new AlbaranCliente();
        if (false === $albaran->loadFromCode($albaranId)) {
            return;
        }

        $netPaid = $this->resolveNetPayment($amountPaid, (float)($albaran->total ?? $documentTotal));
        if ($netPaid <= 0) {
            return;
        }

        $anticipoClass = 'FacturaScripts\\Plugins\\Anticipos\\Model\\Anticipo';
        $anticipo = new $anticipoClass();
        $anticipo->idalbaran = $albaran->idalbaran;
        $anticipo->idempresa = $albaran->idempresa;
        $anticipo->codcliente = $albaran->codcliente;
        $anticipo->coddivisa = $albaran->coddivisa;
        $anticipo->codpago = $codPago ?: $albaran->codpago;
        $anticipo->importe = $netPaid;
        $anticipo->fecha = date('Y-m-d');
        $anticipo->nick = Session::user()->nick ?? ($albaran->nick ?? '');
        $anticipo->nota = 'Cobro desde DixTPV';
        $anticipo->save();
    }

    private function registrarAnticipoPedidoDesdeCobro(int $pedidoId, string $codPago, float $amountPaid, float $documentTotal): void
    {
        if ($pedidoId <= 0 || $amountPaid <= 0 || true === $this->modoHosteleria) {
            return;
        }

        if (false === class_exists('FacturaScripts\\Plugins\\Anticipos\\Model\\Anticipo')) {
            return;
        }

        $pedido = new PedidoCliente();
        if (false === $pedido->loadFromCode($pedidoId)) {
            return;
        }

        $netPaid = $this->resolveNetPayment($amountPaid, (float)($pedido->total ?? $documentTotal));
        if ($netPaid <= 0) {
            return;
        }

        $anticipoClass = 'FacturaScripts\\Plugins\\Anticipos\\Model\\Anticipo';
        $anticipo = new $anticipoClass();
        $anticipo->idpedido = $pedido->idpedido;
        $anticipo->idempresa = $pedido->idempresa;
        $anticipo->codcliente = $pedido->codcliente;
        $anticipo->coddivisa = $pedido->coddivisa;
        $anticipo->codpago = $codPago ?: $pedido->codpago;
        $anticipo->importe = $netPaid;
        $anticipo->fecha = date('Y-m-d');
        $anticipo->nick = Session::user()->nick ?? ($pedido->nick ?? '');
        $anticipo->nota = 'Cobro desde DixTPV';
        $anticipo->save();
    }

    private function resolveOpenCaja(): ?DixTPVCaja
    {
        if (!empty($this->idCaja)) {
            $caja = new DixTPVCaja();
            if ($caja->loadFromCode($this->idCaja)) {
                return $caja;
            }
        }

        $cajaModel = new DixTPVCaja();
        $open = $cajaModel->all([new DataBaseWhere('fechahoracierre', null)], [], 0, 1);
        if (empty($open)) {
            return null;
        }

        $caja = new DixTPVCaja();
        if (false === $caja->loadFromCode($open[0]->idcaja)) {
            return null;
        }

        $this->idCaja = $caja->idcaja;
        $this->ensureCajaSession($this->idCaja);
        return $caja;
    }

    private function pagarFacturaAnterior(): array
    {
        $invoiceId = (int) $this->request->get('idfactura', 0);
        $requestedAmount = $this->request->get('importe');
        $requestedPayment = $this->request->get('codpago', '');

        $response = [
            'success' => false,
            'message' => 'No se pudo registrar el cobro.',
            'idfactura' => $invoiceId
        ];

        if ($invoiceId <= 0) {
            $response['message'] = 'Factura no especificada.';
            return $response;
        }

        $invoice = new FacturaCliente();
        if (false === $invoice->loadFromCode($invoiceId)) {
            $response['message'] = 'La factura indicada no existe.';
            return $response;
        }

        $total = (float) ($invoice->total ?? 0.0);
        if ($total <= 0) {
            $response['message'] = 'La factura no tiene importe pendiente.';
            return $response;
        }

        $currentPaid = 0.0;
        foreach ($invoice->getReceipts() as $receipt) {
            $currentPaid += (float) ($receipt->liquidado ?? 0.0);
        }

        if ($currentPaid >= $total - 0.001) {
            $response['success'] = true;
            $response['message'] = 'La factura ya estaba cobrada.';
            $response['pagada'] = true;
            $response['pendiente'] = 0.0;
            $response['codigo'] = $invoice->codigo;
            return $response;
        }

        $pending = max(0.0, $total - $currentPaid);
        $requestedAmount = is_numeric($requestedAmount) ? (float) $requestedAmount : null;
        $amountToPay = $requestedAmount !== null ? $requestedAmount : $pending;
        $amountToPay = max(0.0, min($pending, $amountToPay));

        if ($amountToPay <= 0) {
            $response['message'] = 'No se ha indicado un importe válido para cobrar.';
            return $response;
        }

        $codPago = $requestedPayment ?: ($invoice->codpago ?: ($this->selectedTerminal->codpago ?? ''));
        $formaPago = new FormaPago();
        if (false === $formaPago->loadFromCode($codPago)) {
            $response['message'] = 'La forma de pago indicada no existe.';
            return $response;
        }

        $caja = $this->resolveOpenCaja();
        if (null === $caja) {
            $response['message'] = 'No hay una caja abierta para registrar el cobro.';
            return $response;
        }

        $this->applyCajaPayment($caja, $formaPago, $amountToPay);
        if (false === $caja->save()) {
            Tools::log()->warning('No se pudo guardar el estado de la caja tras cobrar factura anterior.');
        }

        $nuevoPagado = min($total, $currentPaid + $amountToPay);
        $this->rebuildInvoiceReceipts($invoiceId, $codPago, $nuevoPagado, $total);
        $invoice->pagada = ($nuevoPagado >= ($total - 0.01));
        $invoice->save();

        $response['success'] = true;
        $response['message'] = $invoice->pagada ? 'Factura cobrada correctamente.' : 'Cobro registrado.';
        $response['pendiente'] = max(0.0, $total - $nuevoPagado);
        $response['pagada'] = $invoice->pagada;
        $response['codigo'] = $invoice->codigo;
        $response['cashDrawer'] = $this->openCashDrawer([]);
        return $response;
    }

    private function facturarAlbaranAnterior(): array
    {
        $albaranId = (int) $this->request->get('idalbaran', 0);
        $response = [
            'success' => false,
            'message' => 'No se pudo generar la factura.',
            'idalbaran' => $albaranId
        ];

        if ($albaranId <= 0) {
            $response['message'] = 'Albarán no especificado.';
            return $response;
        }

        $albaran = new AlbaranCliente();
        if (false === $albaran->loadFromCode($albaranId)) {
            $response['message'] = 'El albarán indicado no existe.';
            return $response;
        }

        $existing = $this->resolveAlbaranInvoiceData($albaranId);
        if (!empty($existing)) {
            $response['success'] = true;
            $response['alreadyConverted'] = true;
            $response['factura'] = $existing;
            $response['message'] = 'Este albarán ya fue facturado.';
            return $response;
        }

        $generator = new BusinessDocumentGenerator();
        BusinessDocumentGenerator::setSameDate(false);
        $generated = $generator->generate($albaran, 'FacturaCliente');

        if (false === $generated) {
            $response['message'] = 'No se pudo crear la factura desde el albarán.';
            return $response;
        }

        $factura = null;
        foreach ($generator->getLastDocs() as $doc) {
            if ($doc instanceof FacturaCliente) {
                $factura = $doc;
                break;
            }
        }

        if (null === $factura) {
            $response['message'] = 'No se localizaron los datos de la nueva factura.';
            return $response;
        }

        $response['success'] = true;
        $response['converted'] = true;
        $response['factura'] = [
            'id' => (int) $factura->idfactura,
            'codigo' => (string) $factura->codigo,
            'pagada' => (bool) $factura->pagada,
            'fecha' => (string) ($factura->fecha ?? ''),
            'hora' => (string) ($factura->hora ?? '')
        ];
        $response['message'] = 'Factura generada correctamente.';
        return $response;
    }

    private function convertPedidoAnterior(string $targetDoc): array
    {
        $pedidoId = (int)$this->request->get('idpedido', 0);
        $response = [
            'success' => false,
            'message' => '',
            'idpedido' => $pedidoId
        ];

        if ($pedidoId <= 0) {
            $response['message'] = 'Pedido no especificado.';
            return $response;
        }

        if ('AlbaranCliente' !== $targetDoc && 'FacturaCliente' !== $targetDoc) {
            $response['message'] = 'Conversión no permitida.';
            return $response;
        }

        if ($this->modoHosteleria) {
            $response['message'] = 'Acción disponible solo en modo no hostelería.';
            return $response;
        }

        $pedido = new PedidoCliente();
        if (false === $pedido->loadFromCode($pedidoId)) {
            $response['message'] = 'El pedido indicado no existe.';
            return $response;
        }

        $existing = $this->resolvePedidoConversionData($pedidoId);
        if ('AlbaranCliente' === $targetDoc && !empty($existing['albaran'])) {
            $response['success'] = true;
            $response['alreadyConverted'] = true;
            $response['pedido'] = $existing;
            $response['message'] = 'Este pedido ya se convirtió en albarán.';
            return $response;
        }
        if ('FacturaCliente' === $targetDoc && !empty($existing['factura'])) {
            $response['success'] = true;
            $response['alreadyConverted'] = true;
            $response['pedido'] = $existing;
            $response['message'] = 'Este pedido ya se convirtió en factura.';
            return $response;
        }

        $generator = new BusinessDocumentGenerator();
        BusinessDocumentGenerator::setSameDate('FacturaCliente' !== $targetDoc);
        $generated = $generator->generate($pedido, $targetDoc);
        BusinessDocumentGenerator::setSameDate(false);

        if (false === $generated) {
            $response['message'] = 'No se pudo generar el documento solicitado.';
            return $response;
        }

        $document = null;
        foreach ($generator->getLastDocs() as $doc) {
            if ('AlbaranCliente' === $targetDoc && $doc instanceof AlbaranCliente) {
                $document = $doc;
                break;
            }
            if ('FacturaCliente' === $targetDoc && $doc instanceof FacturaCliente) {
                $document = $doc;
                break;
            }
        }

        if (null === $document) {
            $response['message'] = 'No se localizaron los datos del documento generado.';
            return $response;
        }

        $updated = $this->resolvePedidoConversionData($pedidoId);

        $response['success'] = true;
        $response['pedido'] = $updated;
        $response['message'] = ('AlbaranCliente' === $targetDoc)
            ? 'Pedido convertido a albarán.'
            : 'Pedido convertido a factura.';
        return $response;
    }

    private function ensureCajaSession(?int $idCaja): void
    {
        if ($idCaja <= 0) {
            return;
        }
        if (PHP_SESSION_NONE === session_status()) {
            session_start();
        }

        if (empty($_SESSION['idcaja']) || (int) $_SESSION['idcaja'] !== $idCaja) {
            $_SESSION['idcaja'] = $idCaja;
        }
    }

    private function resolveClosingTicketClass(DixTerminal $terminal = null): string
    {
        $dixTicketClass = 'FacturaScripts\\Plugins\\DixTPV\\Lib\\Tickets\\CashClosingTicket';
        $coreTicketClass = 'FacturaScripts\\Plugins\\Tickets\\Lib\\Tickets\\CashClosingTicket';

        if (class_exists($dixTicketClass)) {
            return $dixTicketClass;
        }

        return class_exists($coreTicketClass) ? $coreTicketClass : $dixTicketClass;
    }

    private function generarDoc($doctype, $comandaId, $formapago, $idcliente, $camarero, $serie, $bono = null) {
        $doctype = $this->normalizeDocType($doctype);
        $document = null;
        $modelClassName = '';

        switch ($doctype) {
            case 'FacturaCliente':
                $document = UtilsTPV::generarFactura($comandaId, $formapago, $idcliente, $camarero, $serie, $bono);
                $modelClassName = 'FacturaCliente';
                break;
            case 'AlbaranCliente':
                $document = UtilsTPV::generarAlbaran($comandaId, $formapago, $idcliente, $serie);
                $modelClassName = 'AlbaranCliente';
                break;
            case 'PedidoCliente':
                $document = UtilsTPV::generarPedido($comandaId, $formapago, $idcliente, $serie);
                $modelClassName = 'PedidoCliente';
                break;
            case 'PresupuestoCliente':
                $document = UtilsTPV::generarPresupuesto($comandaId, $formapago);
                $modelClassName = 'PresupuestoCliente';
                break;
            default:
                error_log('Tipo de documento no válido: ' . $doctype);
                return false;
        }

        if (false === $document) {
            return false;
        }

        return [
            'modelClassName' => $modelClassName,
            'modelCode' => $document->primaryColumnValue(),
            'documentCode' => $document->primaryDescription(),
            'serie' => $document->codserie ?? null,
            'total' => $document->total ?? null
        ];
    }

    private function generarComprobante(array $carrito)
    {
        // Creamos un objeto vacío que simula un ticket
        $ticket = new class {
            public $lines = [];

            public function getLines()
            {
                return $this->lines;
            }
        };

        foreach ($carrito as $item) {
            $line = new class($item) {
                public $referencia;
                public $descripcion;
                public $cantidad;
                public $pvpunitario;
                public $pvptotal;

                public function __construct($i)
                {
                    $this->referencia  = $i['referencia'] ?? '';
                    $this->descripcion = $i['descripcion'] ?? '';
                    $this->cantidad    = $i['cantidad'] ?? 1;
                    $this->pvpunitario = $i['pvp'] ?? 0;
                    $this->pvptotal    = ($i['cantidad'] ?? 1) * ($i['pvp'] ?? 0);
                }
            };

            $ticket->lines[] = $line;
        }

        return $ticket;
    }


    private function buildCajaDocInfo(DixTPVCaja $caja): array
    {
        $printerId = null;
        $paperWidth = null;
        $terminal = null;

        if (!empty($caja->pertenenciaterminal)) {
            $tmpTerminal = new DixTerminal();
            if ($tmpTerminal->loadFromCode($caja->pertenenciaterminal)) {
                $terminal = $tmpTerminal;
                $printerId = $terminal->idprinter ?? null;

                if ($printerId) {
                    $printer = new TicketPrinter();
                    if ($printer->loadFromCode($printerId) && !empty($printer->linelen)) {
                        $paperWidth = $printer->linelen <= 32 ? '58' : '80';
                    }
                }
            }
        }

        $docInfo = [
            'modelClassName' => 'DixTPVCaja',
            'modelCode' => $caja->idcaja,
            'formatClass' => $this->resolveClosingTicketClass($terminal),
            'documentCode' => $caja->nombre,
            'printerId' => $printerId,
            'paperWidth' => $paperWidth
        ];

        if ($terminal instanceof DixTerminal
            && !empty($terminal->ticketformat)
            && false !== strpos($terminal->ticketformat, '\\')) {
            $docInfo['formatClass'] = $terminal->ticketformat;
        }

        return $docInfo;
    }


    private function appendTicketMetadata(array $documentData, DixTerminal $terminal = null): array
    {
        if (null === $terminal) {
            return $documentData;
        }

        $documentData['printerId'] = $documentData['printerId'] ?? null;
        $documentData['paperWidth'] = $documentData['paperWidth'] ?? null;

        if (!empty($terminal->idprinter)) {
            $documentData['printerId'] = (int) $terminal->idprinter;

            $printer = new TicketPrinter();
            if ($printer->loadFromCode($terminal->idprinter) && !empty($printer->linelen)) {
                $documentData['paperWidth'] = $printer->linelen <= 32 ? '58' : '80';
            }
        }

        $modelClassName = (string) ($documentData['modelClassName'] ?? '');
        $documentData['formatClass'] = $this->resolveTicketFormatClass($terminal, $modelClassName);

        return $documentData;
    }

    private function resolveTicketFormatClass(DixTerminal $terminal, string $modelClassName): string
    {
        $configuredFormat = trim((string) ($terminal->ticketformat ?? ''));
        $availableFormats = [];

        if ($modelClassName !== '') {
            try {
                $availableFormats = SendTicket::getFormats($modelClassName);
            } catch (\Throwable $exception) {
                Tools::log()->warning('No se pudieron recuperar los formatos de ticket.', [$exception->getMessage()]);
            }
        }

        if (!empty($availableFormats)) {
            if ($configuredFormat !== '') {
                foreach ($availableFormats as $format) {
                    $className = $format['className'] ?? '';
                    if ($className === $configuredFormat) {
                        return $className;
                    }

                    $label = $format['label'] ?? '';
                    if ($label && 0 === strcasecmp($label, $configuredFormat)) {
                        return $className;
                    }

                    $shortClass = $className ? substr(strrchr($className, '\\'), 1) : '';
                    if ($shortClass && 0 === strcasecmp($shortClass, $configuredFormat)) {
                        return $className;
                    }
                }
            }

            return $availableFormats[0]['className'] ?? 'FacturaScripts\\Plugins\\Tickets\\Lib\\Tickets\\Normal';
        }

        if ($configuredFormat && strpos($configuredFormat, '\\') !== false) {
            return $configuredFormat;
        }

        return $this->resolveClosingTicketClass($terminal);

    }

    private function generarDocDiv($doctype, $cesta, $formapago, $idcliente, $serie, $bono = null) {
        $doctype = $this->normalizeDocType($doctype);
        $document = null;
        $modelClassName = '';

        switch ($doctype) {
            case 'FacturaCliente':
                $document = UtilsTPV::generarFacturaDiv($cesta, $formapago, $idcliente, $serie, $bono);
                $modelClassName = 'FacturaCliente';
                break;
            case 'AlbaranCliente':
                $document = UtilsTPV::generarAlbaranDiv($cesta, $formapago, $idcliente, $serie);
                $modelClassName = 'AlbaranCliente';
                break;
            case 'PedidoCliente':
                $document = UtilsTPV::generarPedidoDiv($cesta, $formapago, $idcliente, $serie);
                $modelClassName = 'PedidoCliente';
                break;
            case 'PresupuestoCliente':
                $document = UtilsTPV::generarPresupuestoDiv($cesta, $formapago);
                $modelClassName = 'PresupuestoCliente';
                break;
            default:
                error_log('Tipo de documento no válido: ' . $doctype);
                return false;
        }

        if (false === $document) {
            return false;
        }

        return [
            'modelClassName' => $modelClassName,
            'modelCode' => $document->primaryColumnValue(),
            'documentCode' => $document->primaryDescription(),
            'serie' => $document->codserie ?? null,
            'total' => $document->total ?? null
        ];
    }

    private function loadInvoiceLines(): array
    {
        $invoiceId = (int) $this->request->get('invoiceId', (int) $this->request->get('idfactura', 0));
        if ($invoiceId <= 0) {
            return ['success' => false, 'message' => 'Factura no válida.'];
        }

        $invoice = new FacturaCliente();
        if (false === $invoice->loadFromCode($invoiceId)) {
            return ['success' => false, 'message' => 'No se encontró la factura seleccionada.'];
        }

        $lines = [];
        foreach ($invoice->getLines() as $line) {
            $qty = (float) ($line->cantidad ?? 0);
            if ($qty <= 0) {
                continue;
            }
            $unit = (float) ($line->pvpunitario ?? 0);
            $base = (float) ($line->pvptotal ?? ($qty * $unit));
            $ivaPct = (float) ($line->iva ?? 0.0);
            $recargoPct = (float) ($line->recargo ?? 0.0);
            $unitWithTax = $unit * (1.0 + ($ivaPct + $recargoPct) / 100.0);
            $lines[] = [
                'idlinea' => $line->idlinea,
                'referencia' => $line->referencia,
                'descripcion' => $line->descripcion,
                'cantidad' => $qty,
                'pvpunitario' => $unit,
                'codimpuesto' => $line->codimpuesto,
                'iva' => $ivaPct,
                'recargo' => $recargoPct,
                'unitWithTax' => round($unitWithTax, 6),
                'totalWithTax' => round($qty * $unitWithTax, 6),
            ];
        }

        return [
            'success' => true,
            'invoice' => [
                'id' => $invoice->idfactura,
                'code' => $invoice->codigo,
                'serie' => $invoice->codserie,
                'date' => $invoice->fecha,
                'hour' => $invoice->hora,
                'total' => (float) ($invoice->total ?? 0),
                'clientCode' => $invoice->codcliente,
                'clientName' => $invoice->nombrecliente,
            ],
            'lines' => $lines,
            'series' => $this->getSeriesOptions($invoice->codserie),
        ];
    }

    private function createRectificativaFromInvoice(): array
    {
        $response = ['success' => false, 'message' => ''];
        $invoiceId = (int) $this->request->get('invoiceId', 0);
        if ($invoiceId <= 0) {
            $response['message'] = 'Factura no válida.';
            return $response;
        }

        $linesPayload = $this->request->get('lines', '[]');
        if (is_string($linesPayload)) {
            $decoded = json_decode($linesPayload, true);
        } elseif (is_array($linesPayload)) {
            $decoded = $linesPayload;
        } else {
            $decoded = [];
        }

        if (!is_array($decoded)) {
            $response['message'] = 'No se recibió ningún detalle para rectificar.';
            return $response;
        }

        $qtyMap = [];
        foreach ($decoded as $lineData) {
            if (!is_array($lineData)) {
                continue;
            }
            $lineId = (int) ($lineData['idlinea'] ?? 0);
            $qty = (float) ($lineData['cantidad'] ?? 0);
            if ($lineId <= 0 || $qty <= 0) {
                continue;
            }
            $qtyMap[$lineId] = $qty;
        }

        if (empty($qtyMap)) {
            $response['message'] = 'Debes seleccionar al menos un producto para devolver.';
            return $response;
        }

        $invoice = new FacturaCliente();
        if (false === $invoice->loadFromCode($invoiceId)) {
            $response['message'] = 'No se encontró la factura indicada.';
            return $response;
        }

        $selectedLines = [];
        $originalLines = $invoice->getLines();
        foreach ($originalLines as $line) {
            if (!isset($qtyMap[$line->idlinea])) {
                continue;
            }
            $maxQty = max(0.0, (float) $line->cantidad);
            $qty = min($qtyMap[$line->idlinea], $maxQty);
            if ($qty <= 0) {
                continue;
            }
            $selectedLines[] = ['line' => $line, 'qty' => $qty];
        }

        if (empty($selectedLines)) {
            $response['message'] = 'Las líneas seleccionadas no coinciden con la factura original.';
            return $response;
        }

        $activeOriginals = array_filter($originalLines, function ($line) {
            return (float) ($line->cantidad ?? 0) > 0;
        });

        $allReturned = count($selectedLines) === count($activeOriginals);
        if ($allReturned) {
            foreach ($selectedLines as $entry) {
                $lineQty = (float) ($entry['line']->cantidad ?? 0);
                if (abs($lineQty - $entry['qty']) > 0.0001) {
                    $allReturned = false;
                    break;
                }
            }
        }

        $serie = $this->resolveRectifySerie(
            $this->request->get('serie', ''),
            $invoice->codserie
        );
        $notes = trim((string) $this->request->get('notes', ''));

        $this->dataBase->beginTransaction();

        try {
            if ($invoice->editable) {
                $this->lockInvoiceForRectification($invoice);
            }

            $refund = new FacturaCliente();
            $refund->loadFromData($invoice->toArray(), $invoice::dontCopyFields());
            $refund->codigorect = $invoice->codigo;
            $refund->codserie = $serie ?: $refund->codserie;
            $refund->idfacturarect = $invoice->idfactura;
            $refund->observaciones = $notes ?: 'Rectificación de ' . $invoice->codigo;
            if ($allReturned) {
                $refund->observaciones = trim($refund->observaciones . ' (Anulación total)');
            }

            if (false === $refund->setDate(date('Y-m-d'), date('H:i:s'))) {
                throw new \RuntimeException('No se pudo establecer la fecha de la rectificativa.');
            }

            if (false === $refund->save()) {
                throw new \RuntimeException('No se pudo guardar la factura rectificativa.');
            }

            foreach ($selectedLines as $entry) {
                /** @var LineaFacturaCliente $baseLine */
                $baseLine = $entry['line'];
                $newLine = $refund->getNewLine($baseLine->toArray());
                $newLine->cantidad = 0 - $entry['qty'];
                $newLine->idlinearect = $baseLine->idlinea;
                if (false === $newLine->save()) {
                    throw new \RuntimeException('No se pudo guardar una línea de la rectificativa.');
                }
            }

            $newLines = $refund->getLines();
            if (!Calculator::calculate($refund, $newLines, true)) {
                throw new \RuntimeException('Error al recalcular los totales de la rectificativa.');
            }

            if ($invoice->pagada) {
                foreach ($refund->getReceipts() as $receipt) {
                    $receipt->pagado = true;
                    $receipt->save();
                }
            }

            $refund->idestado = $invoice->idestado;
            if (false === $refund->save()) {
                throw new \RuntimeException('No se pudo finalizar la factura rectificativa.');
            }

            $this->recalcularCajaTrasRectificativa($refund);
            UtilsTPV::registrarFacturaResumenes($refund, $newLines);

            $this->dataBase->commit();

            $response['success'] = true;
            $response['message'] = $allReturned
                ? 'Factura anulativa creada correctamente.'
                : 'Factura rectificativa creada correctamente.';
            $response['document'] = $this->buildDocumentPayload($refund);
            $response['rectType'] = $allReturned ? 'annulment' : 'rectification';
            return $response;
        } catch (\Throwable $e) {
            $this->dataBase->rollback();
            Tools::log()->warning('rectify-error', ['%message%' => $e->getMessage()]);
            $response['message'] = $e->getMessage();
            return $response;
        }
    }

    private function recalcularCajaTrasRectificativa(FacturaCliente $refund): void
    {
        if (empty($refund->idcaja)) {
            return;
        }

        $caja = new DixTPVCaja();
        if (false === $caja->loadFromCode($refund->idcaja)) {
            return;
        }

        $pago = new FormaPago();
        if (false === $pago->loadFromCode($refund->codpago)) {
            return;
        }

        if ($pago->efectivo) {
            $caja->dinerofin = (float) ($caja->dinerofin ?? 0) + (float) $refund->total;
        } else {
            $caja->dinerocredito = (float) ($caja->dinerocredito ?? 0) + (float) $refund->total;
        }

        $caja->save();
    }

    private function lockInvoiceForRectification(FacturaCliente $invoice): void
    {
        $locked = false;
        foreach ($invoice->getAvailableStatus() as $status) {
            if ($status->editable || !$status->activo) {
                continue;
            }

            $invoice->idestado = $status->idestado;
            $invoice->editable = false;
            if (false === $invoice->save()) {
                throw new \RuntimeException('No se pudo bloquear la factura original.');
            }
            $locked = true;
            break;
        }

        if ($locked) {
            return;
        }

        $invoice->editable = false;
        if (false === $invoice->save()) {
            throw new \RuntimeException('No se pudo bloquear la factura original.');
        }
    }

    private function getSeriesOptions(?string $preferred = null): array
    {
        $preferred = trim((string) $preferred);
        $source = $this->getSeriesSource();
        $options = [];
        foreach ($source as $serie) {
            $options[] = [
                'code' => $serie['codserie'],
                'description' => $serie['descripcion'],
                'selected' => $serie['codserie'] === $preferred,
            ];
        }

        $hasSelected = false;
        foreach ($options as $option) {
            if (!empty($option['selected'])) {
                $hasSelected = true;
                break;
            }
        }

        if (!$hasSelected) {
            if ($preferred !== '' && isset($this->seriesMap[$preferred])) {
                array_unshift($options, [
                    'code' => $preferred,
                    'description' => $this->seriesMap[$preferred]->descripcion ?? $preferred,
                    'selected' => true,
                ]);
            } elseif (!empty($options)) {
                $options[0]['selected'] = true;
            } elseif ($preferred !== '') {
                $options[] = [
                    'code' => $preferred,
                    'description' => $this->seriesMap[$preferred]->descripcion ?? $preferred,
                    'selected' => true,
                ];
            }
        }

        return $options;
    }

    private function getSeriesSource(): array
    {
        if (!empty($this->rectifySeries)) {
            return $this->rectifySeries;
        }

        $output = [];
        foreach ($this->series as $serie) {
            $output[] = [
                'codserie' => $serie->codserie,
                'descripcion' => $serie->descripcion,
                'tipo' => $serie->tipo ?? ''
            ];
        }

        return $output;
    }

    private function resolveRectifySerie(?string $requested, ?string $original): ?string
    {
        $requested = trim((string) $requested);
        $source = $this->getSeriesSource();
        $codes = array_map(static function ($serie) {
            return $serie['codserie'];
        }, $source);

        if ($requested !== '' && in_array($requested, $codes, true)) {
            return $requested;
        }

        $original = trim((string) $original);
        if ($original !== '' && in_array($original, $codes, true)) {
            return $original;
        }

        if (!empty($codes)) {
            return $codes[0];
        }

        if ($original !== '') {
            return $original;
        }

        return $this->defaultRectifySerie ?: ($source[0]['codserie'] ?? ($this->series[0]->codserie ?? null));
    }

    private function buildDocumentPayload(FacturaCliente $document): array
    {
        return [
            'modelClassName' => 'FacturaCliente',
            'modelCode' => $document->primaryColumnValue(),
            'documentCode' => $document->primaryDescription(),
            'serie' => $document->codserie,
            'total' => (float) $document->total,
        ];
    }

    public function obtenerMesasDisponibles() {
        $mesas = new DixMesa();
        $mesasDisponibles = $mesas->all([new DataBaseWhere('ocupada', '0')], [], 0, 0);

        $listaMesas = [];
        foreach ($mesasDisponibles as $mesa) {
            $listaMesas[] = $mesa->nombre;
        }

        $this->response->setContent(json_encode($listaMesas));
    }

    private function searchClient(): array {
        $registro = [];
        $term = $this->request->get('search', 1);
        $where = [
            new DataBaseWhere('nombre', '%' . $term . '%', 'XLIKE'),
            new DataBaseWhere('razonsocial', '%' . $term . '%', 'XLIKE', 'OR')
        ];
        $client = new Cliente();
        $client = $client->all($where);
        foreach ($client as $cliente) {
            $registro[] = ['value' => $cliente->codcliente, 'label' => $cliente->nombre];
        }
        return $registro;
    }

    private function anhadirCliente() {
        //Recogemos los datos de la solicitud del POST
        $razonsocial = $this->request->request->get('razonSocial');
        $tipoidfiscal = $this->request->request->get('tipoIdFiscal');
        $cifnif = $this->request->request->get('numeroIdFiscal');
        $direccion = $this->request->request->get('direccion');
        $codpostal = $this->request->request->get('codigoPostal');
        $ciudad = $this->request->request->get('ciudad');
        $provincia = $this->request->request->get('provincia');
        $codpais = $this->request->request->get('pais');

        //Creamos el modelo y lo rellenamos de datos para guardarlo.
        $clienteNuevo = new Cliente();
        $clienteNuevo->razonsocial = $razonsocial;
        $clienteNuevo->nombre = $razonsocial;
        $clienteNuevo->tipoidfiscal = $tipoidfiscal;
        $clienteNuevo->cifnif = $cifnif;
        $clienteNuevo->save();

        //Hacemos lo mismo con contacto
        $contactoNuevo = new Contacto();
        $where = [new DataBaseWhere('cifnif', $cifnif)];
        $contactoNuevo->loadFromCode('', $where);
        $contactoNuevo->direccion = $direccion;
        $contactoNuevo->codpostal = $codpostal;
        $contactoNuevo->ciudad = $ciudad;
        $contactoNuevo->provincia = $provincia;
        $contactoNuevo->codpais = $codpais;
        $contactoNuevo->save();
    }
}
