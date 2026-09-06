<?php

require_once 'config.php';
require_once 'auth.php';

$usuarioLogado = exigirAutenticacao();
exigirPermissao(['ATENDIMENTO']);

$id = (int) ($_GET['id'] ?? 0);

if ($id <= 0) {
    die('Nota Fiscal não informada.');
}

$stmt = $pdo->prepare("
    SELECT nf.*, os.placa, os.data_abertura, os.desconto, v.modelo, v.marca, v.ano, v.cor, v.km_atual,
           c.telefone AS cliente_telefone, c.email AS cliente_email, c.endereco AS cliente_endereco, c.cidade AS cliente_cidade, c.estado AS cliente_estado
    FROM notas_fiscais nf
    LEFT JOIN ordens_servico os ON os.id = nf.ordem_servico_id
    LEFT JOIN veiculos v ON v.placa = os.placa
    LEFT JOIN clientes c ON c.id = v.cliente_id
    WHERE nf.id = ?
    LIMIT 1
");
$stmt->execute([$id]);
$nf = $stmt->fetch();

if (!$nf) {
    die('Nota Fiscal não encontrada.');
}

// Se tiver O.S. associada, busca lista de serviços e peças
$listaServicos = [];
$listaPecas = [];

if ($nf['ordem_servico_id']) {
    $stmtS = $pdo->prepare("SELECT * FROM servicos WHERE ordem_servico_id = ?");
    $stmtS->execute([$nf['ordem_servico_id']]);
    $listaServicos = $stmtS->fetchAll();

    $stmtP = $pdo->prepare("
        SELECT osp.*, p.codigo, p.nome, p.fabricante, p.unidade
        FROM os_pecas osp
        JOIN pecas p ON p.id = osp.peca_id
        WHERE osp.ordem_servico_id = ?
    ");
    $stmtP->execute([$nf['ordem_servico_id']]);
    $listaPecas = $stmtP->fetchAll();
}

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nota Fiscal <?= htmlspecialchars($nf['numero_nf']) ?> - EDTEC-SOLUTION</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .nf-box {
            background: #fff;
            border: 2px solid #0f172a;
            border-radius: 8px;
            padding: 25px;
            max-width: 900px;
            margin: 0 auto;
            color: #0f172a;
        }
        .nf-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            border-bottom: 2px solid #0f172a;
            padding-bottom: 15px;
            margin-bottom: 20px;
        }
        .nf-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin-bottom: 20px;
        }
        .nf-secao {
            background: #f8fafc;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            padding: 12px;
        }
        .nf-secao h4 {
            margin-top: 0;
            margin-bottom: 8px;
            color: #1e293b;
            border-bottom: 1px dashed #cbd5e1;
            padding-bottom: 4px;
            font-size: 0.95rem;
        }
        .tabela-nf {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        .tabela-nf th, .tabela-nf td {
            border: 1px solid #cbd5e1;
            padding: 8px 10px;
            font-size: 0.9rem;
        }
        .tabela-nf th {
            background: #f1f5f9;
        }
        @media print {
            header, .no-print, .acoes-print { display: none !important; }
            body { background: white !important; margin: 0; }
            main { padding: 0 !important; }
            .nf-box { border: 1px solid #000 !important; border-radius: 0; width: 100%; }
        }
    </style>
</head>
<body>

<?php
$subtitulo = 'Visualização de Nota Fiscal';
include 'header.php';
?>

<main>
    <div class="no-print" style="max-width: 900px; margin: 0 auto 15px auto; display: flex; justify-content: space-between; align-items: center;">
        <a href="notas.php" class="botao secundario">← Voltar para Lista de NFs</a>
        <button onclick="window.print()" class="botao" style="background: #16a34a; font-weight: bold;">🖨️ Imprimir Nota Fiscal</button>
    </div>

    <div class="nf-box">
        <!-- Cabeçalho da Nota Fiscal -->
        <div class="nf-header">
            <div>
                <h2 style="margin: 0; color: #0f172a;">🔧 EDTEC-SOLUTION OFICINA MECÂNICA</h2>
                <p style="margin: 3px 0 0 0; font-size: 0.9rem;">CNPJ: 12.345.678/0001-99 | Inscrição Municipal: 98765-0</p>
                <p style="margin: 2px 0 0 0; font-size: 0.85rem; color: #475569;">Av. Mecânica Central, 1000 - Fortaleza / CE - Tel: (85) 99999-9999</p>
            </div>

            <div style="text-align: right;">
                <div style="background: #0f172a; color: white; padding: 6px 12px; border-radius: 6px; font-weight: bold; font-size: 1.1rem; display: inline-block;">
                    <?= htmlspecialchars($nf['tipo']) ?>
                </div>
                <h3 style="margin: 6px 0 0 0; font-size: 1.3rem; color: #2563eb;"><?= htmlspecialchars($nf['numero_nf']) ?></h3>
                <small style="color: #64748b;">Emissão: <?= date('d/m/Y H:i', strtotime($nf['data_emissao'])) ?></small>
            </div>
        </div>

        <?php if ($nf['status'] === 'CANCELADA'): ?>
            <div style="background: #fee2e2; border: 2px solid #ef4444; color: #991b1b; padding: 10px; border-radius: 6px; text-align: center; font-weight: bold; margin-bottom: 20px; font-size: 1.2rem;">
                ⚠️ ESTA NOTA FISCAL FOI CANCELADA
            </div>
        <?php endif; ?>

        <!-- Dados do Tomador / Cliente e Veículo -->
        <div class="nf-grid">
            <div class="nf-secao">
                <h4>👤 TOMADOR DO SERVIÇO / CLIENTE</h4>
                <p style="margin: 3px 0;"><strong>Nome / Razão Social:</strong> <?= htmlspecialchars($nf['cliente_nome']) ?></p>
                <p style="margin: 3px 0;"><strong>CPF / CNPJ:</strong> <?= htmlspecialchars($nf['cliente_cpf_cnpj'] ?? 'Não informado') ?></p>
                <p style="margin: 3px 0;"><strong>Telefone / Email:</strong> <?= htmlspecialchars($nf['cliente_telefone'] ?? '-') ?> | <?= htmlspecialchars($nf['cliente_email'] ?? '-') ?></p>
                <p style="margin: 3px 0;"><strong>Endereço:</strong> <?= htmlspecialchars(($nf['cliente_endereco'] ?? '-') . ' - ' . ($nf['cliente_cidade'] ?? '') . '/' . ($nf['cliente_estado'] ?? '')) ?></p>
            </div>

            <div class="nf-secao">
                <h4>🚗 DADOS DO VEÍCULO & O.S. VINCULADA</h4>
                <p style="margin: 3px 0;"><strong>Ordem de Serviço:</strong> <?= $nf['ordem_servico_id'] ? '#' . sprintf('%05d', $nf['ordem_servico_id']) : 'Venda Direta / Sem O.S.' ?></p>
                <p style="margin: 3px 0;"><strong>Placa do Veículo:</strong> <span style="font-weight: bold; background: #e2e8f0; padding: 2px 6px; border-radius: 4px;"><?= htmlspecialchars($nf['placa'] ?? 'N/A') ?></span></p>
                <p style="margin: 3px 0;"><strong>Veículo:</strong> <?= htmlspecialchars(($nf['marca'] ?? '') . ' ' . ($nf['modelo'] ?? '')) ?></p>
                <p style="margin: 3px 0;"><strong>Chave de Acesso Autenticação:</strong> <br><small style="font-family: monospace; font-size: 0.75rem; color: #475569;"><?= htmlspecialchars($nf['chave_acesso']) ?></small></p>
            </div>
        </div>

        <!-- Discriminação dos Serviços Prestados -->
        <div class="nf-secao" style="margin-bottom: 20px;">
            <h4>🛠️ DISCRIMINAÇÃO DOS SERVIÇOS & COMPONENTES</h4>
            
            <?php if ($nf['observacoes']): ?>
                <p style="margin: 5px 0 10px 0; font-size: 0.9rem; background: #fff; padding: 8px; border: 1px solid #e2e8f0; border-radius: 4px;">
                    <?= nl2br(htmlspecialchars($nf['observacoes'])) ?>
                </p>
            <?php endif; ?>

            <table class="tabela-nf">
                <thead>
                    <tr>
                        <th>Item / Descrição</th>
                        <th style="width: 80px; text-align: center;">Tipo</th>
                        <th style="width: 70px; text-align: center;">Qtd</th>
                        <th style="width: 110px; text-align: right;">Valor Unit.</th>
                        <th style="width: 120px; text-align: right;">Subtotal</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($listaServicos): ?>
                        <?php foreach ($listaServicos as $s): ?>
                            <tr>
                                <td><?= htmlspecialchars($s['descricao']) ?></td>
                                <td style="text-align: center;"><span style="background: #e0f2fe; color: #0369a1; padding: 2px 6px; border-radius: 4px; font-size: 0.75rem; font-weight: bold;">Serviço</span></td>
                                <td style="text-align: center;"><?= number_format($s['quantidade'], 0) ?></td>
                                <td style="text-align: right;">R$ <?= number_format($s['valor_unitario'], 2, ',', '.') ?></td>
                                <td style="text-align: right;">R$ <?= number_format($s['valor_total'], 2, ',', '.') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>

                    <?php if ($listaPecas): ?>
                        <?php foreach ($listaPecas as $p): ?>
                            <tr>
                                <td><?= htmlspecialchars($p['nome']) ?> <small style="color: #64748b;">(Cod: <?= htmlspecialchars($p['codigo']) ?>)</small></td>
                                <td style="text-align: center;"><span style="background: #fef3c7; color: #92400e; padding: 2px 6px; border-radius: 4px; font-size: 0.75rem; font-weight: bold;">Peça</span></td>
                                <td style="text-align: center;"><?= $p['quantidade'] ?> <?= htmlspecialchars($p['unidade']) ?></td>
                                <td style="text-align: right;">R$ <?= number_format($p['preco_unitario'], 2, ',', '.') ?></td>
                                <td style="text-align: right;">R$ <?= number_format($p['valor_total'], 2, ',', '.') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>

                    <?php if (!$listaServicos && !$listaPecas): ?>
                        <tr>
                            <td>Serviços e Manutenção Automotiva Geral</td>
                            <td style="text-align: center;">Serviço</td>
                            <td style="text-align: center;">1</td>
                            <td style="text-align: right;">R$ <?= number_format($nf['valor_servicos'], 2, ',', '.') ?></td>
                            <td style="text-align: right;">R$ <?= number_format($nf['valor_servicos'], 2, ',', '.') ?></td>
                        </tr>
                        <?php if ($nf['valor_pecas'] > 0): ?>
                            <tr>
                                <td>Peças e Componentes de Reposição</td>
                                <td style="text-align: center;">Peça</td>
                                <td style="text-align: center;">1</td>
                                <td style="text-align: right;">R$ <?= number_format($nf['valor_pecas'], 2, ',', '.') ?></td>
                                <td style="text-align: right;">R$ <?= number_format($nf['valor_pecas'], 2, ',', '.') ?></td>
                            </tr>
                        <?php endif; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- CÁLCULO DO IMPOSTO & TOTALIZADOR -->
        <div class="nf-grid">
            <div class="nf-secao">
                <h4>📊 TRIBUTAÇÃO E RETENÇÕES (ISS)</h4>
                <p style="margin: 4px 0;"><strong>Base de Cálculo ISS (Serviços):</strong> R$ <?= number_format($nf['valor_servicos'], 2, ',', '.') ?></p>
                <p style="margin: 4px 0;"><strong>Alíquota ISS Aplicada:</strong> <?= number_format($nf['aliquota_imposto'], 2, ',', '.') ?>%</p>
                <p style="margin: 4px 0;"><strong>Valor Estimado de Impostos:</strong> <strong style="color: #2563eb;">R$ <?= number_format($nf['valor_impostos'], 2, ',', '.') ?></strong></p>
                <small style="color: #64748b;">Empresa optante pelo Simples Nacional. Tributos calculados na forma da Lei 12.741/2012.</small>
            </div>

            <div class="nf-secao" style="background: #f0fdf4; border-color: #86efac;">
                <h4>💰 TOTALIZAÇÃO DO DOCUMENTO</h4>
                <p style="display: flex; justify-content: space-between; margin: 4px 0;">
                    <span>Total Serviços:</span>
                    <strong>R$ <?= number_format($nf['valor_servicos'], 2, ',', '.') ?></strong>
                </p>
                <p style="display: flex; justify-content: space-between; margin: 4px 0;">
                    <span>Total Peças:</span>
                    <strong>R$ <?= number_format($nf['valor_pecas'], 2, ',', '.') ?></strong>
                </p>
                <hr style="border-top: 1px solid #86efac;">
                <div style="display: flex; justify-content: space-between; font-size: 1.25rem; color: #166534; font-weight: bold; margin-top: 5px;">
                    <span>VALOR TOTAL DA NF:</span>
                    <span>R$ <?= number_format($nf['valor_total'], 2, ',', '.') ?></span>
                </div>
            </div>
        </div>

        <!-- Rodapé da NF -->
        <div style="border-top: 2px solid #0f172a; padding-top: 12px; margin-top: 20px; font-size: 0.8rem; color: #475569; text-align: center;">
            <p style="margin: 0;">DOCUMENTO EMITIDO POR ME OU EPP OPTANTE PELO SIMPLES NACIONAL. NÃO GERA DIREITO A CRÉDITO FISCAL DE IPI.</p>
            <p style="margin: 3px 0 0 0;">EDTEC-SOLUTION OFICINA MECÂNICA - Sistema de Gestão de Oficina Lab-Mecânica</p>
        </div>
    </div>
</main>

</body>
</html>
