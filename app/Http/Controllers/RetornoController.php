<?php

namespace App\Http\Controllers;

use App\Http\Requests\RetornoRequest;
use App\Http\Resources\RetornoResource;
use App\Models\Retorno;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

class RetornoController extends Controller
{
    // Lista os retornos processados
    public function index(Request $request)
    {
        $perPage = $request->integer('per_page', 15);
        if ($perPage < 1) {$perPage = 15;}
        if ($perPage > 500) {$perPage = 500;}
        $retornos = Retorno::query()->orderByDesc('created_at')->paginate($perPage);

        return RetornoResource::collection($retornos);
    }

    // Recebe e processa um arquivo de retorno CNAB.
    // O banco e o layout são identificados automaticamente pelo LaravelBoleto através da Factory
    public function store(RetornoRequest $request): JsonResponse|RetornoResource
    {
        $retorno = new Retorno();

        try {
            $retorno->processarArquivo(arquivo: $request->file('arquivo'),);
            $retorno->save();

            return (new RetornoResource($retorno))->response()->setStatusCode(201);
        } catch (Throwable $exception) {
            Log::error('Erro ao processar arquivo de retorno CNAB.', ['arquivo' => $request->file('arquivo')?->getClientOriginalName(), 'exception' => $exception,]);

            // O método processarArquivo() preenche os metadados do arquivo mesmo quando o processamento falha
            // Assim conseguimos manter o registro com status "Erro" para analisar depois
            if ($retorno->arquivo_nome) {
                try {
                    $retorno->save();
                } catch (Throwable $saveException) {
                    Log::error('Não foi possível salvar o registro do retorno CNAB após erro.', ['exception' => $saveException]);
                }
            }

            return response()->json(
                [
                    'message' => 'Não foi possível processar o arquivo de retorno.',
                    'error' => app()->isProduction() ? 'Arquivo de retorno inválido ou incompatível.' : $exception->getMessage(),
                ], 422
            );
        }
    }

    // Exibe um retorno específico.
    public function show(Retorno $retorno): RetornoResource
    {
        return new RetornoResource($retorno);
    }

    //  Remove um retorno processado.
    public function destroy(Retorno $retorno): JsonResponse
    {
        $retorno->delete();
        return response()->json(['message' => 'Retorno removido com sucesso.']);
    }
}