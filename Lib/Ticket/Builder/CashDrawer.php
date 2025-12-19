<?php

namespace FacturaScripts\Plugins\DixTPV\Lib\Ticket\Builder;

use FacturaScripts\Core\Model\Base\SalesDocument;

use FacturaScripts\Dinamic\Model\FormatoTicket;
use FacturaScripts\Plugins\PrintTicket\Lib\Ticket\Builder\AbstractTicketBuilder;
use Mike42\Escpos\PrintConnectors\DummyPrintConnector;
use Mike42\Escpos\PrintConnectors\PrintConnector;
use Mike42\Escpos\Printer;

class CashDrawer extends AbstractTicketBuilder
{
    /**
     * @var SalesDocument
     */
    protected $document;
    /**
     * @var PrintConnector
     */
    private $connector;

    protected $auxPrinter;

    /**
     * @var int
     */
    private $width;
    /*public function __construct($document, ?FormatoTicket $formato)
    {
        parent::__construct($formato);

        $this->document = $document;

        $this->connector = new DummyPrintConnector();
        $this->auxPrinter = new Printer($this->connector);
        $this->width = 80;
    }*



    /**
     * @return string
     */
    public function getResult(): string
    {
         $this->buildFooter();
        return $this->printer->getBuffer();

    }

    /**
     * Builds the ticket head
     */
    protected function buildHeader(): void
    {

    }

    /**
     * Builds the ticket body
     */
    protected function buildBody(): void
    {

    }


    /**
     * Builds the ticket foot
     */
    protected function buildFooter(): void
    {
        $this->printer->lineBreak();

    }
    public function getBuffer(): string
    {
       // $output = $this->connector->getData()??'';
        $this->connector->finalize();
       return $this->auxPrinter->close() ??'';

       // return $output;
    }
}