<?php

namespace Hadder\NfseNacional\Danfse;

// NOTA: os traits de cada bloco serão adicionados após validação do esqueleto.
// Quando criados, descomente os "use" abaixo e remova os stubs internos
// no final desta classe.
// use Hadder\NfseNacional\Danfse\Traits\TraitCabecalho;
// use Hadder\NfseNacional\Danfse\Traits\TraitPrestador;
// use Hadder\NfseNacional\Danfse\Traits\TraitTomador;
// use Hadder\NfseNacional\Danfse\Traits\TraitDestinatario;
// use Hadder\NfseNacional\Danfse\Traits\TraitIntermediario;
// use Hadder\NfseNacional\Danfse\Traits\TraitServico;
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
    // Quando os traits forem criados:
    // use TraitCabecalho;
    // use TraitPrestador;
    // use TraitTomador;
    // use TraitDestinatario;
    // use TraitIntermediario;
    // use TraitServico;
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

        $y = $this->margsup;
        $y = $this->blocoCabecalho($this->margesq, $y);             // §2.1.1-2, §2.4.3
        $y = $this->blocoPrestador($this->margesq, $y);             // §2.1.3
        $y = $this->blocoTomador($this->margesq, $y);               // §2.1.4 + nota 2
        $y = $this->blocoDestinatario($this->margesq, $y);          // §2.1.5 + notas 2, 3
        $y = $this->blocoIntermediario($this->margesq, $y);         // §2.1.6 + nota 2
        $y = $this->blocoServico($this->margesq, $y);               // §2.1.7
        $y = $this->blocoTributacaoMunicipal($this->margesq, $y);   // §2.1.8 + nota 4
        $y = $this->blocoTributacaoFederal($this->margesq, $y);     // §2.1.9 + nota 6
        $y = $this->blocoTributacaoIBSCBS($this->margesq, $y);      // §2.1.10
        $y = $this->blocoTotaisNFSe($this->margesq, $y);            // §2.1.11
        $y = $this->blocoInfoComplementares($this->margesq, $y);    // §2.1.12

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

    protected function blocoCabecalho(float $x, float $y): float           { return $y + 32; }
    protected function blocoPrestador(float $x, float $y): float           { return $y + 26; }
    protected function blocoTomador(float $x, float $y): float             { return $y + 19; }
    protected function blocoDestinatario(float $x, float $y): float        { return $y + 19; }
    protected function blocoIntermediario(float $x, float $y): float       { return $y + 19; }
    protected function blocoServico(float $x, float $y): float             { return $y + 17; }
    protected function blocoTributacaoMunicipal(float $x, float $y): float { return $y + 19; }
    protected function blocoTributacaoFederal(float $x, float $y): float   { return $y + 13; }
    protected function blocoTributacaoIBSCBS(float $x, float $y): float    { return $y + 20; }
    protected function blocoTotaisNFSe(float $x, float $y): float          { return $y + 14; }
    protected function blocoInfoComplementares(float $x, float $y): float  { return $y + 50; }
    protected function blocoCanhoto(float $x, float $y): float             { return $y + 7; }
}
