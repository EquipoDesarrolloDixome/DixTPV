<?php

namespace FacturaScripts\Plugins\DixTPV\Lib;

use FacturaScripts\Core\Base\Controller;
use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
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
use FacturaScripts\Core\Html;
use FacturaScripts\Dinamic\Lib\AssetManager;
use FacturaScripts\Core\Tools;
use FacturaScripts\Plugins\DixTPV\Funciones\Funciones;
use FacturaScripts\Core\Base\Calculator;
use FacturaScripts\Dinamic\Model\DixListaFin;
use FacturaScripts\Dinamic\Model\Impuesto;
use FacturaScripts\Plugins\DixTPV\Model\DixFormasPagoResumen as DinDixFormasPagoResumen;
use FacturaScripts\Plugins\DixTPV\Model\DixTipoIVAResumen as DinDixTipoIVAResumen;
use FacturaScripts\Plugins\DixTPV\Model\DixProductosResumen as DinDixProductosResumen;
use FacturaScripts\Dinamic\Model\SecuenciaDocumento;
use FacturaScripts\Plugins\DixTPV\Model\DixAlbaranCaja;
use FacturaScripts\Plugins\DixTPV\Model\DixPedidoCaja;

class UtilsTPV {

    private static $albaranCajaTableReady = false;
    private static $pedidoCajaTableReady = false;

public static function generarFactura($comandaId, $formapago, $idcliente, $camarero, $serie, $bono = null) {
    $comanda = self::getComanda($comandaId);
    if (!$comanda) {
        error_log("Comanda no encontrada: ID $comandaId");
        return false;
    }

    $detalles = self::getDetallesComanda($comandaId);
    if (empty($detalles)) {
        error_log("Detalles de la comanda están vacíos: ID $comandaId");
        return false;
    }

    $cliente = new Cliente();
    if (false === $cliente->loadFromCode($idcliente)) {
        error_log("Cliente no encontrado: ID " . $comanda->idcliente);
        return false;
    }

    $factura = new FacturaCliente();
    $factura->setSubject($cliente);
    $factura->fecha    = date('Y-m-d');
    $factura->codpago  = $formapago;
    $factura->idagente= $camarero;
    $factura->codserie = $serie;

    $caja = new DixTPVCaja();
    if ($cajaAbierta = $caja->all([new DataBaseWhere('fechahoracierre', null)])) {
        $factura->idcaja = $cajaAbierta[0]->idcaja;
    }

    if (!$factura->save()) {
        return false;
    }

    foreach ($detalles as $detalle) {
        $variant = self::getVariant($detalle->idproducto);
        $prod    = self::getProduct($detalle->idproducto);

        $newLinea = ($variant && !empty($variant->referencia))
            ? $factura->getNewProductLine($variant->referencia)
            : $factura->getNewLine();

        if (!$newLinea) {
            error_log("No se pudo crear la línea para el producto: " . $detalle->idproducto);
            continue;
        }

        $newLinea->cantidad     = $detalle->cantidad;
        $newLinea->descripcion  = !empty($detalle->descripcion) ? $detalle->descripcion : ($prod ? $prod->descripcion : 'Producto sin descripción');
        $newLinea->pvpunitario  = floatval($detalle->precio_unitario); // SIN IVA
        $newLinea->codimpuesto  = self::resolveTaxCode($detalle->codimpuesto ?? null, $prod->codimpuesto ?? null);

        if (!$newLinea->save()) {
            return false;
        }
    }

    if ($bono && ($bono['importe'] ?? 0) > 0) {
        $voucherLine = $factura->getNewLine();
        if ($voucherLine) {
            $voucherLine->descripcion = 'Canje bono ' . ($bono['codigo'] ?? '');
            $voucherLine->cantidad = 1;
            $voucherLine->pvpunitario = -1 * abs((float)$bono['importe']);
            $voucherLine->codimpuesto = null;
            $voucherLine->observaciones = 'Descuento aplicado por bono.';
            $voucherLine->referencia = 'BONO';
            $voucherLine->save();
        }
    }

    $lineas = $factura->getLines();
    if (!Calculator::calculate($factura, $lineas, true)) {
        error_log("Error al calcular los totales de la factura");
        return false;
    }

    self::registrarFacturaResumenes($factura, $lineas);

    return $factura;
}

public static function generarFacturaDiv($cesta, $formapago, $idcliente, $serie, $bono = null) {
    $cliente = new Cliente();
    if (false === $cliente->loadFromCode($idcliente)) {
        error_log("Cliente no encontrado: ID $idcliente");
        return false;
    }

    $factura = new FacturaCliente();
    $factura->setSubject($cliente);
    $factura->fecha    = date('Y-m-d');
    $factura->codpago  = $formapago;
    $factura->codserie = $serie;

    $caja = new DixTPVCaja();
    if ($cajaAbierta = $caja->all([new DataBaseWhere('fechahoracierre', null)])) {
        $factura->idcaja = $cajaAbierta[0]->idcaja;
    }

    if (!$factura->save()) {
        return false;
    }

    foreach ($cesta as $producto) {
        $variant = self::getVariantDiv($producto['referencia'] ?? '');
        $prod    = $variant ? self::getProductDiv($variant->idproducto) : null;

        $newLinea = ($variant && !empty($variant->referencia))
            ? $factura->getNewProductLine($variant->referencia)
            : $factura->getNewLine();

        if (!$newLinea) {
            error_log("No se pudo crear la línea para el producto: " . ($producto['referencia'] ?? 'sin ref'));
            continue;
        }

        $newLinea->descripcion  = !empty($producto['descripcion']) ? $producto['descripcion'] : ($prod->descripcion ?? 'Producto sin descripción');
        $newLinea->cantidad     = isset($producto['cantidad']) ? $producto['cantidad'] : 1;
        $newLinea->pvpunitario  = isset($producto['pvp']) ? floatval($producto['pvp']) : 0.0; // SIN IVA
        $newLinea->codimpuesto  = self::resolveTaxCode($producto['codimpuesto'] ?? null, $prod->codimpuesto ?? null);

        if (!$newLinea->save()) {
            return false;
        }
    }

    if ($bono && ($bono['importe'] ?? 0) > 0) {
        $voucherLine = $factura->getNewLine();
        if ($voucherLine) {
            $voucherLine->descripcion = 'Canje bono ' . ($bono['codigo'] ?? '');
            $voucherLine->cantidad = 1;
            $voucherLine->pvpunitario = -1 * abs((float)$bono['importe']);
            $voucherLine->codimpuesto = null;
            $voucherLine->observaciones = 'Descuento aplicado por bono.';
            $voucherLine->referencia = 'BONO';
            $voucherLine->save();
        }
    }

    $lineas = $factura->getLines();
    if (!Calculator::calculate($factura, $lineas, true)) {
        error_log("Error al calcular los totales de la factura");
        return false;
    }

    self::registrarFacturaResumenes($factura, $lineas);

    return $factura;
}

public static function registrarFacturaResumenes(FacturaCliente $factura, array $lineas = null): void
{
    if (!$factura) {
        return;
    }

    $lineas = $lineas ?? $factura->getLines();

    $lista = new DixListaFin();
    $lista->codpago    = $factura->codpago;
    $lista->idcaja     = $factura->idcaja;
    $lista->total      = $factura->total;
    $lista->idfactura  = $factura->idfactura;
    $lista->idagente   = $factura->idagente;
    $lista->save();

    self::upsertResumenFormasPago($factura->idcaja, $factura->codpago, (float) $factura->total);

    if (empty($lineas)) {
        return;
    }

    $impModel = new Impuesto();
    foreach ($lineas as $lin) {
        $cod  = $lin->codimpuesto ?? null;
        $qty  = (float)($lin->cantidad ?? 1);
        $unit = (float)($lin->pvpunitario ?? 0.0);
        $base = (float)($lin->pvptotal ?? ($qty * $unit));
        $ivaPct = (float)($lin->iva ?? 0.0);
        $recargoPct = (float)($lin->recargo ?? 0.0);

        if ($cod) {
            if ($impModel->loadFromCode($cod)) {
                $ivaPct = (float)$impModel->iva;
                $recargoPct = (float)$impModel->recargo;
            }
            $totalConIVA = $base * (1.0 + ($ivaPct + $recargoPct) / 100.0);
            self::upsertResumenIVA($factura->idcaja, $cod, $totalConIVA);
        }

        $totalLineaConImpuestos = $base * (1.0 + ($ivaPct + $recargoPct) / 100.0);
        $ref  = trim((string)($lin->referencia ?? ''));
        $desc = trim((string)($lin->descripcion ?? ''));
        self::upsertResumenProducto($factura->idcaja, $ref, $desc, $totalLineaConImpuestos, $qty);
    }
}

    private static function registrarAlbaranResumen(AlbaranCliente $albaran): void
    {
        if (empty($albaran->idcaja) || empty($albaran->idalbaran)) {
            return;
        }

    self::ensureAlbaranCajaTable();
    $registro = new DixAlbaranCaja();
    $registro->idcaja = (int)$albaran->idcaja;
    $registro->idalbaran = (int)$albaran->idalbaran;
    $registro->codpago = $albaran->codpago;
    $registro->codigo = $albaran->codigo;
        $registro->total = (float)($albaran->total ?? 0.0);
        $registro->save();
    }

    private static function registrarPedidoResumen(PedidoCliente $pedido): void
    {
        if (empty($pedido->idcaja) || empty($pedido->idpedido)) {
            return;
        }

        self::ensurePedidoCajaTable();
        $registro = new DixPedidoCaja();
        $registro->idcaja = (int)$pedido->idcaja;
        $registro->idpedido = (int)$pedido->idpedido;
        $registro->codpago = $pedido->codpago;
        $registro->codigo = $pedido->codigo;
        $registro->total = (float)($pedido->total ?? 0.0);
        $registro->save();
    }

    public static function registrarPagoAlbaran(int $idCaja, string $codPago, float $importe): void
    {
        if ($idCaja <= 0 || empty($codPago)) {
            return;
        }

    $importe = (float) $importe;
    if (abs($importe) < 0.0001) {
        return;
    }

        self::upsertResumenFormasPago($idCaja, $codPago, $importe);
    }

    public static function registrarPagoPedido(int $idCaja, string $codPago, float $importe): void
    {
        self::registrarPagoAlbaran($idCaja, $codPago, $importe);
    }

private static function buildProductoKey(string $referencia = null, string $descripcion = null): string
{
    $ref = trim((string)$referencia);
    if ($ref !== '') return mb_substr($ref, 0, 160);

    $desc = trim((string)$descripcion);
    $desc = preg_replace('/\s+/', ' ', $desc ?? '');
    return mb_substr($desc !== '' ? $desc : 'SIN-REF', 0, 160);
}

/** Acumula € (sin IVA) y unidades por producto en la caja dada. */
private static function upsertResumenProducto($idcaja, $referencia, $descripcion, float $importeSinIVA, float $cantidad): void
{
    if (!$idcaja || ($importeSinIVA == 0.0 && $cantidad == 0.0)) return;

    $clave = self::buildProductoKey($referencia, $descripcion);
    $res   = new DinDixProductosResumen();
    $where = [
        new DataBaseWhere('idcaja', $idcaja),
        new DataBaseWhere('claveprod', $clave),
    ];
    $filas = $res->all($where, [], 0, 0);

    if (!empty($filas)) {
        $item = $filas[0];
        $item->total    = max(0.0, (float)$item->total    + $importeSinIVA);
        $item->unidades = max(0.0, (float)$item->unidades + $cantidad);
        if ($referencia && !$item->referencia)  $item->referencia  = mb_substr($referencia, 0, 120);
        if ($descripcion && !$item->descripcion)$item->descripcion = mb_substr($descripcion, 0, 255);
        $item->save();
        // Si quedaron duplicados antiguos, puedes limpiar los extra aquí si quieres.
    } else {
        $item = new DinDixProductosResumen();
        $item->idcaja      = $idcaja;
        $item->claveprod   = $clave;
        $item->referencia  = mb_substr((string)$referencia, 0, 120);
        $item->descripcion = mb_substr((string)$descripcion, 0, 255);
        $item->unidades    = max(0.0, $cantidad);
        $item->total       = max(0.0, $importeSinIVA);
        $item->save();
    }
}

private static function upsertResumenFormasPago($idcaja, $codpago, float $delta): void
{
    if (!$idcaja || !$codpago || $delta == 0.0) return;

    $resumen = new DinDixFormasPagoResumen();
    $where = [
        new DataBaseWhere('idcaja', $idcaja),
        new DataBaseWhere('codpago', $codpago),
    ];

    // Trae todas por si quedó basura previa
    $filas = $resumen->all($where, [], 0, 0);

    if (!empty($filas)) {
        // Suma totales de todas y deja solo una
        $keep = $filas[0];
        $sum = (float)$delta;
        foreach ($filas as $i => $f) {
            $sum += (float)$f->total;
            if ($i > 0) { // borra duplicadas
                $f->delete();
            }
        }
        $keep->total = max(0.0, $sum);
        $keep->save();
    } else {
        // No existe: crear
        $item = new DinDixFormasPagoResumen();
        $item->idcaja  = $idcaja;
        $item->codpago = $codpago;
        $item->total   = max(0.0, $delta);
        $item->save();
    }
}
private static function upsertResumenIVA($idcaja, $codimpuesto, float $delta): void
{
    if (!$idcaja || !$codimpuesto || $delta == 0.0) return;

    $res = new DinDixTipoIVAResumen();
    $where = [
        new DataBaseWhere('idcaja', $idcaja),
        new DataBaseWhere('codimpuesto', $codimpuesto),
    ];
    $filas = $res->all($where, [], 0, 0);

    if (!empty($filas)) {
        $keep = $filas[0];
        $sum = (float)$keep->total + $delta;
        // si hubiera duplicadas antiguas, las consolidamos
        for ($i = 1; $i < count($filas); $i++) {
            $sum += (float)$filas[$i]->total;
            $filas[$i]->delete();
        }
        $keep->total = max(0.0, $sum);
        $keep->save();
    } else {
        $item = new DinDixTipoIVAResumen();
        $item->idcaja = $idcaja;
        $item->codimpuesto = $codimpuesto;
        $item->total = max(0.0, $delta);
        $item->save();
    }
}

public static function ajustarResumenFormaPagoFactura(FacturaCliente $factura, float $importePagado): void
{
    if (!$factura || empty($factura->idcaja) || empty($factura->codpago)) {
        return;
    }

    $delta = (float)$importePagado - (float)($factura->total ?? 0.0);
    if (abs($delta) < 0.0001) {
        return;
    }

    self::upsertResumenFormasPago($factura->idcaja, $factura->codpago, $delta);
}


    public static function getVariant($idProducto) {
        $whereVariant = [new DataBaseWhere('idproducto', $idProducto)];
        $variant = new Variante();
        $variantList = $variant->all($whereVariant, [], 0, 0);
        return $variantList[0] ?? null;
    }

    public static function getProduct($idProducto) {
        $whereProduct = [new DataBaseWhere('idproducto', $idProducto)];
        $producto = new Producto();
        $productoList = $producto->all($whereProduct, [], 0, 0);
        return $productoList[0] ?? null;
    }

    public static function getComanda($id) {
        $comanda = new DixTPVComanda();
        $comanda->loadFromCode($id);
        return $comanda;
    }

    public static function getDetallesComanda($idComanda) {
        if (empty($idComanda)) {
            return [];
        }

        $whereDetails = [new DataBaseWhere('idcomanda', $idComanda)];
        $detalles = new DixDetailComanda();
        $detallesArray = $detalles->all($whereDetails, [], 0, 0);

        return $detallesArray ?: [];
    }

    public static function getVariantDiv($referencia) {
        //$whereVariant = [new DataBaseWhere('referencia', $referencia)];
        $variant = new Variante();
        if ($variant->loadFromCode('', [new DataBaseWhere('referencia', $referencia)])) {
            // OK
        }
        return $variant ?? null;
    }

    public static function getProductDiv($referencia) {
        $whereProduct = [new DataBaseWhere('idproducto', $referencia)];
        $producto = new Producto();
        $productoList = $producto->all($whereProduct, [], 0, 0);
        return $productoList[0] ?? null;
    }

/** Mapea un valor recibido (21 o 'IVA21') a un codimpuesto válido. */
private static function resolveTaxCode($value, string $fallbackCode = null)
{
    $impModel = new Impuesto();

    if (is_numeric($value)) {
        $iva = floatval($value);
        $todos = $impModel->all([], [], 0, 0);
        foreach ($todos as $imp) {
            if (abs(floatval($imp->iva) - $iva) < 0.0001) {
                return $imp->codimpuesto;
            }
        }
    } elseif (is_string($value) && $value !== '') {
        $code = trim($value);
        if ($impModel->loadFromCode($code)) {
            return $code;
        }
    }

    if ($fallbackCode && $impModel->loadFromCode($fallbackCode)) {
        return $fallbackCode;
    }

    // último fallback al de Configuración o IVA21 si existe
    $def = Tools::settings('default', 'codimpuesto');
    if ($def && $impModel->loadFromCode($def)) return $def;
    if ($impModel->loadFromCode('IVA21')) return 'IVA21';

    return null;
}


    public static function generarAlbaran($comandaId, $formapago, $idcliente, $serie = null) {
        $comanda = self::getComanda($comandaId);
        if (!$comanda) {
            error_log("❌ Comanda no encontrada: ID $comandaId");
            return false;
        }

        $detalles = self::getDetallesComanda($comandaId);
        if (empty($detalles)) {
            error_log("❌ Detalles de la comanda están vacíos: ID $comandaId");
            return false;
        }

        $cliente = new Cliente();
        $clienteCode = $idcliente ?: $comanda->idcliente;
        if (false === $cliente->loadFromCode($clienteCode)) {
            error_log("❌ Cliente no encontrado: ID " . $clienteCode);
            return false;
        }

        $albaran = new AlbaranCliente();
        $albaran->setSubject($cliente);
        $fecha = date('Y-m-d');
        $hora = date('H:i:s');
        $albaran->setDate($fecha, $hora);
        $albaran->codpago = $formapago;
        if (!empty($serie)) {
            $albaran->codserie = $serie;
        }

        // Agregamos idcaja si es necesario
        $albaran->idcaja = self::resolveCurrentCajaId();
        self::normalizeSequencePattern('AlbaranCliente', $albaran->codserie, $albaran->codejercicio, (int)($albaran->idempresa ?? 0));

        if (!$albaran->save()) {
            error_log("❌ Error al guardar el albarán: " . self::formatModelErrors($albaran));
            return false;
        } else {
            error_log("✅ Albarán guardado correctamente: ID " . $albaran->idalbaran);
        }

        foreach ($detalles as $detalle) {
            $variant = self::getVariant($detalle->idproducto);
            $prod = self::getProduct($detalle->idproducto);

            $newLinea = ($variant && !empty($variant->referencia))
                ? $albaran->getNewProductLine($variant->referencia)
                : $albaran->getNewLine();
            if (!$newLinea) {
                error_log("⚠️ No se pudo crear la línea para el producto: " . $detalle->idproducto);
                continue;
            }

            // Usamos pvpunitario en lugar de precio
            $newLinea->descripcion = !empty($detalle->descripcion) ? $detalle->descripcion : ($prod ? $prod->descripcion : 'Sin descripción');
            $newLinea->cantidad = $detalle->cantidad;
            $newLinea->pvpunitario = $variant ? $variant->precio : floatval($detalle->precio_unitario ?? 0.0);
            $newLinea->codimpuesto = self::resolveTaxCode($detalle->codimpuesto ?? null, $prod->codimpuesto ?? null);

            if (!$newLinea->save()) {
                error_log("❌ Error al guardar la línea del albarán: " . self::formatModelErrors($newLinea));
                return false;
            } else {
                error_log("✅ Línea del albarán guardada correctamente: " . json_encode($newLinea));
            }
        }

        $lineas = $albaran->getLines();
        if (!Calculator::calculate($albaran, $lineas, true)) {
            error_log("❌ Error al calcular los totales del albarán");
            return false;
        }
        self::registrarAlbaranResumen($albaran);
        return $albaran;
    }

    public static function generarPedido($comandaId, $formapago = null, $idcliente = null, $serie = null) {
        $comanda = self::getComanda($comandaId);
        if (!$comanda) {
            error_log("Comanda no encontrada: ID $comandaId");
            return false;
        }

        $detalles = self::getDetallesComanda($comandaId);
        if (empty($detalles)) {
            error_log("Detalles de la comanda están vacíos: ID $comandaId");
            return false;
        }

        $cliente = new Cliente();
        $clienteCode = $idcliente ?: $comanda->idcliente;
        if (false === $cliente->loadFromCode($clienteCode)) {
            error_log("Cliente no encontrado: ID " . $clienteCode);
            return false;
        }

        $pedido = new PedidoCliente();
        $pedido->setSubject($cliente);
        $pedido->fecha = date('Y-m-d');
        if (!empty($formapago)) {
            $pedido->codpago = $formapago;
        }
        if (!empty($serie)) {
            $pedido->codserie = $serie;
        }
        $pedido->idcaja = self::resolveCurrentCajaId();
        self::normalizeSequencePattern('PedidoCliente', $pedido->codserie, $pedido->codejercicio, (int)($pedido->idempresa ?? 0));
        if (!$pedido->save()) {
            error_log("Error al guardar el pedido: " . self::formatModelErrors($pedido));
            return false;
        }

        foreach ($detalles as $detalle) {
            $variant = self::getVariant($detalle->idproducto);
            $prod = self::getProduct($detalle->idproducto);

            $newLinea = $pedido->getNewProductLine($detalle->idproducto);
            if (!$newLinea) {
                error_log("No se pudo crear la línea para el producto: " . $detalle->idproducto);
                continue;
            }

            $newLinea->descripcion = $prod ? $prod->descripcion : 'Sin descripción';
            $newLinea->cantidad = $detalle->cantidad;
            $newLinea->pvpsindto = $variant ? $variant->precio : 0.0;
            $newLinea->pvptotal = $variant ? $variant->precio : 0.0;
            $newLinea->pvpunitario = $variant ? $variant->precio : 0.0;

            if (!$newLinea->save()) {
                error_log("Error al guardar la línea del pedido: " . self::formatModelErrors($newLinea));
                return false;
            }
        }

        $lineas = $pedido->getLines();
        if (!Calculator::calculate($pedido, $lineas, true)) {
            error_log("Error al calcular los totales del pedido");
            return false;
        }

        self::registrarPedidoResumen($pedido);
        return $pedido;
    }

    public static function generarPresupuesto($comandaId) {
        $comanda = self::getComanda($comandaId);
        if (!$comanda) {
            error_log("Comanda no encontrada: ID $comandaId");
            return false;
        }

        $detalles = self::getDetallesComanda($comandaId);
        if (empty($detalles)) {
            error_log("Detalles de la comanda están vacíos: ID $comandaId");
            return false;
        }

        $cliente = new Cliente();
        if (false === $cliente->loadFromCode($comanda->idcliente)) {
            error_log("Cliente no encontrado: ID " . $comanda->idcliente);
            return false;
        }

        $presupuesto = new PresupuestoCliente();
        $presupuesto->setSubject($cliente);
        $presupuesto->fecha = date('Y-m-d');
        if (!$presupuesto->save()) {
            error_log("Error al guardar el presupuesto: " . self::formatModelErrors($presupuesto));
            return false;
        }

        foreach ($detalles as $detalle) {
            $variant = self::getVariant($detalle->idproducto);
            $prod = self::getProduct($detalle->idproducto);

            $newLinea = $presupuesto->getNewProductLine($detalle->idproducto);
            if (!$newLinea) {
                error_log("No se pudo crear la línea para el producto: " . $detalle->idproducto);
                continue;
            }

            $newLinea->descripcion = $prod ? $prod->descripcion : 'Sin descripción';
            $newLinea->cantidad = $detalle->cantidad;
            $newLinea->precio = $variant ? $variant->precio : 0.0;

            if (!$newLinea->save()) {
                error_log("Error al guardar la línea del presupuesto: " . self::formatModelErrors($newLinea));
                return false;
            }
        }

        $lineas = $presupuesto->getLines();
        if (!Calculator::calculate($presupuesto, $lineas, true)) {
            error_log("Error al calcular los totales del presupuesto");
            return false;
        }

        return $presupuesto;
    }

    public static function generarAlbaranDiv(array $cesta, $formapago, $idcliente, $serie = null) {
        if (empty($cesta)) {
            error_log('❌ No se recibieron productos para generar el albarán dividido.');
            return false;
        }

        $cliente = new Cliente();
        if (false === $cliente->loadFromCode($idcliente)) {
            error_log("❌ Cliente no encontrado: ID " . $idcliente);
            return false;
        }

        $albaran = new AlbaranCliente();
        $albaran->setSubject($cliente);
        $albaran->setDate(date('Y-m-d'), date('H:i:s'));
        $albaran->codpago = $formapago;
        if (!empty($serie)) {
            $albaran->codserie = $serie;
        }

        $albaran->idcaja = self::resolveCurrentCajaId();
        self::normalizeSequencePattern('AlbaranCliente', $albaran->codserie, $albaran->codejercicio, (int)($albaran->idempresa ?? 0));

        if (!$albaran->save()) {
            error_log("❌ Error al guardar el albarán: " . self::formatModelErrors($albaran));
            return false;
        }

        foreach ($cesta as $producto) {
            $variant = self::getVariantDiv($producto['referencia'] ?? '');
            $prod = $variant ? self::getProduct($variant->idproducto) : null;

            $newLinea = ($variant && !empty($variant->referencia))
                ? $albaran->getNewProductLine($variant->referencia)
                : $albaran->getNewLine();

            if (!$newLinea) {
                error_log("⚠️ No se pudo crear la línea para el producto dividido: " . ($producto['referencia'] ?? 'sin ref'));
                continue;
            }

            $newLinea->descripcion = !empty($producto['descripcion']) ? $producto['descripcion'] : ($prod->descripcion ?? 'Sin descripción');
            $newLinea->cantidad = isset($producto['cantidad']) ? $producto['cantidad'] : 1;
            $newLinea->pvpunitario = isset($producto['pvp']) ? floatval($producto['pvp']) : 0.0;
            $newLinea->codimpuesto = self::resolveTaxCode($producto['codimpuesto'] ?? null, $prod->codimpuesto ?? null);

            if (!$newLinea->save()) {
                error_log("❌ Error al guardar la línea del albarán dividido: " . self::formatModelErrors($newLinea));
                return false;
            }
        }

        $lineas = $albaran->getLines();
        if (!Calculator::calculate($albaran, $lineas, true)) {
            error_log("❌ Error al calcular los totales del albarán");
            return false;
        }

        self::registrarAlbaranResumen($albaran);
        return $albaran;
    }

    public static function generarPedidoDiv(array $cesta, $formapago, $idcliente, $serie = null) {
        if (empty($cesta)) {
            error_log('❌ No se recibieron productos para generar el pedido dividido.');
            return false;
        }

        $cliente = new Cliente();
        if (false === $cliente->loadFromCode($idcliente)) {
            error_log("❌ Cliente no encontrado: ID " . $idcliente);
            return false;
        }

        $pedido = new PedidoCliente();
        $pedido->setSubject($cliente);
        $pedido->fecha = date('Y-m-d');
        if (!empty($formapago)) {
            $pedido->codpago = $formapago;
        }
        if (!empty($serie)) {
            $pedido->codserie = $serie;
        }

        $pedido->idcaja = self::resolveCurrentCajaId();
        self::normalizeSequencePattern('PedidoCliente', $pedido->codserie, $pedido->codejercicio, (int)($pedido->idempresa ?? 0));

        if (!$pedido->save()) {
            error_log("❌ Error al guardar el pedido: " . self::formatModelErrors($pedido));
            return false;
        }

        foreach ($cesta as $producto) {
            $variant = self::getVariantDiv($producto['referencia'] ?? '');
            $prod = $variant ? self::getProduct($variant->idproducto) : null;

            $newLinea = ($variant && !empty($variant->referencia))
                ? $pedido->getNewProductLine($variant->referencia)
                : $pedido->getNewLine();

            if (!$newLinea) {
                error_log("⚠️ No se pudo crear la línea para el producto dividido: " . ($producto['referencia'] ?? 'sin ref'));
                continue;
            }

            $newLinea->descripcion = !empty($producto['descripcion']) ? $producto['descripcion'] : ($prod->descripcion ?? 'Sin descripción');
            $newLinea->cantidad = isset($producto['cantidad']) ? $producto['cantidad'] : 1;
            $newLinea->pvpunitario = isset($producto['pvp']) ? floatval($producto['pvp']) : 0.0;
            $newLinea->codimpuesto = self::resolveTaxCode($producto['codimpuesto'] ?? null, $prod->codimpuesto ?? null);

            if (!$newLinea->save()) {
                error_log("❌ Error al guardar la línea del pedido dividido: " . self::formatModelErrors($newLinea));
                return false;
            }
        }

        $lineas = $pedido->getLines();
        if (!Calculator::calculate($pedido, $lineas, true)) {
            error_log("❌ Error al calcular los totales del pedido dividido");
            return false;
        }

        self::registrarPedidoResumen($pedido);
        return $pedido;
    }

    private static function normalizeSequencePattern(string $docType, ?string $codSerie, ?string $codejercicio, int $idempresa): void
    {
        if (empty($codSerie) || empty($codejercicio) || empty($idempresa)) {
            return;
        }

        $sequenceModel = new SecuenciaDocumento();
        $where = [
            new DataBaseWhere('tipodoc', $docType),
            new DataBaseWhere('codserie', $codSerie),
            new DataBaseWhere('idempresa', $idempresa),
            new DataBaseWhere('codejercicio', $codejercicio)
        ];
        $sequence = $sequenceModel->all($where, [], 0, 0)[0] ?? null;
        if (null === $sequence) {
            return;
        }

        $pattern = (string)$sequence->patron;
        if (false !== strpos($pattern, '{ANYO') || false !== strpos($pattern, '{EJE')) {
            return;
        }

        $newPattern = preg_replace('/\\/(\\d{2})(?!\\d)/', '/{ANYO2}', $pattern);
        if ($newPattern === $pattern) {
            return;
        }

        $sequence->patron = $newPattern;
        $sequence->save();
    }

    private static function formatModelErrors($model): string
    {
        if (!is_object($model)) {
            return '';
        }

        try {
            if (method_exists($model, 'getErrors')) {
                $errors = $model->getErrors();
            } elseif (property_exists($model, 'errors')) {
                $errors = $model->errors;
            } else {
                return '';
            }
        } catch (\Throwable $exception) {
            return $exception->getMessage();
        }

        if (empty($errors)) {
            return '';
        }

        return is_string($errors) ? $errors : print_r($errors, true);
    }

    public static function ensureAlbaranCajaTable(): void
    {
        if (self::$albaranCajaTableReady) {
            return;
        }

        try {
            $model = new DixAlbaranCaja();
            $model->install();
            self::$albaranCajaTableReady = true;
        } catch (\Throwable $exception) {
            error_log('Unable to ensure dix_albaranes_caja table: ' . $exception->getMessage());
        }
    }

    public static function ensurePedidoCajaTable(): void
    {
        if (self::$pedidoCajaTableReady) {
            return;
        }

        try {
            $model = new DixPedidoCaja();
            $model->install();
            self::$pedidoCajaTableReady = true;
        } catch (\Throwable $exception) {
            error_log('Unable to ensure dix_pedidos_caja table: ' . $exception->getMessage());
        }
    }

    private static function resolveCurrentCajaId(): ?int
    {
        if (PHP_SESSION_NONE === session_status()) {
            session_start();
        }

        if (!empty($_SESSION['idcaja'])) {
            return (int)$_SESSION['idcaja'];
        }

        $caja = new DixTPVCaja();
        $abiertas = $caja->all([new DataBaseWhere('fechahoracierre', null)], ['idcaja' => 'DESC'], 0, 1);
        if (!empty($abiertas) && !empty($abiertas[0]->idcaja)) {
            $_SESSION['idcaja'] = (int)$abiertas[0]->idcaja;
            return (int)$abiertas[0]->idcaja;
        }

        return null;
    }
}
