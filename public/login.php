<?php
session_start();
require_once(__DIR__ . '/../config/database.php');

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST["email"]);
    $senha = trim($_POST["senha"]);
    
    try {
        $pdo = getDBConnection();
        
        // Consulta preparada com limite para evitar brute force
        $sql = "SELECT id, nome, senha, tipo FROM usuarios WHERE email = :email LIMIT 1";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':email' => $email]);
        $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($usuario && password_verify($senha, $usuario["senha"])) {
            // Regenerar ID da sessão para evitar fixation
            session_regenerate_id(true);
            
            $_SESSION["usuario_id"] = $usuario["id"];
            $_SESSION["usuario_nome"] = $usuario["nome"];
            $_SESSION["usuario_tipo"] = $usuario["tipo"];
            $_SESSION["last_activity"] = time();
            
            // Redirecionamento seguro
            $redirect = match($usuario["tipo"]) {
                'admin' => 'dashboard_adm.php',
                'professor' => 'professor_dashboard.php',
                default => 'aluno_dashboard.php'
            };
            
            header("Location: $redirect");
            exit();
        } else {
            // Atraso para dificultar brute force
            sleep(1);
            $erro = "E-mail ou senha inválidos!";
        }
    } catch (PDOException $e) {
        error_log("Erro no login: " . $e->getMessage());
        $erro = "Erro no sistema. Tente novamente mais tarde.";
    }
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - CCRMais</title>
    <link rel="stylesheet" href="../login.css">
</head>
<body>
    <div class="container">
        <h2>Login</h2>
        <?php if (isset($erro)) echo "<p class='error'>$erro</p>"; ?>
        
        <form method="POST" id="loginForm">
            <label for="email">E-mail:</label>
            <input type="email" name="email" required autocomplete="email">
            
            <label for="senha">Senha:</label>
            <input type="password" name="senha" required autocomplete="current-password">
            
            <button type="submit">Entrar</button>
        </form>
        
        <div class="form-botoes">
            <a href="./index.php" class="btn-voltar">
                <img src="./home.png" alt="Voltar" width="36">
            </a>
        </div>
    </div>
    
    <script>
        // Prevenir múltiplos envios do formulário
        document.getElementById('loginForm').addEventListener('submit', function(e) {
            const btn = this.querySelector('button[type="submit"]');
            btn.disabled = true;
            btn.textContent = 'Autenticando...';
        });
    </script>
</body>
</html>