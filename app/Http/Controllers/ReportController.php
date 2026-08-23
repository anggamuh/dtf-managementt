<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesBranch;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\Material;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    use ResolvesBranch;
    public function index(Request $r) { $branchId=$this->branchId($r);$type=$r->input('type','income');$from=$r->input('from',now()->startOfMonth()->toDateString());$to=$r->input('to',now()->endOfMonth()->toDateString());$rows=$this->rows($type,$branchId,$from,$to);return view('reports.index',compact('type','from','to','rows','branchId')+['branches'=>$this->branches()]); }
    public function export(Request $r,string $format) { $branchId=$this->branchId($r);$type=$r->input('type','income');$from=$r->input('from',now()->startOfMonth()->toDateString());$to=$r->input('to',now()->endOfMonth()->toDateString());$rows=$this->rows($type,$branchId,$from,$to);if($format==='xlsx') return \Maatwebsite\Excel\Facades\Excel::download(new \App\Exports\ReportExport($rows),"laporan-{$type}.xlsx");return \Barryvdh\DomPDF\Facade\Pdf::loadView('reports.pdf',compact('rows','type','from','to'))->download("laporan-{$type}.pdf"); }
    private function rows(string $type,int $branchId,string $from,string $to) {
        return match($type) {
            'expense' => Expense::whereBetween('date',[$from,$to])->when($branchId !== 0, fn($q)=> $q->where('branch_id',$branchId))->get()->map(fn($x)=>['date'=>$x->date->format('d/m/Y'),'name'=>$x->description,'amount'=>$x->amount,'status'=>$x->category]),
            'material' => Material::when($branchId !== 0, fn($q)=> $q->where('branch_id',$branchId))->get()->map(fn($x)=>['date'=>'-','name'=>$x->name,'amount'=>$x->stock*$x->price,'status'=>$x->unit]),
            default => Invoice::with('customer')->whereBetween('date',[$from,$to])->when($branchId !== 0, fn($q)=> $q->where('branch_id',$branchId))->get()->map(fn($x)=>['date'=>$x->date->format('d/m/Y'),'name'=>$x->customer->name,'amount'=>$x->paid,'status'=>$x->status]),
        };
    }
}
