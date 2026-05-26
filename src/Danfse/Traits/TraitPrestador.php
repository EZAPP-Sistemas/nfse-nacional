<?php

namespace Hadder\NfseNacional\Danfse\Traits;

use Hadder\NfseNacional\Danfse\EnumDecoder;

/**
 * Bloco "Prestador / Fornecedor" — NT-008 §2.1.3 e §2.4.5.
 *
 * Conforme modelo do Anexo I: bloco fechado por moldura única, sem bordas verticais
 * entre células. Apenas o título "PRESTADOR / FORNECEDOR" tem fundo cinza, ocupando
 * a primeira "coluna" da primeira linha.
 *
 * Layout em 4 linhas de 6,4mm cada (alt total 25,6mm):
 *   L1: [PRESTADOR/FORNECEDOR cinza] | CNPJ/CPF/NIF | IM | Telefone
 *   L2: Nome (largo)                  | Município/UF | Código IBGE/CEP
 *   L3: Endereço (largo)              | E-mail (largo)
 *   L4: Simples Nacional              | Regime Apuração Tributária SN (largo)
 */
trait TraitPrestador
{
    protected function blocoPrestador(float $xIni, float $yIni): float
    {
        $altLinha = 6.4;
        $larguraTotal = $this->maxW - 2 * $this->margesq;
        $col = 50.9;
        $colDupla = 101.9;

        // Coordenadas X das 4 colunas
        $x1 = $xIni;
        $x2 = $xIni + 51.1;
        $x3 = $xIni + 102.1;
        $x4 = $xIni + 153.2;

        // ----- L1: título cinza + CNPJ/CPF/NIF + IM + Telefone -----
        $this->desenharTituloBlocoCampo($x1, $yIni, $col, $altLinha, 'PRESTADOR / FORNECEDOR');
        $this->desenharCelula($x2, $yIni, $col, $altLinha,
            'CNPJ / CPF / NIF', $this->extrairDocumento($this->prest));
        $this->desenharCelula($x3, $yIni, $col, $altLinha,
            'Indicador Municipal (Inscrição)', $this->getTag($this->prest, 'IM', ''));
        $this->desenharCelula($x4, $yIni, $col, $altLinha,
            'Telefone', $this->formatarTelefone($this->getTag($this->prest, 'fone', '')));

        // ----- L2: Nome | Município/UF | Código IBGE/CEP -----
        $y2 = $yIni + $altLinha;
        $this->desenharCelula($x1, $y2, $colDupla, $altLinha,
            'Nome / Nome Empresarial',
            EnumDecoder::truncate($this->getTag($this->prest, 'xNome', ''), 80));

        [$municipio, $uf, $cep, $cMun] = $this->extrairEndereco($this->prest);
        $munUf = $municipio !== '-' ? $municipio . ($uf !== '' ? " / {$uf}" : '') : '-';
        $this->desenharCelula($x3, $y2, $col, $altLinha, 'Município / Sigla UF', $munUf);

        $codCep = $cMun !== '' ? "{$cMun} / {$this->formatarCEP($cep)}" : '-';
        $this->desenharCelula($x4, $y2, $col, $altLinha, 'Código IBGE / CEP', $codCep);

        // ----- L3: Endereço | E-mail -----
        $y3 = $yIni + 2 * $altLinha;
        $this->desenharCelula($x1, $y3, $colDupla, $altLinha, 'Endereço',
            EnumDecoder::truncate($this->extrairEnderecoLogradouro($this->prest), 80));
        $this->desenharCelula($x3, $y3, $colDupla, $altLinha, 'E-mail',
            EnumDecoder::truncate($this->getTag($this->prest, 'email', ''), 80));

        // ----- L4: Simples Nacional | Regime de Apuração -----
        $y4 = $yIni + 3 * $altLinha;
        $regTrib = $this->getChild($this->prest, 'regTrib');
        $opSimpNac = $this->getTag($regTrib, 'opSimpNac', '');
        $regApTribSN = $this->getTag($regTrib, 'regApTribSN', '');

        $this->desenharCelula($x1, $y4, $col, $altLinha,
            'Simples Nacional na Data de Competência',
            EnumDecoder::truncate(EnumDecoder::decode(EnumDecoder::OP_SIMP_NAC, $opSimpNac), 40));
        $this->desenharCelula($x3, $y4, $colDupla, $altLinha,
            'Regime de Apuração Tributária pelo SN',
            EnumDecoder::truncate(EnumDecoder::decode(EnumDecoder::REG_AP_TRIB_SN, $regApTribSN), 80));

        $alturaBloco = 4 * $altLinha;
        return $yIni + $alturaBloco;
    }
}
