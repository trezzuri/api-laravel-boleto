<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model; 
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\Storage;
use Illuminate\Database\Eloquent\Casts\Attribute;
use \Eduardokum\LaravelBoleto\Util;
use \Eduardokum\LaravelBoleto\Pessoa;
use \Eduardokum\LaravelBoleto\Boleto\Render\Pdf;
use Carbon\Carbon;
 
class Boleto extends Model
{
    use HasFactory;

    // Define a conexão com mongodb
    protected  $connection = 'mongodb';

    // Equivale ao $table do MySQL
    protected  $collection = 'boletos';

    // Define campos 
    protected  $fillable = ['banco', 'dados'];

    // Salva o arquivo 
    public function save(array $options = [])
    {
        return parent::save($options);
    }

    /** 
     * Gera os dados derivados do boleto e o arquivo PDF 
     * Este método deve ser chamado explicitamente antes do save
     * @return void 
     */ 
    public function gerar(): void 
    { 
        // Obtém o objeto do boleto 
        $boleto = $this->boleto; 
        
        // Armazena os dados calculados pelo LaravelBoleto
        $this->linha_digitavel = $boleto->getLinhaDigitavel(); 
        $this->codigo_barras = $boleto->getCodigoBarras(); 
        $this->numero = $boleto->getNumero(); 
        $this->numero_documento = $boleto->getNumeroDocumento(); 
        $this->nosso_numero = $boleto->getNossoNumero(); 
        $this->nosso_numero_boleto = $boleto->getNossoNumeroBoleto(); 

        // Cria o PDF do boleto 
        $pdf = new Pdf(); 
        $pdf->addBoleto($boleto); 
        $pdf->hideInstrucoes(); 

        // Define o nome do arquivo 
        $arquivo = $this->banco . '/' . uniqid('', true) . '.pdf'; 

        // Armazena o PDF 
        Storage::put($arquivo, $pdf->gerarBoleto($pdf::OUTPUT_STRING)); 

        // Armazena o caminho do PDF no boleto 
        $this->pdf = $arquivo; 
    }

    /**
     * Transforma o array dados em objeto Boleto
     */
    protected function boleto(): Attribute
    {
        return Attribute::make(
            get: function (mixed $value, array $attributes) {

                // Obtém a classe do boleto a ser gerado
                $classe = '\\Eduardokum\\LaravelBoleto\\Boleto\\' . Util::getBancoClass($attributes['banco']);

                // Remove do array de dados os campos vazios para evitar mensagens de erro desnecessárias
                // empty também pode ser 0 ou '0'; por isso verifique somente null e ''
                $dados = array_filter($attributes['dados'], fn ($item) => $item !== null && $item !== '');

                // Prepara os dados no formato esperado pela classe 
                $dados['beneficiario'] = new Pessoa($dados['beneficiario']);
                $dados['pagador'] = new Pessoa($dados['pagador']);
                $dados['dataVencimento'] = new Carbon($dados['dataVencimento']);

                // Obtém a url com o logo, senão usa o logo do banco. Se não existir não atribui o campo
                $logo = base_path('vendor/eduardokum/laravel-boleto/logos/' .$attributes['banco'] . '.png');
                if (!isset($dados['logo']) && file_exists($logo)) {
                    $dados['logo'] = realpath($logo);
                }

                // Quebra os textos longos, se necessário
                if (isset($dados['descricaoDemonstrativo']))
                    $dados['descricaoDemonstrativo'] = is_array($dados['descricaoDemonstrativo']) ? 
                        $dados['descricaoDemonstrativo'] :
                        $this->texto_em_array($dados['descricaoDemonstrativo']);
                if (isset($dados['instrucoes']))
                    $dados['instrucoes'] = is_array($dados['instrucoes']) ? 
                        $dados['instrucoes'] :
                        $this->texto_em_array($dados['instrucoes']);

                // Retorna o objeto boleto
                return new $classe($dados);                
            },
        );
    }    

    /**
     * Transforma um texto em um array de textos limitados a $max_char caracteres.
     * @return array<string, mixed>
     */
    private function texto_em_array(string $texto, int $max_char = 60): array
    {
        $temp = wordwrap($texto, $max_char, "\n", true);

        return explode("\n", $temp);
    }
}
