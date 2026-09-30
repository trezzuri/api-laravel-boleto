<?php

namespace App\Http\Requests;

use App\Models\Boleto;
use App\Console\ValidaCPFCNPJ;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use \Eduardokum\LaravelBoleto\Pessoa;
use \Eduardokum\LaravelBoleto\Util;

class RemessaRequest extends FormRequest
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
            "layout" => "required|string|in:240,400",
            "boletos" => "required|array|min:1",
            "boletos.*" => "required|string|distinct",
            "dados.idRemessa" => "required|integer",
            "dados.agencia" => "required|string",
            "dados.conta"  => "required|string",
            "dados.carteira" => "required|string",
			"dados.convenio" => "required|string",
			"dados.variacaoCarteira" => "required_if:banco,001|string",
			"dados.codigoCliente" => "nullable|string",
			"dados.operacao" => "nullable|string",
			"dados.posto" => "nullable|string",
			"dados.modalidadeCarteira" => "nullable|string",
			"dados.range" => "nullable|string",
			"dados.byte" => "nullable|string",
            "dados.beneficiario" => "required|array",
            "dados.beneficiario.nome" => "required|string",
            "dados.beneficiario.endereco" => "required|string",
            "dados.beneficiario.cep" => "required|string",
            "dados.beneficiario.uf" => "required|string",
            "dados.beneficiario.cidade" => "required|string",
            "dados.beneficiario.documento" => "required|string",
        ];
    }

    // Valida dados 
    public function after(): array
    {
        return [
            function (Validator $validator) {

                // Somente realiza as validações seguintes se passou nas rules
                if ($validator->errors()->isNotEmpty()) return;

                // Obtém os dados da request 
                $banco = $this->input('banco'); 
                $layout = $this->input('layout'); 
                $dados = $this->input('dados'); 
                $beneficiario = $dados['beneficiario']; 
                $boletosIds = $this->input('boletos');

                // Verifica se o banco está implementado
                try {
                    $bancoClasse = Util::getBancoClass($banco);
                } catch (\Throwable $e) {
                    $validator->errors()->add(
                        'banco',
                        'Banco não implementado.'
                    );
                    return;
                }

                // Valida se o beneficiario pode ser convertido em objeto Pessoa
                try {
                    $pessoa = new Pessoa($beneficiario);
                } catch (\Throwable $e) {
                    $validator->errors()->add(
                        'dados.beneficiario',
                        'Dados do beneficiário inválidos.'
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
                    
                // Verifica se o layout cnab está implementado
                $remessaClasse = '\\Eduardokum\\LaravelBoleto\\Cnab\\Remessa\\Cnab' . $layout . '\\' . $bancoClasse;
                if (!class_exists($remessaClasse)) {
                    $validator->errors()->add(
                        'layout',
                        'Layout CNAB ' . $layout . ' não foi implementado para o banco ' . $banco
                    );
                    return;
                }

                // Verifica se o boleto existe
                foreach ($boletosIds as $id) {
                    $boleto = Boleto::find($id);
                    if (!isset($boleto)) {
                        $validator->errors()->add(
                            'boletos',
                            'Boleto ' . $id . ' não encontrado'
                        );
                        continue;
                    }
                    else {
                        // Verifica se é do mesmo beneficiário / conta da remessa
                        if ($banco != $boleto->banco) {
                            $validator->errors()->add(
                                'boletos',
                                'Banco ' . $boleto->banco . ' no boleto ' . $id . ' divergente do banco da remessa ' . $banco
                            );
                        }
                        // Normaliza o documento antes de comparar
                        $documentoRemessa = preg_replace('/\D/', '', $pessoa->getDocumento());
                        $documentoBoleto = preg_replace('/\D/', '', $boleto->dados['beneficiario']['documento']);
                        if ($documentoRemessa !== $documentoBoleto) {
                            $validator->errors()->add(
                                'boletos',
                                'Documento do beneficiário ' .  $boleto->dados['beneficiario']['documento'] . ' no boleto ' . $id . 
                                ' divergente do beneficiário da remessa ' . $pessoa->getDocumento()
                            );
                        }
                        // Normaliza agência antes de comparar
                        $agenciaRemessa = preg_replace('/\D/', '', $dados['agencia']);
                        $agenciaBoleto = preg_replace('/\D/', '', $boleto->dados['agencia']);
                        if ($agenciaRemessa !== $agenciaBoleto) {
                            $validator->errors()->add(
                                'boletos',
                                'Agência ' . $boleto->dados['agencia'] . ' no boleto ' . $id . 
                                ' divergente da agência da remessa ' . $dados['agencia']
                            );
                        }
                        if ($dados['conta'] != $boleto->dados['conta']) {
                            $validator->errors()->add(
                                'boletos',
                                'Conta ' . $boleto->dados['conta'] . ' no boleto ' . $id . 
                                ' divergente da conta da remessa ' . $dados['conta']
                            );
                        }
                    }
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
