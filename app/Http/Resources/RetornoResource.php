<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RetornoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->id,
            'custom_id_retorno' => $this->custom_id_retorno,
            'arquivo' => [
                'nome' => $this->arquivo_nome,
                'path' => $this->arquivo_path,
                'mime' => $this->arquivo_mime,
                'tamanho' => $this->arquivo_tamanho,
            ],
            'banco' => $this->banco,
            'codigo_banco' => $this->codigo_banco,
            'nome_banco' => $this->nome_banco,
            'layout' => $this->layout,
            'tipo_cnab' => $this->tipo_cnab,
            'status' => $this->status,
            'mensagem' => $this->mensagem,
            'dados' => $this->dados,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}