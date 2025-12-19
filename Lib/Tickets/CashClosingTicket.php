<?php

namespace FacturaScripts\Plugins\DixTPV\Lib\Tickets;

use FacturaScripts\Core\Template\ModelClass;
use FacturaScripts\Core\Tools;
use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Dinamic\Model\Agente;
use FacturaScripts\Dinamic\Model\DixFormasPagoResumen;
use FacturaScripts\Dinamic\Model\DixListaFin;
use FacturaScripts\Dinamic\Model\DixProductosResumen;
use FacturaScripts\Dinamic\Model\DixTerminal;
use FacturaScripts\Dinamic\Model\DixTipoIVAResumen;
use FacturaScripts\Dinamic\Model\Empresa;
use FacturaScripts\Dinamic\Model\FormaPago;
use FacturaScripts\Dinamic\Model\Impuesto;
use FacturaScripts\Dinamic\Model\Ticket;
use FacturaScripts\Dinamic\Model\TicketPrinter;
use FacturaScripts\Dinamic\Model\User;
use FacturaScripts\Dinamic\Model\DixTPVCaja;
use FacturaScripts\Dinamic\Model\Producto;
use FacturaScripts\Dinamic\Model\FacturaCliente;
use FacturaScripts\Dinamic\Model\LineaFacturaCliente;
use FacturaScripts\Plugins\DixTPV\Model\DixAlbaranCaja;
use FacturaScripts\Plugins\DixTPV\Model\DixPedidoCaja;
use FacturaScripts\Plugins\DixTPV\Lib\UtilsTPV;
use FacturaScripts\Plugins\Tickets\Lib\Tickets\BaseTicket;
use Mike42\Escpos\Printer;

/**
 * Cash closing ticket compatible con BaseTicket y SendTicket
 */
final class CashClosingTicket extends BaseTicket
{
    /** @var array<string, float|null> */
    private static $productPriceCache = [];

    public static function print(ModelClass $model, TicketPrinter $printer, User $user, Agente $agent = null): bool
    {
        static::init();
        static::setOpenDrawer(false);

        $ticket = new Ticket();
        $ticket->idprinter = $printer->id;
        $ticket->nick = $user->nick;
        $title = static::$i18n->trans('cash-closing-title') . ' ' . ($model->nombre ?: ('#' . ($model->idcaja ?? $model->primaryColumnValue())));
        $ticket->title = $title;

        static::setHeader($model, $printer, $title);
        static::setBodyForCashClosing($model, $printer);
        static::setFooterForCashClosing($model, $printer);

        $ticket->body = static::getBody();
        $ticket->bytes = base64_decode($ticket->body);
        $ticket->base64 = true;
        $ticket->appversion = 1;

        if ($agent) {
            $ticket->codagente = $agent->codagente;
        }

        $ticket->newCode();

        return $ticket->save();
    }



    protected static function resolveCompany($model): Empresa
    {
        if (method_exists($model, 'getCompany')) {
            $company = $model->getCompany();
            if ($company instanceof Empresa) {
                return $company;
            }
        }

        $company = new Empresa();
        $companyId = Tools::settings('default', 'idempresa');
        if (!empty($companyId)) {
            $company->loadFromCode($companyId);
        }

        return $company;
    }

    protected static function setHeader(ModelClass $model, TicketPrinter $printer, string $title): void
    {
        if ($printer->print_stored_logo) {
            static::$escpos->setJustification(Printer::JUSTIFY_CENTER);
            static::$connector->write("\x1Cp\x01\x00\x00");
            static::$escpos->feed();
        }

        $company = static::resolveCompany($model);

        static::$escpos->setTextSize($printer->title_font_size, $printer->title_font_size);

        if ($printer->print_comp_shortname && !empty($company->nombrecorto)) {
            static::$escpos->text(static::sanitize($company->nombrecorto) . "\n");
            static::$escpos->setTextSize($printer->head_font_size, $printer->head_font_size);
            static::$escpos->text(static::sanitize($company->nombre) . "\n");
        } else {
            static::$escpos->text(static::sanitize($company->nombre) . "\n");
            static::$escpos->setTextSize($printer->head_font_size, $printer->head_font_size);
        }

        static::$escpos->setJustification();

        if (!empty($company->direccion)) {
            static::$escpos->text(static::sanitize($company->direccion) . "\n");
        }
        $postal = trim(($company->codpostal ?? '') . ', ' . ($company->ciudad ?? ''));
        if (',' !== $postal) {
            static::$escpos->text(static::sanitize('CP: ' . $postal) . "\n");
        }
        if (!empty($company->tipoidfiscal) && !empty($company->cifnif)) {
            static::$escpos->text(static::sanitize($company->tipoidfiscal . ': ' . $company->cifnif) . "\n");
        }

        if ($printer->print_comp_tlf) {
            $phones = array_filter([$company->telefono1 ?? '', $company->telefono2 ?? '']);
            if (!empty($phones)) {
                static::$escpos->text(static::sanitize(implode(' / ', $phones)) . "\n");
            }
        }

        static::$escpos->text(static::sanitize($title) . "\n");

        static::setHeaderTPV($model, $printer);

        if (in_array($model->modelClassName(), ['PresupuestoCliente', 'PedidoCliente', 'AlbaranCliente', 'FacturaCliente'])) {
            static::$escpos->text(static::sanitize(static::$i18n->trans('date') . ': ' . ($model->fecha ?? '') . ' ' . ($model->hora ?? '')) . "\n");
            static::$escpos->text(static::sanitize(static::$i18n->trans('customer') . ': ' . ($model->nombrecliente ?? '')) . "\n\n");
        }

        if ($printer->head) {
            static::$escpos->setJustification(Printer::JUSTIFY_CENTER);
            static::$escpos->text(static::sanitize($printer->head) . "\n\n");
            static::$escpos->setJustification();
        }
    }

    protected static function setBodyForCashClosing(ModelClass $model, TicketPrinter $printer): void
    {
        // Nos aseguramos que siempre hay tamaño de letra configurado
        static::$escpos->setTextSize($printer->font_size, $printer->font_size);

        // Cabecera del resumen
        static::printSectionTitle(static::$i18n->trans('cash-summary-title'), $printer);

        $terminalName = '';
        if (!empty($model->pertenenciaterminal)) {
            $terminal = new DixTerminal();
            if ($terminal->loadFromCode($model->pertenenciaterminal)) {
                $terminalName = $terminal->nametpv ?: $terminal->idtpv;
            }
        }

        $openingUser = static::getAgentName($model->camareroini ?? null);
        $closingUser = static::getAgentName($model->camarerofin ?? null);

        static::printKeyValue($printer, static::$i18n->trans('cash-register-name'), $model->nombre ?: ('#' . ($model->idcaja ?? $model->primaryColumnValue())));
        if ($terminalName) {
            static::printKeyValue($printer, static::$i18n->trans('terminal'), $terminalName);
        }
        if ($openingUser) {
            static::printKeyValue($printer, static::$i18n->trans('cash-opened-by'), $openingUser);
        }
        if (!empty($model->fechahoraapertura)) {
            static::printKeyValue($printer, static::$i18n->trans('opening-date'), Tools::dateTime($model->fechahoraapertura));
        }

        if ($closingUser) {
            static::printKeyValue($printer, static::$i18n->trans('cash-closed-by'), $closingUser);
        }
        if (!empty($model->fechahoracierre)) {
            static::printKeyValue($printer, static::$i18n->trans('closing-date'), Tools::dateTime($model->fechahoracierre));
        }

        static::$escpos->text($printer->getDashLine() . "\n");

        static::printMoneyValue($printer, static::$i18n->trans('cash-opening-amount'), (float)($model->dineroini ?? 0.0));

        $specialPayments = static::getSpecialPaymentTotals($model);
        $albaranSummary = static::getAlbaranSummary($model);
        $pedidoSummary = static::getPedidoSummary($model);
        $cashTotal = (float)($model->dinerofin ?? 0.0);
        $cardTotal = max(0.0, (float)($model->dinerocredito ?? 0.0) - $specialPayments['paypal'] - $specialPayments['transfer']);

        static::printMoneyValue($printer, static::$i18n->trans('cash-sales-total'), $cashTotal);
        static::printMoneyValue($printer, static::$i18n->trans('cash-credit-total'), $cardTotal);
        if ($specialPayments['paypal'] > 0.0) {
            static::printMoneyValue($printer, static::$i18n->trans('cash-paypal-total'), $specialPayments['paypal']);
        }
        if ($specialPayments['transfer'] > 0.0) {
            static::printMoneyValue($printer, static::$i18n->trans('cash-transfer-total'), $specialPayments['transfer']);
        }
        if ($albaranSummary['enabled']) {
            static::printMoneyValue($printer, 'Total albarán', $albaranSummary['total']);
        }
        if ($pedidoSummary['enabled']) {
            static::printMoneyValue($printer, 'Total pedidos', $pedidoSummary['total']);
        }
        $counted = (float)($model->dinerocierre ?? 0.0);
        static::printMoneyValue($printer, static::$i18n->trans('cash-closing-counted'), $counted);

        $expected = (float)($model->dinerofin ?? 0.0) + (float)($model->dinerocredito ?? 0.0) + (float)($model->dineroini ?? 0.0);
        static::printMoneyValue($printer, static::$i18n->trans('cash-expected-amount'), $expected);

        $difference = isset($model->descuadre) && $model->descuadre !== null
            ? (float)$model->descuadre
            : ($counted - $expected);

        static::printMoneyValue($printer, static::$i18n->trans('cash-difference'), $difference);

        static::$escpos->text("\n");

        // Desglose por formas de pago
        static::printPaymentBreakdownForCash($model, $printer);

        // Desglose impuestos / productos / historial de cierres
        static::printTaxBreakdownForCash($model, $printer);
        static::printProductsBreakdownForCash($model, $printer);
        static::printClosureHistoryForCash($model, $printer);
        if ($pedidoSummary['enabled']) {
            static::printPedidoHistoryForCash($pedidoSummary['records'], $printer);
        }
        if ($albaranSummary['enabled']) {
            static::printAlbaranHistoryForCash($albaranSummary['records'], $printer);
        }
    }

    protected static function setFooterForCashClosing(ModelClass $model, TicketPrinter $printer): void
    {
        // Reutilizamos el setFooter estándar de BaseTicket (tamaño de footer)
        static::$escpos->setTextSize($printer->footer_font_size, $printer->footer_font_size);
        if ($printer->footer) {
            static::$escpos->setJustification(Printer::JUSTIFY_CENTER);
            static::$escpos->text("\n" . static::sanitize($printer->footer) . "\n");
            static::$escpos->setJustification(Printer::JUSTIFY_LEFT);
        }
    }

    private static function printPaymentBreakdownForCash(ModelClass $model, TicketPrinter $printer): void
    {
        $items = (new DixFormasPagoResumen())->all([new \FacturaScripts\Core\Base\DataBase\DataBaseWhere('idcaja', $model->idcaja)], ['total' => 'DESC']);
        static::printSectionTitle(static::$i18n->trans('cash-payment-summary'), $printer);
        if (empty($items)) {
            static::printMoneyValue($printer, static::$i18n->trans('total'), 0.0);
            static::$escpos->text("\n");
            return;
        }

        foreach ($items as $item) {
            $payment = new FormaPago();
            $label = $item->codpago ?? '';
            if ($payment->loadFromCode($item->codpago)) {
                $label = $payment->descripcion ?: $item->codpago;
            }
            static::printMoneyValue($printer, $label, (float)($item->total ?? 0.0));
        }

        static::$escpos->text("\n");
    }

    private static function printTaxBreakdownForCash(ModelClass $model, TicketPrinter $printer): void
    {
        $items = static::getTaxSummaryForCash($model);
        if (empty($items)) {
            $items = (new DixTipoIVAResumen())->all([new \FacturaScripts\Core\Base\DataBase\DataBaseWhere('idcaja', $model->idcaja)], ['total' => 'DESC']);
        }

        static::printSectionTitle(static::$i18n->trans('tax-breakdown-title'), $printer);
        if (empty($items)) {
            static::printMoneyValue($printer, static::$i18n->trans('total'), 0.0);
            static::$escpos->text("\n");
            return;
        }

        foreach ($items as $item) {
            if (is_array($item)) {
                $code = $item['codimpuesto'] ?? '';
                $netAmount = (float)($item['base_total'] ?? 0.0);
                $taxAmount = (float)($item['tax_total'] ?? 0.0);
                $grossAmount = (float)($item['total'] ?? ($netAmount + $taxAmount));
            } else {
                $code = $item->codimpuesto;
                $netAmount = (float)($item->base_total ?? 0.0);
                $taxAmount = (float)($item->tax_total ?? 0.0);
                $grossAmount = (float)($item->total ?? ($netAmount + $taxAmount));
            }

            $tax = new Impuesto();
            $label = $code ?: static::$i18n->trans('tax-rate');
            if ($code && $tax->loadFromCode($code)) {
                $label = trim($tax->descripcion);
            }

            $netLabel = sprintf('%s (%s)', $label, static::$i18n->trans('dixtpv-tax-summary-base'));
            $taxLabel = sprintf('%s (%s)', $label, static::$i18n->trans('dixtpv-tax-summary-tax'));
            $grossLabel = sprintf('%s (%s)', $label, static::$i18n->trans('dixtpv-tax-summary-total'));

            static::printMoneyValue($printer, $netLabel, $netAmount);
            static::printMoneyValue($printer, $taxLabel, $taxAmount);
            static::printMoneyValue($printer, $grossLabel, $grossAmount);
        }

        static::$escpos->text("\n");
    }

    private static function printProductsBreakdownForCash(ModelClass $model, TicketPrinter $printer): void
    {
        $items = (new DixProductosResumen())->all([new \FacturaScripts\Core\Base\DataBase\DataBaseWhere('idcaja', $model->idcaja)], ['total' => 'DESC']);
        static::printSectionTitle(static::$i18n->trans('cash-products-summary'), $printer);
        if (empty($items)) {
            static::printMoneyValue($printer, static::$i18n->trans('total'), 0.0);
            static::$escpos->text("\n");
            return;
        }

        static::printListOrFallback($printer, $items, function ($item, $printer) {
            $description = $item->descripcion ?: ($item->referencia ?: $item->claveprod ?? static::$i18n->trans('cash-product-unknown'));
            $unitsValue = (float)($item->unidades ?? 0.0);
            $unitPrice = static::getProductPriceWithTaxes($item->referencia);
            if ($unitPrice === null) {
                $unitPrice = $unitsValue > 0 ? (float)$item->total / $unitsValue : (float)$item->total;
            }
            $totalWithTax = $unitPrice * $unitsValue;

            $units = Tools::number($unitsValue);
            $amount = Tools::number($totalWithTax);
            $lineLength = max(20, $printer->linelen);
            $amountWidth = 12;
            $unitsWidth = 6;
            $labelWidth = max(8, $lineLength - $amountWidth - $unitsWidth - 2);

            $labelText = static::fitText($description, $labelWidth, false);
            $unitsText = static::fitText($units, $unitsWidth, true);
            $amountText = static::fitText($amount, $amountWidth, true);

            static::$escpos->text($labelText . ' ' . $unitsText . ' ' . $amountText . "\n");
        });
        static::$escpos->text("\n");
    }

    private static function printClosureHistoryForCash(ModelClass $model, TicketPrinter $printer): void
    {
        $items = (new DixListaFin())->all([new \FacturaScripts\Core\Base\DataBase\DataBaseWhere('idcaja', $model->idcaja)], ['idlista' => 'DESC']);
        static::printSectionTitle(static::$i18n->trans('cash-history-summary'), $printer);
        static::printListOrFallback($printer, $items, function ($item, $printer) {
            $labelParts = [];
            if (!empty($item->codigofactura)) {
                $labelParts[] = static::$i18n->trans('invoice') . ' ' . $item->codigofactura;
            }
            if (!empty($item->codpago)) {
                $labelParts[] = $item->codpago;
            }
            $label = implode(' · ', array_filter($labelParts));
            static::printMoneyValue($printer, $label ?: ('#' . $item->idlista), (float)($item->total ?? 0.0));
        });
    }

    private static function printAlbaranHistoryForCash(array $items, TicketPrinter $printer): void
    {
        static::printSectionTitle('Historial de albaranes', $printer);
        if (empty($items)) {
            static::$escpos->text(static::sanitize('No hay albaranes registrados.') . "\n\n");
            return;
        }

        $limit = 25;
        $counter = 0;
        foreach ($items as $item) {
            $labelParts = [];
            if (!empty($item->codigo)) {
                $labelParts[] = 'Albarán ' . $item->codigo;
            }
            if (!empty($item->codpago)) {
                $labelParts[] = $item->codpago;
            }
            $label = implode(' · ', array_filter($labelParts));
            static::printMoneyValue($printer, $label ?: ('#' . $item->idalbaran), (float)($item->total ?? 0.0));
            $counter++;
            if ($counter >= $limit) {
                break;
            }
        }
        static::$escpos->text("\n");
    }

    private static function printPedidoHistoryForCash(array $items, TicketPrinter $printer): void
    {
        static::printSectionTitle('Historial de pedidos', $printer);
        if (empty($items)) {
            static::$escpos->text(static::sanitize('No hay pedidos registrados.') . "\n\n");
            return;
        }

        $limit = 25;
        $counter = 0;
        foreach ($items as $item) {
            $labelParts = [];
            if (!empty($item->codigo)) {
                $labelParts[] = 'Pedido ' . $item->codigo;
            }
            if (!empty($item->codpago)) {
                $labelParts[] = $item->codpago;
            }
            $label = implode(' · ', array_filter($labelParts));
            static::printMoneyValue($printer, $label ?: ('#' . $item->idpedido), (float)($item->total ?? 0.0));
            $counter++;
            if ($counter >= $limit) {
                break;
            }
        }
        static::$escpos->text("\n");
    }

    // reutiliza helpers de BaseTicket
    private static function printSectionTitle(string $title, TicketPrinter $printer): void
    {
        static::$escpos->text($printer->getDashLine() . "\n");
        static::$escpos->setJustification(Printer::JUSTIFY_CENTER);
        static::$escpos->text(static::sanitize(mb_strtoupper($title, 'UTF-8')) . "\n");
        static::$escpos->setJustification(Printer::JUSTIFY_LEFT);
    }

    private static function printListOrFallback(TicketPrinter $printer, array $items, callable $printerCallback): void
    {
        if (empty($items)) {
            $message = static::translate('not-data-available', 'No hay datos disponibles.');
            static::$escpos->text(static::sanitize($message) . "\n\n");
            return;
        }

        foreach ($items as $item) {
            $printerCallback($item, $printer);
        }

        static::$escpos->text("\n");
    }

    private static function printKeyValue(TicketPrinter $printer, string $label, string $value): void
    {
        $lineLength = max(20, $printer->linelen);
        $valueWidth = min(max(10, (int)floor($lineLength * 0.35)), $lineLength - 8);
        $labelWidth = $lineLength - $valueWidth - 1;

        $labelText = static::fitText($label, $labelWidth, false);
        $valueText = static::fitText($value, $valueWidth, true);

        static::$escpos->text($labelText . ' ' . $valueText . "\n");
    }

    private static function printMoneyValue(TicketPrinter $printer, string $label, float $amount): void
    {
        static::printKeyValue($printer, $label, Tools::number($amount));
    }

    private static function getAgentName($agentCode): string
    {
        if (empty($agentCode)) {
            return '';
        }

        $agent = new Agente();
        if ($agent->loadFromCode($agentCode)) {
            return trim($agent->nombre);
        }

        return '';
    }

    private static function fitText(string $text, int $width, bool $alignRight): string
    {
        $clean = static::sanitize($text);
        if ($width <= 0) {
            return $clean;
        }

        $strWidth = function_exists('mb_strwidth') ? mb_strwidth($clean, 'UTF-8') : strlen($clean);
        if ($strWidth > $width) {
            if (function_exists('mb_strimwidth')) {
                $clean = mb_strimwidth($clean, 0, $width, '', 'UTF-8');
                $strWidth = function_exists('mb_strwidth') ? mb_strwidth($clean, 'UTF-8') : strlen($clean);
            } else {
                $clean = substr($clean, 0, $width);
                $strWidth = strlen($clean);
            }
        }

        $padding = max(0, $width - $strWidth);
        if ($alignRight) {
            return str_repeat(' ', $padding) . $clean;
        }

        return $clean . str_repeat(' ', $padding);
    }

    private static function getProductPriceWithTaxes($reference)
    {
        if (empty($reference)) {
            return null;
        }

        if (!array_key_exists($reference, static::$productPriceCache)) {
            $product = new Producto();
            if ($product->loadWhere([new \FacturaScripts\Core\Base\DataBase\DataBaseWhere('referencia', $reference)])) {
                static::$productPriceCache[$reference] = $product->priceWithTax();
            } else {
                static::$productPriceCache[$reference] = null;
            }
        }

        return static::$productPriceCache[$reference];
    }

    private static function translate(string $key, string $fallback = ''): string
    {
        try {
            $text = Tools::lang()->trans($key);
        } catch (\Throwable $exception) {
            $text = $key;
        }

        if (empty($text) || $text === $key) {
            return $fallback !== '' ? $fallback : $key;
        }

        return $text;
    }

    private static function getAlbaranSummary(ModelClass $model): array
    {
        $summary = [
            'enabled' => false,
            'total' => 0.0,
            'records' => []
        ];

        if (false === $model instanceof DixTPVCaja || empty($model->idcaja)) {
            return $summary;
        }

        $summary['enabled'] = true;

        UtilsTPV::ensureAlbaranCajaTable();
        $registro = new DixAlbaranCaja();
        $where = [
            new DataBaseWhere('idcaja', $model->idcaja)
        ];
        $records = $registro->all($where, ['idalbarancaja' => 'DESC'], 0, 0);

        $total = 0.0;
        foreach ($records as $record) {
            $total += (float)($record->total ?? 0.0);
        }

        $summary['total'] = $total;
        $summary['records'] = $records;

        return $summary;
    }

    private static function getPedidoSummary(ModelClass $model): array
    {
        $summary = [
            'enabled' => false,
            'total' => 0.0,
            'records' => []
        ];

        if (false === $model instanceof DixTPVCaja || empty($model->idcaja)) {
            return $summary;
        }

        $summary['enabled'] = true;

        UtilsTPV::ensurePedidoCajaTable();
        $registro = new DixPedidoCaja();
        $where = [
            new DataBaseWhere('idcaja', $model->idcaja),
        ];
        $records = $registro->all($where, ['idpedidocaja' => 'DESC'], 0, 0);

        $total = 0.0;
        foreach ($records as $record) {
            $total += (float)($record->total ?? 0.0);
        }

        $summary['total'] = $total;
        $summary['records'] = $records;

        return $summary;
    }

    private static function getSpecialPaymentTotals(ModelClass $model): array
    {
        $totals = ['paypal' => 0.0, 'transfer' => 0.0];
        if (false === $model instanceof DixTPVCaja || empty($model->idcaja)) {
            return $totals;
        }

        $items = (new DixFormasPagoResumen())->all([new \FacturaScripts\Core\Base\DataBase\DataBaseWhere('idcaja', $model->idcaja)], []);
        if (empty($items)) {
            return $totals;
        }

        $payment = new FormaPago();
        $labelCache = [];
        foreach ($items as $item) {
            $code = $item->codpago ?? '';
            $labelCache[$code] = $labelCache[$code] ?? static::normalizePaymentLabel($payment, $code);
            $haystack = $labelCache[$code];

            if (false !== strpos($haystack, 'paypal')) {
                $totals['paypal'] += (float)($item->total ?? 0.0);
                continue;
            }

            if (false !== strpos($haystack, 'transfer')) {
                $totals['transfer'] += (float)($item->total ?? 0.0);
            }
        }

        return $totals;
    }

    private static function normalizePaymentLabel(FormaPago $payment, string $code): string
    {
        $label = $code;
        if (!empty($code) && $payment->loadFromCode($code)) {
            $label = $payment->descripcion ?: $code;
        }

        $normalized = strtolower(trim($label . ' ' . $code));
        $normalized = str_replace(['á', 'é', 'í', 'ó', 'ú'], ['a', 'e', 'i', 'o', 'u'], $normalized);
        return $normalized;
    }

    private static function getTaxSummaryForCash(ModelClass $model): array
    {
        if (false === $model instanceof DixTPVCaja || empty($model->idcaja)) {
            return [];
        }

        $invoiceModel = new FacturaCliente();
        $invoices = $invoiceModel->all([new \FacturaScripts\Core\Base\DataBase\DataBaseWhere('idcaja', $model->idcaja)], [], 0, 0);
        if (empty($invoices)) {
            return [];
        }

        $lineModel = new LineaFacturaCliente();
        $summary = [];
        foreach ($invoices as $invoice) {
            $lines = $lineModel->all([new \FacturaScripts\Core\Base\DataBase\DataBaseWhere('idfactura', $invoice->idfactura)], [], 0, 0);
            foreach ($lines as $line) {
                $code = $line->codimpuesto ?: '';
                $key = $code ?: 'NO-TAX';
                $net = (float)($line->pvptotal ?? 0.0);
                if (0.0 === $net) {
                    continue;
                }
                $ivaPct = (float)($line->iva ?? 0.0);
                $recargoPct = (float)($line->recargo ?? 0.0);
                $taxAmount = $net * (($ivaPct + $recargoPct) / 100.0);
                $total = $net + $taxAmount;

                if (!isset($summary[$key])) {
                    $summary[$key] = [
                        'codimpuesto' => $code,
                        'total' => 0.0,
                        'base_total' => 0.0,
                        'tax_total' => 0.0,
                    ];
                }
                $summary[$key]['total'] += $total;
                $summary[$key]['base_total'] += $net;
                $summary[$key]['tax_total'] += $taxAmount;
            }
        }

        return array_values($summary);
    }
}
