<?php

namespace App\Http\Controllers;

use App\Models\Remessa;
use App\Http\Resources\RemessaResource;
use App\Http\Requests\RemessaRequest;
use Illuminate\Http\Request;

class RemessaController extends Controller
{
    // Lista de Remessas
    public function index()
    {
        try {
            $remessas = Remessa::paginate(500);
            return RemessaResource::collection($remessas);
        } catch (\Throwable $e) {
            report($e);
            return response()->json(['message' => 'Falha ao listar arquivos de Remessa'], 400);
        }
    }

    // Armazenamento de Remessas
    public function store(RemessaRequest $request)
    {
        try {
            // Cria o model com os dados validados 
            $remessa = new Remessa(); 
            $remessa->fill($request->validated()); 
            // Gera o arquivo CNAB 
            $remessa->gerar(); 
            // Salva a Remessa no MongoDB 
            $remessa->save(); 
            
            return new RemessaResource($remessa);
        } catch (\Throwable $e) {
            report($e);
            return response()->json(['message' => 'Falha ao enviar arquivo de Remessa.'], 400);
        }
    }

    // Display de Remessas
    public function show(Remessa $remessa)
    {
        try {
            return new RemessaResource($remessa);
        } catch (\Throwable $e) {
            report($e);
            return response()->json(['message' => 'Falha ao mostrar arquivo de Remessa.'], 400);
        }
    }

    // Update de Remessas
    public function update(RemessaRequest $request, Remessa $remessa)
    {
        try {
            $remessa->update($request->validated());
            return new RemessaResource($remessa);
        } catch (\Throwable $e) {
            report($e);
            return response()->json(['message' => 'Falha ao atualizar arquivo de Remessa.'], 400);
        }
    }

    // Exclusão de Remessas
    public function destroy(Remessa $remessa)
    {
        try {
            return Remessa::destroy($remessa->_id);
        } catch (\Throwable $e) {
            report($e);
            return response()->json(['message' => 'Falha ao excluir arquivo de Remessa.'], 400);
        }
    }
}
