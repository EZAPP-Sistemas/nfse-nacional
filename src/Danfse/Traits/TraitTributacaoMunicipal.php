<?php

namespace Hadder\NfseNacional\Danfse\Traits;

use Hadder\NfseNacional\Danfse\EnumDecoder;

/**
 * Bloco "Tributação Municipal (ISSQN)" — NT-008 §2.1.8 e §2.4.5.
 *
 * Nó XML: NFSe/infNFSe/DPS/infDPS/valores/trib/tribMun
 *
 * Layout em 4 linhas de 6,4mm (alt total ~25,6mm):
 *   L1: Tipo de Tributação do ISSQN | Município / Sigla UF / País de
 *       Incidência do ISSQN
 *   L2: Regime Especial de Tributação do ISSQN | Tipo de Imunidade do ISSQN
 *       | Suspensão da Exigibilidade do ISSQN | Número Processo Suspensão
 *   L3: Benefício Municipal | Cálculo do BM | Total Deduções/Reduções
 *       | Desconto Incondicionado
 *   L4: BC ISSQN | Alíquota Aplicada | Retenção do ISSQN | ISSQN Apurado
 */
trait TraitTributacaoMunicipal
{
    protected function blocoTributacaoMunicipal(float $xIni, float $yIni): float
    {
        $larguraTotal = $this->maxW - 2 * $this->margesq;
        $altLinha = 6.4;

        $tribMun = $this->getChild($this->getChild($this->valores, 'trib'), 'tribMun');
        $colQuarta = $larguraTotal / 4;

        // ----- L1 (grade 4 colunas): TÍTULO | Tipo Tributação | Munic./UF/País (2 cols) -----
        $this->desenharTituloBlocoCampo($xIni, $yIni, $colQuarta, $altLinha,
            'TRIBUTAÇÃO MUNICIPAL (ISSQN)');
        $this->desenharCelula($xIni + $colQuarta, $yIni, $colQuarta, $altLinha,
            'Tipo de Tributação do ISSQN',
            EnumDecoder::truncate(
                EnumDecoder::decode(EnumDecoder::TRIB_ISSQN, $this->getTag($tribMun, 'tribISSQN', '')),
                40));
        $this->desenharCelula($xIni + 2 * $colQuarta, $yIni, 2 * $colQuarta, $altLinha,
            'Município / Sigla UF / País de Incidência do ISSQN',
            $this->formatarMunicipioUfPais(
                $this->getTag($tribMun, 'cLocIncid', ''),
                $this->getTag($tribMun, 'UF', ''),
                $this->getTag($tribMun, 'cPais', '')
            ));

        // ----- L2: Reg. Especial | Imunidade | Suspensão | Nº Processo -----
        $y2 = $yIni + $altLinha;
        $this->desenharCelula($xIni, $y2, $colQuarta, $altLinha,
            'Regime Especial de Tributação do ISSQN',
            EnumDecoder::truncate(
                EnumDecoder::decode(EnumDecoder::REG_ESP_TRIB, $this->getTag($tribMun, 'regEspTrib', '')),
                40));
        $this->desenharCelula($xIni + $colQuarta, $y2, $colQuarta, $altLinha,
            'Tipo de Imunidade do ISSQN',
            EnumDecoder::truncate(
                EnumDecoder::decode(EnumDecoder::TP_IMUNIDADE, $this->getTag($tribMun, 'tpImunidade', '')),
                40));
        $this->desenharCelula($xIni + 2 * $colQuarta, $y2, $colQuarta, $altLinha,
            'Suspensão da Exigibilidade do ISSQN',
            EnumDecoder::truncate(
                EnumDecoder::decode(EnumDecoder::TP_SUSP, $this->getTag($tribMun, 'tpSusp', '')),
                40));
        $this->desenharCelula($xIni + 3 * $colQuarta, $y2, $colQuarta, $altLinha,
            'Número Processo Suspensão', $this->getTag($tribMun, 'nProcesso', ''));

        // ----- L3: Benefício Municipal | Cálculo do BM | Total Deduções/Reduções | Desc. Incondicionado -----
        $y3 = $yIni + 2 * $altLinha;
        $this->desenharCelula($xIni, $y3, $colQuarta, $altLinha,
            'Benefício Municipal',
            EnumDecoder::truncate(
                EnumDecoder::decode(EnumDecoder::TP_BM, $this->getTag($tribMun, 'tpBM', '')),
                40));
        $this->desenharCelula($xIni + $colQuarta, $y3, $colQuarta, $altLinha,
            'Cálculo do BM', $this->getTag($tribMun, 'cBM', ''));
        $this->desenharCelula($xIni + 2 * $colQuarta, $y3, $colQuarta, $altLinha,
            'Total Deduções/Reduções',
            $this->formatar($this->getTagFallback($tribMun, ['vTotDR', 'vDed']), 'moeda'));
        $this->desenharCelula($xIni + 3 * $colQuarta, $y3, $colQuarta, $altLinha,
            'Desconto Incondicionado',
            $this->formatar($this->getTag($this->valores, 'vDescIncond', ''), 'moeda'));

        // ----- L4: BC | Alíquota | Retenção | ISSQN Apurado -----
        $y4 = $yIni + 3 * $altLinha;
        $this->desenharCelula($xIni, $y4, $colQuarta, $altLinha,
            'BC ISSQN',
            $this->formatar($this->getTag($tribMun, 'vBC', ''), 'moeda'));
        $this->desenharCelula($xIni + $colQuarta, $y4, $colQuarta, $altLinha,
            'Alíquota Aplicada',
            $this->formatar($this->getTag($tribMun, 'pAliq', ''), 'percent'));
        $this->desenharCelula($xIni + 2 * $colQuarta, $y4, $colQuarta, $altLinha,
            'Retenção do ISSQN',
            EnumDecoder::truncate(
                EnumDecoder::decode(EnumDecoder::TP_RET_ISSQN, $this->getTag($tribMun, 'tpRetISSQN', '')),
                40));
        $this->desenharCelula($xIni + 3 * $colQuarta, $y4, $colQuarta, $altLinha,
            'ISSQN Apurado',
            $this->formatar($this->getTag($tribMun, 'vISSQN', ''), 'moeda'));

        return $yIni + 4 * $altLinha;
    }

    /** Tenta uma lista de tags; retorna o primeiro valor não-vazio. */
    private function getTagFallback(?\DOMElement $parent, array $tags): string
    {
        foreach ($tags as $tag) {
            $v = $this->getTag($parent, $tag, '');
            if ($v !== '') {
                return $v;
            }
        }
        return '';
    }

    private function formatarMunicipioUfPais(string $cMun, string $uf, string $cPais): string
    {
        $partes = array_filter([
            $cMun !== '' ? $cMun : '',
            $uf !== '' ? $uf : '',
            $cPais !== '' && $cPais !== '1058' ? $cPais : '',
        ], fn($v) => $v !== '');
        return $partes ? implode(' / ', $partes) : '-';
    }
}
