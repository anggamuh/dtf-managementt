<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\CustomerInvoice;
use Barryvdh\DomPDF\Facade\Pdf;

class InvoiceController extends Controller
{
    public function show(CustomerInvoice $invoice){$this->guard($invoice);return view('customer.invoices.show',compact('invoice'));}
    public function pdf(CustomerInvoice $invoice){$this->guard($invoice);$invoice->load(['order','branch','user']);return Pdf::loadView('customer.invoices.pdf',compact('invoice'))->setPaper('a4')->download($invoice->invoice_number.'.pdf');}
    private function guard(CustomerInvoice $invoice):void{abort_unless($invoice->user_id===auth()->id(),403);}
}
