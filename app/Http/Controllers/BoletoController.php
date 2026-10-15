<?php

namespace App\Http\Controllers;

use App\Models\Boleto;
use App\Http\Resources\BoletoResource;
use App\Http\Requests\BoletoRequest;
use Illuminate\Http\Request;

class BoletoController extends Controller
{
    // Lista de Boletos
    public function index()
    {
        try {
            $boletos = Boleto::paginate(500);
            return BoletoResource::collection($boletos);
        } catch (\Throwable $e) {
            report($e);
            return response()->json(['message' => 'Falha ao listar Boletos.'], 400);
        }
    }

    // Armazenamento de Boletos
    public function store(BoletoRequest $request)
    {
        try {
            // Cria o model 
            $boleto = new Boleto(); 
            // Preenche os dados validados 
            $boleto->fill($request->validated()); 
            // Gera os dados derivados e o PDF 
            $boleto->gerar(); 
            // Salva o boleto no MongoDB 
            $boleto->save(); 
            
            return new BoletoResource($boleto);
        } catch (\Throwable $e) {
            report($e);
            return response()->json(['message' => 'Falha ao enviar Boleto.'], 400);
        }
    }

    // Display de Boletos
    public function show(Boleto $boleto)
    {
        try {
            return new BoletoResource($boleto);
        } catch (\Throwable $e) {
            report($e);
            return response()->json(['message' => 'Falha ao mostrar Boleto.'], 400);
        }
    }

    // Update de Boletos (apenas se a requisição for válida)
    public function update(BoletoRequest $request, Boleto $boleto)
    {
        try {
            $boleto->update($request->validated());
            return new BoletoResource($boleto);
        } catch (\Throwable $e) {
            report($e);
            return response()->json(['message' => 'Falha ao atualizar Boleto.'], 400);
        }
    }

    // Exclusão de Boletos
    public function destroy(Boleto $boleto)
    {
        try {
            return Boleto::destroy($boleto->_id);
        } catch (\Throwable $e) {
            report($e);
            return response()->json(['message' => 'Falha ao excluir Boleto.'], 400);
        }
    }
}
