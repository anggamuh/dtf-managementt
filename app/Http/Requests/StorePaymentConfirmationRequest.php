<?php
namespace App\Http\Requests;
use Illuminate\Foundation\Http\FormRequest;
class StorePaymentConfirmationRequest extends FormRequest {public function authorize():bool{return $this->route('order')&&$this->user()->can('view',$this->route('order'));}public function rules():array{return ['payment_account_id'=>'required|exists:payment_accounts,id','sender_name'=>'required|string|max:255','sender_bank'=>'required|string|max:100','transferred_at'=>'required|date|before_or_equal:today','amount'=>'required|numeric|min:1','proof'=>'required|file|mimes:jpg,jpeg,png|max:5120','note'=>'nullable|string|max:2000'];}}
