<?php

namespace Hadder\NfseNacional\Danfse\Traits;

use Hadder\NfseNacional\Danfse\EnumDecoder;

/**
 * Bloco "Dados do Serviço Prestado" — NT-008 §2.1.7 e §2.4.5.
 *
 * Nó XML: NFSe/infNFSe/DPS/infDPS/serv
 *
 * Campos exigidos pela NT-008 §2.1.7 (mínimos):
 *   • Código de Tributação Nacional / Municipal (cTribNac / cTribMun)
 *   • Código da NBS (cNBS)
 *   • Local da Prestação / Sigla UF / País (cLocPrestacao / UF / cPais)
 *   • Descrição do Código de Tributação Nacional / Municipal (xDescTribNac)
 *   • Descrição do Serviço (xDescServ)
 *
 * Layout em 4 linhas:
 *   L1 (alt 6,4mm): [SERVIÇO cinza] | Cód. Trib. Nacional | Cód. Trib. Municipal
 *                                    | Código NBS | Local Prestação (cMun/UF/País)
 *   L2 (alt 6,4mm): Descrição do Código de Tributação Nacional / Municipal (largo)
 *   L3 (alt 12,0mm): Descrição do Serviço (largo, multi-linha com word-wrap)
 *
 * Total: ~25mm. Bloco sempre presente (não há supressão para serviço).
 */
trait TraitServico
{
    protected function blocoServico(float $xIni, float $yIni): float
    {
        $larguraTotal = $this->maxW - 2 * $this->margesq;
        $altL1 = 6.4;
        $altL2 = 6.4;
        $altL3 = 12.0;

        // L1: título cinza (32mm) + 3 colunas iguais
        $colTitulo = 32.0;
        $colCampo = ($larguraTotal - $colTitulo) / 3;

        $x1 = $xIni;
        $x2 = $xIni + $colTitulo;
        $x3 = $x2 + $colCampo;
        $x4 = $x3 + $colCampo;

        $cServ = $this->getChild($this->serv, 'cServ');
        [$cLocPrest, $ufPrest, $cPaisPrest] = $this->extrairLocalPrestacao();

        $cTribNac = $this->getTag($cServ, 'cTribNac', '');
        $cTribMun = $this->getTag($cServ, 'cTribMun', '');
        $codTrib = trim(($cTribNac !== '' ? $cTribNac : '-') . ' / ' . ($cTribMun !== '' ? $cTribMun : '-'));

        // ----- L1: código combinado + NBS + localidade -----
        $this->desenharTituloBlocoCampo($x1, $yIni, $colTitulo, $altL1, 'SERVIÇO PRESTADO');
        $this->desenharCelula($x2, $yIni, $colCampo, $altL1,
            'Código de Tributação Nacional / Municipal', $codTrib);
        $this->desenharCelula($x3, $yIni, $colCampo, $altL1,
            'Código NBS', $this->getTag($cServ, 'cNBS', ''));
        $this->desenharCelula($x4, $yIni, $colCampo, $altL1,
            'Local Prestação / UF / País',
            $this->formatarLocalPrestacao($cLocPrest, $ufPrest, $cPaisPrest));

        // ----- L2: Descrição da Tributação Nacional / Municipal -----
        $y2 = $yIni + $altL1;
        $descTrib = $this->getTag($cServ, 'xDescTribNac', '');
        if ($descTrib === '') {
            $descTrib = $this->getTag($cServ, 'xDescTribMun', '');
        }
        $this->desenharCelula($xIni, $y2, $larguraTotal, $altL2,
            'Descrição do Código de Tributação Nacional / Municipal',
            EnumDecoder::truncate($descTrib, 160));

        // ----- L3: Descrição do Serviço (multi-linha) -----
        $y3 = $yIni + $altL1 + $altL2;
        $descricao = $this->getTag($cServ, 'xDescServ', '');
        if ($descricao === '') {
            $descricao = '-';
        }

        $this->pdf->SetTextColor(0, 0, 0);
        $this->pdf->SetFont($this->defaultFont, 'B', 6);
        $this->pdf->SetXY($xIni + 0.6, $y3 + 0.4);
        $this->pdf->Cell($larguraTotal - 1.2, 2.2,
            $this->pdf->latin('Descrição do Serviço'), 0, 0, 'L');

        $this->pdf->SetFont($this->defaultFont, '', 7);
        $this->pdf->textBox(
            $xIni + 0.6,
            $y3 + 2.8,
            $larguraTotal - 1.2,
            $altL3 - 3.0,
            $descricao,
            'L',
            'T'
        );

        return $yIni + $altL1 + $altL2 + $altL3;
    }

    /**
     * Retorna [cLocPrestacao, UF, cPais] do nó serv/locPrest.
     * Suporta tanto endereço nacional quanto exterior.
     *
     * @return array{0:string,1:string,2:string}
     */
    private function extrairLocalPrestacao(): array
    {
        $locPrest = $this->getChild($this->serv, 'locPrest');
        if (!$locPrest) {
            return ['', '', ''];
        }
        // Nacional: locPrest/cLocPrestacao (código IBGE 7 dígitos)
        $cLoc = $this->getTag($locPrest, 'cLocPrestacao', '');
        // UF pode vir explicit em locPrest/UF ou ser inferida do município (não temos lookup local)
        $uf   = $this->getTag($locPrest, 'UF', '');
        // País: presença de cPais indica prestação no exterior
        $cPais = $this->getTag($locPrest, 'cPais', '');
        return [$cLoc, $uf, $cPais];
    }

    private function formatarLocalPrestacao(string $cLoc, string $uf, string $cPais): string
    {
        if ($cPais !== '' && $cPais !== '1058') {
            // Exterior (1058 = Brasil)
            return ($cLoc !== '' ? $cLoc . ' / ' : '') . ($uf !== '' ? $uf . ' / ' : '') . $cPais;
        }
        if ($cLoc === '' && $uf === '') {
            // Fallback: se locPrest não informado, usa município do emitente (regra geral §2.1.7)
            $cLoc = $this->cMunEmit;
        }
        $partes = array_filter([$cLoc, $uf, $cPais === '1058' ? 'BR' : ''],
            fn($v) => $v !== '');
        return $partes ? implode(' / ', $partes) : '-';
    }
}
