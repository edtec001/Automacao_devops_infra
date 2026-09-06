<?php

require_once 'config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Se já estiver logado, redireciona adequadamente
if (isset($_SESSION['usuario_id'])) {
    if ((int)($_SESSION['trocar_senha'] ?? 0) === 1) {
        header('Location: trocar_senha.php?obrigatorio=1');
        exit;
    }
    header('Location: index.php');
    exit;
}

$erro = '';
$sucesso = '';

if (isset($_GET['logout']) && $_GET['logout'] == 1) {
    $sucesso = 'Sessão encerrada com sucesso.';
}

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $usuarioInput = trim($_POST['usuario'] ?? '');
    $senhaInput = $_POST['senha'] ?? '';

    if (empty($usuarioInput) || empty($senhaInput)) {
        $erro = 'Preencha todos os campos para entrar.';
    } else {
        try {
            $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE usuario = ? LIMIT 1");
            $stmt->execute([$usuarioInput]);
            $user = $stmt->fetch();

            if ($user && password_verify($senhaInput, $user['senha'])) {
                if ((int)$user['ativo'] !== 1) {
                    $erro = 'Esta conta de usuário está inativa. Procure a administração.';
                } else {
                    $_SESSION['usuario_id'] = $user['id'];
                    $_SESSION['usuario_nome'] = $user['nome'];
                    $_SESSION['usuario_login'] = $user['usuario'];
                    $_SESSION['usuario_perfil'] = $user['perfil'];
                    $_SESSION['trocar_senha'] = (int)$user['trocar_senha'];

                    if ((int)$user['trocar_senha'] === 1) {
                        header('Location: trocar_senha.php?obrigatorio=1');
                        exit;
                    }

                    header('Location: index.php');
                    exit;
                }
            } else {
                $erro = 'Usuário ou senha incorretos.';
            }
        } catch (Exception $e) {
            $erro = 'Erro no servidor: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - EDTEC-SOLUTION</title>
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
        .login-card {
            background: #ffffff;
            width: 100%;
            max-width: 420px;
            padding: 40px 30px;
            border-radius: 16px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.3);
            text-align: center;
        }
        .login-card h1 {
            color: #0f172a;
            font-size: 1.8rem;
            margin-bottom: 8px;
        }
        .login-card p.sub {
            color: #64748b;
            font-size: 0.95rem;
            margin-bottom: 25px;
        }
        .login-card form {
            display: flex;
            flex-direction: column;
            gap: 18px;
            text-align: left;
        }
        .login-card label {
            font-size: 0.9rem;
            font-weight: bold;
            color: #334155;
        }
        .login-card input {
            width: 100%;
            padding: 12px 14px;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            font-size: 1rem;
            box-sizing: border-box;
            transition: border-color 0.2s;
        }
        .login-card input:focus {
            outline: none;
            border-color: #2563eb;
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.15);
        }
        .login-card button {
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
        .login-card button:hover {
            background: #1d4ed8;
        }
        .alerta-box {
            padding: 12px;
            border-radius: 8px;
            font-size: 0.9rem;
            margin-bottom: 20px;
            text-align: left;
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
        .dica-acesso {
            margin-top: 25px;
            padding-top: 20px;
            border-top: 1px dashed #e2e8f0;
            font-size: 0.85rem;
            color: #64748b;
            text-align: left;
            background: #f8fafc;
            padding: 12px;
            border-radius: 8px;
        }
        .dica-acesso strong {
            color: #1e293b;
        }
    </style>
</head>
<body>

<div class="login-card">
    <h1>🔧 EDTEC-SOLUTION</h1>
    <p class="sub">Sistema de Gestão Mecânica</p>

    <?php if ($erro): ?>
        <div class="alerta-box erro">⚠️ <?= htmlspecialchars($erro) ?></div>
    <?php endif; ?>

    <?php if ($sucesso): ?>
        <div class="alerta-box sucesso">✅ <?= htmlspecialchars($sucesso) ?></div>
    <?php endif; ?>

    <form method="POST">
        <div>
            <label for="usuario">Usuário</label>
            <input type="text" id="usuario" name="usuario" required placeholder="Digite seu usuário" value="<?= htmlspecialchars($_POST['usuario'] ?? '') ?>" autofocus>
        </div>

        <div>
            <label for="senha">Senha</label>
            <input type="password" id="senha" name="senha" required placeholder="Digite sua senha">
        </div>

        <button type="submit">🔑 Entrar no Sistema</button>
    </form>

    <div class="dica-acesso">
        <strong>💡 Credenciais Iniciais do Sistema:</strong><br>
        • Admin: <code>admin</code> (Senha inicial: <code>12345</code>)<br>
        • Mecânico: <code>carlos</code> (Senha: <code>carlos123</code>)<br>
        • Auxiliar: <code>joao</code> (Senha: <code>joao123</code>)<br>
        • Recepção: <code>mariana</code> (Senha: <code>mariana123</code>)
    </div>
</div>

</body>
</html>
