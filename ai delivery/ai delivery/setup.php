<?php
/**
 * Database Setup Script
 * Run this file once to set up the database
 * Access: http://localhost/ai delivery/setup.php
 */

// Database configuration
$db_host = 'localhost';
$db_user = 'root';
$db_pass = '';
$db_name = 'ai_delivery_system';

echo "<h1>AI Delivery System - Database Setup</h1>";

// Connect to MySQL server
$conn = new mysqli($db_host, $db_user, $db_pass);

if ($conn->connect_error) {
    die("<p style='color: red;'>Connection failed: " . $conn->connect_error . "</p>");
}

echo "<p style='color: green;'>✓ Connected to MySQL server</p>";

// Create database
$sql = "CREATE DATABASE IF NOT EXISTS $db_name CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci";
if ($conn->query($sql)) {
    echo "<p style='color: green;'>✓ Database created/verified</p>";
} else {
    die("<p style='color: red;'>Error creating database: " . $conn->error . "</p>");
}

// Select database
$conn->select_db($db_name);

// Read and execute schema file
$schema_file = __DIR__ . '/database/schema.sql';
if (!file_exists($schema_file)) {
    die("<p style='color: red;'>Schema file not found: $schema_file</p>");
}

$schema = file_get_contents($schema_file);

// Split by semicolons and execute each statement
$statements = array_filter(array_map('trim', explode(';', $schema)));

$executed = 0;
$errors = 0;

foreach ($statements as $statement) {
    if (empty($statement) || strpos($statement, '--') === 0) {
        continue;
    }
    
    // Skip USE statement as we already selected the database
    if (stripos($statement, 'USE ') === 0) {
        continue;
    }
    
    if ($conn->query($statement)) {
        $executed++;
    } else {
        // Ignore "already exists" errors
        if (strpos($conn->error, 'already exists') === false && 
            strpos($conn->error, 'Duplicate entry') === false) {
            echo "<p style='color: orange;'>⚠ Warning: " . $conn->error . "</p>";
            $errors++;
        }
    }
}

echo "<p style='color: green;'>✓ Executed $executed SQL statements</p>";

if ($errors > 0) {
    echo "<p style='color: orange;'>⚠ $errors warnings occurred (may be normal if tables already exist)</p>";
}

// Verify tables
$tables = ['users', 'drivers', 'packages', 'notifications'];
$all_tables_exist = true;

foreach ($tables as $table) {
    $result = $conn->query("SHOW TABLES LIKE '$table'");
    if ($result->num_rows > 0) {
        echo "<p style='color: green;'>✓ Table '$table' exists</p>";
    } else {
        echo "<p style='color: red;'>✗ Table '$table' missing</p>";
        $all_tables_exist = false;
    }
}

if ($all_tables_exist) {
    echo "<h2 style='color: green;'>✓ Setup Complete!</h2>";
    echo "<p><a href='index.html'>Go to Application</a></p>";
} else {
    echo "<h2 style='color: red;'>✗ Setup Incomplete</h2>";
    echo "<p>Please check the errors above and try again.</p>";
}

$conn->close();
?>

