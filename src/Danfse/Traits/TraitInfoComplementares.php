<?php

namespace Hadder\NfseNacional\Danfse\Traits;

use Hadder\NfseNacional\Danfse\Pdf;

/**
 * Bloco "Informações Complementares" — NT-008 §2.1.12 e §2.4.5.
 *
 * Nó XML: NFSe/infNFSe/DPS/infDPS (vários filhos compostos)
 *
 * Layout:
 *   L1 (alt 6,4mm): [INFORMAÇÕES COMPLEMENTARES] título full-width (cinza)
 *   L2 (expande até $yBottom): textBox multi-linha com os fragmentos na ordem
 *       da NT-008 §2.4.5, um por linha:
 *         Imóvel: ...
 *         Obra: ...
 *         Evento: ...
 *         infoCompl: ...
 *         Informações específicas do município (leiaute próprio): ...
 *         Totais Aproximados dos Tributos cfe. Lei nº 12.741/2012:
 *           Federais: R$ ...; Estaduais: R$ ...; Municipais: R$ ...
 */
trait TraitInfoComplementares
{
    protected function blocoInfoComplementares(float $xIni, float $yIni, float $yBottom): float
    {
        $larguraTotal = $this->maxW - 2 * $this->margesq;
        $altTitulo = 6.4;

        // ----- L1: Título full-width cinza -----
        $this->desenharTituloBlocoCampo($xIni, $yIni, $larguraTotal, $altTitulo,
            'INFORMAÇÕES COMPLEMENTARES');

        // ----- L2: textBox expandindo até o fundo da moldura -----
        $texto = $this->montarTextoComplementar();
        $yTexto = $yIni + $altTitulo + 0.6;
        $altTexto = max(8.0, $yBottom - $yTexto - 0.6);

        $this->pdf->SetFont(Pdf::FONT_CONTEUDO, '', 7);
        $this->pdf->textBox(
            $xIni + 0.6,
            $yTexto,
            $larguraTotal - 1.2,
            $altTexto,
            $texto,
            'L',
            'T'
        );

        return $yBottom;
    }

    /**
     * Monta o texto das informações complementares, uma linha por fragmento,
     * na ordem fixa da NT-008 §2.4.5. Fragmentos sem dados são omitidos,
     * exceto a linha de Totais Aproximados (Lei 12.741/2012), sempre presente.
     */
    private function montarTextoComplementar(): string
    {
        $linhas = [];

        $imovel = $this->extrairValor($this->infDPS, 'serv/infoCompl/inscImob', '');
        if ($imovel !== '') {
            $linhas[] = 'Imóvel: ' . $imovel;
        }

        $obra = $this->extrairValor($this->infDPS, 'serv/infoCompl/codObra', '');
        if ($obra !== '') {
            $linhas[] = 'Obra: ' . $obra;
        }

        $evento = $this->extrairValor($this->infDPS, 'serv/infoCompl/codEvento', '');
        if ($evento !== '') {
            $linhas[] = 'Evento: ' . $evento;
        }

        $infoCompl = $this->getTag($this->infDPS, 'xInfComp', '');
        if ($infoCompl !== '') {
            $linhas[] = 'infoCompl: ' . $infoCompl;
        }

        $infMun = $this->extrairValor($this->infDPS, 'serv/infoCompl/infATMun', '');
        if ($infMun !== '') {
            $linhas[] = 'Informações específicas do município (leiaute próprio): ' . $infMun;
        }

        // Totais Aproximados dos Tributos cfe. Lei nº 12.741/2012 (sempre presente)
        $federais   = $this->calcularTributosFederais();
        $estaduais  = (float) $this->getTag($this->ibscbs, 'vIBSUF', '0');
        $vISSQN     = (float) $this->extrairValor($this->valores, 'trib/tribMun/vISSQN', '0');
        $vIBSMun    = (float) $this->getTag($this->ibscbs, 'vIBSMun', '0');
        $municipais = $vISSQN + $vIBSMun;

        $linhas[] = sprintf(
            'Totais Aproximados dos Tributos cfe. Lei nº 12.741/2012: Federais: R$ %s; Estaduais: R$ %s; Municipais: R$ %s',
            number_format($federais, 2, ',', '.'),
            number_format($estaduais, 2, ',', '.'),
            number_format($municipais, 2, ',', '.')
        );

        return implode("\n", $linhas);
    }

    private function calcularTributosFederais(): float
    {
        $vPis    = (float) $this->extrairValor($this->valores, 'trib/tribFed/piscofins/vPis', '0');
        $vCofins = (float) $this->extrairValor($this->valores, 'trib/tribFed/piscofins/vCofins', '0');
        $vIRRF   = (float) $this->extrairValor($this->valores, 'trib/tribFed/vRetIRRF', '0');
        $vRetCP  = (float) $this->extrairValor($this->valores, 'trib/tribFed/vRetCP', '0');
        $vRetCSLL = (float) $this->extrairValor($this->valores, 'trib/tribFed/vRetCSLL', '0');
        $vCBS    = (float) $this->getTag($this->ibscbs, 'vCBS', '0');
        return $vPis + $vCofins + $vIRRF + $vRetCP + $vRetCSLL + $vCBS;
    }
}
