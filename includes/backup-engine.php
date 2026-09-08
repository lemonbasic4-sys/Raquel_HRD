<?php
/**
 * Shared database backup helpers for manual and scheduled backups.
 */

function backup_get_type_meta($type) {
    $type = in_array($type, ['full', 'schema', 'data'], true) ? $type : 'full';

    if ($type === 'schema') {
        return [
            'type' => 'schema',
            'prefix' => 'raquel_hris_schema_',
            'label' => 'Schema Only',
            'mysqldump_flags' => '--no-data',
        ];
    }

    if ($type === 'data') {
        return [
            'type' => 'data',
            'prefix' => 'raquel_hris_data_',
            'label' => 'Data Only',
            'mysqldump_flags' => '--no-create-info',
        ];
    }

    return [
        'type' => 'full',
        'prefix' => 'raquel_hris_backup_',
        'label' => 'Full Backup',
        'mysqldump_flags' => '',
    ];
}

function backup_create_database_snapshot($conn, $type = 'full') {
    $meta = backup_get_type_meta($type);
    $backup_dir = dirname(__DIR__) . '/backups/';

    if (!is_dir($backup_dir) && !@mkdir($backup_dir, 0777, true)) {
        return [
            'success' => false,
            'error' => 'The backups folder could not be created.',
            'type' => $meta['type'],
            'label' => $meta['label'],
        ];
    }

    $filename = $meta['prefix'] . date('Y-m-d_His') . '.sql';
    $dest_path = $backup_dir . $filename;
    $method = 'mysqldump';

    $mysqldump_path = file_exists('C:\xampp\mysql\bin\mysqldump.exe')
        ? 'C:\xampp\mysql\bin\mysqldump.exe'
        : 'mysqldump';

    $password_arg = DB_PASS !== '' ? '--password=' . escapeshellarg(DB_PASS) : '--password=';
    $command = sprintf(
        '%s --user=%s %s --host=%s %s %s > %s',
        escapeshellarg($mysqldump_path),
        escapeshellarg(DB_USER),
        $password_arg,
        escapeshellarg(DB_HOST),
        $meta['mysqldump_flags'],
        escapeshellarg(DB_NAME),
        escapeshellarg($dest_path)
    );

    $return_var = -1;
    $output = [];
    try {
        @exec($command, $output, $return_var);
    } catch (Throwable $e) {
        $return_var = -1;
    }

    $success = ($return_var === 0 && file_exists($dest_path) && filesize($dest_path) > 0);

    if (!$success) {
        $method = 'php_native';
        if ($meta['type'] === 'schema') {
            $success = backup_generate_php_schema($conn, $dest_path);
        } elseif ($meta['type'] === 'data') {
            $success = backup_generate_php_data($conn, $dest_path);
        } else {
            $success = backup_generate_php_full($conn, $dest_path);
        }
    }

    if (!$success) {
        if (file_exists($dest_path)) {
            @unlink($dest_path);
        }

        return [
            'success' => false,
            'error' => "Database {$meta['label']} backup failed. Ensure database settings are correct and the backups folder is writable.",
            'type' => $meta['type'],
            'label' => $meta['label'],
            'method' => $method,
        ];
    }

    return [
        'success' => true,
        'filename' => $filename,
        'path' => $dest_path,
        'size' => filesize($dest_path),
        'method' => $method,
        'type' => $meta['type'],
        'label' => $meta['label'],
    ];
}

function backup_cleanup_old_files($keep) {
    $keep = max(1, (int)$keep);
    $backup_dir = dirname(__DIR__) . '/backups/';
    $files = @scandir($backup_dir);
    $files = is_array($files) ? array_diff($files, ['.', '..', '.htaccess']) : [];
    $sql_files = [];

    foreach ($files as $file) {
        $path = $backup_dir . $file;
        if (is_file($path) && pathinfo($file, PATHINFO_EXTENSION) === 'sql') {
            $sql_files[] = ['name' => $file, 'time' => filemtime($path)];
        }
    }

    usort($sql_files, fn($a, $b) => $b['time'] - $a['time']);

    foreach (array_slice($sql_files, $keep) as $old) {
        @unlink($backup_dir . $old['name']);
    }
}

function backup_generate_php_full($conn, $dest_path) {
    $sql  = backup_php_header('Full Backup (Structure + Data)');
    $sql .= "SET FOREIGN_KEY_CHECKS=0;\n\n";

    foreach (backup_get_tables($conn) as $table) {
        $sql .= backup_php_table_schema($conn, $table);
        $sql .= backup_php_table_data($conn, $table);
    }

    $sql .= "SET FOREIGN_KEY_CHECKS=1;\n";
    return file_put_contents($dest_path, $sql) !== false;
}

function backup_generate_php_schema($conn, $dest_path) {
    $sql  = backup_php_header('Schema Only (Table Structures)');
    $sql .= "SET FOREIGN_KEY_CHECKS=0;\n\n";

    foreach (backup_get_tables($conn) as $table) {
        $sql .= backup_php_table_schema($conn, $table);
    }

    $sql .= "SET FOREIGN_KEY_CHECKS=1;\n";
    return file_put_contents($dest_path, $sql) !== false;
}

function backup_generate_php_data($conn, $dest_path) {
    $sql  = backup_php_header('Data Only (Records / Values)');
    $sql .= "SET FOREIGN_KEY_CHECKS=0;\n\n";

    foreach (backup_get_tables($conn) as $table) {
        $sql .= backup_php_table_data($conn, $table);
    }

    $sql .= "SET FOREIGN_KEY_CHECKS=1;\n";
    return file_put_contents($dest_path, $sql) !== false;
}

function backup_php_header($label) {
    return "-- Raquel HRIS Database Backup ({$label})\n"
        . "-- Generated: " . date('Y-m-d H:i:s') . "\n"
        . "-- Database: " . DB_NAME . "\n\n";
}

function backup_get_tables($conn) {
    $tables = [];
    $result = $conn->query("SHOW TABLES");

    if (!$result) {
        return $tables;
    }

    while ($row = $result->fetch_row()) {
        $tables[] = $row[0];
    }

    return $tables;
}

function backup_php_table_schema($conn, $table) {
    $table_name = str_replace('`', '``', $table);
    $sql  = "-- Table structure for `{$table}`\n";
    $sql .= "DROP TABLE IF EXISTS `{$table_name}`;\n";
    $result = $conn->query("SHOW CREATE TABLE `{$table_name}`");

    if (!$result) {
        return $sql . "\n";
    }

    $row = $result->fetch_row();
    $sql .= $row[1] . ";\n\n";
    return $sql;
}

function backup_php_table_data($conn, $table) {
    $table_name = str_replace('`', '``', $table);
    $sql = "-- Data for `{$table}`\n";
    $result = $conn->query("SELECT * FROM `{$table_name}`");

    if (!$result) {
        return $sql . "\n";
    }

    $row_count = 0;
    while ($row = $result->fetch_row()) {
        $values = array_map(
            fn($value) => isset($value) ? "'" . $conn->real_escape_string($value) . "'" : 'NULL',
            $row
        );
        $sql .= "INSERT INTO `{$table_name}` VALUES(" . implode(',', $values) . ");\n";
        $row_count++;
    }

    if ($row_count === 0) {
        $sql .= "-- (no records)\n";
    }

    return $sql . "\n";
}
?>
