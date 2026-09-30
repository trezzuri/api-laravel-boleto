<?php

namespace App\Models;

use App\Models\Boleto;
use MongoDB\Laravel\Eloquent\Model; 
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use \Eduardokum\LaravelBoleto\Util;
use \Eduardokum\LaravelBoleto\Pessoa;
use Illuminate\Support\Facades\Storage;

class Remessa extends Model
{
    use HasFactory;

    // Define a conexão com mongodb
    protected  $connection = 'mongodb';

    // Equivale ao $table do MySQL
    protected  $collection = 'remessa';

    // Define campos 
    protected  $fillable = ['banco', 'boletos', 'dados', 'layout'];

    // Salva o arquivo 
    public function save(array $options = [])
    {
        return parent::save($options);
    }

    /** 
     * Gera o arquivo CNAB de remessa.
     * Este método deve ser chamado explicitamente antes do save 
     * @return void 
     */ 
    public function gerar(): void { 
        // Obtém o código do banco 
        $banco = $this->banco; 
        // Define o layout padrão 
        $layout = $this->layout ?? '400';

        // Remove somente valores null e string vazia, dessa forma, valores 0 e "0" continuam sendo preservados
        $dados = array_filter($this->dados, fn ($item) => $item !== null && $item !== ''); 
        // Converte o beneficiário para Pessoa 
        $dados['beneficiario'] = new Pessoa( $dados['beneficiario'] ); 
        // Obtém a classe da remessa 
        $classe = '\\Eduardokum\\LaravelBoleto\\Cnab\\Remessa\\Cnab' . $layout . '\\' . Util::getBancoClass($banco); 
    
        // Cria o objeto CNAB 
        $obj_cnab = new $classe($dados); 
        // Adiciona os boletos 
        foreach ($this->boletos as $id) { 
            $boleto = Boleto::findOrFail($id); 
            $obj_cnab->addBoleto( $boleto->boleto ); 
        } 
        // Gera o arquivo de remessa 
        $arquivo = $banco . '/' . uniqid('', true) . '.rem'; 
        Storage::put($arquivo, $obj_cnab->gerar()); 
        
        // Armazena o caminho do arquivo no model 
        $this->arquivo = $arquivo; 
    }
}
