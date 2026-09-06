<?php

require_once 'config.php';
require_once 'auth.php';

$usuarioLogado = exigirAutenticacao();
exigirPermissao(['ADMIN', 'GERENTE']);

$erro = '';
$sucesso = '';

// Processar cadastro de novo usuário
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao']) && $_POST['acao'] === 'novo_usuario') {
    $nome = trim($_POST['nome'] ?? '');
    $usuarioInput = trim($_POST['usuario'] ?? '');
    $senha = $_POST['senha'] ?? '';
    $perfil = $_POST['perfil'] ?? 'ATENDIMENTO';
    $forcarTroca = isset($_POST['trocar_senha']) ? 1 : 0;

    $perfilsPermitidos = ['ADMIN', 'GERENTE', 'MECANICO', 'ESTOQUE', 'ATENDIMENTO'];

    if (empty($nome) || empty($usuarioInput) || empty($senha)) {
        $erro = 'Preencha todos os campos obrigatórios.';
    } elseif (!in_array($perfil, $perfilsPermitidos, true)) {
        $erro = 'Perfil selecionado é inválido.';
    } else {
        try {
            // Verificar se o nome de usuário já existe
            $stmtChk = $pdo->prepare("SELECT COUNT(*) FROM usuarios WHERE usuario = ?");
            $stmtChk->execute([$usuarioInput]);
            if ((int)$stmtChk->fetchColumn() > 0) {
                $erro = 'O nome de usuário "' . htmlspecialchars($usuarioInput) . '" já está em uso.';
            } else {
                $hash = password_hash($senha, PASSWORD_BCRYPT);
                $stmt = $pdo->prepare("
                    INSERT INTO usuarios (nome, usuario, senha, perfil, ativo, trocar_senha)
                    VALUES (?, ?, ?, ?, 1, ?)
                ");
                $stmt->execute([$nome, $usuarioInput, $hash, $perfil, $forcarTroca]);
                $sucesso = 'Usuário registrado com sucesso!';
            }
        } catch (Exception $e) {
            $erro = 'Erro ao criar usuário: ' . $e->getMessage();
        }
    }
}

// Processar redefinição de senha por admin
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao']) && $_POST['acao'] === 'redefinir_senha') {
    $idUser = (int)($_POST['id'] ?? 0);
    $novaSenhaTemp = $_POST['nova_senha_temp'] ?? '12345';

    if ($idUser > 0) {
        try {
            $hashTemp = password_hash($novaSenhaTemp, PASSWORD_BCRYPT);
            $stmt = $pdo->prepare("UPDATE usuarios SET senha = ?, trocar_senha = 1 WHERE id = ?");
            $stmt->execute([$hashTemp, $idUser]);
            $sucesso = "Senha redefinida para '{$novaSenhaTemp}' (com troca obrigatória no próximo login).";
        } catch (Exception $e) {
            $erro = 'Erro ao redefinir senha: ' . $e->getMessage();
        }
    }
}

// Processar alteração de status (Ativar / Desativar)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao']) && $_POST['acao'] === 'toggle_status') {
    $idUser = (int)($_POST['id'] ?? 0);
    $statusAtual = (int)($_POST['status_atual'] ?? 1);
    $novoStatus = $statusAtual === 1 ? 0 : 1;

    if ($idUser === $usuarioLogado['id']) {
        $erro = 'Você não pode desativar seu próprio usuário logado.';
    } elseif ($idUser > 0) {
        try {
            $stmt = $pdo->prepare("UPDATE usuarios SET ativo = ? WHERE id = ?");
            $stmt->execute([$novoStatus, $idUser]);
            $sucesso = 'Status do usuário atualizado!';
        } catch (Exception $e) {
            $erro = 'Erro ao atualizar status: ' . $e->getMessage();
        }
    }
}

// Listar todos os usuários
$stmtUsers = $pdo->query("SELECT id, nome, usuario, perfil, ativo, trocar_senha, criado_em FROM usuarios ORDER BY nome ASC");
$listaUsuarios = $stmtUsers->fetchAll();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gerenciamento de Usuários - EDTEC-SOLUTION</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

<?php
$subtitulo = 'Gerenciamento de Usuários e Acessos';
include 'header.php';
?>

<main>
    <section class="acoes">
        <a href="index.php" class="botao secundario">← Voltar ao Dashboard</a>
    </section>

    <?php if ($erro): ?>
        <div class="alerta erro"><?= htmlspecialchars($erro) ?></div>
    <?php endif; ?>

    <?php if ($sucesso): ?>
        <div class="alerta sucesso"><?= htmlspecialchars($sucesso) ?></div>
    <?php endif; ?>

    <div class="form-container" style="max-width: 100%; margin-bottom: 2rem;">
        <h2>👤 Cadastrar Novo Usuário</h2>
        <form method="POST">
            <input type="hidden" name="acao" value="novo_usuario">

            <div class="linha">
                <div class="campo">
                    <label>Nome Completo *</label>
                    <input type="text" name="nome" required placeholder="EX: Fernando Silva">
                </div>

                <div class="campo">
                    <label>Login de Usuário *</label>
                    <input type="text" name="usuario" required placeholder="EX: fernando">
                </div>

                <div class="campo">
                    <label>Senha Inicial *</label>
                    <input type="password" name="senha" required placeholder="EX: 12345">
                </div>
            </div>

            <div class="linha">
                <div class="campo">
                    <label>Perfil de Acesso *</label>
                    <select name="perfil" required>
                        <option value="ADMIN">👑 Administrador</option>
                        <option value="GERENTE">👔 Gerente</option>
                        <option value="MECANICO">👨‍🔧 Mecânico</option>
                        <option value="ESTOQUE">📦 Estoque</option>
                        <option value="ATENDIMENTO">💼 Atendimento / Recepção</option>
                    </select>
                </div>

                <div class="campo" style="display: flex; align-items: center; gap: 10px; margin-top: 25px;">
                    <input type="checkbox" id="trocar_senha" name="trocar_senha" value="1" checked style="width: auto;">
                    <label for="trocar_senha" style="margin: 0; cursor: pointer;">Exigir troca de senha no primeiro login</label>
                </div>
            </div>

            <button type="submit" class="botao" style="margin-top: 15px;">+ Cadastrar Usuário</button>
        </form>
    </div>

    <section class="tabela">
        <h2>Usuários Cadastrados no Sistema</h2>
        <table>
            <thead>
                <tr>
                    <th>Nome</th>
                    <th>Usuário</th>
                    <th>Perfil</th>
                    <th>Status</th>
                    <th>Troca de Senha</th>
                    <th>Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$listaUsuarios): ?>
                    <tr>
                        <td colspan="6">Nenhum usuário cadastrado.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($listaUsuarios as $u): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($u['nome']) ?></strong></td>
                            <td><code><?= htmlspecialchars($u['usuario']) ?></code></td>
                            <td>
                                <?php
                                $badges = [
                                    'ADMIN' => '👑 Admin',
                                    'GERENTE' => '👔 Gerente',
                                    'MECANICO' => '👨‍🔧 Mecânico',
                                    'ESTOQUE' => '📦 Estoque',
                                    'ATENDIMENTO' => '💼 Atendimento'
                                ];
                                echo $badges[$u['perfil']] ?? $u['perfil'];
                                ?>
                            </td>
                            <td>
                                <?php if ($u['ativo']): ?>
                                    <span style="color: #27ae60; font-weight: bold;">● Ativo</span>
                                <?php else: ?>
                                    <span style="color: #95a5a6;">○ Inativo</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($u['trocar_senha']): ?>
                                    <span style="color: #d97706; font-weight: bold;">⚠️ Pendente</span>
                                <?php else: ?>
                                    <span style="color: #16a34a;">OK</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div style="display: flex; gap: 6px; align-items: center;">
                                    <?php if ($u['id'] !== $usuarioLogado['id']): ?>
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="acao" value="toggle_status">
                                            <input type="hidden" name="id" value="<?= $u['id'] ?>">
                                            <input type="hidden" name="status_atual" value="<?= $u['ativo'] ?>">
                                            <button type="submit" class="botao secundario" style="padding: 4px 8px; font-size: 0.8rem;">
                                                <?= $u['ativo'] ? 'Desativar' : 'Ativar' ?>
                                            </button>
                                        </form>
                                    <?php endif; ?>

                                    <form method="POST" style="display: inline;" onsubmit="return confirm('Redefinir senha do usuário \'<?= htmlspecialchars(addslashes($u['usuario'])) ?>\' para \'12345\' com troca obrigatória?');">
                                        <input type="hidden" name="acao" value="redefinir_senha">
                                        <input type="hidden" name="id" value="<?= $u['id'] ?>">
                                        <input type="hidden" name="nova_senha_temp" value="12345">
                                        <button type="submit" class="botao perigo" style="padding: 4px 8px; font-size: 0.8rem;" title="Resetar senha para 12345">
                                            🔄 Resetar Senha
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
