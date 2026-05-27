<?php

namespace Hadder\NfseNacional\Danfse\Traits;

use Hadder\NfseNacional\Danfse\EnumDecoder;

/**
 * Bloco "Tributação IBS / CBS" — NT-008 §2.1.10 e §2.4.5.
 *
 * Nó XML: NFSe/infNFSe/DPS/infDPS/IBSCBS
 *
 * Layout em 4 linhas de 6,4mm (alt total ~25,6mm):
 *   L1: CST / cClassTrib | Indicador de Operação / Código IBGE Incidência
 *       / Município Incidência / Sigla UF
 *   L2: Exclusões e Reduções da Base de Cálculo | Base de Cálculo Após
 *       Exclusões e Reduções | Red. Alíquota IBS / Red. Alíquota CBS
 *       | Alíquota - IBS UF / IBS Mun
 *   L3: Alíq. Efetiva Municipal - IBS | Valor Apurado Municipal - IBS
 *       | Alíq. Efetiva Estadual - IBS | Valor Apurado Estadual - IBS
 *   L4: Valor Total Apurado - IBS | Alíquota - CBS | Alíquota Efetiva
 *       - CBS | Valor Total Apurado - CBS
 */
trait TraitTributacaoIBSCBS
{
    protected function blocoTributacaoIBSCBS(float $xIni, float $yIni): float
    {
        $larguraTotal = $this->maxW - 2 * $this->margesq;
        $altLinha = 6.4;
        $colQuarta = $larguraTotal / 4;

        $gIBSCBS = $this->ibscbs;
        $gIbs = $this->getChild($gIBSCBS, 'gIBS');
        $gIbsUF = $this->getChild($gIbs, 'gIBSUF');
        $gIbsMun = $this->getChild($gIbs, 'gIBSMun');
        $gCBS = $this->getChild($gIBSCBS, 'gCBS');

        // ----- L1: TÍTULO cinza | CST / cClassTrib | Indic. Op. / Cód. IBGE / Munic. / UF -----
        $cst = $this->getTag($gIBSCBS, 'CST', '');
        $cClassTrib = $this->getTag($gIBSCBS, 'cClassTrib', '');
        $cstComp = trim(($cst !== '' ? $cst : '-') . ' / ' . ($cClassTrib !== '' ? $cClassTrib : '-'));

        $cIndOp = EnumDecoder::decode(EnumDecoder::C_IND_OP, $this->getTag($gIBSCBS, 'cIndOp', ''));
        $cLocIncid = $this->getTag($gIBSCBS, 'cLocIncid', '');
        $uf = $this->getTag($gIBSCBS, 'UF', '');
        $municIncid = ($cLocIncid !== '' && $cLocIncid === $this->cMunEmit) ? $this->xLocEmi : '';
        $idComp = $this->montarComSeparador(' / ', [
            $cIndOp !== '-' ? $cIndOp : '',
            $cLocIncid,
            $municIncid,
            $uf,
        ]);

        // ----- L1 (grade 4 colunas): TÍTULO | CST / cClassTrib | Indic. Op./IBGE/Munic./UF (2 cols) -----
        $this->desenharTituloBlocoCampo($xIni, $yIni, $colQuarta, $altLinha,
            'TRIBUTAÇÃO IBS / CBS');
        $this->desenharCelula($xIni + $colQuarta, $yIni, $colQuarta, $altLinha,
            'CST / cClassTrib', $cstComp);
        $this->desenharCelula($xIni + 2 * $colQuarta, $yIni, 2 * $colQuarta, $altLinha,
            'Indicador de Operação / Código IBGE Incidência / Município Incidência / Sigla UF',
            EnumDecoder::truncate($idComp, 80));

        // ----- L2: Excl/Red BC | BC Após | Red. Alíq IBS/CBS | Alíq IBS Est/Mun -----
        $y2 = $yIni + $altLinha;
        $this->desenharCelula($xIni, $y2, $colQuarta, $altLinha,
            'Exclusões e Reduções da Base de Cálculo',
            $this->formatar($this->getTagFallbackIBS($gIBSCBS, ['vRedBC', 'vBCRed']), 'moeda'));
        $this->desenharCelula($xIni + $colQuarta, $y2, $colQuarta, $altLinha,
            'Base de Cálculo Após Exclusões e Reduções',
            $this->formatar($this->getTag($gIBSCBS, 'vBC', ''), 'moeda'));

        $pRedIBS = $this->getTag($gIbs, 'pRedAliq', '');
        $pRedCBS = $this->getTag($gCBS, 'pRedAliq', '');
        $this->desenharCelula($xIni + 2 * $colQuarta, $y2, $colQuarta, $altLinha,
            'Red. Alíquota IBS / Red. Alíquota CBS',
            $this->montarComSeparador(' / ', [
                $pRedIBS !== '' ? $pRedIBS . '%' : '',
                $pRedCBS !== '' ? $pRedCBS . '%' : '',
            ]));

        $pAliqUF = $this->getTag($gIbsUF, 'pAliq', '');
        $pAliqMun = $this->getTag($gIbsMun, 'pAliq', '');
        $this->desenharCelula($xIni + 3 * $colQuarta, $y2, $colQuarta, $altLinha,
            'Alíquota - IBS UF / IBS Mun',
            $this->montarComSeparador(' / ', [
                $pAliqUF !== '' ? $pAliqUF . '%' : '',
                $pAliqMun !== '' ? $pAliqMun . '%' : '',
            ]));

        // ----- L3: Alíq Efetiva IBS Mun | Valor IBS Mun | Alíq Efetiva IBS Est | Valor IBS Est -----
        $y3 = $yIni + 2 * $altLinha;
        $this->desenharCelula($xIni, $y3, $colQuarta, $altLinha,
            'Alíq. Efetiva Municipal - IBS',
            $this->formatar($this->getTag($gIbsMun, 'pAliqEfet', ''), 'percent'));
        $this->desenharCelula($xIni + $colQuarta, $y3, $colQuarta, $altLinha,
            'Valor Apurado Municipal - IBS',
            $this->formatar($this->getTagFallbackIBS($gIbsMun, ['vIBSMun', 'vIBS']), 'moeda'));
        $this->desenharCelula($xIni + 2 * $colQuarta, $y3, $colQuarta, $altLinha,
            'Alíq. Efetiva Estadual - IBS',
            $this->formatar($this->getTag($gIbsUF, 'pAliqEfet', ''), 'percent'));
        $this->desenharCelula($xIni + 3 * $colQuarta, $y3, $colQuarta, $altLinha,
            'Valor Apurado Estadual - IBS',
            $this->formatar($this->getTagFallbackIBS($gIbsUF, ['vIBSUF', 'vIBS']), 'moeda'));

        // ----- L4: Valor Total IBS | Alíq CBS | Alíq Efetiva CBS | Valor Total CBS -----
        $y4 = $yIni + 3 * $altLinha;
        $this->desenharCelula($xIni, $y4, $colQuarta, $altLinha,
            'Valor Total Apurado - IBS',
            $this->formatar($this->getTag($gIbs, 'vIBS', ''), 'moeda'));
        $this->desenharCelula($xIni + $colQuarta, $y4, $colQuarta, $altLinha,
            'Alíquota - CBS',
            $this->formatar($this->getTag($gCBS, 'pAliq', ''), 'percent'));
        $this->desenharCelula($xIni + 2 * $colQuarta, $y4, $colQuarta, $altLinha,
            'Alíquota Efetiva - CBS',
            $this->formatar($this->getTag($gCBS, 'pAliqEfet', ''), 'percent'));
        $this->desenharCelula($xIni + 3 * $colQuarta, $y4, $colQuarta, $altLinha,
            'Valor Total Apurado - CBS',
            $this->formatar($this->getTag($gCBS, 'vCBS', ''), 'moeda'));

        return $yIni + 4 * $altLinha;
    }

    /** Tenta uma lista de tags; retorna o primeiro valor não-vazio. */
    private function getTagFallbackIBS(?\DOMElement $parent, array $tags): string
    {
        foreach ($tags as $tag) {
            $v = $this->getTag($parent, $tag, '');
            if ($v !== '') {
                return $v;
            }
        }
        return '';
    }

    /** Junta partes não-vazias com separador; retorna '-' se tudo vazio. */
    private function montarComSeparador(string $sep, array $partes): string
    {
        $filtradas = array_filter($partes, fn($v) => $v !== '');
        return $filtradas ? implode($sep, $filtradas) : '-';
    }
}
