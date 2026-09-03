<?php
namespace App\Http\Requests;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
class StoreCustomerOrderRequest extends FormRequest {
    public function authorize(): bool { return $this->user()?->hasRole('Customer') ?? false; }
    protected function prepareForValidation(): void
    {
        if (! $this->filled('design_type')) {
            $this->merge(['design_type'=>$this->hasFile('design_file') ? ($this->filled('request_text') ? 'modify_uploaded_file' : 'ready_to_print') : 'request_design']);
        }
    }
    public function rules(): array { return [
        'branch_id'=>['required',Rule::exists('branches','id')->where('active',true)],
        'design_type'=>['required','in:ready_to_print,request_design,modify_uploaded_file'],
        'request_text'=>['nullable','string','max:5000',Rule::requiredIf(fn()=>in_array($this->input('design_type'),['request_design','modify_uploaded_file'],true))],
        'design_file'=>['nullable',Rule::requiredIf(fn()=>in_array($this->input('design_type'),['ready_to_print','modify_uploaded_file'],true)),'file','mimes:png','mimetypes:image/png','dimensions:min_width=1,min_height=1,max_width=20000,max_height=20000','max:10240'],
        'size'=>['required','string','max:100'],'quantity'=>['required','integer','min:1'],'payment_method'=>['required','in:payment_gateway,bank_transfer'],'notes'=>['nullable','string','max:2000'],
    ]; }
    public function messages(): array { return [
        'request_text.required_without'=>'Isi deskripsi desain atau unggah file PNG.',
        'design_file.required_without'=>'Unggah PNG jika deskripsi desain kosong.',
        'design_file.mimes'=>'File desain wajib berformat PNG.', 'design_file.max'=>'Ukuran PNG maksimal 10 MB.',
    ]; }
}
