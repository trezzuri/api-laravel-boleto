<?php

namespace App\Models;

use Eduardokum\LaravelBoleto\Cnab\Retorno\Factory; 
use Eduardokum\LaravelBoleto\Util; 
use Illuminate\Database\Eloquent\Factories\HasFactory; 
use Illuminate\Http\UploadedFile; 
use Illuminate\Support\Facades\Storage; 
use MongoDB\Laravel\Eloquent\Model; 
use RuntimeException;

class Retorno extends Model
{
    protected $connection = 'mongodb';
    protected $collection = 'retorno';
    protected $fillable = [
        'custom_id_retorno',
        'arquivo_nome',
        'arquivo_path',
        'arquivo_mime',
        'arquivo_tamanho',
        'banco',
        'layout',
        'codigo_banco',
        'nome_banco',
        'tipo_cnab',
        'dados',
        'status',
        'mensagem',
    ];
    protected $casts = [
        'dados' => 'array',
        'arquivo_tamanho' => 'integer',
        'tipo_cnab' => 'integer',
    ];

    // Processa um arquivo CNAB de retorno.
    // O banco e o layout são identificados automaticamente pelo LaravelBoleto\Cnab\Retorno\Factory.
    public function processarArquivo(UploadedFile $arquivo): array
    {
        $nomeOriginal = $arquivo->getClientOriginalName();
        $arquivoPath = $arquivo->store('retornos', 'local');
        if (!$arquivoPath) {
            throw new RuntimeException('Não foi possível armazenar o arquivo de retorno.');
        }
        $caminhoCompleto = Storage::disk('local')->path($arquivoPath);

        //Preenche os dados básicos para já salvar o registro.
        $this->fill([
            'custom_id_retorno' => $this->gerarCustomId($nomeOriginal),
            'arquivo_nome' => $nomeOriginal,
            'arquivo_path' => $arquivoPath,
            'arquivo_mime' => $arquivo->getClientMimeType(),
            'arquivo_tamanho' => $arquivo->getSize(),
            'status' => 'Processando',
            'mensagem' => null,
        ]);
        try {
            /* Factory::make():
             *
             * 1. lê o arquivo;
             * 2. verifica se é retorno CNAB;
             * 3. identifica CNAB240/CNAB400;
             * 4. identifica o banco;
             * 5. instancia o parser correto;
             * 6. chama processar().
             */
            $parser = Factory::make($caminhoCompleto);
            $dados = $parser->toArray();
            $dados = $this->normalizarDados($dados);

            //Determina banco/layout a partir do próprio arquivo.
            $metadados = $this->extrairMetadados(caminho: $caminhoCompleto, dados: $dados, parser: $parser);
            $this->fill([
                'banco' => $metadados['banco'],
                'layout' => $metadados['layout'],
                'codigo_banco' => $metadados['codigo_banco'],
                'nome_banco' => $metadados['nome_banco'],
                'tipo_cnab' => $metadados['tipo_cnab'],
                'dados' => $dados,
                'status' => 'Processado',
                'mensagem' => null,
            ]);

            return $dados;
        } catch (\Throwable $exception) {
            $this->fill(['status' => 'Erro', 'mensagem' => $exception->getMessage()]);
            throw $exception;
        }
    }

    // Extrai os metadados do arquivo/parser.
    protected function extrairMetadados(string $caminho, array $dados, object $parser): array {
        $linhas = Util::file2array($caminho);
        if (!$linhas || !isset($linhas[0])) {
            throw new RuntimeException('Não foi possível ler o arquivo de retorno.');
        }

        $primeiraLinha = $linhas[0];
        $classe = get_class($parser);

        if (str_contains($classe, '\\Cnab240\\')) {
            $layout = '240';

            // CNAB240: posições 1-3 = código do banco.
            $codigoBanco = mb_substr($primeiraLinha, 0, 3);
            $nomeBanco = $dados['header']['nomeBanco'] ?? null;
        } elseif (str_contains($classe, '\\Cnab400\\')) {
            $layout = '400';

            // CNAB400: posições 77-79 = código do banco.
            $codigoBanco = mb_substr($primeiraLinha, 76, 3);
            $nomeBanco = $this->obterNomeBancoDoParser($classe);
        } else {
            throw new RuntimeException('Não foi possível identificar o layout CNAB do arquivo.');
        }

        $codigoBanco = trim($codigoBanco);
        if (!preg_match('/^\d{3}$/', $codigoBanco)) {
            throw new RuntimeException('Não foi possível identificar o código do banco no arquivo de retorno.');
        }

        return [
            'banco' => $codigoBanco,
            'codigo_banco' => $codigoBanco,
            'nome_banco' => $nomeBanco,
            'layout' => $layout,
            'tipo_cnab' => (int) $layout,
        ];
    }

    // Obtém o nome do banco a partir da classe do parser quando o header CNAB400 não disponibiliza o nome
    protected function obterNomeBancoDoParser(string $classe): ?string
    {
        $mapa = [
            'Bb' => 'BANCO DO BRASIL',
            'Bradesco' => 'BRADESCO',
            'Itau' => 'ITAÚ',
            'Santander' => 'SANTANDER',
            'Caixa' => 'CAIXA ECONÔMICA FEDERAL',
            'Sicredi' => 'SICREDI',
            'Sicoob' => 'SICOOB',
            'Safra' => 'SAFRA',
            'Banrisul' => 'BANRISUL',
            'Hsbc' => 'HSBC',
        ];

        foreach ($mapa as $classeBanco => $nomeBanco) {
            if (str_ends_with($classe, '\\' . $classeBanco)) {return $nomeBanco;}
        }

        return null;
    }

    // Gera um id para o retorno
    protected function gerarCustomId(string $nomeOriginal): string
    {
        $nome = pathinfo($nomeOriginal, PATHINFO_FILENAME);
        $nome = preg_replace('/[^A-Za-z0-9_-]/', '_', $nome);
        return $nome . '_' . uniqid('', true) . '_retorno';
    }

    // Converte Collections e objetos com toArray() para arrays
    protected function normalizarDados(mixed $valor): mixed
    {
        if ($valor instanceof \Illuminate\Support\Collection) {
            return $valor
                ->map(fn ($item) => $this->normalizarDados($item))
                ->values()
                ->all();
        }

        if (is_array($valor)) {
            $resultado = [];

            foreach ($valor as $chave => $item) {
                $resultado[$chave] = $this->normalizarDados($item);
            }
            return $resultado;
        }

        if (is_object($valor) && method_exists($valor, 'toArray')) {
            return $this->normalizarDados($valor->toArray());
        }

        return $valor;
    }
}