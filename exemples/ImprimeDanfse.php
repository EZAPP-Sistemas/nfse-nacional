<?php
/**
 * Smoke test do componente Danfse.
 *
 * Valida a fundação ANTES de implementar os 12 traits dos blocos:
 *   - Autoload PSR-4 resolve Hadder\NfseNacional\Danfse\Danfse
 *   - new Danfse($xml) parseia o XML sem erro
 *   - printParameters → logoMunicipioParameters → render() retorna PDF válido
 *   - Pdf wrapper: qrCode (chillerlan), textBox (acentuação), marcaDagua (cancelada)
 *
 * Uso:
 *   php exemples/ImprimeDanfse.php [caminho-para-xml.xml] [output.pdf]
 *
 * Sem argumentos, usa um XML sintético embutido e salva em
 * exemples/output/DANFSe_smoke.pdf (e Pdf_features.pdf).
 */

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Hadder\NfseNacional\Danfse\Danfse;
use Hadder\NfseNacional\Danfse\Pdf;

$xmlPath = $argv[1] ?? null;
$outDir  = __DIR__ . '/output';
$outPath = $argv[2] ?? $outDir . '/DANFSe_smoke.pdf';

@mkdir($outDir, 0777, true);

// =====================================================================
// PARTE 1: Pipeline completo do Danfse (verifica autoload + ciclo render)
// =====================================================================

if ($xmlPath && is_readable($xmlPath)) {
    $xml = file_get_contents($xmlPath);
    fwrite(STDOUT, "→ Usando XML real: {$xmlPath}\n");
} else {
    $xml = obterXmlSintetico('100'); // Autorizada
    fwrite(STDOUT, "→ Usando XML sintético embutido (cStat=100, Autorizada)\n");
}

try {
    fwrite(STDOUT, "\n=== Parte 1: Pipeline Danfse ===\n");

    $danfse = new Danfse($xml);
    fwrite(STDOUT, "✓ Danfse instanciado\n");

    $danfse->printParameters('P', 'A4', 1.5, 1.5)
        ->setDefaultFont('helvetica')
        ->logoMunicipioParameters(null)
        ->exibirCanhoto(true)
        ->creditsIntegratorFooter('Smoke test — Sygma/EZAPP', false);
    fwrite(STDOUT, "✓ printParameters/logoParameters/credits OK (fluent)\n");

    $pdf = $danfse->render();
    fwrite(STDOUT, "✓ render() retornou " . strlen($pdf) . " bytes\n");

    if (substr($pdf, 0, 5) !== '%PDF-') {
        throw new RuntimeException('Saída não começa com %PDF- — algo errado no FPDF');
    }
    fwrite(STDOUT, "✓ Assinatura %PDF- válida\n");

    file_put_contents($outPath, $pdf);
    fwrite(STDOUT, "✓ PDF salvo: {$outPath}\n");
} catch (Throwable $e) {
    fwrite(STDERR, "\n✗ FALHA na Parte 1: {$e->getMessage()}\n");
    fwrite(STDERR, $e->getTraceAsString() . "\n");
    exit(1);
}

// =====================================================================
// PARTE 2: Variação com cStat=101 (Cancelada → marca d'água)
// =====================================================================

try {
    fwrite(STDOUT, "\n=== Parte 2: Marca d'água (cStat=101 Cancelada) ===\n");

    $xmlCancelada = obterXmlSintetico('101');
    $danfseCancelada = new Danfse($xmlCancelada);
    $danfseCancelada->printParameters('P', 'A4', 1.5, 1.5)
        ->setDefaultFont('helvetica')
        ->creditsIntegratorFooter('Smoke test (cancelada)', false);

    $pdfCancelada = $danfseCancelada->render();
    $outCancelada = $outDir . '/DANFSe_smoke_cancelada.pdf';
    file_put_contents($outCancelada, $pdfCancelada);
    fwrite(STDOUT, "✓ PDF (cancelada) salvo: {$outCancelada}\n");
} catch (Throwable $e) {
    fwrite(STDERR, "\n✗ FALHA na Parte 2: {$e->getMessage()}\n");
    fwrite(STDERR, $e->getTraceAsString() . "\n");
    exit(1);
}

// =====================================================================
// PARTE 3: Pdf wrapper standalone (qrCode + textBox + acentuação)
// =====================================================================

try {
    fwrite(STDOUT, "\n=== Parte 3: Wrapper Pdf — qrCode, textBox, latin ===\n");

    $p = new Pdf('P', 'mm', 'A4');
    $p->SetMargins(10, 10, 10);
    $p->SetAutoPageBreak(false);
    $p->AddPage();

    // Título
    $p->SetFont('helvetica', 'B', 14);
    $p->SetXY(10, 10);
    $p->Cell(190, 8, $p->latin('Pdf wrapper — smoke test'), 0, 1, 'C');

    // textBox com acentuação portuguesa
    $p->SetFont('helvetica', '', 9);
    $p->textBox(10, 25, 90, 25,
        "Texto com acentuação: ç ã õ é à ê ó ú. "
        . "Lorem ipsum dolor sit amet, consectetur adipiscing elit, "
        . "sed do eiusmod tempor incididunt ut labore et dolore magna aliqua.",
        'L', 'T', true, false
    );
    fwrite(STDOUT, "✓ textBox + acentuação executados\n");

    // cellFit com texto que estoura
    $p->SetFont('helvetica', '', 8);
    $p->SetXY(10, 55);
    $p->cellFit(40, 5, 'Nome muito longo que precisa encolher fonte', 1);
    fwrite(STDOUT, "✓ cellFit executado\n");

    // QR Code
    $p->SetXY(110, 25);
    $p->SetFont('helvetica', 'B', 8);
    $p->Cell(80, 4, 'QR Code (chillerlan/php-qrcode):', 0, 1);
    $chave = '31431042205405941000129000000000446626055512757118';
    $p->qrCode(110, 32, 25, 'https://www.nfse.gov.br/ConsultaPublica/?tpc=1&chave=' . $chave);
    fwrite(STDOUT, "✓ qrCode renderizado\n");

    // Marca d'água numa segunda página
    $p->AddPage();
    $p->SetFont('helvetica', '', 12);
    $p->SetXY(10, 10);
    $p->Cell(190, 8, $p->latin('Página 2 — teste de marca d\'água'), 0, 1, 'C');
    $p->marcaDagua('SUBSTITUÍDA');
    fwrite(STDOUT, "✓ marcaDagua executada\n");

    $featuresPath = $outDir . '/Pdf_features.pdf';
    $p->Output('F', $featuresPath);
    fwrite(STDOUT, "✓ PDF de features salvo: {$featuresPath}\n");
} catch (Throwable $e) {
    fwrite(STDERR, "\n✗ FALHA na Parte 3: {$e->getMessage()}\n");
    fwrite(STDERR, $e->getTraceAsString() . "\n");
    exit(1);
}

// =====================================================================
// PARTE 4: XML completo — valida que as linhas suprimíveis (notas */**)
// REAPARECEM quando há dados (Endereço/E-mail, ISSQN L2/L3, dest, interm).
// =====================================================================

try {
    fwrite(STDOUT, "\n=== Parte 4: XML completo (linhas suprimíveis reaparecem) ===\n");

    $danfseCompleto = new Danfse(obterXmlSinteticoCompleto());
    $danfseCompleto->printParameters('P', 'A4', 1.5, 1.5)
        ->setDefaultFont('helvetica')
        ->logoMunicipioParameters(null)
        ->exibirCanhoto(true)
        ->creditsIntegratorFooter('Smoke test — completo', false);

    $pdfCompleto = $danfseCompleto->render();
    $outCompleto = $outDir . '/DANFSe_smoke_completo.pdf';
    file_put_contents($outCompleto, $pdfCompleto);
    fwrite(STDOUT, "✓ PDF (completo) salvo: {$outCompleto}\n");
} catch (Throwable $e) {
    fwrite(STDERR, "\n✗ FALHA na Parte 4: {$e->getMessage()}\n");
    fwrite(STDERR, $e->getTraceAsString() . "\n");
    exit(1);
}

fwrite(STDOUT, "\n=== SMOKE TEST OK ===\n");
fwrite(STDOUT, "Arquivos gerados:\n");
fwrite(STDOUT, "  - {$outPath}                     (pipeline Danfse, cStat=100)\n");
fwrite(STDOUT, "  - {$outDir}/DANFSe_smoke_cancelada.pdf  (com marca d'água CANCELADA)\n");
fwrite(STDOUT, "  - {$outDir}/DANFSe_smoke_completo.pdf   (todas as linhas suprimíveis preenchidas)\n");
fwrite(STDOUT, "  - {$outDir}/Pdf_features.pdf            (qrCode + textBox + acentuação)\n");

// =====================================================================
// XML sintético embutido — não cobre todos os campos da NT-008, só o
// suficiente para o loadDoc() popular as propriedades do Danfse.
// =====================================================================

function obterXmlSintetico(string $cStat): string
{
    return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<NFSe xmlns="http://www.sped.fazenda.gov.br/nfse">
  <infNFSe Id="NFS31431042205405941000129000000000446626055512757118">
    <xLocEmi>Monte Carmelo</xLocEmi>
    <cStat>{$cStat}</cStat>
    <nNFSe>4466</nNFSe>
    <dhProc>2026-05-22T14:45:43-03:00</dhProc>
    <ambGer>1</ambGer>
    <DPS>
      <infDPS>
        <tpAmb>2</tpAmb>
        <cLocEmi>3143104</cLocEmi>
        <nDPS>47</nDPS>
        <serie>6</serie>
        <dhEmi>2026-05-22T14:45:00-03:00</dhEmi>
        <tpEmit>1</tpEmit>
        <dCompet>2026-05-22</dCompet>
        <prest>
          <CNPJ>05405941000129</CNPJ>
          <xNome>JULIANO MARCAL LTDA — teste ç ã õ é</xNome>
          <IM>8933</IM>
          <end>
            <endNac>
              <cMun>3143104</cMun>
              <CEP>38500000</CEP>
            </endNac>
            <xLgr>DOS MUNDINS</xLgr>
            <nro>328</nro>
            <xBairro>CENTRO</xBairro>
          </end>
          <fone>3438423398</fone>
          <email>brasilcontabilidademg@gmail.com</email>
          <regTrib>
            <opSimpNac>1</opSimpNac>
          </regTrib>
        </prest>
        <toma>
          <CNPJ>43461062000103</CNPJ>
          <xNome>AGILIZA TRANSPORTES E LOGISTICA LTDA</xNome>
          <end>
            <endNac>
              <cMun>5107875</cMun>
              <CEP>78850000</CEP>
            </endNac>
            <xLgr>R SANTO AMARO</xLgr>
            <nro>515</nro>
            <xCpl>SALA 1A</xCpl>
            <xBairro>CIDADE PRIMAVERA I</xBairro>
          </end>
        </toma>
        <serv>
          <locPrest>
            <cLocPrestacao>5107875</cLocPrestacao>
          </locPrest>
          <cServ>
            <cTribNac>010101</cTribNac>
            <xTribNac>Análise e desenvolvimento de sistemas</xTribNac>
            <cNBS>111032200</cNBS>
            <xDescServ>Licenciamento de Direito de Uso de Software — smoke test do componente</xDescServ>
          </cServ>
        </serv>
        <valores>
          <vServPrest>
            <vServ>281.00</vServ>
          </vServPrest>
          <trib>
            <tribMun>
              <tribISSQN>1</tribISSQN>
              <pAliq>3.00</pAliq>
              <tpRetISSQN>2</tpRetISSQN>
            </tribMun>
          </trib>
        </valores>
      </infDPS>
    </DPS>
  </infNFSe>
</NFSe>
XML;
}

/**
 * XML sintético COMPLETO — preenche os campos suprimíveis (notas de asterisco)
 * para validar que as linhas reaparecem: Endereço/E-mail em todas as pessoas,
 * ISSQN L2 (regEspTrib/tpImunidade/tpSusp/nProcesso) e L3 (tpBM/cBM/vTotDR/
 * vDescIncond), destinatário (IBSCBS/dest) e intermediário (interm).
 */
function obterXmlSinteticoCompleto(): string
{
    return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<NFSe xmlns="http://www.sped.fazenda.gov.br/nfse">
  <infNFSe Id="NFS31431042205405941000129000000000446626055512757118">
    <xLocEmi>Monte Carmelo</xLocEmi>
    <cStat>100</cStat>
    <nNFSe>4466</nNFSe>
    <dhProc>2026-05-22T14:45:43-03:00</dhProc>
    <ambGer>1</ambGer>
    <DPS>
      <infDPS>
        <tpAmb>2</tpAmb>
        <cLocEmi>3143104</cLocEmi>
        <nDPS>47</nDPS>
        <serie>6</serie>
        <dhEmi>2026-05-22T14:45:00-03:00</dhEmi>
        <tpEmit>1</tpEmit>
        <dCompet>2026-05-22</dCompet>
        <prest>
          <CNPJ>05405941000129</CNPJ>
          <xNome>JULIANO MARCAL LTDA — teste ç ã õ é</xNome>
          <IM>8933</IM>
          <end>
            <endNac><cMun>3143104</cMun><CEP>38500000</CEP></endNac>
            <xLgr>DOS MUNDINS</xLgr><nro>328</nro><xBairro>CENTRO</xBairro>
          </end>
          <fone>3438423398</fone>
          <email>brasilcontabilidademg@gmail.com</email>
          <regTrib><opSimpNac>1</opSimpNac></regTrib>
        </prest>
        <toma>
          <CNPJ>43461062000103</CNPJ>
          <xNome>AGILIZA TRANSPORTES E LOGISTICA LTDA</xNome>
          <end>
            <endNac><cMun>5107875</cMun><CEP>78850000</CEP></endNac>
            <xLgr>R SANTO AMARO</xLgr><nro>515</nro><xBairro>CIDADE PRIMAVERA I</xBairro>
          </end>
          <email>contato@agiliza.com.br</email>
        </toma>
        <interm>
          <CNPJ>11222333000144</CNPJ>
          <xNome>MARKETPLACE INTERMEDIA LTDA</xNome>
          <IM>5512</IM>
          <end>
            <endNac><cMun>3550308</cMun><CEP>01310100</CEP></endNac>
            <xLgr>AV PAULISTA</xLgr><nro>1000</nro><xBairro>BELA VISTA</xBairro>
          </end>
          <fone>1133334444</fone>
          <email>fiscal@intermedia.com.br</email>
        </interm>
        <serv>
          <locPrest><cLocPrestacao>5107875</cLocPrestacao></locPrest>
          <cServ>
            <cTribNac>010101</cTribNac>
            <xTribNac>Análise e desenvolvimento de sistemas</xTribNac>
            <cNBS>111032200</cNBS>
            <xDescServ>Licenciamento de Direito de Uso de Software — smoke test completo</xDescServ>
          </cServ>
        </serv>
        <valores>
          <vServPrest>
            <vServ>1000.00</vServ>
          </vServPrest>
          <vDescIncond>50.00</vDescIncond>
          <trib>
            <tribMun>
              <tribISSQN>1</tribISSQN>
              <regEspTrib>3</regEspTrib>
              <tpImunidade>1</tpImunidade>
              <tpSusp>1</tpSusp>
              <nProcesso>0012345-67.2026.8.13.0433</nProcesso>
              <tpBM>2</tpBM>
              <cBM>123456</cBM>
              <vTotDR>30.00</vTotDR>
              <vBC>920.00</vBC>
              <pAliq>3.00</pAliq>
              <tpRetISSQN>2</tpRetISSQN>
              <vISSQN>27.60</vISSQN>
            </tribMun>
            <tribFed>
              <piscofins>
                <tpRetPISCofins>7</tpRetPISCofins>
                <vPis>6.50</vPis>
                <vCofins>30.00</vCofins>
              </piscofins>
              <vRetCP>110.00</vRetCP>
              <vRetIRRF>15.00</vRetIRRF>
              <vRetCSLL>10.00</vRetCSLL>
            </tribFed>
          </trib>
        </valores>
        <IBSCBS>
          <cIndOp>1</cIndOp>
          <CST>00</CST>
          <cClassTrib>000001</cClassTrib>
          <vBC>1000.00</vBC>
          <dest>
            <CNPJ>99888777000166</CNPJ>
            <xNome>DESTINATARIO FINAL LTDA</xNome>
            <end>
              <endNac><cMun>3304557</cMun><CEP>20040002</CEP></endNac>
              <xLgr>AV RIO BRANCO</xLgr><nro>156</nro><xBairro>CENTRO</xBairro>
            </end>
            <email>dest@final.com.br</email>
          </dest>
          <gIBS>
            <vIBS>85.00</vIBS>
            <gIBSUF><pAliq>10.00</pAliq><pAliqEfet>9.50</pAliqEfet><vIBS>50.00</vIBS></gIBSUF>
            <gIBSMun><pAliq>7.00</pAliq><pAliqEfet>6.80</pAliqEfet><vIBS>35.00</vIBS></gIBSMun>
          </gIBS>
          <gCBS><pAliq>9.00</pAliq><pAliqEfet>8.80</pAliqEfet><vCBS>88.00</vCBS></gCBS>
        </IBSCBS>
      </infDPS>
    </DPS>
  </infNFSe>
</NFSe>
XML;
}
