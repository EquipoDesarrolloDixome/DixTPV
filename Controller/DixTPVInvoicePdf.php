<?php

namespace FacturaScripts\Plugins\DixTPV\Controller;

use FacturaScripts\Core\Base\Controller;
use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Core\Response;
use FacturaScripts\Core\Tools;
use FacturaScripts\Dinamic\Lib\ExportManager;
use FacturaScripts\Dinamic\Model\FacturaCliente;

class DixTPVInvoicePdf extends Controller
{
    public function getPageData(): array
    {
        $data = parent::getPageData();
        $data['title'] = 'invoice';
        $data['icon'] = 'fa-solid fa-file-pdf';
        $data['showonmenu'] = false;
        return $data;
    }

    public function privateCore(&$response, $user, $permissions)
    {
        parent::privateCore($response, $user, $permissions);
        $this->setTemplate(false);

        $invoiceCode = trim((string)$this->request->get('code', ''));
        if ($invoiceCode === '') {
            $this->response->setHttpCode(Response::HTTP_BAD_REQUEST);
            $this->response->setContent('Missing invoice code.');
            return;
        }

        $invoice = new FacturaCliente();
        $where = [new DataBaseWhere('codigo', $invoiceCode)];
        if (false === $invoice->loadFromCode('', $where)) {
            $this->response->setHttpCode(Response::HTTP_NOT_FOUND);
            $this->response->setContent('Invoice not found.');
            return;
        }

        $lang = $invoice->getSubject()->langcode ?? Tools::settings('default', 'lang', 'es_ES');
        $title = Tools::lang($lang)->trans('invoice') . ' ' . ($invoice->codigo ?? $invoice->idfactura);

        $exportManager = new ExportManager();
        $exportManager->newDoc('PDF', $title, 0, $lang);
        $exportManager->addBusinessDocPage($invoice);
        $exportManager->show($this->response);
    }
}
