<?php

namespace Hadder\NfseNacional\Danfse\Traits;

use Hadder\NfseNacional\Danfse\EnumDecoder;

/**
 * Bloco "Tributação Federal (Exceto CBS)" — NT-008 §2.1.9 e §2.4.5.
 *
 * Nó XML: NFSe/infNFSe/DPS/infDPS/valores/trib/tribFed
 *
 * Layout em 2 linhas de 6,4mm (alt total ~12,8mm):
 *   L1: IRRF | Contribuição Previdenciária - Retida | Contribuições Sociais - Retidas
 *   L2: PIS - Débito Apuração Própria | COFINS - Débito Apuração Própria
 *       | Descrição Contrib. Sociais - Retidas
 */
trait TraitTributacaoFederal
{
    protected function blocoTributacaoFederal(float $xIni, float $yIni): float
    {
        $larguraTotal = $this->maxW - 2 * $this->margesq;
        $altLinha = 6.4;
        $colQuarta = $larguraTotal / 4;

        $tribFed = $this->getChild($this->getChild($this->valores, 'trib'), 'tribFed');
        $piscofins = $this->getChild($tribFed, 'piscofins');

        // Contribuições Sociais retidas: soma de CSLL/PIS/COFINS retidos quando presentes
        $vRetPis    = (float) $this->getTag($tribFed, 'vRetPis', '0');
        $vRetCofins = (float) $this->getTag($tribFed, 'vRetCofins', '0');
        $vRetCSLL   = (float) $this->getTag($tribFed, 'vRetCSLL', '0');
        $vRetSociais = $vRetPis + $vRetCofins + $vRetCSLL;

        // ----- L1 (grade 4 colunas): TÍTULO | IRRF | Contrib. Previdenciária | Contrib. Sociais Retidas -----
        $this->desenharTituloBlocoCampo($xIni, $yIni, $colQuarta, $altLinha,
            'TRIBUTAÇÃO FEDERAL (EXCETO CBS)');
        $this->desenharCelula($xIni + $colQuarta, $yIni, $colQuarta, $altLinha,
            'IRRF',
            $this->formatar($this->getTagFallbackTF($tribFed, ['vIRRF', 'vRetIRRF']), 'moeda'));
        $this->desenharCelula($xIni + 2 * $colQuarta, $yIni, $colQuarta, $altLinha,
            'Contribuição Previdenciária - Retida',
            $this->formatar($this->getTag($tribFed, 'vRetCP', ''), 'moeda'));
        $this->desenharCelula($xIni + 3 * $colQuarta, $yIni, $colQuarta, $altLinha,
            'Contribuições Sociais - Retidas',
            $vRetSociais > 0 ? 'R$ ' . number_format($vRetSociais, 2, ',', '.') : '-');

        // ----- L2 (grade 4 colunas): PIS | COFINS | Descrição Contrib. Sociais Retidas (2 cols) -----
        $y2 = $yIni + $altLinha;
        $this->desenharCelula($xIni, $y2, $colQuarta, $altLinha,
            'PIS - Débito Apuração Própria',
            $this->formatar($this->getTag($piscofins, 'vPis', ''), 'moeda'));
        $this->desenharCelula($xIni + $colQuarta, $y2, $colQuarta, $altLinha,
            'COFINS - Débito Apuração Própria',
            $this->formatar($this->getTag($piscofins, 'vCofins', ''), 'moeda'));
        $this->desenharCelula($xIni + 2 * $colQuarta, $y2, 2 * $colQuarta, $altLinha,
            'Descrição Contrib. Sociais - Retidas',
            EnumDecoder::truncate(
                EnumDecoder::decode(EnumDecoder::TP_RET_PIS_COFINS, $this->getTag($piscofins, 'tpRetPISCofins', '')),
                80));

        return $yIni + 2 * $altLinha;
    }

    /** Tenta uma lista de tags; retorna o primeiro valor não-vazio. */
    private function getTagFallbackTF(?\DOMElement $parent, array $tags): string
    {
        foreach ($tags as $tag) {
            $v = $this->getTag($parent, $tag, '');
            if ($v !== '') {
                return $v;
            }
        }
        return '';
    }
}
