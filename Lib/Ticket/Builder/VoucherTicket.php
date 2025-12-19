<?php

namespace FacturaScripts\Plugins\DixTPV\Lib\Ticket\Builder;

use FacturaScripts\Core\Tools;
use FacturaScripts\Dinamic\Model\Empresa;
use FacturaScripts\Dinamic\Model\User;
use FacturaScripts\Dinamic\Model\Agente;
use FacturaScripts\Dinamic\Model\Ticket;
use FacturaScripts\Plugins\Tickets\Lib\Tickets\BaseTicket;
use FacturaScripts\Dinamic\Model\TicketPrinter;
use Mike42\Escpos\Printer;

class VoucherTicket extends BaseTicket
{
    /** @var string */
    protected static $lastTicketBody = '';

    /**
     * Recibe un $model que solo tiene ->lines (un array)
     */
    public static function print($model, TicketPrinter $printer, User $user, ?Agente $agent = null): bool
    {
        static::init();
        static::setOpenDrawer(false);
        static::setHeaderCustom($printer);
        static::setBodyCustom($model, $printer);
        static::setFooterCustom($printer);

        $ticket = new Ticket();
        $ticket->idprinter = $printer->id;
        $ticket->nick = $user->nick;
        $ticket->title = 'Voucher ' . Tools::dateTime();
        $ticket->body = static::getBody();
        static::$lastTicketBody = $ticket->body;
        $ticket->base64 = true;
        $ticket->appversion = 1;

        if ($agent) {
            $ticket->codagente = $agent->codagente;
        }

        return $ticket->save();
    }

    /**
     * Returns the base64 encoded ESC/POS data of the last printed voucher.
     */
    public static function getLastTicketBody(): string
    {
        return (string) static::$lastTicketBody;
    }


    protected static function setHeaderCustom(TicketPrinter $printer): void
    {
        $company = new Empresa();
        $company->loadFromCode(Tools::settings('default', 'idempresa'));

        $separator = $printer->getDashLine() . "\n";

        static::$escpos->setJustification(Printer::JUSTIFY_CENTER);
        static::$escpos->setTextSize(2, 2);
        static::$escpos->text(static::sanitize($company->nombrecorto) . "\n");

        static::$escpos->setTextSize(1, 1);
        static::$escpos->text(static::sanitize($company->nombre) . "\n");
        if (!empty($company->direccion)) {
            static::$escpos->text(static::sanitize($company->direccion) . "\n");
        }
        if (!empty($company->telefono1)) {
            static::$escpos->text(static::sanitize($company->telefono1) . "\n");
        }
        if (!empty($company->cifnif)) {
            static::$escpos->text(static::sanitize($company->cifnif) . "\n");
        }

        static::$escpos->text($separator);
        static::$escpos->text(static::sanitize('COMPROBANTE DE SERVICIO') . "\n");

        $fecha = new \DateTime();
        static::$escpos->text(static::sanitize($fecha->format('d-m-Y H:i')) . "\n");
        static::$escpos->text($separator);

        static::$escpos->setJustification(Printer::JUSTIFY_LEFT);
    }

    protected static function setBodyCustom($model, $printer): void
    {
        $lineLength = max(20, (int)$printer->linelen ?: 32);
        $separator = $printer->getDashLine() . "\n";
        $qtyWidth = 6;
        $priceWidth = 12;
        $totalWidth = 12;
        $headerWidth = $lineLength;

        static::$escpos->setTextSize(1, 1);

        static::$escpos->text(static::sanitize('ARTICULOS') . "\n");
        $header = static::alignText('Cant', $qtyWidth, true)
            . ' ' . static::alignText('P.Unit', $priceWidth, true)
            . ' ' . static::alignText('Importe', $totalWidth, true);
        static::$escpos->text(static::sanitize($header) . "\n");
        static::$escpos->text($separator);

        $totalItems = 0;
        $total = 0;

        foreach ($model->lines as $line) {

            $reference = trim((string)($line->referencia ?? ''));
            $description = trim((string)($line->descripcion ?? ''));
            $title = trim($reference . ' - ' . $description, ' -');
            static::$escpos->text(static::sanitize($title) . "\n");

            $unit = static::getLineUnitPriceWithTax($line);
            $qty  = (float)($line->cantidad ?? 0.0);
            $subtotal = $unit * $qty;

            $t1 = static::alignText(Tools::number($qty, 2), $qtyWidth, true);
            $t2 = static::alignText(static::sanitize(Tools::money($unit)), $priceWidth, true);
            $t3 = static::alignText(static::sanitize(Tools::money($subtotal)), $totalWidth, true);

            static::$escpos->text($t1 . ' ' . $t2 . ' ' . $t3 . "\n");

            $totalItems += $qty;
            $total += $subtotal;
        }

        static::$escpos->text($separator);
        static::$escpos->text(static::sanitize('Total articulos: ' . Tools::number($totalItems, 2)) . "\n");
        static::$escpos->text(static::sanitize('Total importe: ' . Tools::money($total)) . "\n");
        static::$escpos->text($separator);
    }

    protected static function setFooterCustom(TicketPrinter $printer): void
    {
        static::$escpos->setJustification(1);
        static::$escpos->text("\n" . static::sanitize('IMPUESTOS INCLUIDOS') . "\n\n");
        static::$escpos->setJustification();
    }

    private static function alignText(string $text, int $width, bool $alignRight): string
    {
        $clean = static::sanitize($text);
        $length = function_exists('mb_strwidth') ? mb_strwidth($clean, 'UTF-8') : strlen($clean);
        if ($length > $width && $width > 0) {
            $clean = function_exists('mb_strimwidth') ?
                mb_strimwidth($clean, 0, $width, '', 'UTF-8') :
                substr($clean, 0, $width);
            $length = function_exists('mb_strwidth') ? mb_strwidth($clean, 'UTF-8') : strlen($clean);
        }

        $padding = max(0, $width - $length);
        return $alignRight
            ? str_repeat(' ', $padding) . $clean
            : $clean . str_repeat(' ', $padding);
    }

    private static function getLineUnitPriceWithTax($line): float
    {
        $unit = (float)($line->pvpunitario ?? 0.0);
        $iva = (float)($line->iva ?? 0.0);
        $recargo = (float)($line->recargo ?? 0.0);
        return $unit * (1.0 + (($iva + $recargo) / 100.0));
    }
}
