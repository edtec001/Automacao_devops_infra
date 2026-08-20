<?php

require_once 'config.php';

$erro = '';
$sucesso = '';

// Processar cadastro de funcionário
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao']) && $_POST['acao'] === 'novo_funcionario') {
    $nome = trim($_POST['nome'] ?? '');
    $cpf = trim($_POST['cpf'] ?? '');
    $telefone = trim($_POST['telefone'] ?? '');
    $cargo = $_POST['cargo'] ?? 'MECANICO';
    $especialidade = trim($_POST['especialidade'] ?? 'Geral');

    $cargosPermitidos = ['MECANICO', 'AUXILIAR', 'ADMINISTRATIVO'];

    if ($nome === '') {
        $erro = 'Informe o nome do funcionário.';
    } elseif (!in_array($cargo, $cargosPermitidos, true)) {
        $erro = 'Cargo inválido selecionado.';
    } else {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO funcionarios (nome, cpf, telefone, cargo, especialidade)
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([$nome, $cpf, $telefone, $cargo, $especialidade]);
            $sucesso = 'Funcionário registrado com sucesso!';
        } catch (Exception $e) {
            $erro = 'Erro ao registrar funcionário: ' . $e->getMessage();
        }
    }
}

// Processar ativação / desativação
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao']) && $_POST['acao'] === 'toggle_status') {
    $id = (int) ($_POST['id'] ?? 0);
    $statusAtual = (int) ($_POST['status_atual'] ?? 1);
    $novoStatus = $statusAtual === 1 ? 0 : 1;

    try {
        $stmt = $pdo->prepare("UPDATE funcionarios SET ativo = ? WHERE id = ?");
        $stmt->execute([$novoStatus, $id]);
        $sucesso = 'Status do funcionário atualizado!';
    } catch (Exception $e) {
        $erro = 'Erro ao atualizar status: ' . $e->getMessage();
    }
}

// Processar exclusão de funcionário
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao']) && $_POST['acao'] === 'excluir_funcionario') {
    $id = (int) ($_POST['id'] ?? 0);

    if ($id > 0) {
        try {
            // Desassocia o mecânico de veículos vinculados antes de excluir
            $stmtVeh = $pdo->prepare("UPDATE veiculos SET mecanico_id = NULL WHERE mecanico_id = ?");
            $stmtVeh->execute([$id]);

            // Exclui o funcionário do banco de dados
            $stmt = $pdo->prepare("DELETE FROM funcionarios WHERE id = ?");
            $stmt->execute([$id]);
            $sucesso = 'Funcionário excluído com sucesso!';
        } catch (Exception $e) {
            $erro = 'Erro ao excluir funcionário: ' . $e->getMessage();
        }
    }
}

// Filtro por cargo
$filtroCargo = $_GET['cargo'] ?? '';
if (in_array($filtroCargo, ['MECANICO', 'AUXILIAR', 'ADMINISTRATIVO'], true)) {
    $stmt = $pdo->prepare("SELECT * FROM funcionarios WHERE cargo = ? ORDER BY nome ASC");
    $stmt->execute([$filtroCargo]);
} else {
    $stmt = $pdo->query("SELECT * FROM funcionarios ORDER BY cargo ASC, nome ASC");
}
$funcionarios = $stmt->fetchAll();

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestão de Equipe - EDTEC-SOLUTION</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

<?php
$subtitulo = 'Gestão de Equipe';
include 'header.php';
?>

<main>
    <section class="acoes">
        <a href="index.php" class="botao secundario">← Voltar ao Dashboard</a>
        
        <form method="GET" style="display: flex; gap: 10px;">
            <select name="cargo" onchange="this.form.submit()" style="padding: 8px; border-radius: 6px; border: 1px solid #cbd5e1;">
                <option value="">Todos os Cargos</option>
                <option value="MECANICO" <?= $filtroCargo === 'MECANICO' ? 'selected' : '' ?>>Mecânicos</option>
                <option value="AUXILIAR" <?= $filtroCargo === 'AUXILIAR' ? 'selected' : '' ?>>Auxiliares</option>
                <option value="ADMINISTRATIVO" <?= $filtroCargo === 'ADMINISTRATIVO' ? 'selected' : '' ?>>Administrativo</option>
            </select>
        </form>
    </section>

    <?php if ($erro): ?>
        <div class="alerta erro"><?= htmlspecialchars($erro) ?></div>
    <?php endif; ?>

    <?php if ($sucesso): ?>
        <div class="alerta sucesso"><?= htmlspecialchars($sucesso) ?></div>
    <?php endif; ?>

    <div class="form-container" style="max-width: 100%; margin-bottom: 2rem;">
        <h2>👨‍🔧 Cadastrar Novo Funcionário</h2>
        <form method="POST">
            <input type="hidden" name="acao" value="novo_funcionario">
            
            <div class="linha">
                <div class="campo">
                    <label>Nome Completo *</label>
                    <input type="text" name="nome" required placeholder="EX: Carlos Oliveira">
                </div>

                <div class="campo">
                    <label>Cargo *</label>
                    <select name="cargo" required>
                        <option value="MECANICO">👨‍🔧 Mecânico</option>
                        <option value="AUXILIAR">🛠️ Auxiliar Técnico</option>
                        <option value="ADMINISTRATIVO">💼 Administrativo / Recepção</option>
                    </select>
                </div>
            </div>

            <div class="linha">
                <div class="campo">
                    <label>CPF</label>
                    <input type="text" name="cpf" placeholder="000.000.000-00">
                </div>

                <div class="campo">
                    <label>Telefone / WhatsApp</label>
                    <input type="text" name="telefone" placeholder="(85) 99999-9999">
                </div>

                <div class="campo">
                    <label>Especialidade</label>
                    <input type="text" name="especialidade" placeholder="EX: Injeção Eletrônica, Motor, Suspensão">
                </div>
            </div>

            <button type="submit" class="botao">+ Cadastrar Funcionário</button>
        </form>
    </div>

    <section class="tabela">
        <h2>Equipe Cadastrada</h2>
        <table>
            <thead>
                <tr>
                    <th>Nome</th>
                    <th>Cargo</th>
                    <th>Especialidade</th>
                    <th>Telefone</th>
                    <th>CPF</th>
                    <th>Status</th>
                    <th>Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$funcionarios): ?>
                    <tr>
                        <td colspan="7">Nenhum funcionário encontrado.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($funcionarios as $f): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($f['nome']) ?></strong></td>
                            <td>
                                <?php
                                $badgeCargo = [
                                    'MECANICO' => '👨‍🔧 Mecânico',
                                    'AUXILIAR' => '🛠️ Auxiliar',
                                    'ADMINISTRATIVO' => '💼 Admin'
                                ];
                                echo $badgeCargo[$f['cargo']] ?? $f['cargo'];
                                ?>
                            </td>
                            <td><?= htmlspecialchars($f['especialidade'] ?: 'Geral') ?></td>
                            <td><?= htmlspecialchars($f['telefone'] ?: '-') ?></td>
                            <td><?= htmlspecialchars($f['cpf'] ?: '-') ?></td>
                            <td>
                                <?php if ($f['ativo']): ?>
                                    <span style="color: #27ae60; font-weight: bold;">● Ativo</span>
                                <?php else: ?>
                                    <span style="color: #95a5a6;">○ Inativo</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div style="display: flex; gap: 6px; align-items: center;">
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="acao" value="toggle_status">
                                        <input type="hidden" name="id" value="<?= $f['id'] ?>">
                                        <input type="hidden" name="status_atual" value="<?= $f['ativo'] ?>">
                                        <button type="submit" class="botao secundario" style="padding: 4px 8px; font-size: 0.8rem;" title="<?= $f['ativo'] ? 'Desativar funcionário' : 'Ativar funcionário' ?>">
                                            <?= $f['ativo'] ? 'Desativar' : 'Ativar' ?>
                                        </button>
                                    </form>

                                    <form method="POST" style="display: inline;" onsubmit="return confirm('Tem certeza que deseja excluir o funcionário \'<?= htmlspecialchars(addslashes($f['nome'])) ?>\'?');">
                                        <input type="hidden" name="acao" value="excluir_funcionario">
                                        <input type="hidden" name="id" value="<?= $f['id'] ?>">
                                        <button type="submit" class="botao perigo" style="padding: 4px 8px; font-size: 0.8rem;" title="Excluir funcionário">
                                            🗑️ Excluir
                                        </button>
                                    </form>
                                </div>
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
