<?php

namespace Hadder\NfseNacional\Danfse;

use Hadder\NfseNacional\Danfse\Traits\TraitCabecalho;
use Hadder\NfseNacional\Danfse\Traits\TraitPrestador;
use Hadder\NfseNacional\Danfse\Traits\TraitTomador;
use Hadder\NfseNacional\Danfse\Traits\TraitDestinatario;
use Hadder\NfseNacional\Danfse\Traits\TraitIntermediario;
use Hadder\NfseNacional\Danfse\Traits\TraitServico;
// Traits abaixo serão criados nas próximas fatias da NT-008.
// use Hadder\NfseNacional\Danfse\Traits\TraitTributacaoMunicipal;
// use Hadder\NfseNacional\Danfse\Traits\TraitTributacaoFederal;
// use Hadder\NfseNacional\Danfse\Traits\TraitTributacaoIBSCBS;
// use Hadder\NfseNacional\Danfse\Traits\TraitTotaisNFSe;
// use Hadder\NfseNacional\Danfse\Traits\TraitInfoComplementares;
// use Hadder\NfseNacional\Danfse\Traits\TraitCanhoto;

/**
 * DANFSe v2.0 — Documento Auxiliar da NFS-e Padrão Nacional.
 *
 * Conforme Nota Técnica nº 008 SE/CGNFS-e de 05/05/2026.
 * Standalone — não depende de nfephp-org/sped-da.
 *
 * Uso típico:
 *   $danfse = new Danfse($xmlNFSe);
 *   $danfse->printParameters('P', 'A4', 1.5, 1.5);
 *   $danfse->logoMunicipioParameters($pathLogoMunicipio);
 *   $pdf = $danfse->render();
 */
class Danfse extends DanfseCommon
{
    use TraitCabecalho;
    use TraitPrestador;
    use TraitTomador;
    use TraitDestinatario;
    use TraitIntermediario;
    use TraitServico;
    // Traits abaixo serão habilitados nas próximas fatias da implementação.
    // use TraitTributacaoMunicipal;
    // use TraitTributacaoFederal;
    // use TraitTributacaoIBSCBS;
    // use TraitTotaisNFSe;
    // use TraitInfoComplementares;
    // use TraitCanhoto;

    protected \DOMDocument $dom;
    protected ?\DOMElement $infNFSe = null;     // NFSe/infNFSe
    protected ?\DOMElement $infDPS = null;      // NFSe/infNFSe/DPS/infDPS
    protected ?\DOMElement $prest = null;
    protected ?\DOMElement $toma = null;
    protected ?\DOMElement $dest = null;        // NFSe/infNFSe/DPS/infDPS/IBSCBS/dest
    protected ?\DOMElement $interm = null;
    protected ?\DOMElement $serv = null;
    protected ?\DOMElement $valores = null;
    protected ?\DOMElement $ibscbs = null;

    protected string $tpAmb = '2';
    protected string $cStat = '';
    protected string $cMunEmit = '';
    protected string $xLocEmi = '';
    protected string $chaveAcesso = '';

    protected ?string $logoMunicipio = null;
    protected bool $exibirCanhoto = true;

    protected const STORAGE_LOGOS = __DIR__ . '/../../storage/logos';

    /**
     * @param string $xml XML completo da NFS-e (com ou sem wrapper de protocolo).
     */
    public function __construct(string $xml)
    {
        $this->dom = new \DOMDocument('1.0', 'UTF-8');
        $this->dom->preserveWhiteSpace = false;
        $this->dom->loadXML($xml);
        $this->loadDoc();

        // Defaults conforme NT-008 §2.2 (margens mínimas de 0,15 cm = 1,5 mm).
        $this->orientacao = 'P';
        $this->papel = 'A4';
        $this->margsup = 1.5;
        $this->margesq = 1.5;
        $this->maxW = 210;
        $this->maxH = 297;
    }

    /**
     * Resolve o logo do município (estratégia híbrida):
     *  1. Parâmetro explícito (path ou data-URI);
     *  2. Convenção storage/logos/{cMunEmit}.{png|jpg|jpeg};
     *  3. null — DANFSe imprime apenas o logo fixo da NFS-e.
     */
    public function logoMunicipioParameters(?string $logo = null): self
    {
        $this->logoMunicipio = LogoResolver::resolve(
            $logo,
            $this->cMunEmit,
            self::STORAGE_LOGOS
        );
        return $this;
    }

    public function exibirCanhoto(bool $exibir): self
    {
        $this->exibirCanhoto = $exibir;
        return $this;
    }

    /**
     * Orquestra os blocos do DANFSe na ordem fixa do Anexo I da NT-008.
     */
    protected function monta($logo = ''): void
    {
        $this->pdf = new Pdf($this->orientacao, 'mm', $this->papel);
        $this->pdf->SetMargins($this->margesq, $this->margsup, $this->margesq);
        $this->pdf->SetAutoPageBreak(false);
        $this->pdf->SetTitle('DANFSe ' . $this->chaveAcesso);
        $this->pdf->SetCreator($this->creditMessage ?? 'Hadder\\NfseNacional');
        $this->pdf->AddPage();

        $yStart = $this->margsup;
        $y = $yStart;
        $boundaries = [];

        $y = $this->blocoCabecalho($this->margesq, $y);             // §2.1.1-2, §2.4.3
        $boundaries[] = $y;
        $y = $this->blocoPrestador($this->margesq, $y);             // §2.1.3
        $boundaries[] = $y;
        $y = $this->blocoTomador($this->margesq, $y);               // §2.1.4 + nota 2
        $boundaries[] = $y;
        $y = $this->blocoDestinatario($this->margesq, $y);          // §2.1.5 + notas 2, 3
        $boundaries[] = $y;
        $y = $this->blocoIntermediario($this->margesq, $y);         // §2.1.6 + nota 2
        $boundaries[] = $y;
        $y = $this->blocoServico($this->margesq, $y);               // §2.1.7
        $boundaries[] = $y;
        $y = $this->blocoTributacaoMunicipal($this->margesq, $y);   // §2.1.8 + nota 4
        $boundaries[] = $y;
        $y = $this->blocoTributacaoFederal($this->margesq, $y);     // §2.1.9 + nota 6
        $boundaries[] = $y;
        $y = $this->blocoTributacaoIBSCBS($this->margesq, $y);      // §2.1.10
        $boundaries[] = $y;
        $y = $this->blocoTotaisNFSe($this->margesq, $y);            // §2.1.11
        $boundaries[] = $y;
        $y = $this->blocoInfoComplementares($this->margesq, $y);    // §2.1.12

        $this->desenharMolduraGlobal($yStart, $y, $boundaries);

        if ($this->exibirCanhoto) {
            $this->blocoCanhoto($this->margesq, $y);                // §2.1.13 (opcional)
        }

        $this->aplicarMarcaDagua();   // §2.5.1 CANCELADA / §2.5.2 SUBSTITUÍDA
        $this->rodapeCreditos();
    }

    /**
     * Parsing do XML: popula os DOMElement utilizados pelos blocos.
     */
    private function loadDoc(): void
    {
        $this->infNFSe = $this->dom->getElementsByTagName('infNFSe')->item(0);
        $this->infDPS  = $this->dom->getElementsByTagName('infDPS')->item(0);
        $this->prest   = $this->getChild($this->infDPS, 'prest');
        $this->toma    = $this->getChild($this->infDPS, 'toma');
        $this->interm  = $this->getChild($this->infDPS, 'interm');
        $this->serv    = $this->getChild($this->infDPS, 'serv');
        $this->valores = $this->getChild($this->infDPS, 'valores');
        $this->ibscbs  = $this->getChild($this->infDPS, 'IBSCBS');
        $this->dest    = $this->getChild($this->ibscbs, 'dest');

        $this->tpAmb    = $this->getTag($this->infDPS, 'tpAmb', '2');
        $this->cStat    = $this->getTag($this->infNFSe, 'cStat', '');
        $this->cMunEmit = $this->getTag($this->infDPS, 'cLocEmi', '');
        $this->xLocEmi  = $this->getTag($this->infNFSe, 'xLocEmi', '');

        $id = $this->infNFSe?->getAttribute('Id') ?? '';
        $this->chaveAcesso = str_starts_with($id, 'NFS') ? substr($id, 3) : $id;
    }

    /**
     * Lê o valor de uma tag filha direta, com fallback.
     */
    protected function getTag(?\DOMElement $parent, string $tag, string $default = ''): string
    {
        if (!$parent) {
            return $default;
        }
        $node = $parent->getElementsByTagName($tag)->item(0);
        return $node ? trim($node->nodeValue) : $default;
    }

    /**
     * Retorna o primeiro filho com o tag dado, ou null.
     */
    protected function getChild(?\DOMElement $parent, string $tag): ?\DOMElement
    {
        if (!$parent) {
            return null;
        }
        $node = $parent->getElementsByTagName($tag)->item(0);
        return $node instanceof \DOMElement ? $node : null;
    }

    /**
     * Lê valor por caminho relativo simples (a/b/c). Suporta alternativas (a|b|c).
     */
    protected function extrairValor(?\DOMElement $base, string $path, string $default = ''): string
    {
        if (!$base) {
            return $default;
        }
        foreach (explode('|', $path) as $alternativa) {
            $node = $base;
            foreach (explode('/', $alternativa) as $part) {
                if ($node === null) {
                    break;
                }
                $node = $node->getElementsByTagName($part)->item(0);
            }
            if ($node && trim($node->nodeValue) !== '') {
                return trim($node->nodeValue);
            }
        }
        return $default;
    }

    /**
     * Formata um valor conforme o tipo declarado no mapa de campos.
     *
     * @param string $tipo  text|doc|data|moeda|percent
     */
    protected function formatar(string $valor, string $tipo = 'text', int $maxLen = 0): string
    {
        if ($valor === '') {
            return '-';
        }
        $formatado = match ($tipo) {
            'doc'     => $this->formatarDocumento($valor),
            'data'    => $this->formatarData($valor),
            'moeda'   => 'R$ ' . number_format((float) $valor, 2, ',', '.'),
            'percent' => number_format((float) $valor, 2, ',', '.') . ' %',
            default   => $valor,
        };
        if ($maxLen > 0) {
            $formatado = EnumDecoder::truncate($formatado, $maxLen);
        }
        return $formatado;
    }

    protected function formatarDocumento(string $doc): string
    {
        $digits = preg_replace('/\D/', '', $doc);
        return match (strlen($digits)) {
            11 => preg_replace('/(\d{3})(\d{3})(\d{3})(\d{2})/', '$1.$2.$3-$4', $digits),
            14 => preg_replace('/(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})/', '$1.$2.$3/$4-$5', $digits),
            default => $doc,
        };
    }

    protected function formatarData(string $iso): string
    {
        try {
            return (new \DateTime($iso))->format('d/m/Y H:i:s');
        } catch (\Exception $e) {
            return $iso;
        }
    }

    /**
     * Extrai CNPJ ou CPF ou NIF do nó (na ordem de preferência), já formatado.
     * Usado pelos blocos Prestador, Tomador, Destinatário, Intermediário.
     */
    protected function extrairDocumento(?\DOMElement $node): string
    {
        if (!$node) return '-';
        $cnpj = $this->getTag($node, 'CNPJ', '');
        if ($cnpj !== '') return $this->formatarDocumento($cnpj);
        $cpf = $this->getTag($node, 'CPF', '');
        if ($cpf !== '') return $this->formatarDocumento($cpf);
        $nif = $this->getTag($node, 'NIF', '');
        if ($nif !== '') return $nif;
        return '-';
    }

    /**
     * Retorna [municipio, uf, cep, cMunIbge] a partir do nó pai (prest|toma|interm|dest).
     * Suporta tanto endNac (nacional) quanto endExt (exterior).
     */
    protected function extrairEndereco(?\DOMElement $pai): array
    {
        if (!$pai) return ['-', '', '', ''];
        $end = $this->getChild($pai, 'end');
        if (!$end) return ['-', '', '', ''];

        $endNac = $this->getChild($end, 'endNac');
        if ($endNac) {
            $cMun = $this->getTag($endNac, 'cMun', '');
            $cep = $this->getTag($endNac, 'CEP', '');
            if ($cMun !== '' && $cMun === $this->cMunEmit) {
                return [$this->xLocEmi, '', $cep, $cMun];
            }
            return [$cMun !== '' ? "(IBGE {$cMun})" : '-', '', $cep, $cMun];
        }
        $endExt = $this->getChild($end, 'endExt');
        if ($endExt) {
            return [
                $this->getTag($endExt, 'xCidade', '-'),
                $this->getTag($endExt, 'xEstProvReg', ''),
                $this->getTag($endExt, 'cEndPost', ''),
                $this->getTag($endExt, 'cPais', ''),
            ];
        }
        return ['-', '', '', ''];
    }

    /**
     * Concatena logradouro/número/complemento/bairro num único campo "Endereço".
     */
    protected function extrairEnderecoLogradouro(?\DOMElement $pai): string
    {
        if (!$pai) return '-';
        $end = $this->getChild($pai, 'end');
        if (!$end) return '-';
        $partes = array_filter([
            $this->getTag($end, 'xLgr', ''),
            $this->getTag($end, 'nro', ''),
            $this->getTag($end, 'xCpl', ''),
            $this->getTag($end, 'xBairro', ''),
        ], fn($v) => $v !== '');
        return $partes ? implode(', ', $partes) : '-';
    }

    protected function formatarCEP(string $cep): string
    {
        $d = preg_replace('/\D/', '', $cep);
        return strlen($d) === 8 ? substr($d, 0, 5) . '-' . substr($d, 5) : ($cep ?: '-');
    }

    protected function formatarTelefone(string $fone): string
    {
        $d = preg_replace('/\D/', '', $fone);
        return match (strlen($d)) {
            10 => sprintf('(%s) %s-%s', substr($d, 0, 2), substr($d, 2, 4), substr($d, 6)),
            11 => sprintf('(%s) %s-%s', substr($d, 0, 2), substr($d, 2, 5), substr($d, 7)),
            default => $fone ?: '-',
        };
    }

    /**
     * Desenha o título de um bloco com fundo cinza 5% (NT-008 §2.2.3).
     */
    protected function desenharCabecalhoBloco(float $x, float $y, string $titulo, float $w = 207.0, float $h = 3.5): void
    {
        $this->pdf->SetFillColor(242, 242, 242); // ~5% cinza
        $this->pdf->SetDrawColor(0, 0, 0);
        $this->pdf->SetLineWidth(0.176); // 0,5 pt = 0,176 mm
        $this->pdf->SetTextColor(0, 0, 0);
        $this->pdf->SetFont($this->defaultFont, 'B', 7);
        $this->pdf->SetXY($x, $y);
        $this->pdf->Cell($w, $h, $this->pdf->latin(mb_strtoupper($titulo, 'UTF-8')), 1, 0, 'L', true);
    }

    /**
     * Desenha um par label/valor padrão (label 6pt B, valor 7pt normal).
     */
    protected function desenharCampo(float $x, float $y, float $w, float $h, string $label, string $valor): void
    {
        $this->pdf->SetTextColor(0, 0, 0);
        $this->pdf->SetXY($x, $y);
        $this->pdf->SetFont($this->defaultFont, 'B', 6);
        $this->pdf->Cell($w, 2.2, $this->pdf->latin($label), 0, 0, 'L');
        $this->pdf->SetXY($x, $y + 2.3);
        $this->pdf->SetFont($this->defaultFont, '', 7);
        $this->pdf->cellFit($w, $h - 2.3, $valor, 0, 0, 'L');
    }

    /**
     * Desenha conteúdo de uma célula (label 6pt B + valor 7pt) SEM bordas individuais.
     * As bordas (apenas externas e horizontais entre linhas) são feitas por
     * {@see desenharBlocoFechado()} — alinhado com o modelo do Anexo I da NT-008,
     * que mostra blocos com bordas externas apenas, sem linhas verticais entre células.
     */
    protected function desenharCelula(float $x, float $y, float $w, float $h, string $label, string $valor): void
    {
        $this->pdf->SetTextColor(0, 0, 0);
        $this->pdf->SetFont($this->defaultFont, 'B', 6);
        $this->pdf->SetXY($x + 0.6, $y + 0.4);
        $this->pdf->Cell($w - 1.2, 2.2, $this->pdf->latin($label), 0, 0, 'L');
        $this->pdf->SetFont($this->defaultFont, '', 7);
        $this->pdf->SetXY($x + 0.6, $y + 2.8);
        $this->pdf->cellFit($w - 1.2, $h - 3.0, $valor !== '' ? $valor : '-', 0, 0, 'L');
    }

    /**
     * Versão com label CAIXA ALTA 7pt negrito + valor 7pt normal — usada
     * exclusivamente pelo bloco "DADOS DA NFS-e" (NT-008 §2.4.2 par. 2).
     */
    protected function desenharCelulaCaixaAlta(float $x, float $y, float $w, float $h, string $label, string $valor): void
    {
        $this->pdf->SetTextColor(0, 0, 0);
        $this->pdf->SetFont($this->defaultFont, 'B', 7);
        $this->pdf->SetXY($x + 0.6, $y + 0.5);
        $this->pdf->Cell($w - 1.2, 2.8, $this->pdf->latin($label), 0, 0, 'L');
        $this->pdf->SetFont($this->defaultFont, '', 7);
        $this->pdf->SetXY($x + 0.6, $y + 3.4);
        $this->pdf->cellFit($w - 1.2, $h - 3.5, $valor !== '' ? $valor : '-', 0, 0, 'L');
    }

    /**
     * Desenha o "título de bloco" no canto esquerdo da primeira linha do bloco:
     * fundo cinza 5%, label 7pt negrito caixa alta. Não cobre as demais células
     * da linha (essas continuam com fundo branco) — alinhado com o modelo do Anexo I.
     */
    protected function desenharTituloBlocoCampo(float $x, float $y, float $w, float $h, string $titulo): void
    {
        $this->pdf->SetDrawColor(0, 0, 0);
        $this->pdf->SetLineWidth(0.176);
        $this->pdf->SetFillColor(242, 242, 242);
        $this->pdf->Rect($x, $y, $w, $h, 'F');  // só preenche, sem desenhar borda (a borda do bloco cuida)
        $this->pdf->SetTextColor(0, 0, 0);
        $this->pdf->SetFont($this->defaultFont, 'B', 7);
        $this->pdf->SetXY($x + 0.6, $y);
        $this->pdf->Cell($w - 1.2, $h, $this->pdf->latin(mb_strtoupper($titulo, 'UTF-8')), 0, 0, 'L', false);
    }

    /**
     * Desenha a moldura única envolvendo TODA a área de impressão e os
     * separadores horizontais full-width entre blocos. Substitui o antigo
     * "uma moldura por bloco" — agora a página é um único retângulo com
     * linhas divisórias internas que tocam ambas as laterais.
     *
     * @param float[] $boundaries Ys absolutas dos separadores entre blocos
     */
    protected function desenharMolduraGlobal(float $yTop, float $yBottom, array $boundaries): void
    {
        $larguraTotal = $this->maxW - 2 * $this->margesq;
        $yPageBottom  = $this->maxH - $this->margsup;

        $this->pdf->SetDrawColor(0, 0, 0);
        $this->pdf->SetLineWidth(0.15);
        $this->pdf->Rect($this->margesq, $yTop, $larguraTotal, $yPageBottom - $yTop);

        $this->pdf->SetLineWidth(0.1);
        foreach ($boundaries as $yLine) {
            $this->pdf->Line($this->margesq, $yLine, $this->margesq + $larguraTotal, $yLine);
        }
    }

    /**
     * Borda externa da página inteira (NT-008 §2.2.3: "página deverá ter borda de 1 ponto").
     * 1pt = 0,353mm. Posicionada bem nas margens.
     */
    protected function desenharBordaPagina(): void
    {
        $this->pdf->SetDrawColor(0, 0, 0);
        $this->pdf->SetLineWidth(0.353);
        $this->pdf->Rect(
            $this->margesq,
            $this->margsup,
            $this->maxW - 2 * $this->margesq,
            $this->maxH - 2 * $this->margsup
        );
    }

    /**
     * Desenha uma faixa horizontal com texto centralizado — usada nas supressões
     * "...NÃO IDENTIFICADO NA NFS-e" (NT-008 §2.3.1 nota 2, §2.3.2 nota 3, §2.3.3 nota 4).
     */
    protected function desenharFaixaSupressao(float $x, float $y, float $w, float $h, string $texto): void
    {
        $this->pdf->SetTextColor(0, 0, 0);
        $this->pdf->SetFont($this->defaultFont, 'B', 7);
        $this->pdf->SetXY($x, $y);
        $this->pdf->Cell($w, $h, $this->pdf->latin($texto), 0, 0, 'C');
    }

    /**
     * Aplica marca d'água diagonal CANCELADA ou SUBSTITUÍDA (NT-008 §2.5).
     */
    protected function aplicarMarcaDagua(): void
    {
        $texto = match ($this->cStat) {
            '101' => 'CANCELADA',
            '102' => 'SUBSTITUÍDA',
            default => null,
        };
        if ($texto !== null) {
            $this->pdf->marcaDagua($texto);
        }
    }

    /**
     * Rodapé com créditos do integrador (opcional).
     */
    protected function rodapeCreditos(): void
    {
        if ($this->creditMessage === null) {
            return;
        }
        $this->pdf->SetFont($this->defaultFont, '', 5);
        $this->pdf->SetTextColor(120, 120, 120);
        $this->pdf->SetXY($this->margesq, $this->maxH - 4);
        $msg = $this->creditMessage;
        if ($this->creditPowered) {
            $msg .= ' — Powered by Hadder\\NfseNacional';
        }
        $this->pdf->Cell($this->maxW - 2 * $this->margesq, 3, $this->pdf->latin($msg), 0, 0, 'C');
        $this->pdf->SetTextColor(0, 0, 0);
    }

    // -----------------------------------------------------------------
    // STUBS dos blocos (substituídos pelos traits após validação).
    // Cada método deve retornar o $y final ocupado pelo bloco.
    // -----------------------------------------------------------------

    // blocoCabecalho()     implementado em TraitCabecalho
    // blocoPrestador()     implementado em TraitPrestador
    // blocoTomador()       implementado em TraitTomador
    // blocoDestinatario()  implementado em TraitDestinatario
    // blocoIntermediario() implementado em TraitIntermediario
    // blocoServico()       implementado em TraitServico
    protected function blocoTributacaoMunicipal(float $x, float $y): float { return $y + 19; }
    protected function blocoTributacaoFederal(float $x, float $y): float   { return $y + 13; }
    protected function blocoTributacaoIBSCBS(float $x, float $y): float    { return $y + 20; }
    protected function blocoTotaisNFSe(float $x, float $y): float          { return $y + 14; }
    protected function blocoInfoComplementares(float $x, float $y): float  { return $y + 50; }
    protected function blocoCanhoto(float $x, float $y): float             { return $y + 7; }
}
