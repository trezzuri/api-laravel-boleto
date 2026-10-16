<?php

namespace App\Http\Requests;

use App\Console\ValidaCPFCNPJ;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use \Eduardokum\LaravelBoleto\Pessoa;
use \Eduardokum\LaravelBoleto\Util;

class BoletoRequest extends FormRequest
{
    // Verifica permissão para request
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Verifica regras para a request
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            "banco" => "required|string",
            "dados.agencia" => "required|string",
            "dados.conta"  => "required|string",
            "dados.carteira" => "required|string",
            "dados.numero" => "required|integer",
            "dados.numeroDocumento" => "required|string",
            "dados.dataVencimento" => "required|date",
            "dados.valor" => "required|decimal:0,2",
            "dados.aceite" => "required|boolean",
            "dados.especieDoc" => "required|string",
            "dados.beneficiario.nome" => "required|string",
            "dados.beneficiario.endereco" => "required|string",
            "dados.beneficiario.cep" => "required|string",
            "dados.beneficiario.uf" => "required|string",
            "dados.beneficiario.cidade" => "required|string",
            "dados.beneficiario.documento" => "required|string",
            "dados.pagador.nome" => "required|string",
            "dados.pagador.endereco" => "required|string",
            "dados.pagador.cep" => "required|string",
            "dados.pagador.uf" => "required|string",
            "dados.pagador.cidade" => "required|string",
            "dados.pagador.documento" => "required|string",
            "dados.convenio" => "required_if:banco,001|string",
			"dados.variacaoCarteira" => "required_if:banco,001|nullable|string",
			"dados.codigoCliente" => "nullable|string",
			"dados.operacao" => "nullable|string",
			"dados.posto" => "nullable|string",
			"dados.modalidadeCarteira" => "nullable|string",
			"dados.range" => "nullable|string",
			"dados.byte" => "nullable|string",
        ];
    }

    // Valida dados 
    public function after(): array
    {
        return [
            function (Validator $validator) {  

                // Somente realiza as validações seguintes se passou nas rules
                if ($validator->errors()->isNotEmpty()) 
                    return;

                // Verifica se o banco está implementado
                $banco = $this->input('banco');
                try {
                    Util::getBancoClass($banco);
                } catch (\Throwable $e) {
                    $validator->errors()->add(
                        'banco',
                        'Banco não implementado.'
                    );
                    return;
                }

                // Valida se o beneficiario pode ser convertido em objeto Pessoa
                $beneficiario = $this->input('dados')['beneficiario'];
                try {
                    new Pessoa($beneficiario);
                } catch (\Throwable $e) {
                    $validator->errors()->add(
                        'dados.beneficiario',
                        'Dados do beneficiário inválidos.'
                    );
                    return;
                }

                // Valida se o pagador pode ser convertido em objeto Pessoa
                $pagador = $this->input('dados')['pagador'];
                try {
                    new Pessoa($pagador);
                } catch (\Throwable $e) {
                    $validator->errors()->add(
                        'dados.pagador',
                        'Dados do pagador inválidos.'
                    );
                    return;
                }
                    
                // Valida o documento do beneficiario
                $doc1 = new ValidaCPFCNPJ($beneficiario['documento']);
                if (!$doc1->valida()) {
                    $validator->errors()->add(
                        'dados.beneficiario.documento',
                        'Documento do beneficiário (CPF ou CNPJ) inválido'
                    );
                    return;
                }
                    
                // Valida o documento do pagador
                $doc2 = new ValidaCPFCNPJ($pagador['documento']);
                if (!$doc2->valida()) {
                    $validator->errors()->add(
                        'dados.pagador.documento',
                        'Documento do pagador (CPF ou CNPJ) inválido'
                    );
                    return;
                }
            }
        ];
    }

    // Retorna json com erros
    public function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(response()->json([
            "status" => 400,
            "mensagem" => "Erro na validação de dados",
            "erros" => $validator->errors()
        ], 400));
    }
}
