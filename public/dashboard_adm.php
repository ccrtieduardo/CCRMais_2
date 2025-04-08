<?php
session_start();
require_once(__DIR__ . '/../config/database.php');

if (!isset($_SESSION["usuario_id"]) || $_SESSION["usuario_tipo"] !== "admin") {
    header("Location: login.php");
    exit();
}

$mensagem = '';
$usuarios = [];

try {
    $pdo = getDBConnection();
    
    // Cadastro de usuário
    if ($_SERVER["REQUEST_METHOD"] == "POST") {
        $nome = trim($_POST["nome"]);
        $email = trim($_POST["email"]);
        $senha = trim($_POST["senha"]);
        $tipo = $_POST["tipo"];
        
        // Validação básica
        if (strlen($senha) < 8) {
            $mensagem = "A senha deve ter pelo menos 8 caracteres!";
        } else {
            $hashed_password = password_hash($senha, PASSWORD_DEFAULT);
            
            $sql = "INSERT INTO usuarios (nome, email, senha, tipo) VALUES (:nome, :email, :senha, :tipo)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':nome' => $nome,
                ':email' => $email,
                ':senha' => $hashed_password,
                ':tipo' => $tipo
            ]);
            
            $mensagem = "Usuário cadastrado com sucesso!";
            
            // Redirecionar para evitar reenvio do formulário
            header("Location: dashboard_adm.php?success=1");
            exit();
        }
    }
    
    // Listar usuários com paginação
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $limit = 10;
    $offset = ($page - 1) * $limit;
    
    $sql = "SELECT SQL_CALC_FOUND_ROWS id, nome, email, tipo 
            FROM usuarios 
            WHERE tipo IN ('professor', 'aluno')
            ORDER BY nome
            LIMIT ? OFFSET ?";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$limit, $offset]);
    $usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $total = $pdo->query("SELECT FOUND_ROWS()")->fetchColumn();
    $totalPages = ceil($total / $limit);
    
} catch (PDOException $e) {
    error_log("Erro no dashboard admin: " . $e->getMessage());
    $mensagem = "Erro no sistema. Tente novamente mais tarde.";
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Administrador</title>
    <link rel="stylesheet" href="../dashboard_adm.css">
</head>
<body>
    <div class="container">
        <h1>Bem-vindo, <?= htmlspecialchars($_SESSION["usuario_nome"]) ?>!</h1>
        <a href="logout.php" class="btn">Sair</a>

        <?php if (isset($_GET['success'])): ?>
            <div class="alert success">Usuário cadastrado com sucesso!</div>
        <?php elseif (!empty($mensagem)): ?>
            <div class="alert <?= strpos($mensagem, 'Erro') !== false ? 'error' : 'warning' ?>">
                <?= htmlspecialchars($mensagem) ?>
            </div>
        <?php endif; ?>

        <h2>Cadastrar Usuário</h2>
        <form method="POST" id="cadastroForm">
            <div class="form-group">
                <label>Nome:</label>
                <input type="text" name="nome" required>
            </div>
            
            <div class="form-group">
                <label>Email:</label>
                <input type="email" name="email" required>
            </div>
            
            <div class="form-group">
                <label>Senha (mínimo 8 caracteres):</label>
                <input type="password" name="senha" minlength="8" required>
            </div>
            
            <div class="form-group">
                <label>Tipo:</label>
                <select name="tipo" required>
                    <option value="aluno">Aluno</option>
                    <option value="professor">Professor</option>
                </select>
            </div>
            
            <button type="submit">Cadastrar</button>
        </form>

        <h2>Usuários Cadastrados</h2>
        <div class="table-container">
            <table>
                <tr>
                    <th>Nome</th>
                    <th>Email</th>
                    <th>Tipo</th>
                </tr>
                <?php foreach ($usuarios as $usuario): ?>
                <tr>
                    <td><?= htmlspecialchars($usuario["nome"]) ?></td>
                    <td><?= htmlspecialchars($usuario["email"]) ?></td>
                    <td><?= ucfirst($usuario["tipo"]) ?></td>
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
    </div>
    
    <script>
        // Prevenir múltiplos envios do formulário
        document.getElementById('cadastroForm').addEventListener('submit', function(e) {
            const btn = this.querySelector('button[type="submit"]');
            btn.disabled = true;
            btn.textContent = 'Cadastrando...';
        });
    </script>
</body>
</html>