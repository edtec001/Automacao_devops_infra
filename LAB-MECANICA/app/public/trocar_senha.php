<?php

require_once 'config.php';
require_once 'auth.php';

$usuario = usuarioLogado();

if (!$usuario) {
    header('Location: login.php');
    exit;
}

$erro = '';
$sucesso = '';
$obrigatorio = isset($_GET['obrigatorio']) && $_GET['obrigatorio'] == 1;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $senhaAtual = $_POST['senha_atual'] ?? '';
    $novaSenha = $_POST['nova_senha'] ?? '';
    $confirmaSenha = $_POST['confirma_senha'] ?? '';

    if (empty($senhaAtual) || empty($novaSenha) || empty($confirmaSenha)) {
        $erro = 'Preencha todos os campos para alterar sua senha.';
    } elseif ($novaSenha !== $confirmaSenha) {
        $erro = 'A nova senha e a confirmação não conferem.';
    } elseif (strlen($novaSenha) < 5) {
        $erro = 'A nova senha deve ter no mínimo 5 caracteres.';
    } elseif ($senhaAtual === $novaSenha) {
        $erro = 'A nova senha deve ser diferente da senha atual.';
    } else {
        try {
            $stmt = $pdo->prepare("SELECT senha FROM usuarios WHERE id = ?");
            $stmt->execute([$usuario['id']]);
            $userDb = $stmt->fetch();

            if ($userDb && password_verify($senhaAtual, $userDb['senha'])) {
                $hashNovo = password_hash($novaSenha, PASSWORD_BCRYPT);

                $stmtUpdate = $pdo->prepare("UPDATE usuarios SET senha = ?, trocar_senha = 0 WHERE id = ?");
                $stmtUpdate->execute([$hashNovo, $usuario['id']]);

                $_SESSION['trocar_senha'] = 0;
                $usuario['trocar_senha'] = 0;
                $sucesso = 'Sua senha foi alterada com sucesso! Você já pode navegar com segurança.';
            } else {
                $erro = 'A senha atual informada está incorreta.';
            }
        } catch (Exception $e) {
            $erro = 'Erro ao atualizar senha: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Alterar Senha - EDTEC-SOLUTION</title>
    <link rel="stylesheet" href="style.css">
    <style>
        body {
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .card-senha {
            background: #ffffff;
            width: 100%;
            max-width: 450px;
            padding: 35px 30px;
            border-radius: 16px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.3);
        }
        .card-senha h1 {
            color: #0f172a;
            font-size: 1.6rem;
            margin-bottom: 8px;
            text-align: center;
        }
        .card-senha p.sub {
            color: #64748b;
            font-size: 0.95rem;
            margin-bottom: 20px;
            text-align: center;
        }
        .card-senha form {
            display: flex;
            flex-direction: column;
            gap: 16px;
        }
        .card-senha label {
            font-size: 0.9rem;
            font-weight: bold;
            color: #334155;
        }
        .card-senha input {
            width: 100%;
            padding: 11px 14px;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            font-size: 1rem;
            box-sizing: border-box;
        }
        .card-senha input:focus {
            outline: none;
            border-color: #2563eb;
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.15);
        }
        .card-senha button {
            background: #2563eb;
            color: white;
            padding: 12px;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: bold;
            cursor: pointer;
            transition: background 0.2s;
            margin-top: 10px;
        }
        .card-senha button:hover {
            background: #1d4ed8;
        }
        .alerta-box {
            padding: 12px;
            border-radius: 8px;
            font-size: 0.9rem;
            margin-bottom: 20px;
        }
        .alerta-box.erro {
            background: #fef2f2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }
        .alerta-box.sucesso {
            background: #f0fdf4;
            color: #166534;
            border: 1px solid #bbf7d0;
        }
        .alerta-box.aviso {
            background: #fffbeb;
            color: #b45309;
            border: 1px solid #fef3c7;
        }
        .btn-inicio {
            display: inline-block;
            text-align: center;
            background: #16a34a;
            color: white;
            text-decoration: none;
            padding: 12px;
            border-radius: 8px;
            font-weight: bold;
            margin-top: 15px;
        }
        .btn-inicio:hover {
            background: #15803d;
        }
    </style>
</head>
<body>

<div class="card-senha">
    <h1>🔑 Alteração de Senha</h1>
    <p class="sub">Usuário: <strong><?= htmlspecialchars($usuario['usuario']) ?></strong> (<?= htmlspecialchars($usuario['nome']) ?>)</p>

    <?php if ($obrigatorio && (int)$usuario['trocar_senha'] === 1 && !$sucesso): ?>
        <div class="alerta-box aviso">
            ⚠️ <strong>Troca de senha obrigatória:</strong> Por razões de segurança, você precisa alterar sua senha padrão temporária antes de continuar.
        </div>
    <?php endif; ?>

    <?php if ($erro): ?>
        <div class="alerta-box erro">⚠️ <?= htmlspecialchars($erro) ?></div>
    <?php endif; ?>

    <?php if ($sucesso): ?>
        <div class="alerta-box sucesso">✅ <?= htmlspecialchars($sucesso) ?></div>
        <a href="index.php" class="btn-inicio" style="width: 100%; box-sizing: border-box;">🏠 Ir para o Painel Principal</a>
    <?php else: ?>
        <form method="POST">
            <div>
                <label for="senha_atual">Senha Atual</label>
                <input type="password" id="senha_atual" name="senha_atual" required placeholder="Digite sua senha atual">
            </div>

            <div>
                <label for="nova_senha">Nova Senha</label>
                <input type="password" id="nova_senha" name="nova_senha" required placeholder="Digite a nova senha (mín. 5 caracteres)">
            </div>

            <div>
                <label for="confirma_senha">Confirmar Nova Senha</label>
                <input type="password" id="confirma_senha" name="confirma_senha" required placeholder="Digite novamente a nova senha">
            </div>

            <button type="submit">💾 Salvar Nova Senha</button>
            <?php if ((int)$usuario['trocar_senha'] !== 1): ?>
                <a href="index.php" style="text-align: center; color: #64748b; font-size: 0.9rem; text-decoration: none; margin-top: 5px;">Cancelar</a>
            <?php endif; ?>
        </form>
    <?php endif; ?>
</div>

</body>
</html>
