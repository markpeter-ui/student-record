<?php
// setup.php — auto-create database + table
$host = 'localhost'; $user = 'root'; $pass = '';

try {
    $db = new PDO("mysql:host=$host", $user, $pass);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $db->exec("CREATE DATABASE IF NOT EXISTS student_db
               CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci");
    echo "✅ Database 'student_db' ready.<br>";

    $db->exec("USE student_db");
    $db->exec("CREATE TABLE IF NOT EXISTS students (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        program VARCHAR(100) NOT NULL,
        is_archived TINYINT(1) DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    echo "✅ Table 'students' ready.<br><br>";
    echo "👉 <a href='index.php'>Open Student Record App</a>";
} catch (PDOException $e) {
    die("❌ Setup failed: " . $e->getMessage());
}