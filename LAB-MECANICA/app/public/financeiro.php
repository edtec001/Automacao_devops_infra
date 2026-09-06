<?php

require_once 'config.php';
require_once 'auth.php';

$usuarioLogado = exigirAutenticacao();
exigirPermissao(['ADMIN', 'GERENTE']);

$erroMsg = '';
$sucessoMsg = '';

// 1. Processar Cadastro de Nova Despesa
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao']) && $_POST['acao'] === 'nova_despesa') {
    $descricao = trim($_POST['descricao'] ?? '');
    $categoria = $_POST['categoria'] ?? 'OUTROS';
    $valor = (float) str_replace(',', '.', $_POST['valor'] ?? '0');
    $dataVencimento = $_POST['data_vencimento'] ?? date('Y-m-d');
    $status = $_POST['status'] ?? 'PAGO';
    $observacao = trim($_POST['observacao'] ?? '');
    $dataPagamento = ($status === 'PAGO') ? date('Y-m-d') : null;

    if ($descricao === '' || $valor <= 0) {
        $erroMsg = 'Informe uma descrição e um valor válido para a despesa.';
    } else {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO despesas (descricao, categoria, valor, data_vencimento, data_pagamento, status, observacao)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$descricao, $categoria, $valor, $dataVencimento, $dataPagamento, $status, $observacao]);
            $sucessoMsg = 'Despesa cadastrada com sucesso!';
        } catch (Exception $e) {
            $erroMsg = 'Erro ao cadastrar despesa: ' . $e->getMessage();
        }
    }
}

// 2. Processar Alteração de Status da Despesa (PAGO / PENDENTE)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao']) && $_POST['acao'] === 'alterar_status_despesa') {
    $despesaId = (int) ($_POST['despesa_id'] ?? 0);
    $novoStatus = $_POST['novo_status'] ?? 'PAGO';
    $dataPagamento = ($novoStatus === 'PAGO') ? date('Y-m-d') : null;

    try {
        $stmt = $pdo->prepare("UPDATE despesas SET status = ?, data_pagamento = ? WHERE id = ?");
        $stmt->execute([$novoStatus, $dataPagamento, $despesaId]);
        $sucessoMsg = 'Status da despesa atualizado!';
    } catch (Exception $e) {
        $erroMsg = 'Erro ao atualizar despesa: ' . $e->getMessage();
    }
}

// 3. Processar Exclusão de Despesa
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao']) && $_POST['acao'] === 'excluir_despesa') {
    $despesaId = (int) ($_POST['despesa_id'] ?? 0);
    try {
        $stmt = $pdo->prepare("DELETE FROM despesas WHERE id = ?");
        $stmt->execute([$despesaId]);
        $sucessoMsg = 'Despesa removida.';
    } catch (Exception $e) {
        $erroMsg = 'Erro ao excluir despesa: ' . $e->getMessage();
    }
}

// 4. Definição do Período de Filtro
$periodo = $_GET['periodo'] ?? 'mes_atual';
$dataInicio = $_GET['data_inicio'] ?? '';
$dataFim = $_GET['data_fim'] ?? '';

switch ($periodo) {
    case 'hoje':
        $dataInicio = date('Y-m-d 00:00:00');
        $dataFim = date('Y-m-d 23:59:59');
        break;
    case 'mes_anterior':
        $dataInicio = date('Y-m-01 00:00:00', strtotime('first day of last month'));
        $dataFim = date('Y-m-t 23:59:59', strtotime('last day of last month'));
        break;
    case '30_dias':
        $dataInicio = date('Y-m-d 00:00:00', strtotime('-30 days'));
        $dataFim = date('Y-m-d 23:59:59');
        break;
    case 'ano_atual':
        $dataInicio = date('Y-01-01 00:00:00');
        $dataFim = date('Y-12-31 23:59:59');
        break;
    case 'custom':
        if ($dataInicio) $dataInicio = date('Y-m-d 00:00:00', strtotime($dataInicio));
        if ($dataFim) $dataFim = date('Y-m-d 23:59:59', strtotime($dataFim));
        break;
    case 'mes_atual':
    default:
        $periodo = 'mes_atual';
        $dataInicio = date('Y-m-01 00:00:00');
        $dataFim = date('Y-m-t 23:59:59');
        break;
}

// 5. Cálculo do Faturamento Bruto (O.S. Finalizadas / Pagas no período)
$stmtFat = $pdo->prepare("
    SELECT 
        COALESCE(SUM(valor_servicos), 0) AS total_servicos,
        COALESCE(SUM(valor_pecas), 0) AS total_pecas,
        COALESCE(SUM(desconto), 0) AS total_desconto,
        COALESCE(SUM(valor_total), 0) AS total_faturamento,
        COUNT(id) AS qtd_os
    FROM ordens_servico
    WHERE (status = 'FINALIZADA' OR valor_pago > 0)
    AND (data_fechamento BETWEEN ? AND ? OR data_pagamento BETWEEN ? AND ?)
");
$stmtFat->execute([$dataInicio, $dataFim, $dataInicio, $dataFim]);
$resFat = $stmtFat->fetch();

$faturamentoBruto = (float) $resFat['total_faturamento'];
$totalServicos = (float) $resFat['total_servicos'];
$totalPecas = (float) $resFat['total_pecas'];
$totalDesconto = (float) $resFat['total_desconto'];
$qtdOS = (int) $resFat['qtd_os'];

// 6. Cálculo do Custo das Peças Vendidas (COGS)
$stmtCusto = $pdo->prepare("
    SELECT COALESCE(SUM(osp.quantidade * p.preco_custo), 0) AS custo_total_pecas
    FROM os_pecas osp
    JOIN pecas p ON p.id = osp.peca_id
    JOIN ordens_servico os ON os.id = osp.ordem_servico_id
    WHERE (os.status = 'FINALIZADA' OR os.valor_pago > 0)
    AND (os.data_fechamento BETWEEN ? AND ? OR os.data_pagamento BETWEEN ? AND ?)
");
$stmtCusto->execute([$dataInicio, $dataFim, $dataInicio, $dataFim]);
$custoPecas = (float) $stmtCusto->fetchColumn();

// 7. Cálculo das Despesas Operacionais (Pagas no período)
$stmtDesp = $pdo->prepare("
    SELECT COALESCE(SUM(valor), 0)
    FROM despesas
    WHERE status = 'PAGO'
    AND (data_pagamento BETWEEN ? AND ? OR data_vencimento BETWEEN ? AND ?)
");
$stmtDesp->execute([substr($dataInicio, 0, 10), substr($dataFim, 0, 10), substr($dataInicio, 0, 10), substr($dataFim, 0, 10)]);
$totalDespesas = (float) $stmtDesp->fetchColumn();

// 8. Resultados Consolidados
$lucroBruto = $faturamentoBruto - $custoPecas; // Margem de Contribuição
$resultadoLiquido = $lucroBruto - $totalDespesas; // Lucro ou Prejuízo Líquido
$margemLucro = ($faturamentoBruto > 0) ? (($resultadoLiquido / $faturamentoBruto) * 100) : 0;
$houveLucro = ($resultadoLiquido >= 0);

// Busca Lista de Despesas para Tabela
$stmtListaDespesas = $pdo->prepare("
    SELECT * FROM despesas
    WHERE data_vencimento BETWEEN ? AND ? OR data_pagamento BETWEEN ? AND ?
    ORDER BY data_vencimento DESC
");
$stmtListaDespesas->execute([substr($dataInicio, 0, 10), substr($dataFim, 0, 10), substr($dataInicio, 0, 10), substr($dataFim, 0, 10)]);
$listaDespesas = $stmtListaDespesas->fetchAll();

// Busca Detalhamento de O.S. Faturadas
$stmtListaOS = $pdo->prepare("
    SELECT os.*, v.modelo, v.marca, c.nome AS cliente_nome
    FROM ordens_servico os
    JOIN veiculos v ON v.placa = os.placa
    LEFT JOIN clientes c ON c.id = v.cliente_id
    WHERE (os.status = 'FINALIZADA' OR os.valor_pago > 0)
    AND (os.data_fechamento BETWEEN ? AND ? OR os.data_pagamento BETWEEN ? AND ?)
    ORDER BY os.id DESC
");
$stmtListaOS->execute([$dataInicio, $dataFim, $dataInicio, $dataFim]);
$listaOSFaturadas = $stmtListaOS->fetchAll();

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Demonstrativo Financeiro & Lucro/Prejuízo - EDTEC-SOLUTION</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .grid-kpis {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 18px;
            margin-bottom: 30px;
        }
        .card-kpi {
            background: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 3px 10px rgba(0, 0, 0, .08);
        }
        .card-kpi span.icone {
            font-size: 2rem;
        }
        .card-kpi h3 {
            font-size: 1.8rem;
            margin: 8px 0 4px 0;
        }
        .card-kpi p {
            margin: 0;
            color: #64748b;
            font-size: 0.9rem;
        }
        .dre-tabela {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        .dre-tabela td, .dre-tabela th {
            padding: 12px 15px;
            border-bottom: 1px solid #e2e8f0;
        }
    </style>
</head>
<body>

<?php
$subtitulo = 'Demonstrativo de Faturamento, Custos e Resultado (Lucro ou Prejuízo)';
include 'header.php';
?>

<main>

    <!-- Filtros de Período -->
    <section class="acoes" style="margin-bottom: 20px; background: white; padding: 15px; border-radius: 12px; box-shadow: 0 3px 10px rgba(0,0,0,.08); flex-wrap: wrap; gap: 15px; align-items: center;">
        <div>
            <h2 style="margin: 0; font-size: 1.4rem;">💰 Painel Financeiro da Empresa</h2>
            <small style="color: #64748b;">Período selecionado: <?= date('d/m/Y', strtotime($dataInicio)) ?> até <?= date('d/m/Y', strtotime($dataFim)) ?></small>
        </div>

        <form method="GET" style="display: flex; gap: 10px; flex-wrap: wrap; align-items: center;">
            <select name="periodo" onchange="if(this.value!='custom') this.form.submit();" style="padding: 8px; border-radius: 6px; border: 1px solid #cbd5e1; font-weight: bold;">
                <option value="mes_atual" <?= $periodo === 'mes_atual' ? 'selected' : '' ?>>📅 Este Mês</option>
                <option value="hoje" <?= $periodo === 'hoje' ? 'selected' : '' ?>>☀️ Hoje</option>
                <option value="mes_anterior" <?= $periodo === 'mes_anterior' ? 'selected' : '' ?>>⏪ Mês Anterior</option>
                <option value="30_dias" <?= $periodo === '30_dias' ? 'selected' : '' ?>>📆 Últimos 30 Dias</option>
                <option value="ano_atual" <?= $periodo === 'ano_atual' ? 'selected' : '' ?>>📊 Ano Atual</option>
                <option value="custom" <?= $periodo === 'custom' ? 'selected' : '' ?>>⚙️ Período Personalizado</option>
            </select>

            <?php if ($periodo === 'custom'): ?>
                <input type="date" name="data_inicio" value="<?= substr($dataInicio, 0, 10) ?>" style="padding: 6px;">
                <input type="date" name="data_fim" value="<?= substr($dataFim, 0, 10) ?>" style="padding: 6px;">
                <button type="submit" class="botao">Filtrar</button>
            <?php endif; ?>
        </form>
    </section>

    <?php if ($erroMsg): ?>
        <div class="alerta erro"><?= htmlspecialchars($erroMsg) ?></div>
    <?php endif; ?>

    <?php if ($sucessoMsg): ?>
        <div class="alerta sucesso"><?= htmlspecialchars($sucessoMsg) ?></div>
    <?php endif; ?>

    <!-- CARDS DE METRICAS FINANCEIRAS -->
    <div class="grid-kpis">

        <!-- 1. Faturamento Bruto -->
        <div class="card-kpi" style="border-left: 5px solid #2563eb;">
            <span class="icone">💵</span>
            <p>Faturamento Bruto (Entradas)</p>
            <h3 style="color: #2563eb;">R$ <?= number_format($faturamentoBruto, 2, ',', '.') ?></h3>
            <small style="color: #64748b;"><?= $qtdOS ?> O.S. finalizadas no período</small>
        </div>

        <!-- 2. Custo de Peças (COGS) -->
        <div class="card-kpi" style="border-left: 5px solid #d97706;">
            <span class="icone">📦</span>
            <p>Custo das Peças (Preço Custo)</p>
            <h3 style="color: #d97706;">R$ <?= number_format($custoPecas, 2, ',', '.') ?></h3>
            <small style="color: #64748b;">Valor de custo de repotenciamento</small>
        </div>

        <!-- 3. Despesas Operacionais -->
        <div class="card-kpi" style="border-left: 5px solid #dc2626;">
            <span class="icone">💸</span>
            <p>Despesas Operacionais (Saídas)</p>
            <h3 style="color: #dc2626;">R$ <?= number_format($totalDespesas, 2, ',', '.') ?></h3>
            <small style="color: #64748b;">Contas da oficina pagas no período</small>
        </div>

        <!-- 4. RESULTADO LÍQUIDO (LUCRO OU PREJUÍZO) -->
        <div class="card-kpi <?= $houveLucro ? 'card-lucro' : 'card-prejuizo' ?>">
            <span class="icone"><?= $houveLucro ? '📈' : '📉' ?></span>
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <p>Resultado Líquido do Período</p>
                <span class="<?= $houveLucro ? 'badge-lucro' : 'badge-prejuizo' ?>">
                    <?= $houveLucro ? '🟢 LUCRO' : '🔴 PREJUÍZO' ?>
                </span>
            </div>
            <h3 style="color: <?= $houveLucro ? '#15803d' : '#b91c1c' ?>;">
                R$ <?= number_format($resultadoLiquido, 2, ',', '.') ?>
            </h3>
            <small style="font-weight: bold; color: <?= $houveLucro ? '#15803d' : '#b91c1c' ?>;">
                Margem Líquida: <?= number_format($margemLucro, 1, ',', '.') ?>%
            </small>
        </div>

    </div>

    <!-- DEMONSTRATIVO DE RESULTADO SINTÉTICO (DRE) -->
    <div class="form-container" style="max-width: 100%; margin-bottom: 30px;">
        <h2>📊 Demonstrativo de Resultado do Exercício (DRE)</h2>
        <table class="dre-tabela">
            <tbody>
                <tr style="background: #f8fafc; font-weight: bold; font-size: 1.05rem;">
                    <td>(+) RECEITA BRUTA COM SERVIÇOS E PEÇAS (FATURAMENTO)</td>
                    <td style="text-align: right; color: #2563eb;">R$ <?= number_format($faturamentoBruto, 2, ',', '.') ?></td>
                </tr>
                <tr>
                    <td style="padding-left: 30px; color: #64748b;">• Total em Mão de Obra / Serviços</td>
                    <td style="text-align: right; color: #64748b;">R$ <?= number_format($totalServicos, 2, ',', '.') ?></td>
                </tr>
                <tr>
                    <td style="padding-left: 30px; color: #64748b;">• Total em Venda de Peças</td>
                    <td style="text-align: right; color: #64748b;">R$ <?= number_format($totalPecas, 2, ',', '.') ?></td>
                </tr>
                <?php if ($totalDesconto > 0): ?>
                    <tr>
                        <td style="padding-left: 30px; color: #ef4444;">• Descontos Concedidos aos Clientes</td>
                        <td style="text-align: right; color: #ef4444;">- R$ <?= number_format($totalDesconto, 2, ',', '.') ?></td>
                    </tr>
                <?php endif; ?>

                <tr style="background: #fef3c7;">
                    <td>(-) CUSTO DAS PEÇAS VENDIDAS (COGS)</td>
                    <td style="text-align: right; color: #d97706; font-weight: bold;">- R$ <?= number_format($custoPecas, 2, ',', '.') ?></td>
                </tr>

                <tr style="background: #eff6ff; font-weight: bold;">
                    <td>(=) MARGEM DE CONTRIBUIÇÃO (LUCRO BRUTO DA OPERAÇÃO)</td>
                    <td style="text-align: right; color: #1d4ed8; font-size: 1.1rem;">R$ <?= number_format($lucroBruto, 2, ',', '.') ?></td>
                </tr>

                <tr style="background: #fee2e2;">
                    <td>(-) DESPESAS OPERACIONAIS E ADMINISTRATIVAS DA OFICINA</td>
                    <td style="text-align: right; color: #dc2626; font-weight: bold;">- R$ <?= number_format($totalDespesas, 2, ',', '.') ?></td>
                </tr>

                <tr style="background: <?= $houveLucro ? '#dcfce7' : '#fee2e2' ?>; font-size: 1.25rem; font-weight: bold;">
                    <td>(=) RESULTADO LÍQUIDO DO PERÍODO (<?= $houveLucro ? 'LUCRO 🟢' : 'PREJUÍZO 🔴' ?>)</td>
                    <td style="text-align: right; color: <?= $houveLucro ? '#15803d' : '#b91c1c' ?>;">
                        R$ <?= number_format($resultadoLiquido, 2, ',', '.') ?>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>

    <!-- SEÇÃO GESTÃO DE DESPESAS DA OFICINA -->
    <div class="form-container" style="max-width: 100%; margin-bottom: 30px;">
        <h2>💸 Lançar Nova Despesa Operacional</h2>
        <form method="POST">
            <input type="hidden" name="acao" value="nova_despesa">
            
            <div class="linha">
                <div class="campo">
                    <label>Descrição da Despesa *</label>
                    <input type="text" name="descricao" required placeholder="Ex: Aluguel do Galpão, Conta de Energia, Peças Fornecedor X">
                </div>
                <div class="campo">
                    <label>Categoria *</label>
                    <select name="categoria" required>
                        <option value="ALUGUEL">🏢 Aluguel</option>
                        <option value="ENERGIA_AGUA">⚡ Energia / Água / Internet</option>
                        <option value="SALARIOS">👨‍🔧 Salários / Comissões</option>
                        <option value="FERRAMENTAS">🛠️ Equipamentos / Ferramentas</option>
                        <option value="PECAS_REPOSICAO">📦 Compra de Peças Reposição</option>
                        <option value="IMPOSTOS">🏛️ Impostos & Taxas</option>
                        <option value="OUTROS">📌 Outros Custos Operacionais</option>
                    </select>
                </div>
                <div class="campo">
                    <label>Valor (R$) *</label>
                    <input type="text" name="valor" required placeholder="0.00">
                </div>
            </div>

            <div class="linha">
                <div class="campo">
                    <label>Data de Vencimento *</label>
                    <input type="date" name="data_vencimento" value="<?= date('Y-m-d') ?>" required>
                </div>
                <div class="campo">
                    <label>Status do Pagamento *</label>
                    <select name="status" required>
                        <option value="PAGO">✅ PAGO (Quitado)</option>
                        <option value="PENDENTE">⏳ PENDENTE (A Pagar)</option>
                    </select>
                </div>
                <div class="campo">
                    <label>Observação / Fornecedor</label>
                    <input type="text" name="observacao" placeholder="Ex: Nota fiscal fornecedor #1234">
                </div>
            </div>

            <button type="submit" class="botao" style="background: #dc2626; font-weight: bold;">+ Cadastrar Despesa</button>
        </form>

        <h3 style="margin-top: 30px;">📋 Histórico de Despesas do Período</h3>
        <table class="tabela">
            <thead>
                <tr>
                    <th>Vencimento / Pago</th>
                    <th>Descrição</th>
                    <th>Categoria</th>
                    <th>Valor</th>
                    <th>Status</th>
                    <th>Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$listaDespesas): ?>
                    <tr>
                        <td colspan="6" style="text-align: center;">Nenhuma despesa registrada neste período.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($listaDespesas as $d): ?>
                        <tr>
                            <td>
                                <?= date('d/m/Y', strtotime($d['data_vencimento'])) ?>
                                <?php if ($d['data_pagamento']): ?>
                                    <br><small style="color: #16a34a;">Pago em: <?= date('d/m/Y', strtotime($d['data_pagamento'])) ?></small>
                                <?php endif; ?>
                            </td>
                            <td><strong><?= htmlspecialchars($d['descricao']) ?></strong></td>
                            <td><span style="background: #f1f5f9; padding: 2px 6px; border-radius: 4px; font-size: 0.8rem;"><?= htmlspecialchars($d['categoria']) ?></span></td>
                            <td><strong style="color: #dc2626;">R$ <?= number_format($d['valor'], 2, ',', '.') ?></strong></td>
                            <td>
                                <?php if ($d['status'] === 'PAGO'): ?>
                                    <span class="badge-status badge-conforme">PAGO</span>
                                <?php else: ?>
                                    <span class="badge-status badge-atencao">PENDENTE</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="acao" value="alterar_status_despesa">
                                    <input type="hidden" name="despesa_id" value="<?= $d['id'] ?>">
                                    <input type="hidden" name="novo_status" value="<?= $d['status'] === 'PAGO' ? 'PENDENTE' : 'PAGO' ?>">
                                    <button type="submit" class="botao secundario" style="padding: 4px 8px; font-size: 0.8rem;">
                                        <?= $d['status'] === 'PAGO' ? 'Marcar PENDENTE' : 'Marcar PAGO' ?>
                                    </button>
                                </form>

                                <form method="POST" style="display: inline;" onsubmit="return confirm('Remover esta despesa?');">
                                    <input type="hidden" name="acao" value="excluir_despesa">
                                    <input type="hidden" name="despesa_id" value="<?= $d['id'] ?>">
                                    <button type="submit" style="background: none; border: none; color: #ef4444; cursor: pointer;">❌</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Tabela de O.S. Faturadas no Período -->
    <div class="tabela">
        <h2>🚗 Ordens de Serviço Faturadas no Período</h2>
        <table>
            <thead>
                <tr>
                    <th>O.S. #</th>
                    <th>Placa / Veículo</th>
                    <th>Cliente</th>
                    <th>Data Fechamento</th>
                    <th>Serviços</th>
                    <th>Peças</th>
                    <th>Desconto</th>
                    <th>Total Faturado</th>
                    <th>Ação</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$listaOSFaturadas): ?>
                    <tr>
                        <td colspan="9" style="text-align: center;">Nenhuma O.S. finalizada no período selecionado.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($listaOSFaturadas as $os): ?>
                        <tr>
                            <td><strong>#<?= sprintf('%05d', $os['id']) ?></strong></td>
                            <td>
                                <strong><?= htmlspecialchars($os['placa']) ?></strong><br>
                                <small style="color: #64748b;"><?= htmlspecialchars($os['marca'] . ' ' . $os['modelo']) ?></small>
                            </td>
                            <td><?= htmlspecialchars($os['cliente_nome'] ?? 'N/I') ?></td>
                            <td><?= $os['data_fechamento'] ? date('d/m/Y H:i', strtotime($os['data_fechamento'])) : '-' ?></td>
                            <td>R$ <?= number_format($os['valor_servicos'], 2, ',', '.') ?></td>
                            <td>R$ <?= number_format($os['valor_pecas'], 2, ',', '.') ?></td>
                            <td style="color: #ef4444;">- R$ <?= number_format($os['desconto'], 2, ',', '.') ?></td>
                            <td><strong style="color: #16a34a; font-size: 1.05rem;">R$ <?= number_format($os['valor_total'], 2, ',', '.') ?></strong></td>
                            <td>
                                <a href="os.php?id=<?= $os['id'] ?>" class="link">👁️ Ver O.S.</a> |
                                <a href="notas.php?emitir_os=<?= $os['id'] ?>" class="link" style="color: #2563eb;">📄 Emitir NF</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

</main>

</body>
</html>
