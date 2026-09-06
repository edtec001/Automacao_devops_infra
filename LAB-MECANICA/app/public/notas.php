<?php

require_once 'config.php';
require_once 'auth.php';

$usuarioLogado = exigirAutenticacao();
exigirPermissao(['ATENDIMENTO']);

$erroMsg = '';
$sucessoMsg = '';

// 1. Emissão de Nota Fiscal
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao']) && $_POST['acao'] === 'emitir_nf') {
    $osId = (int) ($_POST['ordem_servico_id'] ?? 0);
    $clienteNome = trim($_POST['cliente_nome'] ?? '');
    $clienteCpfCnpj = trim($_POST['cliente_cpf_cnpj'] ?? '');
    $tipo = $_POST['tipo'] ?? 'NFS-E';
    $valorServicos = (float) str_replace(',', '.', $_POST['valor_servicos'] ?? '0');
    $valorPecas = (float) str_replace(',', '.', $_POST['valor_pecas'] ?? '0');
    $aliquotaImposto = (float) str_replace(',', '.', $_POST['aliquota_imposto'] ?? '5.00');
    $observacoes = trim($_POST['observacoes'] ?? '');

    $valorTotal = $valorServicos + $valorPecas;
    $valorImpostos = ($valorServicos * ($aliquotaImposto / 100)); // ISS incide sobre serviços

    if ($clienteNome === '') {
        $erroMsg = 'O nome do cliente/razão social é obrigatório para emissão da NF.';
    } elseif ($valorTotal <= 0) {
        $erroMsg = 'O valor total da Nota Fiscal deve ser maior que zero.';
    } else {
        try {
            // Gerar número da NF sequencial
            $proximoNumero = (int) $pdo->query("SELECT MAX(id) FROM notas_fiscais")->fetchColumn() + 1;
            $numeroNF = 'NF-' . date('Y') . '-' . sprintf('%05d', $proximoNumero);
            $chaveAcesso = md5(uniqid(rand(), true));

            $stmt = $pdo->prepare("
                INSERT INTO notas_fiscais (
                    ordem_servico_id, numero_nf, chave_acesso, tipo, cliente_nome, cliente_cpf_cnpj,
                    valor_servicos, valor_pecas, valor_impostos, valor_total, aliquota_imposto, status, observacoes
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'EMITIDA', ?)
            ");
            $stmt->execute([
                $osId ?: null, $numeroNF, $chaveAcesso, $tipo, $clienteNome, $clienteCpfCnpj,
                $valorServicos, $valorPecas, $valorImpostos, $valorTotal, $aliquotaImposto, $observacoes
            ]);

            $nfId = $pdo->lastInsertId();
            $sucessoMsg = "Nota Fiscal <strong>{$numeroNF}</strong> emitida com sucesso!";
            header("Location: ver_nota.php?id=" . $nfId);
            exit;
        } catch (Exception $e) {
            $erroMsg = 'Erro ao emitir Nota Fiscal: ' . $e->getMessage();
        }
    }
}

// 2. Cancelar Nota Fiscal
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao']) && $_POST['acao'] === 'cancelar_nf') {
    $nfId = (int) ($_POST['nf_id'] ?? 0);
    try {
        $stmt = $pdo->prepare("UPDATE notas_fiscais SET status = 'CANCELADA' WHERE id = ?");
        $stmt->execute([$nfId]);
        $sucessoMsg = 'Nota Fiscal cancelada com sucesso.';
    } catch (Exception $e) {
        $erroMsg = 'Erro ao cancelar Nota Fiscal: ' . $e->getMessage();
    }
}

// Pesquisa e Listagem
$busca = trim($_GET['busca'] ?? '');
$statusFiltro = $_GET['status'] ?? '';

$sql = "
    SELECT nf.*, os.placa, v.modelo, v.marca
    FROM notas_fiscais nf
    LEFT JOIN ordens_servico os ON os.id = nf.ordem_servico_id
    LEFT JOIN veiculos v ON v.placa = os.placa
    WHERE 1=1
";
$params = [];

if ($busca !== '') {
    $sql .= " AND (nf.numero_nf LIKE ? OR nf.cliente_nome LIKE ? OR nf.cliente_cpf_cnpj LIKE ? OR os.placa LIKE ?)";
    $term = '%' . $busca . '%';
    $params = array_merge($params, [$term, $term, $term, $term]);
}

if ($statusFiltro !== '') {
    $sql .= " AND nf.status = ?";
    $params[] = $statusFiltro;
}

$sql .= " ORDER BY nf.id DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$notas = $stmt->fetchAll();

// Preenchimento automático se for emitir NF a partir de uma O.S.
$osParaEmissao = null;
$osEmitirId = (int) ($_GET['emitir_os'] ?? 0);
if ($osEmitirId > 0) {
    $stmtOS = $pdo->prepare("
        SELECT os.*, c.nome AS cliente_nome, c.cpf_cnpj AS cliente_cpf_cnpj
        FROM ordens_servico os
        JOIN veiculos v ON v.placa = os.placa
        LEFT JOIN clientes c ON c.id = v.cliente_id
        WHERE os.id = ?
        LIMIT 1
    ");
    $stmtOS->execute([$osEmitirId]);
    $osParaEmissao = $stmtOS->fetch();
}

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Controle de Notas Fiscais - EDTEC-SOLUTION</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

<?php
$subtitulo = 'Controle & Emissão de Notas Fiscais';
include 'header.php';
?>

<main>
    <section class="acoes">
        <a href="index.php" class="botao secundario">← Voltar ao Dashboard</a>

        <form method="GET" style="display: flex; gap: 10px;">
            <input type="text" name="busca" placeholder="Buscar por NF, Cliente, CPF/CNPJ ou Placa" value="<?= htmlspecialchars($busca) ?>">
            <select name="status" onchange="this.form.submit()" style="padding: 10px; border-radius: 6px; border: 1px solid #cbd5e1;">
                <option value="">Todos os Status</option>
                <option value="EMITIDA" <?= $statusFiltro === 'EMITIDA' ? 'selected' : '' ?>>Emitidas</option>
                <option value="CANCELADA" <?= $statusFiltro === 'CANCELADA' ? 'selected' : '' ?>>Canceladas</option>
            </select>
            <button type="submit">Pesquisar</button>
        </form>
    </section>

    <?php if ($erroMsg): ?>
        <div class="alerta erro"><?= htmlspecialchars($erroMsg) ?></div>
    <?php endif; ?>

    <?php if ($sucessoMsg): ?>
        <div class="alerta sucesso"><?= $sucessoMsg ?></div>
    <?php endif; ?>

    <!-- Form para Emitir Nova Nota Fiscal -->
    <div class="form-container" style="max-width: 100%; margin-bottom: 2rem;">
        <h2>📄 Emitir Nova Nota Fiscal / Recibo Fiscal</h2>
        
        <form method="POST">
            <input type="hidden" name="acao" value="emitir_nf">
            <?php if ($osParaEmissao): ?>
                <input type="hidden" name="ordem_servico_id" value="<?= $osParaEmissao['id'] ?>">
                <div style="background: #e0f2fe; border: 1px solid #7dd3fc; padding: 10px; border-radius: 6px; margin-bottom: 15px; font-weight: bold; color: #0369a1;">
                    📌 Emitindo NF para a Ordem de Serviço #<?= sprintf('%05d', $osParaEmissao['id']) ?> (Placa: <?= htmlspecialchars($osParaEmissao['placa']) ?>)
                </div>
            <?php endif; ?>

            <div class="linha">
                <div class="campo">
                    <label>Tipo de Documento Fiscal *</label>
                    <select name="tipo" required>
                        <option value="NFS-E">NFS-e (Nota Fiscal de Serviços Eletrônica)</option>
                        <option value="NF-E">NF-e (Nota Fiscal Eletrônica de Peças/Produtos)</option>
                        <option value="RECIBO_FISCAL">Recibo Fiscal de Serviço</option>
                    </select>
                </div>
                <div class="campo">
                    <label>Cliente / Razão Social *</label>
                    <input type="text" name="cliente_nome" required value="<?= htmlspecialchars($osParaEmissao['cliente_nome'] ?? '') ?>" placeholder="Nome completo do cliente ou empresa">
                </div>
                <div class="campo">
                    <label>CPF / CNPJ do Cliente</label>
                    <input type="text" name="cliente_cpf_cnpj" value="<?= htmlspecialchars($osParaEmissao['cliente_cpf_cnpj'] ?? '') ?>" placeholder="000.000.000-00 ou 00.000.000/0001-00">
                </div>
            </div>

            <div class="linha">
                <div class="campo">
                    <label>Valor dos Serviços (Mão de Obra R$)</label>
                    <input type="text" name="valor_servicos" id="nf_valor_servicos" value="<?= number_format($osParaEmissao['valor_servicos'] ?? 0, 2, ',', '.') ?>" required placeholder="0,00" onchange="calcularTotalNF()">
                </div>
                <div class="campo">
                    <label>Valor das Peças Trocadas (R$)</label>
                    <input type="text" name="valor_pecas" id="nf_valor_pecas" value="<?= number_format($osParaEmissao['valor_pecas'] ?? 0, 2, ',', '.') ?>" required placeholder="0,00" onchange="calcularTotalNF()">
                </div>
                <div class="campo">
                    <label>Alíquota ISS / Imposto (%)</label>
                    <input type="text" name="aliquota_imposto" id="nf_aliquota" value="5,00" required placeholder="5,00" onchange="calcularTotalNF()">
                </div>
            </div>

            <div class="campo">
                <label>Observações / Discriminação dos Serviços na NF</label>
                <textarea name="observacoes" rows="2" placeholder="Discriminação detalhada dos serviços prestados e garantia aplicada..."><?= htmlspecialchars($osParaEmissao ? ('Serviços automotivos e substituição de peças conforme O.S. #' . sprintf('%05d', $osParaEmissao['id'])) : '') ?></textarea>
            </div>

            <button type="submit" class="botao" style="background: #16a34a; font-weight: bold;">✅ Emitir Nota Fiscal</button>
        </form>
    </div>

    <!-- Tabela de Notas Fiscais -->
    <section class="tabela">
        <h2>Notas Fiscais Emitidas</h2>
        <table>
            <thead>
                <tr>
                    <th>Número NF</th>
                    <th>Emissão</th>
                    <th>Cliente / CPF-CNPJ</th>
                    <th>O.S. / Placa</th>
                    <th>Serviços</th>
                    <th>Peças</th>
                    <th>Impostos</th>
                    <th>Total NF</th>
                    <th>Status</th>
                    <th>Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$notas): ?>
                    <tr>
                        <td colspan="10" style="text-align: center;">Nenhuma Nota Fiscal emitida.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($notas as $nf): ?>
                        <tr>
                            <td>
                                <strong><?= htmlspecialchars($nf['numero_nf']) ?></strong><br>
                                <small style="color: #64748b;"><?= htmlspecialchars($nf['tipo']) ?></small>
                            </td>
                            <td><?= date('d/m/Y H:i', strtotime($nf['data_emissao'])) ?></td>
                            <td>
                                <strong><?= htmlspecialchars($nf['cliente_nome']) ?></strong><br>
                                <small style="color: #64748b;"><?= htmlspecialchars($nf['cliente_cpf_cnpj'] ?? 'CPF/CNPJ N/I') ?></small>
                            </td>
                            <td>
                                <?php if ($nf['ordem_servico_id']): ?>
                                    <a href="os.php?id=<?= $nf['ordem_servico_id'] ?>" class="link">O.S. #<?= sprintf('%05d', $nf['ordem_servico_id']) ?></a>
                                    (<?= htmlspecialchars($nf['placa'] ?? '-') ?>)
                                <?php else: ?>
                                    --
                                <?php endif; ?>
                            </td>
                            <td>R$ <?= number_format($nf['valor_servicos'], 2, ',', '.') ?></td>
                            <td>R$ <?= number_format($nf['valor_pecas'], 2, ',', '.') ?></td>
                            <td>R$ <?= number_format($nf['valor_impostos'], 2, ',', '.') ?> <small>(<?= number_format($nf['aliquota_imposto'], 1) ?>%)</small></td>
                            <td><strong style="color: #16a34a; font-size: 1.05rem;">R$ <?= number_format($nf['valor_total'], 2, ',', '.') ?></strong></td>
                            <td>
                                <?php if ($nf['status'] === 'EMITIDA'): ?>
                                    <span class="badge-status badge-conforme">EMITIDA</span>
                                <?php else: ?>
                                    <span class="badge-status badge-prejuizo">CANCELADA</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <a href="ver_nota.php?id=<?= $nf['id'] ?>" class="botao" style="padding: 4px 8px; font-size: 0.85rem; text-decoration: none;">👁️ Ver / Imprimir</a>
                                
                                <?php if ($nf['status'] === 'EMITIDA'): ?>
                                    <form method="POST" style="display: inline;" onsubmit="return confirm('Tem certeza que deseja cancelar esta Nota Fiscal?');">
                                        <input type="hidden" name="acao" value="cancelar_nf">
                                        <input type="hidden" name="nf_id" value="<?= $nf['id'] ?>">
                                        <button type="submit" class="botao perigo" style="padding: 4px 8px; font-size: 0.85rem;">❌ Cancelar</button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </section>
</main>

</body>
</html>
