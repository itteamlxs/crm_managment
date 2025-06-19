<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = $_POST['password'] ?? '';

    if (!empty($password)) {
        $hashed = password_hash($password, PASSWORD_DEFAULT);
        echo "<p><strong>Hash generado:</strong></p>";
        echo "<textarea rows='3' cols='80' readonly>$hashed</textarea>";
    } else {
        echo "<p style='color:red;'>Introduce una contraseña válida.</p>";
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Generador de Hash</title>
</head>
<body>
    <h2>Generador de Hash para Contraseñas</h2>
    <form method="POST">
        <label for="password">Contraseña:</label>
        <input type="text" name="password" id="password" required>
        <button type="submit">Generar hash</button>
    </form>
</body>
</html>
