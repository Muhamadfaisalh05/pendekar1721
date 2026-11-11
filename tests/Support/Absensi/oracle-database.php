<?php

declare(strict_types=1);

/**
 * Helper bersama untuk oracle SEMARAK (capture & export seed).
 *
 * Semua operasi hanya menyentuh database `semarak_oracle` dan dump asli
 * di folder semarak (read-only). Folder sumber tidak pernah dimodifikasi.
 */

const ORACLE_DB = 'semarak_oracle';
const ORACLE_DUMP_PATH = 'C:/laragon/www/Dinsos/semarak/db/semarak.sql';

/**
 * Koneksi PDO ke server MySQL lokal (tanpa memilih database).
 */
function oracleServerPdo(): PDO
{
    return new PDO('mysql:host=127.0.0.1;port=3306', 'root', '', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
}

/**
 * Koneksi PDO ke database oracle.
 */
function oraclePdo(): PDO
{
    $pdo = oracleServerPdo();
    $pdo->exec('USE `'.ORACLE_DB.'`');

    return $pdo;
}

/**
 * Reset database oracle dari dump asli (drop + create + import).
 */
function resetOracleDatabase(): void
{
    $pdo = oracleServerPdo();

    $pdo->exec('DROP DATABASE IF EXISTS `'.ORACLE_DB.'`');
    $pdo->exec('CREATE DATABASE `'.ORACLE_DB.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci');
    $pdo->exec('USE `'.ORACLE_DB.'`');

    $sql = (string) file_get_contents(ORACLE_DUMP_PATH);
    $sql = preg_replace('/^\s*--.*$/m', '', $sql) ?? $sql;
    $sql = preg_replace('/\/\*!?\d*.*?\*\//s', '', $sql) ?? $sql;
    // MySQL 8 menolak DEFAULT literal pada kolom TEXT (dump dibuat di MariaDB 10.4).
    $sql = preg_replace('/text NOT NULL DEFAULT \'\'/i', 'text NOT NULL', $sql) ?? $sql;
    $sql = preg_replace('/text DEFAULT \'\'/i', 'text NULL', $sql) ?? $sql;

    foreach (array_filter(array_map('trim', explode(';', $sql))) as $statement) {
        if ($statement === '' || str_starts_with($statement, 'SET ')) {
            continue;
        }
        $pdo->exec($statement);
    }
}
