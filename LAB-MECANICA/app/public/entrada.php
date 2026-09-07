<?php

require_once 'config.php';
require_once 'auth.php';

$usuarioLogado = exigirAutenticacao();

$erro = '';
$sucesso = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $placa = strtoupper(trim($_POST['placa'] ?? ''));
    $nome = trim($_POST['nome'] ?? '');
    $telefone = trim($_POST['telefone'] ?? '');
    $marca = trim($_POST['marca'] ?? '');
    $modelo = trim($_POST['modelo'] ?? '');
    $ano = (int) ($_POST['ano'] ?? 0);
    $cor = trim($_POST['cor'] ?? '');
    $km = (int) ($_POST['km_atual'] ?? 0);
    $posicao = (int) ($_POST['posicao_chave'] ?? 0);
    $mecanico_id = (int) ($_POST['mecanico_id'] ?? 0);
    $observacoes = trim($_POST['observacoes'] ?? '');
    $veiculoId = $pdo->lastInsertId();

    // Dados da O.S. e Vistoria
    $defeito_relatado = trim($_POST['defeito_relatado'] ?? '');
    $nivel_combustivel = trim($_POST['nivel_combustivel'] ?? '1/2');
    $estado_lataria = trim($_POST['estado_lataria'] ?? '');
    $itens_array = $_POST['itens_veiculo'] ?? [];
    $itens_veiculo = is_array($itens_array) ? implode(', ', $itens_array) : '';

    // Upload da foto da lataria
    $foto_lataria = null;
    if (isset($_FILES['foto_lataria']) && $_FILES['foto_lataria']['error'] === UPLOAD_ERR_OK) {
        $tmpName = $_FILES['foto_lataria']['tmp_name'];
        $fileName = basename($_FILES['foto_lataria']['name']);
        $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'webp'];
        if (in_array($ext, $allowed, true)) {
            $newFileName = $placa . '_' . time() . '.' . $ext;
            $targetDir = __DIR__ . '/uploads/';
            if (!is_dir($targetDir)) {
                mkdir($targetDir, 0777, true);
            }
            if (move_uploaded_file($tmpName, $targetDir . $newFileName)) {
                $foto_lataria = 'uploads/' . $newFileName;
            }
        }
    }

    /*
     * Validação básica
     */

    if ($placa === '') {
        $erro = 'Informe a placa do veículo.';
    }

    elseif ($modelo === '') {
        $erro = 'Informe o modelo do veículo.';
    }

    elseif ($posicao < 1 || $posicao > 10) {
        $erro = 'Selecione uma posição válida para a chave.';
    }

    else {

        try {

            $pdo->beginTransaction();

            /*
             * Verifica se a placa já está na oficina
             */

            $stmt = $pdo->prepare("
                SELECT placa
                FROM veiculos
                WHERE placa = ?
                AND saida IS NULL
                LIMIT 1
            ");

            $stmt->execute([$placa]);

            if ($stmt->fetch()) {

                throw new Exception(
                    'Este veículo já está registrado na oficina.'
                );
            }

            /*
             * Verifica se a posição da chave está ocupada
             */

            $stmt = $pdo->prepare("
                SELECT placa
                FROM veiculos
                WHERE posicao_chave = ?
                AND saida IS NULL
                LIMIT 1
            ");

            $stmt->execute([$posicao]);

            if ($veiculo = $stmt->fetch()) {

                throw new Exception(
                    'A posição ' . $posicao .
                    ' já está ocupada pela placa ' .
                    $veiculo['placa'] . '.'
                );
            }

            /*
             * Procura o cliente pelo telefone
             */

            $clienteId = null;

            if ($telefone !== '') {

                $stmt = $pdo->prepare("
                    SELECT id
                    FROM clientes
                    WHERE telefone = ?
                    LIMIT 1
                ");

                $stmt->execute([$telefone]);

                $cliente = $stmt->fetch();

                if ($cliente) {

                    $clienteId = $cliente['id'];

                }

            }

            /*
             * Se o cliente não existir, cria
             */

            if (!$clienteId && $nome !== '') {

                $stmt = $pdo->prepare("
                    INSERT INTO clientes (
                        nome,
                        telefone
                    )
                    VALUES (?, ?)
                ");

                $stmt->execute([
                    $nome,
                    $telefone
                ]);

                $clienteId = $pdo->lastInsertId();

            }

            /*
             * Cadastra o veículo
             */

            $stmt = $pdo->prepare("
                INSERT INTO veiculos (
                    placa,
                    cliente_id,
                    mecanico_id,
                    modelo,
                    marca,
                    ano,
                    cor,
                    km_atual,
                    status,
                    posicao_chave,
                    observacoes
                )
                VALUES (
                    ?, ?, ?, ?, ?, ?, ?, ?, 'ENTRADA', ?, ?
                )
            ");

            $stmt->execute([
                $placa,
                $clienteId,
                $mecanico_id ?: null,
                $modelo,
                $marca,
                $ano ?: null,
                $cor,
                $km ?: null,
                $posicao,
                $observacoes
            ]);


            $veiculoId = $pdo->lastInsertId();

          
            $osId = $pdo->lastInsertId();
            /*
             * Cria a Ordem de Serviço (O.S.) de entrada
             */

            $stmtOS = $pdo->prepare("
                INSERT INTO ordens_servico (
                    veiculo_id,
                    placa,
                    defeito_relatado,
                    itens_veiculo,
                    estado_lataria,
                    foto_lataria,
                    nivel_combustivel,
                    observacoes,
                    status
                )
				VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");

            $stmtOS->execute([
                $veiculoId,
                $placa,
                $defeito_relatado,
                $itens_veiculo,
                $estado_lataria,
                $foto_lataria,
                $nivel_combustivel,
                $observacoes,
				'ABERTA'
            ]);

            $osId = $pdo->lastInsertId();

            /*
             * Registra comando para o Arduino
             */

            $stmt = $pdo->prepare("
                INSERT INTO arduino_comandos (
                    placa,
                    status,
                    comando
                )
                VALUES (?, 'ENTRADA', 'VERMELHO')
            ");

            $stmt->execute([
                $placa
            ]);

            $pdo->commit();

            $sucesso =
                'Veículo ' . $placa .
                ' registrado com sucesso na posição ' .
                $posicao . '. O.S. #' . $osId . ' criada!';

        }

        catch (Exception $e) {

            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            $erro = $e->getMessage();

        }

    }

}

// Busca lista de mecânicos ativos
$stmtMecanicos = $pdo->query("SELECT id, nome, especialidade FROM funcionarios WHERE ativo = 1 AND cargo = 'MECANICO' ORDER BY nome ASC");
$mecanicos = $stmtMecanicos->fetchAll();

?>

<!DOCTYPE html>

<html lang="pt-BR">

<head>

    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>Entrada de veículo - EDTEC-SOLUTION</title>

    <link rel="stylesheet"
          href="style.css">

</head>

<body>

<?php
$subtitulo = 'Entrada de Veículo';
include 'header.php';
?>

<main>

    <div class="form-container">

        <h2>🚗 Registrar entrada</h2>

<?php if ($erro): ?>

        <div class="alerta erro">

            <?= htmlspecialchars($erro) ?>

        </div>

<?php endif; ?>


<?php if ($sucesso): ?>

        <div class="alerta sucesso">

            <?= htmlspecialchars($sucesso) ?>

        </div>

<?php endif; ?>


        <form method="POST" enctype="multipart/form-data">

            <div class="campo">

                <label>Placa *</label>

                <input
                    type="text"
                    name="placa"
                    maxlength="10"
                    required
                    placeholder="ABC1D23"
                    value="<?= htmlspecialchars(
                        $_POST['placa'] ?? ''
                    ) ?>"
                >

            </div>


            <div class="campo">

                <label>Nome do cliente</label>

                <input
                    type="text"
                    name="nome"
                    placeholder="Nome completo"
                    value="<?= htmlspecialchars(
                        $_POST['nome'] ?? ''
                    ) ?>"
                >

            </div>


            <div class="campo">

                <label>Telefone</label>

                <input
                    type="text"
                    name="telefone"
                    placeholder="(85) 99999-9999"
                    value="<?= htmlspecialchars(
                        $_POST['telefone'] ?? ''
                    ) ?>"
                >

            </div>


            <div class="linha">

                <div class="campo">

                    <label>Marca</label>

                    <input
                        type="text"
                        name="marca"
                        id="input-marca"
                        list="lista-marcas"
                        placeholder="Chevrolet (selecione ou digite)"
                        autocomplete="off"
                        value="<?= htmlspecialchars(
                            $_POST['marca'] ?? ''
                        ) ?>"
                    >

                    <datalist id="lista-marcas"></datalist>

                </div>


                <div class="campo">

                    <label>Modelo *</label>

                    <input
                        type="text"
                        name="modelo"
                        id="input-modelo"
                        list="lista-modelos"
                        required
                        placeholder="Onix (selecione ou digite)"
                        autocomplete="off"
                        value="<?= htmlspecialchars(
                            $_POST['modelo'] ?? ''
                        ) ?>"
                    >

                    <datalist id="lista-modelos"></datalist>

                </div>

            </div>


            <div class="linha">

                <div class="campo">

                    <label>Ano</label>

                    <select name="ano" id="select-ano">

                        <option value="">Selecione o ano</option>

<?php
$anoAtual = (int) date('Y') + 1;
$anoSelecionado = (int) ($_POST['ano'] ?? 0);
for ($a = $anoAtual; $a >= 1970; $a--):
?>

                        <option value="<?= $a ?>" <?= $anoSelecionado === $a ? 'selected' : '' ?>>
                            <?= $a ?>
                        </option>

<?php endfor; ?>

                    </select>

                </div>


                <div class="campo">

                    <label>Cor</label>

                    <input
                        type="text"
                        name="cor"
                        placeholder="Prata"
                        value="<?= htmlspecialchars(
                            $_POST['cor'] ?? ''
                        ) ?>"
                    >

                </div>


                <div class="campo">

                    <label>KM atual</label>

                    <input
                        type="number"
                        name="km_atual"
                        min="0"
                        value="<?= htmlspecialchars(
                            $_POST['km_atual'] ?? ''
                        ) ?>"
                    >

                </div>

            </div>


            <div class="campo">

                <label>🔑 Posição da chave no painel *</label>

                <select
                    name="posicao_chave"
                    required
                >

                    <option value="">
                        Selecione a posição
                    </option>

<?php

for ($i = 1; $i <= 10; $i++) {

    $stmt = $pdo->prepare("
        SELECT placa
        FROM veiculos
        WHERE posicao_chave = ?
        AND saida IS NULL
        LIMIT 1
    ");

    $stmt->execute([$i]);

    $ocupada = $stmt->fetch();

?>

                    <option
                        value="<?= $i ?>"
                        <?= (
                            isset($_POST['posicao_chave']) &&
                            $_POST['posicao_chave'] == $i
                        ) ? 'selected' : '' ?>
                        <?= $ocupada ? 'disabled' : '' ?>
                    >

                        Posição <?= $i ?>

                        <?= $ocupada
                            ? ' - OCUPADA (' .
                              htmlspecialchars(
                                  $ocupada['placa']
                              ) .
                              ')'
                            : ' - DISPONÍVEL'
                        ?>

                    </option>

<?php } ?>

                </select>

            </div>

            <div class="campo">

                <label>👨‍🔧 Mecânico Responsável</label>

                <select name="mecanico_id">
                    <option value="">Selecione o mecânico (Opcional)</option>
                    <?php foreach ($mecanicos as $m): ?>
                        <option value="<?= $m['id'] ?>" <?= (isset($_POST['mecanico_id']) && $_POST['mecanico_id'] == $m['id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($m['nome']) ?> (<?= htmlspecialchars($m['especialidade']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>

            </div>

            <hr style="margin: 20px 0; border: 0; border-top: 1px solid #e2e8f0;">

            <h3>📋 Checklist & Vistoria da Ordem de Serviço</h3>

            <div class="campo">
                <label>🛠️ Defeito Apresentado / Reclamação do Cliente</label>
                <textarea
                    name="defeito_relatado"
                    rows="3"
                    placeholder="Descreva os defeitos relatados (ex: barulho na suspensão dianteira, luz da injeção acesa...)"
                ><?= htmlspecialchars($_POST['defeito_relatado'] ?? '') ?></textarea>
            </div>

            <div class="linha">
                <div class="campo">
                    <label>⛽ Nível de Combustível</label>
                    <select name="nivel_combustivel">
                        <option value="Reserva">Reserva</option>
                        <option value="1/4">1/4</option>
                        <option value="1/2" selected>1/2 (Meio Tanque)</option>
                        <option value="3/4">3/4</option>
                        <option value="Cheio">Cheio</option>
                    </select>
                </div>

                <div class="campo">
                    <label>📷 Foto da Lataria / Veículo</label>
                    <input type="file" name="foto_lataria" accept="image/*">
                </div>
            </div>

            <div class="campo">
                <label>📦 Itens Encontrados no Veículo (Checklist de Pertences)</label>
                <div class="checklist-grid">
                    <?php
                    $opcoesItens = ['Estepe', 'Macaco', 'Triângulo', 'Chave de Roda', 'Manual do Proprietário', 'Rádio / Multimídia', 'Documento do Veículo', 'Chave Reserva', 'Ferramentas'];
                    foreach ($opcoesItens as $item):
                    ?>
                        <label class="checklist-item">
                            <input type="checkbox" name="itens_veiculo[]" value="<?= $item ?>" checked>
                            <span><?= $item ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="campo">
                <label>🚗 Estado da Lataria / Avarias Prévias</label>
                <textarea
                    name="estado_lataria"
                    rows="2"
                    placeholder="Registre avarias, riscos ou amassados pré-existentes..."
                ><?= htmlspecialchars($_POST['estado_lataria'] ?? '') ?></textarea>
            </div>

            <div class="campo">

                <label>Observações Gerais</label>

                <textarea
                    name="observacoes"
                    rows="5"
                    placeholder="Defeitos informados pelo cliente..."
                ><?= htmlspecialchars(
                    $_POST['observacoes'] ?? ''
                ) ?></textarea>

            </div>


            <div class="botoes">

                <button
                    type="submit"
                    class="botao"
                >

                    🚗 Registrar entrada

                </button>


                <a
                    href="index.php"
                    class="botao secundario"
                >

                    Voltar

                </a>

            </div>

        </form>

    </div>

<script>
const marcasEModelos = {
    "Chevrolet": ["Onix", "Onix Plus", "Prisma", "Celta", "Classic", "Corsa", "Astra", "Vectra", "Tracker", "S10", "Spin", "Cruze", "Montana", "Equinox", "Trailblazer", "Meriva", "Zafira", "Agile", "Cobalt", "Kadett", "Monza", "Opala", "Omega"],
    "Volkswagen": ["Gol", "Voyage", "Fox", "Polo", "Virtus", "T-Cross", "Nivus", "Taos", "Saveiro", "Amarok", "Jetta", "Golf", "Up!", "Parati", "Santana", "Kombi", "Fusca", "Bora", "SpaceFox", "Passat", "Tiguan"],
    "Fiat": ["Uno", "Palio", "Siena", "Grand Siena", "Strada", "Toro", "Mobi", "Argo", "Cronos", "Pulse", "Fastback", "Fiorino", "Idea", "Punto", "Stilo", "Doblo", "Linea", "Ducato", "Tempra", "Marea", "147", "Titano"],
    "Ford": ["Ka", "Ka+", "Fiesta", "Focus", "EcoSport", "Ranger", "Fusion", "Courier", "Escort", "Verona", "Belina", "Corcel", "F-1000", "F-250", "Edge", "Territory", "Maverick", "Bronco Sport"],
    "Toyota": ["Corolla", "Corolla Cross", "Hilux", "SW4", "Etios", "Yaris", "RAV4", "Fielder", "Camry", "Corona", "Bandeirante", "Prius"],
    "Honda": ["Civic", "Fit", "City", "HR-V", "WR-V", "CR-V", "Accord", "ZR-V"],
    "Hyundai": ["HB20", "HB20S", "HB20X", "Creta", "Tucson", "ix35", "Santa Fe", "i30", "Azera", "Elantra", "HR", "Veloster"],
    "Renault": ["Kwid", "Sandero", "Logan", "Duster", "Captur", "Oroch", "Master", "Clio", "Megane", "Scenic", "Fluence", "Kardian", "Symbol"],
    "Nissan": ["Kicks", "March", "Versa", "Sentra", "Frontier", "Tiida", "Livina"],
    "Jeep": ["Renegade", "Compass", "Commander", "Wrangler", "Cherokee", "Grand Cherokee"],
    "Peugeot": ["208", "2008", "3008", "206", "207", "307", "308", "408", "Partner", "Boxer", "Expert"],
    "Citroën": ["C3", "C3 Aircross", "C4 Cactus", "C4 Lounge", "C4 Pallas", "Aircross", "Berlingo", "Jumper", "Jumpy"],
    "Mitsubishi": ["L200 Triton", "ASX", "Outlander", "Pajero", "Pajero TR4", "Pajero Full", "Eclipse Cross", "Lancer"],
    "Chery": ["Tiggo 2", "Tiggo 3x", "Tiggo 5x", "Tiggo 7", "Tiggo 8", "QQ", "Celer", "Arrizo 5", "Arrizo 6"],
    "BMW": ["320i", "328i", "Série 1", "Série 3", "X1", "X3", "X5", "X6", "Série 5"],
    "Mercedes-Benz": ["Classe A", "Classe C", "Classe E", "GLA", "GLC", "Sprinter"],
    "Audi": ["A3", "A4", "A5", "A6", "Q3", "Q5", "Q7"],
    "Kia": ["Sportage", "Cerato", "Picanto", "Sorento", "Soul", "Bongo", "Stonic", "Niro"],
    "Volvo": ["XC40", "XC60", "XC90", "C40", "V40"],
    "RAM": ["Rampage", "1500", "2500", "3500"],
    "BYD": ["Dolphin", "Dolphin Mini", "Seal", "Song Plus", "Yuan Plus", "King", "Shark"]
};

document.addEventListener('DOMContentLoaded', function() {
    const inputMarca = document.getElementById('input-marca');
    const datalistMarcas = document.getElementById('lista-marcas');
    const inputModelo = document.getElementById('input-modelo');
    const datalistModelos = document.getElementById('lista-modelos');

    if (!inputMarca || !datalistMarcas || !inputModelo || !datalistModelos) return;

    function popularMarcas() {
        datalistMarcas.innerHTML = '';
        Object.keys(marcasEModelos).sort().forEach(function(marca) {
            const option = document.createElement('option');
            option.value = marca;
            datalistMarcas.appendChild(option);
        });
    }

    function atualizarModelos() {
        datalistModelos.innerHTML = '';
        const marcaSelecionada = inputMarca.value.trim();
        
        let modelos = [];
        const marcaEncontrada = Object.keys(marcasEModelos).find(function(m) {
            return m.toLowerCase() === marcaSelecionada.toLowerCase();
        });
        
        if (marcaEncontrada) {
            modelos = marcasEModelos[marcaEncontrada];
        } else {
            Object.values(marcasEModelos).forEach(function(lista) {
                modelos = modelos.concat(lista);
            });
            modelos = Array.from(new Set(modelos));
        }

        modelos.sort().forEach(function(modelo) {
            const option = document.createElement('option');
            option.value = modelo;
            datalistModelos.appendChild(option);
        });
    }

    popularMarcas();
    atualizarModelos();

    inputMarca.addEventListener('input', atualizarModelos);
    inputMarca.addEventListener('change', atualizarModelos);
});
</script>

</body>

</html>