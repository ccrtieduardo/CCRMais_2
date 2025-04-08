<?php
session_start();
require_once(__DIR__ . '/../config/database.php');

if (!isset($_SESSION["usuario_id"]) || $_SESSION["usuario_tipo"] !== "professor") {
    header("Location: login.php");
    exit();
}

// Configurações do cache
$cache_dir = __DIR__ . '/../cache/';
$cache_time = 300; // 5 minutos em segundos

$mensagem = '';
$alunos = [];
$transacoes = [];

try {
    $pdo = getDBConnection();
    
    // Listar alunos com cache (5 minutos)
    $cache_key = 'alunos_list_' . md5($_SESSION["usuario_id"]);
    $cache_file = $cache_dir . $cache_key . '.cache';
    
    // Verifica se o cache existe e é válido
    if (file_exists($cache_file) && (time() - filemtime($cache_file) < $cache_time)) {
        $alunos = unserialize(file_get_contents($cache_file));
    } else {
        $sql = "SELECT id, nome FROM usuarios WHERE tipo = 'aluno' ORDER BY nome";
        $stmt = $pdo->query($sql);
        $alunos = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Cria o diretório de cache se não existir
        if (!file_exists($cache_dir)) {
            mkdir($cache_dir, 0755, true);
        }
        
        // Salva no cache
        file_put_contents($cache_file, serialize($alunos));
    }
    
    // Buscar histórico de transações com paginação
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $limit = 10;
    $offset = ($page - 1) * $limit;
    
    $sql = "SELECT SQL_CALC_FOUND_ROWS t.quantidade, t.tipo, t.motivo, u.nome AS aluno_nome, 
                   t.data_hora, t.data_atividade 
            FROM transacoes t
            JOIN usuarios u ON t.aluno_id = u.id
            WHERE t.professor_id = ?
            ORDER BY t.data_hora DESC
            LIMIT ? OFFSET ?";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$_SESSION["usuario_id"], $limit, $offset]);
    $transacoes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $total = $pdo->query("SELECT FOUND_ROWS()")->fetchColumn();
    $totalPages = ceil($total / $limit);
    
    // Processar adição/remoção de moedas
    if ($_SERVER["REQUEST_METHOD"] == "POST") {
        $alunos_ids = $_POST["alunos_ids"] ?? [];
        $quantidade = (int)$_POST["quantidade"];
        $tipo = $_POST["tipo"];
        $motivo = $_POST["motivo"];
        $data_atividade = $_POST["data_atividade"];
        
        if (empty($alunos_ids)) {
            $mensagem = "Selecione pelo menos um aluno!";
        } elseif ($quantidade <= 0) {
            $mensagem = "Quantidade deve ser maior que zero!";
        } else {
            try {
                $pdo->beginTransaction();
                
                // Preparar statements para reutilização
                $checkAluno = $pdo->prepare("SELECT usuario_id FROM alunos WHERE usuario_id = ?");
                $insertAluno = $pdo->prepare("INSERT INTO alunos (usuario_id, saldo_moedas) VALUES (?, 0)");
                $updateSaldo = $pdo->prepare("UPDATE alunos SET saldo_moedas = saldo_moedas " . 
                                           ($tipo == 'adicao' ? '+' : '-') . " ? WHERE usuario_id = ?");
                $insertTransacao = $pdo->prepare("INSERT INTO transacoes 
                    (aluno_id, professor_id, quantidade, tipo, motivo, data_atividade) 
                    VALUES (?, ?, ?, ?, ?, ?)");
                
                foreach ($alunos_ids as $aluno_id) {
                    // Verificar/criar registro do aluno
                    $checkAluno->execute([$aluno_id]);
                    if (!$checkAluno->fetch()) {
                        $insertAluno->execute([$aluno_id]);
                    }
                    
                    // Atualizar saldo
                    $updateSaldo->execute([$quantidade, $aluno_id]);
                    
                    // Registrar transação
                    $insertTransacao->execute([
                        $aluno_id, 
                        $_SESSION["usuario_id"], 
                        $quantidade, 
                        $tipo, 
                        $motivo, 
                        $data_atividade
                    ]);
                }
                
                $pdo->commit();
                $mensagem = "Operação realizada com sucesso para " . count($alunos_ids) . " aluno(s)!";
                
                // Invalidar cache - apaga o arquivo de cache
                if (file_exists($cache_file)) {
                    unlink($cache_file);
                }
                
                // Redirecionar para evitar reenvio do formulário
                header("Location: professor_dashboard.php?success=1");
                exit();
                
            } catch (PDOException $e) {
                $pdo->rollBack();
                error_log("Erro na transação: " . $e->getMessage());
                $mensagem = "Erro ao processar operação: " . $e->getMessage();
            }
        }
    }
    
} catch (PDOException $e) {
    error_log("Erro no dashboard do professor: " . $e->getMessage());
    $mensagem = "Erro no sistema. Tente novamente mais tarde.";
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Dashboard do Professor</title>
    <link rel="stylesheet" href="../professor_dashboard.css">
</head>
<body>
    <div class="container">
        <h1>Bem-vindo, Professor(a) <?= htmlspecialchars($_SESSION["usuario_nome"]) ?>!</h1>
        <a href="logout.php" class="btn">Sair</a>

        <?php if (isset($_GET['success'])): ?>
            <div class="alert success">Operação realizada com sucesso!</div>
        <?php elseif (!empty($mensagem)): ?>
            <div class="alert <?= strpos($mensagem, 'Erro') !== false ? 'error' : 'warning' ?>">
                <?= htmlspecialchars($mensagem) ?>
            </div>
        <?php endif; ?>

        <h2>Gerenciar Moedas</h2>
        <?php if (empty($alunos)): ?>
            <p class="warning">Nenhum aluno cadastrado encontrado!</p>
        <?php else: ?>
            <form method="POST" id="moedasForm">
                <div class="form-group">
                    <label>Aluno(s):</label>
                    <select name="alunos_ids[]" multiple required size="5" class="multi-select">
                        <?php foreach ($alunos as $a): ?>
                        <option value="<?= htmlspecialchars($a['id']) ?>">
                            <?= htmlspecialchars($a['nome']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <small class="select-hint">Pressione Ctrl (Windows) ou Command (Mac) para selecionar múltiplos alunos</small>
                </div>

                <div class="form-group">
                    <label>Quantidade:</label>
                    <input type="number" name="quantidade" min="1" required>
                </div>

                <div class="form-group">
                    <label>Tipo:</label>
                    <select name="tipo" required>
                        <option value="adicao">Adicionar</option>
                        <option value="remocao">Remover</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Motivo:</label>
                    <select name="motivo" required>
                        <option value="comportamento">Comportamento</option>
                        <option value="atividade">Atividade</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Data da Atividade:</label>
                    <input type="datetime-local" name="data_atividade" required>
                </div>

                <button type="submit">Confirmar</button>
            </form>
        <?php endif; ?>

        <h2>Histórico de Transações Recentes</h2>
        <?php if (empty($transacoes)): ?>
            <p>Nenhuma transação realizada ainda.</p>
        <?php else: ?>
            <div class="table-container">
                <table>
                    <tr>
                        <th>Aluno</th>
                        <th>Quantidade</th>
                        <th>Tipo</th>
                        <th>Motivo</th>
                        <th>Data/Hora</th>
                        <th>Data Atividade</th>
                    </tr>
                    <?php foreach ($transacoes as $t): ?>
                    <tr>
                        <td><?= htmlspecialchars($t['aluno_nome']) ?></td>
                        <td><?= $t['quantidade'] ?></td>
                        <td><?= $t['tipo'] == 'adicao' ? 'Adição' : 'Remoção' ?></td>
                        <td><?= ucfirst($t['motivo']) ?></td>
                        <td><?= date('d/m/Y H:i', strtotime($t['data_hora'])) ?></td>
                        <td><?= isset($t['data_atividade']) ? date('d/m/Y H:i', strtotime($t['data_atividade'])) : '--' ?></td>
                    </tr>
                    <?php endforeach; ?>
                </table>
                
                <!-- Paginação -->
                <div class="pagination">
                    <?php if ($page > 1): ?>
                        <a href="?page=<?= $page-1 ?>">Anterior</a>
                    <?php endif; ?>
                    
                    <span>Página <?= $page ?> de <?= $totalPages ?></span>
                    
                    <?php if ($page < $totalPages): ?>
                        <a href="?page=<?= $page+1 ?>">Próxima</a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
    
    <script>
        // Prevenir múltiplos envios do formulário
        document.getElementById('moedasForm').addEventListener('submit', function(e) {
            const btn = this.querySelector('button[type="submit"]');
            btn.disabled = true;
            btn.textContent = 'Processando...';
        });
    </script>
</body>
</html>