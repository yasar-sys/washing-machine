<?php
/**
 * Database Helper Functions
 */

/**
 * Execute query with parameters
 */
function executeQuery($sql, $params = [], $types = '') {
    global $conn;
    
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        return false;
    }
    
    if (!empty($params)) {
        if (empty($types)) {
            $types = str_repeat('s', count($params));
        }
        mysqli_stmt_bind_param($stmt, $types, ...$params);
    }
    
    $success = mysqli_stmt_execute($stmt);
    if (!$success) {
        mysqli_stmt_close($stmt);
        return false;
    }
    
    $result = mysqli_stmt_get_result($stmt);
    mysqli_stmt_close($stmt);
    
    return $result;
}

/**
 * Insert data into table
 */
function insertData($table, $data) {
    global $conn;
    
    $columns = implode(', ', array_keys($data));
    $placeholders = implode(', ', array_fill(0, count($data), '?'));
    $values = array_values($data);
    
    $sql = "INSERT INTO $table ($columns) VALUES ($placeholders)";
    $stmt = mysqli_prepare($conn, $sql);
    
    if (!$stmt) {
        return false;
    }
    
    $types = str_repeat('s', count($data));
    mysqli_stmt_bind_param($stmt, $types, ...$values);
    $success = mysqli_stmt_execute($stmt);
    $insert_id = mysqli_insert_id($conn);
    mysqli_stmt_close($stmt);
    
    return $success ? $insert_id : false;
}

/**
 * Update data in table
 */
function updateData($table, $data, $where, $where_params = []) {
    global $conn;
    
    $set_clause = implode(' = ?, ', array_keys($data)) . ' = ?';
    $values = array_values($data);
    $all_params = array_merge($values, $where_params);
    
    $sql = "UPDATE $table SET $set_clause WHERE $where";
    $stmt = mysqli_prepare($conn, $sql);
    
    if (!$stmt) {
        return false;
    }
    
    $types = str_repeat('s', count($all_params));
    mysqli_stmt_bind_param($stmt, $types, ...$all_params);
    $success = mysqli_stmt_execute($stmt);
    $affected_rows = mysqli_affected_rows($conn);
    mysqli_stmt_close($stmt);
    
    return $success ? $affected_rows : false;
}

/**
 * Delete data from table
 */
function deleteData($table, $where, $params = []) {
    global $conn;
    
    $sql = "DELETE FROM $table WHERE $where";
    $stmt = mysqli_prepare($conn, $sql);
    
    if (!$stmt) {
        return false;
    }
    
    if (!empty($params)) {
        $types = str_repeat('s', count($params));
        mysqli_stmt_bind_param($stmt, $types, ...$params);
    }
    
    $success = mysqli_stmt_execute($stmt);
    $affected_rows = mysqli_affected_rows($conn);
    mysqli_stmt_close($stmt);
    
    return $success ? $affected_rows : false;
}

/**
 * Get single row
 */
function getRow($sql, $params = []) {
    $result = executeQuery($sql, $params);
    return $result ? $result->fetch_assoc() : false;
}

/**
 * Get multiple rows
 */
function getRows($sql, $params = []) {
    $result = executeQuery($sql, $params);
    
    $rows = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $rows[] = $row;
        }
    }
    
    return $rows;
}

/**
 * Get single value
 */
function getValue($sql, $params = []) {
    $row = getRow($sql, $params);
    return $row ? reset($row) : false;
}

/**
 * Check if record exists
 */
function recordExists($table, $conditions) {
    $where = [];
    $params = [];
    
    foreach ($conditions as $column => $value) {
        $where[] = "$column = ?";
        $params[] = $value;
    }
    
    $where_clause = implode(' AND ', $where);
    $sql = "SELECT COUNT(*) as count FROM $table WHERE $where_clause LIMIT 1";
    
    $result = getValue($sql, $params);
    return $result > 0;
}

/**
 * Begin transaction
 */
function beginTransaction() {
    global $conn;
    return mysqli_begin_transaction($conn);
}

/**
 * Commit transaction
 */
function commitTransaction() {
    global $conn;
    return mysqli_commit($conn);
}

/**
 * Rollback transaction
 */
function rollbackTransaction() {
    global $conn;
    return mysqli_rollback($conn);
}

/**
 * Get last insert ID
 */
function lastInsertId() {
    global $conn;
    return mysqli_insert_id($conn);
}

/**
 * Get affected rows
 */
function affectedRows() {
    global $conn;
    return mysqli_affected_rows($conn);
}

/**
 * Escape string
 */
function escapeString($string) {
    global $conn;
    return mysqli_real_escape_string($conn, $string);
}

/**
 * Get table columns
 */
function getTableColumns($table) {
    global $conn;
    
    $sql = "DESCRIBE $table";
    $result = mysqli_query($conn, $sql);
    
    $columns = [];
    while ($row = $result->fetch_assoc()) {
        $columns[] = $row['Field'];
    }
    
    return $columns;
}

/**
 * Backup database (simple version)
 */
function backupDatabase($backup_path = 'backups/') {
    global $conn;
    
    if (!is_dir($backup_path)) {
        mkdir($backup_path, 0755, true);
    }
    
    $backup_file = $backup_path . 'backup_' . date('Y-m-d_H-i-s') . '.sql';
    $tables = [];
    
    // Get all tables
    $result = mysqli_query($conn, "SHOW TABLES");
    while ($row = $result->fetch_row()) {
        $tables[] = $row[0];
    }
    
    $output = "-- WashMate Database Backup\n";
    $output .= "-- Generated: " . date('Y-m-d H:i:s') . "\n\n";
    
    foreach ($tables as $table) {
        // Table structure
        $output .= "--\n-- Table structure for table `$table`\n--\n\n";
        $output .= "DROP TABLE IF EXISTS `$table`;\n";
        
        $create_result = mysqli_query($conn, "SHOW CREATE TABLE `$table`");
        $create_row = $create_result->fetch_row();
        $output .= $create_row[1] . ";\n\n";
        
        // Table data
        $output .= "--\n-- Dumping data for table `$table`\n--\n\n";
        
        $data_result = mysqli_query($conn, "SELECT * FROM `$table`");
        while ($row = $data_result->fetch_assoc()) {
            $columns = array_map(function($col) {
                return "`$col`";
            }, array_keys($row));
            
            $values = array_map(function($value) use ($conn) {
                if ($value === null) return 'NULL';
                return "'" . mysqli_real_escape_string($conn, $value) . "'";
            }, array_values($row));
            
            $output .= "INSERT INTO `$table` (" . implode(', ', $columns) . ") VALUES (" . implode(', ', $values) . ");\n";
        }
        $output .= "\n";
    }
    
    file_put_contents($backup_file, $output);
    return $backup_file;
}
?>