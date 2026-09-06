<?php

require_once 'config.php';
require_once 'auth.php';

$usuarioLogado = exigirAutenticacao();

$placa = strtoupper(trim($_GET['placa'] ?? $_POST['placa'] ?? ''));
$osId = (int) ($_GET['os_id'] ?? $_POST['os_id'] ?? 0);
$checklistId = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);

$erroMsg = '';
$sucessoMsg = '';

// Definição dos itens padrão do checklist de revisão por categoria
$itensPadrao = [
    'Sistema de Freios' => [
        'Pastilhas e Discos de Freio',
        'Fluido de Freio (Nível e Umidade)',
        'Lonas e Tambores Traseiros',
        'Freio de Estacionamento (Freio de Mão)'
    ],
    'Motor & Transmissão' => [
        'Nível e Estado do Óleo do Motor',
        'Filtro de Óleo e Filtro de Ar do Motor',
        'Velas e Cabos de Ignição',
        'Correia Dentada / Correia de Acessórios',
        'Nível do Líquido de Arrefecimento (Aditivo)'
    ],
    'Suspensão & Direção' => [
        'Amortecedores e Batentes',
        'Pivôs, Buchas e Terminais de Direção',
        'Caixa de Direção e Geometria/Alinhamento',
        'Calibragem, Desgaste e Estado dos Pneus'
    ],
    'Elétrica & Eletrônica' => [
        'Teste de Carga da Bateria e Alternador',
        'Faróis Principais, Lanternas e Luz de Freio',
        'Setas, Pisca-Alerta e Luz de Ré',
        'Limpadores e Palhetas de Pára-brisa',
        'Scanner de Injeção Eletrônica (DTC/Erros)'
    ],
    'Climatização & Segurança' => [
        'Filtro de Cabine (Ar-Condicionado)',
        'Cintos de Segurança e Travamento',
        'Triângulo, Macaco e Chave de Roda',
        'Teste de Rodagem Final'
    ]
];

// Se tiver id do checklist, busca o checklist
$checklist = null;
$itensExistentes = [];

if ($checklistId > 0) {
    $stmt = $pdo->prepare("
        SELECT c.*, v.modelo, v.marca, v.ano, v.cor, v.km_atual,
               f.nome AS mecanico_nome, cl.nome AS cliente_nome
        FROM checklist_revisao c
        JOIN veiculos v ON v.placa = c.placa
        LEFT JOIN funcionarios f ON f.id = c.mecanico_id
        LEFT JOIN clientes cl ON cl.id = v.cliente_id
        WHERE c.id = ?
        LIMIT 1
    ");
    $stmt->execute([$checklistId]);
    $checklist = $stmt->fetch();

    if ($checklist) {
        $placa = $checklist['placa'];
        $osId = (int) $checklist['ordem_servico_id'];

        $stmtItens = $pdo->prepare("SELECT * FROM checklist_revisao_itens WHERE checklist_id = ?");
        $stmtItens->execute([$checklistId]);
        while ($row = $stmtItens->fetch()) {
            $itensExistentes[$row['categoria']][$row['item_nome']] = $row;
        }
    }
} elseif ($placa !== '') {
    // Busca O.S. mais recente se não informada
    if ($osId === 0) {
        $stmtOS = $pdo->prepare("SELECT id FROM ordens_servico WHERE placa = ? ORDER BY id DESC LIMIT 1");
        $stmtOS->execute([$placa]);
        $osId = (int) $stmtOS->fetchColumn();
    }

    // Busca se já existe um checklist recente para essa O.S. ou Placa
    if ($osId > 0) {
        $stmtExist = $pdo->prepare("SELECT id FROM checklist_revisao WHERE ordem_servico_id = ? ORDER BY id DESC LIMIT 1");
        $stmtExist->execute([$osId]);
        $checklistExistId = (int) $stmtExist->fetchColumn();

        if ($checklistExistId > 0) {
            header("Location: checklist.php?id=" . $checklistExistId);
            exit;
        }
    }
}

// 1. Processar Salvamento do Checklist
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['salvar_checklist'])) {
    $placa = strtoupper(trim($_POST['placa'] ?? ''));
    $osId = (int) ($_POST['os_id'] ?? 0);
    $mecanicoId = (int) ($_POST['mecanico_id'] ?? 0);
    $kmVeiculo = (int) ($_POST['km_veiculo'] ?? 0);
    $statusGeral = $_POST['status_geral'] ?? 'EM_ANDAMENTO';
    $observacoesGerais = trim($_POST['observacoes_gerais'] ?? '');

    if ($placa === '') {
        $erroMsg = 'Informe a placa do veículo para o checklist.';
    } else {
        try {
            $pdo->beginTransaction();

            if ($checklistId > 0) {
                // Atualizar cabeçalho
                $stmt = $pdo->prepare("
                    UPDATE checklist_revisao
                    SET mecanico_id = ?, km_veiculo = ?, status_geral = ?, observacoes_gerais = ?
                    WHERE id = ?
                ");
                $stmt->execute([$mecanicoId ?: null, $kmVeiculo ?: null, $statusGeral, $observacoesGerais, $checklistId]);

                // Limpa itens antigos para reinserir
                $pdo->prepare("DELETE FROM checklist_revisao_itens WHERE checklist_id = ?")->execute([$checklistId]);
            } else {
                // Inserir cabeçalho
                $stmt = $pdo->prepare("
                    INSERT INTO checklist_revisao (ordem_servico_id, placa, mecanico_id, km_veiculo, status_geral, observacoes_gerais)
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([$osId ?: null, $placa, $mecanicoId ?: null, $kmVeiculo ?: null, $statusGeral, $observacoesGerais]);
                $checklistId = (int) $pdo->lastInsertId();
            }

            // Inserir Itens do Form
            $stmtItem = $pdo->prepare("
                INSERT INTO checklist_revisao_itens (checklist_id, categoria, item_nome, status_item, observacao)
                VALUES (?, ?, ?, ?, ?)
            ");

            $statusItems = $_POST['item_status'] ?? [];
            $obsItems = $_POST['item_obs'] ?? [];

            foreach ($itensPadrao as $cat => $itens) {
                foreach ($itens as $itemNome) {
                    $key = md5($cat . '_' . $itemNome);
                    $statusVal = $statusItems[$key] ?? 'CONFORME';
                    $obsVal = trim($obsItems[$key] ?? '');

                    $stmtItem->execute([$checklistId, $cat, $itemNome, $statusVal, $obsVal]);
                }
            }

            $pdo->commit();
            header("Location: checklist.php?id=" . $checklistId . "&sucesso=1");
            exit;

        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $erroMsg = 'Erro ao salvar checklist: ' . $e->getMessage();
        }
    }
}

if (isset($_GET['sucesso'])) {
    $sucessoMsg = 'Checklist de revisão salvo com sucesso!';
}

// Busca dados do veículo para preencher o cabeçalho se houver placa
$veiculo = null;
if ($placa !== '') {
    $stmtV = $pdo->prepare("
        SELECT v.*, c.nome AS cliente_nome, f.nome AS mecanico_nome
        FROM veiculos v
        LEFT JOIN clientes c ON c.id = v.cliente_id
        LEFT JOIN funcionarios f ON f.id = v.mecanico_id
        WHERE v.placa = ?
        LIMIT 1
    ");
    $stmtV->execute([$placa]);
    $veiculo = $stmtV->fetch();
}

// Lista mecânicos
$mecanicos = $pdo->query("SELECT id, nome, especialidade FROM funcionarios WHERE ativo = 1 AND cargo = 'MECANICO' ORDER BY nome ASC")->fetchAll();

// Lista histórico de checklists
$historicoChecklists = [];
if ($placa !== '') {
    $stmtH = $pdo->prepare("
        SELECT c.*, f.nome AS mecanico_nome
        FROM checklist_revisao c
        LEFT JOIN funcionarios f ON f.id = c.mecanico_id
        WHERE c.placa = ?
        ORDER BY c.id DESC
    ");
    $stmtH->execute([$placa]);
    $historicoChecklists = $stmtH->fetchAll();
} else {
    $stmtH = $pdo->query("
        SELECT c.*, v.modelo, v.marca, f.nome AS mecanico_nome
        FROM checklist_revisao c
        JOIN veiculos v ON v.placa = c.placa
        LEFT JOIN funcionarios f ON f.id = c.mecanico_id
        ORDER BY c.id DESC LIMIT 20
    ");
    $historicoChecklists = $stmtH->fetchAll();
}

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checklist de Revisão de Procedimentos - EDTEC-SOLUTION</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .badge-status-sel {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.85rem;
            font-weight: bold;
        }
    </style>
</head>
<body>

<?php
$subtitulo = 'Checklist de Revisão dos Procedimentos do Veículo';
include 'header.php';
?>

<main>
    <div class="form-container" style="max-width: 1100px;">

        <div class="no-print" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 10px;">
            <div>
                <h2>📋 Checklist de Revisão e Qualidade</h2>
                <p style="margin: 0; color: #64748b;">Inspeção de itens de segurança, motor, freios e procedimentos realizados.</p>
            </div>
            
            <div style="display: flex; gap: 10px;">
                <?php if ($checklistId > 0): ?>
                    <button onclick="window.print()" class="botao">🖨️ Imprimir Checklist</button>
                <?php endif; ?>
                <a href="index.php" class="botao secundario">← Voltar ao Dashboard</a>
            </div>
        </div>

        <?php if ($erroMsg): ?>
            <div class="alerta erro no-print"><?= htmlspecialchars($erroMsg) ?></div>
        <?php endif; ?>

        <?php if ($sucessoMsg): ?>
            <div class="alerta sucesso no-print"><?= htmlspecialchars($sucessoMsg) ?></div>
        <?php endif; ?>

        <!-- Formulário de Seleção/Busca de Veículo se não houver placa selecionada -->
        <?php if ($placa === '' && $checklistId === 0): ?>
            <div class="no-print" style="background: #f8fafc; padding: 20px; border-radius: 8px; border: 1px solid #cbd5e1; margin-bottom: 25px;">
                <h3>🔍 Selecionar Veículo para Criar Checklist</h3>
                <form method="GET" style="display: flex; gap: 10px; align-items: center;">
                    <input type="text" name="placa" placeholder="Digite a placa do veículo (Ex: ABC1D23)" required style="padding: 10px; flex: 1;">
                    <button type="submit" class="botao">+ Iniciar Checklist</button>
                </form>
            </div>
        <?php endif; ?>

        <?php if ($veiculo || $checklist): ?>
            <form method="POST">
                <input type="hidden" name="salvar_checklist" value="1">
                <input type="hidden" name="id" value="<?= $checklistId ?>">
                <input type="hidden" name="placa" value="<?= htmlspecialchars($placa) ?>">
                <input type="hidden" name="os_id" value="<?= $osId ?>">

                <div class="checklist-categoria" style="background: #eff6ff; border-color: #bfdbfe;">
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 15px;">
                        <div>
                            <p style="margin: 0; font-size: 0.85rem; color: #1e40af;">VEÍCULO / PLACA</p>
                            <h3 style="margin: 2px 0 0 0; color: #1e3a8a; border: none; padding: 0;">
                                🚗 <?= htmlspecialchars($placa) ?>
                                <small style="font-size: 0.9rem; font-weight: normal;">(<?= htmlspecialchars(($veiculo['marca'] ?? $checklist['marca'] ?? '') . ' ' . ($veiculo['modelo'] ?? $checklist['modelo'] ?? '')) ?>)</small>
                            </h3>
                        </div>

                        <div>
                            <label style="font-size: 0.85rem; color: #1e40af; font-weight: bold;">MECÂNICO RESPONSÁVEL</label>
                            <select name="mecanico_id" class="no-print" style="margin-top: 3px; padding: 6px;">
                                <option value="">-- Selecione o Mecânico --</option>
                                <?php 
                                $mecSel = $checklist['mecanico_id'] ?? $veiculo['mecanico_id'] ?? 0;
                                foreach ($mecanicos as $m): 
                                ?>
                                    <option value="<?= $m['id'] ?>" <?= ($mecSel == $m['id']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($m['nome']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="only-print" style="display: none; font-weight: bold; margin-top: 3px;">
                                <?= htmlspecialchars($checklist['mecanico_nome'] ?? $veiculo['mecanico_nome'] ?? 'Não informado') ?>
                            </div>
                        </div>

                        <div>
                            <label style="font-size: 0.85rem; color: #1e40af; font-weight: bold;">KM ATUAL DO VEÍCULO</label>
                            <input type="number" name="km_veiculo" class="no-print" value="<?= htmlspecialchars($checklist['km_veiculo'] ?? $veiculo['km_atual'] ?? '') ?>" style="margin-top: 3px; padding: 6px;">
                            <div class="only-print" style="display: none; font-weight: bold; margin-top: 3px;">
                                <?= number_format($checklist['km_veiculo'] ?? $veiculo['km_atual'] ?? 0, 0, ',', '.') ?> KM
                            </div>
                        </div>

                        <div>
                            <label style="font-size: 0.85rem; color: #1e40af; font-weight: bold;">PARECER GERAL DA REVISÃO</label>
                            <select name="status_geral" class="no-print" style="margin-top: 3px; padding: 6px; font-weight: bold;">
                                <?php $stGeral = $checklist['status_geral'] ?? 'EM_ANDAMENTO'; ?>
                                <option value="EM_ANDAMENTO" <?= $stGeral === 'EM_ANDAMENTO' ? 'selected' : '' ?>>🟡 Em Andamento</option>
                                <option value="APROVADO" <?= $stGeral === 'APROVADO' ? 'selected' : '' ?>>🟢 Aprovado em 100%</option>
                                <option value="RECOMENDACOES" <?= $stGeral === 'RECOMENDACOES' ? 'selected' : '' ?>>⚠️ Com Recomendações / Pendências</option>
                            </select>
                            <div class="only-print" style="display: none; font-weight: bold; margin-top: 3px;">
                                <?= $stGeral ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Lista de Categorias e Itens de Inspeção -->
                <?php foreach ($itensPadrao as $categoria => $itens): ?>
                    <div class="checklist-categoria">
                        <h3>🔧 <?= htmlspecialchars($categoria) ?></h3>

                        <table class="checklist-tabela">
                            <thead>
                                <tr style="background: #f1f5f9;">
                                    <th style="width: 40%;">Item Avaliado / Procedimento</th>
                                    <th style="width: 35%;">Status da Inspeção</th>
                                    <th style="width: 25%;">Observação / Recomendação</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($itens as $itemNome): 
                                    $key = md5($categoria . '_' . $itemNome);
                                    $itemExistente = $itensExistentes[$categoria][$itemNome] ?? null;
                                    $statusAtual = $itemExistente['status_item'] ?? 'CONFORME';
                                    $obsAtual = $itemExistente['observacao'] ?? '';
                                ?>
                                    <tr>
                                        <td><strong><?= htmlspecialchars($itemNome) ?></strong></td>
                                        <td>
                                            <div class="checklist-radio-group no-print">
                                                <label style="color: #166534;">
                                                    <input type="radio" name="item_status[<?= $key ?>]" value="CONFORME" <?= $statusAtual === 'CONFORME' ? 'checked' : '' ?>>
                                                    🟢 Conforme
                                                </label>
                                                <label style="color: #1e40af;">
                                                    <input type="radio" name="item_status[<?= $key ?>]" value="REPARADO" <?= $statusAtual === 'REPARADO' ? 'checked' : '' ?>>
                                                    🔧 Reparado
                                                </label>
                                                <label style="color: #92400e;">
                                                    <input type="radio" name="item_status[<?= $key ?>]" value="ATENCAO" <?= $statusAtual === 'ATENCAO' ? 'checked' : '' ?>>
                                                    ⚠️ Atenção
                                                </label>
                                                <label style="color: #64748b;">
                                                    <input type="radio" name="item_status[<?= $key ?>]" value="NAO_APLICAVEL" <?= $statusAtual === 'NAO_APLICAVEL' ? 'checked' : '' ?>>
                                                    ⚪ N/A
                                                </label>
                                            </div>

                                            <div class="only-print" style="display: none;">
                                                <?php
                                                $badgesClass = [
                                                    'CONFORME' => 'badge-conforme',
                                                    'REPARADO' => 'badge-reparado',
                                                    'ATENCAO' => 'badge-atencao',
                                                    'NAO_APLICAVEL' => 'badge-nao-aplicavel'
                                                ];
                                                $badgesLabel = [
                                                    'CONFORME' => '🟢 Conforme',
                                                    'REPARADO' => '🔧 Reparado / Trocado',
                                                    'ATENCAO' => '⚠️ Atenção / Substituir Em Breve',
                                                    'NAO_APLICAVEL' => '⚪ N/A'
                                                ];
                                                ?>
                                                <span class="badge-status <?= $badgesClass[$statusAtual] ?? '' ?>">
                                                    <?= $badgesLabel[$statusAtual] ?? $statusAtual ?>
                                                </span>
                                            </div>
                                        </td>
                                        <td>
                                            <input type="text" name="item_obs[<?= $key ?>]" value="<?= htmlspecialchars($obsAtual) ?>" placeholder="Ex: Substituído pastilha" class="no-print" style="width: 100%; padding: 5px; font-size: 0.85rem;">
                                            <span class="only-print" style="display: none; font-size: 0.85rem; color: #475569;">
                                                <?= htmlspecialchars($obsAtual ?: '-') ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endforeach; ?>

                <div class="checklist-categoria">
                    <h3>📝 Observações Finais do Mecânico</h3>
                    <textarea name="observacoes_gerais" class="no-print" rows="3" placeholder="Insira observações gerais, diagnósticos adicionais ou observações do teste de rodagem..." style="width: 100%; padding: 10px;"><?= htmlspecialchars($checklist['observacoes_gerais'] ?? '') ?></textarea>
                    <div class="only-print" style="display: none; background: #fff; padding: 10px; border: 1px solid #cbd5e1; border-radius: 6px;">
                        <?= nl2br(htmlspecialchars($checklist['observacoes_gerais'] ?? 'Sem observações adicionais.')) ?>
                    </div>
                </div>

                <div class="no-print" style="display: flex; gap: 10px; margin-top: 20px;">
                    <button type="submit" class="botao" style="background: #16a34a; font-weight: bold; font-size: 1.05rem;">
                        💾 Salvar Checklist de Vistoria
                    </button>
                    <?php if ($osId > 0): ?>
                        <a href="os.php?id=<?= $osId ?>" class="botao secundario">📋 Voltar para a O.S. #<?= sprintf('%05d', $osId) ?></a>
                    <?php endif; ?>
                </div>
            </form>
        <?php endif; ?>

        <!-- Histórico de Checklists do Veículo -->
        <?php if ($historicoChecklists): ?>
            <div class="tabela no-print" style="margin-top: 35px;">
                <h3>📜 Histórico de Checklists Registrados</h3>
                <table>
                    <thead>
                        <tr>
                            <th>ID / Data</th>
                            <th>Placa</th>
                            <th>Mecânico</th>
                            <th>KM</th>
                            <th>Status Geral</th>
                            <th>Ação</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($historicoChecklists as $h): ?>
                            <tr>
                                <td>
                                    <strong>#<?= sprintf('%04d', $h['id']) ?></strong><br>
                                    <small><?= date('d/m/Y H:i', strtotime($h['data_checklist'])) ?></small>
                                </td>
                                <td><strong><?= htmlspecialchars($h['placa']) ?></strong></td>
                                <td><?= htmlspecialchars($h['mecanico_nome'] ?? '-') ?></td>
                                <td><?= number_format($h['km_veiculo'] ?? 0, 0, ',', '.') ?> KM</td>
                                <td>
                                    <?php
                                    $stClass = [
                                        'EM_ANDAMENTO' => 'badge-atencao',
                                        'APROVADO' => 'badge-conforme',
                                        'RECOMENDACOES' => 'badge-atencao'
                                    ];
                                    ?>
                                    <span class="badge-status <?= $stClass[$h['status_geral']] ?? '' ?>">
                                        <?= htmlspecialchars($h['status_geral']) ?>
                                    </span>
                                </td>
                                <td>
                                    <a href="checklist.php?id=<?= $h['id'] ?>" class="link">👁️ Abrir / Imprimir</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

    </div>
</main>

<style>
@media print {
    .only-print { display: block !important; }
    .no-print { display: none !important; }
}
</style>

</body>
</html>
